<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Create backup directory if it doesn't exist
$backupDir = '../../backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Process backup or restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create backup
    if (isset($_POST['action']) && $_POST['action'] === 'create_backup') {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "$backupDir/elearning_backup_$timestamp.sql";
        
        // Get database configuration
        $host = DB_HOST;
        $user = DB_USERNAME;
        $pass = DB_PASSWORD;
        $name = DB_NAME;
        
        // Create backup command
        // Use mysqldump path that should work in XAMPP
        $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump';
        
        // Check if the file exists before using it
        if (!file_exists($mysqldump)) {
            // Try to find mysqldump in common locations
            $possible_paths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql5.7.26\\bin\\mysqldump.exe',
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                'mysqldump' // try using the system path
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $mysqldump = $path;
                    break;
                }
            }
        }
        
        $command = "\"$mysqldump\" --host=$host --user=$user";
        
        // Add password only if it's not empty
        if (!empty($pass)) {
            $command .= " --password=$pass";
        }
        
        $command .= " $name > \"$backupFile\"";
        
        try {
            // Execute backup command
            $output = [];
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
                // Log the action
                logActivity($_SESSION['user_id'], 'backup', "Admin membuat backup database: $backupFile");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Backup Database', 'Membuat backup: $backupFile', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'Backup database berhasil dibuat.');
            } else {
                // Get error details for better troubleshooting
                $errorMsg = implode("\n", $output);
                if (empty($errorMsg)) {
                    $errorMsg = "Kode error: $return_var";
                }
                
                setFlashMessage('error', 'Gagal membuat backup database. ' . $errorMsg);
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Error: ' . $e->getMessage());
        }
    }
    
    // Restore backup
    else if (isset($_POST['action']) && $_POST['action'] === 'restore_backup' && isset($_POST['file'])) {
        $backupFile = sanitizeInput($_POST['file']);
        $fullPath = "$backupDir/$backupFile";
        
        if (file_exists($fullPath)) {
            // Get database configuration
            $host = DB_HOST;
            $user = DB_USERNAME;
            $pass = DB_PASSWORD;
            $name = DB_NAME;
            
            // Create restore command
            // Use mysql path that should work in XAMPP
            $mysql = 'C:\\xampp\\mysql\\bin\\mysql';
            
            // Check if the file exists before using it
            if (!file_exists($mysql)) {
                // Try to find mysql in common locations
                $possible_paths = [
                    'C:\\xampp\\mysql\\bin\\mysql.exe',
                    'C:\\wamp\\bin\\mysql\\mysql5.7.26\\bin\\mysql.exe',
                    '/usr/bin/mysql',
                    '/usr/local/bin/mysql',
                    'mysql' // try using the system path
                ];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $mysql = $path;
                        break;
                    }
                }
            }
            
            $command = "\"$mysql\" --host=$host --user=$user";
            
            // Add password only if it's not empty
            if (!empty($pass)) {
                $command .= " --password=$pass";
            }
            
            $command .= " $name < \"$fullPath\"";
            
            try {
                // Execute restore command
                $output = [];
                exec($command, $output, $return_var);
                
                if ($return_var === 0) {
                    // Log the action
                    logActivity($_SESSION['user_id'], 'restore', "Admin melakukan restore database dari: $backupFile");
                    
                    // Add to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'Restore Database', 'Restore dari: $backupFile', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                    
                    setFlashMessage('success', 'Database berhasil di-restore dari backup.');
                } else {
                    // Get error details for better troubleshooting
                    $errorMsg = implode("\n", $output);
                    if (empty($errorMsg)) {
                        $errorMsg = "Kode error: $return_var";
                    }
                    
                    setFlashMessage('error', 'Gagal melakukan restore database. ' . $errorMsg);
                }
            } catch (Exception $e) {
                setFlashMessage('error', 'Error: ' . $e->getMessage());
            }
        } else {
            setFlashMessage('error', 'File backup tidak ditemukan.');
        }
    }
    
    // Delete backup
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_backup' && isset($_POST['file'])) {
        $backupFile = sanitizeInput($_POST['file']);
        $fullPath = "$backupDir/$backupFile";
        
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                // Log the action
                logActivity($_SESSION['user_id'], 'delete_backup', "Admin menghapus file backup: $backupFile");
                
                // Add to system log
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                             VALUES ('{$_SESSION['user_id']}', 'Hapus Backup', 'Menghapus file: $backupFile', '$ip', '$user_agent')";
                mysqli_query($conn, $log_query);
                
                setFlashMessage('success', 'File backup berhasil dihapus.');
            } else {
                setFlashMessage('error', 'Gagal menghapus file backup.');
            }
        } else {
            setFlashMessage('error', 'File backup tidak ditemukan.');
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header('Location: backup.php');
    exit;
}

