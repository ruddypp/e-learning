<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Check if add parameter is present and material_id is provided
$add_mode = isset($_GET['add']) && $_GET['add'] === 'true';
$materi_id = isset($_GET['materi_id']) ? sanitizeInput($_GET['materi_id']) : '';

// If material_id is provided, verify it exists and belongs to this teacher
if ($materi_id) {
    $query_material = "SELECT m.*, k.id as kelas_id, k.nama as kelas_nama 
                      FROM materi_coding m 
                      JOIN kelas k ON m.kelas_id = k.id 
                      WHERE m.id = '$materi_id' AND m.dibuat_oleh = '{$_SESSION['user_id']}'";
    $result_material = mysqli_query($conn, $query_material);
    
    if (mysqli_num_rows($result_material) === 0) {
        setFlashMessage('error', 'Materi tidak ditemukan atau bukan milik Anda.');
        header('Location: materials.php');
        exit;
    }
    
    $selected_material = mysqli_fetch_assoc($result_material);
}

// Process form submission for adding/editing quizzes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? sanitizeInput($_POST['id']) : generateUniqueId('QZ');
            $judul = sanitizeInput($_POST['judul']);
            $deskripsi = sanitizeInput($_POST['deskripsi'], false); // Allow HTML for rich content
            $materi_id = sanitizeInput($_POST['materi_id']);
            $kelas_id = sanitizeInput($_POST['kelas_id']);
            $deadline = !empty($_POST['tanggal_deadline']) ? sanitizeInput($_POST['tanggal_deadline']) : null;
            
            // Verify material belongs to the class and teacher
            $material_check = "SELECT kelas_id, dibuat_oleh FROM materi_coding WHERE id = '$materi_id'";
            $material_result = mysqli_query($conn, $material_check);
            
            if (mysqli_num_rows($material_result) === 0) {
                setFlashMessage('error', 'Materi tidak ditemukan.');
                header('Location: quizzes.php');
                exit;
            }
            
            $material = mysqli_fetch_assoc($material_result);
            
            if ($material['kelas_id'] != $kelas_id) {
                setFlashMessage('error', 'Materi tidak sesuai dengan kelas yang dipilih.');
                header('Location: quizzes.php');
                exit;
            }
            
            if ($_POST['action'] === 'add') {
                $query = "INSERT INTO tugas (id, materi_id, kelas_id, judul, dibuat_oleh, deskripsi, tanggal_dibuat, tanggal_deadline) 
                          VALUES ('$id', '$materi_id', '$kelas_id', '$judul', '{$_SESSION['user_id']}', '$deskripsi', CURDATE(), " . 
                          ($deadline ? "'$deadline'" : "NULL") . ")";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Quiz berhasil ditambahkan. Tambahkan soal sekarang.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'tambah_quiz', "Guru menambahkan quiz baru: $judul");
                    
                    // Redirect to quiz detail page to add questions
                    header('Location: quiz_edit.php?id=' . $id);
                    exit;
                } else {
                    setFlashMessage('error', 'Gagal menambahkan quiz: ' . mysqli_error($conn));
                }
            } else { // Edit action
                // Verify the quiz belongs to this teacher
                $check_query = "SELECT dibuat_oleh FROM tugas WHERE id = '$id'";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $quiz = mysqli_fetch_assoc($check_result);
                    
                    if ($quiz['dibuat_oleh'] === $_SESSION['user_id']) {
                        $query = "UPDATE tugas SET 
                                 judul = '$judul', 
                                 deskripsi = '$deskripsi',
                                 materi_id = '$materi_id', 
                                 kelas_id = '$kelas_id',
                                 tanggal_deadline = " . ($deadline ? "'$deadline'" : "NULL") . "
                                 WHERE id = '$id'";
                        
                        if (mysqli_query($conn, $query)) {
                            setFlashMessage('success', 'Quiz berhasil diperbarui.');
                            
                            // Log activity
                            logActivity($_SESSION['user_id'], 'edit_quiz', "Guru mengedit quiz: $judul");
                        } else {
                            setFlashMessage('error', 'Gagal memperbarui quiz: ' . mysqli_error($conn));
                        }
                    } else {
                        setFlashMessage('error', 'Anda tidak memiliki izin untuk mengedit quiz ini.');
                    }
                } else {
                    setFlashMessage('error', 'Quiz tidak ditemukan.');
                }
            }
            
            // Redirect to refresh the page
            header('Location: quizzes.php');
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = sanitizeInput($_POST['id']);
            
            // Verify the quiz belongs to this teacher
            $check_query = "SELECT judul, dibuat_oleh FROM tugas WHERE id = '$id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $quiz = mysqli_fetch_assoc($check_result);
                
                if ($quiz['dibuat_oleh'] === $_SESSION['user_id']) {
                    // Delete quiz and all associated questions
                    $conn->begin_transaction();
                    
                    try {
                        // Delete answer options for multiple choice questions
                        $query_delete_options = "DELETE po FROM pilihan_jawaban po
                                              JOIN soal_quiz sq ON po.soal_id = sq.id
                                              WHERE sq.tugas_id = '$id'";
                        $conn->query($query_delete_options);
                        
                        // Delete student answers
                        $query_delete_answers = "DELETE ja FROM jawaban_siswa ja
                                             JOIN nilai_tugas nt ON ja.nilai_tugas_id = nt.id
                                             WHERE nt.tugas_id = '$id'";
                        $conn->query($query_delete_answers);
                        
                        // Delete quiz scores
                        $query_delete_scores = "DELETE FROM nilai_tugas WHERE tugas_id = '$id'";
                        $conn->query($query_delete_scores);
                        
                        // Delete quiz questions
                        $query_delete_questions = "DELETE FROM soal_quiz WHERE tugas_id = '$id'";
                        $conn->query($query_delete_questions);
                        
                        // Delete quiz
                        $query_delete_quiz = "DELETE FROM tugas WHERE id = '$id'";
                        $conn->query($query_delete_quiz);
                        
                        $conn->commit();
                        
                        setFlashMessage('success', 'Quiz berhasil dihapus.');
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'hapus_quiz', "Guru menghapus quiz: {$quiz['judul']}");
                    } catch (Exception $e) {
                        $conn->rollback();
                        setFlashMessage('error', 'Gagal menghapus quiz: ' . $e->getMessage());
                    }
                } else {
                    setFlashMessage('error', 'Anda tidak memiliki izin untuk menghapus quiz ini.');
                }
            } else {
                setFlashMessage('error', 'Quiz tidak ditemukan.');
            }
            
            // Redirect to refresh the page
            header('Location: quizzes.php');
            exit;
        }
    }
}

