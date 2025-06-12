<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add_student') {
        // Get form data
        $nama = sanitizeInput($_POST['nama']);
        $nisn = sanitizeInput($_POST['nisn']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password']; // Will be hashed
        $kelas_id = sanitizeInput($_POST['kelas_id']);
        
        // Validate required fields
        if (empty($nama) || empty($nisn) || empty($email) || empty($password) || empty($kelas_id)) {
            setFlashMessage('error', 'Semua field yang bertanda * wajib diisi.');
            header('Location: class_detail.php?id=' . $kelas_id);
            exit;
        }
        
        // Check if NISN or email already exists
        $query = "SELECT id FROM pengguna WHERE nisn = '$nisn' OR email = '$email'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            setFlashMessage('error', 'NISN atau Email sudah terdaftar.');
            header('Location: class_detail.php?id=' . $kelas_id);
            exit;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate a unique ID with the same format as users.php
        $unique_id = 'U' . substr(md5(uniqid()), 0, 8);
        
        // Current date for registration
        $current_date = date('Y-m-d');
        
        // Insert new student
        $query = "INSERT INTO pengguna (id, nama, nisn, email, password, kelas_id, tipe_pengguna, status, tanggal_daftar) 
                 VALUES ('$unique_id', '$nama', '$nisn', '$email', '$hashed_password', '$kelas_id', 'siswa', 'aktif', '$current_date')";
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Siswa baru berhasil ditambahkan.');
        } else {
            setFlashMessage('error', 'Gagal menambahkan siswa: ' . mysqli_error($conn));
        }
        
        header('Location: class_detail.php?id=' . $kelas_id);
        exit;
    } elseif ($action === 'edit_student') {
        // Get form data
        $student_id = sanitizeInput($_POST['student_id']);
        $nama = sanitizeInput($_POST['nama']);
        $nisn = sanitizeInput($_POST['nisn']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password']; // Will be hashed if not empty
        $kelas_id = sanitizeInput($_POST['kelas_id']);
        
        // Validate required fields
        if (empty($nama) || empty($nisn) || empty($email) || empty($student_id) || empty($kelas_id)) {
            setFlashMessage('error', 'Semua field yang bertanda * wajib diisi.');
            header('Location: class_detail.php?id=' . $kelas_id);
            exit;
        }
        
        // Check if NISN or email already exists (excluding current student)
        $query = "SELECT id FROM pengguna WHERE (nisn = '$nisn' OR email = '$email') AND id != '$student_id'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            setFlashMessage('error', 'NISN atau Email sudah digunakan oleh siswa lain.');
            header('Location: class_detail.php?id=' . $kelas_id);
            exit;
        }
        
        // Prepare update query
        if (!empty($password)) {
            // Hash password if provided
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE pengguna SET 
                     nama = '$nama', 
                     nisn = '$nisn', 
                     email = '$email', 
                     password = '$hashed_password' 
                     WHERE id = '$student_id' AND kelas_id = '$kelas_id'";
        } else {
            // Don't update password if not provided
            $query = "UPDATE pengguna SET 
                     nama = '$nama', 
                     nisn = '$nisn', 
                     email = '$email' 
                     WHERE id = '$student_id' AND kelas_id = '$kelas_id'";
        }
        
        if (mysqli_query($conn, $query)) {
            setFlashMessage('success', 'Data siswa berhasil diperbarui.');
        } else {
            setFlashMessage('error', 'Gagal memperbarui data siswa: ' . mysqli_error($conn));
        }
        
        header('Location: class_detail.php?id=' . $kelas_id);
        exit;
    }
}

// If no valid action, redirect back to class list
header('Location: manage_classes.php');
exit;
?> 