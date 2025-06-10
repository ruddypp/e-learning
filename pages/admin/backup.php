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
        $deskripsi = isset($_POST['deskripsi']) ? sanitizeInput($_POST['deskripsi']) : "Backup database $timestamp";
        
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
                // Get file size
                $fileSize = filesize($backupFile);
                
                // Generate backup ID
                $backupId = generateUniqueId('BKP');
                
                // Record backup in database
                $query = "INSERT INTO backup_data (id, nama_file, ukuran_file, dibuat_oleh, deskripsi, status) 
                          VALUES ('$backupId', 'elearning_backup_$timestamp.sql', $fileSize, '{$_SESSION['user_id']}', '$deskripsi', 'success')";
                
                if (mysqli_query($conn, $query)) {
                    // Log the action
                    logActivity($_SESSION['user_id'], 'create_backup', "Admin membuat backup database: elearning_backup_$timestamp.sql", $backupId);
                    
                    // Add to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'Backup Database', 'Membuat backup: elearning_backup_$timestamp.sql', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                    
                    setFlashMessage('success', 'Backup database berhasil dibuat dan dicatat.');
                } else {
                    setFlashMessage('warning', 'Backup database berhasil dibuat tetapi gagal dicatat: ' . mysqli_error($conn));
                }
            } else {
                // Get error details for better troubleshooting
                $errorMsg = implode("\n", $output);
                if (empty($errorMsg)) {
                    $errorMsg = "Kode error: $return_var";
                }
                
                // Record failed backup attempt
                $backupId = generateUniqueId('BKP');
                $query = "INSERT INTO backup_data (id, nama_file, ukuran_file, dibuat_oleh, deskripsi, status) 
                          VALUES ('$backupId', 'elearning_backup_$timestamp.sql', 0, '{$_SESSION['user_id']}', 'Backup gagal: $errorMsg', 'failed')";
                mysqli_query($conn, $query);
                
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
                    // Find backup record
                    $query = "SELECT id FROM backup_data WHERE nama_file = '$backupFile'";
                    $result = mysqli_query($conn, $query);
                    $backupId = null;
                    
                    if (mysqli_num_rows($result) > 0) {
                        $row = mysqli_fetch_assoc($result);
                        $backupId = $row['id'];
                    }
                    
                    // Log the action
                    logActivity($_SESSION['user_id'], 'create_backup', "Admin melakukan restore database dari: $backupFile", $backupId);
                    
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
                // Find and update backup record
                $query = "SELECT id FROM backup_data WHERE nama_file = '$backupFile'";
                $result = mysqli_query($conn, $query);
                
                if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $backupId = $row['id'];
                    
                    // Update status in database
                    $update_query = "UPDATE backup_data SET status = 'failed' WHERE id = '$backupId'";
                    mysqli_query($conn, $update_query);
                    
                    // Log the action
                    logActivity($_SESSION['user_id'], 'create_backup', "Admin menghapus file backup: $backupFile", $backupId);
                }
                
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
    
    // Reset Database (keeping users)
    else if (isset($_POST['action']) && $_POST['action'] === 'reset_database') {
        try {
            // First, create a backup before resetting
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = "$backupDir/pre_reset_backup_$timestamp.sql";
            $deskripsi = "Backup otomatis sebelum reset database";
            
            // Get database configuration
            $host = DB_HOST;
            $user = DB_USERNAME;
            $pass = DB_PASSWORD;
            $name = DB_NAME;
            
            // Find mysqldump path
            $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump';
            if (!file_exists($mysqldump)) {
                $possible_paths = [
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'C:\\wamp\\bin\\mysql\\mysql5.7.26\\bin\\mysqldump.exe',
                    '/usr/bin/mysqldump',
                    '/usr/local/bin/mysqldump',
                    'mysqldump'
                ];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $mysqldump = $path;
                        break;
                    }
                }
            }
            
            // Create backup command
            $command = "\"$mysqldump\" --host=$host --user=$user";
            if (!empty($pass)) {
                $command .= " --password=$pass";
            }
            $command .= " $name > \"$backupFile\"";
            
            // Execute backup command
            $output = [];
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
                // Record backup in database
                $backupId = generateUniqueId('BKP');
                $fileSize = filesize($backupFile);
                $query = "INSERT INTO backup_data (id, nama_file, ukuran_file, dibuat_oleh, deskripsi, status) 
                          VALUES ('$backupId', 'pre_reset_backup_$timestamp.sql', $fileSize, '{$_SESSION['user_id']}', '$deskripsi', 'success')";
                mysqli_query($conn, $query);
                
                // Now perform the reset operation
                // 1. First, get tables that need to be preserved
                $preserved_tables = ['pengguna', 'pengguna_metadata', 'roles', 'log_sistem', 'backup_data'];
                
                // 2. Get all tables in the database
                $tables_query = "SHOW TABLES";
                $tables_result = mysqli_query($conn, $tables_query);
                $all_tables = [];
                
                while ($row = mysqli_fetch_row($tables_result)) {
                    $all_tables[] = $row[0];
                }
                
                // 3. Identify tables to reset (all except preserved ones)
                $tables_to_reset = array_diff($all_tables, $preserved_tables);
                
                // 4. Start a transaction
                mysqli_begin_transaction($conn);
                
                $reset_success = true;
                
                try {
                    // 5. Disable foreign key checks temporarily
                    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
                    
                    // 6. Truncate each table that needs to be reset
                    foreach ($tables_to_reset as $table) {
                        $truncate_query = "TRUNCATE TABLE `$table`";
                        if (!mysqli_query($conn, $truncate_query)) {
                            throw new Exception("Failed to truncate table $table: " . mysqli_error($conn));
                        }
                    }
                    
                    // 7. Re-enable foreign key checks
                    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
                    
                    // 8. Commit the transaction
                    mysqli_commit($conn);
                    
                    // 9. Log the action
                    logActivity($_SESSION['user_id'], 'create_backup', "Admin melakukan reset database (mempertahankan data pengguna)", null);
                    
                    // 10. Add to system log
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_query = "INSERT INTO log_sistem (pengguna_id, aksi, detail, ip_address, user_agent) 
                                 VALUES ('{$_SESSION['user_id']}', 'Reset Database', 'Reset database dengan mempertahankan data pengguna', '$ip', '$user_agent')";
                    mysqli_query($conn, $log_query);
                    
                    setFlashMessage('success', 'Database berhasil direset. Semua data telah dihapus kecuali data pengguna.');
                } catch (Exception $e) {
                    // Rollback on error
                    mysqli_rollback($conn);
                    $reset_success = false;
                    setFlashMessage('error', 'Gagal mereset database: ' . $e->getMessage());
                }
                
                if (!$reset_success) {
                    // If reset failed, update backup status
                    $update_query = "UPDATE backup_data SET deskripsi = CONCAT(deskripsi, ' (Reset gagal)') WHERE id = '$backupId'";
                    mysqli_query($conn, $update_query);
                }
            } else {
                setFlashMessage('error', 'Gagal membuat backup sebelum reset database.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Error: ' . $e->getMessage());
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header('Location: backup.php');
    exit;
}

