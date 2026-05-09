<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();

try {
    include '../config/db.php';
    include '../config/auth.php';
    requireApiAdmin();

    // Get filter parameters - sanitize them
    $action_type = isset($_GET['action_type']) && $_GET['action_type'] !== 'ALL' ? $conn->real_escape_string($_GET['action_type']) : '';
    $entity_type = isset($_GET['entity_type']) && $_GET['entity_type'] !== 'ALL' ? $conn->real_escape_string($_GET['entity_type']) : '';
    $user_id = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : '';
    $date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $conn->real_escape_string($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $conn->real_escape_string($_GET['date_to']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause
    $where_parts = [];

    if ($action_type) {
        $where_parts[] = "al.action_type = '$action_type'";
    }

    if ($entity_type) {
        $where_parts[] = "al.entity_type = '$entity_type'";
    }

    if ($user_id) {
        $where_parts[] = "al.user_id = $user_id";
    }

    if ($date_from) {
        $where_parts[] = "DATE(al.created_at) >= '$date_from'";
    }

    if ($date_to) {
        $where_parts[] = "DATE(al.created_at) <= '$date_to'";
    }

    $where = count($where_parts) > 0 ? implode(" AND ", $where_parts) : "1=1";

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM activity_logs al WHERE $where";
    $count_result = $conn->query($count_sql);
    if (!$count_result) {
        throw new Exception("Count query failed: " . $conn->error);
    }
    $count_row = $count_result->fetch_assoc();
    $total = $count_row['total'];

    // Get paginated results
    $sql = "SELECT 
                al.*,
                u.username
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE $where
            ORDER BY al.created_at DESC
            LIMIT $limit OFFSET $offset";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        // Parse JSON fields
        if ($row['old_value']) {
            $row['old_value'] = json_decode($row['old_value'], true);
        }
        if ($row['new_value']) {
            $row['new_value'] = json_decode($row['new_value'], true);
        }
        $logs[] = $row;
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
