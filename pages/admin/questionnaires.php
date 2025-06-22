<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new questionnaire
    if (isset($_POST['action']) && $_POST['action'] === 'create_questionnaire') {
        $kelas_id = sanitizeInput($_POST['kelas_id']);
        $judul = sanitizeInput($_POST['judul']);
        $deskripsi = sanitizeInput($_POST['deskripsi'], false);
        
        // Generate ID
        $questionnaire_id = generateID('K', 'kuesioner', 'id');
        
        // Insert questionnaire
        $query = "INSERT INTO kuesioner (id, judul, deskripsi, kelas_id, dibuat_oleh, tanggal_dibuat) 
                 VALUES ('$questionnaire_id', '$judul', '$deskripsi', '$kelas_id', '{$_SESSION['user_id']}', CURDATE())";
                 
        if (mysqli_query($conn, $query)) {
            // Log activity
            logActivity($_SESSION['user_id'], 'tambah_materi', "Admin membuat kuesioner baru: $judul");
            
            setFlashMessage('success', 'Kuesioner berhasil dibuat.');
            header('Location: questionnaire_edit.php?id=' . $questionnaire_id);
            exit;
        } else {
            setFlashMessage('error', 'Gagal membuat kuesioner: ' . mysqli_error($conn));
        }
    }
    
    // Delete questionnaire
    if (isset($_POST['action']) && $_POST['action'] === 'delete_questionnaire') {
        $questionnaire_id = sanitizeInput($_POST['questionnaire_id']);
        
        // Check if questionnaire exists
        $query_check = "SELECT * FROM kuesioner WHERE id = '$questionnaire_id'";
        $result_check = mysqli_query($conn, $query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $questionnaire = mysqli_fetch_assoc($result_check);
            
            // Delete questions first (this will cascade delete answers)
            $query_delete_questions = "DELETE FROM pertanyaan_kuesioner WHERE kuesioner_id = '$questionnaire_id'";
            mysqli_query($conn, $query_delete_questions);
            
            // Delete questionnaire
            $query_delete = "DELETE FROM kuesioner WHERE id = '$questionnaire_id'";
            
            if (mysqli_query($conn, $query_delete)) {
                // Log activity
                logActivity($_SESSION['user_id'], 'hapus_materi', "Admin menghapus kuesioner: {$questionnaire['judul']}");
                
                setFlashMessage('success', 'Kuesioner berhasil dihapus.');
            } else {
                setFlashMessage('error', 'Gagal menghapus kuesioner: ' . mysqli_error($conn));
            }
        } else {
            setFlashMessage('error', 'Kuesioner tidak ditemukan.');
        }
        
        header('Location: questionnaires.php');
        exit;
    }
}

// Get filter values
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';

// Get list of classes for filter
$query_classes = "SELECT id, nama, tahun_ajaran FROM kelas ORDER BY nama ASC";
$result_classes = mysqli_query($conn, $query_classes);
$classes = [];

while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes[] = $row;
}

// Get questionnaires
$query_questionnaires = "SELECT k.*, p.nama as dibuat_oleh_nama, kl.nama as kelas_nama,
                       (SELECT COUNT(DISTINCT jk.siswa_id) FROM jawaban_kuesioner jk 
                        JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                        WHERE pk.kuesioner_id = k.id) as jumlah_responden
                       FROM kuesioner k
                       JOIN pengguna p ON k.dibuat_oleh = p.id
                       JOIN kelas kl ON k.kelas_id = kl.id
                       WHERE 1=1 ";

// Apply class filter if selected
if (!empty($class_filter)) {
    $query_questionnaires .= "AND k.kelas_id = '$class_filter' ";
}

// Order by date
$query_questionnaires .= "ORDER BY k.tanggal_dibuat DESC";

// Execute query
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Calculate overall statistics
$total_questionnaires = mysqli_num_rows($result_questionnaires);

// Calculate average ratings
$query_avg_ratings = "SELECT 
                     ROUND(AVG(CASE WHEN pk.pertanyaan LIKE '%kesulitan%' THEN jk.jawaban ELSE NULL END), 1) as avg_difficulty,
                     ROUND(AVG(CASE WHEN pk.pertanyaan LIKE '%kejelasan%' THEN jk.jawaban ELSE NULL END), 1) as avg_clarity
                     FROM jawaban_kuesioner jk
                     JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                     JOIN kuesioner k ON pk.kuesioner_id = k.id
                     WHERE jk.jawaban REGEXP '^[0-9]+$'";

// Apply class filter if selected
if (!empty($class_filter)) {
    $query_avg_ratings .= " AND k.kelas_id = '$class_filter'";
}

