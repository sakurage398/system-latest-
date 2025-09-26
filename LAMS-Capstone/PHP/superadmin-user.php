<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connection.php';

// Set content type header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        switch ($action) {
            case 'add':
                addUser($conn);
                break;
            case 'edit':
                editUser($conn);
                break;
            case 'delete':
                deleteUser($conn);
                break;
            case 'getUsers':
                getUsers($conn);
                break;
            case 'getUser':
                if (empty($_POST['id'])) {
                    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                    return;
                }
                
                $id = (int)$_POST['id'];
                $query = "SELECT id, name, role, username, created_at FROM users WHERE id = $id";
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    echo json_encode(['status' => 'success', 'user' => $user]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'User not found']);
                }
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
    }

    // Close connection
    $conn->close();
    exit();
}

/**
 * Custom function to hash password and limit to 8 characters
 * @param string $password The password to hash
 * @return string The 8-character hash
 */
function customHash($password) {
    // Use md5 and truncate to 8 chars
    return substr(md5($password), 0, 8);
}

/**
 * Add a new user to the database
 * @param mysqli $conn Database connection
 */
function addUser($conn) {
    // Validate input
    if (empty($_POST['name']) || empty($_POST['role']) || empty($_POST['username']) || empty($_POST['password']) || empty($_POST['pincode'])) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }

    $name = $conn->real_escape_string($_POST['name']);
    $role = 'Admin'; // Force Admin role only
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $pincode = $conn->real_escape_string($_POST['pincode']);
    
    // Validate pincode is 6 digits
    if (!preg_match('/^\d{6}$/', $pincode)) {
        echo json_encode(['status' => 'error', 'message' => 'Pincode must be 6 digits']);
        return;
    }
    
    // Check if username already exists
    $checkQuery = "SELECT id FROM users WHERE username = '$username'";
    $result = $conn->query($checkQuery);
    
    if ($result === false) {
        echo json_encode(['status' => 'error', 'message' => 'Database query error: ' . $conn->error]);
        return;
    }
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        return;
    }
    
    // Hash password with the custom 8-character hash
    $hashedPassword = customHash($password);
    
    // Insert new user
    $query = "INSERT INTO users (name, role, username, password, pincode) VALUES ('$name', '$role', '$username', '$hashedPassword', '$pincode')";
    
    if ($conn->query($query) === TRUE) {
        $userId = $conn->insert_id;
        echo json_encode([
            'status' => 'success', 
            'message' => 'User added successfully', 
            'user' => [
                'id' => $userId,
                'name' => $name,
                'role' => $role,
                'username' => $username
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding user: ' . $conn->error]);
    }
}

/**
 * Edit an existing user
 * @param mysqli $conn Database connection
 */
function editUser($conn) {
    // Validate input
    if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['username'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    $id = (int)$_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $role = 'Admin'; // Force Admin role only
    $username = $conn->real_escape_string($_POST['username']);
    $password = isset($_POST['password']) ? $conn->real_escape_string($_POST['password']) : '';
    $pincode = isset($_POST['pincode']) ? $conn->real_escape_string($_POST['pincode']) : '';
    
    // Validate pincode if provided
    if (!empty($pincode) && !preg_match('/^\d{6}$/', $pincode)) {
        echo json_encode(['status' => 'error', 'message' => 'Pincode must be 6 digits']);
        return;
    }
    
    // Check if username already exists and is not the current user
    $checkQuery = "SELECT id FROM users WHERE username = '$username' AND id != $id";
    $result = $conn->query($checkQuery);
    
    if ($result === false) {
        echo json_encode(['status' => 'error', 'message' => 'Database query error: ' . $conn->error]);
        return;
    }
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        return;
    }
    
    // Update user
    $queryParts = ["name = '$name'", "role = '$role'", "username = '$username'"];
    
    // Only update password if a new one was provided
    if (!empty($password)) {
        $hashedPassword = customHash($password);
        $queryParts[] = "password = '$hashedPassword'";
    }
    
    // Update pincode if provided
    if (!empty($pincode)) {
        $queryParts[] = "pincode = '$pincode'";
    }
    
    $updateClause = implode(", ", $queryParts);
    $query = "UPDATE users SET $updateClause WHERE id = $id";
    
    if ($conn->query($query) === TRUE) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'User updated successfully',
            'user' => [
                'id' => $id,
                'name' => $name,
                'role' => $role,
                'username' => $username
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating user: ' . $conn->error]);
    }
}

/**
 * Delete a user
 * @param mysqli $conn Database connection
 */
function deleteUser($conn) {
    // Validate input
    if (empty($_POST['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        return;
    }
    
    $id = (int)$_POST['id'];
    
    // Check if user exists
    $checkQuery = "SELECT id FROM users WHERE id = $id";
    $result = $conn->query($checkQuery);
    
    if ($result === false) {
        echo json_encode(['status' => 'error', 'message' => 'Database query error: ' . $conn->error]);
        return;
    }
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        return;
    }
    
    // Prevent deletion of the current user (if you're implementing session management)
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $id) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Delete user
    $query = "DELETE FROM users WHERE id = $id";
    
    if ($conn->query($query) === TRUE) {
        echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting user: ' . $conn->error]);
    }
}

/**
 * Get all users based on role filter
 * @param mysqli $conn Database connection
 */
function getUsers($conn) {
    $role = 'Admin'; // Only get Admin users
    $searchTerm = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : '';
    
    $whereClause = ["role = 'Admin'"]; // Only Admin users
    
    if (!empty($searchTerm)) {
        $whereClause[] = "(name LIKE '%$searchTerm%' OR username LIKE '%$searchTerm%')";
    }
    
    $whereStatement = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";
    
    $query = "SELECT id, name, role, username, created_at FROM users $whereStatement ORDER BY name";
    $result = $conn->query($query);
    
    if ($result) {
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'role' => $row['role'],
                'username' => $row['username'],
                'created_at' => $row['created_at']
            ];
        }
        echo json_encode(['status' => 'success', 'users' => $users]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching users: ' . $conn->error]);
    }
}
?>