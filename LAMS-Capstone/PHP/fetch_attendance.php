<?php
// fetch_attendance.php
require_once 'db_connection.php';

header('Content-Type: application/json');

try {
    $idNumber = $_POST['idNumber'] ?? '';
    $userType = $_POST['userType'] ?? '';
    
    if (empty($idNumber) || empty($userType)) {
        throw new Exception('ID number and user type are required');
    }
    
    $attendanceTable = $userType . '_attendance';
    $idField = $userType . '_number';
    
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE '$attendanceTable'");
    if ($tableExists->num_rows == 0) {
        echo json_encode(['success' => true, 'attendance' => []]);
        exit;
    }
    
    // Get last 5 attendance records
    $stmt = $conn->prepare("SELECT time_in, time_out, log_date 
                           FROM $attendanceTable 
                           WHERE $idField = ? 
                           ORDER BY log_date DESC, id DESC 
                           LIMIT 5");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        // Format time to be more readable
        $row['time_in'] = $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : null;
        $row['time_out'] = $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : null;
        $row['log_date'] = date('M j, Y', strtotime($row['log_date']));
        $attendance[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}