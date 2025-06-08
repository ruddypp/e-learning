<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Get statistics
// Count users by type
$query_users = "SELECT tipe_pengguna, COUNT(*) as jumlah FROM pengguna GROUP BY tipe_pengguna";
$result_users = mysqli_query($conn, $query_users);
$user_counts = [];

while ($row = mysqli_fetch_assoc($result_users)) {
    $user_counts[$row['tipe_pengguna']] = $row['jumlah'];
}

// Count classes
$query_classes = "SELECT COUNT(*) as jumlah FROM kelas";
$result_classes = mysqli_query($conn, $query_classes);
$class_count = mysqli_fetch_assoc($result_classes)['jumlah'];

// Count materials
$query_materials = "SELECT COUNT(*) as jumlah FROM materi_coding";
$result_materials = mysqli_query($conn, $query_materials);
$material_count = mysqli_fetch_assoc($result_materials)['jumlah'];

// Count quizzes
$query_quizzes = "SELECT COUNT(*) as jumlah FROM tugas";
$result_quizzes = mysqli_query($conn, $query_quizzes);
$quiz_count = mysqli_fetch_assoc($result_quizzes)['jumlah'];

// Count questionnaires
$query_questionnaires = "SELECT COUNT(*) as jumlah FROM kuesioner";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);
$questionnaire_count = mysqli_fetch_assoc($result_questionnaires)['jumlah'];

// Get recent activities
$query_activities = "SELECT la.*, p.nama, p.tipe_pengguna,
                   CASE 
                       WHEN la.referensi_id IS NOT NULL AND la.tipe_aktivitas = 'view_materi' THEN m.judul
                       WHEN la.referensi_id IS NOT NULL AND (la.tipe_aktivitas = 'submit_tugas' OR la.tipe_aktivitas = 'nilai_tugas') THEN t.judul
                       ELSE NULL
                   END as referensi_judul
                   FROM laporan_aktivitas la
                   JOIN pengguna p ON la.pengguna_id = p.id
                   LEFT JOIN materi_coding m ON la.referensi_id = m.id AND la.tipe_aktivitas = 'view_materi'
                   LEFT JOIN tugas t ON la.referensi_id = t.id AND (la.tipe_aktivitas = 'submit_tugas' OR la.tipe_aktivitas = 'nilai_tugas')
                   ORDER BY la.waktu DESC
                   LIMIT 10";
$result_activities = mysqli_query($conn, $query_activities);

// Get recent users
$query_recent_users = "SELECT * FROM pengguna ORDER BY tanggal_daftar DESC LIMIT 5";
$result_recent_users = mysqli_query($conn, $query_recent_users);

