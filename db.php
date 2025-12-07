<?php
// Database connection settings
$host = 'localhost';
$user = 'root';          // default XAMPP MySQL user
$pass = '';              // default XAMPP MySQL password is empty
$db   = 'doctor_appointment';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Optional: set charset
$conn->set_charset('utf8mb4');
?>
