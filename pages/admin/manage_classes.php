<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new class
    if (isset($_POST['action']) && $_POST['action'] === 'add_class') {
        $class_id = generateID('CLS', 'kelas');
        $class_name = sanitizeInput($_POST['class_name']);
        $academic_year = sanitizeInput($_POST['academic_year']);
        $wali_kelas = !empty($_POST['wali_kelas']) ? sanitizeInput($_POST['wali_kelas']) : null;
        
        // Validate inputs
        if (empty($class_name) || empty($academic_year)) {
            setFlashMessage('error', 'Nama kelas dan tahun ajaran harus diisi.');
        } else {
            // Check if class name already exists for the academic year
            $check_query = "SELECT id FROM kelas WHERE nama = '$class_name' AND tahun_ajaran = '$academic_year'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                setFlashMessage('error', 'Kelas dengan nama dan tahun ajaran yang sama sudah ada.');
            } else {
                // Insert new class
                $query = "INSERT INTO kelas (id, nama, tahun_ajaran, wali_kelas_id, status, tanggal_dibuat) 
                         VALUES ('$class_id', '$class_name', '$academic_year', " . 
                         ($wali_kelas ? "'$wali_kelas'" : "NULL") . ", 'aktif', CURDATE())";
                
                if (mysqli_query($conn, $query)) {
                    // Log activity
                    logActivity($_SESSION['user_id'], 'tambah_materi', "Admin menambahkan kelas baru: $class_name ($academic_year)");
                    
                    // Add to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'Tambah Kelas', 'Menambahkan kelas: $class_name ($academic_year)', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                    
                    setFlashMessage('success', 'Kelas berhasil ditambahkan.');
                } else {
                    setFlashMessage('error', 'Gagal menambahkan kelas: ' . mysqli_error($conn));
                }
            }
        }
    }
    
    // Edit class
    else if (isset($_POST['action']) && $_POST['action'] === 'edit_class') {
        $class_id = sanitizeInput($_POST['class_id']);
        $class_name = sanitizeInput($_POST['class_name']);
        $academic_year = sanitizeInput($_POST['academic_year']);
        $wali_kelas = !empty($_POST['wali_kelas']) ? sanitizeInput($_POST['wali_kelas']) : null;
        $status = sanitizeInput($_POST['status']);
        
        // Validate inputs
        if (empty($class_name) || empty($academic_year)) {
            setFlashMessage('error', 'Nama kelas dan tahun ajaran harus diisi.');
        } else {
            // Check if class name already exists for the academic year (excluding this class)
            $check_query = "SELECT id FROM kelas WHERE nama = '$class_name' AND tahun_ajaran = '$academic_year' AND id != '$class_id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                setFlashMessage('error', 'Kelas dengan nama dan tahun ajaran yang sama sudah ada.');
            } else {
                // Update class
                $query = "UPDATE kelas SET 
                         nama = '$class_name', 
                         tahun_ajaran = '$academic_year', 
                         wali_kelas_id = " . ($wali_kelas ? "'$wali_kelas'" : "NULL") . ",
                         status = '$status'
                         WHERE id = '$class_id'";
                
                if (mysqli_query($conn, $query)) {
                    // Log activity
                    logActivity($_SESSION['user_id'], 'edit_materi', "Admin mengedit kelas: $class_name ($academic_year)");
                    
                    // Add to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'Edit Kelas', 'Mengedit kelas ID: $class_id', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                    
                    setFlashMessage('success', 'Kelas berhasil diperbarui.');
                } else {
                    setFlashMessage('error', 'Gagal memperbarui kelas: ' . mysqli_error($conn));
                }
            }
        }
    }
    
    // Delete class
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_class') {
        $class_id = sanitizeInput($_POST['class_id']);
        
        // Check if class has students
        $check_query = "SELECT COUNT(*) as total FROM pengguna WHERE kelas_id = '$class_id'";
        $check_result = mysqli_query($conn, $check_query);
        $row = mysqli_fetch_assoc($check_result);
        
        if ($row['total'] > 0) {
            setFlashMessage('error', 'Tidak dapat menghapus kelas karena masih ada siswa yang terdaftar.');
        } else {
            // Delete class
            $query = "DELETE FROM kelas WHERE id = '$class_id'";
            
            if (mysqli_query($conn, $query)) {
                // Log activity
                logActivity($_SESSION['user_id'], 'hapus_materi', "Admin menghapus kelas dengan ID: $class_id");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Hapus Kelas', 'Menghapus kelas ID: $class_id', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Kelas berhasil dihapus.');
            } else {
                setFlashMessage('error', 'Gagal menghapus kelas: ' . mysqli_error($conn));
            }
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header('Location: manage_classes.php');
    exit;
}

// Get all classes
$query = "SELECT k.*, p.nama as wali_kelas_nama, 
         (SELECT COUNT(*) FROM pengguna WHERE kelas_id = k.id AND tipe_pengguna = 'siswa') as jumlah_siswa
         FROM kelas k 
         LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
         ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result = mysqli_query($conn, $query);

