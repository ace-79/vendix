<?php
session_start();
include '../config/db.php';
include '../config/auth.php';

requireLogin();

// Permission check
if (!hasPermission('view_dashboard')) {
    header("Location: sales.php");
    exit;
}

// Get overall statistics
$salesCount = $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch_assoc()['total'];
$totalProfit = $conn->query("SELECT COALESCE(SUM(si.quantity * (si.unit_price - COALESCE(p.cost_price, 0))), 0) as profit FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.payment_status = 'Paid'")->fetch_assoc()['profit'];
$customerCount = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$productCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];

// Get today's statistics
$todaySales = $conn->query("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['count'];
$todayRevenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(paid_at) = CURDATE()")->fetch_assoc()['total'];
$todayProfit = $conn->query("SELECT COALESCE(SUM(si.quantity * (si.unit_price - COALESCE(p.cost_price, 0))), 0) as profit FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.payment_status = 'Paid' AND DATE(s.sale_date) = CURDATE()")->fetch_assoc()['profit'];

// Get top 5 selling products
$topProducts = $conn->query("SELECT 
                             p.id,
                             p.name,
                             SUM(si.quantity) as total_qty,
                             SUM(si.subtotal) as revenue 
                             FROM sale_items si
                             LEFT JOIN products p ON si.product_id = p.id
                             LEFT JOIN sales s ON si.sale_id = s.id
                             INNER JOIN payments pay ON pay.sale_id = s.id
                             GROUP BY p.id, p.name
                             ORDER BY total_qty DESC
                             LIMIT 5");

// Get low stock products
$lowStock = $conn->query("SELECT id, name, stock, min_stock FROM products WHERE stock <= min_stock ORDER BY stock ASC LIMIT 10");

$lowStockActionCount = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock <= min_stock")->fetch_assoc()['count'];
$unpaidSalesCount = $conn->query("SELECT COUNT(*) as count FROM sales WHERE payment_status <> 'Paid'")->fetch_assoc()['count'];
$slowMovingCount = $conn->query("
    SELECT COUNT(*) as count
    FROM products p
    WHERE p.stock > 0
      AND NOT EXISTS (
          SELECT 1
          FROM stock_movements sm
          WHERE sm.product_id = p.id
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      )
")->fetch_assoc()['count'];

$reorderSuggestions = $conn->query("
    SELECT
        p.id,
        p.name,
        p.stock,
        p.min_stock,
        GREATEST(p.min_stock - p.stock, 0) as deficit,
        COALESCE(s.name, 'No supplier') as supplier_name
    FROM products p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.stock <= p.min_stock
    ORDER BY deficit DESC, p.stock ASC, p.name ASC
    LIMIT 5
");

$unpaidSales = $conn->query("
    SELECT
        s.id,
        s.sale_date,
        s.total_amount,
        s.payment_status,
        COALESCE(c.name, 'Walk-in Customer') as customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.payment_status <> 'Paid'
    ORDER BY s.sale_date DESC
    LIMIT 5
");

$slowMovingProducts = $conn->query("
    SELECT
        p.id,
        p.name,
        p.stock,
        p.category,
        MAX(sm.created_at) as last_movement_at
    FROM products p
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    WHERE p.stock > 0
    GROUP BY p.id, p.name, p.stock, p.category
    HAVING last_movement_at IS NULL OR last_movement_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY last_movement_at IS NULL DESC, last_movement_at ASC, p.stock DESC
    LIMIT 5
");

// Get last 7 days sales data for chart (including days with no sales)
$sevenDaysSales = $conn->query("
    SELECT 
        dates.date as date, 
        COUNT(s.id) as sales_count, 
        COALESCE(SUM(pay.amount), 0) as revenue 
    FROM (
        SELECT DATE_SUB(CURDATE(), INTERVAL day_offset DAY) as date
        FROM (SELECT 0 as day_offset UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) days
    ) dates
    LEFT JOIN sales s ON DATE(s.sale_date) = dates.date
    LEFT JOIN payments pay ON pay.sale_id = s.id
    GROUP BY dates.date
    ORDER BY dates.date ASC
");

$chartData = [];
while ($row = $sevenDaysSales->fetch_assoc()) {
    // Ensure sales_count is an integer
    $row['sales_count'] = intval($row['sales_count']);
    $chartData[] = $row;
}

// Stock & Inventory stats
$stockValue = $conn->query("SELECT COALESCE(SUM(stock * cost_price), 0) as cost_val, COALESCE(SUM(stock * price), 0) as retail_val, COALESCE(SUM(stock), 0) as total_units FROM products")->fetch_assoc();

$pendingPOs = $conn->query("SELECT po.id, po.status, po.total_cost, po.order_date, s.name as supplier_name,
    (SELECT COALESCE(SUM(quantity_ordered),0) FROM purchase_order_items WHERE purchase_order_id=po.id) as total_qty,
    (SELECT COALESCE(SUM(quantity_received),0) FROM purchase_order_items WHERE purchase_order_id=po.id) as recv_qty
    FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id
    WHERE po.status IN ('draft','ordered','partially_received') ORDER BY po.order_date DESC LIMIT 5");

$recentMovements = $conn->query("SELECT sm.*, p.name as product_name, u.username
    FROM stock_movements sm LEFT JOIN products p ON sm.product_id=p.id LEFT JOIN users u ON sm.user_id=u.id
    ORDER BY sm.created_at DESC LIMIT 8");

// Stock Value by Category
$categoryStats = $conn->query("SELECT COALESCE(category, 'Uncategorized') as cat, SUM(stock * cost_price) as value FROM products GROUP BY cat ORDER BY value DESC");
$categoryData = [];
while($row = $categoryStats->fetch_assoc()) $categoryData[] = $row;

// Stock Flow (30 days)
$stockFlow = $conn->query("SELECT 
    SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as stock_in,
    SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as stock_out
    FROM stock_movements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();

// Top Suppliers
$topSuppliers = $conn->query("SELECT s.name, SUM(po.total_cost) as total_spent, COUNT(po.id) as po_count
    FROM suppliers s JOIN purchase_orders po ON s.id = po.supplier_id
    WHERE po.status IN ('received', 'partially_received') GROUP BY s.id ORDER BY total_spent DESC LIMIT 5");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Dashboard</h1>
            
            <!-- Overview Section -->
            <div class="dashboard-section">
                <h2 class="section-title">📊 Business Overview</h2>
                
                <!-- Overall Stats -->
                <div class="row">
                    <div class="col-md-3" style="flex: 0 0 calc(20% - 16px);">
                        <div class="card card-primary">
                            <h3><i class="fas fa-shopping-cart"></i> Total Sales</h3>
                            <p class="stat-value"><?php echo number_format($salesCount); ?></p>
                        </div>
                    </div>
                    <?php if (hasPermission('view_reports')): ?>
                    <div class="col-md-3" style="flex: 0 0 calc(20% - 16px);">
                        <div class="card card-success">
                            <h3><i class="fas fa-dollar-sign"></i> Total Revenue</h3>
                            <p class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3" style="flex: 0 0 calc(20% - 16px);">
                        <div class="card card-accent-orange" style="border-left-color: #8b5cf6;">
                            <h3><i class="fas fa-chart-line"></i> Total Profit</h3>
                            <p class="stat-value">$<?php echo number_format($totalProfit, 2); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3" style="flex: 0 0 calc(20% - 16px);">
                        <div class="card card-info">
                            <h3><i class="fas fa-users"></i> Total Customers</h3>
                            <p class="stat-value"><?php echo number_format($customerCount); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3" style="flex: 0 0 calc(20% - 16px);">
                        <div class="card card-warning">
                            <h3><i class="fas fa-box"></i> Total Products</h3>
                            <p class="stat-value"><?php echo number_format($productCount); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Stats -->
                <div style="margin-top: 30px;">
                    <h3 style="color: #6F4E37; margin-bottom: 15px; font-size: 1.1rem;">📅 Today's Performance</h3>
                    <div class="row">
                        <div class="col-md-3" style="flex: 0 0 calc(33.333% - 14px);">
                            <div class="card card-accent-orange">
                                <h3><i class="fas fa-calendar-day"></i> Today's Sales</h3>
                                <p class="stat-value"><?php echo number_format($todaySales); ?></p>
                            </div>
                        </div>
                        <?php if (hasPermission('view_reports')): ?>
                        <div class="col-md-3" style="flex: 1;">
                            <div class="card card-accent-green">
                                <h3><i class="fas fa-money-bill-wave"></i> Today's Revenue</h3>
                                <p class="stat-value">$<?php echo number_format($todayRevenue, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3" style="flex: 1;">
                            <div class="card card-accent-orange" style="border-left-color: #8b5cf6;">
                                <h3><i class="fas fa-arrow-trend-up"></i> Today's Profit</h3>
                                <p class="stat-value">$<?php echo number_format($todayProfit, 2); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-section" style="margin-top: 36px;">
                <h2 class="section-title">Action Center</h2>

                <div class="dashboard-action-summary">
                    <div class="dashboard-action-card dashboard-action-danger">
                        <div class="dashboard-action-icon"><i class="fas fa-box-open"></i></div>
                        <div>
                            <div class="dashboard-action-value"><?php echo number_format($lowStockActionCount); ?></div>
                            <div class="dashboard-action-label">Products Need Reorder</div>
                        </div>
                    </div>
                    <div class="dashboard-action-card dashboard-action-warning">
                        <div class="dashboard-action-icon"><i class="fas fa-credit-card"></i></div>
                        <div>
                            <div class="dashboard-action-value"><?php echo number_format($unpaidSalesCount); ?></div>
                            <div class="dashboard-action-label">Unpaid Sales To Follow Up</div>
                        </div>
                    </div>
                    <div class="dashboard-action-card dashboard-action-muted">
                        <div class="dashboard-action-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div>
                            <div class="dashboard-action-value"><?php echo number_format($slowMovingCount); ?></div>
                            <div class="dashboard-action-label">Slow Moving Products</div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-action-grid">
                    <div class="dashboard-panel dashboard-panel-danger">
                        <div class="dashboard-panel-head">
                            <div>
                                <h3><i class="fas fa-exclamation-triangle"></i> Reorder Suggestions</h3>
                                <p>Stock at or below the minimum threshold.</p>
                            </div>
                            <a href="products.php?filter=low_stock" class="dashboard-inline-link">Open Products</a>
                        </div>
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Gap</th>
                                    <th>Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $hasReorderSuggestions = false; ?>
                                <?php while ($product = $reorderSuggestions->fetch_assoc()): $hasReorderSuggestions = true; ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><?php echo number_format($product['stock']); ?> / <?php echo number_format($product['min_stock']); ?></td>
                                    <td><span class="badge badge-danger">-<?php echo number_format($product['deficit']); ?></span></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasReorderSuggestions): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:20px;color:#16a34a;">All stock levels are healthy</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="dashboard-panel dashboard-panel-warning">
                        <div class="dashboard-panel-head">
                            <div>
                                <h3><i class="fas fa-file-invoice-dollar"></i> Unpaid Sales Follow-up</h3>
                                <p>Recent sales that still need payment attention.</p>
                            </div>
                            <a href="sales.php" class="dashboard-inline-link">Open Sales</a>
                        </div>
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Sale</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $hasUnpaidSales = false; ?>
                                <?php while ($sale = $unpaidSales->fetch_assoc()): $hasUnpaidSales = true; ?>
                                <tr>
                                    <td>
                                        <strong>SALE-<?php echo str_pad((string) $sale['id'], 4, '0', STR_PAD_LEFT); ?></strong><br>
                                        <small style="color:#6b7280;"><?php echo date('M d, H:i', strtotime($sale['sale_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                    <td><span class="badge badge-warning-soft"><?php echo htmlspecialchars($sale['payment_status']); ?></span></td>
                                    <td style="font-weight:700;color:#92400e;">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasUnpaidSales): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:20px;color:#16a34a;">No unpaid sales need follow-up</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-panel dashboard-panel-muted" style="margin-top: 25px;">
                    <div class="dashboard-panel-head">
                        <div>
                            <h3><i class="fas fa-pause-circle"></i> Zero or Slow Movement Inventory</h3>
                            <p>Products with stock on hand but no movement in the last 30 days.</p>
                        </div>
                        <a href="stock.php" class="dashboard-inline-link">Open Stock Activity</a>
                    </div>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Units On Hand</th>
                                <th>Last Movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $hasSlowProducts = false; ?>
                            <?php while ($product = $slowMovingProducts->fetch_assoc()): $hasSlowProducts = true; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></td>
                                <td><?php echo number_format($product['stock']); ?></td>
                                <td>
                                    <?php if (!empty($product['last_movement_at'])): ?>
                                        <?php echo date('M d, Y', strtotime($product['last_movement_at'])); ?>
                                    <?php else: ?>
                                        <span class="badge badge-muted-soft">No movement logged</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasSlowProducts): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:20px;color:#16a34a;">No slow-moving products right now</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="dashboard-section" style="margin-top: 40px;">
                <h2 class="section-title">📈 Analytics</h2>
                
                <?php if (hasPermission('view_reports')): ?>
                <!-- 7 Days Sales Chart -->
                <div style="background-color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h3 style="color: #6F4E37; margin-bottom: 20px;">Sales Last 7 Days</h3>
                    <canvas id="sevenDaysChart" height="80"></canvas>
                </div>
                <?php endif; ?>
                
                <!-- Top 5 Selling Products -->
                <div style="background-color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h3 style="color: #6F4E37; margin-bottom: 15px;"><i class="fas fa-star"></i> Top 5 Selling Products</h3>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasTopProducts = false;
                            while ($p = $topProducts->fetch_assoc()): 
                                $hasTopProducts = true;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name'] ?? 'Unknown'); ?></td>
                                <td><span class="badge badge-info"><?php echo number_format($p['total_qty']); ?></span></td>
                                <td class="text-success font-weight-bold">$<?php echo number_format($p['revenue'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasTopProducts): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">No sales data available yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Low Stock Alerts -->
                <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #dc2626;">
                    <h3 style="color: #dc2626; margin-bottom: 15px;"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasLowStock = false;
                            while ($p = $lowStock->fetch_assoc()): 
                                $hasLowStock = true;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo $p['stock']; ?></td>
                                <td><?php echo $p['min_stock']; ?></td>
                                <td><span class="badge badge-danger">⚠ Critical</span></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasLowStock): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: #16a34a;">✓ All products have sufficient stock</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory & Stock Section -->
            <div class="dashboard-section" style="margin-top: 40px;">
                <h2 class="section-title">📦 Inventory & Stock</h2>

                <!-- Inventory Value Cards -->
                <div class="row" style="margin-bottom: 25px;">
                    <div class="col-md-3" style="flex: 0 0 calc(33.333% - 14px);">
                        <div class="card" style="border-left-color: #6366f1;">
                            <h3><i class="fas fa-cubes"></i> Total Units in Stock</h3>
                            <p class="stat-value" style="color: #6366f1;"><?php echo number_format($stockValue['total_units']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3" style="flex: 0 0 calc(33.333% - 14px);">
                        <div class="card" style="border-left-color: #10b981;">
                            <h3><i class="fas fa-coins"></i> Stock Value (Cost)</h3>
                            <p class="stat-value" style="color: #10b981;">$<?php echo number_format($stockValue['cost_val'], 2); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3" style="flex: 0 0 calc(33.333% - 14px);">
                        <div class="card" style="border-left-color: #8b5cf6;">
                            <h3><i class="fas fa-tag"></i> Retail Value</h3>
                            <p class="stat-value" style="color: #8b5cf6;">$<?php echo number_format($stockValue['retail_val'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Two Column: Pending POs + Recent Movements -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <!-- Pending Purchase Orders -->
                    <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #f59e0b;">
                        <h3 style="color: #f59e0b; margin-bottom: 15px;"><i class="fas fa-truck"></i> Pending Purchase Orders</h3>
                        <table class="dashboard-table">
                            <thead><tr><th>PO #</th><th>Supplier</th><th>Progress</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php
                            $hasPendingPO = false;
                            while ($po = $pendingPOs->fetch_assoc()):
                                $hasPendingPO = true;
                                $totalQ = intval($po['total_qty']); $recvQ = intval($po['recv_qty']);
                                $pct = $totalQ > 0 ? round($recvQ / $totalQ * 100) : 0;
                                $stColors = ['draft'=>'#9ca3af','ordered'=>'#3b82f6','partially_received'=>'#f59e0b'];
                                $stColor = $stColors[$po['status']] ?? '#666';
                            ?>
                            <tr>
                                <td><strong>PO-<?php echo $po['id']; ?></strong></td>
                                <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($po['supplier_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <div style="background:#e5e7eb;border-radius:10px;height:6px;width:60px;display:inline-block;vertical-align:middle;">
                                        <div style="background:<?php echo $pct >= 100 ? '#16a34a' : '#f59e0b'; ?>;height:100%;border-radius:10px;width:<?php echo $pct; ?>%;"></div>
                                    </div>
                                    <small style="margin-left:4px;"><?php echo $recvQ; ?>/<?php echo $totalQ; ?></small>
                                </td>
                                <td><span style="color:<?php echo $stColor; ?>;font-weight:600;font-size:0.78rem;"><?php echo ucwords(str_replace('_', ' ', $po['status'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasPendingPO): ?>
                            <tr><td colspan="4" style="text-align:center;padding:20px;color:#16a34a;">✓ No pending purchase orders</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <?php if ($hasPendingPO): ?>
                        <div style="margin-top:12px;text-align:right;">
                            <a href="purchase_orders.php" style="color:#6F4E37;font-size:0.85rem;font-weight:600;text-decoration:none;">View all POs →</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Stock Movements -->
                    <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #6366f1;">
                        <h3 style="color: #6366f1; margin-bottom: 15px;"><i class="fas fa-exchange-alt"></i> Recent Stock Activity</h3>
                        <table class="dashboard-table">
                            <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>When</th></tr></thead>
                            <tbody>
                            <?php
                            $hasMovements = false;
                            while ($mv = $recentMovements->fetch_assoc()):
                                $hasMovements = true;
                                $typeLabels = ['sale'=>['🛒','#991b1b'],'purchase'=>['📦','#166534'],'adjustment'=>['⚙️','#1e40af'],'return'=>['↩️','#92400e'],'cancel_restore'=>['🔄','#5b21b6']];
                                $tl = $typeLabels[$mv['movement_type']] ?? ['•','#666'];
                            ?>
                            <tr>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($mv['product_name'] ?? 'Unknown'); ?></td>
                                <td><span style="color:<?php echo $tl[1]; ?>;font-size:0.8rem;font-weight:600;"><?php echo $tl[0]; ?> <?php echo ucfirst(str_replace('_',' ',$mv['movement_type'])); ?></span></td>
                                <td><span style="font-weight:700;color:<?php echo $mv['quantity'] >= 0 ? '#16a34a' : '#dc2626'; ?>;"><?php echo ($mv['quantity'] >= 0 ? '+' : '') . $mv['quantity']; ?></span></td>
                                <td style="font-size:0.78rem;color:#888;white-space:nowrap;"><?php echo date('M d, H:i', strtotime($mv['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasMovements): ?>
                            <tr><td colspan="4" style="text-align:center;padding:20px;color:#999;">No stock movements yet</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <?php if ($hasMovements): ?>
                        <div style="margin-top:12px;text-align:right;">
                            <a href="stock.php" style="color:#6F4E37;font-size:0.85rem;font-weight:600;text-decoration:none;">View all movements →</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Advanced Analytics Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 25px;">
                    <!-- Stock Value by Category Chart -->
                    <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <h3 style="color: #6F4E37; margin-bottom: 20px;"><i class="fas fa-chart-pie"></i> Stock Value by Category</h3>
                        <div style="height: 250px; position: relative;">
                            <canvas id="categoryValueChart"></canvas>
                        </div>
                    </div>

                    <!-- Supplier Performance -->
                    <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <h3 style="color: #6F4E37; margin-bottom: 20px;"><i class="fas fa-medal"></i> Top Suppliers</h3>
                        <table class="dashboard-table">
                            <thead><tr><th>Supplier</th><th>Orders</th><th>Total Spent</th></tr></thead>
                            <tbody>
                            <?php 
                            $hasSuppliers = false;
                            while($s = $topSuppliers->fetch_assoc()): $hasSuppliers = true; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                <td><?php echo $s['po_count']; ?> POs</td>
                                <td style="font-weight:700; color:#10b981;">$<?php echo number_format($s['total_spent'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(!$hasSuppliers): ?>
                            <tr><td colspan="3" style="text-align:center;padding:20px;color:#999;">No supplier data yet</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- 30-Day Flow Summary -->
                        <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; display: flex; justify-content: space-around; text-align: center;">
                            <div>
                                <small style="display:block; color:#888;">30-Day Stock In</small>
                                <span style="font-size: 1.2rem; font-weight: 700; color: #16a34a;">+<?php echo number_format($stockFlow['stock_in'] ?? 0); ?></span>
                            </div>
                            <div>
                                <small style="display:block; color:#888;">30-Day Stock Out</small>
                                <span style="font-size: 1.2rem; font-weight: 700; color: #dc2626;">-<?php echo number_format($stockFlow['stock_out'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-section {
    margin-bottom: 20px;
}

.section-title {
    color: #6F4E37;
    font-size: 1.3rem;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

.row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.col-md-3 {
    flex: 0 0 calc(25% - 15px);
}

@media (max-width: 1200px) {
    .col-md-3 { flex: 0 0 calc(50% - 10px); }
    .dashboard-action-summary { grid-template-columns: 1fr; }
    .dashboard-action-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .col-md-3 { flex: 0 0 calc(100%); }
    .dashboard-section [style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    .dashboard-panel-head {
        flex-direction: column;
    }
}

.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 4px solid #6F4E37;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.card h3 {
    color: #666;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #6F4E37;
    margin: 0;
}

.card-primary { border-left-color: #3b82f6; }
.card-success { border-left-color: #10b981; }
.card-info { border-left-color: #8B6F47; }
.card-warning { border-left-color: #f59e0b; }
.card-accent-orange { border-left-color: #f97316; }
.card-accent-green { border-left-color: #22c55e; }

.dashboard-action-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.dashboard-action-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: white;
    border-radius: 10px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #ece7df;
}

.dashboard-action-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.dashboard-action-value {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}

.dashboard-action-label {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 600;
}

.dashboard-action-danger .dashboard-action-icon,
.dashboard-panel-danger .dashboard-panel-head i {
    background: #fee2e2;
    color: #b91c1c;
}

.dashboard-action-warning .dashboard-action-icon,
.dashboard-panel-warning .dashboard-panel-head i {
    background: #fef3c7;
    color: #b45309;
}

.dashboard-action-muted .dashboard-action-icon,
.dashboard-panel-muted .dashboard-panel-head i {
    background: #e5e7eb;
    color: #4b5563;
}

.dashboard-action-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.dashboard-panel {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-top: 4px solid #6F4E37;
}

.dashboard-panel-danger {
    border-top-color: #dc2626;
}

.dashboard-panel-warning {
    border-top-color: #f59e0b;
}

.dashboard-panel-muted {
    border-top-color: #6b7280;
}

.dashboard-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 16px;
}

.dashboard-panel-head h3 {
    margin: 0 0 4px 0;
    color: #111827;
    font-size: 1.05rem;
}

.dashboard-panel-head p {
    margin: 0;
    color: #6b7280;
    font-size: 0.88rem;
}

.dashboard-inline-link {
    color: #6F4E37;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.dashboard-inline-link:hover {
    text-decoration: underline;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.dashboard-table thead {
    background-color: #f3f4f6;
    border-bottom: 2px solid #e5e7eb;
}

.dashboard-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.dashboard-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.dashboard-table tbody tr:hover {
    background-color: #f9fafb;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}

.badge-info {
    background-color: #dbeafe;
    color: #1e40af;
}

.badge-danger {
    background-color: #fee2e2;
    color: #991b1b;
}

.badge-success {
    background-color: #dcfce7;
    color: #166534;
}

.badge-warning-soft {
    background-color: #fef3c7;
    color: #92400e;
}

.badge-muted-soft {
    background-color: #e5e7eb;
    color: #4b5563;
}

.text-success {
    color: #16a34a;
}

.font-weight-bold {
    font-weight: bold;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare chart data
const chartData = <?php echo json_encode($chartData); ?>;
const categoryData = <?php echo json_encode($categoryData); ?>;

if (chartData && chartData.length > 0) {
    const dates = chartData.map(d => d.date);
    const sales = chartData.map(d => parseInt(d.sales_count));
    const revenue = chartData.map(d => parseFloat(d.revenue));

    const ctx = document.getElementById('sevenDaysChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Sales Count',
                    data: sales,
                    backgroundColor: 'rgba(111, 78, 55, 0.7)',
                    borderColor: 'rgba(111, 78, 55, 1)',
                    borderWidth: 2,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenue ($)',
                    data: revenue,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Sales Count',
                        color: 'rgba(111, 78, 55, 1)'
                    },
                    ticks: {
                        color: 'rgba(111, 78, 55, 1)',
                        stepSize: 1,
                        precision: 0
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue ($)',
                        color: 'rgba(16, 185, 129, 1)'
                    },
                    ticks: {
                        color: 'rgba(16, 185, 129, 1)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Category Value Chart
if (categoryData && categoryData.length > 0) {
    const labels = categoryData.map(c => c.cat);
    const values = categoryData.map(c => parseFloat(c.value));
    
    const colors = [
        'rgba(111, 78, 55, 0.8)',   // Coffee
        'rgba(139, 111, 71, 0.8)',  // Light Coffee
        'rgba(16, 185, 129, 0.8)',  // Emerald
        'rgba(99, 102, 241, 0.8)',  // Indigo
        'rgba(245, 158, 11, 0.8)',  // Amber
        'rgba(139, 92, 246, 0.8)',  // Violet
        'rgba(236, 72, 153, 0.8)',  // Pink
        'rgba(107, 114, 128, 0.8)'   // Gray
    ];

    const ctxCat = document.getElementById('categoryValueChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) label += ': ';
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed);
                            }
                            return label;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}
</script>


