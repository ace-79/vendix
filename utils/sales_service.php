<?php

include_once __DIR__ . '/inventory_service.php';

function createSaleRecord(mysqli $conn, array $input, $userId) {
    $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
    $totalAmount = (float) ($input['total_amount'] ?? 0);
    $discountAmount = (float) ($input['discount_amount'] ?? 0);
    $paymentStatus = $conn->real_escape_string((string) ($input['payment_status'] ?? 'Pending'));
    $paymentMethod = $conn->real_escape_string((string) ($input['payment_method'] ?? 'Cash'));
    $items = $input['items'] ?? [];
    $userId = (int) $userId;

    if (!is_array($items) || count($items) === 0) {
        throw new Exception('At least one sale item is required');
    }

    beginDatabaseTransaction($conn);

    $query = "INSERT INTO sales (customer_id, user_id, total_amount, discount_amount, payment_status, payment_method)
              VALUES (" . ($customerId ? $customerId : 'NULL') . ", $userId, $totalAmount, $discountAmount, '$paymentStatus', '$paymentMethod')";

    if (!$conn->query($query)) {
        throw new Exception('Failed to create sale: ' . $conn->error);
    }

    $saleId = $conn->insert_id;

    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $subtotal = (float) ($item['subtotal'] ?? 0);

        if ($productId <= 0 || $quantity <= 0 || $unitPrice < 0 || $subtotal < 0) {
            throw new Exception('Invalid sale item payload');
        }

        $product = getProductSnapshot($conn, $productId);
        if (($product['status'] ?? 'active') !== 'active') {
            throw new Exception('Product "' . $product['name'] . '" is inactive and cannot be sold');
        }

        $itemQuery = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
                      VALUES ($saleId, $productId, $quantity, $unitPrice, $subtotal)";

        if (!$conn->query($itemQuery)) {
            throw new Exception('Failed to insert sale item: ' . $conn->error);
        }

        applyInventoryDelta(
            $conn,
            $productId,
            -$quantity,
            'sale',
            'sale',
            $saleId,
            "Sale #$saleId",
            $userId,
            true
        );
    }

    if ($paymentStatus === 'Paid') {
        $paymentQuery = "INSERT INTO payments (sale_id, amount, method, paid_at)
                         VALUES ($saleId, $totalAmount, '$paymentMethod', NOW())";

        if (!$conn->query($paymentQuery)) {
            throw new Exception('Failed to create payment record: ' . $conn->error);
        }
    }

    $conn->commit();

    return [
        'sale_id' => $saleId,
        'customer_id' => $customerId,
        'total_amount' => $totalAmount,
        'discount_amount' => $discountAmount,
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod,
        'items' => $items
    ];
}

