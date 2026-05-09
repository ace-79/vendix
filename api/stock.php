<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    ob_clean();

    include '../config/db.php';
    include '../config/auth.php';
    include_once '../config/helpers.php';
    include_once '../utils/inventory_service.php';
    requireApiLogin();

    $method = $_SERVER['REQUEST_METHOD'];

    // GET — Fetch stock movements and summary
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'movements';

        if ($action === 'movements') {
            // Fetch stock movements with filters
            $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
            $movement_type = isset($_GET['movement_type']) ? $conn->real_escape_string($_GET['movement_type']) : '';
            $date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
            $date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

            $where = [];
            if ($product_id) $where[] = "sm.product_id = $product_id";
            if ($movement_type) $where[] = "sm.movement_type = '$movement_type'";
            if ($date_from) $where[] = "DATE(sm.created_at) >= '$date_from'";
            if ($date_to) $where[] = "DATE(sm.created_at) <= '$date_to'";

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $query = "SELECT sm.*, p.name as product_name, u.username 
                      FROM stock_movements sm
                      LEFT JOIN products p ON sm.product_id = p.id
                      LEFT JOIN users u ON sm.user_id = u.id
                      $whereClause
                      ORDER BY sm.created_at DESC
                      LIMIT $limit";

            $result = $conn->query($query);
            $movements = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $movements[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $movements]);

        } elseif ($action === 'summary') {
            // Stock value summary
            $query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(stock) as total_units,
                        SUM(stock * cost_price) as total_stock_value,
                        SUM(stock * price) as total_retail_value,
                        SUM(CASE WHEN stock <= min_stock THEN 1 ELSE 0 END) as low_stock_count,
                        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
                      FROM products";
            $result = $conn->query($query);
            $summary = $result ? $result->fetch_assoc() : [];
            echo json_encode(['status' => 'success', 'data' => $summary]);

        } elseif ($action === 'adjustments') {
            // Fetch stock adjustments
            $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
            $where = $product_id ? "WHERE sa.product_id = $product_id" : '';

            $query = "SELECT sa.*, p.name as product_name, u.username
                      FROM stock_adjustments sa
                      LEFT JOIN products p ON sa.product_id = p.id
                      LEFT JOIN users u ON sa.user_id = u.id
                      $where
                      ORDER BY sa.created_at DESC
                      LIMIT 100";
            $result = $conn->query($query);
            $adjustments = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $adjustments[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $adjustments]);

        } elseif ($action === 'products') {
            // Get products list for dropdowns
            $result = $conn->query("SELECT id, name, stock, min_stock, cost_price, price FROM products WHERE status = 'active' ORDER BY name ASC");
            $products = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $products[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $products]);

        } else {
            throw new Exception('Invalid action');
        }

    // POST — Create stock adjustment
    } elseif ($method === 'POST') {
        requireCsrfToken(true);

        if (!hasPermission('adjust_stock')) {
            throw new Exception('Access Denied: You do not have permission to adjust stock.');
        }

        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            throw new Exception('Empty request body');
        }
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        $product_id = intval($input['product_id'] ?? 0);
        $adjustment_type = $conn->real_escape_string($input['adjustment_type'] ?? '');
        $quantity = intval($input['quantity'] ?? 0);
        $reason = $conn->real_escape_string($input['reason'] ?? '');

        if (!$product_id) throw new Exception('Product is required');
        if (!$adjustment_type) throw new Exception('Adjustment type is required');
        if ($quantity === 0) throw new Exception('Quantity cannot be zero');
        if (empty($reason)) throw new Exception('Reason is required');

        $user_id = intval($_SESSION['user_id']);
        $adjustment = createStockAdjustmentRecord($conn, $product_id, $adjustment_type, $quantity, $reason, $user_id);

        // Log activity
        logActivity(
            user_id: $user_id,
            action_type: 'STOCK_ADJUST',
            entity_type: 'product',
            entity_id: $product_id,
            old_value: ['stock' => $adjustment['stock_before']],
            new_value: ['stock' => $adjustment['stock_after'], 'adjustment_type' => $adjustment_type, 'quantity' => $quantity],
            description: "Stock adjustment ($adjustment_type) for {$adjustment['product']['name']}: $quantity units (was {$adjustment['stock_before']}, now {$adjustment['stock_after']}). Reason: $reason"
        );

        echo json_encode([
            'status' => 'success',
            'message' => 'Stock adjusted successfully',
            'data' => [
                'adjustment_id' => $adjustment['adjustment_id'],
                'product_name' => $adjustment['product']['name'],
                'stock_before' => $adjustment['stock_before'],
                'stock_after' => $adjustment['stock_after'],
                'quantity' => $adjustment['quantity']
            ]
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }

    if (isset($conn)) {
        $conn->close();
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
