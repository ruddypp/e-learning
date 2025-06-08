<?php
/**
 * Common utility functions for the e-learning system
 */

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a unique ID
 * 
 * @param string $prefix Prefix for the ID
 * @return string The generated ID
 */
function generateUniqueId($prefix = '') {
    // Calculate remaining length after the prefix to stay within 20 characters
    $remainingLength = 20 - strlen($prefix);
    
    // If prefix is too long, truncate it
    if ($remainingLength <= 0) {
        $prefix = substr($prefix, 0, 3);
        $remainingLength = 17;
    }
    
    // Divide the remaining length between uniqid and random parts
    $uniqidLength = min(8, $remainingLength - 4);
    $randomLength = $remainingLength - $uniqidLength;
    
    return $prefix . substr(uniqid(), 0, $uniqidLength) . substr(md5(rand()), 0, $randomLength);
}

/**
 * Generate a unique ID for a specific table
 * 
 * @param string $prefix Prefix for the ID
 * @param string $table Table name to check uniqueness
 * @param string $field Field name in the table for ID
 * @return string The generated ID
 */
function generateID($prefix = '', $table = '', $field = 'id') {
    global $conn;
    
    // Generate a new ID
    $new_id = $prefix . substr(uniqid(), -8) . substr(md5(rand()), 0, 4);
    
    // If table and field are provided, check for uniqueness
    if (!empty($table) && !empty($field)) {
        $is_unique = false;
        
        while (!$is_unique) {
            $query = "SELECT $field FROM $table WHERE $field = '$new_id'";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) === 0) {
                $is_unique = true;
            } else {
                // Generate a new ID if the current one already exists
                $new_id = $prefix . substr(uniqid(), -8) . substr(md5(rand()), 0, 4);
            }
        }
    }
    
    return $new_id;
}

/**
 * Sanitize user input
 * 
 * @param mixed $data The data to sanitize
 * @return mixed The sanitized data
 */
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    return $data;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get user role
 * 
 * @return string The user role or empty string if not logged in
 */
function getUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
}

/**
 * Check if the current user has the specified role
 * 
 * @param string|array $roles The role(s) to check
 * @return bool True if the user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

/**
 * Redirect to a URL
 * 
 * @param string $url The URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if the current user has access to a page
 * 
 * @param array $allowedRoles The roles allowed to access the page
 */
function checkAccess($allowedRoles) {
    if (!isLoggedIn() || !hasRole($allowedRoles)) {
        setFlashMessage('error', 'You do not have permission to access this page.');
        redirect('../index.php');
    }
}

/**
 * Set a flash message to be displayed on the next page
 * 
 * @param string $type The type of message (success, error, info, warning)
 * @param string $message The message to display
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display a flash message if it exists and clear it
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        $alertClass = 'alert-info';
        if ($type === 'success') {
            $alertClass = 'alert-success';
        } elseif ($type === 'error') {
            $alertClass = 'alert-danger';
        } elseif ($type === 'warning') {
            $alertClass = 'alert-warning';
        }
        
        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        // Clear the flash message
        unset($_SESSION['flash_message']);
    }
}

/**
 * Log user activity
 * 
 * @param string $userId The user ID
 * @param string $activityType The type of activity
 * @param string $description Description of the activity
 * @param string $referenceId Reference ID (optional)
 * @return bool True if successful, false otherwise
 */
function logActivity($userId, $activityType, $description = '', $referenceId = null) {
    global $conn;
    
    $id = generateUniqueId('ACT');
    $userId = sanitizeInput($userId);
    $activityType = sanitizeInput($activityType);
    $description = sanitizeInput($description);
    $referenceId = $referenceId ? sanitizeInput($referenceId) : null;
    
    $query = "INSERT INTO laporan_aktivitas (id, pengguna_id, tipe_aktivitas, deskripsi, referensi_id) 
              VALUES ('$id', '$userId', '$activityType', '$description', " . ($referenceId ? "'$referenceId'" : "NULL") . ")";
    
    return mysqli_query($conn, $query);
}

/**
 * Format date to Indonesian format
 * 
 * @param string $date The date to format
 * @param bool $withTime Whether to include time
 * @return string The formatted date
 */
function formatDate($date, $withTime = false) {
    if (!$date) return '';
    
    $timestamp = strtotime($date);
    $format = $withTime ? 'd F Y H:i' : 'd F Y';
    
    $indonesianMonths = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    
    $englishDate = date($format, $timestamp);
    
    foreach ($indonesianMonths as $english => $indonesian) {
        $englishDate = str_replace($english, $indonesian, $englishDate);
    }
    
    return $englishDate;
}

/**
 * Get error message for PHP file upload errors
 * 
 * @param int $errorCode The error code
 * @return string The error message
 */
function getFileUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

/**
 * Limit text to a specific length and add ellipsis
 * 
 * @param string $text The text to limit
 * @param int $limit The maximum length
 * @return string The limited text
 */
function limitText($text, $limit = 100) {
    if (strlen($text) <= $limit) {
        return $text;
    }
    
    return substr($text, 0, $limit) . '...';
}
?> 