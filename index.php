<?php
// Untuk debugging - tampilkan error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set flag for login page
$is_login_page = true;

// Include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    switch ($role) {
        case 'admin':
            redirect('pages/admin/dashboard.php');
            break;
        case 'guru':
            redirect('pages/guru/dashboard.php');
            break;
        case 'siswa':
            redirect('pages/siswa/dashboard.php');
            break;
        case 'kepsek':
            redirect('pages/kepsek/dashboard.php');
            break;
        default:
            // Fallback to login page
            break;
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitizeInput($_POST['identifier']);
    $password = $_POST['password'];
    $error = '';
    
    // Check which type of identifier was used based on user role:
    // - Admin: Only email
    // - Guru & Kepsek: NUPTK or email
    // - Siswa: Only NISN
    $query = "SELECT id, nama, email, password, tipe_pengguna, nisn, nuptk FROM pengguna 
              WHERE (tipe_pengguna = 'siswa' AND nisn = '$identifier') 
              OR (tipe_pengguna = 'admin' AND email = '$identifier')
              OR ((tipe_pengguna = 'guru' OR tipe_pengguna = 'kepsek') AND (nuptk = '$identifier' OR email = '$identifier'))";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_role'] = $user['tipe_pengguna'];
            
            // Update last login timestamp
            $updateQuery = "UPDATE pengguna SET last_login = NOW() WHERE id = '{$user['id']}'";
            mysqli_query($conn, $updateQuery);
            
            // Log the login activity
            logActivity($user['id'], 'login', 'Login berhasil');
            
            // Redirect based on role
            switch ($user['tipe_pengguna']) {
                case 'admin':
                    redirect('pages/admin/dashboard.php');
                    break;
                case 'guru':
                    redirect('pages/guru/dashboard.php');
                    break;
                case 'siswa':
                    redirect('pages/siswa/dashboard.php');
                    break;
                case 'kepsek':
                    redirect('pages/kepsek/dashboard.php');
                    break;
                default:
                    redirect('index.php');
                    break;
            }
        } else {
            $error = 'Password yang dimasukkan tidak valid.';
        }
    } else {
        $error = 'Data login tidak valid. Pastikan Anda menggunakan: NISN (untuk siswa), NUPTK/Email (untuk guru/kepsek), atau Email (untuk admin).';
    }
    
    if (!empty($error)) {
        setFlashMessage('error', $error);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portaldik - ELearning Coding</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #3730A3;
            --secondary-color: #06B6D4;
            --accent-color: #F59E0B;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --light-color: #F8FAFC;
            --dark-color: #1E293B;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-500: #64748B;
            --gray-700: #334155;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --border-radius: 12px;
            --border-radius-lg: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="25" cy="75" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="75" cy="25" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
            z-index: 1;
        }
        
        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 30%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 900px;
        }
        
        .login-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInUp 0.8s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-image {
            background: linear-gradient(45deg, #4F46E5, #06B6D4);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 400px;
            overflow: hidden;
        }
        
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.1)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .login-image-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            padding: 2rem;
        }
        
        .login-image-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .login-image-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-image-subtitle {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .login-form {
            padding: 2rem 2rem;
            position: relative;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            color: white;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .login-logo:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .login-logo i {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .login-logo-text {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .form-floating {
            position: relative;
        }
        
        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-color);
            width: 100%;
            box-sizing: border-box;
            height: 3.5rem;
            line-height: 1.5;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
            background: var(--white);
            outline: none;
        }
        
        .form-floating {
            position: relative;
            width: 100%;
        }
        
        .form-floating label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem;
            pointer-events: none;
            border: 2px solid transparent;
            transform-origin: 0 0;
            transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
            color: var(--gray-500);
            font-size: 1rem;
            line-height: 1.5;
            display: flex;
            align-items: center;
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-help {
            text-align: center;
            margin-top: 1.25rem;
        }
        
        .help-text {
            color: var(--gray-500);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .footer-text {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .school-name {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Alert Styles */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slideInDown 0.5s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .login-form {
                padding: 1.5rem 1.25rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .login-image {
                min-height: 200px;
            }
            
            .login-image-icon {
                font-size: 2.5rem;
            }
            
            .login-image-title {
                font-size: 1.1rem;
            }
            
            .form-control {
                padding: 0.875rem;
                height: 3.25rem;
                font-size: 0.95rem;
            }
            
            .form-floating label {
                padding: 0.875rem;
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-card {
                margin: 0 5px;
            }
            
            .login-form {
                padding: 1.25rem 1rem;
            }
            
            .login-title {
                font-size: 1.35rem;
            }
            
            .form-control {
                padding: 0.75rem;
                height: 3rem;
                font-size: 0.9rem;
            }
            
            .form-floating label {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .form-floating > .form-control:focus ~ label,
            .form-floating > .form-control:not(:placeholder-shown) ~ label {
                transform: scale(0.8) translateY(-0.4rem) translateX(0.1rem);
            }
            
            .login-image {
                min-height: 150px;
            }
        }
        
        /* Loading Animation */
        .btn-login.loading {
            pointer-events: none;
        }
        
        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Floating background shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container login-container">
        <?php displayFlashMessage(); ?>
        
        <div class="card login-card">
            <div class="row g-0 h-100">
                <!-- Left side - Image/Branding -->
                <div class="col-lg-6 login-image d-none d-lg-flex">
                    <div class="login-image-content">
                        <div class="login-image-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3 class="login-image-title">Selamat Datang di Portaldik</h3>
                        <p class="login-image-subtitle">Platform pembelajaran digital terdepan untuk masa depan pendidikan yang lebih baik</p>
                    </div>
                </div>
                
                <!-- Right side - Login Form -->
                <div class="col-lg-6">
                    <div class="login-form">
                        <div class="login-header">
                            <a href="#" class="login-logo">
                                <i class="fas fa-laptop-code"></i>
                                <span class="login-logo-text">Portaldik</span>
                            </a>
                            <h2 class="login-title">Masuk ke Akun</h2>
                            <p class="login-subtitle">Silakan masuk untuk melanjutkan pembelajaran digital Anda</p>
                        </div>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="identifier" name="identifier" placeholder=" " required>
                                    <label for="identifier">NISN (Siswa) / NUPTK atau Email (Guru/Kepsek) / Email (Admin)</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="password" name="password" placeholder=" " required>
                                    <label for="password">Kata Sandi</label>
                                </div>
                            </div>
                            
                            <button class="w-100 btn btn-primary btn-login" type="submit" id="loginBtn">
                                <span class="btn-text">Masuk Sekarang</span>
                            </button>
                        </form>
                        
                        <div class="login-footer">
                            <p class="footer-text">&copy; <?php echo date('Y'); ?> Portaldik - ELearning Coding</p>
                            <p class="footer-text school-name">SDN Bintaro 05 PAGI</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Form submission with loading state
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const btnText = loginBtn.querySelector('.btn-text');
            
            loginForm.addEventListener('submit', function(e) {
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                btnText.textContent = 'Memproses...';
            });
            
            // Input focus effects
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
                
                // Check if input has value on page load
                if (input.value) {
                    input.parentElement.classList.add('focused');
                }
            });
            
            // Add smooth scrolling for mobile
            if (window.innerWidth < 768) {
                document.body.style.overflowX = 'hidden';
            }
        });
        
        // Add some interactive elements
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                const xOffset = (x - 0.5) * speed * 20;
                const yOffset = (y - 0.5) * speed * 20;
                
                shape.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
            });
        });
    </script>
</body>
</html>