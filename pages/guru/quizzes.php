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
                $query = "INSERT INTO tugas (id, materi_id, kelas_id, judul, dibuat_oleh, deskripsi, tanggal_dibuat, tanggal_deadline, status) 
                          VALUES ('$id', '$materi_id', '$kelas_id', '$judul', '{$_SESSION['user_id']}', '$deskripsi', CURDATE(), " . 
                          ($deadline ? "'$deadline'" : "NULL") . ", 'draft')";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Quiz berhasil ditambahkan. Tambahkan soal sekarang.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'tambah_materi', "Guru menambahkan quiz baru: $judul");
                    
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
                            logActivity($_SESSION['user_id'], 'edit_materi', "Guru mengedit quiz: $judul");
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
                        logActivity($_SESSION['user_id'], 'hapus_materi', "Guru menghapus quiz: {$quiz['judul']}");
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
        } elseif ($_POST['action'] === 'update_status' && isset($_POST['id']) && isset($_POST['status'])) {
            $id = sanitizeInput($_POST['id']);
            $status = sanitizeInput($_POST['status']);
            
            // Verify the quiz belongs to this teacher
            $check_query = "SELECT judul, dibuat_oleh FROM tugas WHERE id = '$id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $quiz = mysqli_fetch_assoc($check_result);
                
                if ($quiz['dibuat_oleh'] === $_SESSION['user_id']) {
                    // Update quiz status
                    $query = "UPDATE tugas SET status = '$status' WHERE id = '$id'";
                    
                    if (mysqli_query($conn, $query)) {
                        if ($status === 'published') {
                            setFlashMessage('success', 'Quiz berhasil dipublikasikan dan sekarang tersedia untuk siswa.');
                            logActivity($_SESSION['user_id'], 'publish_quiz', "Guru mempublikasikan quiz: {$quiz['judul']}", $id);
                        } elseif ($status === 'closed') {
                            setFlashMessage('success', 'Quiz ditutup dan tidak lagi tersedia untuk pengerjaan baru.');
                            logActivity($_SESSION['user_id'], 'close_quiz', "Guru menutup quiz: {$quiz['judul']}", $id);
                        } else {
                            setFlashMessage('success', 'Status quiz berhasil diperbarui menjadi draft.');
                            logActivity($_SESSION['user_id'], 'tambah_materi', "Guru mengubah status quiz menjadi draft: {$quiz['judul']}", $id);
                        }
                    } else {
                        setFlashMessage('error', 'Gagal memperbarui status quiz: ' . mysqli_error($conn));
                    }
                } else {
                    setFlashMessage('error', 'Anda tidak memiliki izin untuk mengubah status quiz ini.');
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
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Judul</th>
                            <th>Materi</th>
                            <th>Kelas</th>
                            <th>Jumlah Soal</th>
                            <th>Tanggal Dibuat</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th style="width: 80px;">Aksi</th>
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
                                        <span class="badge bg-<?php echo ($quiz['jumlah_soal'] > 0) ? 'success' : 'danger'; ?>">
                                            <?php echo $quiz['jumlah_soal']; ?> Soal
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($quiz['tanggal_dibuat']); ?></td>
                                    <td>
                                        <?php if ($quiz['tanggal_deadline']): ?>
                                            <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'secondary';
                                        $statusText = 'Draft';
                                        
                                        if ($quiz['status'] === 'published') {
                                            $statusClass = 'success';
                                            $statusText = 'Dipublikasi';
                                        } else if ($quiz['status'] === 'closed') {
                                            $statusClass = 'danger';
                                            $statusText = 'Ditutup';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary btn-action" 
                                               onclick="showActionModal('<?php echo $quiz['id']; ?>', '<?php echo htmlspecialchars($quiz['judul'], ENT_QUOTES); ?>', '<?php echo $quiz['status']; ?>', <?php echo $quiz['jumlah_soal']; ?>)">
                                            <i class="fas fa-ellipsis-h"></i> Aksi
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Anda belum memiliki quiz.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Aksi untuk Quiz: <span id="quiz-title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <a href="#" id="view-detail-btn" class="btn btn-info">
                        <i class="fas fa-eye fa-fw me-1"></i> Lihat Detail
                    </a>
                    <a href="#" id="edit-soal-btn" class="btn btn-primary">
                        <i class="fas fa-edit fa-fw me-1"></i> Edit Soal
                    </a>
                    <a href="#" id="edit-pengaturan-btn" class="btn btn-secondary">
                        <i class="fas fa-cog fa-fw me-1"></i> Edit Pengaturan
                    </a>
                    
                    <div id="publish-btn-container" class="d-none mt-3">
                        <button type="button" id="publish-btn" class="btn btn-success w-100" 
                                onclick="submitAction('update_status', '', 'published', 'Apakah Anda yakin ingin mempublikasikan quiz ini? Quiz akan tersedia untuk siswa.')">
                            <i class="fas fa-check-circle fa-fw me-1"></i> Publikasikan
                        </button>
                    </div>
                    
                    <div id="close-btn-container" class="d-none mt-3">
                        <button type="button" id="close-btn" class="btn btn-warning w-100" 
                                onclick="submitAction('update_status', '', 'closed', 'Apakah Anda yakin ingin menutup quiz ini? Siswa tidak akan bisa mengerjakan quiz ini lagi.')">
                            <i class="fas fa-lock fa-fw me-1"></i> Tutup Quiz
                        </button>
                    </div>
                    
                    <div id="reopen-btn-container" class="d-none mt-3">
                        <button type="button" id="reopen-btn" class="btn btn-outline-primary w-100" 
                                onclick="submitAction('update_status', '', 'published', 'Apakah Anda yakin ingin membuka kembali quiz ini?')">
                            <i class="fas fa-unlock fa-fw me-1"></i> Buka Kembali
                        </button>
                    </div>
                    
                    <hr class="mt-3 mb-3">
                    
                    <button type="button" class="btn btn-outline-danger" 
                            onclick="submitAction('delete', '', '', 'PERINGATAN: Tindakan ini akan menghapus quiz dan semua soal terkait. Data nilai siswa juga akan dihapus. Lanjutkan?')">
                        <i class="fas fa-trash fa-fw me-1"></i> Hapus Quiz
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
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

<!-- Hidden Form for Actions -->
<form id="action_form" method="POST" action="quizzes.php" style="display: none;">
    <input type="hidden" id="action_type" name="action" value="">
    <input type="hidden" id="action_id" name="id" value="">
    <input type="hidden" id="action_status" name="status" value="">
</form>

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

<style>
    /* Memastikan dropdown menu berada di atas konten lain */
    .modal-backdrop {
        z-index: 1040;
    }
    
    .modal {
        z-index: 1050;
    }
    
    /* Memperbaiki tampilan tombol aksi */
    .btn-action {
        min-width: 80px;
    }
    
    /* Memastikan tabel responsif */
    .table-responsive {
        overflow-x: auto;
    }
    
    /* Memastikan kolom aksi tidak terlalu lebar */
    .table th:last-child,
    .table td:last-child {
        width: 100px;
        min-width: 100px;
    }
    
    /* Memastikan semua baris memiliki tinggi yang sama */
    .table tbody tr {
        height: 60px;
    }
    
    /* Styling untuk modal aksi */
    #actionModal .btn {
        text-align: left;
        padding: 10px 15px;
    }
    
    #actionModal .modal-body {
        padding: 20px;
    }
    
    #actionModal .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    #actionModal .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
    }
