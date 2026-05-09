<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';

requireLogin();

// Permission check
if (!hasPermission('view_customers')) {
    header("HTTP/1.0 403 Forbidden");
    die('Access Denied: You do not have permission to access customer information.');
}

include '../includes/header.php';
include '../includes/navbar.php';

// Get all customers
$result = $conn->query("SELECT * FROM customers ORDER BY name ASC");
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Customers Management</h1>
            <button class="btn btn-primary" onclick="showAddCustomerModal()"><i class="fas fa-plus"></i> Add New Customer</button>
            
            <!-- Instant Search Panel -->
            <div style="margin: 20px 0;">
                <input type="text" id="customerSearch" placeholder="Search by name or phone..." style="width: 100%; padding: 12px; border: 2px solid #E8D9C8; border-radius: 4px; font-size: 1rem;">
                <small style="color: #666; display: block; margin-top: 5px;">Results update instantly as you type</small>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customerTableBody">
                    <?php foreach ($customers as $c): ?>
                    <tr class="customer-row" data-name="<?php echo strtolower(htmlspecialchars($c['name'])); ?>" data-phone="<?php echo strtolower(htmlspecialchars($c['phone'] ?? '')); ?>">
                        <td><?php echo formatId($c['id'], 'customer'); ?></td>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><?php echo htmlspecialchars($c['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($c['phone'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-info" onclick="editCustomer(this)" data-id="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>" data-email="<?php echo htmlspecialchars($c['email'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($c['phone'] ?? ''); ?>"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger" onclick="deleteCustomer(<?php echo $c['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="noCustomersMessage" style="text-align: center; padding: 20px; color: #999; display: none;">
                No customers found matching your search.
            </div>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div id="customerModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeCustomerModal()">&times;</span>
        <h2 id="modalTitle">Add New Customer</h2>
        <form id="customerForm">
            <input type="hidden" id="customerId">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" id="customerName" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="customerEmail">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" id="customerPhone">
            </div>
            <button type="submit" class="btn btn-primary">Save Customer</button>
            <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">Cancel</button>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.close { color: #aaa; float: right; font-size: 28px; cursor: pointer; }
.close:hover { color: black; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
.form-group input { width: 100%; padding: 10px; border: 2px solid #E8D9C8; border-radius: 4px; }
.form-group input:focus { outline: none; border: 2px solid #8B6F47; }
.btn-secondary { background-color: #888; color: white; margin-left: 10px; }
.btn-danger { background-color: #dc2626; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px; }
</style>

<script>
// Instant Search Functionality
document.getElementById('customerSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const phone = row.dataset.phone;
        
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide "no results" message
    const noMessage = document.getElementById('noCustomersMessage');
    if (visibleCount === 0 && searchTerm !== '') {
        noMessage.style.display = 'block';
    } else {
        noMessage.style.display = 'none';
    }
});

function showAddCustomerModal() {
    document.getElementById('customerId').value = '';
    document.getElementById('customerForm').reset();
    document.getElementById('modalTitle').textContent = 'Add New Customer';
    document.getElementById('customerModal').style.display = 'block';
}

function editCustomer(button) {
    document.getElementById('customerId').value = button.dataset.id;
    document.getElementById('customerName').value = button.dataset.name || '';
    document.getElementById('customerEmail').value = button.dataset.email || '';
    document.getElementById('customerPhone').value = button.dataset.phone || '';
    document.getElementById('modalTitle').textContent = 'Edit Customer';
    document.getElementById('customerModal').style.display = 'block';
}

function closeCustomerModal() {
    document.getElementById('customerModal').style.display = 'none';
}

window.onclick = function(e) {
    const modal = document.getElementById('customerModal');
    if (e.target == modal) modal.style.display = 'none';
}

document.getElementById('customerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('customerId').value;
    const data = {
        name: document.getElementById('customerName').value,
        email: document.getElementById('customerEmail').value,
        phone: document.getElementById('customerPhone').value
    };
    
    if (id) data.id = parseInt(id);
    
    try {
        const response = await fetch('../api/customers.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.status === 'success') {
            vendixNotifyAndReload(result.message, 'success');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

async function deleteCustomer(id) {
    const confirmed = await vendixConfirm('Delete this customer?', {
        title: 'Delete Customer',
        acceptLabel: 'Delete'
    });

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(`../api/customers.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        vendixNotifyAndReload(result.message, 'success');
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>


