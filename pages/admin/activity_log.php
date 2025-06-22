<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Get filter values
$user_type = isset($_GET['user_type']) ? sanitizeInput($_GET['user_type']) : '';
$activity_type = isset($_GET['activity_type']) ? sanitizeInput($_GET['activity_type']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Default to showing the latest 30 days if no date is specified
if (empty($date_from)) {
    $date_from = date('Y-m-d', strtotime('-30 days'));
}
if (empty($date_to)) {
    $date_to = date('Y-m-d');
}

// Build the query
$query = "SELECT la.*, p.nama, p.tipe_pengguna,
           CASE 
               WHEN la.referensi_id IS NOT NULL AND la.tipe_aktivitas = 'view_materi' THEN m.judul
               WHEN la.referensi_id IS NOT NULL AND (la.tipe_aktivitas = 'submit_tugas' OR la.tipe_aktivitas = 'nilai_tugas') THEN t.judul
               ELSE NULL
           END as referensi_judul
           FROM laporan_aktivitas la
           JOIN pengguna p ON la.pengguna_id = p.id
           LEFT JOIN materi_coding m ON la.referensi_id = m.id AND la.tipe_aktivitas = 'view_materi'
           LEFT JOIN tugas t ON la.referensi_id = t.id AND (la.tipe_aktivitas = 'submit_tugas' OR la.tipe_aktivitas = 'nilai_tugas')
           WHERE DATE(la.waktu) BETWEEN '$date_from' AND '$date_to'";

// Apply filters
if (!empty($user_type)) {
    $query .= " AND p.tipe_pengguna = '$user_type'";
}
if (!empty($activity_type)) {
    $query .= " AND la.tipe_aktivitas = '$activity_type'";
}

// Complete the query with order
$query .= " ORDER BY la.waktu DESC";

// Execute query
$result = mysqli_query($conn, $query);

// Get activity types for filter
$query_activity_types = "SELECT DISTINCT tipe_aktivitas FROM laporan_aktivitas ORDER BY tipe_aktivitas";
$result_activity_types = mysqli_query($conn, $query_activity_types);

// Check if there are any activities in the database
$check_activities_query = "SELECT COUNT(*) as activity_count FROM laporan_aktivitas";
$check_activities_result = mysqli_query($conn, $check_activities_query);
$activity_count = 0;
if ($check_activities_result) {
    $activity_count_row = mysqli_fetch_assoc($check_activities_result);
    $activity_count = $activity_count_row['activity_count'];
}

// Get user activity summary
$query_summary = "SELECT 
                  DATE(waktu) as tanggal,
                  COUNT(CASE WHEN tipe_aktivitas = 'login' THEN 1 END) as login_count,
                  COUNT(CASE WHEN tipe_aktivitas = 'view_materi' THEN 1 END) as view_materi_count,
                  COUNT(CASE WHEN tipe_aktivitas = 'submit_tugas' THEN 1 END) as submit_tugas_count,
                  COUNT(CASE WHEN tipe_aktivitas = 'nilai_tugas' THEN 1 END) as nilai_tugas_count,
                  COUNT(*) as total_count
                FROM laporan_aktivitas
                WHERE DATE(waktu) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE(waktu)
                ORDER BY tanggal DESC
                LIMIT 14";
$result_summary = mysqli_query($conn, $query_summary);

// Get top active users
$query_top_users = "SELECT p.id, p.nama, p.tipe_pengguna, COUNT(la.id) as activity_count
                   FROM pengguna p
                   JOIN laporan_aktivitas la ON p.id = la.pengguna_id
                   WHERE DATE(la.waktu) BETWEEN '$date_from' AND '$date_to'
                   GROUP BY p.id, p.nama, p.tipe_pengguna
                   ORDER BY activity_count DESC
                   LIMIT 10";
$result_top_users = mysqli_query($conn, $query_top_users);

// Include header
include_once '../../includes/header.php';
?>

<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Error handling script -->
<script>
window.addEventListener('error', function(e) {
    console.error('JavaScript error detected:', e.message);
    
    // Create a visible error message for debugging
    var errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger';
    errorDiv.innerHTML = '<strong>JavaScript Error:</strong> ' + e.message + 
                         '<br><small>This message only appears during development.</small>';
    
    // Insert at the top of the page
    document.body.insertBefore(errorDiv, document.body.firstChild);
});
</script>

<!-- Error handling script -->
<script>
window.addEventListener('error', function(e) {
    console.error('JavaScript error detected:', e.message);
    
    // Create a visible error message for debugging
    var errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger';
    errorDiv.innerHTML = '<strong>JavaScript Error:</strong> ' + e.message + 
                         '<br><small>This message only appears during development.</small>';
    
    // Insert at the top of the page
    document.body.insertBefore(errorDiv, document.body.firstChild);
});
</script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Monitoring Aktivitas Pengguna</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Aktivitas</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="activity_log.php" class="row g-3">
                <div class="col-md-3">
                    <label for="user_type" class="form-label">Tipe Pengguna</label>
                    <select class="form-select" id="user_type" name="user_type">
                        <option value="">Semua</option>
                        <option value="admin" <?php if ($user_type === 'admin') echo 'selected'; ?>>Admin</option>
                        <option value="guru" <?php if ($user_type === 'guru') echo 'selected'; ?>>Guru</option>
                        <option value="siswa" <?php if ($user_type === 'siswa') echo 'selected'; ?>>Siswa</option>
                        <option value="kepsek" <?php if ($user_type === 'kepsek') echo 'selected'; ?>>Kepala Sekolah</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="activity_type" class="form-label">Tipe Aktivitas</label>
                    <select class="form-select" id="activity_type" name="activity_type">
                        <option value="">Semua</option>
                        <?php while ($activity_type_row = mysqli_fetch_assoc($result_activity_types)): ?>
                            <option value="<?php echo $activity_type_row['tipe_aktivitas']; ?>" <?php if ($activity_type === $activity_type_row['tipe_aktivitas']) echo 'selected'; ?>>
                                <?php echo formatActivityType($activity_type_row['tipe_aktivitas']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Activity Summary Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Ringkasan Aktivitas Harian</h5>
                </div>
                <div class="card-body">
                    <?php 
                    // Check if we have activity data
                    $has_activity_data = mysqli_num_rows($result_summary) > 0;
                    
                    if ($has_activity_data):
                    ?>
                        <canvas id="activityChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php if ($activity_count > 0): ?>
                                Tidak ada data aktivitas untuk periode yang dipilih. Coba ubah filter tanggal.
                            <?php else: ?>
                                Belum ada data aktivitas yang tercatat dalam sistem. Data akan muncul setelah pengguna melakukan aktivitas.
                            <?php endif; ?>
                        </div>
                        
                        <!-- Show demo chart when no data available -->
                        <canvas id="demoChart" height="250"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <!-- Top Active Users -->
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Pengguna Paling Aktif</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_top_users) > 0): ?>
                        <div class="list-group">
                            <?php while ($user = mysqli_fetch_assoc($result_top_users)): ?>
                                <a href="activity_log.php?user_id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $user['nama']; ?></h6>
                                        <span class="badge bg-primary rounded-pill"><?php echo $user['activity_count']; ?></span>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge bg-<?php echo getUserTypeBadgeClass($user['tipe_pengguna']); ?>">
                                            <?php echo ucfirst($user['tipe_pengguna']); ?>
                                        </span>
                                    </p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Tidak ada data aktivitas pengguna.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Aktivitas</h5>
            <div>
                <a href="logs.php" class="btn btn-sm btn-secondary text-white">
                    <i class="fas fa-server me-1 "></i> Log Sistem
                </a>
                <a href="#" class="btn btn-sm btn-info text-white" onclick="exportTableToCSV('activity_log.csv')">
                    <i class="fas fa-download me-1"></i> Ekspor CSV
                </a>
            </div>
        </div>
        <div class="card-body">
                <div class="table-responsive">
                <table class="table table-hover" id="activityTable">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Tipe</th>
                                <th>Aktivitas</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo formatDate($row['waktu'], true); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getUserTypeBadgeClass($row['tipe_pengguna']); ?> me-1">
                                            <?php echo ucfirst($row['tipe_pengguna']); ?>
                                        </span>
                                            <?php echo $row['nama']; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getActivityTypeBadgeClass($row['tipe_aktivitas']); ?>">
                                            <?php echo formatActivityType($row['tipe_aktivitas']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $activity_desc = $row['deskripsi'];
                                        if (!empty($row['referensi_judul']) && !empty($row['referensi_id'])) {
                                            $activity_desc = str_replace($row['referensi_id'], $row['referensi_judul'], $activity_desc);
                                        }
                                        echo $activity_desc;
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['referensi_id'])): ?>
                                            <a href="get_activity_detail.php?id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-outline-info"
                                               data-bs-toggle="modal" data-bs-target="#activityDetailModal"
                                               data-activity-id="<?php echo $row['id']; ?>">
                                                <i class="fas fa-info-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data aktivitas untuk periode ini.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>
