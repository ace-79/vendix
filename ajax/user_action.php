<?php
session_start();

include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrfToken(true);

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$currentUserId = (int) $_SESSION['user_id'];

if ($userId <= 0 || $action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action or user']);
    exit;
}

if (in_array($action, ['block', 'force_logout'], true) && $userId === $currentUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot perform this action on your own account']);
    exit;
}

$validActions = [
    'block' => [
        'sql' => "UPDATE users SET status = 'blocked', force_logout = 1, last_seen = NULL WHERE id = ?",
        'message' => 'User blocked successfully',
        'description' => 'Blocked user account'
    ],
    'unblock' => [
        'sql' => "UPDATE users SET status = 'active' WHERE id = ?",
        'message' => 'User unblocked successfully',
        'description' => 'Unblocked user account'
    ],
    'force_logout' => [
        'sql' => "UPDATE users SET force_logout = 1, last_seen = NULL WHERE id = ?",
        'message' => 'User has been marked offline and will be logged out',
        'description' => 'Forced user logout'
    ]
];

if (!isset($validActions[$action])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$userStmt = $conn->prepare("SELECT id, username, role, status, last_seen, force_logout FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    $userStmt->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user = $userResult->fetch_assoc();
$userStmt->close();

$stmt = $conn->prepare($validActions[$action]['sql']);
$stmt->bind_param("i", $userId);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$refreshStmt = $conn->prepare("SELECT id, username, role, status, last_seen, force_logout FROM users WHERE id = ?");
$refreshStmt->bind_param("i", $userId);
$refreshStmt->execute();
$updatedResult = $refreshStmt->get_result();
$updatedUser = $updatedResult->fetch_assoc();
$refreshStmt->close();

$isOnline = (int) ($updatedUser['force_logout'] ?? 0) !== 1
    && !empty($updatedUser['last_seen'])
    && strtotime($updatedUser['last_seen']) >= (time() - 300);

logActivity(
    user_id: $currentUserId,
    action_type: 'UPDATE',
    entity_type: 'user',
    entity_id: $userId,
    old_value: [
        'status' => $user['status'],
        'force_logout' => (int) $user['force_logout']
    ],
    new_value: [
        'status' => $updatedUser['status'],
        'force_logout' => (int) $updatedUser['force_logout']
    ],
    description: $validActions[$action]['description'] . ': ' . $updatedUser['username'],
    ip_address: getUserIP()
);

echo json_encode([
    'success' => true,
    'message' => $validActions[$action]['message'],
    'user' => [
        'id' => (int) $updatedUser['id'],
        'status' => $updatedUser['status'],
        'last_seen' => $updatedUser['last_seen'],
        'is_online' => $isOnline,
        'force_logout' => (int) $updatedUser['force_logout']
    ]
]);

$conn->close();
?>
