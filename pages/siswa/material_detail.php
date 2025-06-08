<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa', 'guru', 'kepsek', 'admin']);

// Check if material ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Materi tidak ditemukan.');
    
    if ($_SESSION['user_type'] === 'siswa') {
        header('Location: materials.php');
    } elseif ($_SESSION['user_type'] === 'guru') {
        header('Location: ../guru/materials.php');
    } elseif ($_SESSION['user_type'] === 'kepsek') {
        header('Location: ../kepsek/materials.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$material_id = sanitizeInput($_GET['id']);

// Get material details
$query_material = "SELECT m.*, p.nama as guru_nama, k.nama as kelas_nama, k.tahun_ajaran 
                  FROM materi_coding m 
                  JOIN pengguna p ON m.dibuat_oleh = p.id 
                  JOIN kelas k ON m.kelas_id = k.id 
                  WHERE m.id = '$material_id'";
$result_material = mysqli_query($conn, $query_material);

if (mysqli_num_rows($result_material) === 0) {
    setFlashMessage('error', 'Materi tidak ditemukan.');
    
    if ($_SESSION['user_type'] === 'siswa') {
        header('Location: materials.php');
    } elseif ($_SESSION['user_type'] === 'guru') {
        header('Location: ../guru/materials.php');
    } elseif ($_SESSION['user_type'] === 'kepsek') {
        header('Location: ../kepsek/materials.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$material = mysqli_fetch_assoc($result_material);

// Check if student is authorized to view this material (must be in the same class)
if ($_SESSION['user_type'] === 'siswa') {
    $student_id = $_SESSION['user_id'];
    $query_check = "SELECT kelas_id FROM pengguna WHERE id = '$student_id'";
    $result_check = mysqli_query($conn, $query_check);
    $student = mysqli_fetch_assoc($result_check);
    
    if ($student['kelas_id'] !== $material['kelas_id']) {
        setFlashMessage('error', 'Anda tidak memiliki akses untuk melihat materi ini.');
        header('Location: materials.php');
        exit;
    }
    
    // Log activity
    logActivity($student_id, 'view_materi', "Siswa melihat materi: {$material['judul']}", $material_id);
}

// Get related quizzes
$query_quizzes = "SELECT t.*, 
                (SELECT COUNT(*) FROM soal_quiz WHERE tugas_id = t.id) as jumlah_soal,
                (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '{$_SESSION['user_id']}') as sudah_dikerjakan
                FROM tugas t 
                WHERE t.materi_id = '$material_id'
                ORDER BY t.tanggal_dibuat DESC";
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <?php if ($_SESSION['user_type'] === 'siswa'): ?>
            <a href="materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php elseif ($_SESSION['user_type'] === 'guru'): ?>
            <a href="../guru/materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php elseif ($_SESSION['user_type'] === 'kepsek'): ?>
            <a href="../kepsek/materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php else: ?>
            <a href="../admin/dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3"><?php echo $material['judul']; ?></h1>
            
            <?php if ($_SESSION['user_type'] === 'guru' && $material['dibuat_oleh'] === $_SESSION['user_id']): ?>
                <a href="../guru/material_detail.php?id=<?php echo $material_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i> Edit Materi
                </a>
            <?php endif; ?>
        </div>
        
        <p class="mb-0">
            <span class="badge bg-info me-2">Kelas: <?php echo $material['kelas_nama'] . ' (' . $material['tahun_ajaran'] . ')'; ?></span>
            <span class="badge bg-secondary me-2">Tingkat: <?php echo $material['tingkat']; ?></span>
            <span class="badge bg-primary">Guru: <?php echo $material['guru_nama']; ?></span>
            <span class="badge bg-light text-dark ms-2">Dibuat pada: <?php echo formatDate($material['tanggal_dibuat']); ?></span>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Material Content -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Materi Pembelajaran</h5>
                </div>
                <div class="card-body">
                    <div class="material-content">
                        <?php echo $material['deskripsi']; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Related Quizzes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quiz Terkait</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_quizzes) > 0): ?>
                        <div class="list-group">
                            <?php while ($quiz = mysqli_fetch_assoc($result_quizzes)): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h6 class="mb-1"><?php echo $quiz['judul']; ?></h6>
                                        <?php if ($quiz['sudah_dikerjakan'] > 0): ?>
                                            <span class="badge bg-success">Sudah dikerjakan</span>
                                        <?php else: ?>
                                            <?php if ($quiz['tanggal_deadline']): ?>
                                                <?php
                                                $deadline = strtotime($quiz['tanggal_deadline']);
                                                $now = time();
                                                $days_left = ($deadline - $now) / (60 * 60 * 24);
                                                
                                                if ($days_left < 0) {
                                                    $badge_class = 'bg-danger';
                                                    $badge_text = 'Deadline terlewat';
                                                } elseif ($days_left < 2) {
                                                    $badge_class = 'bg-warning';
                                                    $badge_text = 'Deadline: ' . formatDate($quiz['tanggal_deadline']);
                                                } else {
                                                    $badge_class = 'bg-info';
                                                    $badge_text = 'Deadline: ' . formatDate($quiz['tanggal_deadline']);
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Belum dikerjakan</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1">Jumlah Soal: <?php echo $quiz['jumlah_soal']; ?></p>
                                    <small class="text-muted">Dibuat pada: <?php echo formatDate($quiz['tanggal_dibuat']); ?></small>
                                    
                                    <div class="mt-2">
                                        <?php if ($_SESSION['user_type'] === 'siswa'): ?>
                                            <?php if ($quiz['sudah_dikerjakan'] > 0): ?>
                                                <a href="quiz_result.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-eye me-1"></i> Lihat Hasil
                                                </a>
                                            <?php else: ?>
                                                <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-pencil-alt me-1"></i> Kerjakan Quiz
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($_SESSION['user_type'] === 'guru'): ?>
                                            <a href="../guru/quiz_edit.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i> Edit Quiz
                                            </a>
                                            <a href="../guru/quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-chart-bar me-1"></i> Lihat Hasil
                                            </a>
                                        <?php elseif ($_SESSION['user_type'] === 'kepsek'): ?>
                                            <a href="../guru/quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-chart-bar me-1"></i> Lihat Hasil
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada quiz terkait untuk materi ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Material Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informasi Tambahan</h5>
                </div>
                <div class="card-body">
                    <p><strong>Deskripsi Singkat:</strong></p>
                    <p><?php echo limitText(strip_tags($material['deskripsi']), 200); ?></p>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Tingkat:</strong></span>
                        <span class="badge bg-info"><?php echo $material['tingkat']; ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Kelas:</strong></span>
                        <span><?php echo $material['kelas_nama'] . ' (' . $material['tahun_ajaran'] . ')'; ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Guru:</strong></span>
                        <span><?php echo $material['guru_nama']; ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Tanggal Dibuat:</strong></span>
                        <span><?php echo formatDate($material['tanggal_dibuat']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.material-content {
    line-height: 1.7;
}

.material-content h1, 
.material-content h2, 
.material-content h3, 
.material-content h4, 
.material-content h5, 
.material-content h6 {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.material-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
}

.material-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    overflow-x: auto;
    margin: 1rem 0;
}

.material-content code {
    background-color: #f1f1f1;
    padding: 2px 4px;
    border-radius: 4px;
}

.material-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.material-content table, 
.material-content th, 
.material-content td {
    border: 1px solid #dee2e6;
}

.material-content th, 
.material-content td {
    padding: 0.5rem;
}

.material-content blockquote {
    border-left: 4px solid #6c757d;
    padding-left: 1rem;
    margin-left: 0;
    color: #6c757d;
}
</style>

<?php include_once '../../includes/footer.php'; ?> 