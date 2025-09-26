<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

// Query to get login history for admin users
$query = "SELECT 
            u.name,
            u.role,
            DATE_FORMAT(ll.login_time, '%Y-%m-%d') AS date,
            DATE_FORMAT(ll.login_time, '%H:%i:%s') AS login_time,
            DATE_FORMAT(ll.logout_time, '%H:%i:%s') AS logout_time
          FROM login_logs ll
          JOIN users u ON ll.user_id = u.id
          ORDER BY ll.login_time DESC
          LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'name' => $row['name'],
            'role' => $row['role'],
            'date' => $row['date'],
            'login_time' => $row['login_time'] ?: 'N/A',
            'logout_time' => $row['logout_time'] ?: 'N/A'
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'logs' => $logs
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch log history: ' . $conn->error
    ]);
}

$conn->close();
?>