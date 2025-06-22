<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Check if questionnaire ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire_id = sanitizeInput($_GET['id']);
$admin_id = $_SESSION['user_id'];

// Get questionnaire details
$query_questionnaire = "SELECT k.*, c.nama as kelas_nama, c.tahun_ajaran, p.nama as created_by_name
                      FROM kuesioner k 
                      JOIN kelas c ON k.kelas_id = c.id 
                      JOIN pengguna p ON k.dibuat_oleh = p.id
                      WHERE k.id = '$questionnaire_id'";
$result_questionnaire = mysqli_query($conn, $query_questionnaire);

if (mysqli_num_rows($result_questionnaire) === 0) {
    setFlashMessage('error', 'Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire = mysqli_fetch_assoc($result_questionnaire);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update questionnaire
    if (isset($_POST['action']) && $_POST['action'] === 'update_questionnaire') {
        $judul = sanitizeInput($_POST['judul']);
        $deskripsi = sanitizeInput($_POST['deskripsi'], false);
        
        $query = "UPDATE kuesioner SET judul = '$judul', deskripsi = '$deskripsi' WHERE id = '$questionnaire_id'";
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Kuesioner berhasil diperbarui.');
            
            // Log activity
            logActivity($admin_id, 'edit_materi', "Admin mengedit kuesioner: $judul");
        } else {
            setFlashMessage('error', 'Gagal memperbarui kuesioner: ' . mysqli_error($conn));
        }
        
        // Refresh page
        header('Location: questionnaire_edit.php?id=' . $questionnaire_id);
        exit;
    }
    
    // Add question
    if (isset($_POST['action']) && $_POST['action'] === 'add_question') {
        $pertanyaan = sanitizeInput($_POST['pertanyaan'], false);
        $jenis = sanitizeInput($_POST['jenis']);
        
        // Generate ID
        $question_id = generateID('PK', 'pertanyaan_kuesioner', 'id');
        
        $query = "INSERT INTO pertanyaan_kuesioner (id, kuesioner_id, pertanyaan, jenis)
                 VALUES ('$question_id', '$questionnaire_id', '$pertanyaan', '$jenis')";
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Pertanyaan berhasil ditambahkan.');
        } else {
            setFlashMessage('error', 'Gagal menambahkan pertanyaan: ' . mysqli_error($conn));
        }
        
        // Refresh page
        header('Location: questionnaire_edit.php?id=' . $questionnaire_id);
        exit;
    }
    
    // Delete question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $question_id = sanitizeInput($_POST['question_id']);
        
        // Check if question belongs to this questionnaire
        $query_check = "SELECT * FROM pertanyaan_kuesioner 
                       WHERE id = '$question_id' AND kuesioner_id = '$questionnaire_id'";
        $result_check = mysqli_query($conn, $query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // Delete answers first
            $query_delete_answers = "DELETE FROM jawaban_kuesioner WHERE pertanyaan_id = '$question_id'";
            mysqli_query($conn, $query_delete_answers);
            
            // Delete question
            $query_delete = "DELETE FROM pertanyaan_kuesioner WHERE id = '$question_id'";
            
            if (mysqli_query($conn, $query_delete)) {
                setFlashMessage('success', 'Pertanyaan berhasil dihapus.');
            } else {
                setFlashMessage('error', 'Gagal menghapus pertanyaan: ' . mysqli_error($conn));
            }
        } else {
            setFlashMessage('error', 'Pertanyaan tidak ditemukan atau tidak termasuk dalam kuesioner ini.');
        }
        
        // Refresh page
        header('Location: questionnaire_edit.php?id=' . $questionnaire_id);
        exit;
    }
    
    // Update question
    if (isset($_POST['action']) && $_POST['action'] === 'update_question') {
        $question_id = sanitizeInput($_POST['question_id']);
        $pertanyaan = sanitizeInput($_POST['pertanyaan'], false);
        $jenis = sanitizeInput($_POST['jenis']);
        
        // Check if question belongs to this questionnaire
        $query_check = "SELECT * FROM pertanyaan_kuesioner 
                       WHERE id = '$question_id' AND kuesioner_id = '$questionnaire_id'";
        $result_check = mysqli_query($conn, $query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE pertanyaan_kuesioner 
                     SET pertanyaan = '$pertanyaan', jenis = '$jenis' 
                     WHERE id = '$question_id'";
            
            if (mysqli_query($conn, $query)) {
                setFlashMessage('success', 'Pertanyaan berhasil diperbarui.');
            } else {
                setFlashMessage('error', 'Gagal memperbarui pertanyaan: ' . mysqli_error($conn));
            }
        } else {
            setFlashMessage('error', 'Pertanyaan tidak ditemukan atau tidak termasuk dalam kuesioner ini.');
        }
        
        // Refresh page
        header('Location: questionnaire_edit.php?id=' . $questionnaire_id);
        exit;
    }
}

// Get questions for this questionnaire
$query_questions = "SELECT * FROM pertanyaan_kuesioner 
                   WHERE kuesioner_id = '$questionnaire_id' 
                   ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);

// Check if there are any responses already
$query_responses = "SELECT COUNT(DISTINCT siswa_id) as jumlah_responden 
                  FROM jawaban_kuesioner jk 
                  JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                  WHERE pk.kuesioner_id = '$questionnaire_id'";