</style>

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
        
        // Initialize action modal
        window.actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
    });
    
    // Show action modal for a quiz
    function showActionModal(quizId, quizTitle, quizStatus, questionCount) {
        // Set the quiz title in the modal
        document.getElementById('quiz-title').textContent = quizTitle;
        
        // Update all button URLs and actions with the quiz ID
        document.getElementById('view-detail-btn').href = 'quiz_detail.php?id=' + quizId;
        document.getElementById('edit-soal-btn').href = 'quiz_edit.php?id=' + quizId;
        document.getElementById('edit-pengaturan-btn').href = 'quizzes.php?action=edit&id=' + quizId;
        
        // Update publish button
        var publishContainer = document.getElementById('publish-btn-container');
        if (quizStatus !== 'published' && questionCount > 0) {
            publishContainer.classList.remove('d-none');
            document.getElementById('publish-btn').onclick = function() {
                submitAction('update_status', quizId, 'published', 'Apakah Anda yakin ingin mempublikasikan quiz ini? Quiz akan tersedia untuk siswa.');
            };
        } else {
            publishContainer.classList.add('d-none');
        }
        
        // Update close button
        var closeContainer = document.getElementById('close-btn-container');
        if (quizStatus === 'published') {
            closeContainer.classList.remove('d-none');
            document.getElementById('close-btn').onclick = function() {
                submitAction('update_status', quizId, 'closed', 'Apakah Anda yakin ingin menutup quiz ini? Siswa tidak akan bisa mengerjakan quiz ini lagi.');
            };
        } else {
            closeContainer.classList.add('d-none');
        }
        
        // Update reopen button
        var reopenContainer = document.getElementById('reopen-btn-container');
        if (quizStatus === 'closed') {
            reopenContainer.classList.remove('d-none');
            document.getElementById('reopen-btn').onclick = function() {
                submitAction('update_status', quizId, 'published', 'Apakah Anda yakin ingin membuka kembali quiz ini?');
            };
        } else {
            reopenContainer.classList.add('d-none');
        }
        
        // Update delete button
        var deleteBtn = document.querySelector('.btn-outline-danger');
        deleteBtn.onclick = function() {
            submitAction('delete', quizId, '', 'PERINGATAN: Tindakan ini akan menghapus quiz dan semua soal terkait. Data nilai siswa juga akan dihapus. Lanjutkan?');
        };
        
        // Show the modal
        window.actionModal.show();
    }
    
    // Function to submit form action via hidden form
    function submitAction(action, id, status, confirmMessage) {
        if (confirmMessage && !confirm(confirmMessage)) {
            return false;
        }
        
        document.getElementById('action_type').value = action;
        document.getElementById('action_id').value = id;
        document.getElementById('action_status').value = status;
        document.getElementById('action_form').submit();
    }
    
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