// Get all teachers for dropdown
$query_teachers = "SELECT id, nama FROM pengguna WHERE tipe_pengguna = 'guru' ORDER BY nama ASC";
$result_teachers = mysqli_query($conn, $query_teachers);
$teachers = [];
while ($row = mysqli_fetch_assoc($result_teachers)) {
    $teachers[] = $row;
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manajemen Kelas</h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus me-2"></i> Tambah Kelas Baru
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-school me-2"></i> Daftar Kelas</h5>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kelas</th>
                                <th>Tahun Ajaran</th>
                                <th>Wali Kelas</th>
                                <th>Jumlah Siswa</th>
                                <th>Status</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($class = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo $class['id']; ?></td>
                                    <td><?php echo $class['nama']; ?></td>
                                    <td><?php echo $class['tahun_ajaran']; ?></td>
                                    <td>
                                        <?php echo $class['wali_kelas_nama'] ?? '<span class="text-muted">Belum ditentukan</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $class['jumlah_siswa']; ?> siswa</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $class['status'] === 'aktif' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($class['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($class['tanggal_dibuat']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-primary edit-class-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#editClassModal"
                                                    data-class-id="<?php echo $class['id']; ?>"
                                                    data-class-name="<?php echo $class['nama']; ?>"
                                                    data-academic-year="<?php echo $class['tahun_ajaran']; ?>"
                                                    data-wali-kelas="<?php echo $class['wali_kelas_id']; ?>"
                                                    data-status="<?php echo $class['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-class-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteClassModal"
                                                    data-class-id="<?php echo $class['id']; ?>"
                                                    data-class-name="<?php echo $class['nama']; ?>"
                                                    data-student-count="<?php echo $class['jumlah_siswa']; ?>">
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
                    <i class="fas fa-info-circle me-2"></i> Belum ada kelas yang ditambahkan.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClassModalLabel">Tambah Kelas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_classes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_class">
                    
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="contoh: 2023/2024" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wali_kelas" class="form-label">Wali Kelas</label>
                        <select class="form-select" id="wali_kelas" name="wali_kelas">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['nama']; ?></option>
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

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editClassModalLabel">Edit Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_classes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_class">
                    <input type="hidden" name="class_id" id="edit_class_id">
                    
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_academic_year" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_wali_kelas" class="form-label">Wali Kelas</label>
                        <select class="form-select" id="edit_wali_kelas" name="wali_kelas">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['nama']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
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

<!-- Delete Class Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteClassModalLabel">Hapus Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="delete_confirmation_text">Anda yakin ingin menghapus kelas ini?</p>
                <div id="delete_warning" class="alert alert-danger d-none">
                    <i class="fas fa-exclamation-triangle me-2"></i> Kelas ini memiliki siswa terdaftar. Tidak dapat dihapus.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="manage_classes.php" id="delete_class_form">
                    <input type="hidden" name="action" value="delete_class">
                    <input type="hidden" name="class_id" id="delete_class_id">
                    <button type="submit" class="btn btn-danger" id="delete_class_btn">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit class modal
    var editButtons = document.querySelectorAll('.edit-class-btn');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var classId = this.getAttribute('data-class-id');
            var className = this.getAttribute('data-class-name');
            var academicYear = this.getAttribute('data-academic-year');
            var waliKelas = this.getAttribute('data-wali-kelas');
            var status = this.getAttribute('data-status');
            
            document.getElementById('edit_class_id').value = classId;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_academic_year').value = academicYear;
            
            var waliKelasSelect = document.getElementById('edit_wali_kelas');
            if (waliKelas) {
                for (var i = 0; i < waliKelasSelect.options.length; i++) {
                    if (waliKelasSelect.options[i].value === waliKelas) {
                        waliKelasSelect.options[i].selected = true;
                        break;
                    }
                }
            } else {
                waliKelasSelect.selectedIndex = 0;
            }
            
            var statusSelect = document.getElementById('edit_status');
            for (var i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value === status) {
                    statusSelect.options[i].selected = true;
                    break;
                }
            }
        });
    });
    
    // Handle delete class modal
    var deleteButtons = document.querySelectorAll('.delete-class-btn');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var classId = this.getAttribute('data-class-id');
            var className = this.getAttribute('data-class-name');
            var studentCount = parseInt(this.getAttribute('data-student-count'));
            
            document.getElementById('delete_class_id').value = classId;
            document.getElementById('delete_confirmation_text').textContent = 'Anda yakin ingin menghapus kelas "' + className + '"?';
            
            var deleteWarning = document.getElementById('delete_warning');
            var deleteButton = document.getElementById('delete_class_btn');
            
            if (studentCount > 0) {
                deleteWarning.classList.remove('d-none');
                deleteButton.disabled = true;
            } else {
                deleteWarning.classList.add('d-none');
                deleteButton.disabled = false;
            }
        });
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 