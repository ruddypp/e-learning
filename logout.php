<?php
// Include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isLoggedIn()) {
    // Get user details before destroying session
    $userId = $_SESSION['user_id'];
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Pengguna';
    
    // Log the logout activity
    try {
        $checkUserQuery = "SELECT id FROM pengguna WHERE id = ?";
        $stmt = mysqli_prepare($conn, $checkUserQuery);
        mysqli_stmt_bind_param($stmt, "s", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Only log if the user exists
            logActivity($userId, 'logout', 'Logout berhasil');
        }
    } catch (Exception $e) {
        // If there's an error, continue with logout anyway
    }
    
    // Destroy the session properly
    $_SESSION = array();
    
    // If a session cookie is used, unset that too
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally destroy the session
    session_destroy();
    
    // Start a new session for the flash message
    session_start();
    setFlashMessage('success', $userName . ' telah berhasil logout.');
} else {
    // If not logged in, just show a message
    setFlashMessage('info', 'Anda telah logout.');
}

// Redirect to login page
header('Location: index.php');
exit;
?> 