// Get quiz data if edit action is requested
$edit_quiz = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitizeInput($_GET['id']);
    $query = "SELECT * FROM tugas WHERE id = '$id' AND dibuat_oleh = '{$_SESSION['user_id']}'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_quiz = mysqli_fetch_assoc($result);
        
        // Get material and class info for this quiz
        $material_query = "SELECT m.*, k.id as kelas_id, k.nama as kelas_nama 
                          FROM materi_coding m 
                          JOIN kelas k ON m.kelas_id = k.id 
                          WHERE m.id = '{$edit_quiz['materi_id']}'";
        $material_result = mysqli_query($conn, $material_query);
        
        if (mysqli_num_rows($material_result) > 0) {
            $selected_material = mysqli_fetch_assoc($material_result);
        }
    } else {
        setFlashMessage('error', 'Quiz tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.');
        header('Location: quizzes.php');
        exit;
    }
}

// Get list of materials for dropdown (only those created by this teacher)
$query_materials = "SELECT m.*, k.nama as kelas_nama 
                   FROM materi_coding m 
                   JOIN kelas k ON m.kelas_id = k.id 
                   WHERE m.dibuat_oleh = '{$_SESSION['user_id']}' 
                   ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);
$materials = [];

while ($row = mysqli_fetch_assoc($result_materials)) {
    $materials[] = $row;
}

