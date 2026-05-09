<?php
/**
 * Authentication Helper Functions
 * Handles user login, session management, and role-based access control
 */
include_once dirname(__FILE__) . '/helpers.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken($token) {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function getRequestCsrfToken() {
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    if (isset($_POST['_csrf_token'])) {
        return trim((string) $_POST['_csrf_token']);
    }

    return '';
}

function requireCsrfToken($expectsJson = false) {
    if (isValidCsrfToken(getRequestCsrfToken())) {
        return;
    }

    http_response_code(419);

    if ($expectsJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing CSRF token',
            'reason' => 'invalid_csrf'
        ]);
        exit;
    }

    die('Invalid or expired form session. Please refresh the page and try again.');
}

function getLoginUrl() {
    $scriptName = $_SERVER['PHP_SELF'] ?? '';
    return strpos($scriptName, '/pages/') !== false ? '../login.php' : 'login.php';
}

function getCurrentSessionState() {
    global $conn;

    if (!isLoggedIn()) {
        return [
            'valid' => false,
            'reason' => 'not_logged_in'
        ];
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        return [
            'valid' => true,
            'reason' => null
        ];
    }

    $currentUserId = (int) $_SESSION['user_id'];
    $sessionStmt = $conn->prepare("SELECT status, force_logout FROM users WHERE id = ?");

    if (!$sessionStmt) {
        return [
            'valid' => true,
            'reason' => null
        ];
    }

    $sessionStmt->bind_param("i", $currentUserId);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    $sessionUser = $sessionResult ? $sessionResult->fetch_assoc() : null;
    $sessionStmt->close();

    if (!$sessionUser) {
        return [
            'valid' => false,
            'reason' => 'missing_user'
        ];
    }

    if ((int) ($sessionUser['force_logout'] ?? 0) === 1) {
        return [
            'valid' => false,
            'reason' => 'force_logout'
        ];
    }

    if (($sessionUser['status'] ?? '') === 'blocked') {
        return [
            'valid' => false,
            'reason' => 'blocked'
        ];
    }

    return [
        'valid' => true,
        'reason' => null
    ];
}

function clearAuthenticatedSession() {
    global $conn;

    if (isset($_SESSION['user_id']) && isset($conn) && ($conn instanceof mysqli)) {
        $userId = (int) $_SESSION['user_id'];
        $presenceStmt = $conn->prepare("UPDATE users SET last_seen = NULL WHERE id = ?");
        if ($presenceStmt) {
            $presenceStmt->bind_param("i", $userId);
            $presenceStmt->execute();
            $presenceStmt->close();
        }
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function enforceActiveSession() {
    $sessionState = getCurrentSessionState();

    if ($sessionState['valid']) {
        return;
    }

    clearAuthenticatedSession();
    header("Location: " . getLoginUrl());
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . getLoginUrl());
        exit;
    }

    enforceActiveSession();
}

function requireRole($requiredRole) {
    requireLogin();
    
    if ($_SESSION['role'] !== $requiredRole && $_SESSION['role'] !== 'admin') {
        header("HTTP/1.0 403 Forbidden");
        die('Access Denied: You do not have permission to access this page.');
    }
}

function requireAdmin() {
    requireRole('admin');
}

function requireApiLogin() {
    $sessionState = getCurrentSessionState();

    if ($sessionState['valid']) {
        return;
    }

    clearAuthenticatedSession();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Session is no longer valid',
        'reason' => $sessionState['reason']
    ]);
    exit;
}

function requireApiAdmin() {
    requireApiLogin();

    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied'
        ]);
        exit;
    }
}

function requireCashier() {
    requireLogin();
    if (!in_array($_SESSION['role'], ['cashier', 'admin', 'manager'])) {
        header("HTTP/1.0 403 Forbidden");
        die('Access Denied: Cashier access required.');
    }
}

function requireManager() {
    requireRole('manager');
}

function logout() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    clearAuthenticatedSession();
    header("Location: " . getLoginUrl());
    exit;
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

function canViewReports() {
    return hasPermission('view_reports');
}

function canManageProducts() {
    return hasPermission('view_products');
}

function canManageUsers() {
    return hasPermission('manage_users');
}

function canProcessSales() {
    return hasPermission('view_pos');
}
?>
