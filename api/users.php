<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';
include '../config/passwords.php';

requireApiAdmin(); // Only admins can manage users

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all users or single user
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT id, username, role FROM users ORDER BY username");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $users]);
        $stmt->close();
    }
} 
elseif ($method === 'POST') {
    requireCsrfToken(true);

    // Create new user
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password']) || !isset($input['role'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: username, password, role']);
        exit;
    }
    
    $username = trim($input['username']);
    $password = hashPasswordForStorage($input['password']);
    $role = $input['role'];
    
    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $check = $checkStmt->get_result();
    if ($check->num_rows > 0) {
        $checkStmt->close();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        exit;
    }
    $checkStmt->close();
    
    $valid_roles = ['admin', 'manager', 'inventory', 'cashier'];
    if (!in_array($role, $valid_roles)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid role']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Log user creation
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'user',
            entity_id: $user_id,
            new_value: [
                'username' => $username,
                'role' => $role
            ],
            description: "Created user: $username with role: $role"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'User created', 'id' => $user_id]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    $stmt->close();
}
elseif ($method === 'PUT') {
    requireCsrfToken(true);

    // Update user
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
        exit;
    }
    
    $id = intval($input['id']);
    
    // Prevent deleting yourself
    if ($id === $_SESSION['user_id']) {
        // Only allow role change if updating yourself, not username
        if (isset($input['username']) && $input['username'] !== $_SESSION['username']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Cannot change your own username']);
            exit;
        }
    }
    
    // Fetch old user data before update
    $oldStmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $oldStmt->bind_param("i", $id);
    $oldStmt->execute();
    $old_result = $oldStmt->get_result();
    if ($old_result->num_rows === 0) {
        $oldStmt->close();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    $old_user = $old_result->fetch_assoc();
    $oldStmt->close();
    
    $updates = [];
    $types = '';
    $params = [];
    $new_values = [];
    
    if (isset($input['username'])) {
        $username = trim($input['username']);
        // Check if new username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $id);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
        if ($check->num_rows > 0) {
            $checkStmt->close();
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
            exit;
        }
        $checkStmt->close();
        $updates[] = "username = ?";
        $types .= 's';
        $params[] = $username;
        $new_values['username'] = $username;
    }
    
    if (isset($input['password']) && !empty($input['password'])) {
        $password = hashPasswordForStorage($input['password']);
        $updates[] = "password = ?";
        $types .= 's';
        $params[] = $password;
        $new_values['password_changed'] = true;
    }
    
    if (isset($input['role'])) {
        $role = $input['role'];
        $valid_roles = ['admin', 'manager', 'inventory', 'cashier'];
        if (!in_array($role, $valid_roles)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid role']);
            exit;
        }
        $updates[] = "role = ?";
        $types .= 's';
        $params[] = $role;
        $new_values['role'] = $role;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
        exit;
    }
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $types .= 'i';
    $params[] = $id;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Log user update
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'user',
            entity_id: $id,
            old_value: $old_user,
            new_value: $new_values,
            description: "Updated user ID: $id"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'User updated']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    $stmt->close();
}
elseif ($method === 'DELETE') {
    requireCsrfToken(true);

    // Delete user
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
        exit;
    }
    
    $id = intval($_GET['id']);
    
    // Prevent deleting yourself
    if ($id === $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete yourself']);
        exit;
    }
    
    // Prevent deleting the last admin
    $adminsStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $adminRole = 'admin';
    $adminsStmt->bind_param("s", $adminRole);
    $adminsStmt->execute();
    $admins = $adminsStmt->get_result();
    $admin_count = $admins->fetch_assoc()['count'];
    $adminsStmt->close();
    
    $userStmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $userStmt->bind_param("i", $id);
    $userStmt->execute();
    $user = $userStmt->get_result();
    if ($user->num_rows === 0) {
        $userStmt->close();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    $deleted_user = $user->fetch_assoc();
    $userStmt->close();
    
    if ($deleted_user['role'] === 'admin' && $admin_count <= 1) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete the last admin user']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        // Log user deletion
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'DELETE',
            entity_type: 'user',
            entity_id: $id,
            old_value: $deleted_user,
            description: "Deleted user: {$deleted_user['username']}"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'User deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    $stmt->close();
}
else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>
