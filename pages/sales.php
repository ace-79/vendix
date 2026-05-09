<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
// Permission check
if (!hasPermission('view_sales')) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to access sales.');
}

$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build filter query
$where = [];
$params = [];

// Search by Sale ID or Customer Name
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(s.id LIKE ? OR c.name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Filter by date range
if (!empty($_GET['date_from'])) {
    $where[] = "DATE(s.sale_date) >= ?";
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = "DATE(s.sale_date) <= ?";
    $params[] = $_GET['date_to'];
}

// Filter by payment status
if (!empty($_GET['payment_status'])) {
    $where[] = "s.payment_status = ?";
    $params[] = $_GET['payment_status'];
}

// Filter by cashier
if (!empty($_GET['cashier'])) {
    $where[] = "s.user_id = ?";
    $params[] = $_GET['cashier'];
}

// Build final query
$query = "SELECT s.*, c.name as customer_name, u.username FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          LEFT JOIN users u ON s.user_id = u.id";
          
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY s.sale_date DESC";

if (!$exportCsv) {
    $query .= " LIMIT 100";
}

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

if ($exportCsv) {
    $rows = [];

    foreach ($sales as $sale) {
        $rows[] = [
            formatId($sale['id'], 'sale'),
            $sale['sale_date'],
            $sale['customer_name'] ?? 'Walk-in',
            number_format((float) $sale['total_amount'], 2, '.', ''),
            $sale['payment_status'],
            $sale['payment_method'],
            $sale['username'] ?? ''
        ];
    }

    outputCsvDownload(
        'sales_' . date('Y-m-d') . '.csv',
        ['Sale ID', 'Sale Date', 'Customer', 'Total Amount', 'Payment Status', 'Payment Method', 'Cashier'],
        $rows
    );
}

// Get cashiers for filter dropdown
$cashiersResult = $conn->query("SELECT DISTINCT u.id, u.username FROM users u 
                               WHERE u.role IN ('cashier', 'admin') 
                               ORDER BY u.username");
$cashiers = [];
while ($row = $cashiersResult->fetch_assoc()) {
    $cashiers[] = $row;
}

// Get products for dropdown
$productsResult = $conn->query("SELECT id, name, price, cost_price, stock, sku, barcode FROM products WHERE status = 'active' ORDER BY name");
$products = [];
$productsJson = [];
while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
    $productsJson[] = [
        'id' => intval($row['id']),
        'name' => $row['name'],
        'price' => floatval($row['price']),
        'cost_price' => floatval($row['cost_price']),
        'stock' => intval($row['stock']),
        'sku' => $row['sku'] ?? '',
        'barcode' => $row['barcode'] ?? ''
    ];
}

// Get customers for dropdown
$customersResult = $conn->query("SELECT id, name FROM customers ORDER BY name");
$customers = [];
while ($row = $customersResult->fetch_assoc()) {
    $customers[] = $row;
}

// Stats for status badges
$statsResult = $conn->query("SELECT payment_status, COUNT(*) as count FROM sales GROUP BY payment_status");
$stats = ['Paid' => 0, 'Pending' => 0, 'Cancelled' => 0];
while ($row = $statsResult->fetch_assoc()) {
    if (isset($stats[$row['payment_status']])) {
        $stats[$row['payment_status']] = (int)$row['count'];
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Sales Management</h1>
            <?php if ($_SESSION['role'] === 'cashier'): ?>
            <button class="btn btn-primary" onclick="showNewSaleModal()"><i class="fas fa-plus"></i> Create New Sale</button>
            <?php endif; ?>
            
            <!-- Search and Filter Panel -->
            <div class="sales-filter-panel">
                <form method="GET" class="sales-filter-form">
                    <div class="sales-filter-field">
                        <label class="sales-filter-label">Search (Sale ID or Customer)</label>
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="sales-filter-input">
                    </div>
                    <div class="sales-filter-field">
                        <label class="sales-filter-label">From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>" class="sales-filter-input">
                    </div>
                    <div class="sales-filter-field">
                        <label class="sales-filter-label">To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>" class="sales-filter-input">
                    </div>
                    <div class="sales-filter-field">
                        <label class="sales-filter-label">Payment Status</label>
                        <select name="payment_status" class="sales-filter-input">
                            <option value="">All Statuses</option>
                            <option value="Paid" <?php echo ($_GET['payment_status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Pending" <?php echo ($_GET['payment_status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Cancelled" <?php echo ($_GET['payment_status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="sales-filter-field">
                        <label class="sales-filter-label">Cashier</label>
                        <select name="cashier" class="sales-filter-input">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?php echo $cashier['id']; ?>" <?php echo ($_GET['cashier'] ?? '') === $cashier['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cashier['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sales-filter-actions sales-filter-actions-right">
                        <button type="submit" class="btn sales-toolbar-btn sales-toolbar-btn-primary"><i class="fas fa-search"></i><span>Filter</span></button>
                        <button type="submit" name="export" value="csv" class="btn sales-toolbar-btn sales-toolbar-btn-export"><i class="fas fa-file-csv"></i><span>Export CSV</span></button>
                        <a href="sales.php" class="btn sales-toolbar-btn sales-toolbar-btn-clear"><i class="fas fa-rotate-right"></i><span>Clear</span></a>
                    </div>
                </form>
            </div>

            <!-- Status Summary Badges -->
            <div class="sales-status-badges">
                <div class="sales-status-card sales-status-paid"
                     onclick="document.querySelector('[name=payment_status]').value='Paid'; document.querySelector('.sales-filter-form').submit();">
                    <div class="sales-status-card-inner">
                        <div class="sales-status-icon">✅</div>
                        <div>
                            <div class="sales-status-label">Paid</div>
                            <div class="sales-status-count"><?php echo $stats['Paid']; ?></div>
                        </div>
                    </div>
                    <div class="sales-status-hint">Click to filter</div>
                </div>
                <div class="sales-status-card sales-status-pending"
                     onclick="document.querySelector('[name=payment_status]').value='Pending'; document.querySelector('.sales-filter-form').submit();">
                    <div class="sales-status-card-inner">
                        <div class="sales-status-icon">⏳</div>
                        <div>
                            <div class="sales-status-label">Pending</div>
                            <div class="sales-status-count"><?php echo $stats['Pending']; ?></div>
                        </div>
                    </div>
                    <div class="sales-status-hint">Click to filter</div>
                </div>
                <div class="sales-status-card sales-status-cancelled"
                     onclick="document.querySelector('[name=payment_status]').value='Cancelled'; document.querySelector('.sales-filter-form').submit();">
                    <div class="sales-status-card-inner">
                        <div class="sales-status-icon">❌</div>
                        <div>
                            <div class="sales-status-label">Cancelled</div>
                            <div class="sales-status-count"><?php echo $stats['Cancelled']; ?></div>
                        </div>
                    </div>
                    <div class="sales-status-hint">Click to filter</div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Cashier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #999;">No sales found matching your criteria.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><?php echo formatId($s['id'], 'sale'); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($s['sale_date'])); ?></td>
                        <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in'); ?></td>
                        <td>$<?php echo number_format($s['total_amount'], 2); ?></td>
                        <td><span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo $s['payment_status'] == 'Paid' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $s['payment_status'] == 'Paid' ? '#065f46' : '#92400e'; ?>;"><?php echo $s['payment_status']; ?></span></td>
                        <td><?php echo htmlspecialchars($s['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($s['username']); ?></td>
                        <td>
                            <div class="sales-row-actions">
                            <button class="btn sales-action-btn sales-action-btn-view" onclick="viewSale(<?php echo $s['id']; ?>)" title="View sale"><i class="fas fa-eye"></i></button>
                            <?php if (($_SESSION['role'] === 'cashier' || $_SESSION['role'] === 'admin') && $s['payment_status'] !== 'Cancelled'): ?>
                            <button class="btn sales-action-btn sales-action-btn-edit" onclick="editSale(<?php echo $s['id']; ?>)" title="Edit sale"><i class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Sale Modal -->
<div id="saleModal" class="modal">
    <div class="modal-content" style="max-width: 900px; overflow-y: auto;">
        <span class="close" onclick="closeSaleModal()">&times;</span>
        <h2>Create New Sale</h2>
        <form id="saleForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Customer (Optional)</label>
                    <select id="saleCustomer">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select id="salePaymentMethod">
                        <option>Cash</option>
                        <option>Card</option>
                        <option>Bank Transfer</option>
                        <option>Cheque</option>
                    </select>
                </div>
            </div>

            <!-- Barcode Scanner Input -->
            <div class="form-group" style="margin-top: 10px; background: #fef3c7; padding: 15px; border-radius: 10px; border: 1px solid #fde68a;">
                <label style="color: #92400e;"><i class="fas fa-barcode"></i> Barcode Scanner / SKU Search</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="barcodeScanner" placeholder="Scan barcode or type SKU..." style="flex: 1; border-color: #f59e0b;" autocomplete="off">
                    <button type="button" class="btn btn-warning" onclick="handleBarcodeSearch()"><i class="fas fa-search"></i></button>
                </div>
                <small style="color: #b45309;">Focus this field to use a physical scanner</small>
            </div>
            
            <h3 style="margin-top: 20px;">Add Products</h3>
            <div style="overflow-x: auto;">
                <table class="table" style="margin-bottom: 20px; width: 100%; min-width: 600px;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 15%;">Price</th>
                            <th style="width: 15%;">Qty</th>
                            <th style="width: 20%;">Subtotal</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="saleItemsBody">
                    </tbody>
                </table>
            </div>
            
            <button type="button" class="btn btn-info" onclick="addSaleItem()"><i class="fas fa-plus"></i> Add Product</button>
            
            <div style="margin-top: 20px; text-align: right;">
                <div style="margin-bottom: 10px; font-size: 14px; color: #666;">
                    Subtotal: $<span id="saleSubtotalDisplay">0.00</span>
                </div>
                <div style="margin-bottom: 10px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                    <label style="margin: 0;">Discount:</label>
                    $<input type="number" id="saleDiscount" value="0.00" min="0" step="0.01" style="width: 80px; padding: 5px; text-align: right;" onchange="calculateTotal()">
                </div>
                <h3>Total: $<span id="saleTotalDisplay">0.00</span></h3>
                <input type="hidden" id="saleTotal" value="0">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Save Sale</button>
        </form>
    </div>
</div>

<!-- View Sale Modal -->
<div id="viewSaleModal" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Sales Details</h2>
            <span class="close" onclick="closeViewSaleModal()">&times;</span>
        </div>
        
        <!-- Sale Info Row -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
            <div>
                <label style="font-size: 12px; color: #999; text-transform: uppercase;">Sale ID</label>
                <div style="font-size: 18px; font-weight: 600; color: #333;" id="viewSaleId"></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #999; text-transform: uppercase;">Date & Time</label>
                <div style="font-size: 16px; color: #333;" id="viewSaleDate"></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #999; text-transform: uppercase;">Status</label>
                <div id="viewSaleStatus" style="display: inline-block; padding: 6px 12px; border-radius: 4px; font-weight: 600; margin-top: 5px;"></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #999; text-transform: uppercase;">Cashier</label>
                <div style="font-size: 16px; color: #333;" id="viewSaleCashier"></div>
            </div>
        </div>
        
        <!-- Customer Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <div>
                <h4 style="color: #666; margin-bottom: 10px; font-size: 14px;">CUSTOMER</h4>
                <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;" id="viewSaleCustomer"></div>
                <div style="font-size: 13px; color: #666; margin-bottom: 4px;" id="viewSaleCustomerPhone"></div>
                <div style="font-size: 13px; color: #666;" id="viewSaleCustomerEmail"></div>
            </div>
            <div>
                <h4 style="color: #666; margin-bottom: 10px; font-size: 14px;">PAYMENT METHOD</h4>
                <div style="font-size: 16px; font-weight: 600;" id="viewSalePaymentMethod"></div>
            </div>
        </div>
        
        <!-- Products Table -->
        <div style="overflow-x: auto; margin-bottom: 30px;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #8B6F47;">
                        <th style="text-align: left; padding: 12px 16px; border: none; color: white; font-weight: 600; font-size: 14px;">Product Name</th>
                        <th style="text-align: center; padding: 12px 16px; border: none; color: white; font-weight: 600; font-size: 14px; width: 80px;">Qty</th>
                        <th style="text-align: right; padding: 12px 16px; border: none; color: white; font-weight: 600; font-size: 14px; width: 100px;">Price</th>
                        <th style="text-align: right; padding: 12px 16px; border: none; color: white; font-weight: 600; font-size: 14px; width: 100px;">Total</th>
                    </tr>
                </thead>
                <tbody id="viewSaleItemsBody">
                </tbody>
            </table>
        </div>
        
        <!-- Summary Section -->
        <div style="background-color: #f9fafb; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #666;">
                <span>Subtotal:</span>
                <span id="viewSaleSubtotal" style="font-weight: 600;">$0.00</span>
            </div>
            <div id="viewSaleDiscountRow" style="display: none; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #dc2626;">
                <span>Discount:</span>
                <span id="viewSaleDiscount" style="font-weight: 600;">-$0.00</span>
            </div>
            <div style="border-top: 2px solid #e5e7eb; padding-top: 12px; display: flex; justify-content: space-between; font-weight: 700; font-size: 18px; color: #333;">
                <span>Total Amount:</span>
                <span id="viewSaleTotalAmount" style="color: #8B6F47; font-size: 20px;">$0.00</span>
            </div>
        </div>
        
        <!-- Footer Actions -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <button type="button" class="btn btn-default" onclick="closeViewSaleModal()" style="background-color: #e5e7eb; color: #333; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Close</button>
            <button type="button" class="btn btn-primary" onclick="printSaleDetails()" style="background-color: #8B6F47; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
</div>

<!-- Edit Sale Modal -->
<div id="editSaleModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeEditSaleModal()">&times;</span>
        <h2>Edit Sale</h2>
        <form id="editSaleForm">
            <input type="hidden" id="editSaleId" value="">
            
            <div class="form-group">
                <label>Sale ID:</label>
                <input type="text" id="editSaleIdDisplay" readonly style="background-color: #f0f0f0;">
            </div>
            
            <div class="form-group">
                <label>Total Amount:</label>
                <input type="text" id="editSaleTotalAmount" readonly style="background-color: #f0f0f0;">
            </div>
            
            <div class="form-group">
                <label>Payment Status</label>
                <select id="editSalePaymentStatus">
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Payment Method</label>
                <select id="editSalePaymentMethod">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Cheque">Cheque</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Update Sale</button>
        </form>
    </div>
</div>

<!-- View Sale Modal Styles and Scripts -->
<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background-color: #fff; margin: 20px auto; padding: 30px; border-radius: 8px; width: 95%; max-width: 900px; max-height: 90vh; overflow-y: auto; }
.close { color: #aaa; float: right; font-size: 28px; cursor: pointer; }
.sales-filter-panel { background: linear-gradient(180deg, #fbfbfb 0%, #f5f3ef 100%); padding: 24px; border-radius: 18px; margin: 20px 0; border: 1px solid #e5ddd4; box-shadow: inset 0 1px 0 rgba(255,255,255,0.7); }
.sales-filter-form { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; align-items: end; }
.sales-filter-field { min-width: 0; }
.sales-filter-label { display: block; margin-bottom: 8px; font-weight: 700; color: #6a5242; font-size: 0.95rem; }
.sales-filter-input { width: 100%; height: 44px; padding: 0 14px; border: 1px solid #d8cec2; border-radius: 10px; background-color: #fff; font-size: 0.96rem; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
.sales-filter-input:focus { outline: none; border-color: #8B6F47; box-shadow: 0 0 0 4px rgba(139, 111, 71, 0.12); }
.sales-filter-actions { display: flex; gap: 12px; align-items: center; }
.sales-filter-actions-right { grid-column: 3 / 5; justify-content: flex-end; align-self: end; }
.sales-toolbar-btn { min-width: 138px; height: 48px; margin-bottom: 0; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border-radius: 12px; font-weight: 700; text-decoration: none; }
.sales-toolbar-btn i { font-size: 0.95rem; }
.sales-toolbar-btn-primary { background: linear-gradient(135deg, #7b5738 0%, #9a744c 100%); color: #fff; box-shadow: 0 10px 22px rgba(123, 87, 56, 0.24); }
.sales-toolbar-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(123, 87, 56, 0.28); }
.sales-toolbar-btn-export { background: linear-gradient(135deg, #d3beaa 0%, #bfa489 100%); color: #fff; box-shadow: 0 10px 22px rgba(160, 130, 109, 0.22); }
.sales-toolbar-btn-export:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(160, 130, 109, 0.28); }
.sales-toolbar-btn-clear { background: #eceff3; color: #5a6572; box-shadow: none; border: 1px solid #d7dce3; }
.sales-toolbar-btn-clear:hover { background: #e3e8ee; transform: translateY(-2px); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #E8D9C8; border-radius: 4px; }
.btn-danger { background-color: #dc2626; color: white; padding: 4px 8px; border: none; cursor: pointer; border-radius: 4px; }
.btn-warning { background-color: #f59e0b; color: white; }
.btn-default { background-color: #e5e7eb; color: #333; padding: 8px 16px; border: none; cursor: pointer; border-radius: 4px; }
.sales-row-actions { display: flex; align-items: center; gap: 10px; }
.sales-action-btn { width: 48px; height: 48px; margin-bottom: 0; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; }
.sales-action-btn-view { background: linear-gradient(135deg, #cab195 0%, #b89b7c 100%); color: #fff; box-shadow: 0 10px 18px rgba(160, 130, 109, 0.18); }
.sales-action-btn-edit { background: linear-gradient(135deg, #f4a100 0%, #ffb11b 100%); color: #fff; box-shadow: 0 10px 18px rgba(245, 158, 11, 0.22); }
.sales-action-btn:hover { transform: translateY(-2px); }
@media print {
    body { background-color: white; }
    .sidebar, .navbar, .btn, .modal, .close { display: none !important; }
}
@media (max-width: 768px) {
    .sales-filter-panel { padding: 18px; border-radius: 16px; }
    .sales-filter-form { grid-template-columns: 1fr; }
    .sales-filter-actions-right { grid-column: auto; justify-content: stretch; }
    .sales-filter-actions { flex-direction: column; }
    .sales-toolbar-btn { width: 100%; }
}

/* Status Summary Badges */
.sales-status-badges { display: flex; gap: 16px; margin: 18px 0 22px 0; }
.sales-status-card { flex: 1; border-radius: 14px; padding: 18px 22px 12px 22px; cursor: pointer; transition: transform 0.18s, box-shadow 0.18s; user-select: none; }
.sales-status-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.13); }
.sales-status-card-inner { display: flex; align-items: center; gap: 14px; }
.sales-status-icon { font-size: 28px; }
.sales-status-label { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.sales-status-count { font-size: 34px; font-weight: 800; line-height: 1; }
.sales-status-hint { font-size: 11px; margin-top: 8px; opacity: 0.7; }
.sales-status-paid { background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-left: 5px solid #10b981; }
.sales-status-paid .sales-status-label, .sales-status-paid .sales-status-count { color: #065f46; }
.sales-status-paid .sales-status-hint { color: #059669; }
.sales-status-pending { background: linear-gradient(135deg, #fef3c7, #fde68a); border-left: 5px solid #f59e0b; }
.sales-status-pending .sales-status-label, .sales-status-pending .sales-status-count { color: #92400e; }
.sales-status-pending .sales-status-hint { color: #b45309; }
.sales-status-cancelled { background: linear-gradient(135deg, #fee2e2, #fecaca); border-left: 5px solid #ef4444; }
.sales-status-cancelled .sales-status-label, .sales-status-cancelled .sales-status-count { color: #991b1b; }
.sales-status-cancelled .sales-status-hint { color: #dc2626; }
@media (max-width: 600px) {
    .sales-status-badges { flex-direction: column; }
}
</style>

<script>
let saleItems = [];
let productsData = <?php echo json_encode($products); ?>;

function showNewSaleModal() {
    saleItems = [];
    document.getElementById('saleForm').reset();
    document.getElementById('saleItemsBody').innerHTML = '';
    document.getElementById('saleTotal').value = 0;
    document.getElementById('saleDiscount').value = '0.00';
    document.getElementById('saleSubtotalDisplay').textContent = '0.00';
    document.getElementById('saleTotalDisplay').textContent = '0.00';
    document.getElementById('saleModal').style.display = 'block';
    
    // Focus barcode scanner
    setTimeout(() => {
        document.getElementById('barcodeScanner').focus();
    }, 100);
}

function handleBarcodeSearch() {
    const code = document.getElementById('barcodeScanner').value.trim();
    if (!code) return;

    const product = productsData.find(p => p.barcode === code || p.sku === code);
    if (product) {
        // Check if product already in list
        let found = false;
        document.querySelectorAll('.productSelect').forEach(select => {
            if (parseInt(select.value) === product.id) {
                const qtyInput = select.closest('tr').querySelector('.itemQuantity');
                qtyInput.value = parseInt(qtyInput.value) + 1;
                updateItemSubtotal(qtyInput);
                found = true;
            }
        });

        if (!found) {
            addSaleItem(product.id);
        }
        
        document.getElementById('barcodeScanner').value = '';
        document.getElementById('barcodeScanner').focus();
    } else {
        alert('Product not found: ' + code);
    }
}

// Add event listener for enter key on barcode scanner
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && document.activeElement.id === 'barcodeScanner') {
        e.preventDefault();
        handleBarcodeSearch();
    }
});

function closeSaleModal() {
    document.getElementById('saleModal').style.display = 'none';
}

function closeViewSaleModal() {
    document.getElementById('viewSaleModal').style.display = 'none';
}

function closeEditSaleModal() {
    document.getElementById('editSaleModal').style.display = 'none';
}

function htmlEscape(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatDatetime(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('en-US', options);
}

function addSaleItem(productId = null) {
    const rowId = 'row_' + Date.now();
    const row = `
        <tr id="${rowId}">
            <td>
                <select class="productSelect" onchange="updateItemPrice(this)">
                    <option value="">Select Product</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?php echo $p['id']; ?>" ${productId == <?php echo $p['id']; ?> ? 'selected' : ''}>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" class="itemPrice" value="0" step="0.01" readonly style="width: 100%;"></td>
            <td><input type="number" class="itemQuantity" value="1" min="1" onchange="updateItemSubtotal(this)" style="width: 100%;"></td>
            <td><span class="itemSubtotal">$0.00</span></td>
            <td><button type="button" class="btn btn-danger" onclick="this.parentElement.parentElement.remove(); calculateTotal();">×</button></td>
        </tr>
    `;
    document.getElementById('saleItemsBody').insertAdjacentHTML('beforeend', row);
    
    // If productId was passed, trigger price update
    if (productId) {
        const select = document.querySelector(`#${rowId} .productSelect`);
        updateItemPrice(select);
    }
}

function updateItemPrice(select) {
    const selectValue = select.value;
    const product = productsData.find(p => parseInt(p.id) === parseInt(selectValue));
    
    if (product) {
        const row = select.closest('tr');
        const priceInput = row.querySelector('.itemPrice');
        const price = parseFloat(product.price);
        priceInput.value = price;
        updateItemSubtotal(row.querySelector('.itemQuantity'));
    }
}

function updateItemSubtotal(input) {
    const row = input.closest('tr');
    const price = parseFloat(row.querySelector('.itemPrice').value) || 0;
    const qty = parseInt(input.value) || 1;
    const subtotal = price * qty;
    row.querySelector('.itemSubtotal').textContent = '$' + subtotal.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('#saleItemsBody tr').forEach(row => {
        const subtotalText = row.querySelector('.itemSubtotal').textContent;
        subtotal += parseFloat(subtotalText.replace('$', ''));
    });
    
    let discount = parseFloat(document.getElementById('saleDiscount').value);
    if (isNaN(discount) || discount < 0) discount = 0;
    
    let total = subtotal - discount;
    if (total < 0) total = 0;
    
    document.getElementById('saleSubtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('saleTotal').value = total.toFixed(2);
    document.getElementById('saleTotalDisplay').textContent = total.toFixed(2);
}

window.onclick = function(e) {
    const saleModal = document.getElementById('saleModal');
    if (e.target == saleModal) saleModal.style.display = 'none';
    
    const viewSaleModal = document.getElementById('viewSaleModal');
    if (e.target == viewSaleModal) viewSaleModal.style.display = 'none';
    
    const editSaleModal = document.getElementById('editSaleModal');
    if (e.target == editSaleModal) editSaleModal.style.display = 'none';
}

document.getElementById('saleForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#saleItemsBody tr').forEach(row => {
        const select = row.querySelector('.productSelect');
        const productId = parseInt(select.value);
        const product = productsData.find(p => parseInt(p.id) === productId);
        if (product) {
            items.push({
                product_id: product.id,
                quantity: parseInt(row.querySelector('.itemQuantity').value),
                unit_price: parseFloat(row.querySelector('.itemPrice').value),
                subtotal: parseFloat(row.querySelector('.itemSubtotal').textContent.replace('$', ''))
            });
        }
    });
    
    if (items.length === 0) {
        alert('Please add at least one product');
        return;
    }
    
    const discount = parseFloat(document.getElementById('saleDiscount').value) || 0;
    
    const data = {
        customer_id: document.getElementById('saleCustomer').value || null,
        total_amount: parseFloat(document.getElementById('saleTotal').value),
        discount_amount: discount,
        payment_status: 'Pending',
        payment_method: document.getElementById('salePaymentMethod').value,
        items: items
    };
    
    try {
        const response = await fetch('../api/sales.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const text = await response.text();
        if (!text) {
            alert('Error: Empty response from server');
            return;
        }
        const result = JSON.parse(text);
        if (result.status === 'success') {
            vendixNotifyAndReload('Sale created successfully!', 'success');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

async function viewSale(id) {
    try {
        const response = await fetch('../api/sales.php?id=' + id);
        const text = await response.text();
        if (!text) {
            alert('Error: Empty response from server');
            return;
        }
        const result = JSON.parse(text);
        if (result.status === 'success') {
            const sale = result.data;
            const items = result.items || [];
            
            document.getElementById('viewSaleId').textContent = 'SALE-' + String(sale.id).padStart(4, '0');
            document.getElementById('viewSaleDate').textContent = formatDatetime(sale.sale_date);
            document.getElementById('viewSaleCashier').textContent = sale.username || 'N/A';
            
            const statusBadge = document.getElementById('viewSaleStatus');
            const statusColor = sale.payment_status == 'Paid' ? '#d1fae5' : (sale.payment_status == 'Cancelled' ? '#fee2e2' : '#fef3c7');
            const statusTextColor = sale.payment_status == 'Paid' ? '#065f46' : (sale.payment_status == 'Cancelled' ? '#991b1b' : '#92400e');
            statusBadge.style.backgroundColor = statusColor;
            statusBadge.style.color = statusTextColor;
            statusBadge.textContent = sale.payment_status;
            
            const customerName = sale.customer_name || 'Walk-in Customer';
            document.getElementById('viewSaleCustomer').textContent = customerName;
            document.getElementById('viewSaleCustomerPhone').textContent = sale.customer_phone ? 'Phone: ' + sale.customer_phone : '';
            document.getElementById('viewSaleCustomerEmail').textContent = sale.customer_email ? 'Email: ' + sale.customer_email : '';
            document.getElementById('viewSalePaymentMethod').textContent = sale.payment_method || 'N/A';
            
            const itemsBody = document.getElementById('viewSaleItemsBody');
            itemsBody.innerHTML = '';
            let subtotal = 0;
            
            items.forEach(item => {
                const itemTotal = parseFloat(item.unit_price) * parseInt(item.quantity);
                subtotal += itemTotal;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: left; color: #333; font-size: 14px;">${htmlEscape(item.product_name)}</td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: center; color: #666; font-size: 14px;">${item.quantity}</td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #666; font-size: 14px;">$${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #333; font-weight: 600; font-size: 14px;">$${itemTotal.toFixed(2)}</td>
                `;
                itemsBody.appendChild(row);
            });
            
            document.getElementById('viewSaleSubtotal').textContent = '$' + subtotal.toFixed(2);
            const discount = parseFloat(sale.discount_amount) || 0;
            if (discount > 0) {
                document.getElementById('viewSaleDiscountRow').style.display = 'flex';
                document.getElementById('viewSaleDiscount').textContent = '-$' + discount.toFixed(2);
            } else {
                document.getElementById('viewSaleDiscountRow').style.display = 'none';
            }
            document.getElementById('viewSaleTotalAmount').textContent = '$' + parseFloat(sale.total_amount).toFixed(2);
            
            window.currentSaleForPrint = { sale: sale, items: items, subtotal: subtotal };
            document.getElementById('viewSaleModal').style.display = 'block';
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error loading sale: ' + e.message);
    }
}

function printSaleDetails() {
    if (!window.currentSaleForPrint) {
        alert('No sale data available');
        return;
    }
    
    const sale = window.currentSaleForPrint.sale;
    const items = window.currentSaleForPrint.items;
    const subtotal = window.currentSaleForPrint.subtotal;
    const discount = parseFloat(sale.discount_amount) || 0;
    const printWindow = window.open('', '', 'height=600,width=800');
    const customerName = sale.customer_name || 'Walk-in Customer';
    
    let itemsHtml = '';
    items.forEach(item => {
        const itemTotal = parseFloat(item.unit_price) * parseInt(item.quantity);
        itemsHtml += '<tr><td style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">' + htmlEscape(item.product_name) + '</td><td style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">' + item.quantity + '</td><td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' + parseFloat(item.unit_price).toFixed(2) + '</td><td style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">$' + itemTotal.toFixed(2) + '</td></tr>';
    });
    
    const html = '<!DOCTYPE html><html><head><title>Sale Receipt - SALE-' + String(sale.id).padStart(4, '0') + '</title><style>body{font-family:Arial,sans-serif;margin:0;padding:20px;background:#fff}.container{max-width:800px;margin:0 auto}.header{text-align:center;margin-bottom:30px;border-bottom:3px solid #8B6F47;padding-bottom:20px}.app-name{font-size:28px;font-weight:bold;color:#8B6F47;margin-bottom:5px}.title{font-size:18px;color:#666}.info-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}.info-label{font-size:12px;color:#999;text-transform:uppercase;margin-bottom:5px;font-weight:600}.info-value{font-size:14px;font-weight:600;color:#333}table{width:100%;border-collapse:collapse;margin-bottom:20px}th{background-color:#8B6F47;color:white;padding:12px;text-align:left;font-weight:600;font-size:14px;border:none}td{padding:10px 12px;border-bottom:1px solid #e5e7eb}.summary{text-align:right;margin-top:20px;background:#f9fafb;padding:15px;border-radius:6px}.summary-row{display:flex;justify-content:space-between;margin:8px 0;font-size:14px}.summary-label{font-weight:600;color:#666}.summary-value{font-weight:600;color:#333}.total-amount{font-size:16px !important;color:#8B6F47;font-weight:700;padding-top:10px;border-top:2px solid #e5e7eb}.total-amount .summary-label{color:#333}.footer{text-align:center;margin-top:30px;font-size:12px;color:#999;border-top:1px solid #e5e7eb;padding-top:15px}</style></head><body><div class="container"><div class="header"><div class="app-name">Vendix</div><div class="title">Sales Receipt</div></div><div class="info-row"><div class="info-item"><div class="info-label">Sale ID</div><div class="info-value">SALE-' + String(sale.id).padStart(4, '0') + '</div></div><div class="info-item"><div class="info-label">Date & Time</div><div class="info-value">' + formatDatetime(sale.sale_date) + '</div></div></div><div class="info-row"><div class="info-item"><div class="info-label">Customer</div><div class="info-value">' + htmlEscape(customerName) + '</div></div><div class="info-item"><div class="info-label">Cashier</div><div class="info-value">' + htmlEscape(sale.username || 'N/A') + '</div></div></div><table><thead><tr><th>Product Name</th><th style="text-align:center;width:80px">Qty</th><th style="text-align:right;width:100px">Price</th><th style="text-align:right;width:100px">Total</th></tr></thead><tbody>' + itemsHtml + '</tbody></table><div class="summary"><div class="summary-row"><div class="summary-label">Subtotal:</div><div class="summary-value">$' + subtotal.toFixed(2) + '</div></div>' + (discount > 0 ? '<div class="summary-row"><div class="summary-label">Discount:</div><div class="summary-value" style="color:#dc2626;">-$' + discount.toFixed(2) + '</div></div>' : '') + '<div class="summary-row total-amount"><div class="summary-label">Total Amount:</div><div class="summary-value">$' + parseFloat(sale.total_amount).toFixed(2) + '</div></div><div class="summary-row"><div class="summary-label">Payment Method:</div><div class="summary-value">' + htmlEscape(sale.payment_method) + '</div></div><div class="summary-row"><div class="summary-label">Status:</div><div class="summary-value">' + htmlEscape(sale.payment_status) + '</div></div></div><div class="footer"><p>Thank you for your purchase!</p><p>Generated on ' + new Date().toLocaleString() + '</p></div></div></body></html>';
    
    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 250);
}

async function editSale(id) {
    try {
        const response = await fetch('../api/sales.php?id=' + id);
        const text = await response.text();
        if (!text) {
            alert('Error: Empty response from server');
            return;
        }
        const result = JSON.parse(text);
        if (result.status === 'success') {
            const sale = result.data;
            document.getElementById('editSaleId').value = sale.id;
            document.getElementById('editSaleIdDisplay').value = 'SALE-' + String(sale.id).padStart(4, '0');
            document.getElementById('editSaleTotalAmount').value = '$' + parseFloat(sale.total_amount).toFixed(2);
            const statusSelect = document.getElementById('editSalePaymentStatus');
            const methodSelect = document.getElementById('editSalePaymentMethod');
            statusSelect.disabled = false;
            statusSelect.innerHTML = `
                <option value="Pending">Pending</option>
                <option value="Paid">Paid</option>
                <option value="Cancelled">Cancelled</option>
            `;
            statusSelect.value = sale.payment_status;
            methodSelect.disabled = false;
            
            if (sale.payment_status === 'Paid') {
                // For Paid sales, only allow switching to Cancelled
                statusSelect.innerHTML = `
                    <option value="Paid">Paid</option>
                    <option value="Cancelled">Cancelled</option>
                `;
                methodSelect.disabled = true; // Payment method is locked once paid
            }
            
            methodSelect.value = sale.payment_method;
            document.getElementById('editSaleModal').style.display = 'block';
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error loading sale: ' + e.message);
    }
}

document.getElementById('editSaleForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
        id: parseInt(document.getElementById('editSaleId').value),
        payment_status: document.getElementById('editSalePaymentStatus').value,
        payment_method: document.getElementById('editSalePaymentMethod').value
    };
    
    try {
        const response = await fetch('../api/sales.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const text = await response.text();
        if (!text) {
            alert('Error: Empty response from server');
            return;
        }
        const result = JSON.parse(text);
        if (result.status === 'success') {
            persistNotification('Sale updated successfully!', 'success');
            closeEditSaleModal();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});
</script>


