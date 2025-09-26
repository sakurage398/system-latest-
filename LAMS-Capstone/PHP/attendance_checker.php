<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'check_id':
            checkIdExists();
            break;
        case 'verify_pin':
            verifyPin();
            break;
        case 'get_attendance':
            getAttendanceHistory();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function checkIdExists() {
    global $conn;
    
    $idNumber = $_GET['id_number'] ?? '';
    if (empty($idNumber)) {
        throw new Exception('ID number is required');
    }

    // Check in students table
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_number = ?");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user['type'] = 'student';
        echo json_encode(['status' => 'success', 'user' => $user]);
        return;
    }

    // Check in faculty table
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_number = ?");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user['type'] = 'faculty';
        echo json_encode(['status' => 'success', 'user' => $user]);
        return;
    }

    // Check in staff table
    $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_number = ?");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user['type'] = 'staff';
        echo json_encode(['status' => 'success', 'user' => $user]);
        return;
    }

    throw new Exception('No record found for the entered ID number');
}

function verifyPin() {
    global $conn;
    
    $idNumber = $_GET['id_number'] ?? '';
    $pin = $_GET['pin'] ?? '';
    $userType = $_GET['user_type'] ?? '';
    
    if (empty($idNumber) || empty($pin) || empty($userType)) {
        throw new Exception('Missing required parameters');
    }

    $table = '';
    $idField = '';
    $pinField = '';
    
    switch ($userType) {
        case 'student':
            $table = 'students';
            $idField = 'student_number';
            $pinField = 'pin_code';
            break;
        case 'faculty':
            $table = 'faculty';
            $idField = 'faculty_number';
            $pinField = 'pincode';
            break;
        case 'staff':
            $table = 'staff';
            $idField = 'staff_number';
            $pinField = 'pincode';
            break;
        default:
            throw new Exception('Invalid user type');
    }
    
    $stmt = $conn->prepare("SELECT $pinField FROM $table WHERE $idField = ?");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $row = $result->fetch_assoc();
    $storedPin = $row[$pinField];
    
    if ($storedPin !== $pin) {
        throw new Exception('Incorrect PIN');
    }
    
    echo json_encode(['status' => 'success']);
}

function getAttendanceHistory() {
    global $conn;
    
    $idNumber = $_GET['id_number'] ?? '';
    $userType = $_GET['user_type'] ?? '';
    
    if (empty($idNumber) || empty($userType)) {
        throw new Exception('Missing required parameters');
    }

    $table = '';
    $idField = '';
    
    switch ($userType) {
        case 'student':
            $table = 'student_attendance';
            $idField = 'student_number';
            break;
        case 'faculty':
            $table = 'faculty_attendance';
            $idField = 'faculty_number';
            break;
        case 'staff':
            $table = 'staff_attendance';
            $idField = 'staff_number';
            break;
        default:
            throw new Exception('Invalid user type');
    }
    
    $stmt = $conn->prepare("SELECT time_in, time_out, log_date FROM $table WHERE $idField = ? ORDER BY log_date DESC");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = [
            'time_in' => $row['time_in'] ? date('h:i:s A', strtotime($row['time_in'])) : 'N/A',
            'time_out' => $row['time_out'] ? date('h:i:s A', strtotime($row['time_out'])) : 'N/A',
            'log_date' => date('F j, Y', strtotime($row['log_date']))
        ];
    }
    
    echo json_encode(['status' => 'success', 'attendance' => $attendance]);
}
?>