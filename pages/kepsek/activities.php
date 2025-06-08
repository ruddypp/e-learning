<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has kepsek role
checkAccess(['kepsek']);

// Filter parameters
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
$kelas_id = isset($_GET['kelas_id']) ? sanitizeInput($_GET['kelas_id']) : '';
$activity_type = isset($_GET['activity_type']) ? sanitizeInput($_GET['activity_type']) : '';

// Base query
$base_query = "SELECT la.id, la.pengguna_id, la.tipe_aktivitas, la.deskripsi, la.waktu, la.referensi_id,
              p.nama, p.tipe_pengguna, p.kelas_id,
              k.nama AS kelas_nama,
              CASE 
                  WHEN la.tipe_aktivitas = 'view_materi' THEN (SELECT judul FROM materi_coding WHERE id = la.referensi_id)
                  WHEN la.tipe_aktivitas = 'submit_tugas' OR la.tipe_aktivitas = 'nilai_tugas' THEN (SELECT judul FROM tugas WHERE id = la.referensi_id)
                  ELSE NULL
              END as referensi_judul
              FROM laporan_aktivitas la
              JOIN pengguna p ON la.pengguna_id = p.id
              LEFT JOIN kelas k ON p.kelas_id = k.id
              WHERE la.waktu BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

// Add filters if provided
if (!empty($kelas_id)) {
    $base_query .= " AND p.kelas_id = '$kelas_id'";
}

if (!empty($activity_type)) {
    $base_query .= " AND la.tipe_aktivitas = '$activity_type'";
}

// Order by time descending
$base_query .= " ORDER BY la.waktu DESC";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total results
$count_query = "SELECT COUNT(*) as total FROM ($base_query) AS filtered_activities";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);

// Get paginated results
$query = "$base_query LIMIT $offset, $per_page";
$result = mysqli_query($conn, $query);

// Get login analytics
$login_query = "SELECT DATE(waktu) as login_date, COUNT(*) as count 
               FROM laporan_aktivitas 
               WHERE tipe_aktivitas = 'login' 
               AND waktu BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
               GROUP BY DATE(waktu)
               ORDER BY login_date";
$login_result = mysqli_query($conn, $login_query);
$login_data = [];

while ($row = mysqli_fetch_assoc($login_result)) {
    $login_data[$row['login_date']] = $row['count'];
}

// Fill in missing dates
$current_date = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$end_date_obj->modify('+1 day');
$date_range = [];

while ($current_date < $end_date_obj) {
    $date_str = $current_date->format('Y-m-d');
    $date_range[] = $date_str;
    if (!isset($login_data[$date_str])) {
        $login_data[$date_str] = 0;
    }
    $current_date->modify('+1 day');
}

// Sort by date
ksort($login_data);

// Format dates for display
$formatted_dates = [];
$login_counts = [];
foreach ($login_data as $date => $count) {
    $date_obj = new DateTime($date);
    $formatted_dates[] = $date_obj->format('d M');
    $login_counts[] = $count;
}

// Get material views analytics
$material_query = "SELECT DATE(waktu) as view_date, COUNT(*) as count 
                  FROM laporan_aktivitas 
                  WHERE tipe_aktivitas = 'view_materi' 
                  AND waktu BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
                  GROUP BY DATE(waktu)
                  ORDER BY view_date";
$material_result = mysqli_query($conn, $material_query);
$material_data = [];

while ($row = mysqli_fetch_assoc($material_result)) {
    $material_data[$row['view_date']] = $row['count'];
}

// Fill in missing dates for material views
$material_counts = [];
foreach ($date_range as $date) {
    $material_counts[] = isset($material_data[$date]) ? $material_data[$date] : 0;
}

// Get quiz submissions analytics
$quiz_query = "SELECT DATE(waktu) as submit_date, COUNT(*) as count 
              FROM laporan_aktivitas 
              WHERE tipe_aktivitas = 'submit_tugas' 
              AND waktu BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
              GROUP BY DATE(waktu)
              ORDER BY submit_date";
$quiz_result = mysqli_query($conn, $quiz_query);
$quiz_data = [];

while ($row = mysqli_fetch_assoc($quiz_result)) {
    $quiz_data[$row['submit_date']] = $row['count'];
}

// Fill in missing dates for quiz submissions
$quiz_counts = [];
foreach ($date_range as $date) {
    $quiz_counts[] = isset($quiz_data[$date]) ? $quiz_data[$date] : 0;
}

