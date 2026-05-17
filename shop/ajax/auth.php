<?php
/**
 * Shop AJAX Authentication Handler
 * Handles register (OTP), login, forgot/reset password
 * Follows the same pattern as /ajax/auth.php in the main project
 */

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db     = new ShopDatabase();
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Parse JSON body
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $input = json_decode($raw, true) ?? [];
    }
    if (empty($input)) {
        $input = $_POST;
    }
    $action = $input['action'] ?? $action;
}

$response = ['success' => false, 'message' => 'Invalid request'];

// =====================================================================
// REGISTER STEP 1 — validate, create user (unverified), send OTP
// =====================================================================
if ($action === 'register_step1') {
    $firstName = htmlspecialchars(trim($input['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $lastName  = htmlspecialchars(trim($input['last_name']  ?? ''), ENT_QUOTES, 'UTF-8');
    $email     = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone     = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password  = $input['password'] ?? '';

    if (empty($firstName) || strlen($firstName) < 2) {
        shopJsonError('First name must be at least 2 characters');
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        shopJsonError('Please enter a valid email address');
    }
    if (empty($password) || strlen($password) < 6) {
        shopJsonError('Password must be at least 6 characters');
    }

    // Check duplicate email
    $existing = $db->single("SELECT user_id, email_verified FROM general_users WHERE email = ?", [$email]);
    if ($existing) {
        if ($existing['email_verified']) {
            shopJsonError('Email already registered. Please login.');
        }
        // Not verified yet — delete and allow re-registration
        $db->query("DELETE FROM general_users WHERE user_id = ?")
           ->bind(1, $existing['user_id'])->execute();
    }

    // Save optional profile image
    $profileImgUrl = null;
    $profileImageB64 = $input['profile_image'] ?? '';
    if (!empty($profileImageB64) && preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $profileImageB64, $matches)) {
        $ext       = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $imgData   = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $profileImageB64));
        if ($imgData && strlen($imgData) <= 2 * 1024 * 1024) { // max 2MB
            $uploadDir = __DIR__ . '/../../shop/assets/uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename  = 'avatar_' . uniqid() . '.' . $ext;
            if (file_put_contents($uploadDir . $filename, $imgData)) {
                $profileImgUrl = SHOP_URL . 'assets/uploads/avatars/' . $filename;
            }
        }
    }

    // Generate 6-digit OTP
    $otp       = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash   = password_hash($otp, PASSWORD_BCRYPT);
    $passHash  = password_hash($password, PASSWORD_BCRYPT);

    // Insert user (email_verified = 0)
    $db->query("INSERT INTO general_users
                    (first_name, last_name, email, phone, password_hash, profile_img_url, email_verified, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 1)")
       ->bind(1, $firstName)->bind(2, $lastName)
       ->bind(3, $email)->bind(4, $phone)
       ->bind(5, $passHash)->bind(6, $profileImgUrl)->execute();

    $userId = $db->lastInsertId();

    // Store OTP in session
    $_SESSION['shop_pending_user_id']  = $userId;
    $_SESSION['shop_pending_email']    = $email;
    $_SESSION['shop_pending_name']     = $firstName;
    $_SESSION['shop_otp_hash']         = $otpHash;
    $_SESSION['shop_otp_expires']      = time() + 600; // 10 min
    $_SESSION['shop_otp_purpose']      = 'register';

    // Send email
    if (shopSendOtpEmail($email, $otp, $firstName, 'verify')) {
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent to your email',
            'email'   => shopMaskEmail($email)
        ]);
    } else {
        // Roll back — delete user if email failed
        $db->query("DELETE FROM general_users WHERE user_id = ?")
           ->bind(1, $userId)->execute();
        shopJsonError('Failed to send verification email. Please try again.');
    }
    exit;
}

