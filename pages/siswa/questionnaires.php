<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Get student's information
$student_id = $_SESSION['user_id'];
$query_student = "SELECT p.*, k.nama AS kelas_nama, k.tahun_ajaran 
                  FROM pengguna p 
                  LEFT JOIN kelas k ON p.kelas_id = k.id 
                  WHERE p.id = '$student_id'";
$result_student = mysqli_query($conn, $query_student);
$student = mysqli_fetch_assoc($result_student);
$class_id = $student['kelas_id'];

// Get all questionnaires for this class
$query_questionnaires = "SELECT k.*, p.nama AS dibuat_oleh_nama, 
                       (SELECT COUNT(*) FROM pertanyaan_kuesioner WHERE kuesioner_id = k.id) AS jumlah_pertanyaan,
                       (SELECT COUNT(*) FROM jawaban_kuesioner jk 
                        JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                        WHERE pk.kuesioner_id = k.id AND jk.siswa_id = '$student_id') AS sudah_isi
                       FROM kuesioner k
                       JOIN pengguna p ON k.dibuat_oleh = p.id
                       WHERE k.kelas_id = '$class_id'
                       ORDER BY k.tanggal_dibuat DESC";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Kuesioner</h1>
            <p class="text-muted">Daftar kuesioner untuk kelas <?php echo $student['kelas_nama'] . ' (' . $student['tahun_ajaran'] . ')'; ?></p>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Questionnaires List -->
    <div class="row">
        <?php if (mysqli_num_rows($result_questionnaires) > 0): ?>
            <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo $questionnaire['judul']; ?></h5>
                            <?php if ($questionnaire['sudah_isi'] > 0): ?>
                                <span class="badge bg-success">Sudah Diisi</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Belum Diisi</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($questionnaire['deskripsi']): ?>
                                <p><?php echo $questionnaire['deskripsi']; ?></p>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Tidak ada deskripsi</p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mt-3">
                                <div>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-user me-1"></i> Dibuat oleh: <?php echo $questionnaire['dibuat_oleh_nama']; ?>
                                    </small>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-calendar me-1"></i> Tanggal: <?php echo formatDate($questionnaire['tanggal_dibuat']); ?>
                                    </small>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-list me-1"></i> Jumlah pertanyaan: <?php echo $questionnaire['jumlah_pertanyaan']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <?php if ($questionnaire['sudah_isi'] > 0): ?>
                                <button class="btn btn-success w-100" disabled>
                                    <i class="fas fa-check-circle me-2"></i> Sudah Diisi
                                </button>
                            <?php else: ?>
                                <a href="questionnaire.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-edit me-2"></i> Isi Kuesioner
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum ada kuesioner yang tersedia untuk kelas Anda.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 