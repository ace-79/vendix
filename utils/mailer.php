<?php
/**
 * Vendix Mailer Utility
 * Handles sending of invoices and notifications
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendInvoiceEmail($saleId, $customerEmail, $customerName, $totalAmount, $discountAmount, $items) {
    if (empty($customerEmail)) return false;

    // Load SMTP settings from database
    include_once __DIR__ . '/../config/helpers.php';
    
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = trim(getSetting('smtp_host', 'smtp.gmail.com')); 
        $mail->SMTPAuth   = true;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Username   = trim(getSetting('smtp_user', ''));
        $mail->Password   = trim(getSetting('smtp_pass', ''));
        
        $secure = getSetting('smtp_secure', 'tls');
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        
        $port = intval(getSetting('smtp_port', 0));
        if ($port === 0) $port = ($secure === 'ssl') ? 465 : 587;
        $mail->Port       = $port;

        // Recipients
        $fromEmail = trim(getSetting('from_email', 'noreply@vendix.com'));
        $fromName  = trim(getSetting('from_name', 'Vendix POS'));
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($customerEmail, $customerName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Invoice from Vendix - SALE-" . str_pad($saleId, 4, '0', STR_PAD_LEFT);
        
        // Build items HTML
        $itemsHtml = '';
        $subtotal = 0;
        foreach ($items as $item) {
            $itemTotal = $item['unit_price'] * $item['quantity'];
            $subtotal += $itemTotal;
            $itemsHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$item['product_name']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$item['quantity']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($item['unit_price'], 2) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($itemTotal, 2) . "</td>
                </tr>";
        }

        $discountRow = "";
        if ($discountAmount > 0) {
            $discountRow = "
                <tr>
                    <td colspan='3' style='padding: 10px; text-align: right; color: #dc2626;'>Discount:</td>
                    <td style='padding: 10px; text-align: right; color: #dc2626;'>-$" . number_format($discountAmount, 2) . "</td>
                </tr>";
        }

        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #6F4E37 0%, #8B6F47 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f3f4f6; text-align: left; padding: 10px; font-size: 13px; text-transform: uppercase; }
                .total { font-size: 18px; font-weight: bold; color: #6F4E37; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>VENDIX</h1>
                    <p>Smart Ecommerce System</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$customerName}!</h2>
                    <p>Thank you for your purchase. Here is your invoice details for sale <strong>#SALE-" . str_pad($saleId, 4, '0', STR_PAD_LEFT) . "</strong>.</p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style='text-align: center;'>Qty</th>
                                <th style='text-align: right;'>Price</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                            <tr>
                                <td colspan='3' style='padding: 10px; text-align: right;'>Subtotal:</td>
                                <td style='padding: 10px; text-align: right;'>$" . number_format($subtotal, 2) . "</td>
                            </tr>
                            {$discountRow}
                            <tr>
                                <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold;'>Total Amount:</td>
                                <td style='padding: 10px; text-align: right;' class='total'>$" . number_format($totalAmount, 2) . "</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p style='margin-top: 30px;'>If you have any questions, please feel free to contact us.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Vendix Smart Ecommerce System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("Vendix Mailer: Failed to send email to $customerEmail. Error: " . $e->getMessage());
        throw $e; 
    } catch (\Throwable $t) {
        throw $t;
    }
}

function sendPurchaseOrderEmail($poId, $supplierEmail, $supplierName, $totalAmount, $expectedDate, $items) {
    if (empty($supplierEmail)) return false;

    // Load SMTP settings from database
    include_once __DIR__ . '/../config/helpers.php';
    
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = trim(getSetting('smtp_host', 'smtp.gmail.com')); 
        $mail->SMTPAuth   = true;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Username   = trim(getSetting('smtp_user', ''));
        $mail->Password   = trim(getSetting('smtp_pass', ''));
        
        $secure = getSetting('smtp_secure', 'tls');
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        
        $port = intval(getSetting('smtp_port', 0));
        if ($port === 0) $port = ($secure === 'ssl') ? 465 : 587;
        $mail->Port       = $port;

        // Recipients
        $fromEmail = trim(getSetting('from_email', 'noreply@vendix.com'));
        $fromName  = trim(getSetting('from_name', 'Vendix POS'));
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($supplierEmail, $supplierName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Purchase Order from Vendix - PO-" . str_pad($poId, 4, '0', STR_PAD_LEFT);
        
        // Build items HTML
        $itemsHtml = '';
        $subtotal = 0;
        foreach ($items as $item) {
            $itemTotal = $item['unit_cost'] * $item['quantity_ordered'];
            $subtotal += $itemTotal;
            $itemsHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$item['product_name']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$item['quantity_ordered']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($item['unit_cost'], 2) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>$" . number_format($itemTotal, 2) . "</td>
                </tr>";
        }

        $expectedDateText = $expectedDate ? "<p><strong>Expected Delivery Date:</strong> " . htmlspecialchars($expectedDate) . "</p>" : "";

        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #1f2937 0%, #374151 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f3f4f6; text-align: left; padding: 10px; font-size: 13px; text-transform: uppercase; }
                .total { font-size: 18px; font-weight: bold; color: #1f2937; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>VENDIX</h1>
                    <p>Purchase Order</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$supplierName}!</h2>
                    <p>Please find attached our new purchase order <strong>#PO-" . str_pad($poId, 4, '0', STR_PAD_LEFT) . "</strong>.</p>
                    {$expectedDateText}
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style='text-align: center;'>Qty</th>
                                <th style='text-align: right;'>Unit Cost</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                            <tr>
                                <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold;'>Total Amount:</td>
                                <td style='padding: 10px; text-align: right;' class='total'>$" . number_format($subtotal, 2) . "</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p style='margin-top: 30px;'>Please confirm receipt of this order. If you have any questions, feel free to contact us.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Vendix Smart Ecommerce System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("Vendix Mailer: Failed to send PO email to $supplierEmail. Error: " . $e->getMessage());
        throw $e; 
    } catch (\Throwable $t) {
        throw $t;
    }
}
?>