// =====================================================================
// VERIFY OTP — verify 6-digit code (for register OR password reset)
// =====================================================================
if ($action === 'verify_otp') {
    $code    = trim($input['code'] ?? '');
    $purpose = $_SESSION['shop_otp_purpose'] ?? 'register';

    if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
        shopJsonError('Invalid verification code format');
    }
    if (!isset($_SESSION['shop_otp_hash']) || !isset($_SESSION['shop_otp_expires'])) {
        shopJsonError('Session expired. Please start again.');
    }
    if (time() > $_SESSION['shop_otp_expires']) {
        shopJsonError('Verification code expired. Please request a new one.');
    }
    if (!password_verify($code, $_SESSION['shop_otp_hash'])) {
        shopJsonError('Invalid verification code');
    }

    if ($purpose === 'register') {
        $userId = $_SESSION['shop_pending_user_id'] ?? null;
        if (!$userId) {
            shopJsonError('Session error. Please register again.');
        }

        // Mark email verified
        $db->query("UPDATE general_users SET email_verified = 1, is_verified = 1 WHERE user_id = ?")
           ->bind(1, $userId)->execute();

        // Log user in
        session_regenerate_id(true);
        $_SESSION['shop_user_id']   = $userId;
        $_SESSION['shop_user_name'] = $_SESSION['shop_pending_name'] ?? '';

        // Clean up OTP session vars
        shopClearOtpSession();

        // Migrate any guest cart
        migrateGuestCart($userId);

        echo json_encode([
            'success'  => true,
            'message'  => 'Email verified! Welcome to ' . SHOP_NAME,
            'redirect' => SHOP_URL
        ]);
    } elseif ($purpose === 'reset_password') {
        // Allow password reset — store a flag in session
        $_SESSION['shop_reset_verified'] = true;
        shopClearOtpSession(false); // keep email

        echo json_encode([
            'success' => true,
            'message' => 'Code verified. Please enter your new password.'
        ]);
    }
    exit;
}

// =====================================================================
// RESEND OTP
// =====================================================================
if ($action === 'resend_otp') {
    if (!isset($_SESSION['shop_pending_email'])) {
        shopJsonError('Session expired. Please start again.');
    }

    $email   = $_SESSION['shop_pending_email'];
    $name    = $_SESSION['shop_pending_name'] ?? 'User';
    $purpose = $_SESSION['shop_otp_purpose'] ?? 'register';

    $otp     = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT);

    $_SESSION['shop_otp_hash']    = $otpHash;
    $_SESSION['shop_otp_expires'] = time() + 600;

    $type = ($purpose === 'reset_password') ? 'reset' : 'verify';
    if (shopSendOtpEmail($email, $otp, $name, $type)) {
        echo json_encode(['success' => true, 'message' => 'New code sent!']);
    } else {
        shopJsonError('Failed to send code. Please try again.');
    }
    exit;
}

