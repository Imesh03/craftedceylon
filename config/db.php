<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'craftedceylon_db';

// Create database connection using MySQLi
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set character set to UTF-8 for Sinhala/English support
$conn->set_charset("utf8mb4");
?>