</div>

<!-- Activity Detail Modal -->
<div class="modal fade" id="activityDetailModal" tabindex="-1" aria-labelledby="activityDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityDetailModalLabel">Detail Aktivitas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="activityDetailContent">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load activity details into modal
$(document).ready(function() {
    $('#activityDetailModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var activityId = button.data('activity-id');
        var modal = $(this);
            
            // Load activity details via AJAX
        $.ajax({
            url: 'get_activity_detail.php',
            type: 'GET',
            data: { id: activityId },
            success: function(response) {
                modal.find('#activityDetailContent').html(response);
            },
            error: function() {
                modal.find('#activityDetailContent').html('<div class="alert alert-danger">Gagal memuat detail aktivitas.</div>');
            }
        });
    });
    
    // Reset modal content when modal is hidden
    $('#activityDetailModal').on('hidden.bs.modal', function () {
        $(this).find('#activityDetailContent').html(`
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
    });
});

// Function to export table to CSV
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#activityTable tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) {
            // Get text content and clean it
            var text = cols[j].textContent.trim();
            // Remove multiple spaces and newlines
            text = text.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ");
            // Escape double quotes
            text = text.replace(/"/g, '""');
            // Enclose with quotes if contains comma or quotes
            if (text.includes(',') || text.includes('"')) {
                text = '"' + text + '"';
            }
            row.push(text);
        }
        
        csv.push(row.join(","));
    }
    
    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
}

function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;
    
    // Create CSV file
    csvFile = new Blob([csv], {type: "text/csv"});
    
    // Create download link
    downloadLink = document.createElement("a");
    
    // Set file name
    downloadLink.download = filename;
    
    // Create link to file
    downloadLink.href = window.URL.createObjectURL(csvFile);
    
    // Hide download link
    downloadLink.style.display = "none";
    
    // Add link to DOM
    document.body.appendChild(downloadLink);
    
    // Click download link
    downloadLink.click();
    
    // Remove link from DOM
    document.body.removeChild(downloadLink);
}

// Simple chart initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize real data chart if element exists
    var chartElement = document.getElementById('activityChart');
    if (chartElement) {
        var ctx = chartElement.getContext('2d');
        
        <?php if ($has_activity_data): ?>
        // Extract and prepare data
        <?php
        mysqli_data_seek($result_summary, 0);
        $dates = [];
        $logins = [];
        $views = [];
        $submits = [];
        $grading = [];
        
        while ($row = mysqli_fetch_assoc($result_summary)) {
            $dates[] = formatDate($row['tanggal']);
            $logins[] = (int)$row['login_count'];
            $views[] = (int)$row['view_materi_count'];
            $submits[] = (int)$row['submit_tugas_count'];
            $grading[] = (int)$row['nilai_tugas_count'];
        }
        
        // Reverse arrays to get chronological order
        $dates = array_reverse($dates);
        $logins = array_reverse($logins);
        $views = array_reverse($views);
        $submits = array_reverse($submits);
        $grading = array_reverse($grading);
        ?>
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [
                    {
                        label: 'Login',
                        data: <?php echo json_encode($logins); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true
                    },
                    {
                        label: 'Lihat Materi',
                        data: <?php echo json_encode($views); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: true
                    },
                    {
                        label: 'Submit Tugas',
                        data: <?php echo json_encode($submits); ?>,
                        borderColor: 'rgba(255, 159, 64, 1)',
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        fill: true
                    },
                    {
                        label: 'Penilaian',
                        data: <?php echo json_encode($grading); ?>,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Aktivitas Pengguna per Hari'
                    }
                },
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
        <?php endif; ?>
    }
    
    // Initialize demo chart if element exists
    var demoElement = document.getElementById('demoChart');
    if (demoElement) {
        var demoCtx = demoElement.getContext('2d');
        
        new Chart(demoCtx, {
            type: 'line',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
                datasets: [
                    {
                        label: 'Login (Demo)',
                        data: [12, 19, 8, 15, 10, 5, 14],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true
                    },
                    {
                        label: 'Lihat Materi (Demo)',
                        data: [8, 15, 20, 10, 7, 4, 9],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Contoh Data Aktivitas (Demo)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<?php
/**
 * Get badge class for user type
 * 
 * @param string $type User type
 * @return string CSS class
 */
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

/**
 * Get badge class for activity type
 * 
 * @param string $type Activity type
 * @return string CSS class
 */
function getActivityTypeBadgeClass($type) {
    switch ($type) {
        case 'login':
        case 'logout':
            return 'info';
        case 'view_materi':
            return 'primary';
        case 'submit_tugas':
            return 'success';
        case 'nilai_tugas':
            return 'warning';
        case 'tambah_materi':
        case 'edit_materi':
            return 'primary';
        case 'hapus_materi':
            return 'danger';
        case 'verifikasi':
            return 'secondary';
        default:
            return 'secondary';
    }
}

/**
 * Format activity type for display
 * 
 * @param string $type Activity type
 * @return string Formatted activity type
 */
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
        case 'verifikasi':
            return 'Verifikasi';
        default:
            return ucfirst($type);
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 