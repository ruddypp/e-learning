<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = sanitizeInput($_POST['id']);
        
        // Approve user
        if ($_POST['action'] === 'approve_user') {
            $query = "UPDATE pengguna SET status = 'aktif' WHERE id = '$id'";
            
            if (mysqli_query($conn, $query)) {
                // Log the action
                logActivity($_SESSION['user_id'], 'verifikasi', "Admin menyetujui pengguna dengan ID: $id");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Verifikasi Pengguna', 'Menyetujui pengguna ID: $id', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Pengguna berhasil diverifikasi.');
            } else {
                setFlashMessage('error', 'Gagal memverifikasi pengguna: ' . mysqli_error($conn));
            }
        }
        
        // Reject user
        else if ($_POST['action'] === 'reject_user') {
            $reason = isset($_POST['reason']) ? sanitizeInput($_POST['reason']) : '';
            
            // Update status and add rejection reason
            $query = "UPDATE pengguna SET status = 'ditolak', keterangan = '$reason' WHERE id = '$id'";
            
            if (mysqli_query($conn, $query)) {
                // Log the action
                logActivity($_SESSION['user_id'], 'verifikasi', "Admin menolak pengguna dengan ID: $id. Alasan: $reason");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Tolak Pengguna', 'Menolak pengguna ID: $id. Alasan: $reason', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Pengguna berhasil ditolak.');
            } else {
                setFlashMessage('error', 'Gagal menolak pengguna: ' . mysqli_error($conn));
            }
        }
        
        // Approve class data
        else if ($_POST['action'] === 'approve_class') {
            $query = "UPDATE kelas SET status = 'aktif' WHERE id = '$id'";
            
            if (mysqli_query($conn, $query)) {
                // Log the action
                logActivity($_SESSION['user_id'], 'verifikasi', "Admin menyetujui data kelas dengan ID: $id");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Verifikasi Kelas', 'Menyetujui kelas ID: $id', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Data kelas berhasil diverifikasi.');
            } else {
                setFlashMessage('error', 'Gagal memverifikasi data kelas: ' . mysqli_error($conn));
            }
        }
        
        // Approve material
        else if ($_POST['action'] === 'approve_material') {
            $query = "UPDATE materi_coding SET status = 'aktif' WHERE id = '$id'";
            
            if (mysqli_query($conn, $query)) {
                // Log the action
                logActivity($_SESSION['user_id'], 'verifikasi', "Admin menyetujui materi dengan ID: $id");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Verifikasi Materi', 'Menyetujui materi ID: $id', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Materi berhasil diverifikasi.');
            } else {
                setFlashMessage('error', 'Gagal memverifikasi materi: ' . mysqli_error($conn));
            }
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header('Location: verify.php');
    exit;
}

// Get pending users
$query_users = "SELECT * FROM pengguna WHERE status = 'pending' ORDER BY tanggal_daftar DESC";
$result_users = mysqli_query($conn, $query_users);

// Get pending classes
$query_classes = "SELECT k.*, p.nama as wali_kelas_nama 
                FROM kelas k 
                LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
                WHERE k.status = 'pending' 
                ORDER BY k.tanggal_dibuat DESC";
$result_classes = mysqli_query($conn, $query_classes);

