<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');
session_start();

function getProductReferenceSummary(mysqli $conn, int $productId): array {
    $checks = [
        'sale item' => 'SELECT COUNT(*) AS cnt FROM sale_items WHERE product_id = ' . $productId,
        'purchase order item' => 'SELECT COUNT(*) AS cnt FROM purchase_order_items WHERE product_id = ' . $productId,
        'stock movement' => 'SELECT COUNT(*) AS cnt FROM stock_movements WHERE product_id = ' . $productId,
        'stock adjustment' => 'SELECT COUNT(*) AS cnt FROM stock_adjustments WHERE product_id = ' . $productId
    ];

    $summary = [];

    foreach ($checks as $label => $sql) {
        $result = $conn->query($sql);
        $count = $result ? (int) ($result->fetch_assoc()['cnt'] ?? 0) : 0;
        if ($count > 0) {
            $summary[] = $count . ' ' . $label . ($count === 1 ? '' : 's');
        }
    }

    return $summary;
}

function normalizeProductImagePath($path) {
    $trimmedPath = trim((string) $path);
    if ($trimmedPath === '') {
        return '';
    }

    return str_replace('\\', '/', $trimmedPath);
}

function uploadProductImage($fieldName, $existingImageUrl = '') {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return normalizeProductImagePath($existingImageUrl);
    }

    $file = $_FILES[$fieldName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return normalizeProductImagePath($existingImageUrl);
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Failed to upload product image');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new Exception('Invalid uploaded file');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Unsupported image format');
    }

    $uploadDir = realpath(__DIR__ . '/../assets/images/uploads/products');
    if ($uploadDir === false) {
        $uploadDir = __DIR__ . '/../assets/images/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new Exception('Unable to prepare image upload directory');
        }
    }

    $baseName = pathinfo($file['name'] ?? 'product-image', PATHINFO_FILENAME);
    $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
    $safeBaseName = trim((string) $safeBaseName, '_');
    if ($safeBaseName === '') {
        $safeBaseName = 'product_image';
    }

    $newFileName = $safeBaseName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new Exception('Failed to save uploaded image');
    }

    return '/Vendix/assets/images/uploads/products/' . $newFileName;
}

