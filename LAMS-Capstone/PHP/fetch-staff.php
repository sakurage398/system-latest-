<?php
// Include database connection
require_once 'db_connection.php';

// Set headers to prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response array
$response = array(
    'status' => 'error',
    'message' => '',
    'data' => array()
);

try {
    // Get filter parameters from request
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $role = isset($_GET['role']) ? $_GET['role'] : '';
    $logDate = isset($_GET['logDate']) ? $_GET['logDate'] : '';
    
    // Base query
    $query = "SELECT * FROM staff_attendance WHERE 1=1";
    $params = array();
    $types = "";
    
    // Add filters if provided
    if (!empty($department)) {
        $query .= " AND department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if (!empty($role)) {
        $query .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    if (!empty($logDate)) {
        $query .= " AND log_date = ?";
        $params[] = $logDate;
        $types .= "s";
    }
    
    // Add order by clause
    $query .= " ORDER BY log_date DESC, time_in DESC";
    
    // Prepare and execute statement
    $stmt = $conn->prepare($query);
    
    // Bind parameters if any exist
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if data was found
    if ($result->num_rows > 0) {
        $staffData = array();
        
        while ($row = $result->fetch_assoc()) {
            $staffData[] = array(
                'staff_number' => $row['staff_number'],
                'name' => $row['name'],
                'department' => $row['department'],
                'role' => $row['role'],
                'time_in' => $row['time_in'],
                'time_out' => $row['time_out'],
                'log_date' => $row['log_date']
            );
        }
        
        $response['status'] = 'success';
        $response['data'] = $staffData;
    } else {
        $response['status'] = 'success';
        $response['message'] = 'No staff attendance records found';
        $response['data'] = array();
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

// Close database connection
$conn->close();

// Return JSON response
echo json_encode($response);
?>