// Get system logs
$query_logs = "SELECT * FROM log_sistem ORDER BY waktu DESC LIMIT 10";
$result_logs = mysqli_query($conn, $query_logs);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Dashboard Admin</h1>
        <div>
            <a href="backup.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-database me-2"></i> Backup & Maintenance
            </a>
            <a href="users.php" class="btn btn-primary">
                <i class="fas fa-users me-2"></i> Kelola Pengguna
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-primary text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-0">Total Pengguna</h6>
                        <h3 class="mt-2 mb-0">
                            <?php 
                            $total_users = array_sum($user_counts);
                            echo $total_users;
                            ?>
                        </h3>
                        <div class="small text-muted mt-1">
                            <span class="text-primary"><?php echo isset($user_counts['guru']) ? $user_counts['guru'] : 0; ?> Guru</span> | 
                            <span class="text-success"><?php echo isset($user_counts['siswa']) ? $user_counts['siswa'] : 0; ?> Siswa</span> | 
                            <span class="text-info"><?php echo isset($user_counts['kepsek']) ? $user_counts['kepsek'] : 0; ?> Kepsek</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-success text-white">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-0">Total Kelas</h6>
                        <h3 class="mt-2 mb-0"><?php echo $class_count; ?></h3>
                        <div class="small text-muted mt-1">
                            <a href="classes.php" class="text-decoration-none">Kelola Kelas</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-info">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-info text-white">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-0">Materi Coding</h6>
                        <h3 class="mt-2 mb-0"><?php echo $material_count; ?></h3>
                        <div class="small text-muted mt-1">
                            <?php echo $quiz_count; ?> Quiz & Tugas
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-warning text-white">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-0">Kuesioner</h6>
                        <h3 class="mt-2 mb-0"><?php echo $questionnaire_count; ?></h3>
                        <div class="small text-muted mt-1">
                            Evaluasi pembelajaran
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Recent Activity -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Aktivitas Terbaru</h5>
                    <a href="logs.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_activities) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Tipe</th>
                                        <th>Aktivitas</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($activity = mysqli_fetch_assoc($result_activities)): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo getUserTypeBadgeClass($activity['tipe_pengguna']); ?> me-2">
                                                    <?php echo ucfirst($activity['tipe_pengguna']); ?>
                                                </span>
                                                <?php echo $activity['nama']; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getActivityTypeBadgeClass($activity['tipe_aktivitas']); ?>">
                                                    <?php echo formatActivityType($activity['tipe_aktivitas']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $activity_desc = $activity['deskripsi'];
                                                if (!empty($activity['referensi_judul'])) {
                                                    $activity_desc = str_replace($activity['referensi_id'], $activity['referensi_judul'], $activity_desc);
                                                }
                                                echo $activity_desc;
                                                ?>
                                            </td>
                                            <td><?php echo formatDateTime($activity['waktu']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada aktivitas yang tercatat.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Logs -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Log Sistem</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pengguna</th>
                                        <th>Aksi</th>
                                        <th>IP Address</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = mysqli_fetch_assoc($result_logs)): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['pengguna_id']): ?>
                                                    <?php
                                                    $query_user = "SELECT nama, tipe_pengguna FROM pengguna WHERE id = '{$log['pengguna_id']}'";
                                                    $result_user = mysqli_query($conn, $query_user);
                                                    if (mysqli_num_rows($result_user) > 0) {
                                                        $user = mysqli_fetch_assoc($result_user);
                                                        echo '<span class="badge bg-' . getUserTypeBadgeClass($user['tipe_pengguna']) . ' me-2">' . 
                                                             ucfirst($user['tipe_pengguna']) . '</span>' . $user['nama'];
                                                    } else {
                                                        echo $log['pengguna_id'];
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $log['aksi']; ?></td>
                                            <td><?php echo $log['ip_address']; ?></td>
                                            <td><?php echo formatDateTime($log['waktu']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada log sistem yang tercatat.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- User Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistik Pengguna</h5>
                </div>
                <div class="card-body">
                    <canvas id="userChart" height="200"></canvas>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pengguna Terbaru</h5>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">Kelola Pengguna</a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_recent_users) > 0): ?>
                        <div class="list-group">
                            <?php while ($user = mysqli_fetch_assoc($result_recent_users)): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo $user['nama']; ?></h6>
                                        <span class="badge bg-<?php echo getUserTypeBadgeClass($user['tipe_pengguna']); ?>">
                                            <?php echo ucfirst($user['tipe_pengguna']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i> <?php echo $user['email']; ?><br>
                                        <i class="fas fa-calendar me-1"></i> Terdaftar: <?php echo formatDate($user['tanggal_daftar']); ?>
                                        <?php if ($user['tipe_pengguna'] === 'siswa'): ?>
                                            <br><i class="fas fa-id-card me-1"></i> NISN: <?php echo $user['nisn']; ?>
                                        <?php elseif ($user['tipe_pengguna'] === 'guru'): ?>
                                            <br><i class="fas fa-id-card me-1"></i> NUPTK: <?php echo $user['nuptk']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada pengguna yang terdaftar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Akses Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="users.php" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i> Kelola Pengguna
                        </a>
                        <a href="classes.php" class="btn btn-outline-success">
                            <i class="fas fa-school me-2"></i> Kelola Kelas
                        </a>
                        <a href="logs.php" class="btn btn-outline-info">
                            <i class="fas fa-history me-2"></i> Aktivitas Pengguna
                        </a>
                        <a href="backup.php" class="btn btn-outline-warning">
                            <i class="fas fa-database me-2"></i> Backup Database
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Statistics Chart
    var ctxUser = document.getElementById('userChart').getContext('2d');
    var userChart = new Chart(ctxUser, {
        type: 'pie',
        data: {
            labels: ['Admin', 'Guru', 'Siswa', 'Kepala Sekolah'],
            datasets: [{
                data: [
                    <?php echo isset($user_counts['admin']) ? $user_counts['admin'] : 0; ?>,
                    <?php echo isset($user_counts['guru']) ? $user_counts['guru'] : 0; ?>,
                    <?php echo isset($user_counts['siswa']) ? $user_counts['siswa'] : 0; ?>,
                    <?php echo isset($user_counts['kepsek']) ? $user_counts['kepsek'] : 0; ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(255, 159, 64, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<style>
.icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.border-primary {
    border-top: 3px solid #007bff !important;
}

.border-success {
    border-top: 3px solid #28a745 !important;
}

.border-info {
    border-top: 3px solid #17a2b8 !important;
}

.border-warning {
    border-top: 3px solid #ffc107 !important;
}
</style>

<?php
// Helper functions
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

function getActivityTypeBadgeClass($type) {
    switch ($type) {
        case 'login':
        case 'logout':
            return 'secondary';
        case 'view_materi':
            return 'info';
        case 'submit_tugas':
            return 'success';
        case 'nilai_tugas':
            return 'primary';
        case 'tambah_materi':
        case 'edit_materi':
            return 'warning';
        case 'hapus_materi':
            return 'danger';
        default:
            return 'secondary';
    }
}

function formatActivityType($type) {
    switch ($type) {
        case 'login':
            return 'Login';
        case 'logout':
            return 'Logout';
        case 'view_materi':
            return 'Lihat Materi';
        case 'submit_tugas':
            return 'Submit Quiz';
        case 'nilai_tugas':
            return 'Nilai Quiz';
        case 'tambah_materi':
            return 'Tambah Materi';
        case 'edit_materi':
            return 'Edit Materi';
        case 'hapus_materi':
            return 'Hapus Materi';
        default:
            return ucfirst($type);
    }
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

// Include footer
include_once '../../includes/footer.php';
?> 