// Get list of backup files
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backupFiles[] = [
                'name' => $file,
                'size' => formatBytes(filesize("$backupDir/$file")),
                'date' => date("Y-m-d H:i:s", filemtime("$backupDir/$file"))
            ];
        }
    }
    
    // Sort by date descending (newest first)
    usort($backupFiles, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manajemen Backup Database</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <!-- Backup Controls -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i> Backup & Restore</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Backup database secara berkala untuk melindungi data dari kehilangan atau kerusakan.
                    </p>
                    
                    <form method="POST" action="backup.php" class="mb-4">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Apakah Anda yakin ingin membuat backup database saat ini?');">
                            <i class="fas fa-download me-2"></i> Buat Backup Baru
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Perhatian!</strong> 
                        Restore database akan menimpa seluruh data saat ini. Pastikan untuk membuat backup terlebih dahulu.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Database Stats -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Statistik Database</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php
                        // Get table stats
                        $tables = [
                            'pengguna' => ['icon' => 'fas fa-users', 'color' => 'primary'],
                            'kelas' => ['icon' => 'fas fa-school', 'color' => 'success'],
                            'materi_coding' => ['icon' => 'fas fa-book', 'color' => 'info'],
                            'tugas' => ['icon' => 'fas fa-tasks', 'color' => 'warning'],
                            'soal_quiz' => ['icon' => 'fas fa-question-circle', 'color' => 'danger'],
                            'nilai_tugas' => ['icon' => 'fas fa-star', 'color' => 'secondary'],
                            'kuesioner' => ['icon' => 'fas fa-clipboard-list', 'color' => 'dark'],
                            'log_sistem' => ['icon' => 'fas fa-history', 'color' => 'light']
                        ];
                        
                        foreach ($tables as $table => $info) {
                            $query = "SELECT COUNT(*) as total FROM $table";
                            $result = mysqli_query($conn, $query);
                            $row = mysqli_fetch_assoc($result);
                            $count = $row['total'];
                            
                            echo '<div class="col-md-3 col-sm-6">';
                            echo '<div class="card bg-light">';
                            echo '<div class="card-body text-center p-3">';
                            echo '<div class="mb-2"><i class="' . $info['icon'] . ' fa-2x text-' . $info['color'] . '"></i></div>';
                            echo '<h6 class="card-title">' . ucfirst(str_replace('_', ' ', $table)) . '</h6>';
                            echo '<h3 class="mb-0">' . number_format($count) . '</h3>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backup Files -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Riwayat Backup</h5>
        </div>
        <div class="card-body">
            <?php if (count($backupFiles) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupFiles as $file): ?>
                                <tr>
                                    <td><?php echo $file['name']; ?></td>
                                    <td><?php echo $file['size']; ?></td>
                                    <td><?php echo formatDate($file['date'], true); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <form method="POST" action="backup.php" class="d-inline">
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="file" value="<?php echo $file['name']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('PERINGATAN: Tindakan ini akan menimpa seluruh data saat ini dengan data dari backup ini. Lanjutkan?');">
                                                    <i class="fas fa-redo-alt"></i>
                                                </button>
                                            </form>
                                            
                                            <a href="<?php echo "../../backups/" . $file['name']; ?>" download class="btn btn-sm btn-info">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <form method="POST" action="backup.php" class="d-inline">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="file" value="<?php echo $file['name']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Apakah Anda yakin ingin menghapus file backup ini?');">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Belum ada file backup yang tersedia.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Helper function for formatting file size
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Include footer
include_once '../../includes/footer.php';
?> 