$result_avg_ratings = mysqli_query($conn, $query_avg_ratings);
$avg_ratings = mysqli_fetch_assoc($result_avg_ratings);

// Get total respondents
$query_respondents = "SELECT COUNT(DISTINCT jk.siswa_id) as total_respondents
                    FROM jawaban_kuesioner jk
                    JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                    JOIN kuesioner k ON pk.kuesioner_id = k.id";

// Apply class filter if selected
if (!empty($class_filter)) {
    $query_respondents .= " WHERE k.kelas_id = '$class_filter'";
}

$result_respondents = mysqli_query($conn, $query_respondents);
$total_respondents = mysqli_fetch_assoc($result_respondents)['total_respondents'];

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manajemen Kuesioner</h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuestionnaireModal">
                <i class="fas fa-plus me-2"></i> Buat Kuesioner Baru
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Kuesioner</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="questionnaires.php" class="row g-3">
                <div class="col-md-4">
                    <label for="class" class="form-label">Kelas</label>
                    <select class="form-select" id="class" name="class" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_filter === $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Kuesioner</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_questionnaires; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Responden</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_respondents; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Rata-rata Tingkat Kesulitan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $avg_ratings['avg_difficulty'] ?? '-'; ?> / 5
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Rata-rata Kejelasan Materi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $avg_ratings['avg_clarity'] ?? '-'; ?> / 5
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rating Distribution Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Distribusi Tingkat Kesulitan</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get difficulty rating distribution
                    $query_difficulty = "SELECT jk.jawaban, COUNT(*) as count
                                      FROM jawaban_kuesioner jk
                                      JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                                      JOIN kuesioner k ON pk.kuesioner_id = k.id
                                      WHERE pk.pertanyaan LIKE '%kesulitan%'
                                      AND jk.jawaban REGEXP '^[0-9]+$'";
                    
                    if (!empty($class_filter)) {
                        $query_difficulty .= " AND k.kelas_id = '$class_filter'";
                    }
                    
                    $query_difficulty .= " GROUP BY jk.jawaban ORDER BY jk.jawaban";
                    $result_difficulty = mysqli_query($conn, $query_difficulty);
                    
                    $difficulty_labels = ['1 (Sangat Mudah)', '2', '3', '4', '5 (Sangat Sulit)'];
                    $difficulty_data = array_fill(0, 5, 0);
                    
                    while ($row = mysqli_fetch_assoc($result_difficulty)) {
                        $index = (int)$row['jawaban'] - 1;
                        if ($index >= 0 && $index < 5) {
                            $difficulty_data[$index] = (int)$row['count'];
                        }
                    }
                    ?>
                    <canvas id="difficultyChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Distribusi Kejelasan Materi</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get clarity rating distribution
                    $query_clarity = "SELECT jk.jawaban, COUNT(*) as count
                                   FROM jawaban_kuesioner jk
                                   JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                                   JOIN kuesioner k ON pk.kuesioner_id = k.id
                                   WHERE pk.pertanyaan LIKE '%kejelasan%'
                                   AND jk.jawaban REGEXP '^[0-9]+$'";
                    
                    if (!empty($class_filter)) {
                        $query_clarity .= " AND k.kelas_id = '$class_filter'";
                    }
                    
                    $query_clarity .= " GROUP BY jk.jawaban ORDER BY jk.jawaban";
                    $result_clarity = mysqli_query($conn, $query_clarity);
                    
                    $clarity_labels = ['1 (Tidak Jelas)', '2', '3', '4', '5 (Sangat Jelas)'];
                    $clarity_data = array_fill(0, 5, 0);
                    
                    while ($row = mysqli_fetch_assoc($result_clarity)) {
                        $index = (int)$row['jawaban'] - 1;
                        if ($index >= 0 && $index < 5) {
                            $clarity_data[$index] = (int)$row['count'];
                        }
                    }
                    ?>
                    <canvas id="clarityChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Questionnaires Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Kuesioner</h5>
            <span class="badge bg-primary"><?php echo $total_questionnaires; ?> Kuesioner</span>
        </div>
        <div class="card-body">
            <?php if ($total_questionnaires > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Kelas</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal</th>
                                <th>Responden</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($result_questionnaires, 0); ?>
                            <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                                <tr>
                                    <td><?php echo $questionnaire['judul']; ?></td>
                                    <td><?php echo $questionnaire['kelas_nama']; ?></td>
                                    <td><?php echo $questionnaire['dibuat_oleh_nama']; ?></td>
                                    <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $questionnaire['jumlah_responden']; ?> siswa</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="showQuestionnaire('<?php echo $questionnaire['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="questionnaire_edit.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete('<?php echo $questionnaire['id']; ?>', '<?php echo $questionnaire['judul']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <p class="lead">Tidak ada data kuesioner yang ditemukan.</p>
                    <?php if (!empty($class_filter)): ?>
                        <a href="questionnaires.php" class="btn btn-outline-primary">
                            <i class="fas fa-redo me-2"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Student Feedback -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Umpan Balik Siswa Terbaru</h5>
        </div>
        <div class="card-body">
            <?php
            // Get recent feedback (text answers)
            $query_feedback = "SELECT jk.jawaban, jk.tanggal_jawab, p.nama as siswa_nama, k.judul as kuesioner_judul
                            FROM jawaban_kuesioner jk
                            JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                            JOIN kuesioner k ON pk.kuesioner_id = k.id
                            JOIN pengguna p ON jk.siswa_id = p.id
                            WHERE pk.jenis = 'text'
                            AND jk.jawaban != ''";
            
            if (!empty($class_filter)) {
                $query_feedback .= " AND k.kelas_id = '$class_filter'";
            }
            
            $query_feedback .= " ORDER BY jk.tanggal_jawab DESC LIMIT 10";
            $result_feedback = mysqli_query($conn, $query_feedback);
            ?>
            
            <?php if (mysqli_num_rows($result_feedback) > 0): ?>
                <div class="list-group">
                    <?php while ($feedback = mysqli_fetch_assoc($result_feedback)): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo $feedback['siswa_nama']; ?></h6>
                                <small><?php echo formatDate($feedback['tanggal_jawab']); ?></small>
                            </div>
                            <p class="mb-1"><?php echo $feedback['jawaban']; ?></p>
                            <small class="text-muted">Kuesioner: <?php echo $feedback['kuesioner_judul']; ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">Belum ada umpan balik teks dari siswa.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Questionnaire Detail Modal -->
<div class="modal fade" id="questionnaireModal" tabindex="-1" aria-labelledby="questionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionnaireModalLabel">Detail Kuesioner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center" id="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Memuat data kuesioner...</p>
                </div>
                <div id="questionnaireData" style="display: none;">
                    <h5 id="questionnaireTitle" class="mb-3"></h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Kelas:</strong> <span id="questionnaireClass"></span></p>
                            <p><strong>Dibuat oleh:</strong> <span id="questionnaireCreator"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Tanggal dibuat:</strong> <span id="questionnaireDate"></span></p>
                            <p><strong>Jumlah responden:</strong> <span id="questionnaireRespondents"></span></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div id="questionnaireResults">
                        <!-- Results will be displayed here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Difficulty Chart
    var ctxDifficulty = document.getElementById('difficultyChart').getContext('2d');
    var difficultyChart = new Chart(ctxDifficulty, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($difficulty_labels); ?>,
            datasets: [{
                label: 'Jumlah Responden',
                data: <?php echo json_encode($difficulty_data); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Tingkat Kesulitan Materi (1-5)'
                }
            }
        }
    });
    
    // Clarity Chart
    var ctxClarity = document.getElementById('clarityChart').getContext('2d');
    var clarityChart = new Chart(ctxClarity, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($clarity_labels); ?>,
            datasets: [{
                label: 'Jumlah Responden',
                data: <?php echo json_encode($clarity_data); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Kejelasan Materi (1-5)'
                }
            }
        }
    });
});

