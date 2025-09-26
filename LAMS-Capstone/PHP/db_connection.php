<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";  
$password = "";
$database = "library_attendance_system";


$conn = new mysqli($servername, $username, $password, $database);


if ($conn->connect_error) {
   
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}


$conn->set_charset("utf8mb4");
?>