<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Get teacher's ID
$teacher_id = $_SESSION['user_id'];

// Get classes where this teacher has created materials or quizzes
$query_classes = "SELECT DISTINCT k.id, k.nama, k.tahun_ajaran
                FROM kelas k 
                JOIN tugas t ON t.kelas_id = k.id
                WHERE t.dibuat_oleh = '$teacher_id'
                ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

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
                 VALUES ('$questionnaire_id', '$judul', '$deskripsi', '$kelas_id', '$teacher_id', CURDATE())";
                 
        if (mysqli_query($conn, $query)) {
            // Log activity
            logActivity($teacher_id, 'tambah_materi', "Guru membuat kuesioner baru: $judul");
            
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
        
        // Check if questionnaire belongs to this teacher
        $query_check = "SELECT * FROM kuesioner WHERE id = '$questionnaire_id' AND dibuat_oleh = '$teacher_id'";
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
                logActivity($teacher_id, 'hapus_materi', "Guru menghapus kuesioner: {$questionnaire['judul']}");
                
                setFlashMessage('success', 'Kuesioner berhasil dihapus.');
            } else {
                setFlashMessage('error', 'Gagal menghapus kuesioner: ' . mysqli_error($conn));
            }
        } else {
            setFlashMessage('error', 'Anda tidak memiliki akses untuk menghapus kuesioner ini.');
        }
        
        header('Location: questionnaires.php');
        exit;
    }
}

// Get questionnaires created by this teacher
$query_questionnaires = "SELECT k.*, c.nama as kelas_nama, c.tahun_ajaran,
                       (SELECT COUNT(*) FROM pertanyaan_kuesioner WHERE kuesioner_id = k.id) as jumlah_pertanyaan,
                       (SELECT COUNT(DISTINCT siswa_id) FROM jawaban_kuesioner jk 
                        JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                        WHERE pk.kuesioner_id = k.id) as jumlah_responden
                       FROM kuesioner k
                       JOIN kelas c ON k.kelas_id = c.id
                       WHERE k.dibuat_oleh = '$teacher_id'
                       ORDER BY k.tanggal_dibuat DESC";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manajemen Kuesioner</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuestionnaireModal">
            <i class="fas fa-plus me-2"></i> Buat Kuesioner Baru
        </button>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Questionnaires List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Kuesioner</h5>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result_questionnaires) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Kelas</th>
                                <th>Tanggal Dibuat</th>
                                <th>Jumlah Pertanyaan</th>
                                <th>Jumlah Responden</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                                <tr>
                                    <td><?php echo $questionnaire['judul']; ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $questionnaire['kelas_nama'] . ' (' . $questionnaire['tahun_ajaran'] . ')'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                                    <td><?php echo $questionnaire['jumlah_pertanyaan']; ?></td>
                                    <td>
                                        <?php if ($questionnaire['jumlah_responden'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $questionnaire['jumlah_responden']; ?> siswa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 siswa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="questionnaire_edit.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="questionnaire_results.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-chart-bar"></i>
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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Anda belum membuat kuesioner. Klik tombol "Buat Kuesioner Baru" untuk mulai membuat kuesioner.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"></textarea>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus kuesioner "<span id="questionnaireTitle"></span>"?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Semua pertanyaan dan jawaban siswa akan ikut terhapus!
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="questionnaires.php">
                    <input type="hidden" name="action" value="delete_questionnaire">
                    <input type="hidden" name="questionnaire_id" id="deleteQuestionnaireId">
                    
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    document.getElementById('deleteQuestionnaireId').value = id;
    document.getElementById('questionnaireTitle').textContent = title;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>

<?php include_once '../../includes/footer.php'; ?> 