$result_responses = mysqli_query($conn, $query_responses);
$responses_count = mysqli_fetch_assoc($result_responses)['jumlah_responden'];

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="questionnaires.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Kuesioner
        </a>
        
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3">Edit Kuesioner: <?php echo $questionnaire['judul']; ?></h1>
            
            <div>
                <?php if (mysqli_num_rows($result_questions) > 0): ?>
                    <a href="view_questionnaire_results.php?id=<?php echo $questionnaire_id; ?>" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i> Lihat Hasil
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <p class="mb-0">
            <span class="badge bg-info me-2">Kelas: <?php echo $questionnaire['kelas_nama'] . ' (' . $questionnaire['tahun_ajaran'] . ')'; ?></span>
            <span class="badge bg-secondary">Dibuat pada: <?php echo formatDate($questionnaire['tanggal_dibuat']); ?></span>
            <span class="badge bg-primary">Dibuat oleh: <?php echo $questionnaire['created_by_name']; ?></span>
            
            <?php if ($responses_count > 0): ?>
                <span class="badge bg-success ms-2"><?php echo $responses_count; ?> siswa telah merespons</span>
            <?php else: ?>
                <span class="badge bg-warning ms-2">Belum ada respons</span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-4">
            <!-- Questionnaire Details Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Detail Kuesioner</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="questionnaire_edit.php?id=<?php echo $questionnaire_id; ?>">
                        <input type="hidden" name="action" value="update_questionnaire">
                        
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Kuesioner</label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?php echo $questionnaire['judul']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?php echo $questionnaire['deskripsi']; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Perbarui Detail</button>
                    </form>
                </div>
            </div>
            
            <!-- Add Question Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tambah Pertanyaan</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="questionnaire_edit.php?id=<?php echo $questionnaire_id; ?>">
                        <input type="hidden" name="action" value="add_question">
                        
                        <div class="mb-3">
                            <label for="pertanyaan" class="form-label">Pertanyaan</label>
                            <textarea class="form-control" id="pertanyaan" name="pertanyaan" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="jenis" class="form-label">Jenis Pertanyaan</label>
                            <select class="form-select" id="jenis" name="jenis" required>
                                <option value="text">Text (Jawaban Terbuka)</option>
                                <option value="skala">Skala (1-5)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Tambah Pertanyaan</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Questions List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daftar Pertanyaan</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_questions) > 0): ?>
                        <div class="list-group">
                            <?php $question_number = 1; ?>
                            <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Pertanyaan <?php echo $question_number; ?></h6>
                                        <div>
                                            <button class="btn btn-sm btn-info edit-question-btn" 
                                                    data-id="<?php echo $question['id']; ?>"
                                                    data-pertanyaan="<?php echo htmlspecialchars($question['pertanyaan']); ?>"
                                                    data-jenis="<?php echo $question['jenis']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-question-btn"
                                                    data-id="<?php echo $question['id']; ?>"
                                                    data-number="<?php echo $question_number; ?>">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <p><?php echo $question['pertanyaan']; ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-info">
                                            Jenis: <?php echo ($question['jenis'] === 'text') ? 'Text (Jawaban Terbuka)' : 'Skala (1-5)'; ?>
                                        </span>
                                        
                                        <?php
                                        // Get response count for this question
                                        $question_id = $question['id'];
                                        $query_answers = "SELECT COUNT(*) as total FROM jawaban_kuesioner WHERE pertanyaan_id = '$question_id'";
                                        $result_answers = mysqli_query($conn, $query_answers);
                                        $answers_count = mysqli_fetch_assoc($result_answers)['total'];
                                        ?>
                                        
                                        <span class="badge bg-<?php echo ($answers_count > 0) ? 'success' : 'secondary'; ?>">
                                            <?php echo $answers_count; ?> jawaban
                                        </span>
                                    </div>
                                </div>
                                <?php $question_number++; ?>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada pertanyaan untuk kuesioner ini. Tambahkan pertanyaan menggunakan form di samping.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQuestionModalLabel">Edit Pertanyaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="questionnaire_edit.php?id=<?php echo $questionnaire_id; ?>">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_pertanyaan" class="form-label">Pertanyaan</label>
                        <textarea class="form-control" id="edit_pertanyaan" name="pertanyaan" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_jenis" class="form-label">Jenis Pertanyaan</label>
                        <select class="form-select" id="edit_jenis" name="jenis" required>
                            <option value="text">Text (Jawaban Terbuka)</option>
                            <option value="skala">Skala (1-5)</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Question Modal -->
<div class="modal fade" id="deleteQuestionModal" tabindex="-1" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteQuestionModalLabel">Hapus Pertanyaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus Pertanyaan <span id="delete_question_number" class="fw-bold"></span>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus semua jawaban yang terkait.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="questionnaire_edit.php?id=<?php echo $questionnaire_id; ?>">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="question_id" id="delete_question_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit question buttons
    const editButtons = document.querySelectorAll('.edit-question-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const pertanyaan = this.getAttribute('data-pertanyaan');
            const jenis = this.getAttribute('data-jenis');
            
            document.getElementById('edit_question_id').value = id;
            document.getElementById('edit_pertanyaan').value = pertanyaan;
            document.getElementById('edit_jenis').value = jenis;
            
            const editModal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
            editModal.show();
        });
    });
    
    // Delete question buttons
    const deleteButtons = document.querySelectorAll('.delete-question-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const number = this.getAttribute('data-number');
            
            document.getElementById('delete_question_id').value = id;
            document.getElementById('delete_question_number').textContent = number;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteQuestionModal'));
            deleteModal.show();
        });
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 