// =====================================================================
// LOGIN
// =====================================================================
if ($action === 'login') {
    $email    = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';
    $remember = !empty($input['remember']);

    if (empty($email)) {
        shopJsonError('Email is required');
    }
    if (empty($password)) {
        shopJsonError('Password is required');
    }

    $user = $db->single("SELECT * FROM general_users WHERE email = ? AND is_active = 1", [$email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        shopJsonError('Invalid email or password');
    }

    if (!$user['email_verified']) {
        // Not verified — send a new OTP
        $otp     = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);

        $_SESSION['shop_pending_user_id'] = $user['user_id'];
        $_SESSION['shop_pending_email']   = $user['email'];
        $_SESSION['shop_pending_name']    = $user['first_name'];
        $_SESSION['shop_otp_hash']        = $otpHash;
        $_SESSION['shop_otp_expires']     = time() + 600;
        $_SESSION['shop_otp_purpose']     = 'register';

        shopSendOtpEmail($user['email'], $otp, $user['first_name'], 'verify');

        echo json_encode([
            'success'           => false,
            'needs_verification' => true,
            'message'           => 'Please verify your email first.',
            'email'             => shopMaskEmail($user['email'])
        ]);
        exit;
    }

    // Successful login
    session_regenerate_id(true);
    $_SESSION['shop_user_id']   = $user['user_id'];
    $_SESSION['shop_user_name'] = $user['first_name'];

    // Remember me
    if ($remember) {
        $rmToken     = bin2hex(random_bytes(32));
        $rmTokenHash = hash('sha256', $rmToken);
        $rmExpires   = date('Y-m-d H:i:s', time() + 30 * 24 * 3600);
        $db->query("UPDATE general_users SET remember_token = ?, remember_token_expires = ? WHERE user_id = ?")
           ->bind(1, $rmTokenHash)->bind(2, $rmExpires)->bind(3, $user['user_id'])->execute();
        setcookie('sc_shop_remember', $user['user_id'] . ':' . $rmToken, [
            'expires'  => time() + 30 * 24 * 3600,
            'path'     => $cookiePath,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // Update last login
    $db->query("UPDATE general_users SET last_login = NOW() WHERE user_id = ?")
       ->bind(1, $user['user_id'])->execute();

    // Migrate guest cart
    migrateGuestCart($user['user_id']);

    // Redirect URL
    $redirect = $_SESSION['shop_redirect_url'] ?? SHOP_URL;
    unset($_SESSION['shop_redirect_url']);
    if (!$redirect || strpos($redirect, '/auth/') !== false) {
        $redirect = SHOP_URL;
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Welcome back, ' . $user['first_name'] . '!',
        'redirect' => $redirect
    ]);
    exit;
}

// =====================================================================
// FORGOT PASSWORD — send OTP to email
// =====================================================================
if ($action === 'forgot_password') {
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        shopJsonError('Please enter a valid email address');
    }

    $user = $db->single("SELECT * FROM general_users WHERE email = ? AND is_active = 1", [$email]);

    if (!$user) {
        shopJsonError('No account found with this email address. Please register first.');
    }

    $otp     = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT);

    $_SESSION['shop_pending_email']   = $email;
    $_SESSION['shop_pending_name']    = $user['first_name'];
    $_SESSION['shop_pending_user_id'] = $user['user_id'];
    $_SESSION['shop_otp_hash']        = $otpHash;
    $_SESSION['shop_otp_expires']     = time() + 600;
    $_SESSION['shop_otp_purpose']     = 'reset_password';

    shopSendOtpEmail($email, $otp, $user['first_name'], 'reset');

    echo json_encode([
        'success' => true,
        'message' => 'A password reset code has been sent to your email.',
        'email'   => shopMaskEmail($email)
    ]);
    exit;
}

// =====================================================================
// RESET PASSWORD — OTP already verified, set new password
// =====================================================================
if ($action === 'reset_password') {
    if (empty($_SESSION['shop_reset_verified']) || empty($_SESSION['shop_pending_user_id'])) {
        shopJsonError('Invalid request. Please start the reset process again.');
    }

    $newPassword = $input['password'] ?? '';
    $confirm     = $input['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        shopJsonError('Password must be at least 6 characters');
    }
    if ($newPassword !== $confirm) {
        shopJsonError('Passwords do not match');
    }

    $userId = $_SESSION['shop_pending_user_id'];
    $db->query("UPDATE general_users SET password_hash = ? WHERE user_id = ?")
       ->bind(1, password_hash($newPassword, PASSWORD_BCRYPT))
       ->bind(2, $userId)->execute();

    // Clean up
    unset($_SESSION['shop_reset_verified'], $_SESSION['shop_pending_user_id'],
          $_SESSION['shop_pending_email'], $_SESSION['shop_pending_name']);

    echo json_encode([
        'success'  => true,
        'message'  => 'Password reset successfully! You can now login.',
        'redirect' => SHOP_URL . 'auth/login.php'
    ]);
    exit;
}

echo json_encode($response);
exit;

// =====================================================================
// HELPER FUNCTIONS
// =====================================================================

function shopJsonError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Migrate session cart items to a logged-in user's account.
 * Called immediately after successful login or OTP verification.
 */
