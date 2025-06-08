<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Get classes where this teacher has created materials or quizzes
$teacher_id = $_SESSION['user_id'];
$query_classes = "SELECT DISTINCT k.id, k.nama, k.tahun_ajaran,
                (SELECT COUNT(*) FROM pengguna WHERE kelas_id = k.id AND tipe_pengguna = 'siswa') as jumlah_siswa
                FROM kelas k 
                JOIN tugas t ON t.kelas_id = k.id
                WHERE t.dibuat_oleh = '$teacher_id'
                ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

// Get filter values
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$filter_applied = !empty($class_filter);

// Get class details if filter is applied
$class_name = '';
if ($filter_applied) {
    $query_class_name = "SELECT nama, tahun_ajaran FROM kelas WHERE id = '$class_filter'";
    $result_class_name = mysqli_query($conn, $query_class_name);
    if (mysqli_num_rows($result_class_name) > 0) {
        $class_data = mysqli_fetch_assoc($result_class_name);
        $class_name = $class_data['nama'] . ' (' . $class_data['tahun_ajaran'] . ')';
    }
}

// Get students in the selected class
$students = [];
if ($filter_applied) {
    $query_students = "SELECT id, nama, nisn, email FROM pengguna 
                      WHERE kelas_id = '$class_filter' AND tipe_pengguna = 'siswa' 
                      ORDER BY nama ASC";
    $result_students = mysqli_query($conn, $query_students);
    
    while ($row = mysqli_fetch_assoc($result_students)) {
        $students[$row['id']] = $row;
    }
}

