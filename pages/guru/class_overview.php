<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Check if class ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Kelas tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$class_id = sanitizeInput($_GET['id']);

// Get class details
$query_class = "SELECT k.*, p.nama as wali_kelas_nama 
               FROM kelas k 
               LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
               WHERE k.id = '$class_id'";
$result_class = mysqli_query($conn, $query_class);

if (mysqli_num_rows($result_class) === 0) {
    setFlashMessage('error', 'Kelas tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$class = mysqli_fetch_assoc($result_class);

// Get students in the class
$query_students = "SELECT id, nama, nisn, last_login 
                  FROM pengguna 
                  WHERE kelas_id = '$class_id' 
                  AND tipe_pengguna = 'siswa' 
                  ORDER BY nama ASC";
$result_students = mysqli_query($conn, $query_students);

// Get materials for this class
$query_materials = "SELECT m.*, p.nama as guru_nama 
                   FROM materi_coding m 
                   JOIN pengguna p ON m.dibuat_oleh = p.id 
                   WHERE m.kelas_id = '$class_id' 
                   ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Get quizzes for this class
$query_quizzes = "SELECT t.*, m.judul as materi_judul,
                  (SELECT COUNT(*) FROM nilai_tugas nt WHERE nt.tugas_id = t.id) as submission_count,
                  (SELECT COUNT(*) FROM nilai_tugas nt WHERE nt.tugas_id = t.id AND nt.nilai IS NOT NULL) as graded_count
                  FROM tugas t 
                  JOIN materi_coding m ON t.materi_id = m.id 
                  WHERE t.kelas_id = '$class_id' 
                  ORDER BY t.tanggal_dibuat DESC";
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Class Information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="card-title mb-1"><?php echo $class['nama']; ?></h2>
                            <p class="card-text mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Tahun Ajaran: <?php echo $class['tahun_ajaran']; ?>
                            </p>
                            <p class="card-text mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Wali Kelas: <?php echo $class['wali_kelas_nama']; ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <h3 class="mb-0"><?php echo mysqli_num_rows($result_students); ?></h3>
                            <small>Total Siswa</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Student List -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Siswa</h5>
                    <span class="badge bg-primary"><?php echo mysqli_num_rows($result_students); ?> Siswa</span>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>NISN</th>
                                        <th>Login Terakhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = mysqli_fetch_assoc($result_students)): ?>
                                        <tr>
                                            <td><?php echo $student['nama']; ?></td>
                                            <td><?php echo $student['nisn']; ?></td>
                                            <td>
                                                <?php if ($student['last_login']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($student['last_login'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Belum pernah login</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada siswa di kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Materials List -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Materi Coding</h5>
                    <a href="materials.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Materi
                    </a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_materials) > 0): ?>
                        <div class="list-group">
                            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $material['judul']; ?></h6>
                                        <small class="text-muted"><?php echo formatDate($material['tanggal_dibuat']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge bg-info me-2"><?php echo $material['tingkat']; ?></span>
                                        <small class="text-muted">Oleh: <?php echo $material['guru_nama']; ?></small>
                                    </p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada materi untuk kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quizzes List -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quiz</h5>
                    <a href="quizzes.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Quiz
                    </a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_quizzes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul Quiz</th>
                                        <th>Materi</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($quiz = mysqli_fetch_assoc($result_quizzes)): ?>
                                        <tr>
                                            <td><?php echo $quiz['judul']; ?></td>
                                            <td><?php echo $quiz['materi_judul']; ?></td>
                                            <td>
                                                <?php if ($quiz['tanggal_deadline']): ?>
                                                    <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($quiz['status'] === 'draft'): ?>
                                                    <span class="badge bg-secondary">Draft</span>
                                                <?php elseif ($quiz['status'] === 'published'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Ditutup</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $total_students = mysqli_num_rows($result_students);
                                                $submission_percentage = $total_students > 0 ? 
                                                    round(($quiz['submission_count'] / $total_students) * 100) : 0;
                                                $graded_percentage = $quiz['submission_count'] > 0 ? 
                                                    round(($quiz['graded_count'] / $quiz['submission_count']) * 100) : 0;
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $graded_percentage; ?>%">
                                                        <?php echo $quiz['graded_count']; ?> dinilai
                                                    </div>
                                                    <div class="progress-bar bg-warning" role="progressbar" 
                                                         style="width: <?php echo $submission_percentage - $graded_percentage; ?>%">
                                                        <?php echo $quiz['submission_count'] - $quiz['graded_count']; ?> belum dinilai
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">
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
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada quiz untuk kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 