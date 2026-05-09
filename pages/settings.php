<?php
session_start();
include '../config/db.php';
include '../config/auth.php';

requireLogin();

include '../includes/header.php';
include '../includes/navbar.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT id, username, role FROM users WHERE id = $user_id")->fetch_assoc();
?>

<div class="main-container">
    <?php include '../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="content-wrapper">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            
            <!-- User Profile Card -->
            <div class="settings-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 600px;">
                <h2>User Profile</h2>
                
                <div class="profile-info" style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 15px; margin-bottom: 15px;">
                        <strong>Username:</strong>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 15px;">
                        <strong>Role:</strong>
                        <span>
                            <span style="background: <?php 
                                echo $user['role'] === 'admin' ? '#dc2626' : ($user['role'] === 'manager' ? '#d97706' : '#0891b2');
                            ?>; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- App Theme Section -->
            <div class="settings-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 600px;">
                <h2><i class="fas fa-palette"></i> App Theme</h2>
                <div class="theme-selector" style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                    <div class="theme-option" onclick="changeTheme('theme-default')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%); margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease;" class="theme-color" data-theme="theme-default"></div>
                        <span style="font-weight: 500;">Coffee</span>
                    </div>
                    <div class="theme-option" onclick="changeTheme('theme-ocean')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #0f4c75 0%, #3282b8 100%); margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease;" class="theme-color" data-theme="theme-ocean"></div>
                        <span style="font-weight: 500;">Ocean</span>
                    </div>
                    <div class="theme-option" onclick="changeTheme('theme-forest')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%); margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease;" class="theme-color" data-theme="theme-forest"></div>
                        <span style="font-weight: 500;">Forest</span>
                    </div>
                    <div class="theme-option" onclick="changeTheme('theme-sunset')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #c62828 0%, #e53935 100%); margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease;" class="theme-color" data-theme="theme-sunset"></div>
                        <span style="font-weight: 500;">Sunset</span>
                    </div>
                    <div class="theme-option" onclick="changeTheme('theme-purple')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #4a148c 0%, #7b1fa2 100%); margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease;" class="theme-color" data-theme="theme-purple"></div>
                        <span style="font-weight: 500;">Purple</span>
                    </div>
                    <div class="theme-option" onclick="changeTheme('theme-dark')" style="cursor: pointer; text-align: center;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #1e1e1e; margin: 0 auto 8px; border: 3px solid transparent; transition: all 0.3s ease; position: relative; overflow: hidden;" class="theme-color" data-theme="theme-dark">
                            <div style="position: absolute; bottom: 0; right: 0; width: 25px; height: 25px; background: linear-gradient(135deg, #bb86fc 0%, #3700b3 100%); border-top-left-radius: 100%;"></div>
                        </div>
                        <span style="font-weight: 500;">Dark</span>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Section -->
            <div class="settings-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px;">
                <h2>Change Password</h2>
                
                <form id="passwordForm" onsubmit="handlePasswordChange(event)" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="currentPassword">Current Password *</label>
                        <input type="password" id="currentPassword" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newPassword">New Password *</label>
                        <input type="password" id="newPassword" required>
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password *</label>
                        <input type="password" id="confirmPassword" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="margin-right: 10px;">
                            <i class="fas fa-check"></i> Update Password
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if (strtolower($user['role']) === 'admin'): ?>
            <!-- Admin System Settings -->
            <div class="settings-card" style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px; max-width: 600px;">
                <h2><i class="fas fa-tools"></i> System Configuration</h2>
                
                <div style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f3f4f6;">
                        <div>
                            <strong style="display: block;">Auto-Email Invoices</strong>
                            <small style="color: #666;">Send PDF receipts automatically to customers after every sale.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="autoEmailToggle" <?php echo getSetting('auto_email_invoices', '0') === '1' ? 'checked' : ''; ?> onchange="updateSystemSetting('auto_email_invoices', this.checked ? '1' : '0')">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <div style="padding: 15px; border-bottom: 1px solid #f3f4f6;">
                        <a href="permissions.php" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit;">
                            <div>
                                <strong style="display: block;">Roles & Permissions</strong>
                                <small style="color: #666;">Control what managers and cashiers can access.</small>
                            </div>
                            <i class="fas fa-chevron-right" style="color: #ccc;"></i>
                        </a>
                    </div>
                    
                    <div style="padding: 15px;">
                        <strong style="display: block; margin-bottom: 10px;">Company Details</strong>
                        <div class="form-group">
                            <label>Store Name</label>
                            <input type="text" id="setting_app_name" value="<?php echo htmlspecialchars(getSetting('app_name', 'Vendix')); ?>" onchange="updateSystemSetting('app_name', this.value)">
                        </div>
                        <div class="form-group">
                            <label>Store Address</label>
                            <input type="text" id="setting_address" value="<?php echo htmlspecialchars(getSetting('address', '')); ?>" onchange="updateSystemSetting('address', this.value)">
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (strtolower($_SESSION['role']) === 'admin'): ?>
            <!-- Email & SMTP Configuration -->
            <div class="settings-card" style="background-color: white; padding: 30px; border-radius: 12px; margin-top: 30px; border: 1px solid #e5e7eb; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="color: #6F4E37; margin-top: 0; display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #f3e8e0; padding-bottom: 15px;">
                    <i class="fas fa-envelope-open-text"></i> Email & SMTP Settings
                </h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 25px;">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" id="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.mailtrap.io')); ?>" onblur="updateSystemSetting('smtp_host', this.value)">
                        <small>e.g., smtp.gmail.com or mail.yourdomain.com</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" id="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port', '2525')); ?>" onblur="updateSystemSetting('smtp_port', this.value)">
                        <small>Common: 587 (TLS), 465 (SSL), or 2525</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" id="smtp_user" value="<?php echo htmlspecialchars(getSetting('smtp_user', '')); ?>" onblur="updateSystemSetting('smtp_user', this.value)">
                        <small>Your email or SMTP username</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" id="smtp_pass" value="<?php echo htmlspecialchars(getSetting('smtp_pass', '')); ?>" onblur="updateSystemSetting('smtp_pass', this.value)">
                        <small>Your SMTP password (stored securely)</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Security</label>
                        <select id="smtp_secure" onchange="updateSystemSetting('smtp_secure', this.value)" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="tls" <?php echo getSetting('smtp_secure', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo getSetting('smtp_secure', 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo getSetting('smtp_secure', 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sender Name</label>
                        <input type="text" id="from_name" value="<?php echo htmlspecialchars(getSetting('from_name', 'Vendix POS')); ?>" onblur="updateSystemSetting('from_name', this.value)">
                    </div>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f3e8e0; display: flex; justify-content: space-between; align-items: center;">
                    <p style="font-size: 0.9rem; color: #666; margin: 0;">
                        <i class="fas fa-info-circle"></i> SMTP changes are saved automatically.
                    </p>
                    <button onclick="testSmtpConnection()" class="btn btn-primary" style="background-color: #6F4E37; border: none; padding: 10px 20px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-paper-plane"></i> Send Test Email
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div id="smtp-debug-log" style="display:none; margin-top: 20px; padding: 20px; background: #fee; border: 1px solid red; border-radius: 8px; font-family: monospace; white-space: pre-wrap; font-size: 12px; color: #900;"></div>

            <!-- System Information -->
            <div class="settings-card" style="background-color: #F9F5F0; padding: 20px; border-radius: 8px; margin-top: 40px; border-left: 4px solid #8B6F47;">
                <h2><i class="fas fa-info-circle"></i> System Information</h2>
                <div style="margin-top: 15px;">
                    <p><strong>Staff Member ID:</strong> <?php echo $user['id']; ?></p>
                    <p><strong>Session Started:</strong> <?php echo date('M d, Y H:i:s', $_SERVER['REQUEST_TIME']); ?></p>
                    <p><strong>System Name:</strong> Vendix Premium POS</p>
                    <p><strong>Mail Engine:</strong> PHPMailer Library (SMTP)</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-card { transition: box-shadow 0.3s ease; }
.settings-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
.form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
.form-group input:focus { outline: none; border-color: #8B6F47; box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1); }
.form-group small { display: block; margin-top: 5px; color: #888; font-size: 12px; }

/* Toggle Switch Style */
.switch { position: relative; display: inline-block; width: 46px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
input:checked + .slider { background-color: #6F4E37; }
input:checked + .slider:before { transform: translateX(22px); }
.slider.round { border-radius: 34px; }
.slider.round:before { border-radius: 50%; }
</style>

<script>
function changeTheme(themeName) {
    document.documentElement.className = '';
    if (themeName !== 'theme-default') {
        document.documentElement.classList.add(themeName);
    }
    localStorage.setItem('vendix_theme', themeName);
    updateActiveThemeSelector(themeName);
}

function updateActiveThemeSelector(themeName) {
    document.querySelectorAll('.theme-color').forEach(el => {
        el.style.borderColor = 'transparent';
        el.style.transform = 'scale(1)';
        if (el.dataset.theme === themeName) {
            el.style.borderColor = (themeName === 'theme-dark' || document.documentElement.classList.contains('theme-dark')) ? '#bb86fc' : '#333';
            el.style.transform = 'scale(1.1)';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = localStorage.getItem('vendix_theme') || 'theme-default';
    updateActiveThemeSelector(currentTheme);
});

function resetPasswordForm() {
    document.getElementById('passwordForm').reset();
}

async function handlePasswordChange(e) {
    e.preventDefault();
    
    const current = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    
    if (newPass !== confirm) {
        alert('New passwords do not match');
        return;
    }
    
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters');
        return;
    }
    
    try {
        const response = await fetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'change_password',
                current_password: current,
                new_password: newPass
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert('Password changed successfully!');
            resetPasswordForm();
        } else {
            alert('Error: ' + (data.message || 'Failed to change password'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function updateSystemSetting(key, value) {
    try {
        const response = await fetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_system_setting',
                key: key,
                value: value
            })
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            vendixNotify(data.message || 'Setting updated', 'success');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function testSmtpConnection() {
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Test...';
    
    const debugLog = document.getElementById('smtp-debug-log');
    debugLog.style.display = 'none';
    debugLog.innerHTML = '';
    
    try {
        const response = await fetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'test_email'
            })
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            vendixNotify(data.message, 'success');
        } else {
            debugLog.style.display = 'block';
            debugLog.innerHTML = '<strong>SMTP ERROR:</strong>\n' + data.message;
            vendixNotify('Email failed. See log below.', 'error');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}
</script>


