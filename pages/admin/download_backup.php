<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Check if file parameter is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    setFlashMessage('error', 'Nama file tidak valid.');
    header('Location: backup.php');
    exit;
}

$file_name = sanitizeInput($_GET['file']);

// Validate file name (only allow .sql files with alphanumeric characters, underscore, hyphen)
if (!preg_match('/^[a-zA-Z0-9_\-]+\.sql$/', $file_name)) {
    setFlashMessage('error', 'Nama file tidak valid.');
    header('Location: backup.php');
    exit;
}

// Check if file exists
$backup_path = '../../backups/';
$file_path = $backup_path . $file_name;

if (!file_exists($file_path)) {
    setFlashMessage('error', 'File tidak ditemukan.');
    header('Location: backup.php');
    exit;
}

// Log activity
logActivity($_SESSION['user_id'], 'download', "Admin mengunduh file backup: $file_name");

// Set headers and send file
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?> 