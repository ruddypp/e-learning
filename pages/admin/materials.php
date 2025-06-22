<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process form submission for adding/editing materials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? sanitizeInput($_POST['id']) : generateUniqueId('MTR');
            $judul = sanitizeInput($_POST['judul']);
            $deskripsi = sanitizeInput($_POST['deskripsi'], false); // Allow HTML for rich content
            $tingkat = sanitizeInput($_POST['tingkat']);
            $kelas_id = sanitizeInput($_POST['kelas_id']);
            
            // Process image upload if present
            $image_url = null;
            if (isset($_FILES['material_image']) && $_FILES['material_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/materials/images/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['material_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'material_' . $id . '_' . time() . '.' . $file_extension;
                $upload_file = $upload_dir . $file_name;
                
                // Check file size (max 2MB)
                $max_size = 2 * 1024 * 1024; // 2MB in bytes
                if ($_FILES['material_image']['size'] <= $max_size) {
                    // Check if the file is an image
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array(strtolower($file_extension), $allowed_types)) {
                        if (move_uploaded_file($_FILES['material_image']['tmp_name'], $upload_file)) {
                            $image_url = 'uploads/materials/images/' . $file_name;
                        } else {
                            setFlashMessage('warning', 'Gagal mengunggah gambar. Materi tetap disimpan tanpa gambar.');
                        }
                    } else {
                        setFlashMessage('warning', 'Format gambar tidak didukung. Hanya file JPG, JPEG, PNG, dan GIF yang diperbolehkan.');
                    }
                } else {
                    setFlashMessage('warning', 'Ukuran gambar terlalu besar. Maksimal 2MB.');
                }
            } elseif (isset($_POST['existing_image']) && $_POST['existing_image'] !== '' && !isset($_POST['remove_image'])) {
                // Keep existing image if editing and not removing
                $image_url = sanitizeInput($_POST['existing_image']);
            }
            
            // Remove existing image if checkbox is checked
            if (isset($_POST['remove_image']) && isset($_POST['existing_image']) && $_POST['existing_image'] !== '') {
                $old_image_path = '../../' . $_POST['existing_image'];
                if (file_exists($old_image_path)) {
                    @unlink($old_image_path);
                }
                $image_url = null;
            }
            
            if ($_POST['action'] === 'add') {
                $query = "INSERT INTO materi_coding (id, judul, deskripsi, image_url, tingkat, tanggal_dibuat, kelas_id, dibuat_oleh) 
                          VALUES ('$id', '$judul', '$deskripsi', " . ($image_url ? "'$image_url'" : "NULL") . ", '$tingkat', CURDATE(), '$kelas_id', '{$_SESSION['user_id']}')";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Materi coding berhasil ditambahkan.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'tambah_materi', "Admin menambahkan materi coding baru: $judul");
                } else {
                    setFlashMessage('error', 'Gagal menambahkan materi: ' . mysqli_error($conn));
                }
            } else { // Edit action
                // Get the material to check
                $check_query = "SELECT dibuat_oleh, image_url FROM materi_coding WHERE id = '$id'";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $material = mysqli_fetch_assoc($check_result);
                    
                    // If uploading new image and there was an old one, delete it
                    if ($image_url && !empty($material['image_url']) && $material['image_url'] !== $image_url) {
                        $old_image_path = '../../' . $material['image_url'];
                        if (file_exists($old_image_path)) {
                            @unlink($old_image_path);
                        }
                    }
                    
                    $query = "UPDATE materi_coding SET 
                              judul = '$judul', 
                              deskripsi = '$deskripsi',
                              image_url = " . ($image_url ? "'$image_url'" : "NULL") . ",
                              tingkat = '$tingkat', 
                              kelas_id = '$kelas_id'
                              WHERE id = '$id'";
                    
                    if (mysqli_query($conn, $query)) {
                        setFlashMessage('success', 'Materi coding berhasil diperbarui.');
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'edit_materi', "Admin mengedit materi coding: $judul");
                    } else {
                        setFlashMessage('error', 'Gagal memperbarui materi: ' . mysqli_error($conn));
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
            
            // Get material info before deletion
            $check_query = "SELECT judul, image_url FROM materi_coding WHERE id = '$id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $material = mysqli_fetch_assoc($check_result);
                
                // Delete associated image if exists
                if (!empty($material['image_url'])) {
                    $image_path = '../../' . $material['image_url'];
                    if (file_exists($image_path)) {
                        @unlink($image_path);
                    }
                }
                
                // Delete material
                $query = "DELETE FROM materi_coding WHERE id = '$id'";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Materi coding berhasil dihapus.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'hapus_materi', "Admin menghapus materi coding: {$material['judul']}");
                } else {
                    setFlashMessage('error', 'Gagal menghapus materi. Mungkin materi masih terkait dengan data lain.');
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
    $query = "SELECT * FROM materi_coding WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_material = mysqli_fetch_assoc($result);
    } else {
        setFlashMessage('error', 'Materi tidak ditemukan.');
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

// Apply filters if set
$where_clauses = [];
$filter_params = [];

if (isset($_GET['kelas_filter']) && !empty($_GET['kelas_filter'])) {
    $kelas_filter = sanitizeInput($_GET['kelas_filter']);
    $where_clauses[] = "m.kelas_id = '$kelas_filter'";
    $filter_params[] = "kelas_filter=$kelas_filter";
}

if (isset($_GET['tingkat_filter']) && !empty($_GET['tingkat_filter'])) {
    $tingkat_filter = sanitizeInput($_GET['tingkat_filter']);
    $where_clauses[] = "m.tingkat = '$tingkat_filter'";
    $filter_params[] = "tingkat_filter=$tingkat_filter";
}

// Get list of all materials
$query_materials = "SELECT m.*, k.nama as kelas_nama, p.nama as created_by_name
                   FROM materi_coding m 
                   JOIN kelas k ON m.kelas_id = k.id
                   JOIN pengguna p ON m.dibuat_oleh = p.id";

// Add WHERE clause if filters are applied
if (!empty($where_clauses)) {
    $query_materials .= " WHERE " . implode(" AND ", $where_clauses);
}

$query_materials .= " ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Kelola Materi Coding</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#materialModal">
                <i class="fas fa-plus-circle me-2"></i> Tambah Materi
            </button>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php
        // Get total materials count
        $total_materials_query = "SELECT COUNT(*) as count FROM materi_coding";
        $total_materials_result = mysqli_query($conn, $total_materials_query);
        $total_materials = mysqli_fetch_assoc($total_materials_result)['count'];
        
        // Get materials by difficulty level
        $difficulty_query = "SELECT tingkat, COUNT(*) as count FROM materi_coding GROUP BY tingkat";
        $difficulty_result = mysqli_query($conn, $difficulty_query);
        $difficulty_counts = [
            'Pemula' => 0,
            'Menengah' => 0,
            'Lanjutan' => 0
        ];
        
        while ($row = mysqli_fetch_assoc($difficulty_result)) {
            $difficulty_counts[$row['tingkat']] = $row['count'];
        }
        
        // Get total classes with materials
        $classes_query = "SELECT COUNT(DISTINCT kelas_id) as count FROM materi_coding";
        $classes_result = mysqli_query($conn, $classes_query);
        $classes_count = mysqli_fetch_assoc($classes_result)['count'];
        
        // Get total creators
        $creators_query = "SELECT COUNT(DISTINCT dibuat_oleh) as count FROM materi_coding";
        $creators_result = mysqli_query($conn, $creators_query);
        $creators_count = mysqli_fetch_assoc($creators_result)['count'];
        ?>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Materi</h5>
                    <p class="card-text display-4"><?php echo $total_materials; ?></p>
                    <p class="card-text text-muted">Materi pembelajaran coding</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <h5 class="card-title">Tingkat Kesulitan</h5>
                    <div class="mt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Pemula</span>
                            <span class="badge bg-success"><?php echo $difficulty_counts['Pemula']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Menengah</span>
                            <span class="badge bg-warning"><?php echo $difficulty_counts['Menengah']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Lanjutan</span>
                            <span class="badge bg-danger"><?php echo $difficulty_counts['Lanjutan']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title">Kelas</h5>
                    <p class="card-text display-4"><?php echo $classes_count; ?></p>
                    <p class="card-text text-muted">Kelas dengan materi</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <h5 class="card-title">Kontributor</h5>
                    <p class="card-text display-4"><?php echo $creators_count; ?></p>
                    <p class="card-text text-muted">Guru & admin yang membuat materi</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Materials Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Materi Coding</h5>
            <div>
                <button class="btn btn-sm btn-info text-white" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="collapse" id="filterCollapse">
            <div class="card-body border-bottom">
                <form method="GET" action="materials.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="kelas_filter" class="form-label">Kelas</label>
                        <select class="form-select" id="kelas_filter" name="kelas_filter">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo (isset($_GET['kelas_filter']) && $_GET['kelas_filter'] === $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tingkat_filter" class="form-label">Tingkat Kesulitan</label>
                        <select class="form-select" id="tingkat_filter" name="tingkat_filter">
                            <option value="">Semua Tingkat</option>
                            <option value="Pemula" <?php echo (isset($_GET['tingkat_filter']) && $_GET['tingkat_filter'] === 'Pemula') ? 'selected' : ''; ?>>Pemula</option>
                            <option value="Menengah" <?php echo (isset($_GET['tingkat_filter']) && $_GET['tingkat_filter'] === 'Menengah') ? 'selected' : ''; ?>>Menengah</option>
                            <option value="Lanjutan" <?php echo (isset($_GET['tingkat_filter']) && $_GET['tingkat_filter'] === 'Lanjutan') ? 'selected' : ''; ?>>Lanjutan</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> Terapkan Filter
                        </button>
                        <a href="materials.php" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Tingkat</th>
                            <th>Kelas</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_materials) > 0): ?>
                            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                <tr>
                                    <td><?php echo $material['judul']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $material['tingkat'] === 'Pemula' ? 'success' : 
                                                ($material['tingkat'] === 'Menengah' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo $material['tingkat']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $material['kelas_nama']; ?></td>
                                    <td><?php echo $material['created_by_name']; ?></td>
                                    <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="materials.php?action=edit&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info" title="Edit Materi">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete('<?php echo $material['id']; ?>', '<?php echo $material['judul']; ?>')" title="Hapus Materi">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Belum ada materi coding.</td>
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
            <form method="POST" action="materials.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_material ? 'edit' : 'add'; ?>">
                    <?php if ($edit_material): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_material['id']; ?>">
                        <?php if (!empty($edit_material['image_url'])): ?>
                            <input type="hidden" name="existing_image" value="<?php echo $edit_material['image_url']; ?>">
                        <?php endif; ?>
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
                    
                    <!-- Image Upload Field -->
                    <div class="mb-3">
                        <label for="material_image" class="form-label">Gambar (Opsional)</label>
                        <?php if ($edit_material && !empty($edit_material['image_url'])): ?>
                            <div class="mb-2">
                                <img src="../../<?php echo $edit_material['image_url']; ?>" class="img-thumbnail" style="max-height: 200px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                    <label class="form-check-label" for="remove_image">
                                        Hapus gambar ini
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="material_image" name="material_image" accept="image/*">
                        <small class="form-text text-muted">Format yang didukung: JPG, JPEG, PNG, GIF. Maksimal 2MB.</small>
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