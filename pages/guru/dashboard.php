<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has guru role
checkAccess(['guru']);

// Get teacher data
$teacher_id = $_SESSION['user_id'];
$query_teacher = "SELECT * FROM pengguna WHERE id = '$teacher_id'";
$result_teacher = mysqli_query($conn, $query_teacher);
$teacher = mysqli_fetch_assoc($result_teacher);

// Get classes assigned to teacher
$query_classes = "SELECT k.* FROM kelas k WHERE k.wali_kelas_id = '$teacher_id' ORDER BY k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

// Get count of materials created by teacher
$query_materials = "SELECT COUNT(*) as total FROM materi_coding WHERE dibuat_oleh = '$teacher_id'";
$result_materials = mysqli_query($conn, $query_materials);
$materials_count = mysqli_fetch_assoc($result_materials)['total'];

// Get count of quizzes created by teacher
$query_quizzes = "SELECT COUNT(*) as total FROM tugas WHERE dibuat_oleh = '$teacher_id'";
$result_quizzes = mysqli_query($conn, $query_quizzes);
$quizzes_count = mysqli_fetch_assoc($result_quizzes)['total'];

// Get recent student submissions
$query_submissions = "SELECT nt.*, t.judul as tugas_judul, p.nama as siswa_nama, k.nama as kelas_nama 
                    FROM nilai_tugas nt 
                    JOIN tugas t ON nt.tugas_id = t.id 
                    JOIN pengguna p ON nt.siswa_id = p.id 
                    JOIN kelas k ON p.kelas_id = k.id 
                    WHERE t.dibuat_oleh = '$teacher_id' 
                    ORDER BY nt.tanggal_pengumpulan DESC 
                    LIMIT 5";
$result_submissions = mysqli_query($conn, $query_submissions);

// Get class statistics
$query_class_stats = "SELECT k.id, k.nama, COUNT(p.id) as siswa_count 
                     FROM kelas k 
                     LEFT JOIN pengguna p ON k.id = p.kelas_id AND p.tipe_pengguna = 'siswa' 
                     WHERE k.wali_kelas_id = '$teacher_id' 
                     GROUP BY k.id, k.nama";
$result_class_stats = mysqli_query($conn, $query_class_stats);
$class_stats = [];
while ($row = mysqli_fetch_assoc($result_class_stats)) {
    $class_stats[] = $row;
}

// Set the current page for the active menu highlighting
$current_page = basename(__FILE__);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h2 class="card-title">Selamat Datang, <?php echo $teacher['nama']; ?>!</h2>
                    <p class="card-text">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Guru | NUPTK: <?php echo $teacher['nuptk']; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1 class="display-4 text-primary mb-2">
                        <?php echo mysqli_num_rows($result_classes); ?>
                    </h1>
                    <p class="card-text">Kelas Diampu</p>
                    <i class="fas fa-users fa-2x text-muted"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1 class="display-4 text-success mb-2">
                        <?php echo $materials_count; ?>
                    </h1>
                    <p class="card-text">Materi Dibuat</p>
                    <i class="fas fa-book fa-2x text-muted"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1 class="display-4 text-warning mb-2">
                        <?php echo $quizzes_count; ?>
                    </h1>
                    <p class="card-text">Quiz Dibuat</p>
                    <i class="fas fa-tasks fa-2x text-muted"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <?php
                    $query_ugraded = "SELECT COUNT(*) as total FROM nilai_tugas nt 
                                    JOIN tugas t ON nt.tugas_id = t.id 
                                    WHERE t.dibuat_oleh = '$teacher_id' AND nt.nilai IS NULL";
                    $result_ungraded = mysqli_query($conn, $query_ugraded);
                    $ungraded_count = mysqli_fetch_assoc($result_ungraded)['total'];
                    ?>
                    <h1 class="display-4 text-danger mb-2">
                        <?php echo $ungraded_count; ?>
                    </h1>
                    <p class="card-text">Belum Dinilai</p>
                    <i class="fas fa-exclamation-circle fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Classes Assigned -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Kelas Diampu</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_classes) > 0): ?>
                        <div class="list-group">
                            <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                <?php
                                // Get student count
                                $class_id = $class['id'];
                                $query_student_count = "SELECT COUNT(*) as total FROM pengguna WHERE kelas_id = '$class_id' AND tipe_pengguna = 'siswa'";
                                $result_student_count = mysqli_query($conn, $query_student_count);
                                $student_count = mysqli_fetch_assoc($result_student_count)['total'];
                                ?>
                                <a href="materials.php?class=<?php echo $class['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo $class['nama']; ?></h5>
                                        <small class="text-muted"><?php echo $class['tahun_ajaran']; ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <i class="fas fa-user-graduate me-2"></i>
                                        <?php echo $student_count; ?> siswa
                                    </p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Anda belum menjadi wali kelas untuk kelas manapun.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Submissions -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Pengumpulan Tugas Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_submissions) > 0): ?>
                        <div class="list-group">
                            <?php while ($submission = mysqli_fetch_assoc($result_submissions)): ?>
                                <a href="quiz_detail.php?id=<?php echo $submission['tugas_id']; ?>&grade=<?php echo $submission['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo $submission['tugas_judul']; ?></h5>
                                        <small class="text-muted"><?php echo formatDate($submission['tanggal_pengumpulan']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php echo $submission['siswa_nama']; ?> (<?php echo $submission['kelas_nama']; ?>)
                                    </p>
                                    <small>
                                        <?php if ($submission['nilai'] !== null): ?>
                                            <span class="badge bg-success">Nilai: <?php echo $submission['nilai']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum dinilai</span>
                                        <?php endif; ?>
                                    </small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada pengumpulan tugas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Access Links -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Akses Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <a href="materials.php" class="card text-center h-100 text-decoration-none">
                                <div class="card-body">
                                    <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Kelola Materi</h5>
                                    <p class="card-text">Tambah, edit, dan hapus materi pembelajaran</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="quizzes.php" class="card text-center h-100 text-decoration-none">
                                <div class="card-body">
                                    <i class="fas fa-tasks fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Kelola Quiz</h5>
                                    <p class="card-text">Buat dan nilai quiz untuk siswa</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="grades.php" class="card text-center h-100 text-decoration-none">
                                <div class="card-body">
                                    <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Laporan Nilai</h5>
                                    <p class="card-text">Lihat dan analisis nilai siswa</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php include_once '../../includes/footer.php'; ?> 