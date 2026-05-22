<?php
function getDatabaseConfigValue($key, $default)
{
    $value = getenv($key);
    return $value !== false && $value !== '' ? $value : $default;
}

// Database configuration
define('DB_HOST', getDatabaseConfigValue('VENDIX_DB_HOST', 'localhost'));
define('DB_USER', getDatabaseConfigValue('VENDIX_DB_USER', 'root'));
define('DB_PASS', getDatabaseConfigValue('VENDIX_DB_PASS', ''));
define('DB_NAME', getDatabaseConfigValue('VENDIX_DB_NAME', 'vendix'));

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset('utf8mb4');
?>