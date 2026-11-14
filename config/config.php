
<?php

// Database connection settings
$host = "localhost";
$username = "sam";
$password = "Sam@local1994";
$database = "membership_management";

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8mb4");


?>
