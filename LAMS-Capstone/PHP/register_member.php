<?php
// register_member.php
require_once 'db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the ID number, user type, and QR code from the POST request
$idNumber = isset($_POST['idNumber']) ? $_POST['idNumber'] : '';
$userType = isset($_POST['userType']) ? $_POST['userType'] : '';
$qrCode = isset($_POST['qrCode']) ? $_POST['qrCode'] : '';
$expirationDate = isset($_POST['expirationDate']) ? $_POST['expirationDate'] : '';

// Validate input
if (empty($idNumber) || empty($userType) || empty($qrCode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Verify that QR code matches ID number
if ($qrCode !== $idNumber) {
    echo json_encode([
        'success' => false,
        'message' => 'QR code does not match the ID number'
    ]);
    exit;
}

// Calculate expiration date if not provided (5-6 months from now)
if (empty($expirationDate)) {
    $monthsToAdd = 5 + rand(0, 1); // Randomly choose 5 or 6 months
    $expirationDate = date('Y-m-d', strtotime("+$monthsToAdd months"));
}

// Process registration based on user type
try {
    if ($userType === 'student') {
        // Check if student exists and is not already registered
        $stmt = $conn->prepare("SELECT registration_status FROM students WHERE student_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found'
            ]);
            exit;
        }
        
        $student = $result->fetch_assoc();
        if ($student['registration_status'] === 'Registered') {
            echo json_encode([
                'success' => false,
                'message' => 'Student already registered'
            ]);
            exit;
        }
        
        // Update registration status and expiration date
        $stmt = $conn->prepare("UPDATE students SET registration_status = 'Registered', expiration_date = ? WHERE student_number = ?");
        $stmt->bind_param("ss", $expirationDate, $idNumber);
        $success = $stmt->execute();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Student registration successful',
                'expirationDate' => $expirationDate
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update registration status'
            ]);
        }
        
    } elseif ($userType === 'faculty') {
        // Check if faculty exists and is not already registered
        $stmt = $conn->prepare("SELECT registration_status FROM faculty WHERE faculty_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Faculty member not found'
            ]);
            exit;
        }
        
        $faculty = $result->fetch_assoc();
        if ($faculty['registration_status'] === 'Registered') {
            echo json_encode([
                'success' => false,
                'message' => 'Faculty member already registered'
            ]);
            exit;
        }
        
        // Update registration status and expiration date
        $stmt = $conn->prepare("UPDATE faculty SET registration_status = 'Registered', expiration_date = ? WHERE faculty_number = ?");
        $stmt->bind_param("ss", $expirationDate, $idNumber);
        $success = $stmt->execute();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Faculty registration successful',
                'expirationDate' => $expirationDate
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update registration status'
            ]);
        }
        
    } elseif ($userType === 'staff') {
        // Check if staff exists and is not already registered
        $stmt = $conn->prepare("SELECT registration_status FROM staff WHERE staff_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Staff member not found'
            ]);
            exit;
        }
        
        $staff = $result->fetch_assoc();
        if ($staff['registration_status'] === 'Registered') {
            echo json_encode([
                'success' => false,
                'message' => 'Staff member already registered'
            ]);
            exit;
        }
        
        // Update registration status and expiration date
        $stmt = $conn->prepare("UPDATE staff SET registration_status = 'Registered', expiration_date = ? WHERE staff_number = ?");
        $stmt->bind_param("ss", $expirationDate, $idNumber);
        $success = $stmt->execute();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Staff registration successful',
                'expirationDate' => $expirationDate
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update registration status'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close the database connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>