<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();
if (!hasPermission('manage_suppliers')) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to manage suppliers.');
}

// Get suppliers
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = array_merge($params, [$search, $search, $search, $search]);
}
if ($status_filter) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$query = "SELECT s.*, (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.id) as po_count FROM suppliers s";
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}
$query .= " ORDER BY name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h1><i class="fas fa-truck" style="margin-right: 8px;"></i>Suppliers</h1>
                <button class="btn btn-primary" onclick="showAddSupplier()"><i class="fas fa-plus"></i> Add Supplier</button>
            </div>

            <!-- Filter -->
            <div style="background-color: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb;">
                <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Search</label>
                        <input type="text" name="search" placeholder="Name, contact, phone, email..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </div>
                    <div style="min-width: 140px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Status</label>
                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">All</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="suppliers.php" class="btn" style="padding: 8px 15px; background-color: #e5e7eb; color: #333; text-decoration: none; border-radius: 4px;"><i class="fas fa-redo"></i> Clear</a>
                    </div>
                </form>
            </div>

            <!-- Suppliers Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>POs</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                            <i class="fas fa-truck" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                            No suppliers found. Add your first supplier to get started.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><span style="color: #888; font-size: 0.85rem;"><?php echo formatId($s['id'], 'supplier'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['contact_person'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($s['phone'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($s['email'] ?: '—'); ?></td>
                        <td><span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;"><?php echo $s['po_count']; ?></span></td>
                        <td>
                            <span style="background: <?php echo $s['status'] === 'active' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $s['status'] === 'active' ? '#166534' : '#991b1b'; ?>; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-info" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($s)); ?>)" style="padding: 5px 10px;"><i class="fas fa-edit"></i></button>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <button class="btn btn-danger" onclick="deleteSupplier(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>')" style="padding: 5px 10px;"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div id="supplierModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 id="supplierModalTitle"><i class="fas fa-truck" style="margin-right: 8px; color: #6F4E37;"></i>Add Supplier</h2>
        <form id="supplierForm">
            <input type="hidden" id="supplierId">
            <div class="form-group">
                <label>Supplier Name *</label>
                <input type="text" id="supplierName" required placeholder="e.g. TechParts Morocco">
            </div>
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" id="supplierContact" placeholder="e.g. Ahmed Benali">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="supplierPhone" placeholder="e.g. 0600000000">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="supplierEmail" placeholder="e.g. info@supplier.com">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea id="supplierAddress" rows="2" placeholder="Full address..."></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="supplierStatus">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Supplier</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 550px; max-height: 85vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
.close { color: #aaa; float: right; font-size: 28px; cursor: pointer; }
.close:hover { color: black; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #E8D9C8; border-radius: 8px; font-size: 0.95rem; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #8B6F47; }
.btn-secondary { background-color: #888; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
.btn-secondary:hover { background-color: #666; }
.btn-danger { background-color: #dc2626; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px; }
.btn-danger:hover { background-color: #b91c1c; }
</style>

<script>
function showAddSupplier() {
    document.getElementById('supplierId').value = '';
    document.getElementById('supplierForm').reset();
    document.getElementById('supplierStatus').value = 'active';
    document.getElementById('supplierModalTitle').innerHTML = '<i class="fas fa-truck" style="margin-right: 8px; color: #6F4E37;"></i>Add Supplier';
    document.getElementById('supplierModal').style.display = 'block';
}

function editSupplier(s) {
    document.getElementById('supplierId').value = s.id;
    document.getElementById('supplierName').value = s.name || '';
    document.getElementById('supplierContact').value = s.contact_person || '';
    document.getElementById('supplierPhone').value = s.phone || '';
    document.getElementById('supplierEmail').value = s.email || '';
    document.getElementById('supplierAddress').value = s.address || '';
    document.getElementById('supplierStatus').value = s.status || 'active';
    document.getElementById('supplierModalTitle').innerHTML = '<i class="fas fa-edit" style="margin-right: 8px; color: #6F4E37;"></i>Edit Supplier';
    document.getElementById('supplierModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('supplierModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target === document.getElementById('supplierModal')) closeModal();
}

document.getElementById('supplierForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('supplierId').value;
    const data = {
        name: document.getElementById('supplierName').value.trim(),
        contact_person: document.getElementById('supplierContact').value.trim(),
        phone: document.getElementById('supplierPhone').value.trim(),
        email: document.getElementById('supplierEmail').value.trim(),
        address: document.getElementById('supplierAddress').value.trim(),
        status: document.getElementById('supplierStatus').value
    };

    if (id) data.id = parseInt(id);

    try {
        const response = await fetch('../api/suppliers.php', {
            method: id ? 'PUT' : 'POST',
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

async function deleteSupplier(id, name) {
    const confirmed = typeof vendixConfirm === 'function'
        ? await vendixConfirm(`Delete supplier "${name}"? This cannot be undone.`, { title: 'Delete Supplier', acceptLabel: 'Delete' })
        : confirm(`Delete supplier "${name}"?`);

    if (!confirmed) return;

    try {
        const response = await fetch(`../api/suppliers.php?id=${id}`, { method: 'DELETE' });
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
}
</script>