function updateSalePaymentRecord(mysqli $conn, $saleId, array $input, $userId) {
    $saleId = (int) $saleId;
    $userId = (int) $userId;
    $saleResult = $conn->query("SELECT total_amount, payment_status, payment_method FROM sales WHERE id = $saleId");

    if (!$saleResult) {
        throw new Exception('Failed to fetch sale: ' . $conn->error);
    }

    $sale = $saleResult->fetch_assoc();
    if (!$sale) {
        throw new Exception('Sale not found');
    }

    if ($sale['payment_status'] === 'Cancelled') {
        throw new Exception('Cannot edit a cancelled sale');
    }

    $newPaymentStatus = $input['payment_status'] ?? $sale['payment_status'];
    $newPaymentMethod = $input['payment_method'] ?? $sale['payment_method'];

    if ($sale['payment_status'] === 'Paid') {
        if ($newPaymentStatus === 'Pending') {
            throw new Exception('Paid sales cannot be changed back to Pending');
        }

        if ($newPaymentStatus === 'Paid' && $newPaymentMethod !== $sale['payment_method']) {
            throw new Exception('Payment method cannot be changed after a sale is paid');
        }
    }

    beginDatabaseTransaction($conn);

    if (($input['payment_status'] ?? '') === 'Cancelled' && $sale['payment_status'] !== 'Cancelled') {
        $saleItems = $conn->query("SELECT product_id, quantity FROM sale_items WHERE sale_id = $saleId");
        if ($saleItems) {
            while ($saleItem = $saleItems->fetch_assoc()) {
                $productId = (int) $saleItem['product_id'];
                $quantity = (int) $saleItem['quantity'];

                applyInventoryDelta(
                    $conn,
                    $productId,
                    $quantity,
                    'cancel_restore',
                    'sale',
                    $saleId,
                    "Stock restored from cancelled sale #$saleId",
                    $userId,
                    false
                );
            }
        }

        if (!$conn->query("DELETE FROM payments WHERE sale_id = $saleId")) {
            throw new Exception('Failed to remove payment records: ' . $conn->error);
        }
    }

    $updates = [];
    if (isset($input['payment_status'])) {
        $updates[] = "payment_status = '" . $conn->real_escape_string((string) $input['payment_status']) . "'";
    }
    if (isset($input['payment_method'])) {
        $updates[] = "payment_method = '" . $conn->real_escape_string((string) $input['payment_method']) . "'";
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    if (!$conn->query("UPDATE sales SET " . implode(', ', $updates) . " WHERE id = $saleId")) {
        throw new Exception('Failed to update sale');
    }

    $paymentId = null;
    if ($newPaymentStatus === 'Paid' && $sale['payment_status'] !== 'Paid') {
        $paymentCheck = $conn->query("SELECT id FROM payments WHERE sale_id = $saleId");
        if ($paymentCheck && $paymentCheck->num_rows === 0) {
            $paymentQuery = "INSERT INTO payments (sale_id, amount, method, paid_at)
                             VALUES ($saleId, " . (float) $sale['total_amount'] . ", '" . $conn->real_escape_string((string) $newPaymentMethod) . "', NOW())";

            if (!$conn->query($paymentQuery)) {
                throw new Exception('Failed to create payment record: ' . $conn->error);
            }

            $paymentId = $conn->insert_id;
        }
    }

    $conn->commit();

    return [
        'sale' => $sale,
        'new_payment_status' => $newPaymentStatus,
        'new_payment_method' => $newPaymentMethod,
        'payment_id' => $paymentId
    ];
}

function deleteSaleRecord(mysqli $conn, $saleId, $userId) {
    $saleId = (int) $saleId;
    $userId = (int) $userId;
    $saleToDelete = $conn->query("SELECT * FROM sales WHERE id = $saleId")->fetch_assoc();

    if (!$saleToDelete) {
        throw new Exception('Sale not found');
    }

    if ($saleToDelete['payment_status'] === 'Cancelled') {
        throw new Exception('Cannot delete a cancelled sale');
    }

    beginDatabaseTransaction($conn);

    $saleItems = $conn->query("SELECT product_id, quantity FROM sale_items WHERE sale_id = $saleId");
    if ($saleItems) {
        while ($saleItem = $saleItems->fetch_assoc()) {
            $productId = (int) $saleItem['product_id'];
            $quantity = (int) $saleItem['quantity'];

            applyInventoryDelta(
                $conn,
                $productId,
                $quantity,
                'delete_restore',
                'sale',
                $saleId,
                "Stock restored from deleted sale #$saleId",
                $userId,
                false
            );
        }
    }

    if (!$conn->query("DELETE FROM payments WHERE sale_id = $saleId")) {
        throw new Exception('Failed to delete payment records');
    }

    if (!$conn->query("DELETE FROM sale_items WHERE sale_id = $saleId")) {
        throw new Exception('Failed to delete sale items');
    }

    if (!$conn->query("DELETE FROM sales WHERE id = $saleId")) {
        throw new Exception('Failed to delete sale');
    }

    $conn->commit();

    return $saleToDelete;
}
?>