// Get quizzes for the selected class created by this teacher
$quizzes = [];
if ($filter_applied) {
    $query_quizzes = "SELECT t.id, t.judul, t.tanggal_dibuat, m.judul as materi_judul,
                     (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id) as jumlah_pengerjaan,
                     (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as jumlah_dinilai,
                     (SELECT AVG(nilai) FROM nilai_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as rata_nilai
                     FROM tugas t
                     JOIN materi_coding m ON t.materi_id = m.id
                     WHERE t.kelas_id = '$class_filter' AND t.dibuat_oleh = '$teacher_id'
                     ORDER BY t.tanggal_dibuat DESC";
    $result_quizzes = mysqli_query($conn, $query_quizzes);
    
    while ($row = mysqli_fetch_assoc($result_quizzes)) {
        $quizzes[$row['id']] = $row;
    }
}

// Get all grades for the selected class
$grades = [];
$student_averages = [];
$quiz_data = [];

if ($filter_applied && !empty($students) && !empty($quizzes)) {
    // Inisialisasi quiz_data untuk semua quiz
    foreach ($quizzes as $quiz_id => $quiz) {
        $quiz_data[$quiz_id] = [
            'label' => limitText($quiz['judul'], 30),
            'scores' => [],
            'average' => 0,
            'total' => 0,
            'count' => 0
        ];
    }
    
    // Get all grades at once
    $query_grades = "SELECT nt.*, t.judul as quiz_judul
                    FROM nilai_tugas nt
                    JOIN tugas t ON nt.tugas_id = t.id
                    WHERE t.kelas_id = '$class_filter' AND t.dibuat_oleh = '$teacher_id'
                    ORDER BY nt.tanggal_pengumpulan DESC";
    $result_grades = mysqli_query($conn, $query_grades);
    
    // Organize grades by student and quiz
    while ($grade = mysqli_fetch_assoc($result_grades)) {
        $student_id = $grade['siswa_id'];
        $quiz_id = $grade['tugas_id'];
        
        // Skip if student or quiz is not in our filtered lists
        if (!isset($students[$student_id]) || !isset($quizzes[$quiz_id])) {
            continue;
        }
        
        // Store grade
        $grades[$student_id][$quiz_id] = $grade;
        
        // Calculate student averages
        if ($grade['nilai'] !== null) {
            if (!isset($student_averages[$student_id])) {
                $student_averages[$student_id] = ['total' => 0, 'count' => 0];
            }
            $student_averages[$student_id]['total'] += $grade['nilai'];
            $student_averages[$student_id]['count']++;
        }
        
        // Collect quiz data for chart
        if ($grade['nilai'] !== null) {
            $quiz_data[$quiz_id]['scores'][] = $grade['nilai'];
            $quiz_data[$quiz_id]['total'] += $grade['nilai'];
            $quiz_data[$quiz_id]['count']++;
        }
    }
    
    // Calculate quiz averages
    foreach ($quiz_data as $quiz_id => &$data) {
        if ($data['count'] > 0) {
            $data['average'] = round($data['total'] / $data['count'], 1);
        } else {
            $data['average'] = 0; // Pastikan kunci 'average' selalu ada
        }
    }
    unset($data); // Hapus referensi terakhir
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Analisis Nilai Siswa</h1>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Class Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Pilih Kelas</h5>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result_classes) > 0): ?>
                <form method="GET" action="grades.php" class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select" name="class" id="class">
                            <option value="">Pilih Kelas</option>
                            <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($class_filter === $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ') - ' . $class['jumlah_siswa'] . ' siswa'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Terapkan
                        </button>
                    </div>
                    <?php if ($filter_applied): ?>
                        <div class="col-md-2">
                            <a href="grades.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-2"></i> Reset
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Anda belum membuat quiz atau materi untuk kelas manapun.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($filter_applied): ?>
        <h4 class="mb-4">Analisis Nilai Kelas: <?php echo $class_name; ?></h4>
        
        <?php if (empty($students)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Tidak ada siswa yang terdaftar di kelas ini.
            </div>
        <?php elseif (empty($quizzes)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Anda belum membuat quiz untuk kelas ini.
            </div>
        <?php else: ?>
            <!-- Grade Statistics -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Statistik Nilai Quiz</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="quizChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Distribusi Nilai Rata-rata</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Calculate distribution
                            $avg_distribution = [0, 0, 0, 0, 0]; // <60, 60-69, 70-79, 80-89, 90-100
                            
                            foreach ($student_averages as $student_id => $data) {
                                if ($data['count'] > 0) {
                                    $avg = $data['total'] / $data['count'];
                                    
                                    if ($avg < 60) {
                                        $avg_distribution[0]++;
                                    } elseif ($avg < 70) {
                                        $avg_distribution[1]++;
                                    } elseif ($avg < 80) {
                                        $avg_distribution[2]++;
                                    } elseif ($avg < 90) {
                                        $avg_distribution[3]++;
                                    } else {
                                        $avg_distribution[4]++;
                                    }
                                }
                            }
                            ?>
                            
                            <canvas id="distributionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grade Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tabel Nilai Siswa</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle">No</th>
                                    <th rowspan="2" class="align-middle">Nama Siswa</th>
                                    <th rowspan="2" class="align-middle">NISN</th>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <th class="text-center"><?php echo limitText($quiz['judul'], 20); ?></th>
                                    <?php endforeach; ?>
                                    <th rowspan="2" class="align-middle text-center">Rata-rata</th>
                                </tr>
                                <tr>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <th class="text-center small"><?php echo formatDate($quiz['tanggal_dibuat']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($students as $student_id => $student): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo $student['nama']; ?></td>
                                        <td><?php echo $student['nisn']; ?></td>
                                        
                                        <?php foreach ($quizzes as $quiz_id => $quiz): ?>
                                            <td class="text-center">
                                                <?php if (isset($grades[$student_id][$quiz_id])): ?>
                                                    <?php if ($grades[$student_id][$quiz_id]['nilai'] !== null): ?>
                                                        <span class="badge bg-<?php echo scoreColor($grades[$student_id][$quiz_id]['nilai']); ?>">
                                                            <?php echo $grades[$student_id][$quiz_id]['nilai']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">BN</span>
                                                        <span class="d-none d-md-inline small text-muted">(Belum dinilai)</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">-</span>
                                                    <span class="d-none d-md-inline small text-muted">(Tidak mengerjakan)</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="text-center">
                                            <?php if (isset($student_averages[$student_id]) && $student_averages[$student_id]['count'] > 0): ?>
                                                <?php $avg = round($student_averages[$student_id]['total'] / $student_averages[$student_id]['count'], 1); ?>
                                                <span class="badge bg-<?php echo scoreColor($avg); ?> p-2">
                                                    <?php echo $avg; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th colspan="3" class="text-end">Rata-rata Quiz:</th>
                                    <?php foreach ($quizzes as $quiz_id => $quiz): ?>
                                        <th class="text-center">
                                            <?php if (isset($quiz_data[$quiz_id]) && $quiz_data[$quiz_id]['count'] > 0): ?>
                                                <?php echo isset($quiz_data[$quiz_id]['average']) ? $quiz_data[$quiz_id]['average'] : '-'; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Quiz Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Detail Quiz</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><?php echo $quiz['judul']; ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i> <?php echo formatDate($quiz['tanggal_dibuat']); ?><br>
                                                <i class="fas fa-book me-1"></i> Materi: <?php echo $quiz['materi_judul']; ?>
                                            </small>
                                        </p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['jumlah_pengerjaan']; ?></h6>
                                                <small class="text-muted">Mengerjakan</small>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['jumlah_dinilai']; ?></h6>
                                                <small class="text-muted">Dinilai</small>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['rata_nilai'] ? round($quiz['rata_nilai'], 1) : '-'; ?></h6>
                                                <small class="text-muted">Rata-rata</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <a href="quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-chart-bar me-1"></i> Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Silakan pilih kelas untuk melihat analisis nilai siswa.
        </div>
    <?php endif; ?>
</div>

<?php if ($filter_applied && !empty($students) && !empty($quizzes)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quiz Chart
    var ctxQuiz = document.getElementById('quizChart').getContext('2d');
    var quizChart = new Chart(ctxQuiz, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($quiz_data, 'label')); ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?php echo json_encode(array_column($quiz_data, 'average')); ?>,
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
                    max: 100
                }
            }
        }
    });
    
    // Distribution Chart
    var ctxDistribution = document.getElementById('distributionChart').getContext('2d');
    var distributionChart = new Chart(ctxDistribution, {
        type: 'pie',
        data: {
            labels: ['<60', '60-69', '70-79', '80-89', '90-100'],
            datasets: [{
                data: <?php echo json_encode($avg_distribution); ?>,
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
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
// Helper function to determine score color
function scoreColor($score) {
    if ($score >= 90) {
        return 'success';
    } elseif ($score >= 80) {
        return 'info';
    } elseif ($score >= 70) {
        return 'primary';
    } elseif ($score >= 60) {
        return 'warning';
    } else {
        return 'danger';
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 