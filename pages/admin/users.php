<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process form submission for adding/editing users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = isset($_POST['id']) ? sanitizeInput($_POST['id']) : 'U' . substr(md5(uniqid()), 0, 8);
            $nama = sanitizeInput($_POST['nama']);
            $email = sanitizeInput($_POST['email']);
            $tipe_pengguna = sanitizeInput($_POST['tipe_pengguna']);
            $kelas_id = !empty($_POST['kelas_id']) ? sanitizeInput($_POST['kelas_id']) : null;
            $nisn = !empty($_POST['nisn']) ? sanitizeInput($_POST['nisn']) : null;
            $nuptk = !empty($_POST['nuptk']) ? sanitizeInput($_POST['nuptk']) : null;
            
            // Validate input based on user type
            $error = '';
            if ($tipe_pengguna === 'siswa' && (empty($nisn) || empty($kelas_id))) {
                $error = 'NISN dan Kelas harus diisi untuk akun siswa.';
            } elseif ($tipe_pengguna === 'guru' && empty($nuptk)) {
                $error = 'NUPTK harus diisi untuk akun guru.';
            } elseif ($tipe_pengguna === 'kepsek' && empty($nuptk)) {
                $error = 'NUPTK harus diisi untuk akun kepala sekolah.';
            }
            
            if (!empty($error)) {
                setFlashMessage('error', $error);
                header('Location: users.php');
                exit;
            }
            
            // Handle password (only hash if it's provided for edit)
            if ($_POST['action'] === 'add' || !empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_sql = ", password = '$password'";
            } else {
                $password_sql = "";
            }
            
            if ($_POST['action'] === 'add') {
                // Check if email, NISN, or NUPTK already exists
                $check_query = "SELECT id FROM pengguna WHERE email = '$email' OR 
                               (nisn = '$nisn' AND nisn IS NOT NULL AND '$nisn' != '') OR 
                               (nuptk = '$nuptk' AND nuptk IS NOT NULL AND '$nuptk' != '')";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    setFlashMessage('error', 'Email, NISN, atau NUPTK sudah terdaftar.');
                    header('Location: users.php');
                    exit;
                }
                
                $query = "INSERT INTO pengguna (id, nama, email, password, tipe_pengguna, kelas_id, nisn, nuptk, tanggal_daftar) 
                          VALUES ('$id', '$nama', '$email', '$password', '$tipe_pengguna', " . 
                          ($kelas_id ? "'$kelas_id'" : "NULL") . ", " . 
                          ($nisn ? "'$nisn'" : "NULL") . ", " . 
                          ($nuptk ? "'$nuptk'" : "NULL") . ", CURDATE())";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Pengguna berhasil ditambahkan.');
                    
                    // Log activity with allowed activity type
                    logActivity($_SESSION['user_id'], 'tambah_materi', "Admin menambahkan pengguna baru: $nama");
                    
                    // Log to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'tambah_pengguna', 'Admin menambahkan pengguna baru: $nama (ID: $id)', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                } else {
                    setFlashMessage('error', 'Gagal menambahkan pengguna: ' . mysqli_error($conn));
                }
            } else { // Edit action
                // Check for email, NISN, or NUPTK duplicates (excluding current user)
                $check_query = "SELECT id FROM pengguna WHERE 
                               (email = '$email' AND id != '$id') OR 
                               (nisn = '$nisn' AND nisn IS NOT NULL AND '$nisn' != '' AND id != '$id') OR 
                               (nuptk = '$nuptk' AND nuptk IS NOT NULL AND '$nuptk' != '' AND id != '$id')";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    setFlashMessage('error', 'Email, NISN, atau NUPTK sudah terdaftar pada pengguna lain.');
                    header('Location: users.php');
                    exit;
                }
                
                $query = "UPDATE pengguna SET 
                          nama = '$nama', 
                          email = '$email',
                          tipe_pengguna = '$tipe_pengguna', 
                          kelas_id = " . ($kelas_id ? "'$kelas_id'" : "NULL") . ", 
                          nisn = " . ($nisn ? "'$nisn'" : "NULL") . ", 
                          nuptk = " . ($nuptk ? "'$nuptk'" : "NULL") . 
                          $password_sql . 
                          " WHERE id = '$id'";
                
                if (mysqli_query($conn, $query)) {
                    setFlashMessage('success', 'Pengguna berhasil diperbarui.');
                    
                    // Log activity with allowed activity type
                    logActivity($_SESSION['user_id'], 'verifikasi', "Admin mengedit pengguna: $nama");
                    
                    // Log to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'edit_pengguna', 'Admin mengedit pengguna: $nama (ID: $id)', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                } else {
                    setFlashMessage('error', 'Gagal memperbarui pengguna: ' . mysqli_error($conn));
                }
            }
            
            // Redirect to refresh the page
            header('Location: users.php');
            exit;
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = sanitizeInput($_POST['id']);
            
            // Check if the user exists
            try {
                $check_query = "SELECT nama FROM pengguna WHERE id = ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "s", $id);
                mysqli_stmt_execute($stmt);
                $check_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $user = mysqli_fetch_assoc($check_result);
                    
                    // Don't allow deleting the currently logged-in user
                    if ($id === $_SESSION['user_id']) {
                        setFlashMessage('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
                    } else {
                        // Check if user has related data
                        $has_related_data = false;
                        
                        // Check if teacher is assigned to classes
                        $check_classes = "SELECT id FROM kelas WHERE wali_kelas_id = ?";
                        $stmt = mysqli_prepare($conn, $check_classes);
                        mysqli_stmt_bind_param($stmt, "s", $id);
                        mysqli_stmt_execute($stmt);
                        $result_classes = mysqli_stmt_get_result($stmt);
                        if (mysqli_num_rows($result_classes) > 0) {
                            $has_related_data = true;
                        }
                        
                        // Check if user has created materials
                        $check_materials = "SELECT id FROM materi_coding WHERE dibuat_oleh = ?";
                        $stmt = mysqli_prepare($conn, $check_materials);
                        mysqli_stmt_bind_param($stmt, "s", $id);
                        mysqli_stmt_execute($stmt);
                        $result_materials = mysqli_stmt_get_result($stmt);
                        if (mysqli_num_rows($result_materials) > 0) {
                            $has_related_data = true;
                        }
                        
                        // Check if student has quiz results
                        $check_results = "SELECT id FROM nilai_tugas WHERE siswa_id = ?";
                        $stmt = mysqli_prepare($conn, $check_results);
                        mysqli_stmt_bind_param($stmt, "s", $id);
                        mysqli_stmt_execute($stmt);
                        $result_results = mysqli_stmt_get_result($stmt);
                        if (mysqli_num_rows($result_results) > 0) {
                            $has_related_data = true;
                        }
                        
                        if ($has_related_data) {
                            setFlashMessage('error', 'Pengguna tidak dapat dihapus karena masih memiliki data terkait. Hapus data terkait terlebih dahulu.');
                        } else {
                            // Delete user
                            $query = "DELETE FROM pengguna WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "s", $id);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                setFlashMessage('success', 'Pengguna berhasil dihapus.');
                                
                                // Log activity with allowed activity type
                                logActivity($_SESSION['user_id'], 'hapus_materi', "Admin menghapus pengguna: {$user['nama']}");
                                
                                // Log to system log
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                            VALUES (?, 'hapus_pengguna', ?, ?, ?)";
                                $stmt = mysqli_prepare($conn, $log_query);
                                $detail = "Admin menghapus pengguna: {$user['nama']} (ID: $id)";
                                mysqli_stmt_bind_param($stmt, "ssss", $_SESSION['user_id'], $detail, $ip, $user_agent);
                                mysqli_stmt_execute($stmt);
                            } else {
                                setFlashMessage('error', 'Gagal menghapus pengguna: ' . mysqli_error($conn));
                            }
                        }
                    }
                } else {
                    setFlashMessage('error', 'Pengguna tidak ditemukan.');
                }
            } catch (Exception $e) {
                setFlashMessage('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
            
            // Redirect to refresh the page
            header('Location: users.php');
            exit;
        }
    }
}

