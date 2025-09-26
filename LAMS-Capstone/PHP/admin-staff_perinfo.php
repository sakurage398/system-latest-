<?php
// Include database connection
require_once 'db_connection.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Function to sanitize input data
function sanitize_input($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function validate_staff_data($staff_number, $name, $department, $role, $pincode = null) {
    $errors = [];
    
    if (empty($staff_number)) {
        $errors[] = "Staff number is required";
    }
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    // Validate pincode if provided
    if (!empty($pincode) && (!preg_match('/^\d{6}$/', $pincode))) {
        $errors[] = "Pincode must be exactly 6 digits";
    }
    
    return $errors;
}

function identifier_exists_in_other_tables($conn, $identifier) {
    // Check in students table
    $student_sql = "SELECT id FROM students WHERE student_number = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("s", $identifier);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    if ($student_result->num_rows > 0) {
        $student_stmt->close();
        return "students";
    }
    $student_stmt->close();
    
    // Check in faculty table
    $faculty_sql = "SELECT id FROM faculty WHERE faculty_number = ?";
    $faculty_stmt = $conn->prepare($faculty_sql);
    $faculty_stmt->bind_param("s", $identifier);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();
    if ($faculty_result->num_rows > 0) {
        $faculty_stmt->close();
        return "faculty";
    }
    $faculty_stmt->close();
    
    return false;
}

// Handle GET request to retrieve all staff or specific staff by ID
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        // Get specific staff by ID
        $id = sanitize_input($_GET['id']);
        $sql = "SELECT * FROM staff WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $staff = $result->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $staff]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Staff not found']);
        }
        $stmt->close();
    } else {
        // Get all staff with optional filtering
        $where_conditions = [];
        $params = [];
        $types = "";
        
        if (isset($_GET['department']) && !empty($_GET['department'])) {
            $department = sanitize_input($_GET['department']);
            $where_conditions[] = "department = ?";
            $params[] = $department;
            $types .= "s";
        }
        
        if (isset($_GET['role']) && !empty($_GET['role'])) {
            $role = sanitize_input($_GET['role']);
            $where_conditions[] = "role = ?";
            $params[] = $role;
            $types .= "s";
        }
        
        if (isset($_GET['registration_status']) && !empty($_GET['registration_status'])) {
            $registration_status = sanitize_input($_GET['registration_status']);
            $where_conditions[] = "registration_status = ?";
            $params[] = $registration_status;
            $types .= "s";
        }
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = sanitize_input($_GET['search']);
            $where_conditions[] = "(staff_number LIKE ? OR name LIKE ? OR department LIKE ? OR role LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ssss";
        }
        
        $sql = "SELECT * FROM staff";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY name ASC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $staff_list = [];
        while ($row = $result->fetch_assoc()) {
            $staff_list[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'data' => $staff_list]);
        $stmt->close();
    }
}

// Handle POST request to add new staff
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input if content type is application/json
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
    } else {
        $data = $_POST;
    }
    
    // Extract and sanitize input data
    $staff_number = isset($data['staff_number']) ? sanitize_input($data['staff_number']) : '';
    $name = isset($data['name']) ? sanitize_input($data['name']) : '';
    $department = isset($data['department']) ? sanitize_input($data['department']) : '';
    $role = isset($data['role']) ? sanitize_input($data['role']) : '';
    $registration_status = isset($data['registration_status']) ? sanitize_input($data['registration_status']) : 'Unregistered';
    $picture = isset($data['picture']) ? $data['picture'] : null;
    $pincode = isset($data['pincode']) ? sanitize_input($data['pincode']) : null;
    
    // Validate input data
   $validation_errors = validate_staff_data($staff_number, $name, $department, $role, $pincode);
    
    if (!empty($validation_errors)) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validation_errors]);
        exit();
    }
    
    // Check if identifier exists in other tables
    $existing_table = identifier_exists_in_other_tables($conn, $staff_number);
    if ($existing_table) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Staff number already exists in the $existing_table table"
        ]);
        exit();
    }
    
    // Check if staff number already exists in staff table
    $check_sql = "SELECT id FROM staff WHERE staff_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $staff_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Staff number already exists in staff records']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    
   $sql = "INSERT INTO staff (staff_number, name, department, role, picture, pincode, registration_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $staff_number, $name, $department, $role, $picture, $pincode, $registration_status);
        
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        
        // Retrieve the newly created staff record
        $sql = "SELECT * FROM staff WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $new_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $new_staff = $result->fetch_assoc();
        
        echo json_encode(['status' => 'success', 'message' => 'Staff added successfully', 'data' => $new_staff]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add staff: ' . $stmt->error]);
    }
    $stmt->close();
}

// Handle PUT or PATCH request to update staff
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    // Decode JSON input
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Staff ID is required']);
        exit();
    }
    
    $id = sanitize_input($data['id']);
    
    // Check if staff exists
    $check_sql = "SELECT * FROM staff WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Staff not found']);
        $check_stmt->close();
        exit();
    }
    
    $current_staff = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Extract and sanitize input data
    $staff_number = isset($data['staff_number']) ? sanitize_input($data['staff_number']) : $current_staff['staff_number'];
    $name = isset($data['name']) ? sanitize_input($data['name']) : $current_staff['name'];
    $department = isset($data['department']) ? sanitize_input($data['department']) : $current_staff['department'];
    $role = isset($data['role']) ? sanitize_input($data['role']) : $current_staff['role'];
    $registration_status = isset($data['registration_status']) ? sanitize_input($data['registration_status']) : $current_staff['registration_status'];
    $picture = isset($data['picture']) ? $data['picture'] : $current_staff['picture'];
    $pincode = isset($data['pincode']) ? sanitize_input($data['pincode']) : $current_staff['pincode'];

    // Validate input data
    $validation_errors = validate_staff_data($staff_number, $name, $department, $role, $pincode);
    
    if (!empty($validation_errors)) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validation_errors]);
        exit();
    }
    
    // Check if staff number is being changed
    if ($staff_number !== $current_staff['staff_number']) {
        // Check if new identifier exists in other tables
        $existing_table = identifier_exists_in_other_tables($conn, $staff_number);
        if ($existing_table) {
            echo json_encode([
                'status' => 'error', 
                'message' => "Staff number already exists in the $existing_table table"
            ]);
            exit();
        }
        
        // Check if staff number exists for another staff
        $check_sql = "SELECT id FROM staff WHERE staff_number = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $staff_number, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Staff number already exists in staff records']);
            $check_stmt->close();
            exit();
        }
        $check_stmt->close();
    }
    
    $sql = "UPDATE staff SET staff_number = ?, name = ?, department = ?, role = ?, picture = ?, pincode = ?, registration_status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $staff_number, $name, $department, $role, $picture, $pincode, $registration_status, $id);
    
    if ($stmt->execute()) {
        // Retrieve the updated staff record
        $sql = "SELECT * FROM staff WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $updated_staff = $result->fetch_assoc();
        
        echo json_encode(['status' => 'success', 'message' => 'Staff updated successfully', 'data' => $updated_staff]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update staff: ' . $stmt->error]);
    }
    $stmt->close();
}

// DELETE functionality has been completely removed for security reasons

// Handle unsupported methods
else {
    echo json_encode(['status' => 'error', 'message' => 'Unsupported request method']);
}

// Close database connection
$conn->close();
?>