<?php
// Include database connection
require_once 'db_connection.php';

// Enable CORS if needed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Initialize query parameters
    $params = [];
    $where_clauses = [];

    // Base SQL query
    $sql = "SELECT id, faculty_number, name, department, program, time_in, time_out, log_date FROM faculty_attendance";
    
    // Add filters if provided
    if (!empty($_GET['department'])) {
        $where_clauses[] = "department = ?";
        $params[] = $_GET['department'];
    }
    
    if (!empty($_GET['program'])) {
        $where_clauses[] = "program = ?";
        $params[] = $_GET['program'];
    }
    
    if (!empty($_GET['logDate'])) {
        $where_clauses[] = "log_date = ?";
        $params[] = $_GET['logDate'];
    }
    
    // Add WHERE clause if any filters were applied
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Order by log date (newest first) and time in
    $sql .= " ORDER BY log_date DESC, time_in DESC";
    
    // Prepare statement
    $stmt = $conn->prepare($sql);
    
    // Bind parameters if any
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assuming all params are strings
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute query
    $stmt->execute();
    
    // Get results
    $result = $stmt->get_result();
    
    // Fetch data
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for JavaScript
        if ($row['time_in']) {
            $row['time_in'] = date('Y-m-d H:i:s', strtotime($row['time_in']));
        }
        
        if ($row['time_out']) {
            $row['time_out'] = date('Y-m-d H:i:s', strtotime($row['time_out']));
        }
        
        if ($row['log_date']) {
            $row['log_date'] = date('Y-m-d', strtotime($row['log_date']));
        }
        
        $data[] = $row;
    }
    
    // Close statement
    $stmt->close();
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => count($data)
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?>