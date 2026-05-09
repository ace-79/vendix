<?php
session_start();
include '../config/db.php';
include '../config/auth.php';

requireLogin();
if (!canViewReports()) {
    die('Access denied: Reports are for managers and admins only');
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Reports</h1>
            
            <!-- Report Generator Section -->
            <div style="max-width: 800px;">
                <div style="background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%); padding: 40px; border-radius: 12px; color: white; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(111, 78, 55, 0.3);">
                    <h2 style="margin: 0 0 10px 0; font-size: 1.8rem; color: white;">📊 Generate Custom Reports</h2>
                    <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Select a report type to access detailed analytics and insights</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Daily Report Button -->
                    <a href="daily_report.php" class="report-btn report-btn-daily">
                        <div class="report-btn-icon">📅</div>
                        <div class="report-btn-content">
                            <h3>Daily Sales Report</h3>
                            <p>View detailed sales data for a specific date with filters and insights</p>
                        </div>
                        <div class="report-btn-arrow">→</div>
                    </a>
                    
                    <!-- Monthly Report Button -->
                    <a href="monthly_report.php" class="report-btn report-btn-monthly">
                        <div class="report-btn-icon">📈</div>
                        <div class="report-btn-content">
                            <h3>Monthly Sales Report</h3>
                            <p>Analyze monthly trends, revenue, and performance metrics</p>
                        </div>
                        <div class="report-btn-arrow">→</div>
                    </a>
                    
                    <!-- Yearly Report Button -->
                    <a href="yearly_report.php" class="report-btn report-btn-yearly">
                        <div class="report-btn-icon">📊</div>
                        <div class="report-btn-content">
                            <h3>Yearly Sales Report</h3>
                            <p>Review annual performance with comprehensive data and charts</p>
                        </div>
                        <div class="report-btn-arrow">→</div>
                    </a>

                    <!-- Inventory Report Button -->
                    <a href="inventory_report.php" class="report-btn report-btn-inventory">
                        <div class="report-btn-icon">📦</div>
                        <div class="report-btn-content">
                            <h3>Inventory Report</h3>
                            <p>Detailed stock analysis, movement trends, and value breakdown</p>
                        </div>
                        <div class="report-btn-arrow">→</div>
                    </a>
                </div>
            </div>
            
            <!-- Info Box -->
            <div style="margin-top: 40px; background-color: #f0f9ff; border-left: 4px solid #3b82f6; padding: 20px; border-radius: 8px; color: #1e40af;">
                <h4 style="margin-top: 0;">💡 Tips</h4>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li>Each report includes filters to narrow down your data</li>
                    <li>View charts and detailed tables for deeper insights</li>
                    <li>Export features will help you share reports with stakeholders</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.report-btn {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.report-btn:hover {
    border-color: #6F4E37;
    box-shadow: 0 4px 16px rgba(111, 78, 55, 0.15);
    transform: translateY(-4px);
}

.report-btn-icon {
    font-size: 2.5rem;
    flex-shrink: 0;
}

.report-btn-content {
    flex: 1;
}

.report-btn-content h3 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    color: #6F4E37;
    font-weight: 600;
}

.report-btn-content p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
    line-height: 1.4;
}

.report-btn-arrow {
    font-size: 1.5rem;
    color: #9ca3af;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.report-btn:hover .report-btn-arrow {
    color: #6F4E37;
    transform: translateX(5px);
}

.report-btn-daily {
    background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
}

.report-btn-daily:hover {
    border-color: #3b82f6;
}

.report-btn-monthly {
    background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
}

.report-btn-monthly:hover {
    border-color: #10b981;
}

.report-btn-yearly {
    background: linear-gradient(135deg, #fce7f3 0%, #fecdd3 100%);
}

.report-btn-yearly:hover {
    border-color: #ec4899;
}

.report-btn-inventory {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.report-btn-inventory:hover {
    border-color: #f59e0b;
}

@media print {
    body {
        background-color: white;
        margin: 0;
        padding: 0;
    }
    
    .navbar,
    .sidebar,
    .hamburger,
    .btn,
    input[type="date"],
    input[type="text"],
    select,
    label,
    .back-to-reports,
    .load-report-btn,
    .print-report-btn,
    .report-controls,
    .control-group,
    .report-selection {
        display: none !important;
    }
    
    .main-container {
        margin-left: 0;
        display: block;
    }
    
    .content-area {
        margin-left: 0;
        padding: 0;
    }
    
    .content-wrapper {
        max-width: 100%;
        padding: 0;
        margin: 0;
    }
}
</style>

