<?php
/**
 * Helper Functions
 * Utility functions for the system
 */

/**
 * Format database ID with prefix for display
 */
function formatId($id, $type = 'CUS') {
    $prefixes = [
        'customer' => 'CUS',
        'product' => 'PRD',
        'sale' => 'SAL',
        'user' => 'UID',
        'payment' => 'PAY',
        'supplier' => 'SUP',
        'purchase_order' => 'PO',
        'stock_movement' => 'STK',
        'stock_adjustment' => 'ADJ'
    ];
    $type = strtolower($type);
    $prefix = isset($prefixes[$type]) ? $prefixes[$type] : strtoupper($type);
    return $prefix . '-' . $id;
}

/**
 * Get prefix for a given type
 */
function getIdPrefix($type) {
    $prefixes = [
        'customer' => 'CUS',
        'product' => 'PRD',
        'sale' => 'SAL',
        'user' => 'UID',
        'payment' => 'PAY',
        'supplier' => 'SUP',
        'purchase_order' => 'PO',
        'stock_movement' => 'STK',
        'stock_adjustment' => 'ADJ'
    ];
    return $prefixes[strtolower($type)] ?? 'ID';
}

/**
 * Get a setting value by key
 */
function getSetting($key, $default = '') {
    global $conn;
    if (!isset($conn)) return $default;
    $safe_key = $conn->real_escape_string($key);
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$safe_key'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Check if the current user has a specific permission
 */
function hasPermission($permission_key) {
    global $conn;
    if (!isset($_SESSION['role'])) return false;
    
    $role = $_SESSION['role'];
    if (strtolower($role) === 'admin') return true;
    
    if (!isset($conn)) return false;
    
    $safe_role = $conn->real_escape_string($role);
    $safe_perm = $conn->real_escape_string($permission_key);
    
    $result = $conn->query("SELECT is_allowed FROM role_permissions WHERE LOWER(role_name) = LOWER('$safe_role') AND permission_key = '$safe_perm'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row['is_allowed'];
    }
    return false;
}

/**
 * Log Activity to activity_logs table
 */
function logActivity($user_id = null, $action_type = '', $entity_type = null, $entity_id = null, $old_value = null, $new_value = null, $description = null, $ip_address = null) {
    global $conn;
    if (!isset($conn)) return;

    try {
        $old_value_json = $old_value ? json_encode($old_value, JSON_UNESCAPED_UNICODE) : null;
        $new_value_json = $new_value ? json_encode($new_value, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO activity_logs 
                    (user_id, action_type, entity_type, entity_id, old_value, new_value, description, ip_address)
                VALUES 
                    (" . ($user_id !== null ? intval($user_id) : "NULL") . ", 
                     '" . $conn->real_escape_string($action_type) . "', 
                     " . ($entity_type !== null ? "'" . $conn->real_escape_string($entity_type) . "'" : "NULL") . ", 
                     " . ($entity_id !== null ? intval($entity_id) : "NULL") . ", 
                     " . ($old_value_json !== null ? "'" . $conn->real_escape_string($old_value_json) . "'" : "NULL") . ", 
                     " . ($new_value_json !== null ? "'" . $conn->real_escape_string($new_value_json) . "'" : "NULL") . ", 
                     " . ($description !== null ? "'" . $conn->real_escape_string($description) . "'" : "NULL") . ", 
                     " . ($ip_address !== null ? "'" . $conn->real_escape_string($ip_address) . "'" : "NULL") . ")";

        $conn->query($sql);
    } catch (Exception $e) {
        error_log("[LogActivity Error] " . $e->getMessage());
    }
}

/**
 * Get User IP
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    return trim($ip);
}

/**
 * CSV Output Helper
 */
function outputCsvDownload($filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    if ($output === false) exit('Unable to generate CSV file.');
    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($rows as $row) fputcsv($output, $row);
    fclose($output);
    exit;
}
?>
