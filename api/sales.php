<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');
session_start();

function assertSalesAccess($permission = 'view_sales') {
    if (hasPermission($permission) || hasPermission('view_pos')) {
        return;
    }

    throw new Exception('Access Denied: You do not have permission to manage sales.');
}

try {
    // Clear any buffered output
    ob_clean();

    include '../config/db.php';
    include '../config/auth.php';
    include_once '../config/helpers.php';
    include_once '../utils/sales_service.php';
    include_once '../utils/mailer.php';
    requireApiLogin();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method == 'GET') {
        assertSalesAccess();

        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sale = $conn->query("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, u.username FROM sales s 
                                  LEFT JOIN customers c ON s.customer_id = c.id 
                                  LEFT JOIN users u ON s.user_id = u.id 
                                  WHERE s.id = $id")->fetch_assoc();

            if ($sale) {
                $items = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si 
                                       LEFT JOIN products p ON si.product_id = p.id 
                                       WHERE si.sale_id = $id")->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $sale, 'items' => $items]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sale not found']);
            }
        } else {
            $result = $conn->query("SELECT s.*, c.name as customer_name FROM sales s 
                                   LEFT JOIN customers c ON s.customer_id = c.id 
                                   ORDER BY s.sale_date DESC LIMIT 100");
            $sales = [];
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $sales]);
        }
    } elseif ($method == 'POST') {
        requireCsrfToken(true);
        assertSalesAccess('view_pos');

        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            throw new Exception('Empty request body');
        }

        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if ($input === null) {
            throw new Exception('Failed to parse request');
        }

        $saleData = createSaleRecord($conn, $input, $_SESSION['user_id']);


        // Log sale creation
        $sale_id = $saleData['sale_id'];
        $items = $saleData['items'];
        $items_count = count($items);
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'sale',
            entity_id: $sale_id,
            new_value: [
                'customer_id' => $saleData['customer_id'],
                'total_amount' => $saleData['total_amount'],
                'discount_amount' => $saleData['discount_amount'],
                'items_count' => $items_count,
                'payment_status' => $saleData['payment_status'],
                'payment_method' => $saleData['payment_method']
            ],
            description: "Created sale with $items_count item(s), total: " . $saleData['total_amount'] . ($saleData['discount_amount'] > 0 ? " (Discount: " . $saleData['discount_amount'] . ")" : "")
        );

        // Auto-email invoice if enabled and customer has email
        if ($saleData['customer_id'] && getSetting('auto_email_invoices', '0') === '1') {
            $cust_res = $conn->query("SELECT name, email FROM customers WHERE id = " . (int) $saleData['customer_id']);
            if ($cust_res && $cust_res->num_rows > 0) {
                $customer = $cust_res->fetch_assoc();
                if (!empty($customer['email'])) {
                    // Fetch product names for the email
                    $items_with_names = [];
                    foreach ($items as $item) {
                        $p_res = $conn->query("SELECT name FROM products WHERE id = " . intval($item['product_id']));
                        $p_name = ($p_res && $p_res->num_rows > 0) ? $p_res->fetch_assoc()['name'] : "Product #" . $item['product_id'];
                        $items_with_names[] = [
                            'product_name' => $p_name,
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price']
                        ];
                    }

                    sendInvoiceEmail($sale_id, $customer['email'], $customer['name'], $saleData['total_amount'], $saleData['discount_amount'], $items_with_names);
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Sale created', 'id' => $sale_id]);
    } elseif ($method == 'PUT') {
        requireCsrfToken(true);
        assertSalesAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);

        if (!$id) {
            throw new Exception('Sale ID required');
        }

        $saleUpdate = updateSalePaymentRecord($conn, $id, $input, $_SESSION['user_id']);
        $sale = $saleUpdate['sale'];

        // Log the sale update
        $old_data = [
            'payment_status' => $sale['payment_status'],
            'payment_method' => $sale['payment_method']
        ];
        $new_data = [
            'payment_status' => $saleUpdate['new_payment_status'],
            'payment_method' => $saleUpdate['new_payment_method']
        ];

        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'sale',
            entity_id: $id,
            old_value: $old_data,
            new_value: $new_data,
            description: "Updated sale payment status and method"
        );

        if ($saleUpdate['payment_id'] !== null) {
            logActivity(
                user_id: $_SESSION['user_id'],
                action_type: 'PAYMENT',
                entity_type: 'payment',
                entity_id: $saleUpdate['payment_id'],
                new_value: [
                    'sale_id' => $id,
                    'amount' => $sale['total_amount'],
                    'method' => $saleUpdate['new_payment_method']
                ],
                description: "Payment recorded for sale #$id, amount: " . $sale['total_amount']
            );
        }

        echo json_encode(['status' => 'success', 'message' => 'Sale updated']);
    } elseif ($method == 'DELETE') {
        requireCsrfToken(true);
        assertSalesAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);

        if (!$id) {
            throw new Exception('Sale ID required');
        }

        $saleToDelete = deleteSaleRecord($conn, $id, $_SESSION['user_id']);

        // Log the deletion
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'DELETE',
            entity_type: 'sale',
            entity_id: $id,
            old_value: [
                'customer_id' => $saleToDelete['customer_id'],
                'total_amount' => $saleToDelete['total_amount'],
                'payment_status' => $saleToDelete['payment_status']
            ],
            description: "Deleted sale, total amount: " . $saleToDelete['total_amount']
        );

        echo json_encode(['status' => 'success', 'message' => 'Sale deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }

    if (isset($conn)) {
        $conn->close();
    }
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        rollbackDatabaseTransaction($conn);
    }

    ob_clean();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
