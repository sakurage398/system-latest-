<?php
session_start();
require 'db_connection.php';

header("Content-Type: application/json");

// Only proceed if this is a confirmed logout
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['confirm_logout']) || $data['confirm_logout'] !== true) {
    echo json_encode(["status" => "error", "message" => "Invalid logout request"]);
    exit();
}

$response = ["status" => "success"];

// Update logout time if login_log_id exists
if (isset($_SESSION['login_log_id'])) {
    try {
        $updateLogout = $conn->prepare("UPDATE login_logs SET logout_time = NOW() WHERE id = ?");
        $updateLogout->bind_param("i", $_SESSION['login_log_id']);
        if (!$updateLogout->execute()) {
            $response = [
                "status" => "error",
                "message" => "Failed to record logout time: " . $conn->error
            ];
        }
    } catch (Exception $e) {
        $response = [
            "status" => "error",
            "message" => "Error recording logout: " . $e->getMessage()
        ];
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Return response
echo json_encode($response);
?>