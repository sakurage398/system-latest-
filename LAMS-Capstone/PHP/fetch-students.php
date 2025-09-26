<?php

require_once 'db_connection.php';


header('Content-Type: application/json');

try {
    // Prepare the SQL query
    $query = "SELECT 
                id, 
                student_number, 
                name, 
                department, 
                program, 
                block, 
                year, 
                time_in, 
                time_out, 
                log_date 
              FROM student_attendance 
              ORDER BY log_date DESC, time_in DESC";

    // Execute the query
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    // Fetch all records as an associative array
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

    // Return data as JSON
    echo json_encode($data);

} catch (Exception $e) {
    // Return error as JSON
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>