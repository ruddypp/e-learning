<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Check if questionnaire ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire_id = sanitizeInput($_GET['id']);
$teacher_id = $_SESSION['user_id'];

// Get questionnaire details
$query_questionnaire = "SELECT k.*, c.nama as kelas_nama, c.tahun_ajaran 
                      FROM kuesioner k 
                      JOIN kelas c ON k.kelas_id = c.id 
                      WHERE k.id = '$questionnaire_id'";
$result_questionnaire = mysqli_query($conn, $query_questionnaire);

if (mysqli_num_rows($result_questionnaire) === 0) {
    setFlashMessage('error', 'Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire = mysqli_fetch_assoc($result_questionnaire);

// Verify the questionnaire belongs to this teacher
if ($questionnaire['dibuat_oleh'] !== $teacher_id) {
    setFlashMessage('error', 'Anda tidak memiliki akses untuk mengedit kuesioner ini.');
    header('Location: questionnaires.php');
    exit;
}

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
            logActivity($teacher_id, 'edit_materi', "Guru mengedit kuesioner: $judul");
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
                    <a href="questionnaire_results.php?id=<?php echo $questionnaire_id; ?>" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i> Lihat Hasil
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <p class="mb-0">
            <span class="badge bg-info me-2">Kelas: <?php echo $questionnaire['kelas_nama'] . ' (' . $questionnaire['tahun_ajaran'] . ')'; ?></span>
            <span class="badge bg-secondary">Dibuat pada: <?php echo formatDate($questionnaire['tanggal_dibuat']); ?></span>
            
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
                            <input type="text" class="form-control" id="judul" name="judul" 
                                   value="<?php echo $questionnaire['judul']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"><?php echo $questionnaire['deskripsi']; ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Add Question Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tambah Pertanyaan Baru</h5>
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
                                <option value="text">Teks (Jawaban Terbuka)</option>
                                <option value="skala">Skala (1-5)</option>
                                <option value="pilihan_ganda">Pilihan Ganda (Ya/Tidak)</option>
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i> Tambah Pertanyaan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Questions List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Pertanyaan</h5>
                    <span class="badge bg-primary"><?php echo mysqli_num_rows($result_questions); ?> Pertanyaan</span>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_questions) > 0): ?>
                        <div class="list-group">
                            <?php $question_number = 1; ?>
                            <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-secondary me-2"><?php echo $question_number++; ?></span>
                                            <?php
                                            $badge_class = '';
                                            $badge_text = '';
                                            
                                            switch ($question['jenis']) {
                                                case 'text':
                                                    $badge_class = 'bg-primary';
                                                    $badge_text = 'Teks';
                                                    break;
                                                case 'skala':
                                                    $badge_class = 'bg-info';
                                                    $badge_text = 'Skala (1-5)';
                                                    break;
                                                case 'pilihan_ganda':
                                                    $badge_class = 'bg-warning';
                                                    $badge_text = 'Pilihan Ganda (Ya/Tidak)';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-primary me-1" 
                                                    data-bs-toggle="modal" data-bs-target="#editQuestionModal"
                                                    onclick="prepareEditQuestion('<?php echo $question['id']; ?>', 
                                                                              '<?php echo addslashes($question['pertanyaan']); ?>', 
                                                                              '<?php echo $question['jenis']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDeleteQuestion('<?php echo $question['id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mb-1"><?php echo $question['pertanyaan']; ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada pertanyaan dalam kuesioner ini. Gunakan form di samping untuk menambahkan pertanyaan.
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
                <input type="hidden" name="question_id" id="editQuestionId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editPertanyaan" class="form-label">Pertanyaan</label>
                        <textarea class="form-control" id="editPertanyaan" name="pertanyaan" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editJenis" class="form-label">Jenis Pertanyaan</label>
                        <select class="form-select" id="editJenis" name="jenis" required>
                            <option value="text">Teks (Jawaban Terbuka)</option>
                            <option value="skala">Skala (1-5)</option>
                            <option value="pilihan_ganda">Pilihan Ganda (Ya/Tidak)</option>
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
                <h5 class="modal-title" id="deleteQuestionModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pertanyaan ini?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Semua jawaban siswa untuk pertanyaan ini akan ikut terhapus!
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="questionnaire_edit.php?id=<?php echo $questionnaire_id; ?>">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="question_id" id="deleteQuestionId">
                    
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function prepareEditQuestion(id, pertanyaan, jenis) {
    document.getElementById('editQuestionId').value = id;
    document.getElementById('editPertanyaan').value = pertanyaan;
    document.getElementById('editJenis').value = jenis;
}

function confirmDeleteQuestion(id) {
    document.getElementById('deleteQuestionId').value = id;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteQuestionModal'));
    deleteModal.show();
}
</script>

<?php include_once '../../includes/footer.php'; ?> 