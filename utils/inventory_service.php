<?php

function beginDatabaseTransaction(mysqli $conn) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn->begin_transaction();
}

function rollbackDatabaseTransaction(mysqli $conn) {
    try {
        $conn->rollback();
    } catch (Throwable $e) {
        // Ignore rollback errors so the original failure can be returned.
    }
}

function getProductSnapshot(mysqli $conn, $productId) {
    $productId = (int) $productId;
    $result = $conn->query("SELECT id, name, stock, status FROM products WHERE id = $productId");

    if (!$result || $result->num_rows === 0) {
        throw new Exception("Product #$productId not found");
    }

    return $result->fetch_assoc();
}

function recordInventoryMovement(
    mysqli $conn,
    $productId,
    $movementType,
    $quantity,
    $stockBefore,
    $stockAfter,
    $referenceType = null,
    $referenceId = null,
    $notes = null,
    $userId = null
) {
    $safeType = $conn->real_escape_string((string) $movementType);
    $safeRefType = $referenceType !== null ? "'" . $conn->real_escape_string((string) $referenceType) . "'" : 'NULL';
    $safeRefId = $referenceId !== null ? (int) $referenceId : 'NULL';
    $safeNotes = $notes !== null ? "'" . $conn->real_escape_string((string) $notes) . "'" : 'NULL';
    $safeUserId = $userId !== null ? (int) $userId : 'NULL';

    $query = "INSERT INTO stock_movements (product_id, movement_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, user_id)
              VALUES (" . (int) $productId . ", '$safeType', " . (int) $quantity . ", " . (int) $stockBefore . ", " . (int) $stockAfter . ", $safeRefType, $safeRefId, $safeNotes, $safeUserId)";

    if (!$conn->query($query)) {
        throw new Exception('Failed to record stock movement: ' . $conn->error);
    }
}

function applyInventoryDelta(
    mysqli $conn,
    $productId,
    $quantityDelta,
    $movementType,
    $referenceType = null,
    $referenceId = null,
    $notes = null,
    $userId = null,
    $preventNegativeStock = true
) {
    $product = getProductSnapshot($conn, $productId);
    $stockBefore = (int) $product['stock'];
    $stockAfter = $stockBefore + (int) $quantityDelta;

    if ($preventNegativeStock && $stockAfter < 0) {
        throw new Exception('Insufficient stock for ' . $product['name']);
    }

    recordInventoryMovement(
        $conn,
        $productId,
        $movementType,
        $quantityDelta,
        $stockBefore,
        $stockAfter,
        $referenceType,
        $referenceId,
        $notes,
        $userId
    );

    if (!$conn->query("UPDATE products SET stock = $stockAfter WHERE id = " . (int) $productId)) {
        throw new Exception('Failed to update stock: ' . $conn->error);
    }

    return [
        'product' => $product,
        'stock_before' => $stockBefore,
        'stock_after' => $stockAfter,
        'quantity' => (int) $quantityDelta
    ];
}

function createStockAdjustmentRecord(mysqli $conn, $productId, $adjustmentType, $quantity, $reason, $userId) {
    $product = getProductSnapshot($conn, $productId);
    $stockBefore = (int) $product['stock'];
    $stockAfter = $stockBefore + (int) $quantity;

    if ($stockAfter < 0) {
        throw new Exception("Cannot adjust: would result in negative stock (current: $stockBefore, adjustment: $quantity)");
    }

    $safeAdjustmentType = $conn->real_escape_string((string) $adjustmentType);
    $safeReason = $conn->real_escape_string((string) $reason);
    $userId = (int) $userId;
    $productId = (int) $productId;
    $quantity = (int) $quantity;

    $adjustmentQuery = "INSERT INTO stock_adjustments (product_id, adjustment_type, quantity, reason, user_id)
                        VALUES ($productId, '$safeAdjustmentType', $quantity, '$safeReason', $userId)";

    if (!$conn->query($adjustmentQuery)) {
        throw new Exception('Failed to create adjustment: ' . $conn->error);
    }

    $adjustmentId = $conn->insert_id;

    $movement = applyInventoryDelta(
        $conn,
        $productId,
        $quantity,
        'adjustment',
        'adjustment',
        $adjustmentId,
        $reason,
        $userId,
        true
    );

    return [
        'adjustment_id' => $adjustmentId,
        'product' => $movement['product'],
        'stock_before' => $movement['stock_before'],
        'stock_after' => $movement['stock_after'],
        'quantity' => $movement['quantity']
    ];
}
?>