// Get all classes for filter
$classes_query = "SELECT id, nama FROM kelas ORDER BY nama ASC";
$classes_result = mysqli_query($conn, $classes_query);
$classes = [];
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $row;
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Aktivitas Pembelajaran</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Analytics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <h5 class="card-title text-primary">Login Pengguna</h5>
                    <canvas id="loginChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card border-success h-100">
                <div class="card-body">
                    <h5 class="card-title text-success">Akses Materi</h5>
                    <canvas id="materialChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card border-info h-100">
                <div class="card-body">
                    <h5 class="card-title text-info">Pengerjaan Quiz</h5>
                    <canvas id="quizChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="kelas_id" class="form-label">Kelas</label>
                    <select class="form-select" id="kelas_id" name="kelas_id">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($kelas_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['nama']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="activity_type" class="form-label">Jenis Aktivitas</label>
                    <select class="form-select" id="activity_type" name="activity_type">
                        <option value="">Semua Aktivitas</option>
                        <option value="login" <?php echo ($activity_type == 'login') ? 'selected' : ''; ?>>Login</option>
                        <option value="logout" <?php echo ($activity_type == 'logout') ? 'selected' : ''; ?>>Logout</option>
                        <option value="view_materi" <?php echo ($activity_type == 'view_materi') ? 'selected' : ''; ?>>Lihat Materi</option>
                        <option value="submit_tugas" <?php echo ($activity_type == 'submit_tugas') ? 'selected' : ''; ?>>Submit Tugas</option>
                        <option value="nilai_tugas" <?php echo ($activity_type == 'nilai_tugas') ? 'selected' : ''; ?>>Nilai Tugas</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                    <a href="activities.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Activities Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Log Aktivitas Pembelajaran</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Kelas</th>
                            <th>Aktivitas</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($activity = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo formatDate($activity['waktu'], true); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getUserTypeBadgeClass($activity['tipe_pengguna']); ?> me-2">
                                            <?php echo ucfirst($activity['tipe_pengguna']); ?>
                                        </span>
                                        <?php echo $activity['nama']; ?>
                                    </td>
                                    <td><?php echo $activity['kelas_nama'] ?? '-'; ?></td>
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
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-3">Tidak ada aktivitas yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&kelas_id=<?php echo $kelas_id; ?>&activity_type=<?php echo $activity_type; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&kelas_id=<?php echo $kelas_id; ?>&activity_type=<?php echo $activity_type; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <a class="page-link" href="#">...</a>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&kelas_id=<?php echo $kelas_id; ?>&activity_type=<?php echo $activity_type; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Login Chart
    const loginCtx = document.getElementById('loginChart').getContext('2d');
    const loginChart = new Chart(loginCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($formatted_dates); ?>,
            datasets: [{
                label: 'Login Pengguna',
                data: <?php echo json_encode($login_counts); ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Material Chart
    const materialCtx = document.getElementById('materialChart').getContext('2d');
    const materialChart = new Chart(materialCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($formatted_dates); ?>,
            datasets: [{
                label: 'Akses Materi',
                data: <?php echo json_encode($material_counts); ?>,
                backgroundColor: 'rgba(46, 204, 113, 0.2)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Quiz Chart
    const quizCtx = document.getElementById('quizChart').getContext('2d');
    const quizChart = new Chart(quizCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($formatted_dates); ?>,
            datasets: [{
                label: 'Pengerjaan Quiz',
                data: <?php echo json_encode($quiz_counts); ?>,
                backgroundColor: 'rgba(41, 128, 185, 0.2)',
                borderColor: 'rgba(41, 128, 185, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>

<?php
// Helper functions
function getUserTypeBadgeClass($type) {
    switch ($type) {
        case 'admin':
            return 'primary';
        case 'guru':
            return 'success';
        case 'siswa':
            return 'info';
        case 'kepsek':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getActivityTypeBadgeClass($type) {
    switch ($type) {
        case 'login':
            return 'success';
        case 'logout':
            return 'danger';
        case 'view_materi':
            return 'info';
        case 'submit_tugas':
            return 'primary';
        case 'nilai_tugas':
            return 'warning';
        case 'tambah_materi':
        case 'edit_materi':
        case 'hapus_materi':
            return 'dark';
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
            return 'Submit Tugas';
        case 'nilai_tugas':
            return 'Nilai Tugas';
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
?>

<?php include_once '../../includes/footer.php'; ?> 