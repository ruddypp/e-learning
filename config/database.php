<?php
/**
 * Database Configuration File
 * This file contains database connection settings
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'elearning');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}

// Set character set
mysqli_set_charset($conn, "utf8");