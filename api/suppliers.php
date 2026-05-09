<?php
session_start();

try {
    include_once '../utils/api_helper.php';
    initJsonApi();
    clearApiOutputBuffer();

    include '../config/db.php';
    include '../config/auth.php';
    include_once '../config/helpers.php';
    requireApiLogin();

    $method = $_SERVER['REQUEST_METHOD'];

    // GET — List suppliers or get single supplier
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM suppliers WHERE id = $id");
            if ($result && $result->num_rows > 0) {
                apiSuccess(['data' => $result->fetch_assoc()]);
            } else {
                apiError('Supplier not found', 404);
            }
        } else {
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
            $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

            $where = [];
            if ($status) $where[] = "status = '$status'";
            if ($search) $where[] = "(name LIKE '%$search%' OR contact_person LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%')";

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $result = $conn->query("SELECT * FROM suppliers $whereClause ORDER BY name ASC");
            $suppliers = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $suppliers[] = $row;
                }
            }
            apiSuccess(['data' => $suppliers]);
        }

    // POST — Create supplier
    } elseif ($method === 'POST') {
        requireCsrfToken(true);

        if (!hasPermission('manage_suppliers')) {
            throw new Exception('Access Denied: You do not have permission to manage suppliers.');
        }

        $input = requireJsonRequestBody();

        $name = $conn->real_escape_string(trim($input['name'] ?? ''));
        $contact_person = $conn->real_escape_string(trim($input['contact_person'] ?? ''));
        $phone = $conn->real_escape_string(trim($input['phone'] ?? ''));
        $email = $conn->real_escape_string(trim($input['email'] ?? ''));
        $address = $conn->real_escape_string(trim($input['address'] ?? ''));
        $status = $conn->real_escape_string($input['status'] ?? 'active');

        if (empty($name)) {
            throw new Exception('Supplier name is required');
        }

        $query = "INSERT INTO suppliers (name, contact_person, phone, email, address, status)
                  VALUES ('$name', '$contact_person', '$phone', '$email', '$address', '$status')";

        if (!$conn->query($query)) {
            throw new Exception('Failed to create supplier: ' . $conn->error);
        }

        $supplier_id = $conn->insert_id;

        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'supplier',
            entity_id: $supplier_id,
            new_value: ['name' => $name, 'contact_person' => $contact_person, 'phone' => $phone, 'email' => $email],
            description: "Created supplier: $name"
        );

        apiSuccess(['message' => 'Supplier created', 'id' => $supplier_id]);

    // PUT — Update supplier
    } elseif ($method === 'PUT') {
        requireCsrfToken(true);

        if (!hasPermission('manage_suppliers')) {
            throw new Exception('Access Denied: You do not have permission to manage suppliers.');
        }

        $input = requireJsonRequestBody();
        $id = intval($input['id'] ?? 0);
        if (!$id) throw new Exception('Supplier ID required');

        // Get old data
        $old = $conn->query("SELECT * FROM suppliers WHERE id = $id")->fetch_assoc();
        if (!$old) throw new Exception('Supplier not found');

        $updates = [];
        if (isset($input['name'])) $updates[] = "name = '" . $conn->real_escape_string(trim($input['name'])) . "'";
        if (isset($input['contact_person'])) $updates[] = "contact_person = '" . $conn->real_escape_string(trim($input['contact_person'])) . "'";
        if (isset($input['phone'])) $updates[] = "phone = '" . $conn->real_escape_string(trim($input['phone'])) . "'";
        if (isset($input['email'])) $updates[] = "email = '" . $conn->real_escape_string(trim($input['email'])) . "'";
        if (isset($input['address'])) $updates[] = "address = '" . $conn->real_escape_string(trim($input['address'])) . "'";
        if (isset($input['status'])) $updates[] = "status = '" . $conn->real_escape_string($input['status']) . "'";

        if (empty($updates)) throw new Exception('No fields to update');

        if (!$conn->query("UPDATE suppliers SET " . implode(', ', $updates) . " WHERE id = $id")) {
            throw new Exception('Failed to update supplier');
        }

        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'supplier',
            entity_id: $id,
            old_value: ['name' => $old['name'], 'status' => $old['status']],
            new_value: $input,
            description: "Updated supplier: " . ($input['name'] ?? $old['name'])
        );

        apiSuccess(['message' => 'Supplier updated']);

    // DELETE — Delete supplier
    } elseif ($method === 'DELETE') {
        requireCsrfToken(true);

        if (!hasPermission('manage_suppliers')) {
            throw new Exception('Access Denied');
        }

        $id = intval($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Supplier ID required');

        $old = $conn->query("SELECT * FROM suppliers WHERE id = $id")->fetch_assoc();
        if (!$old) throw new Exception('Supplier not found');

        // Check if supplier has purchase orders
        $poCheck = $conn->query("SELECT COUNT(*) as cnt FROM purchase_orders WHERE supplier_id = $id")->fetch_assoc();
        if ($poCheck['cnt'] > 0) {
            throw new Exception('Cannot delete: supplier has ' . $poCheck['cnt'] . ' purchase order(s). Deactivate instead.');
        }

        if (!$conn->query("DELETE FROM suppliers WHERE id = $id")) {
            throw new Exception('Failed to delete supplier');
        }

        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'DELETE',
            entity_type: 'supplier',
            entity_id: $id,
            old_value: ['name' => $old['name'], 'phone' => $old['phone']],
            description: "Deleted supplier: " . $old['name']
        );

        apiSuccess(['message' => 'Supplier deleted']);

    } else {
        apiError('Invalid request method', 405);
    }

    apiCloseConnection($conn);
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}
?>
