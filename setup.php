<?php
/**
 * Setup script for MENTARI E-Learning System
 * This script checks and creates necessary directories with proper permissions
 */

// Define required directories
$required_dirs = [
    'backups',
    'uploads',
    'uploads/materials',
    'uploads/assignments',
    'uploads/profiles'
];

// Track results
$results = [];

// Check and create directories
foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            $results[$dir] = [
                'status' => 'created',
                'message' => "Directory '$dir' created successfully."
            ];
        } else {
            $results[$dir] = [
                'status' => 'error',
                'message' => "Failed to create directory '$dir'. Please check permissions."
            ];
        }
    } else {
        // Directory exists, check if writable
        if (is_writable($dir)) {
            $results[$dir] = [
                'status' => 'exists',
                'message' => "Directory '$dir' already exists and is writable."
            ];
        } else {
            // Try to make it writable
            if (chmod($dir, 0755)) {
                $results[$dir] = [
                    'status' => 'fixed',
                    'message' => "Directory '$dir' permissions updated to make it writable."
                ];
            } else {
                $results[$dir] = [
                    'status' => 'warning',
                    'message' => "Directory '$dir' exists but is not writable. Please set permissions manually."
                ];
            }
        }
    }
}

// Create .htaccess files to protect directories if they don't exist
$htaccess_content = "Options -Indexes\nDeny from all\n<FilesMatch '\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|txt)$'>\nAllow from all\n</FilesMatch>";
$htaccess_dirs = ['uploads', 'uploads/materials', 'uploads/assignments', 'uploads/profiles'];

foreach ($htaccess_dirs as $dir) {
    $htaccess_file = $dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        if (file_put_contents($htaccess_file, $htaccess_content)) {
            $results[$htaccess_file] = [
                'status' => 'created',
                'message' => "Security .htaccess created in '$dir'."
            ];
        } else {
            $results[$htaccess_file] = [
                'status' => 'error',
                'message' => "Failed to create .htaccess in '$dir'."
            ];
        }
    } else {
        $results[$htaccess_file] = [
            'status' => 'exists',
            'message' => ".htaccess already exists in '$dir'."
        ];
    }
}

// Special .htaccess for backups directory - deny all access
$backups_htaccess = "Order deny,allow\nDeny from all";
if (!file_exists('backups/.htaccess')) {
    if (file_put_contents('backups/.htaccess', $backups_htaccess)) {
        $results['backups/.htaccess'] = [
            'status' => 'created',
            'message' => "Security .htaccess created in 'backups' - all access denied."
        ];
    } else {
        $results['backups/.htaccess'] = [
            'status' => 'error',
            'message' => "Failed to create .htaccess in 'backups'."
        ];
    }
} else {
    $results['backups/.htaccess'] = [
        'status' => 'exists',
        'message' => ".htaccess already exists in 'backups'."
    ];
}

// Check if we're in CLI or browser mode
if (php_sapi_name() === 'cli') {
    // CLI output
    echo "MENTARI E-Learning Setup Results:\n";
    echo str_repeat("=", 80) . "\n";
    foreach ($results as $item => $result) {
        $status_text = strtoupper($result['status']);
        echo "[$status_text] $item: {$result['message']}\n";
    }
    echo str_repeat("=", 80) . "\n";
    
    // Summary
    $success = count(array_filter($results, function($r) { return in_array($r['status'], ['created', 'exists', 'fixed']); }));
    $warnings = count(array_filter($results, function($r) { return $r['status'] === 'warning'; }));
    $errors = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
    
    echo "Summary: $success successful, $warnings warnings, $errors errors\n";
    
    if ($errors > 0 || $warnings > 0) {
        echo "\nPlease fix the issues above before continuing.\n";
        exit(1);
    } else {
        echo "\nSetup completed successfully. Your system is ready to use.\n";
        exit(0);
    }
} else {
    // Browser output (HTML)
    header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENTARI E-Learning Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 40px 0;
            background-color: #f5f5f5;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .status-created { color: #198754; }
        .status-exists { color: #0d6efd; }
        .status-fixed { color: #fd7e14; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container setup-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h1 class="h3 mb-0">MENTARI E-Learning Setup</h1>
            </div>
            <div class="card-body">
                <h2 class="h5 mb-3">Directory Setup Results</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $item => $result): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($item); ?></code></td>
                                <td>
                                    <span class="badge status-<?php echo $result['status']; ?>">
                                        <?php echo strtoupper(htmlspecialchars($result['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($result['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                // Summary
                $success = count(array_filter($results, function($r) { return in_array($r['status'], ['created', 'exists', 'fixed']); }));
                $warnings = count(array_filter($results, function($r) { return $r['status'] === 'warning'; }));
                $errors = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
                
                if ($errors > 0 || $warnings > 0):
                ?>
                    <div class="alert alert-warning">
                        <h4 class="alert-heading">Setup Incomplete</h4>
                        <p>There are <?php echo $errors; ?> errors and <?php echo $warnings; ?> warnings that need to be addressed.</p>
                        <p>Please fix these issues manually or contact your system administrator before using the system.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Setup Complete!</h4>
                        <p>All directories and permissions have been configured correctly.</p>
                        <p>Your MENTARI E-Learning system is now ready to use.</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary">Go to Homepage</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}
?> 