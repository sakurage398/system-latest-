<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

function handle_picture_upload($file_key) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $file = $_FILES[$file_key];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    $upload_dir = 'uploads/faculty_pictures/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('faculty_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    } else {
        throw new Exception('Failed to upload file.');
    }
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_faculty':
            add_faculty();
            break;
        case 'get_faculty':
            get_faculty();
            break;
        case 'get_single_faculty':
            get_single_faculty();
            break;
        case 'update_faculty':
            update_faculty();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
}

function add_faculty() {
    global $conn;
    
    if (isset($_POST['faculty_number']) && isset($_POST['name']) && isset($_POST['department']) && isset($_POST['program'])) {
        
        try {
            $faculty_number = sanitize_input($_POST['faculty_number']);
            $name = sanitize_input($_POST['name']);
            $department = sanitize_input($_POST['department']);
            $program = sanitize_input($_POST['program']);
            $pincode = isset($_POST['pincode']) && !empty($_POST['pincode']) ? sanitize_input($_POST['pincode']) : null;
            
            $picture_path = null;
            if (isset($_FILES['picture'])) {
                $picture_path = handle_picture_upload('picture');
            }
            
            if ($pincode !== null && !preg_match('/^[0-9]{6}$/', $pincode)) {
                echo json_encode(['status' => 'error', 'message' => 'Pincode must be exactly 6 digits']);
                return;
            }
            
            $exists = false;
            $message = '';
            
            $check_student = "SELECT student_number FROM students WHERE student_number = ?";
            $stmt_student = $conn->prepare($check_student);
            $stmt_student->bind_param("s", $faculty_number);
            $stmt_student->execute();
            $stmt_student->store_result();
            if ($stmt_student->num_rows > 0) {
                $exists = true;
                $message = 'Faculty number already exists as a student number';
            }
            $stmt_student->close();
            
            if (!$exists) {
                $check_faculty = "SELECT faculty_number FROM faculty WHERE faculty_number = ?";
                $stmt_faculty = $conn->prepare($check_faculty);
                $stmt_faculty->bind_param("s", $faculty_number);
                $stmt_faculty->execute();
                $stmt_faculty->store_result();
                if ($stmt_faculty->num_rows > 0) {
                    $exists = true;
                    $message = 'Faculty number already exists';
                }
                $stmt_faculty->close();
            }
            
            if (!$exists) {
                $check_staff = "SELECT staff_number FROM staff WHERE staff_number = ?";
                $stmt_staff = $conn->prepare($check_staff);
                $stmt_staff->bind_param("s", $faculty_number);
                $stmt_staff->execute();
                $stmt_staff->store_result();
                if ($stmt_staff->num_rows > 0) {
                    $exists = true;
                    $message = 'Faculty number already exists as a staff number';
                }
                $stmt_staff->close();
            }
            
            if ($exists) {
                if ($picture_path && file_exists($picture_path)) {
                    unlink($picture_path);
                }
                echo json_encode(['status' => 'error', 'message' => $message]);
                return;
            }
            
            $insert_query = "INSERT INTO faculty (faculty_number, name, department, program, picture, pincode, registration_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Unregistered', NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssss", $faculty_number, $name, $department, $program, $picture_path, $pincode);
            
            if ($stmt->execute()) {
                $faculty_id = $conn->insert_id;
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Faculty added successfully',
                    'faculty_id' => $faculty_id
                ]);
            } else {
                if ($picture_path && file_exists($picture_path)) {
                    unlink($picture_path);
                }
                echo json_encode(['status' => 'error', 'message' => 'Failed to add faculty: ' . $stmt->error]);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    }
}

function get_faculty() {
    global $conn;
    
    $query = "SELECT * FROM faculty WHERE 1=1";
    $params = [];
    $types = "";
    
    if (isset($_POST['department']) && !empty($_POST['department'])) {
        $department = sanitize_input($_POST['department']);
        $query .= " AND department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if (isset($_POST['program']) && !empty($_POST['program'])) {
        $program = sanitize_input($_POST['program']);
        $query .= " AND program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    if (isset($_POST['registration_status']) && !empty($_POST['registration_status'])) {
        $registration_status = sanitize_input($_POST['registration_status']);
        $query .= " AND registration_status = ?";
        $params[] = $registration_status;
        $types .= "s";
    }
    
    if (isset($_POST['pincode']) && !empty($_POST['pincode'])) {
        $pincode = sanitize_input($_POST['pincode']);
        $query .= " AND pincode = ?";
        $params[] = $pincode;
        $types .= "s";
    }
    
    if (isset($_POST['search']) && !empty($_POST['search'])) {
        $search = sanitize_input($_POST['search']);
        $query .= " AND (faculty_number LIKE ? OR name LIKE ? OR department LIKE ? OR program LIKE ? OR pincode LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sssss";
    }
    
    $query .= " ORDER BY name ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $faculty_list = [];
    while ($row = $result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $faculty_list]);
    $stmt->close();
}

