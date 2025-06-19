<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has appropriate role
checkAccess(['guru', 'siswa', 'kepsek', 'admin']);

// Check if material ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Materi tidak ditemukan.');
    
    // Redirect based on user role
    if ($_SESSION['user_role'] === 'siswa') {
        header('Location: ../siswa/materials.php');
    } else if ($_SESSION['user_role'] === 'guru') {
        header('Location: materials.php');
    } else if ($_SESSION['user_role'] === 'kepsek') {
        header('Location: ../kepsek/materials.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$material_id = sanitizeInput($_GET['id']);

// Get material details
$query_material = "SELECT m.*, k.nama as kelas_nama, p.nama as guru_nama 
                  FROM materi_coding m 
                  JOIN kelas k ON m.kelas_id = k.id 
                  JOIN pengguna p ON m.dibuat_oleh = p.id 
                  WHERE m.id = '$material_id'";
$result_material = mysqli_query($conn, $query_material);

if (mysqli_num_rows($result_material) === 0) {
    setFlashMessage('error', 'Materi tidak ditemukan.');
    
    // Redirect based on user role
    if ($_SESSION['user_role'] === 'siswa') {
        header('Location: ../siswa/materials.php');
    } else if ($_SESSION['user_role'] === 'guru') {
        header('Location: materials.php');
    } else if ($_SESSION['user_role'] === 'kepsek') {
        header('Location: ../kepsek/materials.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$material = mysqli_fetch_assoc($result_material);

// Check permissions for students (can only view materials for their class)
if ($_SESSION['user_role'] === 'siswa') {
    $student_query = "SELECT kelas_id FROM pengguna WHERE id = '{$_SESSION['user_id']}'";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);
    
    if ($student['kelas_id'] !== $material['kelas_id']) {
        setFlashMessage('error', 'Anda tidak memiliki akses untuk melihat materi ini.');
        header('Location: ../siswa/materials.php');
        exit;
    }
}

// Log view activity for students
if ($_SESSION['user_role'] === 'siswa') {
    logActivity($_SESSION['user_id'], 'view_materi', "Siswa melihat materi: {$material['judul']}");
}

// Get related quizzes
$query_quizzes = "SELECT id, judul, tanggal_deadline 
                 FROM tugas 
                 WHERE materi_id = '$material_id' 
                 ORDER BY tanggal_dibuat DESC";
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <?php if ($_SESSION['user_role'] === 'siswa'): ?>
            <a href="../siswa/materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php elseif ($_SESSION['user_role'] === 'guru'): ?>
            <a href="materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php elseif ($_SESSION['user_role'] === 'kepsek'): ?>
            <a href="../kepsek/materials.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Materi
            </a>
        <?php else: ?>
            <a href="../admin/dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        <?php endif; ?>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Material Content -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php echo $material['judul']; ?></h2>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <span class="badge bg-primary me-2"><?php echo $material['tingkat']; ?></span>
                                <span class="badge bg-info me-2"><?php echo $material['kelas_nama']; ?></span>
                                <span class="text-muted">Dibuat: <?php echo formatDate($material['tanggal_dibuat']); ?></span>
                            </div>
                            <?php if ($_SESSION['user_role'] === 'guru' && $material['dibuat_oleh'] === $_SESSION['user_id']): ?>
                                <a href="materials.php?action=edit&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-edit me-1"></i> Edit Materi
                                </a>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted">
                            <i class="fas fa-user-circle me-1"></i> Dibuat oleh: <strong><?php echo $material['guru_nama']; ?></strong>
                        </p>
                    </div>
                    
                    <!-- Material Image -->
                    <?php if (!empty($material['image_url'])): ?>
                    <div class="mb-4">
                        <img src="../../<?php echo $material['image_url']; ?>" class="img-fluid rounded" alt="<?php echo $material['judul']; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <!-- Material Content -->
                    <div class="materi-content">
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
                                <a href="<?php echo ($_SESSION['user_role'] === 'siswa') ? '../siswa/quiz.php?id=' . $quiz['id'] : 'quiz_detail.php?id=' . $quiz['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $quiz['judul']; ?></h6>
                                    </div>
                                    <?php if ($quiz['tanggal_deadline']): ?>
                                        <small class="text-danger">
                                            <i class="fas fa-clock me-1"></i> Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                        </small>
                                    <?php endif; ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Belum ada quiz untuk materi ini.</p>
                        
                        <?php if ($_SESSION['user_role'] === 'guru' && $material['dibuat_oleh'] === $_SESSION['user_id']): ?>
                            <div class="d-grid gap-2">
                                <a href="quizzes.php?add=true&materi_id=<?php echo $material['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Buat Quiz
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Class Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informasi Kelas</h5>
                </div>
                <div class="card-body">
                    <p><strong>Kelas:</strong> <?php echo $material['kelas_nama']; ?></p>
                    
                    <?php
                    // Get number of students in the class
                    $student_count_query = "SELECT COUNT(*) as count FROM pengguna WHERE kelas_id = '{$material['kelas_id']}' AND tipe_pengguna = 'siswa'";
                    $student_count_result = mysqli_query($conn, $student_count_query);
                    $student_count = mysqli_fetch_assoc($student_count_result)['count'];
                    
                    // Get number of materials for this class
                    $material_count_query = "SELECT COUNT(*) as count FROM materi_coding WHERE kelas_id = '{$material['kelas_id']}'";
                    $material_count_result = mysqli_query($conn, $material_count_query);
                    $material_count = mysqli_fetch_assoc($material_count_result)['count'];
                    ?>
                    
                    <p><strong>Jumlah Siswa:</strong> <?php echo $student_count; ?> siswa</p>
                    <p><strong>Jumlah Materi:</strong> <?php echo $material_count; ?> materi</p>
                    
                    <?php if ($_SESSION['user_role'] === 'guru'): ?>
                        <a href="class_overview.php?id=<?php echo $material['kelas_id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> Lihat Kelas
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .materi-content {
        line-height: 1.6;
    }
    
    .materi-content img {
        max-width: 100%;
        height: auto;
    }
    
    .materi-content pre {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
    
    .materi-content code {
        background-color: #f1f1f1;
        padding: 2px 4px;
        border-radius: 4px;
    }
    
    .materi-content h1, .materi-content h2, .materi-content h3, 
    .materi-content h4, .materi-content h5, .materi-content h6 {
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .materi-content ul, .materi-content ol {
        padding-left: 2rem;
    }
</style>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 