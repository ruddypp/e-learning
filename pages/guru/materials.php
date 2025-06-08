<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Process form submission for adding/editing materials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? sanitizeInput($_POST['id']) : generateUniqueId('MTR');
            $judul = sanitizeInput($_POST['judul']);
            $deskripsi = sanitizeInput($_POST['deskripsi'], false); // Allow HTML for rich content
            $tingkat = sanitizeInput($_POST['tingkat']);
            $kelas_id = sanitizeInput($_POST['kelas_id']);
            
            if ($_POST['action'] === 'add') {
                $query = "INSERT INTO materi_coding (id, judul, deskripsi, tingkat, tanggal_dibuat, kelas_id, dibuat_oleh) 
                          VALUES ('$id', '$judul', '$deskripsi', '$tingkat', CURDATE(), '$kelas_id', '{$_SESSION['user_id']}')";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Materi coding berhasil ditambahkan.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'tambah_materi', "Guru menambahkan materi coding baru: $judul");
                } else {
                    setFlashMessage('error', 'Gagal menambahkan materi: ' . mysqli_error($conn));
                }
            } else { // Edit action
                // Verify the material belongs to this teacher
                $check_query = "SELECT dibuat_oleh FROM materi_coding WHERE id = '$id'";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $material = mysqli_fetch_assoc($check_result);
                    
                    if ($material['dibuat_oleh'] === $_SESSION['user_id']) {
                        $query = "UPDATE materi_coding SET 
                                  judul = '$judul', 
                                  deskripsi = '$deskripsi',
                                  tingkat = '$tingkat', 
                                  kelas_id = '$kelas_id'
                                  WHERE id = '$id'";
                        
                        if (mysqli_query($conn, $query)) {
                            setFlashMessage('success', 'Materi coding berhasil diperbarui.');
                            
                            // Log activity
                            logActivity($_SESSION['user_id'], 'edit_materi', "Guru mengedit materi coding: $judul");
                        } else {
                            setFlashMessage('error', 'Gagal memperbarui materi: ' . mysqli_error($conn));
                        }
                    } else {
                        setFlashMessage('error', 'Anda tidak memiliki izin untuk mengedit materi ini.');
                    }
                } else {
                    setFlashMessage('error', 'Materi tidak ditemukan.');
                }
            }
            
            // Redirect to refresh the page
            header('Location: materials.php');
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = sanitizeInput($_POST['id']);
            
            // Verify the material belongs to this teacher
            $check_query = "SELECT judul, dibuat_oleh FROM materi_coding WHERE id = '$id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $material = mysqli_fetch_assoc($check_result);
                
                if ($material['dibuat_oleh'] === $_SESSION['user_id']) {
                    // Delete material
                    $query = "DELETE FROM materi_coding WHERE id = '$id'";
                    
                    if (mysqli_query($conn, $query)) {
                        setFlashMessage('success', 'Materi coding berhasil dihapus.');
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'hapus_materi', "Guru menghapus materi coding: {$material['judul']}");
                    } else {
                        setFlashMessage('error', 'Gagal menghapus materi. Mungkin materi masih terkait dengan data lain.');
                    }
                } else {
                    setFlashMessage('error', 'Anda tidak memiliki izin untuk menghapus materi ini.');
                }
            } else {
                setFlashMessage('error', 'Materi tidak ditemukan.');
            }
            
            // Redirect to refresh the page
            header('Location: materials.php');
            exit;
        }
    }
}

// Get material data if edit action is requested
$edit_material = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitizeInput($_GET['id']);
    $query = "SELECT * FROM materi_coding WHERE id = '$id' AND dibuat_oleh = '{$_SESSION['user_id']}'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_material = mysqli_fetch_assoc($result);
    } else {
        setFlashMessage('error', 'Materi tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.');
        header('Location: materials.php');
        exit;
    }
}

