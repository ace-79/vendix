<div class="sidebar" id="sidebar">
    <!-- Brand Title at Top -->
    <div class="sidebar-brand">
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? '../index.php' : 'index.php'; ?>">
            <span class="sidebar-brand-text">Vendix</span>
        </a>
    </div>

    <!-- Navigation Links -->
    <ul class="nav">
        <?php if (hasPermission('view_dashboard')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'dashboard.php' : 'pages/dashboard.php'; ?>" onclick="setPageTitle('Dashboard')">
                <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>Dashboard
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_pos')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'pos.php' : 'pages/pos.php'; ?>" onclick="setPageTitle('POS')">
                <i class="fas fa-cash-register" style="margin-right: 10px; width: 20px;"></i>Point of Sale
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_sales')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'sales.php' : 'pages/sales.php'; ?>" onclick="setPageTitle('Sales')">
                <i class="fas fa-shopping-cart" style="margin-right: 10px; width: 20px;"></i>Sales
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_products')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'products.php' : 'pages/products.php'; ?>" onclick="setPageTitle('Products')">
                <i class="fas fa-boxes" style="margin-right: 10px; width: 20px;"></i>Products
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_stock')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'stock.php' : 'pages/stock.php'; ?>" onclick="setPageTitle('Stock Management')">
                <i class="fas fa-warehouse" style="margin-right: 10px; width: 20px;"></i>Stock Management
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('manage_suppliers')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'suppliers.php' : 'pages/suppliers.php'; ?>" onclick="setPageTitle('Suppliers')">
                <i class="fas fa-truck" style="margin-right: 10px; width: 20px;"></i>Suppliers
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_purchase_orders')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'purchase_orders.php' : 'pages/purchase_orders.php'; ?>" onclick="setPageTitle('Purchase Orders')">
                <i class="fas fa-file-invoice" style="margin-right: 10px; width: 20px;"></i>Purchase Orders
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_customers')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'customers.php' : 'pages/customers.php'; ?>" onclick="setPageTitle('Customers')">
                <i class="fas fa-users" style="margin-right: 10px; width: 20px;"></i>Customers
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_reports')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'reports.php' : 'pages/reports.php'; ?>" onclick="setPageTitle('Reports')">
                <i class="fas fa-chart-bar" style="margin-right: 10px; width: 20px;"></i>Reports
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_logs')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'activity_log.php' : 'pages/activity_log.php'; ?>" onclick="setPageTitle('Activity Logs')">
                <i class="fas fa-history" style="margin-right: 10px; width: 20px;"></i>Activity Logs
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('manage_users')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'users.php' : 'pages/users.php'; ?>" onclick="setPageTitle('Users')">
                <i class="fas fa-user-cog" style="margin-right: 10px; width: 20px;"></i>Users
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('manage_settings')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? 'settings.php' : 'pages/settings.php'; ?>" onclick="setPageTitle('Settings')">
                <i class="fas fa-cog" style="margin-right: 10px; width: 20px;"></i>Settings
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Profile & Logout at Bottom -->
    <div class="sidebar-bottom">
        <div class="sidebar-theme-toggle" onclick="toggleDarkMode()" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; margin-bottom: 12px; border-radius: 14px; background-color: #f3e8e0; cursor: pointer; transition: all 0.3s ease; color: #6F4E37;">
            <span style="font-weight: 600; font-size: 0.92rem;"><i class="fas fa-moon" style="margin-right: 8px;"></i> <span id="themeToggleText">Dark Mode</span></span>
            <div class="toggle-switch" style="width: 36px; height: 20px; background-color: #ccc; border-radius: 20px; position: relative;">
                <div class="toggle-knob" style="width: 16px; height: 16px; background-color: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: all 0.3s ease;"></div>
            </div>
        </div>

        <div class="sidebar-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
        </div>
        <form method="POST" action="<?php echo (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? '../logout.php' : 'logout.php'; ?>">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="sidebar-logout" title="Logout" style="width: 100%; border: none; cursor: pointer;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</div>

<script>
function toggleDarkMode() {
    const isDark = document.documentElement.classList.contains('theme-dark');
    if (isDark) {
        document.documentElement.classList.remove('theme-dark');
        const prevTheme = localStorage.getItem('vendix_theme_prev') || 'theme-default';
        if (prevTheme !== 'theme-default') document.documentElement.classList.add(prevTheme);
        localStorage.setItem('vendix_theme', prevTheme);
    } else {
        const currentTheme = localStorage.getItem('vendix_theme') || 'theme-default';
        if (currentTheme !== 'theme-dark') localStorage.setItem('vendix_theme_prev', currentTheme);
        document.documentElement.className = '';
        document.documentElement.classList.add('theme-dark');
        localStorage.setItem('vendix_theme', 'theme-dark');
    }
    updateDarkModeToggleUI();
    
    // If we're on the settings page, update the theme selector UI
    if (typeof updateActiveThemeSelector === 'function') {
        updateActiveThemeSelector(localStorage.getItem('vendix_theme'));
    }
}

function updateDarkModeToggleUI() {
    const isDark = document.documentElement.classList.contains('theme-dark');
    const toggleBg = document.querySelector('.sidebar-theme-toggle');
    const switchBg = document.querySelector('.toggle-switch');
    const knob = document.querySelector('.toggle-knob');
    const icon = document.querySelector('.sidebar-theme-toggle i');
    const text = document.getElementById('themeToggleText');
    
    if (toggleBg && switchBg && knob && icon && text) {
        if (isDark) {
            toggleBg.style.backgroundColor = '#333';
            toggleBg.style.color = '#bb86fc';
            switchBg.style.backgroundColor = '#bb86fc';
            knob.style.left = '18px';
            icon.className = 'fas fa-sun';
            text.textContent = 'Light Mode';
        } else {
            // Check if another theme is active to adjust colors slightly, or just use default
            toggleBg.style.backgroundColor = '';
            toggleBg.style.color = '';
            switchBg.style.backgroundColor = '#ccc';
            knob.style.left = '2px';
            icon.className = 'fas fa-moon';
            text.textContent = 'Dark Mode';
        }
    }
}

// Call on load
document.addEventListener('DOMContentLoaded', updateDarkModeToggleUI);
// Also call immediately in case DOMContentLoaded already fired
updateDarkModeToggleUI();
</script>
