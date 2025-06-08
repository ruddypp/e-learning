<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process form submission for adding/editing classes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? sanitizeInput($_POST['id']) : generateUniqueId('CLS');
            $nama = sanitizeInput($_POST['nama']);
            $tahun_ajaran = sanitizeInput($_POST['tahun_ajaran']);
            $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? sanitizeInput($_POST['wali_kelas_id']) : null;
            
            try {
                if ($_POST['action'] === 'add') {
                    $query = "INSERT INTO kelas (id, nama, tahun_ajaran, wali_kelas_id) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssss", $id, $nama, $tahun_ajaran, $wali_kelas_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        setFlashMessage('success', 'Kelas berhasil ditambahkan.');
                        
                        // Log activity with shorter activity type
                        logActivity($_SESSION['user_id'], 'add_class', "Admin menambahkan kelas baru: $nama");
                    } else {
                        setFlashMessage('error', 'Gagal menambahkan kelas: ' . mysqli_error($conn));
                    }
                } else { // Edit action
                    $query = "UPDATE kelas SET nama = ?, tahun_ajaran = ?, wali_kelas_id = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssss", $nama, $tahun_ajaran, $wali_kelas_id, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        setFlashMessage('success', 'Kelas berhasil diperbarui.');
                        
                        // Log activity with shorter activity type
                        logActivity($_SESSION['user_id'], 'edit_class', "Admin mengedit kelas: $nama");
                    } else {
                        setFlashMessage('error', 'Gagal memperbarui kelas: ' . mysqli_error($conn));
                    }
                }
            } catch (Exception $e) {
                setFlashMessage('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
            
            // Redirect to refresh the page
            header('Location: classes.php');
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = sanitizeInput($_POST['id']);
            
            try {
                // Check if the class exists
                $check_query = "SELECT nama FROM kelas WHERE id = ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "s", $id);
                mysqli_stmt_execute($stmt);
                $check_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $class = mysqli_fetch_assoc($check_result);
                    
                    // Check if there are students in this class
                    $check_students = "SELECT COUNT(*) as count FROM pengguna WHERE kelas_id = ?";
                    $stmt = mysqli_prepare($conn, $check_students);
                    mysqli_stmt_bind_param($stmt, "s", $id);
                    mysqli_stmt_execute($stmt);
                    $students_result = mysqli_stmt_get_result($stmt);
                    $students_count = mysqli_fetch_assoc($students_result)['count'];
                    
                    if ($students_count > 0) {
                        setFlashMessage('error', 'Kelas tidak dapat dihapus karena masih memiliki siswa. Pindahkan semua siswa terlebih dahulu.');
                    } else {
                        // Check if there are materials for this class
                        $check_materials = "SELECT COUNT(*) as count FROM materi_coding WHERE kelas_id = ?";
                        $stmt = mysqli_prepare($conn, $check_materials);
                        mysqli_stmt_bind_param($stmt, "s", $id);
                        mysqli_stmt_execute($stmt);
                        $materials_result = mysqli_stmt_get_result($stmt);
                        $materials_count = mysqli_fetch_assoc($materials_result)['count'];
                        
                        if ($materials_count > 0) {
                            setFlashMessage('error', 'Kelas tidak dapat dihapus karena masih memiliki materi. Hapus semua materi terlebih dahulu.');
                        } else {
                            // Delete class
                            $query = "DELETE FROM kelas WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "s", $id);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                setFlashMessage('success', 'Kelas berhasil dihapus.');
                                
                                // Log activity with shorter activity type
                                logActivity($_SESSION['user_id'], 'del_class', "Admin menghapus kelas: {$class['nama']}");
                            } else {
                                setFlashMessage('error', 'Gagal menghapus kelas. Mungkin kelas masih terkait dengan data lain.');
                            }
                        }
                    }
                } else {
                    setFlashMessage('error', 'Kelas tidak ditemukan.');
                }
            } catch (Exception $e) {
                setFlashMessage('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
            
            // Redirect to refresh the page
            header('Location: classes.php');
            exit;
        }
    }
}

// Get class data if edit action is requested
$edit_class = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitizeInput($_GET['id']);
    $query = "SELECT * FROM kelas WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_class = mysqli_fetch_assoc($result);
    }
}

// Get list of teachers for dropdown
$query_teachers = "SELECT id, nama FROM pengguna WHERE tipe_pengguna = 'guru' ORDER BY nama ASC";
$result_teachers = mysqli_query($conn, $query_teachers);
$teachers = [];

while ($row = mysqli_fetch_assoc($result_teachers)) {
    $teachers[] = $row;
}

// Get list of all classes with teacher names
$query_classes = "SELECT k.*, p.nama as wali_kelas_nama, 
                 (SELECT COUNT(*) FROM pengguna WHERE kelas_id = k.id AND tipe_pengguna = 'siswa') as jumlah_siswa 
                 FROM kelas k 
                 LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
                 ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Kelola Kelas</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Kelas
        </button>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Classes Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Kelas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kelas</th>
                            <th>Tahun Ajaran</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_classes) > 0): ?>
                            <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                <tr>
                                    <td><?php echo $class['id']; ?></td>
                                    <td><?php echo $class['nama']; ?></td>
                                    <td><?php echo $class['tahun_ajaran']; ?></td>
                                    <td><?php echo $class['wali_kelas_nama'] ?? '<span class="text-muted">Belum ditentukan</span>'; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $class['jumlah_siswa']; ?> siswa</span>
                                    </td>
                                    <td>
                                        <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <a href="classes.php?action=edit&id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete('<?php echo $class['id']; ?>', '<?php echo $class['nama']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data kelas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="classModalLabel"><?php echo $edit_class ? 'Edit Kelas' : 'Tambah Kelas'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="classes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_class ? 'edit' : 'add'; ?>">
                    <?php if ($edit_class): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_class['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" 
                               value="<?php echo $edit_class ? $edit_class['nama'] : ''; ?>" required>
                        <small class="form-text text-muted">Contoh: X-A, XI-IPA2, XII-IPS1</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tahun_ajaran" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" 
                               value="<?php echo $edit_class ? $edit_class['tahun_ajaran'] : ''; ?>" required>
                        <small class="form-text text-muted">Contoh: 2023/2024</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wali_kelas_id" class="form-label">Wali Kelas</label>
                        <select class="form-select" id="wali_kelas_id" name="wali_kelas_id">
                            <option value="">Pilih Wali Kelas</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo ($edit_class && $edit_class['wali_kelas_id'] === $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo $teacher['nama']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Delete Class Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus kelas <strong id="delete-class-name"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus data kelas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="classes.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-class-id">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Auto-open modal if edit action
if ($edit_class) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var modal = new bootstrap.Modal(document.getElementById("classModal"));
            modal.show();
        });
    </script>';
}
?>

<script>
    // Confirm delete
    function confirmDelete(classId, className) {
        document.getElementById('delete-class-id').value = classId;
        document.getElementById('delete-class-name').textContent = className;
        
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 