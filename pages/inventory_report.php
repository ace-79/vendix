<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
if (!canViewReports()) {
    die('Access denied: Reports are for managers and admins only');
}

// Stats
$stats = $conn->query("SELECT 
    COUNT(*) as total_products,
    SUM(stock) as total_units,
    SUM(stock * cost_price) as total_cost_value,
    SUM(stock * price) as total_retail_value,
    SUM(CASE WHEN stock <= min_stock THEN 1 ELSE 0 END) as low_stock_count
    FROM products")->fetch_assoc();

// Stock Value by Category
$categoryStats = $conn->query("SELECT 
    COALESCE(category, 'Uncategorized') as category, 
    SUM(stock * cost_price) as cost_value,
    SUM(stock * price) as retail_value,
    SUM(stock) as units
    FROM products 
    GROUP BY category 
    ORDER BY cost_value DESC");

// Recent Movements (Last 30 Days)
$movements = $conn->query("SELECT 
    movement_type, 
    SUM(ABS(quantity)) as total_qty,
    COUNT(*) as count
    FROM stock_movements 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY movement_type");

$movementData = [];
while($row = $movements->fetch_assoc()) $movementData[] = $row;

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
                <h1><i class="fas fa-boxes" style="margin-right:10px;"></i> Inventory Report</h1>
                <div style="display:flex; gap:10px;">
                    <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print Report</button>
                    <a href="reports.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Reports</a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="report-stats-grid">
                <div class="report-stat-card">
                    <div class="icon" style="background:#dbeafe; color:#1e40af;"><i class="fas fa-box"></i></div>
                    <div class="data">
                        <span class="label">Total Products</span>
                        <span class="value"><?php echo number_format($stats['total_products']); ?></span>
                    </div>
                </div>
                <div class="report-stat-card">
                    <div class="icon" style="background:#dcfce7; color:#166534;"><i class="fas fa-cubes"></i></div>
                    <div class="data">
                        <span class="label">Total Units</span>
                        <span class="value"><?php echo number_format($stats['total_units']); ?></span>
                    </div>
                </div>
                <div class="report-stat-card">
                    <div class="icon" style="background:#fef3c7; color:#92400e;"><i class="fas fa-dollar-sign"></i></div>
                    <div class="data">
                        <span class="label">Stock Value (Cost)</span>
                        <span class="value">$<?php echo number_format($stats['total_cost_value'], 2); ?></span>
                    </div>
                </div>
                <div class="report-stat-card">
                    <div class="icon" style="background:#fee2e2; color:#991b1b;"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="data">
                        <span class="label">Low Stock Items</span>
                        <span class="value" style="color:#dc2626;"><?php echo number_format($stats['low_stock_count']); ?></span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                <!-- Category Breakdown -->
                <div class="report-section">
                    <h3><i class="fas fa-chart-pie"></i> Value by Category</h3>
                    <div style="height: 300px;">
                        <canvas id="categoryValueChart"></canvas>
                    </div>
                </div>

                <!-- Movement Trends -->
                <div class="report-section">
                    <h3><i class="fas fa-exchange-alt"></i> 30-Day Movement Volume</h3>
                    <div style="height: 300px;">
                        <canvas id="movementVolumeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Table -->
            <div class="report-section" style="margin-top: 30px;">
                <h3><i class="fas fa-list"></i> Category Detailed Breakdown</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Units</th>
                            <th>Value (Cost)</th>
                            <th>Value (Retail)</th>
                            <th>Potential Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($c = $categoryStats->fetch_assoc()): 
                            $profit = $c['retail_value'] - $c['cost_value'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['category']); ?></strong></td>
                            <td><?php echo number_format($c['units']); ?></td>
                            <td>$<?php echo number_format($c['cost_value'], 2); ?></td>
                            <td>$<?php echo number_format($c['retail_value'], 2); ?></td>
                            <td style="color:#16a34a; font-weight:700;">$<?php echo number_format($profit, 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$categoryRows = [];
$categoryStats->data_seek(0);
while ($row = $categoryStats->fetch_assoc()) {
    $categoryRows[] = $row;
}
?>

<div id="printReportTemplate" class="print-report-template" style="display: none;">
    <div class="report-header">
        <h1>VENDIX INVENTORY POSITION REPORT</h1>
        <div class="report-period">Stock Position as of <?php echo date('F d, Y'); ?></div>
        <div class="report-date">Generated: <?php echo date('F d, Y \a\t H:i A'); ?></div>
    </div>

    <div class="report-section">
        <h2>EXECUTIVE SUMMARY</h2>
        <table class="data-table">
            <tr><td class="label">Total Products</td><td class="value"><?php echo number_format($stats['total_products']); ?></td></tr>
            <tr><td class="label">Total Units</td><td class="value"><?php echo number_format($stats['total_units']); ?></td></tr>
            <tr><td class="label">Stock Value at Cost</td><td class="value">$<?php echo number_format($stats['total_cost_value'], 2); ?></td></tr>
            <tr><td class="label">Stock Value at Retail</td><td class="value">$<?php echo number_format($stats['total_retail_value'], 2); ?></td></tr>
            <tr><td class="label">Potential Margin</td><td class="value">$<?php echo number_format(($stats['total_retail_value'] ?? 0) - ($stats['total_cost_value'] ?? 0), 2); ?></td></tr>
            <tr><td class="label">Low Stock Items</td><td class="value"><?php echo number_format($stats['low_stock_count']); ?></td></tr>
        </table>
    </div>

    <div class="report-section">
        <h2>CATEGORY BREAKDOWN</h2>
        <table class="data-table-full">
            <thead>
                <tr>
                    <th>CATEGORY</th>
                    <th style="text-align: right;">UNITS</th>
                    <th style="text-align: right;">COST VALUE</th>
                    <th style="text-align: right;">RETAIL VALUE</th>
                    <th style="text-align: right;">POTENTIAL PROFIT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryRows as $c): $profit = $c['retail_value'] - $c['cost_value']; ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['category']); ?></td>
                    <td style="text-align: right;"><?php echo number_format($c['units']); ?></td>
                    <td style="text-align: right;">$<?php echo number_format($c['cost_value'], 2); ?></td>
                    <td style="text-align: right;">$<?php echo number_format($c['retail_value'], 2); ?></td>
                    <td style="text-align: right;">$<?php echo number_format($profit, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <h2>30-DAY MOVEMENT SUMMARY</h2>
        <table class="data-table-full">
            <thead>
                <tr>
                    <th>MOVEMENT TYPE</th>
                    <th style="text-align: right;">ENTRIES</th>
                    <th style="text-align: right;">UNITS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($movementData) > 0): ?>
                    <?php foreach ($movementData as $movement): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(ucfirst($movement['movement_type'])); ?></td>
                        <td style="text-align: right;"><?php echo number_format($movement['count']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($movement['total_qty']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align: center; padding: 15px;">No movement data available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="report-footer">
        <div class="footer-text">Prepared by Vendix Reporting Suite</div>
        <div class="footer-date">Confidential internal business document</div>
    </div>
</div>

<style>
.report-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.report-stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    gap: 15px;
}
.report-stat-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.report-stat-card .label {
    display: block;
    font-size: 0.8rem;
    color: #888;
    text-transform: uppercase;
    font-weight: 600;
}
.report-stat-card .value {
    display: block;
    font-size: 1.4rem;
    font-weight: 700;
    color: #333;
}
.report-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.report-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #6F4E37;
    font-size: 1.1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
.report-table {
    width: 100%;
    border-collapse: collapse;
}
.report-table th {
    text-align: left;
    padding: 12px;
    background: #f9fafb;
    border-bottom: 2px solid #eee;
    font-size: 0.85rem;
    color: #666;
}
.report-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    font-size: 0.95rem;
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
        font-size: 11px;
        line-height: 1.4;
        margin: 0 !important;
        padding: 0 !important;
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
        margin: 0 0 5px 0;
        font-size: 22px;
        color: #111827;
    }

    .report-period {
        font-size: 12px;
        font-weight: 700;
        color: #374151;
        margin-bottom: 4px;
    }

    .report-date {
        font-size: 10px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .report-section {
        display: block !important;
        background: transparent;
        border: none;
        box-shadow: none;
        padding: 0;
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
        border-collapse: collapse;
    }

    .data-table td {
        padding: 6px 0;
        border-bottom: 1px solid #ddd;
        font-variant-numeric: tabular-nums;
    }

    .data-table .label {
        font-weight: 600;
        width: 50%;
        padding-right: 15px;
    }

    .data-table .value {
        text-align: right;
    }

    .data-table-full {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }

    .data-table-full thead {
        background: #e5e7eb;
    }

    .data-table-full th {
        padding: 8px 6px;
        text-align: left;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #6b7280;
    }

    .data-table-full td {
        padding: 7px 6px;
        border-bottom: 1px solid #e5e7eb;
        font-variant-numeric: tabular-nums;
    }

    .data-table-full tbody tr:nth-child(even) td {
        background: #f9fafb;
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
const categoryData = <?php 
    echo json_encode($categoryRows); 
?>;

const movementData = <?php echo json_encode($movementData); ?>;

// Category Chart
new Chart(document.getElementById('categoryValueChart'), {
    type: 'pie',
    data: {
        labels: categoryData.map(c => c.category),
        datasets: [{
            data: categoryData.map(c => c.cost_value),
            backgroundColor: [
                '#6F4E37', '#8B6F47', '#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Movement Chart
new Chart(document.getElementById('movementVolumeChart'), {
    type: 'bar',
    data: {
        labels: movementData.map(m => m.movement_type.charAt(0).toUpperCase() + m.movement_type.slice(1)),
        datasets: [{
            label: 'Volume (Units)',
            data: movementData.map(m => m.total_qty),
            backgroundColor: movementData.map(m => {
                if(m.movement_type === 'sale') return '#991b1b';
                if(m.movement_type === 'purchase') return '#166534';
                if(m.movement_type === 'adjustment') return '#1e40af';
                return '#666';
            })
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
