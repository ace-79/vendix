<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
if (!hasPermission('view_stock')) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to access stock management.');
}

// Get stock summary
$summary = $conn->query("SELECT 
    COUNT(*) as total_products,
    COALESCE(SUM(stock), 0) as total_units,
    COALESCE(SUM(stock * cost_price), 0) as total_stock_value,
    COALESCE(SUM(stock * price), 0) as total_retail_value,
    SUM(CASE WHEN stock <= min_stock THEN 1 ELSE 0 END) as low_stock_count,
    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
    FROM products")->fetch_assoc();

// Get products for dropdown
$productsResult = $conn->query("SELECT id, name, stock, min_stock FROM products WHERE status = 'active' ORDER BY name ASC");
$products = [];
while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
}

// Build movements query with filters
$where = [];
$params = [];
$filterProduct = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$filterType = isset($_GET['movement_type']) ? $_GET['movement_type'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if ($filterProduct) {
    $where[] = "sm.product_id = ?";
    $params[] = $filterProduct;
}
if ($filterType) {
    $where[] = "sm.movement_type = ?";
    $params[] = $filterType;
}
if ($filterDateFrom) {
    $where[] = "DATE(sm.created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(sm.created_at) <= ?";
    $params[] = $filterDateTo;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$movementsQuery = "SELECT sm.*, p.name as product_name, u.username 
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN users u ON sm.user_id = u.id
    $whereClause
    ORDER BY sm.created_at DESC
    LIMIT 200";

if (!empty($params)) {
    $stmt = $conn->prepare($movementsQuery);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $movementsResult = $stmt->get_result();
} else {
    $movementsResult = $conn->query($movementsQuery);
}

$movements = [];
if ($movementsResult) {
    while ($row = $movementsResult->fetch_assoc()) {
        $movements[] = $row;
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h1><i class="fas fa-warehouse" style="margin-right: 8px;"></i>Stock Management</h1>
                <?php if (hasPermission('adjust_stock')): ?>
                <button class="btn btn-primary" onclick="showAdjustModal()"><i class="fas fa-sliders-h"></i> Adjust Stock</button>
                <?php endif; ?>
            </div>

            <!-- Stock Summary Cards -->
            <div class="stock-summary-row">
                <div class="stock-card stock-card-primary">
                    <div class="stock-card-icon"><i class="fas fa-boxes"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Total Products</span>
                        <span class="stock-card-value"><?php echo number_format($summary['total_products']); ?></span>
                    </div>
                </div>
                <div class="stock-card stock-card-info-blue">
                    <div class="stock-card-icon"><i class="fas fa-cubes"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Total Units</span>
                        <span class="stock-card-value"><?php echo number_format($summary['total_units']); ?></span>
                    </div>
                </div>
                <div class="stock-card stock-card-success">
                    <div class="stock-card-icon"><i class="fas fa-coins"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Stock Value (Cost)</span>
                        <span class="stock-card-value">$<?php echo number_format($summary['total_stock_value'], 2); ?></span>
                    </div>
                </div>
                <div class="stock-card stock-card-accent">
                    <div class="stock-card-icon"><i class="fas fa-tag"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Retail Value</span>
                        <span class="stock-card-value">$<?php echo number_format($summary['total_retail_value'], 2); ?></span>
                    </div>
                </div>
                <div class="stock-card stock-card-warning">
                    <div class="stock-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Low Stock</span>
                        <span class="stock-card-value"><?php echo number_format($summary['low_stock_count']); ?></span>
                    </div>
                </div>
                <div class="stock-card stock-card-danger">
                    <div class="stock-card-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stock-card-info">
                        <span class="stock-card-label">Out of Stock</span>
                        <span class="stock-card-value"><?php echo number_format($summary['out_of_stock_count']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="stock-filter-panel">
                <h3 style="margin: 0 0 15px 0; color: #6F4E37; font-size: 1rem;"><i class="fas fa-filter"></i> Filter Movements</h3>
                <form method="GET" class="stock-filter-form">
                    <div class="stock-filter-group">
                        <label>Product</label>
                        <select name="product_id">
                            <option value="">All Products</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($filterProduct == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="stock-filter-group">
                        <label>Movement Type</label>
                        <select name="movement_type">
                            <option value="">All Types</option>
                            <option value="sale" <?php echo ($filterType === 'sale') ? 'selected' : ''; ?>>Sale</option>
                            <option value="purchase" <?php echo ($filterType === 'purchase') ? 'selected' : ''; ?>>Purchase</option>
                            <option value="adjustment" <?php echo ($filterType === 'adjustment') ? 'selected' : ''; ?>>Adjustment</option>
                            <option value="return" <?php echo ($filterType === 'return') ? 'selected' : ''; ?>>Return</option>
                            <option value="cancel_restore" <?php echo ($filterType === 'cancel_restore') ? 'selected' : ''; ?>>Cancel Restore</option>
                        </select>
                    </div>
                    <div class="stock-filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                    </div>
                    <div class="stock-filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                    </div>
                    <div class="stock-filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="stock.php" class="btn btn-secondary-outline"><i class="fas fa-redo"></i> Clear</a>
                    </div>
                </form>
            </div>

            <!-- Stock Movements Table -->
            <div class="stock-table-container">
                <h3 style="margin: 0 0 15px 0; color: #6F4E37;"><i class="fas fa-exchange-alt"></i> Stock Movement History</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Reference</th>
                            <th>By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                No stock movements found. Movements will appear here as sales are made or stock is adjusted.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.85rem;">
                                <?php echo date('M d, Y', strtotime($m['created_at'])); ?><br>
                                <small style="color: #888;"><?php echo date('H:i:s', strtotime($m['created_at'])); ?></small>
                            </td>
                            <td><strong><?php echo htmlspecialchars($m['product_name'] ?? 'Unknown'); ?></strong></td>
                            <td>
                                <?php
                                $typeBadges = [
                                    'sale' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-shopping-cart', 'label' => 'Sale'],
                                    'purchase' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'fa-truck', 'label' => 'Purchase'],
                                    'adjustment' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-sliders-h', 'label' => 'Adjustment'],
                                    'return' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-undo', 'label' => 'Return'],
                                    'cancel_restore' => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => 'fa-undo-alt', 'label' => 'Cancelled'],
                                ];
                                $badge = $typeBadges[$m['movement_type']] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'icon' => 'fa-circle', 'label' => $m['movement_type']];
                                ?>
                                <span class="stock-type-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                    <i class="fas <?php echo $badge['icon']; ?>"></i> <?php echo $badge['label']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="stock-qty-badge <?php echo $m['quantity'] >= 0 ? 'stock-qty-positive' : 'stock-qty-negative'; ?>">
                                    <?php echo ($m['quantity'] >= 0 ? '+' : '') . $m['quantity']; ?>
                                </span>
                            </td>
                            <td style="color: #888;"><?php echo $m['stock_before']; ?></td>
                            <td><strong><?php echo $m['stock_after']; ?></strong></td>
                            <td style="font-size: 0.85rem;">
                                <?php if ($m['reference_type']): ?>
                                    <?php echo ucfirst($m['reference_type']); ?> #<?php echo $m['reference_id']; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($m['username'] ?? '—'); ?></td>
                            <td style="font-size: 0.85rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($m['notes'] ?? ''); ?>">
                                <?php echo htmlspecialchars($m['notes'] ?? '—'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<?php if (hasPermission('adjust_stock')): ?>
<div id="adjustModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAdjustModal()">&times;</span>
        <h2><i class="fas fa-sliders-h" style="margin-right: 8px; color: #6F4E37;"></i>Adjust Stock</h2>
        <form id="adjustForm">
            <div class="form-group">
                <label>Product *</label>
                <select id="adjProduct" required>
                    <option value="">Select a product...</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?php echo $p['id']; ?>" data-stock="<?php echo $p['stock']; ?>">
                        <?php echo htmlspecialchars($p['name']); ?> (Current: <?php echo $p['stock']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Adjustment Type *</label>
                <select id="adjType" required>
                    <option value="">Select type...</option>
                    <option value="count_correction">📊 Count Correction</option>
                    <option value="return">↩️ Customer Return</option>
                    <option value="damage">💥 Damaged Goods</option>
                    <option value="theft">🔒 Theft / Loss</option>
                    <option value="other">📝 Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity * <small style="color: #888;">(positive to add, negative to remove)</small></label>
                <input type="number" id="adjQuantity" required placeholder="e.g. 5 or -3">
                <div id="adjPreview" style="margin-top: 8px; font-size: 0.9rem; color: #666; display: none;">
                    <span id="adjPreviewText"></span>
                </div>
            </div>
            <div class="form-group">
                <label>Reason / Notes *</label>
                <textarea id="adjReason" required rows="3" placeholder="Describe why this adjustment is being made..."></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Apply Adjustment</button>
                <button type="button" class="btn btn-secondary" onclick="closeAdjustModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
/* Stock Summary Cards */
.stock-summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin: 25px 0;
}

.stock-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    border-left: 4px solid #6F4E37;
    transition: all 0.3s ease;
}

.stock-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.stock-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.stock-card-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.stock-card-label {
    font-size: 0.78rem;
    color: #888;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.stock-card-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #333;
    margin-top: 2px;
}

.stock-card-primary { border-left-color: #6F4E37; }
.stock-card-primary .stock-card-icon { background: #f3e8e0; color: #6F4E37; }

.stock-card-info-blue { border-left-color: #3b82f6; }
.stock-card-info-blue .stock-card-icon { background: #dbeafe; color: #2563eb; }

.stock-card-success { border-left-color: #10b981; }
.stock-card-success .stock-card-icon { background: #d1fae5; color: #059669; }

.stock-card-accent { border-left-color: #8b5cf6; }
.stock-card-accent .stock-card-icon { background: #ede9fe; color: #7c3aed; }

.stock-card-warning { border-left-color: #f59e0b; }
.stock-card-warning .stock-card-icon { background: #fef3c7; color: #d97706; }

.stock-card-danger { border-left-color: #ef4444; }
.stock-card-danger .stock-card-icon { background: #fee2e2; color: #dc2626; }

/* Filter Panel */
.stock-filter-panel {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.stock-filter-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.stock-filter-group {
    flex: 1;
    min-width: 150px;
}

.stock-filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
    font-size: 0.85rem;
}

.stock-filter-group select,
.stock-filter-group input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
}

.stock-filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-secondary-outline {
    padding: 8px 15px;
    background: white;
    color: #555;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary-outline:hover {
    background: #f3f4f6;
}

/* Stock Table Container */
.stock-table-container {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

/* Badges */
.stock-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
}

.stock-qty-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.9rem;
}

.stock-qty-positive {
    background: #dcfce7;
    color: #166534;
}

.stock-qty-negative {
    background: #fee2e2;
    color: #991b1b;
}

/* Modal */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 520px; max-height: 85vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
.close { color: #aaa; float: right; font-size: 28px; cursor: pointer; }
.close:hover { color: black; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 10px 12px; border: 2px solid #E8D9C8; border-radius: 8px; font-size: 0.95rem;
    font-family: inherit; transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none; border-color: #8B6F47;
}
.btn-secondary { background-color: #888; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
.btn-secondary:hover { background-color: #666; }

@media (max-width: 768px) {
    .stock-summary-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .stock-filter-form {
        flex-direction: column;
    }
    .stock-filter-group {
        min-width: 100%;
    }
}

@media (max-width: 480px) {
    .stock-summary-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showAdjustModal() {
    document.getElementById('adjustForm').reset();
    document.getElementById('adjPreview').style.display = 'none';
    document.getElementById('adjustModal').style.display = 'block';
}

function closeAdjustModal() {
    document.getElementById('adjustModal').style.display = 'none';
}

window.onclick = function(e) {
    const modal = document.getElementById('adjustModal');
    if (e.target === modal) modal.style.display = 'none';
}

// Live preview of adjustment
const adjProduct = document.getElementById('adjProduct');
const adjQuantity = document.getElementById('adjQuantity');
const adjPreview = document.getElementById('adjPreview');
const adjPreviewText = document.getElementById('adjPreviewText');

function updatePreview() {
    if (!adjProduct || !adjQuantity) return;
    const selected = adjProduct.options[adjProduct.selectedIndex];
    const currentStock = parseInt(selected?.dataset?.stock ?? 0);
    const qty = parseInt(adjQuantity.value || 0);

    if (adjProduct.value && adjQuantity.value) {
        const newStock = currentStock + qty;
        const arrow = qty >= 0 ? '↑' : '↓';
        const color = newStock < 0 ? '#dc2626' : (qty >= 0 ? '#16a34a' : '#f59e0b');
        adjPreviewText.innerHTML = `Stock: <strong>${currentStock}</strong> → <strong style="color:${color}">${newStock}</strong> ${arrow}`;
        if (newStock < 0) {
            adjPreviewText.innerHTML += ' <span style="color:#dc2626">⚠ Negative stock!</span>';
        }
        adjPreview.style.display = 'block';
    } else {
        adjPreview.style.display = 'none';
    }
}

if (adjProduct) adjProduct.addEventListener('change', updatePreview);
if (adjQuantity) adjQuantity.addEventListener('input', updatePreview);

// Form submission
document.getElementById('adjustForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const data = {
        product_id: parseInt(document.getElementById('adjProduct').value),
        adjustment_type: document.getElementById('adjType').value,
        quantity: parseInt(document.getElementById('adjQuantity').value),
        reason: document.getElementById('adjReason').value.trim()
    };

    if (!data.product_id || !data.adjustment_type || data.quantity === 0 || !data.reason) {
        alert('Please fill in all required fields');
        return;
    }

    try {
        const response = await fetch('../api/stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.status === 'success') {
            if (typeof vendixNotifyAndReload === 'function') {
                vendixNotifyAndReload(result.message, 'success');
            } else {
                alert(result.message);
                location.reload();
            }
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
});
</script>
