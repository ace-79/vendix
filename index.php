<?php
session_start();
@include 'config/db.php';
@include 'config/auth.php';

if (!function_exists('requireLogin')) {
    die('Error: Auth functions not loaded. Check config/auth.php');
}

requireLogin();

// Keep a single dashboard entry point and let the page module own the UI.
header('Location: pages/dashboard.php');
exit;
?>
