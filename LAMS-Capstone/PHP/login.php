<?php
session_start();
require 'db_connection.php';

header("Content-Type: application/json");

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Missing username or password."]);
    exit();
}

$stmt = $conn->prepare("SELECT id, name, role, password, pincode FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    if ($password === $user['password']) {
        // If pincode was submitted, verify it
        if (!empty($pincode)) {
            if ($pincode === $user['pincode']) {
                // Pincode is correct, proceed with login
                $insertLogin = $conn->prepare("INSERT INTO login_logs (user_id, login_time) VALUES (?, NOW())");
                $insertLogin->bind_param("i", $user['id']);
                $insertLogin->execute();
                $login_log_id = $conn->insert_id;
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $user['role'];
                $_SESSION['login_log_id'] = $login_log_id;
                $_SESSION['name'] = $user['name'];
                
                $redirect = "";
                if ($user['role'] === 'Admin') {
                    $redirect = "admin-dashboard.html";
                } else if ($user['role'] === 'Super Admin') {
                    $redirect = "superadmin-user.html";
                }
                
                echo json_encode([
                    "status" => "success", 
                    "redirect" => $redirect,
                    "login_log_id" => $login_log_id
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Incorrect pincode."]);
            }
        } else {
            // Password is correct but pincode not submitted yet
            echo json_encode(["status" => "pincode_required"]);
        }
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "Incorrect password."]);
        exit();
    }
}
$stmt->close();

echo json_encode(["status" => "error", "message" => "User account is invalid. This account does not exist."]);
?>