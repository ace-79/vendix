<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied: Permissions management is for admins only');
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    requireCsrfToken();

    if (isset($_POST['perm']) && is_array($_POST['perm'])) {
        foreach ($_POST['perm'] as $role => $perms) {
            foreach ($perms as $perm_key => $is_allowed) {
                $val = intval($is_allowed);
                $stmt = $conn->prepare("UPDATE role_permissions SET is_allowed = ? WHERE LOWER(role_name) = LOWER(?) AND permission_key = ?");
                $stmt->bind_param("iss", $val, $role, $perm_key);
                $stmt->execute();
                $stmt->close();
            }
        }
        $success_msg = "Permissions updated successfully!";
    }
}

// Get roles and permissions
$roles = ['manager', 'inventory', 'cashier'];
$perms_list = [
    'view_dashboard' => 'View Dashboard',
    'view_pos' => 'Access POS',
    'view_sales' => 'View Sales',
    'view_products' => 'Manage Products',
    'view_stock' => 'View Stock Management',
    'adjust_stock' => 'Adjust Stock',
    'manage_suppliers' => 'Manage Suppliers',
    'view_purchase_orders' => 'View Purchase Orders',
    'create_purchase_orders' => 'Create Purchase Orders',
    'receive_purchase_orders' => 'Receive Purchase Orders',
    'view_customers' => 'Manage Customers',
    'view_reports' => 'View Reports',
    'view_logs' => 'View Activity Logs',
    'manage_users' => 'Manage Users',
    'manage_settings' => 'System Settings'
];

$permissions = [];
$res = $conn->query("SELECT LOWER(role_name) as role_name, permission_key, is_allowed FROM role_permissions");
while ($row = $res->fetch_assoc()) {
    $permissions[$row['role_name']][$row['permission_key']] = $row['is_allowed'];
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Roles & Permissions</h1>
                <a href="settings.php" class="btn btn-back">← Back to Settings</a>
            </div>

            <?php if (isset($success_msg)): ?>
                <div
                    style="background-color: #dcfce7; color: #166534; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <div style="background: transparent; box-shadow: none; border: none; padding: 0;">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <table class="table perms-table">
                        <thead>
                            <tr>
                                <th>Permission Module</th>
                                <?php foreach ($roles as $role): ?>
                                    <th style="text-align: center;"><?php echo ucfirst($role); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perms_list as $key => $label): ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <?php foreach ($roles as $role): ?>
                                        <td style="text-align: center;">
                                            <input type="hidden" name="perm[<?php echo $role; ?>][<?php echo $key; ?>]" value="0">
                                            <label class="perms-switch">
                                                <input type="checkbox" name="perm[<?php echo $role; ?>][<?php echo $key; ?>]" value="1" <?php echo ($permissions[$role][$key] ?? 0) ? 'checked' : ''; ?>>
                                                <span class="perms-slider"></span>
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 30px; text-align: right;">
                        <button type="submit" name="update_permissions" class="btn btn-primary" style="padding: 15px 45px; font-size: 1.1rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(111, 78, 55, 0.2);">
                            <i class="fas fa-save" style="margin-right: 10px;"></i> Save All Permissions
                        </button>
                    </div>
                </form>
            </div>

            <div
                style="margin-top: 35px; background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 25px; border-radius: 10px; color: #92400e; box-shadow: 0 2px 10px rgba(245, 158, 11, 0.1);">
                <h4 style="margin-top: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;"><i
                        class="fas fa-exclamation-triangle"></i> Important Note</h4>
                <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;">Admin role has full access to all features
                    by default and cannot be restricted. Changes to permissions will take effect the next time a user
                    navigates to a protected page.</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Toggle Switch Style */
    .switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #e2e8f0;
        transition: .3s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    input:checked+.slider {
        background-color: #6F4E37;
    }

    input:focus+.slider {
        box-shadow: 0 0 1px #6F4E37;
    }

    input:checked+.slider:before {
        transform: translateX(22px);
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }
</style>
