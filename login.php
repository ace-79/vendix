<?php
session_start();
include_once 'config/auth.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isValidCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        include 'config/db.php';
        include_once 'config/helpers.php';
        include 'config/passwords.php';
        
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required';
        } else {
            // Use prepared statement for security
            $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (isset($user['status']) && $user['status'] === 'blocked') {
                    $error = 'Your account has been suspended';

                    logActivity(
                        user_id: $user['id'],
                        action_type: 'LOGIN',
                        entity_type: 'auth',
                        description: 'Blocked login attempt for username: ' . $username,
                        ip_address: getUserIP()
                    );
                } else {
                    $passwordCheck = verifyStoredPassword($password, $user['password']);
                    
                    if ($passwordCheck['valid']) {
                        if ($passwordCheck['needs_rehash']) {
                            upgradeUserPasswordHash($conn, (int) $user['id'], $password);
                        }

                        session_regenerate_id(true);

                        $updateStmt = $conn->prepare("UPDATE users SET force_logout = 0, last_seen = NOW() WHERE id = ?");
                        $updateStmt->bind_param("i", $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();

                        // Login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = strtolower($user['role']); // Normalize role to lowercase
                        
                        // Log successful login
                        logActivity(
                            user_id: $user['id'],
                            action_type: 'LOGIN',
                            entity_type: 'auth',
                            ip_address: getUserIP()
                        );
                        
                        // Redirect to dashboard
                        header("Location: pages/dashboard.php");
                        exit;
                    } else {
                        $error = 'Invalid username or password';
                        // Log failed login attempt
                        logActivity(
                            user_id: null,
                            action_type: 'LOGIN',
                            entity_type: 'auth',
                            description: 'Failed login: Invalid password for username: ' . $username,
                            ip_address: getUserIP()
                        );
                    }
                }
            } else {
                $error = 'Invalid username or password';
                // Log failed login attempt
                logActivity(
                    user_id: null,
                    action_type: 'LOGIN',
                    entity_type: 'auth',
                    description: 'Failed login: User not found - ' . $username,
                    ip_address: getUserIP()
                );
            }
            
            $stmt->close();
        }
        
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendix - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            background-color: #fff;
            padding: 50px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(111, 78, 55, 0.3);
            margin: 20px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header .logo {
            font-size: 3rem;
            color: #6F4E37;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            font-size: 2rem;
            color: #6F4E37;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #888;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #E8D9C8;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #F9F5F0;
        }
        
        .form-group input:focus {
            outline: none;
            border: 2px solid #8B6F47;
            background-color: #fff;
            box-shadow: 0 0 8px rgba(139, 111, 71, 0.25);
        }
        
        .error-message {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message i {
            font-size: 1.1rem;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(111, 78, 55, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .demo-credentials {
            background-color: #F9F5F0;
            border: 2px solid #E8D9C8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 25px;
            font-size: 0.85rem;
        }
        
        .demo-credentials h4 {
            color: #6F4E37;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .demo-credentials p {
            color: #666;
            margin-bottom: 6px;
            padding-left: 10px;
        }
        
        .demo-credentials code {
            background-color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            color: #8B6F47;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .login-header .logo {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo"><img src="images/logo/VX.svg" alt="Vendix Logo" style="max-width: 100px; height: auto;"></div>
            <h1>Vendix</h1>
            <p>Smart Ecommerce System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user" style="margin-right: 8px; color: #8B6F47;"></i>Username
                </label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock" style="margin-right: 8px; color: #8B6F47;"></i>Password
                </label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>Login
            </button>
        </form>
        
        <!-- <div class="demo-credentials">
            <h4><i class="fas fa-info-circle" style="margin-right: 8px;"></i>Test Credentials</h4>
            <p><code>admin</code> / <code>admin123</code> (Admin)</p>
            <p><code>cashier1</code> / <code>cashier123</code> (Cashier)</p>
            <p><code>manager</code> / <code>manager123</code> (Manager)</p>
        </div> -->
    </div>

    <!-- Developer Info Popup -->
    <div id="developerPopup" style="position: fixed; bottom: 20px; right: 20px; background: rgba(0, 0, 0, 0.85); color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-size: 15px; font-weight: 500; z-index: 1000; animation: slideIn 0.5s ease-out forwards; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-code" style="color: #8B6F47;"></i> This app was developed by "Zakaria El Khayat"
    </div>
    <style>
    @keyframes slideIn {
        from { transform: translateX(120%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(120%); opacity: 0; }
    }
    </style>
    <script>
    setTimeout(function() {
        var popup = document.getElementById('developerPopup');
        if(popup) {
            popup.style.animation = 'slideOut 0.5s ease-out forwards';
            setTimeout(function() { popup.remove(); }, 500);
        }
    }, 30000); // 30 seconds
    </script>
</body>
</html>
