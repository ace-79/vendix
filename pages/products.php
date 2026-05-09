<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
if (!canManageProducts()) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to access products.');
}

$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build filter query
$where = [];
$params = [];

// Search by name or ID
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(products.name LIKE ? OR products.id LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Filter by low stock
if (!empty($_GET['low_stock'])) {
    $where[] = "products.stock <= products.min_stock";
}

if (!empty($_GET['status'])) {
    $where[] = "products.status = ?";
    $params[] = $_GET['status'];
}

// Build final query
$query = "SELECT * FROM products";

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY name ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Fetch suppliers for the modal
$suppliers = [];
$supResult = $conn->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC");
if ($supResult) {
    while ($s = $supResult->fetch_assoc()) $suppliers[] = $s;
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

if ($exportCsv) {
    $rows = [];

    foreach ($products as $product) {
        $rows[] = [
            formatId($product['id'], 'product'),
            $product['name'],
            $product['category'] ?? '',
            number_format((float) $product['price'], 2, '.', ''),
            number_format((float) $product['cost_price'], 2, '.', ''),
            $product['stock'],
            $product['min_stock'],
            ($product['stock'] <= $product['min_stock']) ? 'Low Stock' : 'OK',
            $product['image_url'] ?? ''
        ];
    }

    outputCsvDownload(
        'products_' . date('Y-m-d') . '.csv',
        ['Product ID', 'Name', 'Category', 'Selling Price', 'Cost Price', 'Stock', 'Minimum Stock', 'Status', 'Image URL'],
        $rows
    );
}

include '../includes/header.php';
include '../includes/navbar.php';
?>
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Products Management</h1>
            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
            <button class="btn btn-primary" onclick="showAddProductModal()"><i class="fas fa-plus"></i> Add New Product</button>
            <?php endif; ?>
            
            <!-- Search and Filter Panel -->
            <div style="background-color: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb;">
                <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Search (Name or ID)</label>
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #555; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="low_stock" value="1" <?php echo (!empty($_GET['low_stock'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                            Low Stock Only
                        </label>
                    </div>
                    <div style="min-width: 180px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Availability</label>
                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">All</option>
                            <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <button type="submit" name="export" value="csv" class="btn btn-info"><i class="fas fa-file-csv"></i> Export CSV</button>
                        <a href="products.php" class="btn" style="padding: 8px 15px; background-color: #e5e7eb; color: #333; text-decoration: none; border-radius: 4px; text-align: center;"><i class="fas fa-redo"></i> Clear</a>
                    </div>
                </form>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>SKU / Barcode</th>
                        <th>Category</th>
                        <th>Selling Price</th>
                        <th>Cost Price</th>
                        <th>Stock</th>
                        <th>Availability</th>
                        <th>Inventory</th>
                        <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="<?php echo in_array($_SESSION['role'], ['admin', 'manager']) ? '10' : '9'; ?>" style="text-align: center; padding: 20px; color: #999;">No products found matching your criteria.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 50px; height: 50px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="color: #999; font-size: 1.5rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <small style="display: block; color: #666;">SKU: <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></small>
                            <small style="display: block; color: #888;">BC: <?php echo htmlspecialchars($p['barcode'] ?? 'N/A'); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                        <td>$<?php echo number_format($p['price'], 2); ?></td>
                        <td>$<?php echo number_format($p['cost_price'], 2); ?></td>
                        <td><?php echo $p['stock']; ?></td>
                        <td><span style="color: <?php echo ($p['status'] ?? 'active') === 'active' ? '#166534' : '#92400e'; ?>; font-weight: bold;"><?php echo ucfirst($p['status'] ?? 'active'); ?></span></td>
                        <td><span style="color: <?php echo $p['stock'] <= $p['min_stock'] ? '#dc2626' : '#16a34a'; ?>; font-weight: bold;"><?php echo $p['stock'] <= $p['min_stock'] ? '⚠ Low Stock' : '✓ OK'; ?></span></td>
                        <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <button class="btn btn-info" onclick="editProduct(this)" 
                                    data-id="<?php echo $p['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>" 
                                    data-sku="<?php echo htmlspecialchars($p['sku'] ?? ''); ?>" 
                                    data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? ''); ?>" 
                                    data-category="<?php echo htmlspecialchars($p['category'] ?? ''); ?>" 
                                    data-price="<?php echo $p['price']; ?>" 
                                    data-cost="<?php echo $p['cost_price']; ?>" 
                                    data-stock="<?php echo $p['stock']; ?>" 
                                    data-min-stock="<?php echo $p['min_stock']; ?>" 
                                    data-status="<?php echo htmlspecialchars($p['status'] ?? 'active'); ?>"
                                    data-supplier="<?php echo $p['supplier_id'] ?? ''; ?>" 
                                    data-image="<?php echo htmlspecialchars($p['image_url'] ?? ''); ?>"
                                    title="Edit"><i class="fas fa-edit"></i></button>
                                
                                <button class="btn btn-info" onclick="generateProductQR(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', '<?php echo htmlspecialchars($p['barcode'] ?: ($p['sku'] ?: $p['id'])); ?>')" title="Generate QR Code" style="background-color: #8B6F47; border-color: #8B6F47;"><i class="fas fa-qrcode"></i></button>
                                
                                <a href="stock.php?product_id=<?php echo $p['id']; ?>" class="btn" style="background: #6F4E37; color: white; padding: 6px 10px; border-radius: 4px;" title="Stock History"><i class="fas fa-history"></i></a>

                                <button class="btn" onclick="toggleProductStatus(<?php echo $p['id']; ?>, '<?php echo ($p['status'] ?? 'active') === 'active' ? 'inactive' : 'active'; ?>')" style="background: <?php echo ($p['status'] ?? 'active') === 'active' ? '#f59e0b' : '#16a34a'; ?>; color: white; padding: 6px 10px; border-radius: 4px;" title="<?php echo ($p['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate'; ?>"><i class="fas <?php echo ($p['status'] ?? 'active') === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i></button>
                                
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <button class="btn btn-danger" onclick="deleteProduct(<?php echo $p['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR View Modal -->
<div id="qrViewModal" class="modal">
    <div class="modal-content" style="max-width: 450px; text-align: center;">
        <span class="close" onclick="closeQRModal()">&times;</span>
        <h2 id="qrModalTitle">Product Label</h2>
        
        <div style="display: flex; flex-direction: column; align-items: center; padding: 20px; background: white; margin-bottom: 20px; border-radius: 8px; border: 1px solid #eee;">
            <!-- 2D QR Code -->
            <div id="qrcode-container" style="margin-bottom: 20px;"></div>
            
            <!-- 1D Barcode -->
            <svg id="barcode-container"></svg>
            
            <p id="qrModalSku" style="font-weight: bold; font-size: 1.1rem; color: #8B6F47; margin-top: 15px;"></p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button class="btn btn-primary" onclick="printQRCode()"><i class="fas fa-print"></i> Print Label</button>
            <button class="btn btn-success" onclick="downloadQRCode()"><i class="fas fa-download"></i> Download QR</button>
        </div>
    </div>
</div>
<div id="productModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeProductModal()">&times;</span>
        <h2 id="modalTitle">Add New Product</h2>
        <form id="productForm">
            <input type="hidden" id="productId">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" id="productName" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" id="productSku" placeholder="Stock Keeping Unit">
                </div>
                <div class="form-group">
                    <label>Barcode</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="productBarcode" placeholder="EAN/UPC">
                        <button type="button" class="btn btn-info" onclick="toggleQRScanner()" style="padding: 8px 12px; height: 100%;"><i class="fas fa-camera"></i></button>
                    </div>
                </div>
            </div>
            <div id="qr-reader" style="display: none; margin-bottom: 15px; border-radius: 8px; overflow: hidden; border: 2px solid #8B6F47;"></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" id="productCategory">
                </div>
                <div class="form-group">
                    <label>Default Supplier</label>
                    <select id="productSupplier">
                        <option value="">No Supplier</option>
                        <?php foreach($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Availability</label>
                <select id="productStatus">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Selling Price *</label>
                <input type="number" id="productPrice" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Cost Price *</label>
                <input type="number" id="productCost" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" id="productStock" required>
            </div>
            <div class="form-group">
                <label>Minimum Stock Level</label>
                <input type="number" id="productMinStock">
            </div>
            <div class="form-group">
                <label>Product Image URL</label>
                <div class="product-image-input-group">
                    <input type="text" id="productImage" placeholder="e.g., /Vendix/assets/images/uploads/products/laptop.jpg">
                    <button type="button" class="btn btn-info product-browse-btn" onclick="browseProductImage()">Browse</button>
                </div>
                <input type="file" id="productImageFile" accept="image/*" style="display: none;">
                <small style="color: #888;">Enter image path or URL (optional)</small>
            </div>
            <button type="submit" class="btn btn-primary">Save Product</button>
            <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.close { color: #aaa; float: right; font-size: 28px; cursor: pointer; }
.close:hover { color: black; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #E8D9C8; border-radius: 4px; font-size: 0.95rem; }
.form-group input:focus, .form-group select:focus { outline: none; border: 2px solid #8B6F47; }
.product-image-input-group { display: flex; gap: 10px; align-items: center; }
.product-image-input-group input { flex: 1; }
.product-browse-btn { white-space: nowrap; }
.btn-secondary { background-color: #888; color: white; margin-left: 10px; }
.btn-secondary:hover { background-color: #666; }
.btn-danger { background-color: #dc2626; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px; }
.btn-danger:hover { background-color: #b91c1c; }
</style>

<script>
function showAddProductModal() {
    document.getElementById('productId').value = '';
    document.getElementById('productForm').reset();
    document.getElementById('productImageFile').value = '';
    document.getElementById('productStatus').value = 'active';
    document.getElementById('modalTitle').textContent = 'Add New Product';
    document.getElementById('productModal').style.display = 'block';
}

function editProduct(button) {
    document.getElementById('productId').value = button.dataset.id;
    document.getElementById('productName').value = button.dataset.name || '';
    document.getElementById('productSku').value = button.dataset.sku || '';
    document.getElementById('productBarcode').value = button.dataset.barcode || '';
    document.getElementById('productCategory').value = button.dataset.category || '';
    document.getElementById('productSupplier').value = button.dataset.supplier || '';
    document.getElementById('productStatus').value = button.dataset.status || 'active';
    document.getElementById('productPrice').value = button.dataset.price;
    document.getElementById('productCost').value = button.dataset.cost;
    document.getElementById('productStock').value = button.dataset.stock;
    document.getElementById('productMinStock').value = button.dataset.minStock;
    document.getElementById('productImage').value = button.dataset.image || '';
    document.getElementById('productImageFile').value = '';
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('productModal').style.display = 'block';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
    document.getElementById('productImageFile').value = '';
}

function browseProductImage() {
    document.getElementById('productImageFile').click();
}

document.getElementById('productImageFile').addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) {
        return;
    }

    document.getElementById('productImage').value = `/Vendix/assets/images/uploads/products/${file.name}`;
});

window.onclick = function(e) {
    const modal = document.getElementById('productModal');
    if (e.target == modal) modal.style.display = 'none';
}

document.getElementById('productForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('productId').value;
    const formData = new FormData();
    formData.append('name', document.getElementById('productName').value);
    formData.append('sku', document.getElementById('productSku').value);
    formData.append('barcode', document.getElementById('productBarcode').value);
    formData.append('category', document.getElementById('productCategory').value);
    formData.append('supplier_id', document.getElementById('productSupplier').value);
    formData.append('status', document.getElementById('productStatus').value);
    formData.append('price', parseFloat(document.getElementById('productPrice').value));
    formData.append('cost_price', parseFloat(document.getElementById('productCost').value));
    formData.append('stock', parseInt(document.getElementById('productStock').value));
    formData.append('min_stock', parseInt(document.getElementById('productMinStock').value || '0'));
    formData.append('image_url', document.getElementById('productImage').value);

    const selectedFile = document.getElementById('productImageFile').files[0];
    if (selectedFile) {
        formData.append('product_image', selectedFile);
    }

    if (id) {
        formData.append('id', parseInt(id));
        formData.append('_method', 'PUT');
    }
    
    try {
        const response = await fetch('../api/products.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.status === 'success') {
            vendixNotifyAndReload(result.message, 'success');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

async function deleteProduct(id) {
    const confirmed = await vendixConfirm('Delete this product? This cannot be undone.', {
        title: 'Delete Product',
        acceptLabel: 'Delete'
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(`../api/products.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.status === 'success') {
            vendixNotifyAndReload(result.message, 'success');
            return;
        }

        alert('Error: ' + result.message);
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function toggleProductStatus(id, status) {
    const isDeactivating = status === 'inactive';
    const confirmed = await vendixConfirm(
        isDeactivating ? 'Deactivate this product? It will remain in history but should no longer be used for new sales or orders.' : 'Activate this product again?',
        {
            title: isDeactivating ? 'Deactivate Product' : 'Activate Product',
            acceptLabel: isDeactivating ? 'Deactivate' : 'Activate'
        }
    );

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('../api/products.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id, status })
        });
        const result = await response.json();
        if (result.status === 'success') {
            vendixNotifyAndReload(result.message, 'success');
            return;
        }

        alert('Error: ' + result.message);
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

let html5QrCode = null;

async function toggleQRScanner() {
    const scannerDiv = document.getElementById('qr-reader');
    
    if (scannerDiv.style.display === 'block') {
        stopQRScanner();
        return;
    }
    
    scannerDiv.style.display = 'block';
    
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("qr-reader");
    }
    
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };
    
    try {
        await html5QrCode.start(
            { facingMode: "environment" }, 
            config,
            (decodedText, decodedResult) => {
                // Success
                document.getElementById('productBarcode').value = decodedText;
                vendixNotify('Code scanned: ' + decodedText, 'success');
                stopQRScanner();
            }
        );
    } catch (err) {
        alert('Error starting camera: ' + err);
        scannerDiv.style.display = 'none';
    }
}

function stopQRScanner() {
    const scannerDiv = document.getElementById('qr-reader');
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().then(() => {
            scannerDiv.style.display = 'none';
        });
    } else {
        scannerDiv.style.display = 'none';
    }
}

// Ensure scanner stops when modal closes
const originalCloseModal = closeProductModal;
closeProductModal = function() {
    stopQRScanner();
    originalCloseModal();
};

function generateProductQR(id, name, code) {
    // Clear previous
    const qrContainer = document.getElementById('qrcode-container');
    qrContainer.innerHTML = '';
    
    document.getElementById('qrModalTitle').textContent = name;
    document.getElementById('qrModalSku').textContent = 'Code: ' + code;
    
    // Generate 2D QR Code
    new QRCode(qrContainer, {
        text: code,
        width: 150,
        height: 150,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    
    // Generate 1D Barcode
    JsBarcode("#barcode-container", code, {
        format: "CODE128",
        width: 2,
        height: 50,
        displayValue: false
    });
    
    document.getElementById('qrViewModal').style.display = 'block';
}

function closeQRModal() {
    document.getElementById('qrViewModal').style.display = 'none';
}

function printQRCode() {
    const name = document.getElementById('qrModalTitle').textContent;
    const sku = document.getElementById('qrModalSku').textContent;
    const qrImage = document.querySelector('#qrcode-container img').src;
    
    // Get the SVG barcode and convert to string
    const svgElement = document.getElementById('barcode-container');
    const serializer = new XMLSerializer();
    let svgString = serializer.serializeToString(svgElement);
    
    const printWin = window.open('', '', 'width=400,height=600');
    printWin.document.write(`
        <html>
            <head>
                <title>Print Label - ${name}</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                    .label { border: 2px dashed #ccc; padding: 20px; border-radius: 8px; width: 300px; margin: 0 auto; }
                    h2 { margin: 0 0 15px 0; font-size: 18px; }
                    .qr-img { width: 150px; height: 150px; margin: 10px 0; }
                    .barcode-container { margin: 15px 0; }
                    .sku { font-weight: bold; font-size: 16px; margin-top: 10px; }
                </style>
            </head>
            <body onload="setTimeout(function(){ window.print(); window.close(); }, 500);">
                <div class="label">
                    <h2>${name}</h2>
                    <img class="qr-img" src="${qrImage}" alt="QR Code">
                    <div class="barcode-container">
                        ${svgString}
                    </div>
                    <div class="sku">${sku}</div>
                </div>
            </body>
        </html>
    `);
    printWin.document.close();
}

function downloadQRCode() {
    const name = document.getElementById('qrModalTitle').textContent;
    const originalCanvas = document.querySelector('#qrcode-container canvas');
    
    if (!originalCanvas) {
        alert("QR Code not fully generated yet.");
        return;
    }
    
    // Scanners need a "Quiet Zone" (white padding) around the QR code
    const padding = 20; 
    const newCanvas = document.createElement('canvas');
    newCanvas.width = originalCanvas.width + (padding * 2);
    newCanvas.height = originalCanvas.height + (padding * 2);
    
    const ctx = newCanvas.getContext('2d');
    
    // Fill white background for padding
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, newCanvas.width, newCanvas.height);
    
    // Draw original QR code in the center
    ctx.drawImage(originalCanvas, padding, padding);
    
    const link = document.createElement('a');
    link.href = newCanvas.toDataURL('image/png');
    link.download = `QR_${name.replace(/[^a-z0-9]/gi, '_')}.png`;
    link.click();
}
</script>