try {
    ob_clean();

    include '../config/db.php';
    include '../config/auth.php';
    include_once '../config/helpers.php';
    requireApiLogin();
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && isset($_POST['_method']) && strtoupper((string) $_POST['_method']) === 'PUT') {
        $method = 'PUT';
    }

    if ($method == 'GET') {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM products WHERE id = $id");
            if ($result && $result->num_rows > 0) {
                echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            }
        } else {
            $category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
            $query = "SELECT * FROM products";
            if (!empty($category)) {
                $query .= " WHERE category = '$category'";
            }
            $query .= " ORDER BY name ASC";
            $result = $conn->query($query);
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $products]);
        }
    }
    elseif ($method == 'POST') {
        requireCsrfToken(true);

        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
            throw new Exception('Access Denied: Only admins and managers can create products.');
        }
        
        $isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
        if ($isMultipart) {
            $input = $_POST;
        } else {
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                throw new Exception('Empty request body');
            }

            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
        }
        
        $name = $conn->real_escape_string($input['name'] ?? '');
        $category = $conn->real_escape_string($input['category'] ?? '');
        $price = floatval($input['price'] ?? 0);
        $cost_price = floatval($input['cost_price'] ?? 0);
        $stock = intval($input['stock'] ?? 0);
        $min_stock = intval($input['min_stock'] ?? 0);
        $sku = $conn->real_escape_string($input['sku'] ?? '');
        $barcode = $conn->real_escape_string($input['barcode'] ?? '');
        $supplier_id = !empty($input['supplier_id']) ? intval($input['supplier_id']) : "NULL";
        $image_url = $conn->real_escape_string(uploadProductImage('product_image', $input['image_url'] ?? ''));
        
        if (empty($name) || $price == 0) {
            throw new Exception('Name and price are required');
        }
        
        $status = $conn->real_escape_string($input['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new Exception('Invalid product status');
        }

        $query = "INSERT INTO products (name, sku, barcode, category, price, cost_price, stock, min_stock, supplier_id, image_url, status) 
                  VALUES ('$name', '$sku', '$barcode', '$category', $price, $cost_price, $stock, $min_stock, $supplier_id, '$image_url', '$status')";
        
        if (!$conn->query($query)) {
            throw new Exception('Failed to create product');
        }
        
        $product_id = $conn->insert_id;
        
        // Auto-generate unique SKU/Barcode if empty
        $update_needed = false;
        if (empty($sku)) {
            $sku = 'SKU-' . str_pad($product_id, 5, '0', STR_PAD_LEFT);
            $update_needed = true;
        }
        if (empty($barcode)) {
            $barcode = 'BC-' . str_pad($product_id, 5, '0', STR_PAD_LEFT) . rand(10, 99);
            $update_needed = true;
        }
        if ($update_needed) {
            $conn->query("UPDATE products SET sku = '$sku', barcode = '$barcode' WHERE id = $product_id");
        }
        
        // Log product creation
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'CREATE',
            entity_type: 'product',
            entity_id: $product_id,
            new_value: [
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'category' => $category,
                'price' => $price,
                'cost_price' => $cost_price,
                'stock' => $stock,
                'min_stock' => $min_stock,
                'supplier_id' => $supplier_id,
                'status' => $status
            ],
            description: "Created product: $name"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Product created', 'id' => $product_id]);
    }
    elseif ($method == 'PUT') {
        requireCsrfToken(true);

        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
            throw new Exception('Access Denied: Only admins and managers can update products.');
        }
        
        $isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
        if ($isMultipart) {
            $input = $_POST;
        } else {
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                throw new Exception('Empty request body');
            }

            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
        }
        
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            throw new Exception('Product ID required');
        }
        
        // Fetch old product data before update
        $old_result = $conn->query("SELECT name, category, price, cost_price, stock, min_stock, status FROM products WHERE id = $id");
        if (!$old_result || $old_result->num_rows === 0) {
            throw new Exception('Product not found');
        }
        $old_product = $old_result->fetch_assoc();
        
        $updates = [];
        if (isset($input['name'])) $updates[] = "name = '" . $conn->real_escape_string($input['name']) . "'";
        if (isset($input['category'])) $updates[] = "category = '" . $conn->real_escape_string($input['category']) . "'";
        if (isset($input['price'])) $updates[] = "price = " . floatval($input['price']);
        if (isset($input['cost_price'])) $updates[] = "cost_price = " . floatval($input['cost_price']);
        if (isset($input['stock'])) $updates[] = "stock = " . intval($input['stock']);
        if (isset($input['min_stock'])) $updates[] = "min_stock = " . intval($input['min_stock']);
        if (isset($input['sku'])) {
            $sku_val = trim($input['sku']);
            if (empty($sku_val)) $sku_val = 'SKU-' . str_pad($id, 5, '0', STR_PAD_LEFT);
            $updates[] = "sku = '" . $conn->real_escape_string($sku_val) . "'";
        }
        if (isset($input['barcode'])) {
            $bc_val = trim($input['barcode']);
            if (empty($bc_val)) $bc_val = 'BC-' . str_pad($id, 5, '0', STR_PAD_LEFT) . rand(10, 99);
            $updates[] = "barcode = '" . $conn->real_escape_string($bc_val) . "'";
        }
        if (isset($input['supplier_id'])) {
            $sup_id = !empty($input['supplier_id']) ? intval($input['supplier_id']) : "NULL";
            $updates[] = "supplier_id = $sup_id";
        }
        if (isset($input['status'])) {
            $status = $conn->real_escape_string((string) $input['status']);
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new Exception('Invalid product status');
            }
            $updates[] = "status = '$status'";
        }
        $uploadedImageUrl = uploadProductImage('product_image', $input['image_url'] ?? '');
        if ($uploadedImageUrl !== '') $updates[] = "image_url = '" . $conn->real_escape_string($uploadedImageUrl) . "'";
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $query = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = $id";
        
        if (!$conn->query($query)) {
            throw new Exception('Failed to update product');
        }
        
        // Record stock movement if stock was changed manually
        if (isset($input['stock'])) {
            $new_stock = intval($input['stock']);
            $old_stock = intval($old_product['stock']);
            if ($new_stock !== $old_stock) {
                $diff = $new_stock - $old_stock;
                $conn->query("INSERT INTO stock_movements (product_id, movement_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, user_id)
                              VALUES ($id, 'adjustment', $diff, $old_stock, $new_stock, 'product', $id, 'Manual stock edit via product form', " . intval($_SESSION['user_id']) . ")");
            }
        }
        
        // Log product update
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'UPDATE',
            entity_type: 'product',
            entity_id: $id,
            old_value: $old_product,
            new_value: array_filter([
                'name' => $input['name'] ?? null,
                'sku' => $input['sku'] ?? null,
                'barcode' => $input['barcode'] ?? null,
                'category' => $input['category'] ?? null,
                'price' => isset($input['price']) ? floatval($input['price']) : null,
                'cost_price' => isset($input['cost_price']) ? floatval($input['cost_price']) : null,
                'stock' => isset($input['stock']) ? intval($input['stock']) : null,
                'min_stock' => isset($input['min_stock']) ? intval($input['min_stock']) : null,
                'supplier_id' => isset($input['supplier_id']) ? $input['supplier_id'] : null,
                'status' => $input['status'] ?? null
            ], function ($v) { return $v !== null; }),
            description: "Updated product ID: $id"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Product updated']);
    }
    elseif ($method == 'DELETE') {
        requireCsrfToken(true);

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            throw new Exception('Access Denied: Only admins can delete products.');
        }
        
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            throw new Exception('Product ID required');
        }
        
        // Fetch product data before delete
        $del_result = $conn->query("SELECT name, category, price, stock, status FROM products WHERE id = $id");
        if (!$del_result || $del_result->num_rows === 0) {
            throw new Exception('Product not found');
        }
        $deleted_product = $del_result->fetch_assoc();

        $references = getProductReferenceSummary($conn, $id);
        if (!empty($references)) {
            throw new Exception('Cannot delete product with linked history (' . implode(', ', $references) . '). Deactivate it instead.');
        }
        
        if (!$conn->query("DELETE FROM products WHERE id = $id")) {
            throw new Exception('Failed to delete product');
        }
        
        // Log product deletion
        logActivity(
            user_id: $_SESSION['user_id'],
            action_type: 'DELETE',
            entity_type: 'product',
            entity_id: $id,
            old_value: $deleted_product,
            description: "Deleted product: {$deleted_product['name']}"
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Product deleted']);
    }
    else {
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
