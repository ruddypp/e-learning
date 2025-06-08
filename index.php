<?php
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
    
    // Check which type of identifier was used (NISN, NUPTK, or email for admin)
    $query = "SELECT id, nama, email, password, tipe_pengguna, nisn, nuptk FROM pengguna 
              WHERE nisn = '$identifier' OR nuptk = '$identifier' OR (email = '$identifier' AND tipe_pengguna = 'admin')";
    
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
        $error = 'NISN, NUPTK, atau Email tidak ditemukan.';
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
    <title>Portaldik- ELearning Coding</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
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
            height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        
        .login-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .login-card {
            border-radius: 1rem;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-card .row {
            height: 100%;
        }
        
        .login-image {
            background-image: url('assets/images/login-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 400px;
        }
        
        .login-form {
            padding: 2rem;
        }
        
        .login-title {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        
        .login-subtitle {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .login-logo img {
            height: 60px;
            margin-right: 15px;
        }
        
        .login-logo-text {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .login-logo-subtext {
            font-size: 14px;
            color: #6c757d;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .login-footer {
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            margin-top: 2rem;
        }
        
        .login-help {
            text-align: center;
            margin-top: 1rem;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .login-image {
                min-height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <?php displayFlashMessage(); ?>
        
        <div class="card login-card">
            <div class="row g-0">
                <div class="col-md-6 login-image d-none d-md-block">
                    <!-- Background image set in CSS -->
                </div>
                <div class="col-md-6">
                    <div class="login-form">
                        <div class="login-logo">
                            <div>
                                <div class="login-logo-text">Portaldik-ELearning</div>
                                <div class="login-logo-subtext">Manajemen Terpadu Pembelajaran Daring</div>
                            </div>
                        </div>
                        
                        <h2 class="login-title">Selamat Datang!</h2>
                        <p class="login-subtitle">Silakan masuk ke akun Anda, dan mulai pembelajaran daring Anda.</p>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="identifier" name="identifier" placeholder="NISN/NUPTK/Email" required>
                                <label for="identifier">NISN / NUPTK / Email</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password">Password</label>
                            </div>
                            
                            <button class="w-100 btn btn-lg btn-primary btn-login" type="submit">Masuk</button>
                            
                            <div class="login-help">
                                <p>Lupa password? Hubungi administrator.</p>
                            </div>
                        </form>
                        
                        <div class="login-footer">
                            <p>&copy; <?php echo date('Y'); ?> Portaldik - ELearning Coding</p>
                            <p>SDN Bintaro 05 PAGI</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Auto-hide flash messages after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html> 