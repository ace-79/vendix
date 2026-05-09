<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
requireAdmin(); // Only admins can manage users

$currentUserId = (int) $_SESSION['user_id'];

$heartbeatStmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
$heartbeatStmt->bind_param("i", $currentUserId);
$heartbeatStmt->execute();
$heartbeatStmt->close();

include '../includes/header.php';
include '../includes/navbar.php';

// Fetch all users
$usersStmt = $conn->prepare("
    SELECT id, username, role, status, last_seen, force_logout
    FROM users
    ORDER BY username ASC
");
$usersStmt->execute();
$users = $usersStmt->get_result();
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>User Management</h1>
            
            <!-- Add New User Button -->
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='block'">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            
            <!-- Users Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Presence</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <?php
                        $isCurrentUser = (int) $user['id'] === $currentUserId;
                        $isOnline = (int) ($user['force_logout'] ?? 0) !== 1
                            && !empty($user['last_seen'])
                            && strtotime($user['last_seen']) >= (time() - 300);
                        $statusValue = strtolower($user['status'] ?? 'active');
                        $usernameJs = json_encode($user['username'], JSON_HEX_APOS | JSON_HEX_QUOT);
                        $roleJs = json_encode($user['role'], JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                    <tr id="user-row-<?php echo (int) $user['id']; ?>"
                        data-user-id="<?php echo (int) $user['id']; ?>"
                        data-user-status="<?php echo htmlspecialchars($statusValue); ?>"
                        data-user-online="<?php echo $isOnline ? '1' : '0'; ?>"
                        data-is-self="<?php echo $isCurrentUser ? '1' : '0'; ?>">
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <span style="background: <?php 
                                $roleColors = [
                                    'admin' => '#dc2626',      // Red
                                    'manager' => '#d97706',    // Orange
                                    'inventory' => '#6F4E37',  // Brown (Stock)
                                    'cashier' => '#10b981'     // Green
                                ];
                                echo $roleColors[strtolower($user['role'])] ?? '#6b7280';
                            ?>; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td class="presence-cell">
                            <span class="presence-badge <?php echo $isOnline ? 'presence-online' : 'presence-offline'; ?>">
                                <?php echo $isOnline ? '&#x1F7E2; Online' : '&#x26AB; Offline'; ?>
                            </span>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge <?php echo $statusValue === 'blocked' ? 'status-blocked' : 'status-active'; ?>">
                                <?php echo $statusValue === 'blocked' ? 'Blocked' : 'Active'; ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <button class="btn btn-small btn-warning" onclick='editUser(<?php echo (int) $user["id"]; ?>, <?php echo $usernameJs; ?>, <?php echo $roleJs; ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-small btn-danger" onclick='deleteUser(<?php echo (int) $user["id"]; ?>, <?php echo $usernameJs; ?>)'>
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button
                                class="btn btn-small <?php echo $statusValue === 'blocked' ? 'btn-success' : 'btn-danger'; ?> action-toggle-status"
                                onclick='handleUserAction(<?php echo (int) $user["id"]; ?>, <?php echo json_encode($statusValue === "blocked" ? "unblock" : "block"); ?>, <?php echo $usernameJs; ?>)'
                                <?php echo $isCurrentUser ? 'disabled' : ''; ?>
                            >
                                <i class="fas <?php echo $statusValue === 'blocked' ? 'fa-unlock' : 'fa-ban'; ?>"></i>
                                <?php echo $statusValue === 'blocked' ? 'Unblock' : 'Block'; ?>
                            </button>
                            <button
                                class="btn btn-small btn-secondary action-force-logout"
                                onclick='handleUserAction(<?php echo (int) $user["id"]; ?>, "force_logout", <?php echo $usernameJs; ?>)'
                                <?php echo ($isCurrentUser || !$isOnline) ? 'disabled' : ''; ?>
                            >
                                <i class="fas fa-sign-out-alt"></i> Force Logout
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> <span id="modalTitle">Add New User</span></h2>
            <span class="close" onclick="document.getElementById('addUserModal').style.display='none'">&times;</span>
        </div>
        <form id="userForm" onsubmit="handleUserSubmit(event)">
            <input type="hidden" id="userId">
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" required>
                <small id="passwordHint">Password will be securely hashed with bcrypt. Minimum 6 characters.</small>
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="inventory">Inventory Manager</option>
                    <option value="cashier">Cashier</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success">Save User</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; }
.modal-header h2 { margin: 0; color: #6F4E37; }
.close { font-size: 28px; font-weight: bold; color: #999; cursor: pointer; }
.close:hover { color: #000; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
.form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
.form-group small { display: block; margin-top: 5px; color: #888; font-size: 12px; }
.btn-small { padding: 6px 12px; font-size: 12px; margin-right: 5px; }
.btn-small[disabled] { opacity: 0.6; cursor: not-allowed; }
.btn-warning { background-color: #d97706; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
.btn-warning:hover { background-color: #b45309; }
.btn-danger { background-color: #dc2626; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
.btn-danger:hover { background-color: #b91c1c; }
.btn-success { background-color: #16a34a; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
.btn-success:hover { background-color: #15803d; }
.btn-secondary { background-color: #6b7280; }
.btn-secondary:hover { background-color: #4b5563; }
.presence-badge, .status-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: bold; font-size: 12px; }
.presence-online { background-color: #dcfce7; color: #166534; }
.presence-offline { background-color: #e5e7eb; color: #374151; }
.status-active { background-color: #dcfce7; color: #166534; }
.status-blocked { background-color: #fee2e2; color: #991b1b; }
</style>

<script>
function editUser(id, username, role) {
    document.getElementById('userId').value = id;
    document.getElementById('username').value = username;
    document.getElementById('role').value = role;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordHint').textContent = 'Leave blank to keep current password';
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('addUserModal').style.display = 'block';
}

async function handleUserSubmit(e) {
    e.preventDefault();
    const userId = document.getElementById('userId').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const role = document.getElementById('role').value;
    
    if (!username || !role || (!userId && !password)) {
        alert('Please fill in all required fields');
        return;
    }
    
    const url = userId ? '../api/users.php' : '../api/users.php';
    const method = userId ? 'PUT' : 'POST';
    
    const payload = {
        username,
        role,
        ...(password && { password })
    };
    
    if (userId) {
        payload.id = userId;
    }
    
    try {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.status === 'success') {
            vendixNotifyAndReload('User saved successfully', 'success');
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function deleteUser(id, username) {
    const confirmed = await vendixConfirm(`Delete user "${username}"? This action cannot be undone.`, {
        title: 'Delete User',
        acceptLabel: 'Delete'
    });

    if (!confirmed) {
        return;
    }

    fetch(`../api/users.php?id=${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                vendixNotifyAndReload('User deleted successfully', 'success');
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

async function handleUserAction(userId, action, username) {
    const actionLabels = {
        block: 'Block',
        unblock: 'Unblock',
        force_logout: 'Force Logout'
    };

    const row = document.getElementById(`user-row-${userId}`);
    if (!row) {
        return;
    }

    if (row.dataset.isSelf === '1' && (action === 'block' || action === 'force_logout')) {
        alert('You cannot perform this action on your own account.');
        return;
    }

    const confirmed = await vendixConfirm(`${actionLabels[action]} user "${username}"?`, {
        title: `${actionLabels[action]} User`,
        acceptLabel: actionLabels[action]
    });

    if (!confirmed) {
        return;
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('user_id', userId);

    try {
        const response = await fetch('../ajax/user_action.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unknown error');
        }

        updateUserRow(row, result.user);
        vendixNotify(result.message, 'success');
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function updateUserRow(row, user) {
    const isSelf = row.dataset.isSelf === '1';
    const isOnline = user.is_online ? '1' : '0';
    const status = user.status === 'blocked' ? 'blocked' : 'active';

    row.dataset.userOnline = isOnline;
    row.dataset.userStatus = status;

    const presenceCell = row.querySelector('.presence-cell');
    const statusCell = row.querySelector('.status-cell');
    const toggleButton = row.querySelector('.action-toggle-status');
    const forceLogoutButton = row.querySelector('.action-force-logout');

    if (presenceCell) {
        presenceCell.innerHTML = user.is_online
            ? '<span class="presence-badge presence-online">&#x1F7E2; Online</span>'
            : '<span class="presence-badge presence-offline">&#x26AB; Offline</span>';
    }

    if (statusCell) {
        statusCell.innerHTML = status === 'blocked'
            ? '<span class="status-badge status-blocked">Blocked</span>'
            : '<span class="status-badge status-active">Active</span>';
    }

    if (toggleButton) {
        if (status === 'blocked') {
            toggleButton.classList.remove('btn-danger');
            toggleButton.classList.add('btn-success');
            toggleButton.innerHTML = '<i class="fas fa-unlock"></i> Unblock';
            toggleButton.setAttribute('onclick', `handleUserAction(${user.id}, 'unblock', '${escapeJsString(getUsernameFromRow(row))}')`);
        } else {
            toggleButton.classList.remove('btn-success');
            toggleButton.classList.add('btn-danger');
            toggleButton.innerHTML = '<i class="fas fa-ban"></i> Block';
            toggleButton.setAttribute('onclick', `handleUserAction(${user.id}, 'block', '${escapeJsString(getUsernameFromRow(row))}')`);
        }
        toggleButton.disabled = isSelf;
    }

    if (forceLogoutButton) {
        forceLogoutButton.disabled = isSelf || !user.is_online;
    }
}

function getUsernameFromRow(row) {
    const firstCell = row.querySelector('td');
    return firstCell ? firstCell.textContent.trim() : 'User';
}

function escapeJsString(value) {
    return String(value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'");
}
</script>

<?php
$usersStmt->close();
$conn->close();
// include '../includes/footer.php';
?>