// Get list of backup files from database
$query_backups = "SELECT b.*, p.nama as admin_name 
                 FROM backup_data b
                 JOIN pengguna p ON b.dibuat_oleh = p.id
                 WHERE b.status != 'deleted'
                 ORDER BY b.tanggal_dibuat DESC";
$result_backups = mysqli_query($conn, $query_backups);
$backups_from_db = [];

if ($result_backups) {
    while ($row = mysqli_fetch_assoc($result_backups)) {
        $backups_from_db[$row['nama_file']] = $row;
    }
}

// Get list of backup files from filesystem
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $fileInfo = [
                'name' => $file,
                'size' => formatBytes(filesize("$backupDir/$file")),
                'date' => date("Y-m-d H:i:s", filemtime("$backupDir/$file"))
            ];
            
            // Add database info if available
            if (isset($backups_from_db[$file])) {
                $fileInfo['description'] = $backups_from_db[$file]['deskripsi'];
                $fileInfo['admin_name'] = $backups_from_db[$file]['admin_name'];
                $fileInfo['status'] = $backups_from_db[$file]['status'];
                $fileInfo['backup_id'] = $backups_from_db[$file]['id'];
            }
            
            $backupFiles[] = $fileInfo;
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
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi (opsional)</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Tambahkan deskripsi untuk backup ini"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Apakah Anda yakin ingin membuat backup database saat ini?');">
                            <i class="fas fa-download me-2"></i> Buat Backup Baru
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Restore database akan menimpa semua data saat ini dengan data dari backup yang dipilih.
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Reset Database:</strong> Opsi ini akan menghapus seluruh data kecuali data pengguna.
                    </div>
                    
                    <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetDatabaseModal">
                        <i class="fas fa-trash-alt me-2"></i> Reset Database (Pertahankan Pengguna)
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Backup History -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Riwayat Backup</h5>
                </div>
                <div class="card-body">
                    <?php if (count($backupFiles) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th>Deskripsi</th>
                                        <th>Ukuran</th>
                                        <th>Tanggal</th>
                                        <th>Dibuat Oleh</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupFiles as $file): ?>
                                        <tr>
                                            <td><?php echo $file['name']; ?></td>
                                            <td>
                                                <?php 
                                                if (isset($file['description'])) {
                                                    echo $file['description'];
                                                } else {
                                                    echo "<em>Tidak ada deskripsi</em>";
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $file['size']; ?></td>
                                            <td><?php echo formatDate($file['date'], true); ?></td>
                                            <td>
                                                <?php 
                                                if (isset($file['admin_name'])) {
                                                    echo $file['admin_name'];
                                                } else {
                                                    echo "<em>Unknown</em>";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (isset($file['status'])) {
                                                    echo '<span class="badge bg-' . ($file['status'] == 'success' ? 'success' : 'danger') . '">' . 
                                                          ucfirst($file['status']) . '</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">Untracked</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="download_backup.php?file=<?php echo $file['name']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-bs-toggle="modal" data-bs-target="#restoreModal"
                                                            data-file="<?php echo $file['name']; ?>">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                            data-file="<?php echo $file['name']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada file backup yang tersedia.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="restoreModalLabel">Konfirmasi Restore Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan:</strong> Proses ini akan mengganti seluruh data saat ini dengan data dari file backup yang dipilih.
                </div>
                <p>Apakah Anda yakin ingin melakukan restore database dari file: <strong id="restoreFileName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="backup.php">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="file" id="restoreFileInput">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Ya, Restore Database</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus file backup: <strong id="deleteFileName"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="backup.php">
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="file" id="deleteFileInput">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus Backup</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Database Confirmation Modal -->
<div class="modal fade" id="resetDatabaseModal" tabindex="-1" aria-labelledby="resetDatabaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetDatabaseModalLabel">Konfirmasi Reset Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>PERINGATAN!</strong> Tindakan ini akan menghapus semua data dalam database KECUALI data pengguna.
                </div>
                <p>Data berikut akan dihapus:</p>
                <ul>
                    <li>Semua kelas</li>
                    <li>Semua materi</li>
                    <li>Semua kuis dan hasil kuis</li>
                    <li>Semua tugas dan pengumpulan tugas</li>
                    <li>Semua nilai</li>
                    <li>Dan data lainnya</li>
                </ul>
                <p>Data berikut akan <strong>dipertahankan</strong>:</p>
                <ul>
                    <li>Akun pengguna (admin, guru, siswa, dll)</li>
                    <li>Peran pengguna</li>
                    <li>Log sistem</li>
                    <li>Riwayat backup</li>
                </ul>
                <p class="text-danger fw-bold">Tindakan ini tidak dapat dibatalkan!</p>
                <p>Backup otomatis akan dibuat sebelum reset dilakukan.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="backup.php">
                    <input type="hidden" name="action" value="reset_database">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Reset Database</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Set file name in restore modal
document.querySelectorAll('[data-bs-target="#restoreModal"]').forEach(function(button) {
    button.addEventListener('click', function() {
        const file = this.getAttribute('data-file');
        document.getElementById('restoreFileName').textContent = file;
        document.getElementById('restoreFileInput').value = file;
    });
});

// Set file name in delete modal
document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(function(button) {
    button.addEventListener('click', function() {
        const file = this.getAttribute('data-file');
        document.getElementById('deleteFileName').textContent = file;
        document.getElementById('deleteFileInput').value = file;
    });
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';

/**
 * Format bytes to human readable format
 * 
 * @param int $bytes Number of bytes
 * @param int $precision Precision of the result
 * @return string Formatted size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?> 