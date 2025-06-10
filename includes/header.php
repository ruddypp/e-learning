<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';
// Include utility functions
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portaldik - Elearning Coding</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($is_login_page) ? '' : '../'; ?>assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo isset($is_login_page) ? '' : '../'; ?>assets/images/favicon.ico" type="image/x-icon">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom styles for this template -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #f39c12;
            --accent-color: #9b59b6;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        
        .navbar-brand img {
            margin-right: 10px;
            height: 40px;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        
        .nav-link {
            color: var(--dark-color);
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background-color: #e67e22;
            border-color: #e67e22;
        }
        
        .sidebar {
            height: 100%;
            position: fixed;
            top: 56px;
            left: 0;
            width: 250px;
            z-index: 1030;
            background-color: white;
            overflow-x: hidden;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            padding: 15px 20px;
            color: var(--dark-color);
            border-left: 4px solid transparent;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .sidebar.collapsed {
                width: 80px;
            }
            
            .sidebar.collapsed .nav-link span {
                display: none;
            }
            
            .sidebar.collapsed .nav-link i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: 0;
                padding: 0;
                overflow-x: hidden;
            }
            
            .content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block !important;
            }
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--primary-color);
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.2s;
        }
        
        .sidebar-toggle:hover {
            color: var(--dark-color);
            transform: scale(1.1);
        }
        
        .logo-text {
            display: inline-block;
        }
        
        @media (max-width: 576px) {
            .logo-text {
                display: none;
            }
        }
        
        /* Dashboard cards */
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card .card-body {
            display: flex;
            align-items: center;
        }
        
        .dashboard-card .icon {
            font-size: 40px;
            margin-right: 15px;
            color: var(--primary-color);
        }
        
        .dashboard-card .card-title {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .dashboard-card .card-text {
            font-size: 24px;
            font-weight: bold;
        }
        
        /* Custom colors for different user roles */
        .admin-theme {
            --primary-color: #3498db;
        }
        
        .guru-theme {
            --primary-color: #2ecc71;
        }
        
        .siswa-theme {
            --primary-color: #f39c12;
        }
        
        .kepsek-theme {
            --primary-color: #9b59b6;
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="<?php 
    if (isLoggedIn()) {
        echo getUserRole() . '-theme'; 
    }
?>">
<?php if (isLoggedIn() && !isset($is_login_page)): ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
            <button class="btn sidebar-toggle me-2" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="../../index.php">
                <span class="logo-text">Portaldik-ELearning</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php
                            // Detect if we're in localhost or production
                            $base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) 
                                      ? '' 
                                      : '/elearning';
                            ?>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/pages/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="pt-4">
            <?php 
            // Determine base path based on current file location
            $base_path = '';
            if (strpos($_SERVER['PHP_SELF'], '/pages/profile.php') !== false) {
                $base_path = '../';
            } else {
                $base_path = '../../';
            }
            
            if (hasRole('admin')): ?>
                <a href="<?php echo $base_path; ?>pages/admin/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Kelola Pengguna</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/classes.php" class="nav-link <?php echo $current_page == 'classes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-school"></i>
                    <span>Kelola Kelas</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/verify.php" class="nav-link <?php echo $current_page == 'verify.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Verifikasi Data</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/activity_log.php" class="nav-link <?php echo $current_page == 'activity_log.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Aktivitas Pengguna</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/questionnaires.php" class="nav-link <?php echo $current_page == 'questionnaires.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Kuesioner</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/backup.php" class="nav-link <?php echo $current_page == 'backup.php' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    <span>Backup & Maintenance</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/admin/logs.php" class="nav-link <?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Log Sistem</span>
                </a>
            <?php elseif (hasRole('guru')): ?>
                <a href="<?php echo $base_path; ?>pages/guru/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/guru/materials.php" class="nav-link <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Materi Coding</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/guru/quizzes.php" class="nav-link <?php echo $current_page == 'quizzes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Quiz & Tugas</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/guru/grades.php" class="nav-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Nilai Siswa</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/guru/questionnaires.php" class="nav-link <?php echo $current_page == 'questionnaires.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Kuesioner</span>
                </a>
            <?php elseif (hasRole('siswa')): ?>
                <a href="<?php echo $base_path; ?>pages/siswa/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/siswa/materials.php" class="nav-link <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Materi Coding</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/siswa/quizzes.php" class="nav-link <?php echo $current_page == 'quizzes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Quiz & Tugas</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/siswa/grades.php" class="nav-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Nilai Saya</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/siswa/questionnaires.php" class="nav-link <?php echo $current_page == 'questionnaires.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Kuesioner</span>
                </a>
            <?php elseif (hasRole('kepsek')): ?>
                <a href="<?php echo $base_path; ?>pages/kepsek/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/kepsek/materials.php" class="nav-link <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Materi Pembelajaran</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/kepsek/classes.php" class="nav-link <?php echo $current_page == 'classes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-school"></i>
                    <span>Kelas</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/kepsek/grades.php" class="nav-link <?php echo $current_page == 'grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Hasil Belajar</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/kepsek/activities.php" class="nav-link <?php echo $current_page == 'activities.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Aktivitas Pembelajaran</span>
                </a>
                <a href="<?php echo $base_path; ?>pages/kepsek/questionnaires.php" class="nav-link <?php echo $current_page == 'questionnaires.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Hasil Kuesioner</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main content -->
    <div class="content pt-5 mt-3">
        <?php displayFlashMessage(); ?>
        
<?php else: ?>
    <!-- Content for login/public pages will be added directly -->
<?php endif; ?> 