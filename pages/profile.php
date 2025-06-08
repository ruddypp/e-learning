<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setFlashMessage('error', 'Anda harus login terlebih dahulu.');
    redirect('../index.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$query = "SELECT p.*, k.nama as kelas_nama, k.tahun_ajaran 
          FROM pengguna p
          LEFT JOIN kelas k ON p.kelas_id = k.id
          WHERE p.id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $email = sanitizeInput($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate current password
        if (!empty($current_password)) {
            if (!password_verify($current_password, $user['password'])) {
                setFlashMessage('error', 'Password saat ini tidak valid.');
                redirect('profile.php');
                exit;
            }
            
            // Check if new password matches confirmation
            if ($new_password !== $confirm_password) {
                setFlashMessage('error', 'Password baru dan konfirmasi tidak cocok.');
                redirect('profile.php');
                exit;
            }
            
            // Update user with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE pengguna SET email = '$email', password = '$hashed_password' WHERE id = '$user_id'";
        } else {
            // Update user without changing password
            $query = "UPDATE pengguna SET email = '$email' WHERE id = '$user_id'";
        }
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Profil berhasil diperbarui.');
            
            // Log activity
            logActivity($user_id, 'update_profile', 'Pengguna memperbarui profil');
            
            redirect('profile.php');
        } else {
            setFlashMessage('error', 'Gagal memperbarui profil: ' . mysqli_error($conn));
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profil Pengguna</h5>
                </div>
                <div class="card-body">
                    <?php displayFlashMessage(); ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <div class="avatar-container mb-3">
                                <i class="fas fa-user-circle fa-6x text-primary"></i>
                            </div>
                            <h5><?php echo $user['nama']; ?></h5>
                            <span class="badge bg-primary"><?php echo ucfirst($user['tipe_pengguna']); ?></span>
                        </div>
                        <div class="col-md-9">
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">ID</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo $user['id']; ?>">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Tipe Pengguna</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo ucfirst($user['tipe_pengguna']); ?>">
                                </div>
                            </div>
                            <?php if ($user['tipe_pengguna'] === 'siswa'): ?>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">NISN</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo $user['nisn'] ?? '-'; ?>">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Kelas</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo $user['kelas_nama'] ? $user['kelas_nama'] . ' (' . $user['tahun_ajaran'] . ')' : '-'; ?>">
                                </div>
                            </div>
                            <?php elseif ($user['tipe_pengguna'] === 'guru'): ?>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">NUPTK</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo $user['nuptk'] ?? '-'; ?>">
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Tanggal Daftar</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo formatDate($user['tanggal_daftar']); ?>">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Login Terakhir</label>
                                <div class="col-sm-8">
                                    <input type="text" readonly class="form-control-plaintext" value="<?php echo $user['last_login'] ? formatDate($user['last_login'], true) : 'Belum pernah login'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Password Saat Ini</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <small class="form-text text-muted">Masukkan password saat ini jika ingin mengubah password.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 