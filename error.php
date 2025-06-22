<?php
// Set flag for login page (to not show sidebar)
$is_login_page = true;

// Include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error | MENTARI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            text-align: center;
            max-width: 600px;
            padding: 2rem;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .error-message {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: #555;
        }
        
        .error-actions {
            margin-top: 2rem;
        }

        .error-details {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 2rem;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container error-container">
        <div class="error-code">500</div>
        <div class="error-message">Terjadi Kesalahan pada Server</div>
        <div class="error-description mb-4">
            Maaf, terjadi kesalahan internal pada server saat memproses permintaan Anda. Tim teknis kami telah diberitahu dan sedang mengatasi masalah ini.
        </div>
        
        <?php if (isLoggedIn() && hasRole('admin')): ?>
        <div class="error-details">
            <h5><i class="fas fa-bug me-2"></i>Informasi Kesalahan:</h5>
            <ul class="list-unstyled">
                <li><strong>URL:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></li>
                <li><strong>Waktu:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                <li><strong>IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></li>
                <li><strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></li>
            </ul>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Periksa error log server untuk informasi lebih detail.
            </div>
        </div>
        <?php endif; ?>
        
        <div class="error-actions">
            <?php if (isLoggedIn()): ?>
                <?php 
                $role = getUserRole();
                $dashboardUrl = "pages/{$role}/dashboard.php";
                ?>
                <a href="<?php echo $dashboardUrl; ?>" class="btn btn-primary btn-lg me-2">
                    <i class="fas fa-home me-2"></i>Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-primary btn-lg me-2">
                    <i class="fas fa-home me-2"></i>Kembali ke Halaman Login
                </a>
            <?php endif; ?>
            
            <button class="btn btn-outline-secondary btn-lg" onclick="window.location.reload();">
                <i class="fas fa-sync-alt me-2"></i>Muat Ulang Halaman
            </button>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                Jika masalah berlanjut, silakan hubungi administrator sistem di <a href="mailto:admin@elearning.com">admin@elearning.com</a>
            </small>
        </div>
    </div>
</body>
</html> 