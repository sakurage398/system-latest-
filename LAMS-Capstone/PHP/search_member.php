

<?php
// search_member.php
require_once 'db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the ID number and user type from the POST request
$idNumber = isset($_POST['idNumber']) ? $_POST['idNumber'] : '';
$userType = isset($_POST['userType']) ? $_POST['userType'] : '';

// Log received data for debugging
error_log("Received ID: " . $idNumber . ", Type: " . $userType);

// Validate input
if (empty($idNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID number is required'
    ]);
    exit;
}

// Initialize query parameters
$table = '';
$idField = '';

// Determine the table and field to query based on user type
switch ($userType) {
    case 'student':
        $table = 'students';
        $idField = 'student_number';
        break;
    case 'faculty':
        $table = 'faculty';
        $idField = 'faculty_number';
        break;
    case 'staff':
        $table = 'staff';
        $idField = 'staff_number';
        break;
    case 'unknown':
        // Try all tables if user type is unknown
        $userData = null;
        
        // Try students table first
        $stmt = $conn->prepare("SELECT student_number, name, department, program, year_level, block, picture, registration_status, 'student' as user_type FROM students WHERE student_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            $userType = 'student';
        } else {
            // Try faculty table
            $stmt->close();
            $stmt = $conn->prepare("SELECT faculty_number, name, department, program, picture, registration_status, 'faculty' as user_type FROM faculty WHERE faculty_number = ?");
            $stmt->bind_param("s", $idNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $userType = 'faculty';
            } else {
                // Try staff table
                $stmt->close();
                $stmt = $conn->prepare("SELECT staff_number, name, department, role, picture, registration_status, 'staff' as user_type FROM staff WHERE staff_number = ?");
                $stmt->bind_param("s", $idNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $userData = $result->fetch_assoc();
                    $userType = 'staff';
                }
            }
        }
        
        if ($userData) {
            echo json_encode([
                'success' => true,
                'userData' => $userData,
                'userType' => $userType
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No record found for that ID number'
            ]);
        }
        
        // Close connection and exit
        if (isset($stmt)) {
            $stmt->close();
        }
        $conn->close();
        exit;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type'
        ]);
        exit;
}

// If we get here, we have a specific table and field to query
try {
    if ($userType === 'student') {
        $query = "SELECT student_number, name, department, program, year_level, block, picture, registration_status FROM students WHERE student_number = ?";
    } elseif ($userType === 'faculty') {
        $query = "SELECT faculty_number, name, department, program, picture, registration_status FROM faculty WHERE faculty_number = ?";
    } elseif ($userType === 'staff') {
        $query = "SELECT staff_number, name, department, role, picture, registration_status FROM staff WHERE staff_number = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'userData' => $userData,
            'userType' => $userType
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No record found for that ID number'
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