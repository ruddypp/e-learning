<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has principal role
checkAccess(['kepsek']);

// Get all classes with additional information
$query_classes = "SELECT k.*, p.nama as wali_kelas_nama,
                (SELECT COUNT(*) FROM pengguna WHERE kelas_id = k.id AND tipe_pengguna = 'siswa') as jumlah_siswa,
                (SELECT COUNT(*) FROM materi_coding WHERE kelas_id = k.id) as jumlah_materi,
                (SELECT COUNT(*) FROM tugas WHERE kelas_id = k.id) as jumlah_quiz,
                (SELECT AVG(nt.nilai) FROM nilai_tugas nt 
                 JOIN tugas t ON nt.tugas_id = t.id 
                 WHERE t.kelas_id = k.id AND nt.nilai IS NOT NULL) as rata_nilai
                FROM kelas k
                LEFT JOIN pengguna p ON k.wali_kelas_id = p.id
                ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

// Get selected class details if ID is provided
$selected_class = null;
$class_students = [];
$class_materials = [];
$class_quizzes = [];

if (isset($_GET['id'])) {
    $class_id = sanitizeInput($_GET['id']);
    
    // Get class details
    $query_class = "SELECT k.*, p.nama as wali_kelas_nama, p.email as wali_kelas_email, p.nuptk
                   FROM kelas k
                   LEFT JOIN pengguna p ON k.wali_kelas_id = p.id
                   WHERE k.id = '$class_id'";
    $result_class = mysqli_query($conn, $query_class);
    
    if (mysqli_num_rows($result_class) > 0) {
        $selected_class = mysqli_fetch_assoc($result_class);
        
        // Get students in this class
        $query_students = "SELECT p.*, 
                         (SELECT COUNT(*) FROM nilai_tugas nt 
                          JOIN tugas t ON nt.tugas_id = t.id 
                          WHERE t.kelas_id = '$class_id' AND nt.siswa_id = p.id) as jumlah_quiz_dikerjakan,
                         (SELECT AVG(nt.nilai) FROM nilai_tugas nt 
                          JOIN tugas t ON nt.tugas_id = t.id 
                          WHERE t.kelas_id = '$class_id' AND nt.siswa_id = p.id AND nt.nilai IS NOT NULL) as rata_nilai
                         FROM pengguna p
                         WHERE p.kelas_id = '$class_id' AND p.tipe_pengguna = 'siswa'
                         ORDER BY p.nama ASC";
        $result_students = mysqli_query($conn, $query_students);
        
        while ($student = mysqli_fetch_assoc($result_students)) {
            $class_students[] = $student;
        }
        
        // Get materials for this class
        $query_materials = "SELECT m.*, p.nama as guru_nama
                          FROM materi_coding m
                          JOIN pengguna p ON m.dibuat_oleh = p.id
                          WHERE m.kelas_id = '$class_id'
                          ORDER BY m.tanggal_dibuat DESC
                          LIMIT 5";
        $result_materials = mysqli_query($conn, $query_materials);
        
        while ($material = mysqli_fetch_assoc($result_materials)) {
            $class_materials[] = $material;
        }
        
        // Get quizzes for this class
        $query_quizzes = "SELECT t.*, m.judul as materi_judul, p.nama as guru_nama,
                        (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id) as jumlah_dikerjakan,
                        (SELECT AVG(nilai) FROM nilai_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as rata_nilai
                        FROM tugas t
                        JOIN materi_coding m ON t.materi_id = m.id
                        JOIN pengguna p ON t.dibuat_oleh = p.id
                        WHERE t.kelas_id = '$class_id'
                        ORDER BY t.tanggal_dibuat DESC
                        LIMIT 5";
        $result_quizzes = mysqli_query($conn, $query_quizzes);
        
        while ($quiz = mysqli_fetch_assoc($result_quizzes)) {
            $class_quizzes[] = $quiz;
        }
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Daftar Kelas</h1>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <?php if ($selected_class): ?>
            <!-- Class Detail View -->
            <div class="col-md-12 mb-4">
                <a href="classes.php" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Kelas
                </a>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Detail Kelas: <?php echo $selected_class['nama']; ?> - Tahun Ajaran <?php echo $selected_class['tahun_ajaran']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Informasi Kelas</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">ID Kelas</th>
                                        <td><?php echo $selected_class['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Nama Kelas</th>
                                        <td><?php echo $selected_class['nama']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tahun Ajaran</th>
                                        <td><?php echo $selected_class['tahun_ajaran']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Wali Kelas</th>
                                        <td>
                                            <?php if ($selected_class['wali_kelas_nama']): ?>
                                                <?php echo $selected_class['wali_kelas_nama']; ?>
                                                <small class="d-block text-muted">
                                                    NUPTK: <?php echo $selected_class['nuptk']; ?><br>
                                                    Email: <?php echo $selected_class['wali_kelas_email']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Statistik Kelas</h6>
                                <div class="row text-center">
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-3">
                                                <h2 class="mb-0"><?php echo count($class_students); ?></h2>
                                                <p class="mb-0">Siswa</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-3">
                                                <h2 class="mb-0"><?php echo count($class_materials); ?></h2>
                                                <p class="mb-0">Materi</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body py-3">
                                                <h2 class="mb-0"><?php echo count($class_quizzes); ?></h2>
                                                <p class="mb-0">Quiz</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Average Score Chart -->
                                <div class="mt-3">
                                    <h6>Rata-rata Nilai Quiz</h6>
                                    <?php if (!empty($class_quizzes)): ?>
                                        <canvas id="avgScoreChart" height="150"></canvas>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            Belum ada data quiz untuk kelas ini.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabs for Students, Materials, and Quizzes -->
                        <ul class="nav nav-tabs" id="classTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="students-tab" data-bs-toggle="tab" 
                                        data-bs-target="#students" type="button" role="tab" 
                                        aria-controls="students" aria-selected="true">
                                    Daftar Siswa
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="materials-tab" data-bs-toggle="tab" 
                                        data-bs-target="#materials" type="button" role="tab" 
                                        aria-controls="materials" aria-selected="false">
                                    Materi Pembelajaran
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="quizzes-tab" data-bs-toggle="tab" 
                                        data-bs-target="#quizzes" type="button" role="tab" 
                                        aria-controls="quizzes" aria-selected="false">
                                    Quiz dan Tugas
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content" id="classTabContent">
                            <!-- Students Tab -->
                            <div class="tab-pane fade show active p-3" id="students" role="tabpanel" aria-labelledby="students-tab">
                                <?php if (empty($class_students)): ?>
                                    <div class="alert alert-info">
                                        Belum ada siswa yang terdaftar di kelas ini.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>NISN</th>
                                                    <th>Nama</th>
                                                    <th>Email</th>
                                                    <th>Quiz Dikerjakan</th>
                                                    <th>Rata-rata Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($class_students as $student): ?>
                                                    <tr>
                                                        <td><?php echo $student['nisn']; ?></td>
                                                        <td><?php echo $student['nama']; ?></td>
                                                        <td><?php echo $student['email']; ?></td>
                                                        <td><?php echo $student['jumlah_quiz_dikerjakan']; ?></td>
                                                        <td>
                                                            <?php if ($student['rata_nilai']): ?>
                                                                <span class="badge bg-<?php echo scoreColor($student['rata_nilai']); ?>">
                                                                    <?php echo round($student['rata_nilai'], 1); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Belum ada nilai</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Materials Tab -->
                            <div class="tab-pane fade p-3" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                                <?php if (empty($class_materials)): ?>
                                    <div class="alert alert-info">
                                        Belum ada materi pembelajaran untuk kelas ini.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Judul</th>
                                                    <th>Tingkat</th>
                                                    <th>Guru</th>
                                                    <th>Tanggal Dibuat</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($class_materials as $material): ?>
                                                    <tr>
                                                        <td><?php echo $material['judul']; ?></td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $material['tingkat']; ?></span>
                                                        </td>
                                                        <td><?php echo $material['guru_nama']; ?></td>
                                                        <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                                        <td>
                                                            <a href="../guru/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <?php if (count($class_materials) >= 5): ?>
                                        <div class="text-center mt-3">
                                            <a href="materials.php?class=<?php echo $selected_class['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-list me-2"></i> Lihat Semua Materi
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Quizzes Tab -->
                            <div class="tab-pane fade p-3" id="quizzes" role="tabpanel" aria-labelledby="quizzes-tab">
                                <?php if (empty($class_quizzes)): ?>
                                    <div class="alert alert-info">
                                        Belum ada quiz untuk kelas ini.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Judul</th>
                                                    <th>Materi</th>
                                                    <th>Guru</th>
                                                    <th>Deadline</th>
                                                    <th>Dikerjakan</th>
                                                    <th>Rata-rata</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($class_quizzes as $quiz): ?>
                                                    <tr>
                                                        <td><?php echo $quiz['judul']; ?></td>
                                                        <td><?php echo $quiz['materi_judul']; ?></td>
                                                        <td><?php echo $quiz['guru_nama']; ?></td>
                                                        <td>
                                                            <?php if ($quiz['tanggal_deadline']): ?>
                                                                <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tidak ada</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $quiz['jumlah_dikerjakan']; ?> siswa</td>
                                                        <td>
                                                            <?php if ($quiz['rata_nilai']): ?>
                                                                <span class="badge bg-<?php echo scoreColor($quiz['rata_nilai']); ?>">
                                                                    <?php echo round($quiz['rata_nilai'], 1); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="../guru/quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <?php if (count($class_quizzes) >= 5): ?>
                                        <div class="text-center mt-3">
                                            <a href="grades.php?class=<?php echo $selected_class['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-chart-bar me-2"></i> Lihat Analisis Nilai
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Classes Overview -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Daftar Semua Kelas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($result_classes) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama Kelas</th>
                                            <th>Tahun Ajaran</th>
                                            <th>Wali Kelas</th>
                                            <th>Jumlah Siswa</th>
                                            <th>Jumlah Materi</th>
                                            <th>Jumlah Quiz</th>
                                            <th>Rata-rata Nilai</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                            <tr>
                                                <td><?php echo $class['nama']; ?></td>
                                                <td><?php echo $class['tahun_ajaran']; ?></td>
                                                <td><?php echo $class['wali_kelas_nama'] ?? '<span class="text-muted">Belum ditentukan</span>'; ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $class['jumlah_siswa']; ?> siswa</span>
                                                </td>
                                                <td><?php echo $class['jumlah_materi']; ?></td>
                                                <td><?php echo $class['jumlah_quiz']; ?></td>
                                                <td>
                                                    <?php if ($class['rata_nilai']): ?>
                                                        <span class="badge bg-<?php echo scoreColor($class['rata_nilai']); ?>">
                                                            <?php echo round($class['rata_nilai'], 1); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="classes.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Belum ada kelas yang terdaftar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selected_class && !empty($class_quizzes)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for chart
    var quizLabels = <?php echo json_encode(array_map(function($quiz) { return limitText($quiz['judul'], 15); }, $class_quizzes)); ?>;
    var quizData = <?php echo json_encode(array_map(function($quiz) { return $quiz['rata_nilai'] ? round($quiz['rata_nilai'], 1) : 0; }, $class_quizzes)); ?>;
    
    // Score Chart
    var ctxScore = document.getElementById('avgScoreChart').getContext('2d');
    var scoreChart = new Chart(ctxScore, {
        type: 'bar',
        data: {
            labels: quizLabels,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: quizData,
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
            },
            plugins: {
                legend: {
                    display: false
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