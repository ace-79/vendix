<!-- Mobile-only hamburger toggle -->
<button class="hamburger" id="toggleSidebar" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<?php
// Determine page title based on current file
$current_file = basename($_SERVER['PHP_SELF']);
$page_titles = [
    'index.php' => 'Dashboard',
    'pos.php' => 'Point of Sale',
    'sales.php' => 'Sales',
    'products.php' => 'Products',
    'stock.php' => 'Stock Management',
    'suppliers.php' => 'Suppliers',
    'purchase_orders.php' => 'Purchase Orders',
    'customers.php' => 'Customers',
    'reports.php' => 'Reports',
    'users.php' => 'Users',
    'activity_log.php' => 'Logs',
    'settings.php' => 'Settings',
    'permissions.php' => 'Roles & Permissions'
];
$page_title = $page_titles[$current_file] ?? 'Dashboard';
?>
<span class="page-title" id="pageTitle">Vendix <?php echo $page_title; ?></span>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isOpen = sidebar.classList.toggle('show');

        document.body.classList.toggle('sidebar-open', isOpen);

        if (overlay) {
            overlay.classList.toggle('show', isOpen);
        }
    }

    function closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (sidebar) {
            sidebar.classList.remove('show');
        }

        document.body.classList.remove('sidebar-open');

        if (overlay) {
            overlay.classList.remove('show');
        }
    }

    function setPageTitle(title) {
        document.getElementById('pageTitle').textContent = title.startsWith('Vendix ') ? title : `Vendix ${title}`;
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.sidebar .nav-link, .sidebar-logout').forEach(function(element) {
            element.addEventListener('click', function() {
                closeSidebar();
            });
        });
    });
</script>