// Function to show questionnaire details
function showQuestionnaire(id) {
    const modal = new bootstrap.Modal(document.getElementById('questionnaireModal'));
    modal.show();
    
    // Show loading, hide data
    document.getElementById('loading').style.display = 'block';
    document.getElementById('questionnaireData').style.display = 'none';
    
    // Fetch questionnaire details via AJAX
    fetch(`get_questionnaire.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fill in questionnaire details
                document.getElementById('questionnaireTitle').textContent = data.questionnaire.judul;
                document.getElementById('questionnaireClass').textContent = data.questionnaire.kelas_nama;
                document.getElementById('questionnaireCreator').textContent = data.questionnaire.dibuat_oleh_nama;
                document.getElementById('questionnaireDate').textContent = data.questionnaire.tanggal_dibuat;
                document.getElementById('questionnaireRespondents').textContent = data.questionnaire.jumlah_responden + ' siswa';
                
                // Clear previous results
                const resultsContainer = document.getElementById('questionnaireResults');
                resultsContainer.innerHTML = '';
                
                // Add results for each question
                data.questions.forEach(question => {
                    const questionDiv = document.createElement('div');
                    questionDiv.className = 'mb-4';
                    
                    const questionTitle = document.createElement('h6');
                    questionTitle.textContent = question.pertanyaan;
                    questionDiv.appendChild(questionTitle);
                    
                    if (question.jenis === 'text') {
                        // For text questions, show all answers
                        if (question.answers.length > 0) {
                            const answersList = document.createElement('ul');
                            answersList.className = 'list-group mt-2';
                            
                            question.answers.forEach(answer => {
                                const answerItem = document.createElement('li');
                                answerItem.className = 'list-group-item';
                                answerItem.innerHTML = `
                                    <strong>${answer.siswa_nama}:</strong> ${answer.jawaban}
                                    <small class="d-block text-muted">${answer.tanggal_jawab}</small>
                                `;
                                answersList.appendChild(answerItem);
                            });
                            
                            questionDiv.appendChild(answersList);
                        } else {
                            const noAnswers = document.createElement('p');
                            noAnswers.className = 'text-muted';
                            noAnswers.textContent = 'Belum ada jawaban.';
                            questionDiv.appendChild(noAnswers);
                        }
                    } else if (question.jenis === 'skala') {
                        // For scale questions, show distribution
                        const chartCanvas = document.createElement('canvas');
                        chartCanvas.id = `chart-${question.id}`;
                        chartCanvas.style.maxHeight = '200px';
                        questionDiv.appendChild(chartCanvas);
                        
                        // Count answers by value
                        const answerCounts = [0, 0, 0, 0, 0]; // for values 1-5
                        question.answers.forEach(answer => {
                            const value = parseInt(answer.jawaban);
                            if (value >= 1 && value <= 5) {
                                answerCounts[value - 1]++;
                            }
                        });
                        
                        // Create chart
                        setTimeout(() => {
                            const ctx = document.getElementById(`chart-${question.id}`).getContext('2d');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['1', '2', '3', '4', '5'],
                                    datasets: [{
                                        label: 'Jumlah Jawaban',
                                        data: answerCounts,
                                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                precision: 0
                                            }
                                        }
                                    }
                                }
                            });
                        }, 100);
                        
                        // Add average score
                        let sum = 0;
                        question.answers.forEach(answer => {
                            sum += parseInt(answer.jawaban);
                        });
                        const avg = question.answers.length > 0 ? (sum / question.answers.length).toFixed(1) : '-';
                        
                        const avgScore = document.createElement('p');
                        avgScore.className = 'mt-2';
                        avgScore.innerHTML = `<strong>Rata-rata:</strong> ${avg} / 5`;
                        questionDiv.appendChild(avgScore);
                    }
                    
                    resultsContainer.appendChild(questionDiv);
                    
                    // Add separator except for last question
                    if (question !== data.questions[data.questions.length - 1]) {
                        const hr = document.createElement('hr');
                        resultsContainer.appendChild(hr);
                    }
                });
                
                // Hide loading, show data
                document.getElementById('loading').style.display = 'none';
                document.getElementById('questionnaireData').style.display = 'block';
            } else {
                alert('Error fetching questionnaire data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching the questionnaire data.');
        });
}
</script>

<!-- Create Questionnaire Modal -->
<div class="modal fade" id="createQuestionnaireModal" tabindex="-1" aria-labelledby="createQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createQuestionnaireModalLabel">Buat Kuesioner Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="questionnaires.php">
                <input type="hidden" name="action" value="create_questionnaire">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="kelas_id" class="form-label">Kelas</label>
                        <select class="form-select" id="kelas_id" name="kelas_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php 
                            mysqli_data_seek($result_classes, 0);
                            while ($class = mysqli_fetch_assoc($result_classes)): 
                            ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Kuesioner</label>
                        <input type="text" class="form-control" id="judul" name="judul" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Kuesioner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Questionnaire Modal -->
<div class="modal fade" id="deleteQuestionnaireModal" tabindex="-1" aria-labelledby="deleteQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteQuestionnaireModalLabel">Hapus Kuesioner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus kuesioner <strong id="delete-questionnaire-title"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus semua data terkait kuesioner ini.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="questionnaires.php">
                    <input type="hidden" name="action" value="delete_questionnaire">
                    <input type="hidden" name="questionnaire_id" id="delete-questionnaire-id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Function to confirm delete
function confirmDelete(questionnaireId, questionnaireTitle) {
    document.getElementById('delete-questionnaire-id').value = questionnaireId;
    document.getElementById('delete-questionnaire-title').textContent = questionnaireTitle;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteQuestionnaireModal'));
    deleteModal.show();
}
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<?php include_once '../../includes/footer.php'; ?> 