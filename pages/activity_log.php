<?php
session_start();
include '../config/db.php';
include '../config/auth.php';

// Permission check
if (!hasPermission('view_logs')) {
    header("Location: ../index.php");
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1>Activity Logs</h1>
            <p style="color: #666; margin-bottom: 20px;">Track and monitor all system activities and user actions</p>

            <!-- Filter Bar -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <h3 style="margin-top: 0; color: #6F4E37;">Filters</h3>
                <form id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">Action Type:</label>
                        <select id="actionType" name="action_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="ALL">All Actions</option>
                            <option value="LOGIN">Login</option>
                            <option value="LOGOUT">Logout</option>
                            <option value="CREATE">Create</option>
                            <option value="UPDATE">Update</option>
                            <option value="DELETE">Delete</option>
                            <option value="SALE">Sale</option>
                            <option value="PAYMENT">Payment</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">Entity Type:</label>
                        <select id="entityType" name="entity_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="ALL">All Entities</option>
                            <option value="auth">Authentication</option>
                            <option value="product">Product</option>
                            <option value="sale">Sale</option>
                            <option value="payment">Payment</option>
                            <option value="user">User</option>
                            <option value="customer">Customer</option>
                            <option value="settings">Settings</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">From Date:</label>
                        <input type="date" id="dateFrom" name="date_from" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">To Date:</label>
                        <input type="date" id="dateTo" name="date_to" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="loadLogs()" class="btn btn-primary" style="background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%); flex: 1;">
                            <i class="fas fa-search"></i> Apply Filter
                        </button>
                        <button type="button" onclick="resetFilters()" class="btn" style="background-color: #e5e7eb; color: #333; flex: 1;">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Activity Table -->
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden;">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%);">
                            <th style="color: white;">Date & Time</th>
                            <th style="color: white;">User</th>
                            <th style="color: white;">Action</th>
                            <th style="color: white;">Entity Type</th>
                            <th style="color: white;">Entity ID</th>
                            <th style="color: white;">Changes</th>
                            <th style="color: white;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-right: 10px;"></i>Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="paginationContainer" style="margin-top: 20px; display: none; text-align: center;">
                <div id="paginationInfo" style="margin-bottom: 15px; color: #666;"></div>
                <div id="paginationButtons" style="display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
const logsPerPage = 20;

// Action type badge colors
const actionColors = {
    'LOGIN': '#10b981',
    'LOGOUT': '#6b7280',
    'CREATE': '#3b82f6',
    'UPDATE': '#f97316',
    'DELETE': '#ef4444',
    'SALE': '#8b5cf6',
    'PAYMENT': '#06b6d4'
};

function getActionBadge(action) {
    const color = actionColors[action] || '#6F4E37';
    return `<span style="display: inline-block; background-color: ${color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">${action}</span>`;
}

function getEntityType(entityType) {
    return entityType || 'N/A';
}

function getEntityId(entityId, logData = null) {
    // For payments, show the payment ID linked with sale ID
    if (logData && logData.entity_type === 'payment') {
        let saleId = null;
        
        // Get sale_id from new_value (for CREATE and UPDATE actions)
        if (logData.new_value && logData.new_value.sale_id) {
            saleId = logData.new_value.sale_id;
        }
        // Get sale_id from old_value (for DELETE actions)
        else if (logData.old_value && logData.old_value.sale_id) {
            saleId = logData.old_value.sale_id;
        }
        
        // Show payment ID if available, linked with sale
        if (entityId && saleId) {
            return `#${entityId} (Sale #${saleId})`;
        } else if (saleId) {
            // Show just sale ID if entity_id is not available
            return `Sale #${saleId}`;
        } else if (entityId) {
            return `#${entityId}`;
        }
    }
    
    // For other entity types
    if (!entityId) return 'N/A';
    return `#${entityId}`;
}

function formatChanges(oldValue, newValue, description, actionType) {
    if (!oldValue && !newValue && !description) return 'N/A';
    
    // Priority fields to display
    const priorityFields = ['name', 'price', 'cost_price', 'stock', 'min_stock', 'username', 'email', 'phone', 'amount', 'method', 'payment_status'];
    
    // For UPDATE actions: show field changes
    if (oldValue && newValue && actionType === 'UPDATE') {
        let changes = [];
        for (let key in newValue) {
            if (oldValue[key] !== newValue[key]) {
                const fieldName = key.replace(/_/g, ' ');
                changes.push(`<small><strong>${fieldName}:</strong> "${oldValue[key]}" → "${newValue[key]}"</small>`);
            }
        }
        if (changes.length > 0) {
            return changes.join('<br>');
        }
    }
    
    // For DELETE actions: show what was deleted
    if (oldValue && actionType === 'DELETE') {
        let details = [];
        for (let key of priorityFields) {
            if (oldValue[key]) {
                details.push(`${key}: ${oldValue[key]}`);
            }
        }
        if (details.length > 0) {
            return `<small style="color: #d32f2f;"><strong>Deleted:</strong> ${details.join(', ')}</small>`;
        }
    }
    
    // For CREATE actions: show key details
    if (newValue && actionType === 'CREATE') {
        let details = [];
        for (let key of priorityFields) {
            if (newValue[key]) {
                details.push(`${key}: ${newValue[key]}`);
            }
        }
        if (details.length > 0) {
            return `<small style="color: #2e7d32;"><strong>Created:</strong> ${details.join(', ')}</small>`;
        }
    }
    
    // Fallback to description
    if (description) {
        return `<small style="color: #666;">${description}</small>`;
    }
    
    return 'N/A';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit'
    });
}