// Get pending materials
$query_materials = "SELECT m.*, p.nama as guru_nama, k.nama as kelas_nama 
                  FROM materi_coding m 
                  JOIN pengguna p ON m.dibuat_oleh = p.id 
                  JOIN kelas k ON m.kelas_id = k.id 
                  WHERE m.status = 'pending' 
                  ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Verifikasi Data</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" id="verifyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true">
                <i class="fas fa-users me-2"></i> Pengguna
                <span class="badge bg-danger ms-1"><?php echo mysqli_num_rows($result_users); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="classes-tab" data-bs-toggle="tab" data-bs-target="#classes" type="button" role="tab" aria-controls="classes" aria-selected="false">
                <i class="fas fa-school me-2"></i> Kelas
                <span class="badge bg-danger ms-1"><?php echo mysqli_num_rows($result_classes); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">
                <i class="fas fa-book me-2"></i> Materi
                <span class="badge bg-danger ms-1"><?php echo mysqli_num_rows($result_materials); ?></span>
            </button>
        </li>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content">
        <!-- Users Tab -->
        <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Pengguna Menunggu Verifikasi</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Tipe</th>
                                        <th>Detail</th>
                                        <th>Tanggal Daftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = mysqli_fetch_assoc($result_users)): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo $user['nama']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getUserTypeBadgeClass($user['tipe_pengguna']); ?>">
                                                    <?php echo ucfirst($user['tipe_pengguna']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['tipe_pengguna'] === 'siswa'): ?>
                                                    <strong>NISN:</strong> <?php echo $user['nisn']; ?>
                                                <?php elseif ($user['tipe_pengguna'] === 'guru'): ?>
                                                    <strong>NUPTK:</strong> <?php echo $user['nuptk']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($user['tanggal_daftar']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" action="verify.php" class="d-inline">
                                                        <input type="hidden" name="action" value="approve_user">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                onclick="return confirm('Apakah Anda yakin ingin menyetujui pengguna ini?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#rejectUserModal" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-user-name="<?php echo $user['nama']; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Tidak ada pengguna yang menunggu verifikasi.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Classes Tab -->
        <div class="tab-pane fade" id="classes" role="tabpanel" aria-labelledby="classes-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kelas Menunggu Verifikasi</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_classes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Kelas</th>
                                        <th>Tahun Ajaran</th>
                                        <th>Wali Kelas</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                        <tr>
                                            <td><?php echo $class['id']; ?></td>
                                            <td><?php echo $class['nama']; ?></td>
                                            <td><?php echo $class['tahun_ajaran']; ?></td>
                                            <td><?php echo $class['wali_kelas_nama'] ?? '<span class="text-muted">Belum ditentukan</span>'; ?></td>
                                            <td><?php echo formatDate($class['tanggal_dibuat']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" action="verify.php" class="d-inline">
                                                        <input type="hidden" name="action" value="approve_class">
                                                        <input type="hidden" name="id" value="<?php echo $class['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                onclick="return confirm('Apakah Anda yakin ingin menyetujui kelas ini?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Tidak ada kelas yang menunggu verifikasi.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Materials Tab -->
        <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Materi Menunggu Verifikasi</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_materials) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Kelas</th>
                                        <th>Tingkat</th>
                                        <th>Guru</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                        <tr>
                                            <td><?php echo $material['id']; ?></td>
                                            <td><?php echo $material['judul']; ?></td>
                                            <td><?php echo $material['kelas_nama']; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $material['tingkat']; ?></span>
                                            </td>
                                            <td><?php echo $material['guru_nama']; ?></td>
                                            <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <form method="POST" action="verify.php" class="d-inline">
                                                        <input type="hidden" name="action" value="approve_material">
                                                        <input type="hidden" name="id" value="<?php echo $material['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" 
                                                                onclick="return confirm('Apakah Anda yakin ingin menyetujui materi ini?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <a href="../siswa/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Tidak ada materi yang menunggu verifikasi.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject User Modal -->
<div class="modal fade" id="rejectUserModal" tabindex="-1" aria-labelledby="rejectUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectUserModalLabel">Tolak Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="verify.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_user">
                    <input type="hidden" name="id" id="rejectUserId">
                    
                    <p>Anda akan menolak pendaftaran pengguna <strong id="rejectUserName"></strong>.</p>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Alasan Penolakan</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle reject user modal
    var rejectUserModal = document.getElementById('rejectUserModal');
    if (rejectUserModal) {
        rejectUserModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var userId = button.getAttribute('data-user-id');
            var userName = button.getAttribute('data-user-name');
            
            document.getElementById('rejectUserId').value = userId;
            document.getElementById('rejectUserName').textContent = userName;
        });
    }
});
</script>

<?php
// Helper function for user type badge color
function getUserTypeBadgeClass($type) {
    switch ($type) {
        case 'admin':
            return 'danger';
        case 'guru':
            return 'primary';
        case 'siswa':
            return 'success';
        case 'kepsek':
            return 'warning';
        default:
            return 'secondary';
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 