<?php

require_once 'db_connection.php';


header('Content-Type: application/json');

$current_time = date('H:i');
$today = date('Y-m-d');

// Initialize response array
$response = [
    'status' => 'success',
    'data' => [
        'time_in' => 0,
        'time_out' => 0,
        'students' => 0,
        'faculty' => 0,
        'staff' => 0
    ]
];

// Check if it's after 6 PM (18:00)
$reset_stats = ($current_time >= '18:00');

try {
    if ($reset_stats) {
        // If it's after 6 PM, return all zeros
        echo json_encode($response);
        exit;
    }
    
    // Get total time ins (combined from all tables)
    $total_time_in_query = "
        SELECT COUNT(*) as count FROM (
            SELECT id FROM student_attendance WHERE time_in IS NOT NULL AND DATE(time_in) = '$today'
            UNION ALL
            SELECT id FROM faculty_attendance WHERE time_in IS NOT NULL AND DATE(time_in) = '$today'
            UNION ALL
            SELECT id FROM staff_attendance WHERE time_in IS NOT NULL AND DATE(time_in) = '$today'
        ) as combined";
    
    $result = $conn->query($total_time_in_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['data']['time_in'] = (int)$row['count'];
    }
    
    // Get total time outs (combined from all tables)
    $total_time_out_query = "
        SELECT COUNT(*) as count FROM (
            SELECT id FROM student_attendance WHERE time_out IS NOT NULL AND DATE(time_out) = '$today'
            UNION ALL
            SELECT id FROM faculty_attendance WHERE time_out IS NOT NULL AND DATE(time_out) = '$today'
            UNION ALL
            SELECT id FROM staff_attendance WHERE time_out IS NOT NULL AND DATE(time_out) = '$today'
        ) as combined";
    
    $result = $conn->query($total_time_out_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['data']['time_out'] = (int)$row['count'];
    }
    
    // Get total unique students who timed in or out today
    $students_query = "
        SELECT COUNT(DISTINCT student_number) as count 
        FROM student_attendance 
        WHERE (time_in IS NOT NULL AND DATE(time_in) = '$today') 
           OR (time_out IS NOT NULL AND DATE(time_out) = '$today')";
    
    $result = $conn->query($students_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['data']['students'] = (int)$row['count'];
    }
    
    // Get total unique faculty who timed in or out today
    $faculty_query = "
        SELECT COUNT(DISTINCT faculty_number) as count 
        FROM faculty_attendance 
        WHERE (time_in IS NOT NULL AND DATE(time_in) = '$today') 
           OR (time_out IS NOT NULL AND DATE(time_out) = '$today')";
    
    $result = $conn->query($faculty_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['data']['faculty'] = (int)$row['count'];
    }
    
    // Get total unique staff who timed in or out today
    $staff_query = "
        SELECT COUNT(DISTINCT staff_number) as count 
        FROM staff_attendance 
        WHERE (time_in IS NOT NULL AND DATE(time_in) = '$today') 
           OR (time_out IS NOT NULL AND DATE(time_out) = '$today')";
    
    $result = $conn->query($staff_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['data']['staff'] = (int)$row['count'];
    }
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ];
}

// Close database connection
$conn->close();

// Return JSON response
echo json_encode($response);
?>