// Get list of all quizzes created by this teacher
$query_quizzes = "SELECT t.*, m.judul as materi_judul, k.nama as kelas_nama,
                 (SELECT COUNT(*) FROM soal_quiz WHERE tugas_id = t.id) as jumlah_soal,
                 (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id) as jumlah_dikerjakan
                 FROM tugas t 
                 JOIN materi_coding m ON t.materi_id = m.id
                 JOIN kelas k ON t.kelas_id = k.id
                 WHERE t.dibuat_oleh = '{$_SESSION['user_id']}' 
                 ORDER BY t.tanggal_dibuat DESC";
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Kelola Quiz dan Tugas</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quizModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Quiz
        </button>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Quiz Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Quiz</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Materi</th>
                            <th>Kelas</th>
                            <th>Deadline</th>
                            <th>Soal</th>
                            <th>Dikerjakan</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_quizzes) > 0): ?>
                            <?php while ($quiz = mysqli_fetch_assoc($result_quizzes)): ?>
                                <tr>
                                    <td><?php echo $quiz['judul']; ?></td>
                                    <td><?php echo $quiz['materi_judul']; ?></td>
                                    <td><?php echo $quiz['kelas_nama']; ?></td>
                                    <td>
                                        <?php if ($quiz['tanggal_deadline']): ?>
                                            <span class="<?php echo (strtotime($quiz['tanggal_deadline']) < time()) ? 'text-danger' : 'text-warning'; ?>">
                                                <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Tidak ada deadline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $quiz['jumlah_soal']; ?> soal</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $quiz['jumlah_dikerjakan']; ?> siswa</span>
                                    </td>
                                    <td><?php echo formatDate($quiz['tanggal_dibuat']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="quiz_edit.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-tasks"></i>
                                            </a>
                                            <a href="quizzes.php?action=edit&id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete('<?php echo $quiz['id']; ?>', '<?php echo $quiz['judul']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Anda belum memiliki quiz atau tugas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quiz Modal -->
<div class="modal fade" id="quizModal" tabindex="-1" aria-labelledby="quizModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quizModalLabel"><?php echo $edit_quiz ? 'Edit Quiz' : 'Tambah Quiz'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="quizzes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_quiz ? 'edit' : 'add'; ?>">
                    <?php if ($edit_quiz): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_quiz['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Quiz <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo $edit_quiz ? $edit_quiz['judul'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="materi_id" class="form-label">Materi <span class="text-danger">*</span></label>
                        <select class="form-select" id="materi_id" name="materi_id" required>
                            <option value="">Pilih Materi</option>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?php echo $material['id']; ?>" 
                                        data-kelas-id="<?php echo $material['kelas_id']; ?>"
                                        data-kelas-nama="<?php echo $material['kelas_nama']; ?>"
                                        <?php echo (($edit_quiz && $edit_quiz['materi_id'] === $material['id']) || ($materi_id === $material['id'])) ? 'selected' : ''; ?>>
                                    <?php echo $material['judul'] . ' (' . $material['kelas_nama'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kelas_id" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <input type="hidden" id="kelas_id" name="kelas_id" 
                               value="<?php echo $edit_quiz ? $edit_quiz['kelas_id'] : ($selected_material['kelas_id'] ?? ''); ?>" required>
                        <input type="text" class="form-control" id="kelas_nama" 
                               value="<?php echo $selected_material['kelas_nama'] ?? ''; ?>" readonly>
                        <small class="form-text text-muted">Kelas akan otomatis dipilih berdasarkan materi.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tanggal_deadline" class="form-label">Deadline (Opsional)</label>
                        <input type="date" class="form-control" id="tanggal_deadline" name="tanggal_deadline" 
                               value="<?php echo $edit_quiz && $edit_quiz['tanggal_deadline'] ? date('Y-m-d', strtotime($edit_quiz['tanggal_deadline'])) : ''; ?>">
                        <small class="form-text text-muted">Kosongkan jika tidak ada deadline.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Quiz <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="5" required><?php echo $edit_quiz ? $edit_quiz['deskripsi'] : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Quiz Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus quiz <strong id="delete-quiz-title"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus semua soal dan jawaban siswa terkait quiz ini.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="quizzes.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-quiz-id">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Auto-open modal if edit action or add mode with material_id
if ($edit_quiz || ($add_mode && $materi_id)) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var modal = new bootstrap.Modal(document.getElementById("quizModal"));
            modal.show();
        });
    </script>';
}
?>

<script>
    // Update class when material changes
    document.addEventListener('DOMContentLoaded', function() {
        var materiSelect = document.getElementById('materi_id');
        var kelasIdInput = document.getElementById('kelas_id');
        var kelasNamaInput = document.getElementById('kelas_nama');
        
        function updateKelas() {
            var selectedOption = materiSelect.options[materiSelect.selectedIndex];
            
            if (selectedOption.value) {
                var kelasId = selectedOption.getAttribute('data-kelas-id');
                var kelasNama = selectedOption.getAttribute('data-kelas-nama');
                
                kelasIdInput.value = kelasId;
                kelasNamaInput.value = kelasNama;
            } else {
                kelasIdInput.value = '';
                kelasNamaInput.value = '';
            }
        }
        
        // Update on load
        if (materiSelect.value) {
            updateKelas();
        }
        
        // Update on change
        materiSelect.addEventListener('change', updateKelas);
    });
    
    // Confirm delete
    function confirmDelete(quizId, quizTitle) {
        document.getElementById('delete-quiz-id').value = quizId;
        document.getElementById('delete-quiz-title').textContent = quizTitle;
        
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 