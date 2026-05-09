<?php
session_start();
include_once '../utils/api_helper.php';
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireApiLogin();
initJsonApi();
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    if (isset($_GET['sale_id'])) {
        $sale_id = intval($_GET['sale_id']);
        $result = $conn->query("SELECT * FROM payments WHERE sale_id = $sale_id");
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        apiSuccess(['data' => $payments]);
    } else {
        $result = $conn->query("SELECT * FROM payments ORDER BY paid_at DESC");
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        apiSuccess(['data' => $payments]);
    }
}
elseif ($method == 'POST') {
    requireCsrfToken(true);
    $input = requireJsonRequestBody();
    
    $sale_id = intval($input['sale_id'] ?? 0);
    $amount = floatval($input['amount'] ?? 0);
    $method_name = $conn->real_escape_string($input['method'] ?? 'Cash');
    $reference = $conn->real_escape_string($input['reference_number'] ?? '');
    
    if (!$sale_id || !$amount) {
        apiError('Sale ID and amount are required', 400);
    }
    
    $query = "INSERT INTO payments (sale_id, amount, method, reference_number) 
              VALUES ($sale_id, $amount, '$method_name', '$reference')";
    
    if ($conn->query($query)) {
        $payment_id = $conn->insert_id;
        
        // Update sale payment status if fully paid
        $sale = $conn->query("SELECT total_amount FROM sales WHERE id = $sale_id")->fetch_assoc();
        $total_paid = $conn->query("SELECT SUM(amount) as total FROM payments WHERE sale_id = $sale_id")->fetch_assoc()['total'];
        
        if ($total_paid >= $sale['total_amount']) {
            $conn->query("UPDATE sales SET payment_status = 'Paid' WHERE id = $sale_id");
        } else if ($total_paid > 0) {
            $conn->query("UPDATE sales SET payment_status = 'Partial' WHERE id = $sale_id");
        }
        
        // Log payment creation
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'payment',
            entity_id: $payment_id,
            new_value: [
                'sale_id' => $sale_id,
                'amount' => $amount,
                'method' => $method_name,
                'reference_number' => $reference
            ],
            description: "Created payment of $amount for sale ID: $sale_id"
        );
        
        apiSuccess(['message' => 'Payment recorded', 'id' => $payment_id]);
    } else {
        apiError('Failed to record payment', 500);
    }
}
else {
    apiError('Method not allowed', 405);
}

apiCloseConnection($conn);
?>