// Get user data if edit action is requested
$edit_user = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitizeInput($_GET['id']);
    $query = "SELECT * FROM pengguna WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_user = mysqli_fetch_assoc($result);
    }
}

// Get list of classes for dropdown
$query_classes = "SELECT id, nama, tahun_ajaran FROM kelas ORDER BY nama ASC";
$result_classes = mysqli_query($conn, $query_classes);
$classes = [];

while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes[] = $row;
}

// Get list of all users
$query_users = "SELECT p.*, k.nama as kelas_nama 
                FROM pengguna p 
                LEFT JOIN kelas k ON p.kelas_id = k.id 
                ORDER BY p.tipe_pengguna, p.nama ASC";
$result_users = mysqli_query($conn, $query_users);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Kelola Pengguna</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="fas fa-plus-circle me-2"></i> Tambah Pengguna
        </button>
    </div>
    
    <!-- Display flash messages -->
    <?php displayFlashMessage(); ?>
    
    <!-- Users Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Pengguna</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Tipe</th>
                            <th>Kelas</th>
                            <th>NISN/NUPTK</th>
                            <th>Tanggal Daftar</th>
                            <th>Login Terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_users) > 0): ?>
                            <?php while ($user = mysqli_fetch_assoc($result_users)): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['nama']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = 'bg-secondary';
                                        switch ($user['tipe_pengguna']) {
                                            case 'admin':
                                                $badge_class = 'bg-primary';
                                                break;
                                            case 'guru':
                                                $badge_class = 'bg-success';
                                                break;
                                            case 'siswa':
                                                $badge_class = 'bg-warning';
                                                break;
                                            case 'kepsek':
                                                $badge_class = 'bg-info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($user['tipe_pengguna']); ?></span>
                                    </td>
                                    <td><?php echo $user['kelas_nama'] ?? '-'; ?></td>
                                    <td>
                                        <?php 
                                        if ($user['tipe_pengguna'] === 'siswa' && $user['nisn']) {
                                            echo 'NISN: ' . $user['nisn'];
                                        } elseif (($user['tipe_pengguna'] === 'guru' || $user['tipe_pengguna'] === 'kepsek') && $user['nuptk']) {
                                            echo 'NUPTK: ' . $user['nuptk'];
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo formatDate($user['tanggal_daftar']); ?></td>
                                    <td><?php echo $user['last_login'] ? formatDate($user['last_login'], true) : 'Belum login'; ?></td>
                                    <td>
                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete('<?php echo $user['id']; ?>', '<?php echo $user['nama']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">Tidak ada data pengguna.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel"><?php echo $edit_user ? 'Edit Pengguna' : 'Tambah Pengguna'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="users.php" id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?php echo $edit_user ? $edit_user['nama'] : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_user ? $edit_user['email'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <?php echo $edit_user ? '' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>>
                            <?php if ($edit_user): ?>
                                <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah password.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="tipe_pengguna" class="form-label">Tipe Pengguna <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipe_pengguna" name="tipe_pengguna" required onchange="showRoleFields()">
                                <option value="" disabled selected>Pilih tipe pengguna</option>
                                <option value="admin" <?php echo ($edit_user && $edit_user['tipe_pengguna'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="guru" <?php echo ($edit_user && $edit_user['tipe_pengguna'] === 'guru') ? 'selected' : ''; ?>>Guru</option>
                                <option value="siswa" <?php echo ($edit_user && $edit_user['tipe_pengguna'] === 'siswa') ? 'selected' : ''; ?>>Siswa</option>
                                <option value="kepsek" <?php echo ($edit_user && $edit_user['tipe_pengguna'] === 'kepsek') ? 'selected' : ''; ?>>Kepala Sekolah</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Role specific fields -->
                    <div id="student-fields" class="row mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label for="nisn" class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nisn" name="nisn" value="<?php echo $edit_user ? $edit_user['nisn'] : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="kelas_id" class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="kelas_id" name="kelas_id">
                                <option value="">Pilih kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo ($edit_user && $edit_user['kelas_id'] === $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="teacher-fields" class="row mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label for="nuptk" class="form-label">NUPTK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nuptk" name="nuptk" value="<?php echo $edit_user ? $edit_user['nuptk'] : ''; ?>">
                        </div>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pengguna <span id="delete-user-name" class="fw-bold"></span>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-user-id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Show/hide role-specific fields based on selected role
    function showRoleFields() {
        const role = document.getElementById('tipe_pengguna').value;
        
        // Hide all role-specific fields first
        document.getElementById('student-fields').style.display = 'none';
        document.getElementById('teacher-fields').style.display = 'none';
        
        // Reset required attributes
        document.getElementById('nisn').required = false;
        document.getElementById('kelas_id').required = false;
        document.getElementById('nuptk').required = false;
        
        // Show fields based on selected role
        if (role === 'siswa') {
            document.getElementById('student-fields').style.display = 'flex';
            document.getElementById('nisn').required = true;
            document.getElementById('kelas_id').required = true;
        } else if (role === 'guru' || role === 'kepsek') {
            document.getElementById('teacher-fields').style.display = 'flex';
            document.getElementById('nuptk').required = true;
        }
    }
    
    // Confirm delete
    function confirmDelete(userId, userName) {
        document.getElementById('delete-user-id').value = userId;
        document.getElementById('delete-user-name').textContent = userName;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    // Form validation
    function validateUserForm() {
        const form = document.getElementById('userForm');
        const role = document.getElementById('tipe_pengguna').value;
        
        // Basic validation
        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }
        
        // Role-specific validation
        if (role === 'siswa') {
            const nisn = document.getElementById('nisn').value.trim();
            const kelas = document.getElementById('kelas_id').value;
            
            if (!nisn) {
                alert('NISN harus diisi untuk akun siswa');
                return false;
            }
            
            if (!kelas) {
                alert('Kelas harus dipilih untuk akun siswa');
                return false;
            }
        } else if (role === 'guru' || role === 'kepsek') {
            const nuptk = document.getElementById('nuptk').value.trim();
            
            if (!nuptk) {
                alert('NUPTK harus diisi untuk akun ' + (role === 'guru' ? 'guru' : 'kepala sekolah'));
                return false;
            }
        }
        
        return true;
    }
    
    // Initialize role fields on page load for edit
    document.addEventListener('DOMContentLoaded', function() {
        // Add form validation
        document.getElementById('userForm').addEventListener('submit', function(e) {
            if (!validateUserForm()) {
                e.preventDefault();
            }
        });
        
        <?php if ($edit_user): ?>
        showRoleFields();
        
        // Show modal if in edit mode
        const userModal = new bootstrap.Modal(document.getElementById('userModal'));
        userModal.show();
        <?php endif; ?>
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 