// Get list of classes for dropdown
$query_classes = "SELECT id, nama, tahun_ajaran FROM kelas ORDER BY nama ASC";
$result_classes = mysqli_query($conn, $query_classes);
$classes = [];

while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes[] = $row;
}

// Get list of teacher's materials
$query_materials = "SELECT m.*, k.nama as kelas_nama 
                   FROM materi_coding m 
                   JOIN kelas k ON m.kelas_id = k.id 
                   WHERE m.dibuat_oleh = '{$_SESSION['user_id']}' 
                   ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Kelola Materi Coding</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#materialModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Materi
        </button>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Materials Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Materi Coding</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Tingkat</th>
                            <th>Kelas</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_materials) > 0): ?>
                            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                <tr>
                                    <td><?php echo $material['judul']; ?></td>
                                    <td><?php echo $material['tingkat']; ?></td>
                                    <td><?php echo $material['kelas_nama']; ?></td>
                                    <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                    <td>
                                        <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="materials.php?action=edit&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete('<?php echo $material['id']; ?>', '<?php echo $material['judul']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Anda belum memiliki materi coding.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Material Modal -->
<div class="modal fade" id="materialModal" tabindex="-1" aria-labelledby="materialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="materialModalLabel"><?php echo $edit_material ? 'Edit Materi Coding' : 'Tambah Materi Coding'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="materials.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_material ? 'edit' : 'add'; ?>">
                    <?php if ($edit_material): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_material['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo $edit_material ? $edit_material['judul'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tingkat" class="form-label">Tingkat Kesulitan <span class="text-danger">*</span></label>
                        <select class="form-select" id="tingkat" name="tingkat" required>
                            <option value="">Pilih Tingkat Kesulitan</option>
                            <option value="Pemula" <?php echo ($edit_material && $edit_material['tingkat'] === 'Pemula') ? 'selected' : ''; ?>>Pemula</option>
                            <option value="Menengah" <?php echo ($edit_material && $edit_material['tingkat'] === 'Menengah') ? 'selected' : ''; ?>>Menengah</option>
                            <option value="Lanjutan" <?php echo ($edit_material && $edit_material['tingkat'] === 'Lanjutan') ? 'selected' : ''; ?>>Lanjutan</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kelas_id" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" id="kelas_id" name="kelas_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                        <?php echo ($edit_material && $edit_material['kelas_id'] === $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Materi <span class="text-danger">*</span></label>
                        <textarea class="form-control rich-editor" id="deskripsi" name="deskripsi" rows="10" required><?php echo $edit_material ? $edit_material['deskripsi'] : ''; ?></textarea>
                        <small class="form-text text-muted">
                            Gunakan editor untuk menambahkan konten materi. Anda dapat menyisipkan kode, gambar, dan konten lainnya.
                        </small>
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

<!-- Delete Material Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus materi <strong id="delete-material-title"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus semua data terkait materi ini.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="materials.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-material-id">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Auto-open modal if edit action
if ($edit_material) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var modal = new bootstrap.Modal(document.getElementById("materialModal"));
            modal.show();
        });
    </script>';
}
?>

<script>
    // Initialize rich text editor
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof tinymce !== 'undefined') {
            tinymce.init({
                selector: '.rich-editor',
                height: 400,
                plugins: [
                    'advlist autolink lists link image charmap print preview anchor',
                    'searchreplace visualblocks code fullscreen',
                    'insertdatetime media table paste code help wordcount'
                ],
                toolbar: 'undo redo | formatselect | ' +
                        'bold italic backcolor | alignleft aligncenter ' +
                        'alignright alignjustify | bullist numlist outdent indent | ' +
                        'removeformat | code | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }'
            });
        } else {
            console.log('TinyMCE not loaded');
        }
    });
    
    // Confirm delete
    function confirmDelete(materialId, materialTitle) {
        document.getElementById('delete-material-id').value = materialId;
        document.getElementById('delete-material-title').textContent = materialTitle;
        
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 