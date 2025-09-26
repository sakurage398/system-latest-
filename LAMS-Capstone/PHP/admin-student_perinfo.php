<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

function handlePictureUpload() {
    $pictureData = null;
    
    if (isset($_FILES['picture_file']) && $_FILES['picture_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/students/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['picture_file']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid('student_') . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['picture_file']['tmp_name'], $uploadPath)) {
                $pictureData = $uploadPath;
            }
        }
    }
    
    return $pictureData;
}

function isIdUnique($conn, $id, $currentId = null, $currentTable = 'students') {
    if ($currentTable !== 'students') {
        $stmt = $conn->prepare("SELECT student_number FROM students WHERE student_number = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return ['unique' => false, 'message' => 'Student number already exists in students'];
        }
        $stmt->close();
    } else {
        $sql = "SELECT student_number FROM students WHERE student_number = ?";
        $params = [$id];
        if ($currentId !== null) {
            $sql .= " AND id != ?";
            $params[] = $currentId;
        }
        $stmt = $conn->prepare($sql);
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return ['unique' => false, 'message' => 'Student number already exists'];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT faculty_number FROM faculty WHERE faculty_number = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['unique' => false, 'message' => 'Student number already exists in faculty'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT staff_number FROM staff WHERE staff_number = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['unique' => false, 'message' => 'Student number already exists in staff'];
    }
    $stmt->close();

    return ['unique' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'add':
            $studentNumber = $_POST['student_number'];
            $name = $_POST['student_name'];
            $department = $_POST['department'];
            $program = $_POST['program'];
            $year = $_POST['year'];
            $block = $_POST['block'];
            $pinCode = isset($_POST['pin_code']) ? $_POST['pin_code'] : null;
            $registrationStatus = isset($_POST['registration_status']) ? $_POST['registration_status'] : 'Unregistered';
            
            $picture = handlePictureUpload();
            
            $check = isIdUnique($conn, $studentNumber);
            if (!$check['unique']) {
                $response = ['status' => 'error', 'message' => $check['message']];
                break;
            }
            
            if (empty($pinCode)) {
                $pinCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            
            $stmt = $conn->prepare("INSERT INTO students (student_number, name, department, program, year_level, block, picture, pin_code, registration_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $studentNumber, $name, $department, $program, $year, $block, $picture, $pinCode, $registrationStatus);
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Student added successfully', 'id' => $conn->insert_id];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to add student: ' . $stmt->error];
            }
            $stmt->close();
            break;
            
        case 'edit':
            $id = $_POST['id'];
            $studentNumber = $_POST['student_number'];
            $name = $_POST['student_name'];
            $department = $_POST['department'];
            $program = $_POST['program'];
            $year = $_POST['year'];
            $block = $_POST['block'];
            $pinCode = isset($_POST['pin_code']) ? $_POST['pin_code'] : null;
            $registrationStatus = isset($_POST['registration_status']) ? $_POST['registration_status'] : 'Unregistered';
            
            $check = isIdUnique($conn, $studentNumber, $id, 'students');
            if (!$check['unique']) {
                $response = ['status' => 'error', 'message' => $check['message']];
                break;
            }
            
            $stmt = $conn->prepare("SELECT picture, pin_code FROM students WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->bind_result($currentPicture, $currentPin);
            $stmt->fetch();
            $stmt->close();
            
            $picture = handlePictureUpload();
            if ($picture === null) {
                $picture = $currentPicture;
            }
            
            if (empty($pinCode)) {
                $pinCode = $currentPin;
            }
            
            $stmt = $conn->prepare("UPDATE students SET student_number = ?, name = ?, department = ?, program = ?, year_level = ?, block = ?, picture = ?, pin_code = ?, registration_status = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $studentNumber, $name, $department, $program, $year, $block, $picture, $pinCode, $registrationStatus, $id);
        
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Student updated successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to update student: ' . $stmt->error];
            }
            $stmt->close();
            break;
            
        case 'get_all':
            $result = $conn->query("SELECT * FROM students ORDER BY name");
            
            if ($result) {
                $students = [];
                while ($row = $result->fetch_assoc()) {
                    $students[] = $row;
                }
                $response = ['status' => 'success', 'data' => $students];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to fetch students: ' . $conn->error];
            }
            break;
            
        case 'get_departments':
            $result = $conn->query("SELECT DISTINCT department FROM students ORDER BY department");
            
            if ($result) {
                $departments = [];
                while ($row = $result->fetch_assoc()) {
                    $departments[] = $row['department'];
                }
                $response = ['status' => 'success', 'data' => $departments];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to fetch departments: ' . $conn->error];
            }
            break;
            
        case 'get_programs':
            $department = isset($_POST['department']) ? $_POST['department'] : '';
            
            if (!empty($department)) {
                $stmt = $conn->prepare("SELECT DISTINCT program FROM students WHERE department = ? ORDER BY program");
                $stmt->bind_param("s", $department);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query("SELECT DISTINCT program FROM students ORDER BY program");
            }
            
            if ($result) {
                $programs = [];
                while ($row = $result->fetch_assoc()) {
                    $programs[] = $row['program'];
                }
                $response = ['status' => 'success', 'data' => $programs];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to fetch programs: ' . $conn->error];
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
            break;

        case 'get_student':
            if (!isset($_POST['id'])) {
                $response = ['status' => 'error', 'message' => 'ID parameter missing'];
                break;
            }
            
            $id = $_POST['id'];
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $student = $result->fetch_assoc();
                $response = ['status' => 'success', 'data' => $student];
            } else {
                $response = ['status' => 'error', 'message' => 'Student not found'];
            }
            $stmt->close();
            break;
                
        case 'search':
            $searchTerm = isset($_POST['search_term']) ? '%' . $_POST['search_term'] . '%' : '';
            $department = isset($_POST['department']) ? $_POST['department'] : '';
            $program = isset($_POST['program']) ? $_POST['program'] : '';
            $year = isset($_POST['year']) ? $_POST['year'] : '';
            $block = isset($_POST['block']) ? $_POST['block'] : '';
            $registration_status = isset($_POST['registration_status']) ? $_POST['registration_status'] : '';
            
            $sql = "SELECT * FROM students WHERE 1=1";
            $types = "";
            $params = [];
            
            if (!empty($searchTerm)) {
                $sql .= " AND (student_number LIKE ? OR name LIKE ?)";
                $types .= "ss";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($department)) {
                $sql .= " AND department = ?";
                $types .= "s";
                $params[] = $department;
            }
            
            if (!empty($program)) {
                $sql .= " AND program = ?";
                $types .= "s";
                $params[] = $program;
            }
            
            if (!empty($year)) {
                $sql .= " AND year_level = ?";
                $types .= "s";
                $params[] = $year;
            }
            
            if (!empty($block)) {
                $sql .= " AND block = ?";
                $types .= "s";
                $params[] = $block;
            }

            if (!empty($registration_status)) {
                $sql .= " AND registration_status = ?";
                $types .= "s";
                $params[] = $registration_status;
            }
        
            $sql .= " ORDER BY name";
            
            $stmt = $conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                $students = [];
                while ($row = $result->fetch_assoc()) {
                    $students[] = $row;
                }
                $response = ['status' => 'success', 'data' => $students];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to search students: ' . $stmt->error];
            }
            
            $stmt->close();
            break;
    }
}

echo json_encode($response);
$conn->close();
?>