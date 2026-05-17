<?php
/**
 * Shop Authentication Helpers
 * Lower-level helpers used by auth pages and AJAX handler
 */

require_once __DIR__ . '/email.php';

/**
 * Login shop user (used by old form-based login; AJAX login is in ajax/auth.php)
 */
function loginShopUser($email, $password) {
    $db   = new ShopDatabase();
    $user = $db->single("SELECT * FROM general_users WHERE email = ? AND is_active = 1", [$email]);

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $emailVerified = (bool) ($user['email_verified'] ?? $user['is_verified'] ?? 0);

    session_regenerate_id(true);
    $_SESSION['shop_user_id']   = $user['user_id'];
    $_SESSION['shop_user_name'] = $user['first_name'];

    $db->update('general_users', ['last_login' => date('Y-m-d H:i:s')], 'user_id = ?', [$user['user_id']]);
    migrateGuestCart($user['user_id']);

    return [
        'success'        => true,
        'user'           => $user,
        'email_verified' => $emailVerified,
        'message'        => 'Login successful!'
    ];
}

/**
 * Logout shop user
 */
function logoutShopUser() {
    // Clear remember-me cookie and DB token
    if (!empty($_COOKIE['sc_shop_remember'])) {
        $parts = explode(':', $_COOKIE['sc_shop_remember'], 2);
        if (count($parts) === 2 && isset($_SESSION['shop_user_id'])) {
            try {
                $db = new ShopDatabase();
                $db->query("UPDATE general_users SET remember_token = NULL, remember_token_expires = NULL WHERE user_id = ?")
                   ->bind(1, (int)$_SESSION['shop_user_id'])->execute();
            } catch (Exception $e) {
                error_log('Shop logout clear remember token: ' . $e->getMessage());
            }
        }
        setcookie('sc_shop_remember', '', time() - 3600, defined('APP_COOKIE_PATH') ? APP_COOKIE_PATH : '/');
    }

    unset(
        $_SESSION['shop_user_id'],
        $_SESSION['shop_user_name'],
        $_SESSION['shop_csrf_token'],
        $_SESSION['shop_pending_user_id'],
        $_SESSION['shop_pending_email'],
        $_SESSION['shop_pending_name'],
        $_SESSION['shop_otp_hash'],
        $_SESSION['shop_otp_expires'],
        $_SESSION['shop_otp_purpose'],
        $_SESSION['shop_reset_verified']
    );
}

/**
 * Verify email via token link (legacy — kept for backward compatibility)
 */
function verifyEmailToken($token) {
    $db   = new ShopDatabase();
    $user = $db->single(
        "SELECT * FROM general_users WHERE verification_token = ? AND verification_expires > NOW()",
        [$token]
    );

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid or expired verification link'];
    }

    $db->update('general_users', [
        'email_verified'      => 1,
        'is_verified'         => 1,
        'verification_token'  => null,
        'verification_expires'=> null
    ], 'user_id = ?', [$user['user_id']]);

    if (isset($_SESSION['shop_user_id']) && $_SESSION['shop_user_id'] == $user['user_id']) {
        // already logged in
    }

    return ['success' => true, 'message' => 'Email verified successfully!'];
}

/**
 * Migrate guest (session) cart to the newly logged-in user
 */
function migrateGuestCart($userId) {
    $db        = new ShopDatabase();
    $sessionId = session_id();

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
            $db->update('shop_cart',
                ['quantity' => $existing['quantity'] + $item['quantity']],
                'cart_id = ?', [$existing['cart_id']]
            );
            $db->query("DELETE FROM shop_cart WHERE cart_id = ?")
               ->bind(1, $item['cart_id'])->execute();
        } else {
            $db->update('shop_cart',
                ['user_id' => $userId, 'session_id' => null],
                'cart_id = ?', [$item['cart_id']]
            );
        }
    }
}

/**
 * Check if current shop user has verified email
 */
function isEmailVerified() {
    if (!isShopLoggedIn()) return false;
    $db   = new ShopDatabase();
    $user = $db->single(
        "SELECT email_verified, is_verified FROM general_users WHERE user_id = ?",
        [$_SESSION['shop_user_id']]
    );
    return (bool) (($user['email_verified'] ?? 0) || ($user['is_verified'] ?? 0));
}

/**
 * Require verified email — redirect if not
 */
function requireVerifiedEmail() {
    if (!isEmailVerified()) {
        setFlashMessage('warning', 'Please verify your email to continue.');
        shopRedirect('auth/verify-email.php?resend=1');
    }
}
