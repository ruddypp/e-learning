<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$user_filter = isset($_GET['user']) ? sanitizeInput($_GET['user']) : '';
$activity_filter = isset($_GET['activity']) ? sanitizeInput($_GET['activity']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query
$query_logs = "SELECT l.*, p.nama as pengguna_nama, p.tipe_pengguna 
              FROM laporan_aktivitas l 
              JOIN pengguna p ON l.pengguna_id = p.id 
              WHERE 1=1 ";

$query_count = "SELECT COUNT(*) as total FROM laporan_aktivitas l 
                JOIN pengguna p ON l.pengguna_id = p.id 
                WHERE 1=1 ";

// Apply filters
if (!empty($user_filter)) {
    $query_logs .= "AND (p.nama LIKE '%$user_filter%' OR p.id LIKE '%$user_filter%') ";
    $query_count .= "AND (p.nama LIKE '%$user_filter%' OR p.id LIKE '%$user_filter%') ";
}

if (!empty($activity_filter)) {
    $query_logs .= "AND l.tipe_aktivitas = '$activity_filter' ";
    $query_count .= "AND l.tipe_aktivitas = '$activity_filter' ";
}

if (!empty($date_from)) {
    $query_logs .= "AND DATE(l.waktu) >= '$date_from' ";
    $query_count .= "AND DATE(l.waktu) >= '$date_from' ";
}

if (!empty($date_to)) {
    $query_logs .= "AND DATE(l.waktu) <= '$date_to' ";
    $query_count .= "AND DATE(l.waktu) <= '$date_to' ";
}

// Order and limit
$query_logs .= "ORDER BY l.waktu DESC LIMIT $offset, $per_page";

// Execute queries
$result_logs = mysqli_query($conn, $query_logs);
$result_count = mysqli_query($conn, $query_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_logs = $row_count['total'];
$total_pages = ceil($total_logs / $per_page);

// Get activity types for filter
$query_activity_types = "SELECT DISTINCT tipe_aktivitas FROM laporan_aktivitas ORDER BY tipe_aktivitas";
$result_activity_types = mysqli_query($conn, $query_activity_types);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Log Aktivitas Sistem</h1>
        
        <!-- Export Button -->
        <div>
            <button class="btn btn-success" onclick="exportToCSV()">
                <i class="fas fa-file-export me-2"></i> Export CSV
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Log Aktivitas</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="logs.php" id="filter-form">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="user" class="form-label">Pengguna</label>
                        <input type="text" class="form-control" id="user" name="user" 
                               value="<?php echo $user_filter; ?>" placeholder="Nama atau ID pengguna">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="activity" class="form-label">Tipe Aktivitas</label>
                        <select class="form-select" id="activity" name="activity">
                            <option value="">Semua Aktivitas</option>
                            <?php while ($type = mysqli_fetch_assoc($result_activity_types)): ?>
                                <option value="<?php echo $type['tipe_aktivitas']; ?>" 
                                        <?php echo ($activity_filter === $type['tipe_aktivitas']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type['tipe_aktivitas'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="date_from" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="date_to" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 text-end">
                        <a href="logs.php" class="btn btn-secondary me-2">Reset</a>
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Log Aktivitas</h5>
            <span class="badge bg-primary"><?php echo $total_logs; ?> log ditemukan</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="logs-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Tipe</th>
                            <th>Tipe Aktivitas</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_logs) > 0): ?>
                            <?php while ($log = mysqli_fetch_assoc($result_logs)): ?>
                                <tr>
                                    <td><?php echo formatDate($log['waktu'], true); ?></td>
                                    <td>
                                        <?php echo $log['pengguna_nama']; ?> 
                                        <span class="badge bg-secondary"><?php echo ucfirst($log['tipe_pengguna']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $badge_class = 'bg-secondary';
                                        switch ($log['tipe_pengguna']) {
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
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($log['tipe_pengguna']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $activity_badge = 'bg-secondary';
                                        $activity_name = str_replace('_', ' ', $log['tipe_aktivitas']);
                                        
                                        switch ($log['tipe_aktivitas']) {
                                            case 'login':
                                            case 'logout':
                                                $activity_badge = 'bg-info';
                                                break;
                                            case 'view_materi':
                                                $activity_badge = 'bg-primary';
                                                break;
                                            case 'submit_tugas':
                                            case 'nilai_tugas':
                                                $activity_badge = 'bg-warning';
                                                break;
                                            case 'tambah_materi':
                                            case 'tambah_pengguna':
                                            case 'tambah_kelas':
                                                $activity_badge = 'bg-success';
                                                break;
                                            case 'edit_materi':
                                            case 'edit_pengguna':
                                            case 'edit_kelas':
                                                $activity_badge = 'bg-info';
                                                break;
                                            case 'hapus_materi':
                                            case 'hapus_pengguna':
                                            case 'hapus_kelas':
                                                $activity_badge = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $activity_badge; ?>"><?php echo ucfirst($activity_name); ?></span>
                                    </td>
                                    <td><?php echo $log['deskripsi']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data log aktivitas.</td>
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
                            <a class="page-link" href="<?php echo getPageUrl($page - 1); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo getPageUrl($i); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo getPageUrl($page + 1); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Helper function for pagination URL
    function getPageUrl(page) {
        let url = new URL(window.location.href);
        url.searchParams.set('page', page);
        return url.toString();
    }
    
    // Export to CSV
    function exportToCSV() {
        // Get table data
        const table = document.getElementById('logs-table');
        let csv = [];
        
        // Get headers
        const headers = [];
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            headers.push(cell.textContent.trim());
        });
        csv.push(headers.join(','));
        
        // Get rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                // Replace commas with spaces and clean up text
                let text = cell.textContent.trim().replace(/,/g, ' ');
                // Remove badge text that appears multiple times
                text = text.replace(/Admin|Guru|Siswa|Kepsek/g, '').trim();
                rowData.push(`"${text}"`);
            });
            csv.push(rowData.join(','));
        });
        
        // Create CSV file
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        
        // Create download link
        const link = document.createElement('a');
        const date = new Date().toISOString().split('T')[0];
        link.setAttribute('href', url);
        link.setAttribute('download', `aktivitas_log_${date}.csv`);
        link.style.visibility = 'hidden';
        
        // Append to document, click and remove
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';

// Helper function for pagination URLs
function getPageUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'logs.php?' . http_build_query($params);
}
?> 