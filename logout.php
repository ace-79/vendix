<?php
session_start();

include 'config/db.php';
include 'config/auth.php';
include_once 'config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: login.php');
    exit;
}

requireCsrfToken();

if (isset($_SESSION['user_id'])) {
    logActivity(
        user_id: (int) $_SESSION['user_id'],
        action_type: 'LOGOUT',
        entity_type: 'auth',
        ip_address: getUserIP()
    );
}

clearAuthenticatedSession();
header("Location: login.php");
exit;
?>