function loadLogs(page = 1) {
    currentPage = page;
    
    const actionType = document.getElementById('actionType').value;
    const entityType = document.getElementById('entityType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;

    const params = new URLSearchParams({
        action_type: actionType,
        entity_type: entityType,
        date_from: dateFrom,
        date_to: dateTo,
        page: page,
        limit: logsPerPage
    });

    fetch(`../api/activity_logs.php?${params}`, {
        method: 'GET',
        credentials: 'include'  // Send cookies with the request
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayLogs(data.data);
                displayPagination(data.total, data.total_pages, data.page);
            } else {
                console.error('API Error:', data.error);
                alert('Error loading logs: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('Error loading activity logs: ' + error.message);
        });
}

function displayLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #999;">No activity logs found</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => `
        <tr>
            <td style="white-space: nowrap;">${formatDate(log.created_at)}</td>
            <td>${log.username || '<span style="color: #999;">Unknown</span>'}</td>
            <td>${getActionBadge(log.action_type)}</td>
            <td>${getEntityType(log.entity_type)}</td>
            <td>${getEntityId(log.entity_id, log)}</td>
            <td>${formatChanges(log.old_value, log.new_value, log.description, log.action_type)}</td>
            <td style="font-size: 12px; color: #666;">${log.ip_address || 'N/A'}</td>
        </tr>
    `).join('');
}

function displayPagination(total, totalPages, currentPage) {
    const container = document.getElementById('paginationContainer');
    const info = document.getElementById('paginationInfo');
    const buttons = document.getElementById('paginationButtons');

    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    info.textContent = `Showing results 1-${logsPerPage} of ${total} total`;

    let html = '';
    
    // Previous button
    if (currentPage > 1) {
        html += `<button onclick="loadLogs(${currentPage - 1})" class="btn" style="background-color: #e5e7eb; color: #333; padding: 8px 12px;">← Previous</button>`;
    }

    // Page numbers
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        if (i === currentPage) {
            html += `<button style="background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%); color: white; padding: 8px 12px; border-radius: 4px; border: none; cursor: default; font-weight: 600;">${i}</button>`;
        } else {
            html += `<button onclick="loadLogs(${i})" class="btn" style="background-color: #e5e7eb; color: #333; padding: 8px 12px;">${i}</button>`;
        }
    }

    // Next button
    if (currentPage < totalPages) {
        html += `<button onclick="loadLogs(${currentPage + 1})" class="btn" style="background-color: #e5e7eb; color: #333; padding: 8px 12px;">Next →</button>`;
    }

    buttons.innerHTML = html;
}

function resetFilters() {
    document.getElementById('actionType').value = 'ALL';
    document.getElementById('entityType').value = 'ALL';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    loadLogs(1);
}

// Load logs on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLogs(1);
});
</script>

        </div>
    </div>
</div>

<?php // include '../includes/footer.php'; ?>
</script>
