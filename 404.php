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
    <title>404 - Halaman Tidak Ditemukan | MENTARI</title>
    
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
            color: #3498db;
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
    </style>
</head>
<body>
    <div class="container error-container">
        <div class="error-code">404</div>
        <div class="error-message">Halaman yang Anda cari tidak ditemukan.</div>
        <div class="error-description mb-4">
            Maaf, halaman yang Anda coba akses tidak ada atau telah dipindahkan.
        </div>
        <div class="error-actions">
            <?php if (isLoggedIn()): ?>
                <?php 
                $role = getUserRole();
                $dashboardUrl = "pages/{$role}/dashboard.php";
                ?>
                <a href="<?php echo $dashboardUrl; ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-home me-2"></i>Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-home me-2"></i>Kembali ke Halaman Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 