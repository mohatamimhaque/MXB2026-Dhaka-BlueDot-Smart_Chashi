<?php
/**
 * Shop Utility Functions
 * Common helper functions for the shop
 */

/**
 * Get the base URL for the shop
 */
function shopUrl($path = '') {
    $baseUrl = SHOP_URL;
    if (!empty($path)) {
        $baseUrl .= '/' . ltrim($path, '/');
    }
    return $baseUrl;
}

/**
 * Redirect to a shop page
 */
function shopRedirect($path = '', $params = []) {
    $url = shopUrl($path);
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Format currency
 */
function formatPrice($amount) {
    return SHOP_CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Generate a unique order number
 */
function generateOrderNumber() {
    return 'SC' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Get product image URL
 */
function getProductImage($imageUrl) {
    if (empty($imageUrl)) {
        return shopUrl('assets/images/product-placeholder.png');
    }
    // Check if it's a full URL or relative path
    if (strpos($imageUrl, 'http') === 0) {
        return $imageUrl;
    }
    // Relative to main site
    return MAIN_SITE_URL . '/' . ltrim($imageUrl, '/');
}

/**
 * Get user avatar URL
 */
function getUserAvatar($avatarUrl) {
    if (empty($avatarUrl) || $avatarUrl === 'shop/assets/images/default-avatar.png') {
        return shopUrl('assets/images/default-avatar.png');
    }
    return MAIN_SITE_URL . '/' . ltrim($avatarUrl, '/');
}

/**
 * Flash message helpers
 */
function setFlashMessage($type, $message) {
    $_SESSION['shop_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['shop_flash'])) {
        $flash = $_SESSION['shop_flash'];
        unset($_SESSION['shop_flash']);
        return $flash;
    }
    return null;
}

function hasFlashMessage() {
    return isset($_SESSION['shop_flash']);
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Success JSON response
 */
function jsonSuccess($message, $data = []) {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Get cart count for current user/session
 */
function getCartCount() {
    $db = new ShopDatabase();
    
    if (isShopLoggedIn()) {
        $result = $db->single(
            "SELECT SUM(quantity) as count FROM shop_cart WHERE user_id = ?",
            [$_SESSION['shop_user_id']]
        );
    } else {
        $sessionId = session_id();
        $result = $db->single(
            "SELECT SUM(quantity) as count FROM shop_cart WHERE session_id = ? AND user_id IS NULL",
            [$sessionId]
        );
    }
    
    return $result['count'] ?? 0;
}

/**
 * Get order status badge class
 */
function getOrderStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'processing' => 'badge-primary',
        'shipped' => 'badge-info',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'returned' => 'badge-secondary'
    ];
    return $badges[$status] ?? 'badge-secondary';
}

/**
 * Get payment status badge class
 */
function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'failed' => 'badge-danger',
        'refunded' => 'badge-info'
    ];
    return $badges[$status] ?? 'badge-secondary';
}
?>
