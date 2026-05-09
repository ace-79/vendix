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
    include_once '../utils/mailer.php';
    requireApiLogin();

    $method = $_SERVER['REQUEST_METHOD'];

    // GET — List or single purchase order
    if ($method === 'GET') {
        if (!hasPermission('view_purchase_orders')) {
            throw new Exception('Access Denied');
        }

        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);

            // Get PO header
            $po = $conn->query("SELECT po.*, s.name as supplier_name, u.username as created_by
                                FROM purchase_orders po
                                LEFT JOIN suppliers s ON po.supplier_id = s.id
                                LEFT JOIN users u ON po.user_id = u.id
                                WHERE po.id = $id")->fetch_assoc();

            if (!$po) throw new Exception('Purchase order not found');

            // Get PO items
            $items = [];
            $itemsResult = $conn->query("SELECT poi.*, p.name as product_name, p.stock as current_stock
                                          FROM purchase_order_items poi
                                          LEFT JOIN products p ON poi.product_id = p.id
                                          WHERE poi.purchase_order_id = $id");
            if ($itemsResult) {
                while ($row = $itemsResult->fetch_assoc()) {
                    $items[] = $row;
                }
            }

            echo json_encode(['status' => 'success', 'data' => $po, 'items' => $items]);

        } else {
            // List with filters
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
            $supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

            $where = [];
            if ($status) $where[] = "po.status = '$status'";
            if ($supplier_id) $where[] = "po.supplier_id = $supplier_id";

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $result = $conn->query("SELECT po.*, s.name as supplier_name, u.username as created_by,
                                     (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = po.id) as items_count
                                    FROM purchase_orders po
                                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                                    LEFT JOIN users u ON po.user_id = u.id
                                    $whereClause
                                    ORDER BY po.order_date DESC
                                    LIMIT 200");
            $orders = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $orders]);
        }

    // POST — Create purchase order OR receive items
    } elseif ($method === 'POST') {
        requireCsrfToken(true);

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON');
        }

        $action = $input['action'] ?? 'create';

        // === CREATE PO ===
        if ($action === 'create') {
            if (!hasPermission('create_purchase_orders')) {
                throw new Exception('Access Denied: Cannot create purchase orders.');
            }

            $supplier_id = intval($input['supplier_id'] ?? 0);
            $expected_date = $conn->real_escape_string($input['expected_date'] ?? '');
            $notes = $conn->real_escape_string($input['notes'] ?? '');
            $reference_number = $conn->real_escape_string($input['reference_number'] ?? '');
            $status = $conn->real_escape_string($input['status'] ?? 'draft');
            $items = $input['items'] ?? [];
            $user_id = intval($_SESSION['user_id']);

            if (empty($items)) throw new Exception('At least one item is required');

            beginDatabaseTransaction($conn);

            // Calculate total cost
            $total_cost = 0;
            foreach ($items as $item) {
                $total_cost += floatval($item['quantity_ordered'] ?? 0) * floatval($item['unit_cost'] ?? 0);
            }

            $expected_date_sql = $expected_date ? "'$expected_date'" : "NULL";
            $supplier_sql = $supplier_id ? $supplier_id : "NULL";

            $query = "INSERT INTO purchase_orders (supplier_id, user_id, status, total_cost, notes, reference_number, expected_date)
                      VALUES ($supplier_sql, $user_id, '$status', $total_cost, '$notes', '$reference_number', $expected_date_sql)";

            if (!$conn->query($query)) {
                throw new Exception('Failed to create purchase order: ' . $conn->error);
            }

            $po_id = $conn->insert_id;

            // Insert items
            foreach ($items as $item) {
                $product_id = intval($item['product_id']);
                $qty = intval($item['quantity_ordered']);
                $cost = floatval($item['unit_cost']);
                $subtotal = $qty * $cost;

                $product = getProductSnapshot($conn, $product_id);
                if (($product['status'] ?? 'active') !== 'active') {
                    throw new Exception('Product "' . $product['name'] . '" is inactive and cannot be added to a purchase order');
                }

                if (!$conn->query("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity_ordered, unit_cost, subtotal)
                                   VALUES ($po_id, $product_id, $qty, $cost, $subtotal)")) {
                    throw new Exception('Failed to insert PO item: ' . $conn->error);
                }
            }

            $conn->commit();

            logActivity(
                user_id: $user_id,
                action_type: 'CREATE',
                entity_type: 'purchase_order',
                entity_id: $po_id,
                new_value: ['supplier_id' => $supplier_id, 'total_cost' => $total_cost, 'items_count' => count($items), 'status' => $status],
                description: "Created purchase order PO-$po_id with " . count($items) . " item(s), total: $total_cost"
            );

            // Auto-email purchase order if supplier has email
            if ($supplier_id && getSetting('auto_email_invoices', '0') === '1') {
                $sup_res = $conn->query("SELECT name, email FROM suppliers WHERE id = $supplier_id");
                if ($sup_res && $sup_res->num_rows > 0) {
                    $supplier = $sup_res->fetch_assoc();
                    if (!empty($supplier['email'])) {
                        $items_with_names = [];
                        foreach ($items as $item) {
                            $product_id = intval($item['product_id']);
                            $p_res = $conn->query("SELECT name FROM products WHERE id = $product_id");
                            $p_name = ($p_res && $p_res->num_rows > 0) ? $p_res->fetch_assoc()['name'] : "Product #" . $product_id;
                            $items_with_names[] = [
                                'product_name' => $p_name,
                                'quantity_ordered' => $item['quantity_ordered'],
                                'unit_cost' => $item['unit_cost']
                            ];
                        }
                        try {
                            sendPurchaseOrderEmail($po_id, $supplier['email'], $supplier['name'], $total_cost, $expected_date, $items_with_names);
                        } catch (Exception $e) {
                            // Suppress error so PO creation succeeds even if email fails
                            error_log("Failed to send PO email: " . $e->getMessage());
                        }
                    }
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Purchase order created', 'id' => $po_id]);

        // === RECEIVE ITEMS ===
        } elseif ($action === 'receive') {
            if (!hasPermission('receive_purchase_orders')) {
                throw new Exception('Access Denied: Cannot receive purchase orders.');
            }

            $po_id = intval($input['po_id'] ?? 0);
            $receive_items = $input['items'] ?? [];
            $user_id = intval($_SESSION['user_id']);

            if (!$po_id) throw new Exception('Purchase order ID required');
            if (empty($receive_items)) throw new Exception('No items to receive');

            // Verify PO exists and is receivable
            $po = $conn->query("SELECT * FROM purchase_orders WHERE id = $po_id")->fetch_assoc();
            if (!$po) throw new Exception('Purchase order not found');
            if (in_array($po['status'], ['received', 'cancelled'])) {
                throw new Exception('Cannot receive items: order is already ' . $po['status']);
            }

            beginDatabaseTransaction($conn);

            foreach ($receive_items as $item) {
                $poi_id = intval($item['poi_id']);
                $qty_receiving = intval($item['quantity_receiving']);

                if ($qty_receiving <= 0) continue;

                // Get the PO item details
                $poi = $conn->query("SELECT * FROM purchase_order_items WHERE id = $poi_id AND purchase_order_id = $po_id")->fetch_assoc();
                if (!$poi) continue;

                $remaining = intval($poi['quantity_ordered']) - intval($poi['quantity_received']);
                if ($qty_receiving > $remaining) {
                    throw new Exception("Cannot receive more than ordered for item #$poi_id (max: $remaining)");
                }

                // Update received quantity
                $conn->query("UPDATE purchase_order_items SET quantity_received = quantity_received + $qty_receiving WHERE id = $poi_id");

                // Update product stock
                $product_id = intval($poi['product_id']);
                applyInventoryDelta(
                    $conn,
                    $product_id,
                    $qty_receiving,
                    'purchase',
                    'purchase_order',
                    $po_id,
                    "Received from PO-$po_id",
                    $user_id,
                    false
                );
            }

            // Check remaining items too
            $remaining_check = $conn->query("SELECT SUM(quantity_ordered - quantity_received) as remaining FROM purchase_order_items WHERE purchase_order_id = $po_id");
            $total_remaining = intval($remaining_check->fetch_assoc()['remaining']);

            // Update PO status
            if ($total_remaining <= 0) {
                $new_status = 'received';
                $conn->query("UPDATE purchase_orders SET status = 'received', received_date = NOW() WHERE id = $po_id");
            } else {
                $new_status = 'partially_received';
                $conn->query("UPDATE purchase_orders SET status = 'partially_received' WHERE id = $po_id");
            }

            $conn->commit();

            logActivity(
                user_id: $user_id,
                action_type: 'RECEIVE',
                entity_type: 'purchase_order',
                entity_id: $po_id,
                new_value: ['items_received' => count($receive_items), 'new_status' => $new_status],
                description: "Received items for PO-$po_id, new status: $new_status"
            );

            echo json_encode(['status' => 'success', 'message' => 'Items received successfully', 'new_status' => $new_status]);
        } else {
            throw new Exception('Invalid action');
        }

    // PUT — Update PO status
    } elseif ($method === 'PUT') {
        requireCsrfToken(true);

        if (!hasPermission('create_purchase_orders')) {
            throw new Exception('Access Denied');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        if (!$id) throw new Exception('PO ID required');

        $po = $conn->query("SELECT * FROM purchase_orders WHERE id = $id")->fetch_assoc();
        if (!$po) throw new Exception('Purchase order not found');

        $updates = [];
        if (isset($input['status'])) {
            $newStatus = $conn->real_escape_string($input['status']);
            // Validate status transitions
            $allowed = [
                'draft' => ['ordered', 'cancelled'],
                'ordered' => ['partially_received', 'received', 'cancelled'],
                'partially_received' => ['received', 'cancelled'],
            ];
            if (!isset($allowed[$po['status']]) || !in_array($newStatus, $allowed[$po['status']])) {
                throw new Exception("Cannot change status from '{$po['status']}' to '$newStatus'");
            }
            $updates[] = "status = '$newStatus'";
            if ($newStatus === 'received') {
                $updates[] = "received_date = NOW()";
            }
        }
        if (isset($input['notes'])) $updates[] = "notes = '" . $conn->real_escape_string($input['notes']) . "'";
        if (isset($input['reference_number'])) $updates[] = "reference_number = '" . $conn->real_escape_string($input['reference_number']) . "'";
        if (isset($input['expected_date'])) $updates[] = "expected_date = '" . $conn->real_escape_string($input['expected_date']) . "'";

        if (empty($updates)) throw new Exception('No fields to update');

        if (!$conn->query("UPDATE purchase_orders SET " . implode(', ', $updates) . " WHERE id = $id")) {
            throw new Exception('Failed to update purchase order');
        }

        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'purchase_order',
            entity_id: $id,
            old_value: ['status' => $po['status']],
            new_value: $input,
            description: "Updated purchase order PO-$id"
        );

        echo json_encode(['status' => 'success', 'message' => 'Purchase order updated']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }

    if (isset($conn)) $conn->close();
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        rollbackDatabaseTransaction($conn);
    }

    ob_clean();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
