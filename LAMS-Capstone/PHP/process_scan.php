<?php
// process_scan.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

// Check if db_connection.php exists and include it
if (!file_exists('db_connection.php')) {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

require_once 'db_connection.php';

// Check connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection variable not set')]);
    exit;
}

try {
    // Get data from POST request
    $qrCode = isset($_POST['qrCode']) ? $_POST['qrCode'] : '';
    $scanDate = isset($_POST['scanDate']) ? $_POST['scanDate'] : date('Y-m-d');

    // Validate QR code (must be an 8-digit number)
    if (!preg_match('/^\d{8}$/', $qrCode)) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code format']);
        exit;
    }

    // Check if the QR code exists in any of the tables
    $memberType = '';
    $memberData = null;
    $memberNumber = '';
    $registrationStatus = '';

    // Check in students table
    $stmt = $conn->prepare("SELECT *, registration_status FROM students WHERE student_number = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed for students: " . $conn->error);
    }
    
    $stmt->bind_param("s", $qrCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $memberType = 'student';
        $memberData = $result->fetch_assoc();
        $memberNumber = $memberData['student_number'];
        $registrationStatus = $memberData['registration_status'];
    } else {
        // Check in faculty table
        $stmt = $conn->prepare("SELECT *, registration_status FROM faculty WHERE faculty_number = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for faculty: " . $conn->error);
        }
        
        $stmt->bind_param("s", $qrCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $memberType = 'faculty';
            $memberData = $result->fetch_assoc();
            $memberNumber = $memberData['faculty_number'];
            $registrationStatus = $memberData['registration_status'];
        } else {
            // Check in staff table
            $stmt = $conn->prepare("SELECT *, registration_status FROM staff WHERE staff_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for staff: " . $conn->error);
            }
            
            $stmt->bind_param("s", $qrCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $memberType = 'staff';
                $memberData = $result->fetch_assoc();
                $memberNumber = $memberData['staff_number'];
                $registrationStatus = $memberData['registration_status'];
            }
        }
    }

    // If member not found in any table
    if (empty($memberType)) {
        echo json_encode(['success' => false, 'message' => 'No record found for this QR code']);
        exit;
    }
    
    // Check registration status
    if ($registrationStatus !== 'Registered') {
        echo json_encode([
            'success' => false, 
            'message' => 'This QR code must be registered first. Please contact an administrator.',
            'memberType' => $memberType
        ]);
        exit;
    }

    // Handle attendance based on member type
    $attendanceTable = $memberType . '_attendance';
    $numberField = $memberType . '_number';

    // Check if the attendance table exists, create it if not
    $tableExists = $conn->query("SHOW TABLES LIKE '$attendanceTable'");
    if ($tableExists->num_rows == 0) {
        // Create attendance table if it doesn't exist
        createAttendanceTable($conn, $attendanceTable, $numberField);
    }

    // Check if already has attendance for today
    $stmt = $conn->prepare("SELECT * FROM $attendanceTable WHERE $numberField = ? AND log_date = ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        throw new Exception("Prepare failed for attendance check: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $memberNumber, $scanDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $timeType = 'In'; // Default to time in

    if ($result->num_rows > 0) {
        $attendance = $result->fetch_assoc();
        
        // If time_out is NULL, this is a time out
        if ($attendance['time_out'] === null) {
            $timeType = 'Out';
            
            // Update the record with time_out - use TIME() to store only the time component
            $stmt = $conn->prepare("UPDATE $attendanceTable SET time_out = TIME(NOW()) WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for time out update: " . $conn->error);
            }
            
            $stmt->bind_param("i", $attendance['id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record time out: " . $stmt->error);
            }
        } else {
            // Already has complete attendance for today, create a new entry
            $memberName = $memberData['name'] ?? '';
            $memberDept = $memberData['department'] ?? '';
            
            if ($memberType == 'student') {
                $memberProgram = $memberData['program'] ?? '';
                $memberBlock = $memberData['block'] ?? '';
                $memberYear = $memberData['year_level'] ?? '';
                
                $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, program, block, year, time_in, log_date) VALUES (?, ?, ?, ?, ?, ?, TIME(NOW()), ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed for new student entry: " . $conn->error);
                }
                
                $stmt->bind_param("sssssss", $memberNumber, $memberName, $memberDept, $memberProgram, $memberBlock, $memberYear, $scanDate);
            } elseif ($memberType == 'faculty') {
                $memberProgram = $memberData['program'] ?? '';
                
                $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, program, time_in, log_date) VALUES (?, ?, ?, ?, TIME(NOW()), ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed for new faculty entry: " . $conn->error);
                }
                
                $stmt->bind_param("sssss", $memberNumber, $memberName, $memberDept, $memberProgram, $scanDate);
            } else { // staff
                $memberRole = $memberData['role'] ?? '';
                
                $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, role, time_in, log_date) VALUES (?, ?, ?, ?, TIME(NOW()), ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed for new staff entry: " . $conn->error);
                }
                
                $stmt->bind_param("sssss", $memberNumber, $memberName, $memberDept, $memberRole, $scanDate);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create new attendance record: " . $stmt->error);
            }
        }
    } else {
        // No attendance yet today, create new record
        $memberName = $memberData['name'] ?? '';
        $memberDept = $memberData['department'] ?? '';
        
        if ($memberType == 'student') {
            $memberProgram = $memberData['program'] ?? '';
            $memberBlock = $memberData['block'] ?? '';
            $memberYear = $memberData['year_level'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, program, block, year, time_in, log_date) VALUES (?, ?, ?, ?, ?, ?, TIME(NOW()), ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for new student entry: " . $conn->error);
            }
            
            $stmt->bind_param("sssssss", $memberNumber, $memberName, $memberDept, $memberProgram, $memberBlock, $memberYear, $scanDate);
        } elseif ($memberType == 'faculty') {
            $memberProgram = $memberData['program'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, program, time_in, log_date) VALUES (?, ?, ?, ?, TIME(NOW()), ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for new faculty entry: " . $conn->error);
            }
            
            $stmt->bind_param("sssss", $memberNumber, $memberName, $memberDept, $memberProgram, $scanDate);
        } else { // staff
            $memberRole = $memberData['role'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO $attendanceTable ($numberField, name, department, role, time_in, log_date) VALUES (?, ?, ?, ?, TIME(NOW()), ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for new staff entry: " . $conn->error);
            }
            
            $stmt->bind_param("sssss", $memberNumber, $memberName, $memberDept, $memberRole, $scanDate);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create attendance record: " . $stmt->error);
        }
    }

    // Clean the output buffer
    ob_end_clean();
    
    // Return success response with member data
    echo json_encode([
        'success' => true, 
        'memberType' => $memberType,
        'data' => $memberData,
        'timeType' => $timeType
    ]);

} catch (Exception $e) {
    // Clean the output buffer
    ob_end_clean();
    
    // Return error as JSON
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
}

function createAttendanceTable($conn, $tableName, $idField) {
    // Create basic attendance table structure based on member type
    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `$idField` varchar(20) NOT NULL,
        `name` varchar(100) NOT NULL,
        `department` varchar(100) NOT NULL,
        `time_in` time DEFAULT NULL,
        `time_out` time DEFAULT NULL,
        `log_date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),";
        
    // Add specific fields based on member type
    if (strpos($tableName, 'student') === 0) {
        $sql .= "
        `program` varchar(100) DEFAULT NULL,
        `block` varchar(15) DEFAULT NULL,
        `year` varchar(15) DEFAULT NULL,";
    } elseif (strpos($tableName, 'faculty') === 0) {
        $sql .= "
        `program` varchar(100) DEFAULT NULL,";
    } elseif (strpos($tableName, 'staff') === 0) {
        $sql .= "
        `role` varchar(100) DEFAULT NULL,";
    }
    
    // Finish the table creation SQL
    $sql .= "
        PRIMARY KEY (`id`),
        KEY `$idField` (`$idField`),
        KEY `log_date` (`log_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    if (!$conn->query($sql)) {
        throw new Exception("Failed to create attendance table: " . $conn->error);
    }
}