function migrateGuestCart($userId) {
    global $db;
    $sessionId = session_id();
    if (!$sessionId) return;

    $guestItems = $db->resultSet(
        "SELECT * FROM shop_cart WHERE session_id = ? AND user_id IS NULL",
        [$sessionId]
    );

    foreach ($guestItems as $item) {
        $existing = $db->single(
            "SELECT cart_id, quantity FROM shop_cart WHERE user_id = ? AND product_id = ?",
            [$userId, $item['product_id']]
        );
        if ($existing) {
            // Merge quantities
            $db->query("UPDATE shop_cart SET quantity = quantity + ? WHERE cart_id = ?")
               ->bind(1, $item['quantity'])->bind(2, $existing['cart_id'])->execute();
            $db->query("DELETE FROM shop_cart WHERE cart_id = ?")
               ->bind(1, $item['cart_id'])->execute();
        } else {
            // Claim the guest row
            $db->query("UPDATE shop_cart SET user_id = ?, session_id = NULL WHERE cart_id = ?")
               ->bind(1, $userId)->bind(2, $item['cart_id'])->execute();
        }
    }
}

function shopMaskEmail($email) {
    $parts = explode('@', $email);
    $name  = $parts[0];
    $len   = strlen($name);
    if ($len <= 4) {
        $masked = substr($name, 0, 1) . str_repeat('*', $len - 1);
    } else {
        $masked = substr($name, 0, 2) . str_repeat('*', $len - 4) . substr($name, -2);
    }
    return $masked . '@' . $parts[1];
}

function shopClearOtpSession($clearEmail = true) {
    unset($_SESSION['shop_otp_hash'], $_SESSION['shop_otp_expires'], $_SESSION['shop_otp_purpose']);
    if ($clearEmail) {
        unset($_SESSION['shop_pending_email'], $_SESSION['shop_pending_name'],
              $_SESSION['shop_pending_user_id']);
    }
}

function shopSendOtpEmail($email, $otp, $name, $type = 'verify') {
    require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/Exception.php';
    require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/PHPMailer.php';
    require_once dirname(__DIR__, 2) . '/ajax/PHPMailer/src/SMTP.php';

    $shopName = defined('SHOP_NAME') ? SHOP_NAME : 'Smart Chashi Shop';

    if ($type === 'reset') {
        $subject = $otp . ' is your ' . $shopName . ' password reset code';
        $heading = 'Reset Your Password';
        $subtext = 'Use the code below to reset your password:';
    } else {
        $subject = $otp . ' is your ' . $shopName . ' verification code';
        $heading = 'Verify Your Email';
        $subtext = 'Use the code below to verify your email address:';
    }

    $html = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f5f5f5;">
<div style="max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
  <div style="background:linear-gradient(135deg,#557A46,#8FBC46);padding:30px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:24px;">🌾 ' . htmlspecialchars($shopName) . '</h1>
  </div>
  <div style="padding:30px;">
    <h2 style="color:#557A46;margin-top:0;">' . $heading . '</h2>
    <p>Hello <strong>' . htmlspecialchars($name) . '</strong>!</p>
    <p>' . $subtext . '</p>
    <div style="background:#f1f8e9;border:2px solid #557A46;padding:20px;margin:20px 0;
                font-size:36px;font-weight:bold;border-radius:10px;text-align:center;
                letter-spacing:8px;color:#557A46;">' . $otp . '</div>
    <p style="color:#666;">This code expires in <strong>10 minutes</strong>.</p>
    <p style="color:#999;font-size:12px;">If you did not request this, please ignore this email.</p>
  </div>
  <div style="background:#f9f9f9;padding:20px;text-align:center;border-top:1px solid #eee;">
    <p style="color:#999;font-size:12px;margin:0;">
      &copy; ' . date('Y') . ' ' . htmlspecialchars($shopName) . ' &mdash; Fresh from Farm to Your Table
    </p>
  </div>
</div>
</body></html>';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST')     ? SMTP_HOST     : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT')     ? SMTP_PORT     : 587;
        $mail->Timeout    = 10;

        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;
        $mail->setFrom($fromEmail, $shopName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = 'Your ' . $shopName . ' code is: ' . $otp . '. Valid for 10 minutes.';

        return $mail->send();
    } catch (\Exception $e) {
        error_log('Shop OTP email error: ' . $e->getMessage());
        return false;
    }
}
