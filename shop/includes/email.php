<?php
/**
 * Email Helper for Shop
 * Uses PHPMailer from main project
 */

require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 */
function sendShopEmail($to, $subject, $body, $isHtml = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SHOP_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SHOP_NAME);
        
        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $name, $token) {
    $verifyUrl = SHOP_URL . 'auth/verify-email.php?token=' . urlencode($token);
    
    $subject = 'Verify Your Email - ' . SHOP_NAME;
    
    $body = getEmailTemplate('verify', [
        'name' => $name,
        'verify_url' => $verifyUrl,
        'shop_name' => SHOP_NAME
    ]);
    
    return sendShopEmail($email, $subject, $body);
}

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($order, $items) {
    $db = new ShopDatabase();
    $user = $db->single("SELECT * FROM general_users WHERE user_id = ?", [$order['buyer_id']]);
    
    if (!$user) return false;
    
    $subject = 'Order Confirmed #' . $order['order_number'] . ' - ' . SHOP_NAME;
    
    $body = getEmailTemplate('order_confirmation', [
        'name' => $user['first_name'],
        'order_number' => $order['order_number'],
        'order_date' => date('M j, Y', strtotime($order['created_at'])),
        'items' => $items,
        'subtotal' => formatPrice($order['subtotal']),
        'shipping' => $order['shipping_cost'] == 0 ? 'FREE' : formatPrice($order['shipping_cost']),
        'total' => formatPrice($order['total_amount']),
        'shipping_address' => $order['shipping_name'] . '<br>' . $order['shipping_address'] . '<br>' . $order['shipping_district'],
        'track_url' => SHOP_URL . 'pages/track-order.php?order=' . $order['order_number'],
        'shop_name' => SHOP_NAME
    ]);
    
    return sendShopEmail($user['email'], $subject, $body);
}

/**
 * Send order status update email
 */
function sendOrderStatusEmail($orderId, $newStatus) {
    $db = new ShopDatabase();
    $order = $db->single("SELECT o.*, u.email, u.first_name FROM shop_orders o JOIN general_users u ON o.buyer_id = u.user_id WHERE o.order_id = ?", [$orderId]);
    
    if (!$order) return false;
    
    $statusMessages = [
        'confirmed' => 'Your order has been confirmed by the seller.',
        'processing' => 'Your order is being prepared for shipping.',
        'shipped' => 'Great news! Your order has been shipped.',
        'delivered' => 'Your order has been delivered. Enjoy!',
        'cancelled' => 'Your order has been cancelled.'
    ];
    
    $subject = 'Order Update: ' . ucfirst($newStatus) . ' - #' . $order['order_number'];
    
    $body = getEmailTemplate('order_status', [
        'name' => $order['first_name'],
        'order_number' => $order['order_number'],
        'status' => ucfirst($newStatus),
        'status_message' => $statusMessages[$newStatus] ?? 'Your order status has been updated.',
        'track_url' => SHOP_URL . 'pages/track-order.php?order=' . $order['order_number'],
        'shop_name' => SHOP_NAME
    ]);
    
    return sendShopEmail($order['email'], $subject, $body);
}

/**
 * Get email template
 */
function getEmailTemplate($template, $data) {
    $templates = [
        'verify' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #557A46, #8FBC46); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { color: white; margin: 0; }
        .content { background: #fff; padding: 30px; border: 1px solid #e0e0e0; }
        .btn { display: inline-block; background: #557A46; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{shop_name}</h1>
        </div>
        <div class="content">
            <h2>Welcome, {name}!</h2>
            <p>Thank you for registering with {shop_name}. Please verify your email address to complete your registration.</p>
            <p style="text-align: center;">
                <a href="{verify_url}" class="btn">Verify Email Address</a>
            </p>
            <p>Or copy this link: <br><small>{verify_url}</small></p>
            <p>This link will expire in 24 hours.</p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {shop_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',

        'order_confirmation' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #557A46, #8FBC46); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { color: white; margin: 0; }
        .content { background: #fff; padding: 30px; border: 1px solid #e0e0e0; }
        .order-box { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .btn { display: inline-block; background: #557A46; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmed! ✓</h1>
        </div>
        <div class="content">
            <p>Hi {name},</p>
            <p>Thank you for your order! We have received it and it is being processed.</p>
            <div class="order-box">
                <strong>Order Number:</strong> #{order_number}<br>
                <strong>Date:</strong> {order_date}<br>
                <strong>Total:</strong> {total}
            </div>
            <p><strong>Shipping Address:</strong><br>{shipping_address}</p>
            <p style="text-align: center;">
                <a href="{track_url}" class="btn">Track Your Order</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {shop_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',

        'order_status' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #557A46, #8FBC46); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { color: white; margin: 0; }
        .content { background: #fff; padding: 30px; border: 1px solid #e0e0e0; }
        .status-badge { display: inline-block; background: #557A46; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; }
        .btn { display: inline-block; background: #557A46; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Update</h1>
        </div>
        <div class="content">
            <p>Hi {name},</p>
            <p>Your order <strong>#{order_number}</strong> status has been updated:</p>
            <p style="text-align: center; margin: 25px 0;">
                <span class="status-badge">{status}</span>
            </p>
            <p>{status_message}</p>
            <p style="text-align: center;">
                <a href="{track_url}" class="btn">Track Order</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' {shop_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>'
    ];
    
    $html = $templates[$template] ?? '';
    
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            $html = str_replace('{' . $key . '}', $value, $html);
        }
    }
    
    return $html;
}
