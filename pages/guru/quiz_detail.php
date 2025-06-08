<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has appropriate role
checkAccess(['guru', 'kepsek', 'admin']);

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Quiz tidak ditemukan.');
    
    if ($_SESSION['user_role'] === 'guru') {
        header('Location: quizzes.php');
    } else if ($_SESSION['user_role'] === 'kepsek') {
        header('Location: ../kepsek/dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$quiz_id = sanitizeInput($_GET['id']);

// Get quiz details
$query_quiz = "SELECT t.*, m.judul as materi_judul, k.nama as kelas_nama, p.nama as guru_nama 
              FROM tugas t 
              JOIN materi_coding m ON t.materi_id = m.id 
              JOIN kelas k ON t.kelas_id = k.id 
              JOIN pengguna p ON t.dibuat_oleh = p.id
              WHERE t.id = '$quiz_id'";
$result_quiz = mysqli_query($conn, $query_quiz);

if (mysqli_num_rows($result_quiz) === 0) {
    setFlashMessage('error', 'Quiz tidak ditemukan.');
    
    if ($_SESSION['user_role'] === 'guru') {
        header('Location: quizzes.php');
    } else if ($_SESSION['user_role'] === 'kepsek') {
        header('Location: ../kepsek/dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$quiz = mysqli_fetch_assoc($result_quiz);

// For teacher role, verify the quiz belongs to this teacher
if ($_SESSION['user_role'] === 'guru' && $quiz['dibuat_oleh'] !== $_SESSION['user_id']) {
    setFlashMessage('error', 'Anda tidak memiliki akses untuk melihat quiz ini.');
    header('Location: quizzes.php');
    exit;
}

// Process form submission for grading
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'grade_quiz' && $_SESSION['user_role'] === 'guru') {
        $nilai_tugas_id = sanitizeInput($_POST['nilai_tugas_id']);
        $nilai = (int)sanitizeInput($_POST['nilai']);
        $feedback = sanitizeInput($_POST['feedback'], false);
        
        // Update nilai_tugas
        $query = "UPDATE nilai_tugas SET 
                 nilai = $nilai, 
                 feedback = '$feedback',
                 dinilai_oleh = '{$_SESSION['user_id']}',
                 tanggal_dinilai = CURDATE()
                 WHERE id = '$nilai_tugas_id'";
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Nilai quiz berhasil diperbarui.');
            
            // Get student info for logging
            $query_student = "SELECT siswa_id FROM nilai_tugas WHERE id = '$nilai_tugas_id'";
            $result_student = mysqli_query($conn, $query_student);
            $student_id = mysqli_fetch_assoc($result_student)['siswa_id'];
            
            // Log activity
            logActivity($_SESSION['user_id'], 'nilai_tugas', "Guru menilai quiz {$quiz['judul']} untuk siswa dengan ID $student_id");
        } else {
            setFlashMessage('error', 'Gagal memperbarui nilai: ' . mysqli_error($conn));
        }
        
        // Redirect to refresh the page
        header('Location: quiz_detail.php?id=' . $quiz_id);
        exit;
    }
}

// Get all questions for this quiz
$query_questions = "SELECT * FROM soal_quiz WHERE tugas_id = '$quiz_id' ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);
$questions = [];
$total_bobot = 0;

while ($row = mysqli_fetch_assoc($result_questions)) {
    $questions[$row['id']] = $row;
    $total_bobot += $row['bobot'];
}

// Get all students who have taken this quiz
$query_attempts = "SELECT nt.*, p.nama as siswa_nama, p.nisn
                 FROM nilai_tugas nt
                 JOIN pengguna p ON nt.siswa_id = p.id
                 WHERE nt.tugas_id = '$quiz_id'
                 ORDER BY nt.tanggal_pengumpulan DESC";
$result_attempts = mysqli_query($conn, $query_attempts);

// Calculate statistics
$total_attempts = mysqli_num_rows($result_attempts);
$total_graded = 0;
$total_score = 0;
$min_score = 100;
$max_score = 0;
$score_distribution = [0, 0, 0, 0, 0]; // <60, 60-69, 70-79, 80-89, 90-100

$attempts = [];
while ($attempt = mysqli_fetch_assoc($result_attempts)) {
    $attempts[] = $attempt;
    
    if ($attempt['nilai'] !== null) {
        $total_graded++;
        $total_score += $attempt['nilai'];
        
        $min_score = min($min_score, $attempt['nilai']);
        $max_score = max($max_score, $attempt['nilai']);
        
        // Update score distribution
        if ($attempt['nilai'] < 60) {
            $score_distribution[0]++;
        } elseif ($attempt['nilai'] < 70) {
            $score_distribution[1]++;
        } elseif ($attempt['nilai'] < 80) {
            $score_distribution[2]++;
        } elseif ($attempt['nilai'] < 90) {
            $score_distribution[3]++;
        } else {
            $score_distribution[4]++;
        }
    }
}

$avg_score = $total_graded > 0 ? round($total_score / $total_graded, 1) : 0;
$min_score = $total_graded > 0 ? $min_score : 0;
$max_score = $total_graded > 0 ? $max_score : 0;

// Get specific student's attempt if requested
$selected_attempt = null;
$student_answers = [];

if (isset($_GET['student_id'])) {
    $student_id = sanitizeInput($_GET['student_id']);
    
    // Find the attempt for this student
    foreach ($attempts as $attempt) {
        if ($attempt['siswa_id'] === $student_id) {
            $selected_attempt = $attempt;
            break;
        }
    }
    
    if ($selected_attempt) {
        // Get student's answers
        $query_answers = "SELECT js.*, sq.jenis, pj.teks as pilihan_teks, pj.is_benar
                        FROM jawaban_siswa js
                        JOIN soal_quiz sq ON js.soal_id = sq.id
                        LEFT JOIN pilihan_jawaban pj ON js.pilihan_id = pj.id
                        WHERE js.nilai_tugas_id = '{$selected_attempt['id']}'";
        $result_answers = mysqli_query($conn, $query_answers);
        
        while ($answer = mysqli_fetch_assoc($result_answers)) {
            $student_answers[$answer['soal_id']] = $answer;
        }
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <?php if ($_SESSION['user_role'] === 'guru'): ?>
            <a href="quizzes.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Quiz
            </a>
        <?php elseif ($_SESSION['user_role'] === 'kepsek'): ?>
            <a href="../kepsek/dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        <?php else: ?>
            <a href="../admin/dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        <?php endif; ?>
        
        <h1 class="h3">Detail Quiz: <?php echo $quiz['judul']; ?></h1>
        <p>
            <span class="badge bg-info me-2"><?php echo $quiz['kelas_nama']; ?></span>
            <span class="badge bg-secondary me-2">Materi: <?php echo $quiz['materi_judul']; ?></span>
            <?php if ($_SESSION['user_role'] !== 'guru'): ?>
                <span class="badge bg-success me-2">Guru: <?php echo $quiz['guru_nama']; ?></span>
            <?php endif; ?>
            <?php if ($quiz['tanggal_deadline']): ?>
                <span class="badge bg-warning">Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?></span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-4">
            <!-- Quiz Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informasi Quiz</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Deskripsi:</strong>
                        <p class="mt-2"><?php echo $quiz['deskripsi']; ?></p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Jumlah Soal</h6>
                                <h4><?php echo count($questions); ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Total Bobot</h6>
                                <h4><?php echo $total_bobot; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Tanggal Dibuat</h6>
                                <p><?php echo formatDate($quiz['tanggal_dibuat']); ?></p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Deadline</h6>
                                <p><?php echo $quiz['tanggal_deadline'] ? formatDate($quiz['tanggal_deadline']) : 'Tidak ada'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quiz Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistik Quiz</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Jumlah Pengerjaan</h6>
                                <h4><?php echo $total_attempts; ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h6>Telah Dinilai</h6>
                                <h4><?php echo $total_graded; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-4">
                            <div class="text-center">
                                <h6>Nilai Rata-rata</h6>
                                <h4><?php echo $avg_score; ?></h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h6>Nilai Minimum</h6>
                                <h4><?php echo $min_score; ?></h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h6>Nilai Maksimum</h6>
                                <h4><?php echo $max_score; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Score Distribution Chart -->
                    <?php if ($total_graded > 0): ?>
                        <h6 class="text-center mb-3">Distribusi Nilai</h6>
                        <canvas id="scoreDistributionChart" height="200"></canvas>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            Belum ada data nilai yang tersedia.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Students List -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Pengerjaan Siswa</h5>
                    <span class="badge bg-primary"><?php echo $total_attempts; ?> Siswa</span>
                </div>
                <div class="card-body">
                    <?php if ($total_attempts > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>NISN</th>
                                        <th>Tanggal Pengerjaan</th>
                                        <th>Nilai</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attempts as $attempt): ?>
                                        <tr <?php echo ($selected_attempt && $selected_attempt['id'] === $attempt['id']) ? 'class="table-active"' : ''; ?>>
                                            <td><?php echo $attempt['siswa_nama']; ?></td>
                                            <td><?php echo $attempt['nisn']; ?></td>
                                            <td><?php echo formatDate($attempt['tanggal_pengumpulan']); ?></td>
                                            <td>
                                                <?php if ($attempt['nilai'] !== null): ?>
                                                    <span class="badge bg-<?php echo scoreColor($attempt['nilai']); ?>">
                                                        <?php echo $attempt['nilai']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Belum dinilai</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['dinilai_oleh']): ?>
                                                    <span class="badge bg-success">Telah dinilai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Menunggu penilaian</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="quiz_detail.php?id=<?php echo $quiz_id; ?>&student_id=<?php echo $attempt['siswa_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada siswa yang mengerjakan quiz ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($selected_attempt): ?>
                <!-- Student's Answers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Jawaban <?php echo $selected_attempt['siswa_nama']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-1">
                                        <strong>Tanggal Pengerjaan:</strong> <?php echo formatDate($selected_attempt['tanggal_pengumpulan']); ?>
                                    </p>
                                    <?php if ($selected_attempt['tanggal_dinilai']): ?>
                                        <p class="mb-1">
                                            <strong>Tanggal Penilaian:</strong> <?php echo formatDate($selected_attempt['tanggal_dinilai']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($selected_attempt['nilai'] !== null): ?>
                                        <div class="text-center">
                                            <h4 class="mb-0">Nilai: <span class="text-<?php echo scoreColor($selected_attempt['nilai'], true); ?>">
                                                <?php echo $selected_attempt['nilai']; ?>
                                            </span></h4>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($_SESSION['user_role'] === 'guru'): ?>
                            <!-- Grading Form -->
                            <form method="POST" action="quiz_detail.php?id=<?php echo $quiz_id; ?>&student_id=<?php echo $selected_attempt['siswa_id']; ?>">
                                <input type="hidden" name="action" value="grade_quiz">
                                <input type="hidden" name="nilai_tugas_id" value="<?php echo $selected_attempt['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="nilai" class="form-label">Nilai Akhir</label>
                                        <input type="number" class="form-control" id="nilai" name="nilai" min="0" max="100" 
                                               value="<?php echo $selected_attempt['nilai'] ?? ''; ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="feedback" class="form-label">Feedback</label>
                                        <textarea class="form-control" id="feedback" name="feedback" rows="3"><?php echo $selected_attempt['feedback'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Simpan Penilaian
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($selected_attempt['feedback']): ?>
                            <!-- Display Feedback -->
                            <div class="alert alert-info mb-4">
                                <h6>Feedback dari Guru:</h6>
                                <p class="mb-0"><?php echo $selected_attempt['feedback']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Answers -->
                        <div class="accordion" id="accordionAnswers">
                            <?php $question_number = 1; ?>
                            <?php foreach ($questions as $question): ?>
                                <div class="accordion-item mb-3 border">
                                    <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $question['id']; ?>" 
                                                aria-expanded="false" aria-controls="collapse<?php echo $question['id']; ?>">
                                            <div class="d-flex align-items-center w-100">
                                                <span class="badge bg-secondary me-2"><?php echo $question_number++; ?></span>
                                                <div class="me-auto">
                                                    <?php echo limitText(strip_tags($question['pertanyaan']), 70); ?>
                                                </div>
                                                <div class="d-flex align-items-center ms-3">
                                                    <?php if (isset($student_answers[$question['id']]) && $student_answers[$question['id']]['nilai_per_soal'] !== null): ?>
                                                        <span class="badge <?php echo ($student_answers[$question['id']]['nilai_per_soal'] > 0) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                            <?php echo $student_answers[$question['id']]['nilai_per_soal']; ?>/<?php echo $question['bobot']; ?> poin
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning me-2">Belum dinilai</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $question['id']; ?>" class="accordion-collapse collapse" 
                                         aria-labelledby="heading<?php echo $question['id']; ?>" 
                                         data-bs-parent="#accordionAnswers">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <h6>Pertanyaan:</h6>
                                                <div class="question-content p-3 bg-light rounded mb-3">
                                                    <?php echo $question['pertanyaan']; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <h6>Jawaban Siswa:</h6>
                                                <?php if (isset($student_answers[$question['id']])): ?>
                                                    <?php if ($question['jenis'] === 'pilihan_ganda'): ?>
                                                        <div class="p-3 <?php echo $student_answers[$question['id']]['is_benar'] ? 'bg-success text-white' : 'bg-danger text-white'; ?> rounded">
                                                            <?php echo $student_answers[$question['id']]['pilihan_teks'] ?? 'Tidak menjawab'; ?>
                                                            <?php if ($student_answers[$question['id']]['is_benar']): ?>
                                                                <i class="fas fa-check-circle float-end"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-times-circle float-end"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="p-3 bg-light rounded <?php echo $question['jenis'] === 'coding' ? 'font-monospace' : ''; ?>">
                                                            <?php echo $student_answers[$question['id']]['jawaban'] ?? 'Tidak menjawab'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="alert alert-warning">
                                                        Siswa tidak menjawab pertanyaan ini.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($question['jenis'] === 'pilihan_ganda'): ?>
                                                <?php
                                                // Get correct answer for multiple choice
                                                $query_correct = "SELECT * FROM pilihan_jawaban WHERE soal_id = '{$question['id']}' AND is_benar = 1";
                                                $result_correct = mysqli_query($conn, $query_correct);
                                                $correct_answer = mysqli_fetch_assoc($result_correct);
                                                ?>
                                                <div class="mb-3">
                                                    <h6>Jawaban Benar:</h6>
                                                    <div class="p-3 bg-success text-white rounded">
                                                        <?php echo $correct_answer['teks']; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($total_graded > 0): ?>
    // Score Distribution Chart
    var ctxScore = document.getElementById('scoreDistributionChart').getContext('2d');
    var scoreChart = new Chart(ctxScore, {
        type: 'bar',
        data: {
            labels: ['<60', '60-69', '70-79', '80-89', '90-100'],
            datasets: [{
                label: 'Jumlah Siswa',
                data: <?php echo json_encode($score_distribution); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
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
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
.question-content {
    line-height: 1.6;
}

.question-content img {
    max-width: 100%;
    height: auto;
}

.question-content pre {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}

.question-content code {
    background-color: #f1f1f1;
    padding: 2px 4px;
    border-radius: 4px;
}

.font-monospace {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>

<?php
// Helper function to determine score color
function scoreColor($score, $text = false) {
    $prefix = $text ? '' : '';
    if ($score >= 90) {
        return $prefix . 'success';
    } elseif ($score >= 80) {
        return $prefix . 'info';
    } elseif ($score >= 70) {
        return $prefix . 'primary';
    } elseif ($score >= 60) {
        return $prefix . 'warning';
    } else {
        return $prefix . 'danger';
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 