function get_single_faculty() {
    global $conn;
    
    if (isset($_POST['faculty_id']) && !empty($_POST['faculty_id'])) {
        $faculty_id = sanitize_input($_POST['faculty_id']);
        
        $query = "SELECT * FROM faculty WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $faculty = $result->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $faculty]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Faculty not found']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Faculty ID is required']);
    }
}

function update_faculty() {
    global $conn;
    
    if (isset($_POST['faculty_id']) && !empty($_POST['faculty_id'])) {
        $faculty_id = sanitize_input($_POST['faculty_id']);
        
        $check_query = "SELECT id, picture FROM faculty WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $faculty_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Faculty not found']);
            $check_stmt->close();
            return;
        }
        
        $current_faculty = $result->fetch_assoc();
        $current_picture = $current_faculty['picture'];
        $check_stmt->close();
        
        $picture_path = $current_picture;
        try {
            if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
                if ($current_picture && file_exists($current_picture)) {
                    unlink($current_picture);
                }
                
                $picture_path = handle_picture_upload('picture');
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }
        
        $faculty_number = isset($_POST['faculty_number']) ? sanitize_input($_POST['faculty_number']) : null;
        $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : null;
        $department = isset($_POST['department']) ? sanitize_input($_POST['department']) : null;
        $program = isset($_POST['program']) ? sanitize_input($_POST['program']) : null;
        $pincode = isset($_POST['pincode']) ? sanitize_input($_POST['pincode']) : null;
        
        if ($pincode !== null && !empty($pincode) && !preg_match('/^[0-9]{6}$/', $pincode)) {
            if ($picture_path !== $current_picture && file_exists($picture_path)) {
                unlink($picture_path);
            }
            echo json_encode(['status' => 'error', 'message' => 'Pincode must be exactly 6 digits']);
            return;
        }
        
        if ($faculty_number !== null) {
            $exists = false;
            $message = '';
            
            $check_student = "SELECT student_number FROM students WHERE student_number = ?";
            $stmt_student = $conn->prepare($check_student);
            $stmt_student->bind_param("s", $faculty_number);
            $stmt_student->execute();
            $stmt_student->store_result();
            if ($stmt_student->num_rows > 0) {
                $exists = true;
                $message = 'Faculty number already exists as a student number';
            }
            $stmt_student->close();
            
            if (!$exists) {
                $check_faculty = "SELECT faculty_number FROM faculty WHERE faculty_number = ? AND id != ?";
                $stmt_faculty = $conn->prepare($check_faculty);
                $stmt_faculty->bind_param("si", $faculty_number, $faculty_id);
                $stmt_faculty->execute();
                $stmt_faculty->store_result();
                if ($stmt_faculty->num_rows > 0) {
                    $exists = true;
                    $message = 'Faculty number already exists';
                }
                $stmt_faculty->close();
            }
            
            if (!$exists) {
                $check_staff = "SELECT staff_number FROM staff WHERE staff_number = ?";
                $stmt_staff = $conn->prepare($check_staff);
                $stmt_staff->bind_param("s", $faculty_number);
                $stmt_staff->execute();
                $stmt_staff->store_result();
                if ($stmt_staff->num_rows > 0) {
                    $exists = true;
                    $message = 'Faculty number already exists as a staff number';
                }
                $stmt_staff->close();
            }
            
            if ($exists) {
                if ($picture_path !== $current_picture && file_exists($picture_path)) {
                    unlink($picture_path);
                }
                echo json_encode(['status' => 'error', 'message' => $message]);
                return;
            }
        }
        
        $query = "UPDATE faculty SET updated_at = NOW(), picture = ?";
        $params = [$picture_path];
        $types = "s";
        
        if ($faculty_number !== null) {
            $query .= ", faculty_number = ?";
            $params[] = $faculty_number;
            $types .= "s";
        }
        
        if ($name !== null) {
            $query .= ", name = ?";
            $params[] = $name;
            $types .= "s";
        }
        
        if ($department !== null) {
            $query .= ", department = ?";
            $params[] = $department;
            $types .= "s";
        }
        
        if ($program !== null) {
            $query .= ", program = ?";
            $params[] = $program;
            $types .= "s";
        }
        
        if ($pincode !== null) {
            $query .= ", pincode = ?";
            $params[] = $pincode;
            $types .= "s";
        }
        
        $query .= " WHERE id = ?";
        $params[] = $faculty_id;
        $types .= "i";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Faculty updated successfully',
                'picture' => $picture_path
            ]);
        } else {
            if ($picture_path !== $current_picture && file_exists($picture_path)) {
                unlink($picture_path);
            }
            echo json_encode(['status' => 'error', 'message' => 'Failed to update faculty: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Faculty ID is required']);
    }
}

$conn->close();
?>