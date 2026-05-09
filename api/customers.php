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

    // Require base permission to manage customers for any action
    if (!hasPermission('view_customers')) {
        throw new Exception('Access Denied: You do not have permission to manage customers.');
    }

    if ($method == 'GET') {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM customers WHERE id = $id");
            if ($result && $result->num_rows > 0) {
                apiSuccess(['data' => $result->fetch_assoc()]);
            } else {
                apiError('Customer not found', 404);
            }
        } else {
            $result = $conn->query("SELECT * FROM customers ORDER BY name ASC");
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
            apiSuccess(['data' => $customers]);
        }
    }
    elseif ($method == 'POST') {
        requireCsrfToken(true);
        $input = requireJsonRequestBody();
        
        $name = $conn->real_escape_string($input['name'] ?? '');
        $email = $conn->real_escape_string($input['email'] ?? '');
        $phone = $conn->real_escape_string($input['phone'] ?? '');
        
        if (empty($name)) {
            throw new Exception('Customer name is required');
        }
        
        $query = "INSERT INTO customers (name, email, phone) 
                  VALUES ('$name', '$email', '$phone')";
        
        if (!$conn->query($query)) {
            throw new Exception('Failed to create customer: ' . $conn->error);
        }
        
        $customer_id = $conn->insert_id;
        
        // Log customer creation
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'customer',
            entity_id: $customer_id,
            new_value: [
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ],
            description: "Created customer: $name"
        );
        
        apiSuccess(['message' => 'Customer created', 'id' => $customer_id]);
    }
    elseif ($method == 'PUT') {
        requireCsrfToken(true);
        $input = requireJsonRequestBody();
        
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            throw new Exception('Customer ID required');
        }
        
        // Fetch old customer data before update
        $old_result = $conn->query("SELECT name, email, phone FROM customers WHERE id = $id");
        if (!$old_result || $old_result->num_rows === 0) {
            throw new Exception('Customer not found');
        }
        $old_customer = $old_result->fetch_assoc();
        
        $updates = [];
        if (isset($input['name'])) $updates[] = "name = '" . $conn->real_escape_string($input['name']) . "'";
        if (isset($input['email'])) $updates[] = "email = '" . $conn->real_escape_string($input['email']) . "'";
        if (isset($input['phone'])) $updates[] = "phone = '" . $conn->real_escape_string($input['phone']) . "'";
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $query = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = $id";
        
        if (!$conn->query($query)) {
            throw new Exception('Failed to update customer: ' . $conn->error);
        }
        
        // Log customer update
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'customer',
            entity_id: $id,
            old_value: $old_customer,
            new_value: array_filter([
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
                'phone' => $input['phone'] ?? null
            ], function ($v) { return $v !== null; }),
            description: "Updated customer ID: $id"
        );
        
        apiSuccess(['message' => 'Customer updated']);
    }
    elseif ($method == 'DELETE') {
        // Only admin should delete customers
        if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
            throw new Exception('Access Denied: Only administrators can delete customers.');
        }

        requireCsrfToken(true);

        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            throw new Exception('Customer ID required');
        }
        
        // Fetch customer data before delete
        $del_result = $conn->query("SELECT name, email, phone FROM customers WHERE id = $id");
        if (!$del_result || $del_result->num_rows === 0) {
            throw new Exception('Customer not found');
        }
        $deleted_customer = $del_result->fetch_assoc();
        
        if (!$conn->query("DELETE FROM customers WHERE id = $id")) {
            throw new Exception('Failed to delete customer');
        }
        
        // Log customer deletion
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'DELETE',
            entity_type: 'customer',
            entity_id: $id,
            old_value: $deleted_customer,
            description: "Deleted customer: {$deleted_customer['name']}"
        );
        
        apiSuccess(['message' => 'Customer deleted']);
    }
    else {
        apiError('Invalid request method', 405);
    }

    apiCloseConnection($conn);
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}
?>
