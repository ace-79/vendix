<?php
session_start();
include_once '../utils/api_helper.php';
include '../config/db.php';
include '../config/auth.php';
include '../config/passwords.php';

requireApiLogin();

initJsonApi();
clearApiOutputBuffer();

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

if ($method === 'POST') {
    requireCsrfToken(true);
    $input = requireJsonRequestBody();
    
    if (!$input || !isset($input['action'])) {
        apiError('Missing action', 400);
    }
    
    $action = $input['action'];
    
    if ($action === 'change_password') {
        // Get current password hash from database
        $user = $conn->query("SELECT password FROM users WHERE id = $user_id");
        if ($user->num_rows === 0) {
            apiError('User not found', 401);
        }
        
        $userData = $user->fetch_assoc();
        $currentPasswordHash = $userData['password'];
        
        // Verify current password
        if (!isset($input['current_password'])) {
            apiError('Current password required', 400);
        }
        
        $passwordCheck = verifyStoredPassword($input['current_password'], $currentPasswordHash);
        
        if (!$passwordCheck['valid']) {
            apiError('Current password is incorrect', 401);
        }
        
        // Validate new password
        if (!isset($input['new_password']) || strlen($input['new_password']) < 6) {
            apiError('New password must be at least 6 characters', 400);
        }
        
        // Always store new passwords with bcrypt.
        $newPasswordHash = hashPasswordForStorage($input['new_password']);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPasswordHash, $user_id);

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Password updated successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Database error: ' . $conn->error];
            $statusCode = 500;
        }
        $stmt->close();
    } elseif ($action === 'update_system_setting') {
        // Only admin can change system settings
        if (strtolower($_SESSION['role']) !== 'admin') {
            apiError('Unauthorized', 403);
        }

        $key = $conn->real_escape_string($input['key'] ?? '');
        $value = $conn->real_escape_string($input['value'] ?? '');

        if (empty($key)) {
            $response = ['status' => 'error', 'message' => 'Key required'];
            $statusCode = 400;
        } else {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle new settings automatically
            $sql = "INSERT INTO settings (setting_key, setting_value) 
                    VALUES ('$key', '$value') 
                    ON DUPLICATE KEY UPDATE setting_value = '$value'";
            
            if ($conn->query($sql)) {
                $response = ['status' => 'success', 'message' => 'Setting updated successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Database error: ' . $conn->error];
                $statusCode = 500;
            }
        }
    } elseif ($action === 'test_email') {
        // Only admin can test email
        if (strtolower($_SESSION['role']) !== 'admin') {
            apiError('Unauthorized', 403);
        }

        include_once '../utils/mailer.php';
        
        // Get admin email
        $user = $conn->query("SELECT email, name FROM users WHERE id = $user_id")->fetch_assoc();
        $adminEmail = $user['email'] ?? '';
        $adminName = $user['name'] ?? 'Admin';

        if (empty($adminEmail)) {
            apiError('Your profile has no email address. Please update your profile first.', 400);
        }

        // Send a dummy test email
        $testItems = [
            ['product_name' => 'System Test', 'quantity' => 1, 'unit_price' => 0.00]
        ];
        
        try {
            $success = sendInvoiceEmail('0000', $adminEmail, $adminName, 0.00, 0.00, $testItems);
            $response = ['status' => 'success', 'message' => 'Test email sent successfully to ' . $adminEmail];
        } catch (\Exception $e) {
            $response = ['status' => 'error', 'message' => 'Mailer Error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            $response = ['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Unknown action'];
        $statusCode = 400;
    }
    
    apiJsonResponse($response ?? ['status' => 'error', 'message' => 'No response generated'], $statusCode ?? 200);
}
else {
    apiError('Method not allowed', 405);
}
?>
