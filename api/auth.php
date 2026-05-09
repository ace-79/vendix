<?php
header('Content-Type: application/json');
session_start();
include '../config/db.php';
include '../config/auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'login';

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }

    requireCsrfToken(true);

    if (isset($_SESSION['user_id'])) {
        include_once '../config/helpers.php';
        logActivity(
            user_id: (int) $_SESSION['user_id'],
            action_type: 'LOGOUT',
            entity_type: 'auth',
            ip_address: getUserIP()
        );
    }

    clearAuthenticatedSession();
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
    exit;
}

if ($action === 'check') {
    $sessionState = getCurrentSessionState();

    if (!$sessionState['valid']) {
        clearAuthenticatedSession();
        echo json_encode([
            'status' => 'success',
            'logged_in' => false,
            'reason' => $sessionState['reason']
        ]);
        exit;
    }

    if (isset($_SESSION['user_id'])) {
        $heartbeatStmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
        if ($heartbeatStmt) {
            $userId = (int) $_SESSION['user_id'];
            $heartbeatStmt->bind_param("i", $userId);
            $heartbeatStmt->execute();
            $heartbeatStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'success', 'logged_in' => false, 'reason' => 'not_logged_in']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

$conn->close();
?>
