<?php
session_start();
include '../config/db.php';
include '../config/auth.php';

requireLogin();
if (!canViewReports()) {
    die('Access denied: Reports are for managers and admins only');
}

// Get selected date from query or use today
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!strtotime($selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$dailyResult = $conn->query("SELECT 
    COUNT(*) as total_sales,
    COUNT(DISTINCT customer_id) as total_customers,
    COALESCE(SUM(pay.amount), 0) as total_revenue,
    COALESCE(SUM(s.discount_amount), 0) as total_discounts,
    COALESCE(AVG(CASE WHEN pay.id IS NOT NULL THEN s.total_amount END), 0) as avg_sale_value
    FROM sales s
    LEFT JOIN payments pay ON pay.sale_id = s.id
    WHERE DATE(s.sale_date) = '$selectedDate'");
$dailyStats = $dailyResult->fetch_assoc();

// Get sales by payment method
$paymentMethodsResult = $conn->query("SELECT 
    s.payment_method,
    COUNT(*) as count,
    COALESCE(SUM(pay.amount), 0) as amount
    FROM sales s
    INNER JOIN payments pay ON pay.sale_id = s.id
    WHERE DATE(s.sale_date) = '$selectedDate'
    GROUP BY s.payment_method");

$paymentMethods = [];
while ($row = $paymentMethodsResult->fetch_assoc()) {
    $paymentMethods[] = $row;
}

// Get detailed sales transactions
$salesDetailsResult = $conn->query("SELECT 
    s.id,
    c.name as customer_name,
    s.total_amount,
    s.payment_method,
    s.sale_date,
    COUNT(si.id) as item_count
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE DATE(s.sale_date) = '$selectedDate'
    GROUP BY s.id
    ORDER BY s.sale_date DESC");

// Get hourly sales distribution
$hourlyResult = $conn->query("SELECT 
    HOUR(s.sale_date) as hour,
    COUNT(*) as sales_count,
    COALESCE(SUM(pay.amount), 0) as revenue
    FROM sales s
    LEFT JOIN payments pay ON pay.sale_id = s.id
    WHERE DATE(s.sale_date) = '$selectedDate'
    GROUP BY HOUR(s.sale_date)
    ORDER BY hour ASC");

$hourlyData = [];
for ($i = 0; $i < 24; $i++) {
    $hourlyData[$i] = ['hour' => $i, 'sales_count' => 0, 'revenue' => 0];
}

while ($row = $hourlyResult->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $hourlyData[$hour] = $row;
}

$hourlyData = array_values($hourlyData);

// Get top products sold today
$topProductsResult = $conn->query("SELECT 
    p.id,
    p.name,
    SUM(si.quantity) as quantity_sold,
    SUM(si.subtotal) as revenue,
    COUNT(DISTINCT si.sale_id) as number_of_sales
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN sales s ON si.sale_id = s.id
    INNER JOIN payments pay ON pay.sale_id = s.id
    WHERE DATE(s.sale_date) = '$selectedDate'
    GROUP BY p.id
    ORDER BY quantity_sold DESC
    LIMIT 10");

$topProducts = [];
while ($row = $topProductsResult->fetch_assoc()) {
    $topProducts[] = $row;
}

// Calculate insights
$totalSales = $dailyStats['total_sales'] ?? 0;
$totalRevenue = $dailyStats['total_revenue'] ?? 0;
$avgValue = $dailyStats['avg_sale_value'] ?? 0;
$insights = [];

if ($totalSales > 0) {
    $insights[] = [
        'type' => 'info',
        'message' => "Total of $totalSales transactions completed with $" . number_format($totalRevenue, 2) . " revenue"
    ];
    
    if ($totalSales > 50) {
        $insights[] = [
            'type' => 'success',
            'message' => "Outstanding sales volume! More than 50 transactions."
        ];
    }
    
    if ($avgValue > 100) {
        $insights[] = [
            'type' => 'success',
            'message' => "Strong average transaction value of $" . number_format($avgValue, 2)
        ];
    }
} else {
    $insights[] = [
        'type' => 'warning',
        'message' => "No sales recorded for this date"
    ];
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Daily Sales Report</h1>
                <a href="reports.php" class="btn btn-secondary" style="text-decoration: none; padding: 8px 16px; border-radius: 6px; background-color: #e5e7eb; color: #374151; cursor: pointer;">← Back to Reports</a>
            </div>
            
            <!-- Date Filter -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; justify-content: space-between;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Select Date:</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 20px; background-color: #6F4E37; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Load Report</button>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="summary-card card-blue">
                    <h3>Total Sales</h3>
                    <p class="summary-value"><?php echo number_format($totalSales); ?></p>
                    <p class="summary-label">Transactions</p>
                </div>
                <div class="summary-card card-green">
                    <h3>Total Revenue</h3>
                    <p class="summary-value">$<?php echo number_format($totalRevenue, 2); ?></p>
                    <p class="summary-label">Daily Income</p>
                </div>
                <div class="summary-card card-orange">
                    <h3>Average Sale Value</h3>
                    <p class="summary-value">$<?php echo number_format($avgValue, 2); ?></p>
                    <p class="summary-label">Per Transaction</p>
                </div>
                <div class="summary-card card-purple">
                    <h3>Unique Customers</h3>
                    <p class="summary-value"><?php echo number_format($dailyStats['total_customers'] ?? 0); ?></p>
                    <p class="summary-label">Today</p>
                </div>
                <div class="summary-card card-red">
                    <h3>Total Discounts</h3>
                    <p class="summary-value">$<?php echo number_format($dailyStats['total_discounts'] ?? 0, 2); ?></p>
                    <p class="summary-label">Given Today</p>
                </div>
            </div>
            
            <!-- Insights Section -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="color: #6F4E37; margin-top: 0;">💡 Key Insights</h2>
                <?php foreach ($insights as $insight): ?>
                    <div style="padding: 12px 15px; margin-bottom: 10px; border-left: 4px solid <?php echo $insight['type'] === 'success' ? '#10b981' : ($insight['type'] === 'warning' ? '#f59e0b' : '#3b82f6'); ?>; background-color: <?php echo $insight['type'] === 'success' ? '#dcfce7' : ($insight['type'] === 'warning' ? '#fef3c7' : '#dbeafe'); ?>; border-radius: 6px; color: <?php echo $insight['type'] === 'success' ? '#166534' : ($insight['type'] === 'warning' ? '#92400e' : '#1e40af'); ?>;">
                        <?php echo htmlspecialchars($insight['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Charts Section (Screen View) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; margin-bottom: 30px;" class="screen-only">
                <!-- Hourly Sales Chart -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h3 style="color: #6F4E37; margin-top: 0;">Sales by Hour</h3>
                    <canvas id="hourlyChart" height="80"></canvas>
                </div>
                
                <!-- Payment Methods Chart -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h3 style="color: #6F4E37; margin-top: 0;">Payment Methods Distribution</h3>
                    <canvas id="paymentChart" height="80"></canvas>
                </div>
            </div>

            <!-- Print-Only: Hourly Sales Text Summary -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: none;" class="print-only">
                <h3 style="color: #6F4E37; margin-top: 0;">Sales by Hour</h3>
                <table class="report-table" style="font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Hour</th>
                            <th style="text-align: right;">Sales</th>
                            <th style="text-align: right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hourlyData as $hour): ?>
                            <?php if ($hour['sales_count'] > 0): ?>
                            <tr>
                                <td><?php echo str_pad($hour['hour'], 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo str_pad($hour['hour'], 2, '0', STR_PAD_LEFT); ?>:59</td>
                                <td style="text-align: right;"><?php echo number_format($hour['sales_count']); ?></td>
                                <td style="text-align: right; font-weight: bold;">$<?php echo number_format($hour['revenue'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Print-Only: Payment Methods Text Summary -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: none;" class="print-only">
                <h3 style="color: #6F4E37; margin-top: 0;">Payment Methods Distribution</h3>
                <table class="report-table" style="font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th style="text-align: right;">Count</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: right;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalRevenue = isset($dailyStats['total_revenue']) ? $dailyStats['total_revenue'] : 0;
                        foreach ($paymentMethods as $method): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($method['payment_method'] ?? 'Unknown'); ?></td>
                            <td style="text-align: right;"><?php echo number_format($method['count']); ?></td>
                            <td style="text-align: right; font-weight: bold;">$<?php echo number_format($method['amount'], 2); ?></td>
                            <td style="text-align: right;"><?php echo $totalRevenue > 0 ? number_format(($method['amount'] / $totalRevenue) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Top Products -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="color: #6F4E37; margin-top: 0;">⭐ Top Products Sold</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Revenue</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($topProducts) > 0): ?>
                            <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name'] ?? 'Unknown'); ?></td>
                                <td><span class="badge badge-info"><?php echo number_format($product['quantity_sold']); ?></span></td>
                                <td class="text-success font-weight-bold">$<?php echo number_format($product['revenue'], 2); ?></td>
                                <td><?php echo number_format($product['number_of_sales']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: #999;">No products sold on this date</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Payment Methods Summary -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="color: #6F4E37; margin-top: 0;">💳 Payment Methods Summary</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Count</th>
                            <th>Amount</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($paymentMethods) > 0): ?>
                            <?php foreach ($paymentMethods as $method): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($method['payment_method'] ?? 'Unknown'); ?></td>
                                <td><?php echo number_format($method['count']); ?></td>
                                <td>$<?php echo number_format($method['amount'], 2); ?></td>
                                <td><?php echo $totalRevenue > 0 ? number_format(($method['amount'] / $totalRevenue) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: #999;">No payment data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Detailed Sales Transactions -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h2 style="color: #6F4E37; margin-top: 0;">📋 All Sales Transactions</h2>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Customer</th>
                                <th>Time</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $transactionCount = 0;
                            $salesDetailsResult->data_seek(0);
                            while ($sale = $salesDetailsResult->fetch_assoc()): 
                                $transactionCount++;
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($sale['id']); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                <td><?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></td>
                                <td><span class="badge badge-info"><?php echo number_format($sale['item_count']); ?></span></td>
                                <td class="text-success font-weight-bold">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($sale['payment_method'] ?? 'Cash'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($transactionCount === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; color: #999;">No transactions found for this date</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Print Report Button -->
            <div style="display: flex; justify-content: flex-end; margin-top: 30px; margin-bottom: 20px;">
                <button onclick="window.print();" class="btn btn-primary" style="padding: 10px 24px; background-color: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem;">Print Report</button>
            </div>
        </div>
    </div>
</div>

<!-- PRINT-ONLY REPORT TEMPLATE -->
<div id="printReportTemplate" class="print-report-template" style="display: none;">
    <!-- Report Header -->
    <div class="report-header">
        <h1>VENDIX DAILY SALES REPORT</h1>
        <div class="report-period">Daily Report — <?php echo date('F d, Y', strtotime($selectedDate)); ?></div>
        <div class="report-date">Generated: <?php echo date('F d, Y \a\t H:i A'); ?></div>
    </div>

    <!-- Executive Summary -->
    <div class="report-section">
        <h2>EXECUTIVE SUMMARY</h2>
        <table class="data-table">
            <tr>
                <td class="label">Report Date:</td>
                <td class="value"><?php echo date('F d, Y', strtotime($selectedDate)); ?></td>
            </tr>
            <tr>
                <td class="label">Total Transactions:</td>
                <td class="value"><?php echo number_format($dailyStats['total_sales'] ?? 0); ?></td>
            </tr>
            <tr>
                <td class="label">Total Revenue:</td>
                <td class="value">$<?php echo number_format($dailyStats['total_revenue'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td class="label">Average Transaction:</td>
                <td class="value">$<?php echo number_format($dailyStats['avg_sale_value'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td class="label">Unique Customers:</td>
                <td class="value"><?php echo number_format($dailyStats['total_customers'] ?? 0); ?></td>
            </tr>
            <tr>
                <td class="label">Total Discounts:</td>
                <td class="value">$<?php echo number_format($dailyStats['total_discounts'] ?? 0, 2); ?></td>
            </tr>
        </table>
    </div>

    <!-- Hourly Sales Distribution -->
    <div class="report-section">
        <h2>HOURLY SALES DISTRIBUTION</h2>
        <table class="data-table-full">
            <thead>
                <tr>
                    <th>HOUR</th>
                    <th style="text-align: right;">SALES</th>
                    <th style="text-align: right;">REVENUE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hourlyData as $hour): ?>
                    <?php if ($hour['sales_count'] > 0): ?>
                    <tr>
                        <td><?php echo str_pad($hour['hour'], 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo str_pad($hour['hour'], 2, '0', STR_PAD_LEFT); ?>:59</td>
                        <td style="text-align: right;"><?php echo number_format($hour['sales_count']); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($hour['revenue'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Products -->
    <div class="report-section">
        <h2>TOP PRODUCTS SOLD</h2>
        <table class="data-table-full">
            <thead>
                <tr>
                    <th>PRODUCT NAME</th>
                    <th style="text-align: right;">QUANTITY</th>
                    <th style="text-align: right;">REVENUE</th>
                    <th style="text-align: right;">SALES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($topProducts) > 0): ?>
                    <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name'] ?? 'Unknown'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($product['quantity_sold']); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($product['revenue'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($product['number_of_sales']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 15px;">No products sold today</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Payment Methods -->
    <div class="report-section">
        <h2>PAYMENT METHODS ANALYSIS</h2>
        <table class="data-table-full">
            <thead>
                <tr>
                    <th>PAYMENT METHOD</th>
                    <th style="text-align: right;">COUNT</th>
                    <th style="text-align: right;">AMOUNT</th>
                    <th style="text-align: right;">PERCENTAGE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($paymentMethods) > 0): ?>
                    <?php 
                    $totalRev = isset($dailyStats['total_revenue']) ? $dailyStats['total_revenue'] : 0;
                    foreach ($paymentMethods as $method): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($method['payment_method'] ?? 'Unknown'); ?></td>
                        <td style="text-align: right;"><?php echo number_format($method['count']); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($method['amount'], 2); ?></td>
                        <td style="text-align: right;"><?php echo $totalRev > 0 ? number_format(($method['amount'] / $totalRev) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 15px;">No payment data available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Report Footer -->
    <div class="report-footer">
        <hr />
        <div class="footer-text">End of Report</div>
        <div class="footer-date">Vendix Ecommerce Management System</div>
    </div>
</div>

<style>
.summary-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.summary-card h3 {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
}

.summary-value {
    font-size: 2rem;
    font-weight: bold;
    margin: 0 0 5px 0;
}

.summary-label {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
}

.card-blue {
    border-left-color: #3b82f6;
}

.card-blue .summary-value {
    color: #3b82f6;
}

.card-green {
    border-left-color: #10b981;
}

.card-green .summary-value {
    color: #10b981;
}

.card-orange {
    border-left-color: #f97316;
}

.card-orange .summary-value {
    color: #f97316;
}

.card-purple {
    border-left-color: #8B6F47;
}

.card-purple .summary-value {
    color: #8B6F47;
}

.card-red {
    border-left-color: #ef4444;
}

.card-red .summary-value {
    color: #ef4444;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.report-table thead {
    background-color: #f3f4f6;
    border-bottom: 2px solid #e5e7eb;
}

.report-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.report-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.report-table tbody tr:hover {
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

.text-success {
    color: #16a34a;
}

.font-weight-bold {
    font-weight: bold;
}

.btn {
    border: none;
    cursor: pointer;
    font-size: 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn:hover {
    opacity: 0.9;
}

.btn-primary {
    background-color: #6F4E37;
    color: white;
    padding: 10px 20px;
}

.btn-secondary {
    background-color: #e5e7eb;
    color: #374151;
    padding: 10px 20px;
}

.screen-only {
    display: block;
}

.print-only {
    display: none;
}

@media print {
    @page {
        size: A4;
        margin: 14mm 12mm 16mm;
    }
    
    body {
        background: white;
        color: #111827;
        font-family: Arial, Helvetica, sans-serif;
        line-height: 1.4;
        padding: 0 !important;
        margin: 0 !important;
        font-size: 11px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body * {
        visibility: hidden;
        box-sizing: border-box;
    }
    
    .print-report-template {
        display: block !important;
        visibility: visible !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        background: white;
    }

    .print-report-template * {
        visibility: visible !important;
    }
    
    .report-header {
        text-align: left;
        margin-bottom: 20px;
        padding: 16px 18px 14px;
        border: 1px solid #cbd5e1;
        border-top: 4px solid #6F4E37;
        background: #f8fafc;
        position: relative;
    }
    
    .report-header::before {
        content: 'MANAGEMENT REPORTING PACK';
        display: block;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 1.4px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .report-header h1 {
        font-size: 22px;
        margin-bottom: 5px;
        font-weight: 700;
        letter-spacing: 0.3px;
        color: #111827;
    }
    
    .report-period {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.4px;
        margin-bottom: 4px;
        color: #374151;
    }
    
    .report-date {
        font-size: 10px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    
    .report-section {
        margin-bottom: 18px;
        page-break-inside: avoid;
    }
    
    .report-section h2 {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid #9ca3af;
        color: #111827;
    }
    
    .data-table {
        width: 62%;
        margin-bottom: 10px;
        border-collapse: collapse;
    }
    
    .data-table tr {
        page-break-inside: avoid;
    }
    
    .data-table td {
        padding: 6px 0;
        border: none;
        border-bottom: 1px solid #ddd;
        font-variant-numeric: tabular-nums;
    }
    
    .data-table td.label {
        font-weight: 600;
        width: 50%;
        padding-right: 15px;
    }
    
    .data-table td.value {
        text-align: right;
        font-weight: 500;
    }
    
    .data-table-full {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
        font-size: 10px;
    }
    
    .data-table-full thead {
        background-color: #e5e7eb;
    }
    
    .data-table-full th {
        padding: 8px 6px;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 9px;
        border: none;
        border-bottom: 1px solid #6b7280;
        color: #111827;
    }
    
    .data-table-full td {
        padding: 7px 6px;
        border: none;
        border-bottom: 1px solid #e5e7eb;
        color: #111827;
        font-variant-numeric: tabular-nums;
    }
    
    .data-table-full tbody tr:nth-child(even) td {
        background: #f9fafb;
    }
    
    .insights-list {
        margin-left: 0;
    }
    
    .insight-item {
        margin-bottom: 6px;
        font-size: 10px;
        line-height: 1.5;
    }
    
    .report-footer {
        margin-top: 24px;
        text-align: center;
        padding-top: 10px;
        border-top: 1px solid #9ca3af;
    }
    
    .footer-text {
        font-size: 10px;
        font-weight: 600;
        margin-bottom: 3px;
    }
    
    .footer-date {
        font-size: 9px;
        color: #6b7280;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hourly sales chart
const hourlyData = <?php echo json_encode($hourlyData); ?>;
const hours = hourlyData.map(d => d.hour + ':00');
const salesCount = hourlyData.map(d => parseInt(d.sales_count));
const revenue = hourlyData.map(d => parseFloat(d.revenue) || 0);

const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'line',
    data: {
        labels: hours,
        datasets: [
            {
                label: 'Sales Count',
                data: salesCount,
                borderColor: '#6F4E37',
                backgroundColor: 'rgba(111, 78, 55, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#6F4E37',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                yAxisID: 'y'
            },
            {
                label: 'Revenue ($)',
                data: revenue,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
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
                title: {
                    display: true,
                    text: 'Sales Count',
                    color: '#6F4E37'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Revenue ($)',
                    color: '#10b981'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Payment methods pie chart
const paymentData = <?php echo json_encode($paymentMethods); ?>;
const paymentLabels = paymentData.map(p => p.payment_method || 'Unknown');
const paymentAmounts = paymentData.map(p => parseFloat(p.amount) || 0);
const colors = ['#6F4E37', '#10b981', '#F97316', '#ef4444', '#3b82f6', '#8B6F47'];

const paymentCtx = document.getElementById('paymentChart').getContext('2d');
new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: paymentLabels,
        datasets: [{
            data: paymentAmounts,
            backgroundColor: colors.slice(0, paymentLabels.length),
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>


