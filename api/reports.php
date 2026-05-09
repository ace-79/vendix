<?php
header('Content-Type: application/json');
session_start();
include '../config/db.php';
include '../config/auth.php';

requireApiLogin();
if (!canViewReports()) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if ($type === 'daily') {
    $date = $start_date ?: date('Y-m-d');
    $result = $conn->query("SELECT 
                            DATE(s.sale_date) as date,
                            COUNT(*) as total_sales,
                            COALESCE(SUM(pay.amount), 0) as revenue,
                            COALESCE(SUM(CASE WHEN pay.id IS NOT NULL THEN s.total_amount - (
                                SELECT COALESCE(SUM(p.cost_price * si.quantity), 0)
                                FROM sale_items si
                                LEFT JOIN products p ON si.product_id = p.id
                                WHERE si.sale_id = s.id
                            ) ELSE 0 END), 0) as profit
                            FROM sales s
                            LEFT JOIN payments pay ON pay.sale_id = s.id
                            WHERE DATE(s.sale_date) = '$date'
                            GROUP BY DATE(s.sale_date)");
    echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
}
elseif ($type === 'monthly') {
    $month = $start_date ?: date('Y-m');
    $result = $conn->query("SELECT 
                            DATE_FORMAT(s.sale_date, '%Y-%m') as month,
                            COUNT(*) as total_sales,
                            COALESCE(SUM(pay.amount), 0) as revenue
                            FROM sales s
                            LEFT JOIN payments pay ON pay.sale_id = s.id
                            WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = '$month'
                            GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')");
    echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
}
elseif ($type === 'yearly') {
    $year = $start_date ?: date('Y');
    $result = $conn->query("SELECT 
                            YEAR(s.sale_date) as year,
                            COUNT(*) as total_sales,
                            COALESCE(SUM(pay.amount), 0) as revenue
                            FROM sales s
                            LEFT JOIN payments pay ON pay.sale_id = s.id
                            WHERE YEAR(s.sale_date) = $year
                            GROUP BY YEAR(s.sale_date)");
    echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
}
elseif ($type === 'top_products') {
    $result = $conn->query("SELECT 
                            p.id,
                            p.name,
                            SUM(si.quantity) as total_sold,
                            SUM(si.subtotal) as revenue
                            FROM sale_items si
                            LEFT JOIN products p ON si.product_id = p.id
                            LEFT JOIN sales s ON si.sale_id = s.id
                            INNER JOIN payments pay ON pay.sale_id = s.id
                            GROUP BY p.id
                            ORDER BY total_sold DESC
                            LIMIT 10");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $products]);
}
elseif ($type === 'low_stock') {
    $result = $conn->query("SELECT id, name, stock, min_stock FROM products WHERE stock <= min_stock ORDER BY stock ASC");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $products]);
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid report type']);
}

$conn->close();
?>
