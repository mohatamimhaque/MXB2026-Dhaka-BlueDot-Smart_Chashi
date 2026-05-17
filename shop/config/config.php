<?php
/**
 * Shop Configuration
 * Uses main project configuration and adds shop-specific settings
 */

// Include main project config
require_once dirname(__DIR__, 2) . '/config/config.php';

// Shop-specific constants
define('SHOP_NAME', 'Smart Chashi Shop');
define('SHOP_TAGLINE', 'Fresh from Farm to Your Table');

// Shop base URL
$shop_base_url = $base_url . 'shop/';
define('SHOP_URL', $shop_base_url);
define('MAIN_SITE_URL', $base_url);

// Shop paths
define('SHOP_ROOT', dirname(__DIR__));
define('SHOP_UPLOAD_DIR', SHOP_ROOT . '/uploads/');

// Shop settings
define('SHOP_ITEMS_PER_PAGE', 12);
define('SHOP_PASSWORD_MIN_LENGTH', 6);

// Order statuses
define('SHOP_ORDER_STATUSES', [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
]);

// Payment statuses
define('SHOP_PAYMENT_STATUSES', [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'failed' => 'Failed',
    'refunded' => 'Refunded'
]);

// Use ShopDatabase class that extends main Database
class ShopDatabase extends Database {

    public function insert($table, $data) {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql);
        $i = 1;
        foreach ($data as $value) {
            $this->bind($i++, $value);
        }
        $this->execute();
        return $this->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $this->query($sql);
        $i = 1;
        foreach ($data as $value) {
            $this->bind($i++, $value);
        }
        foreach ($whereParams as $param) {
            $this->bind($i++, $param);
        }
        return $this->execute();
    }

    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    public function commit() {
        return $this->getConnection()->commit();
    }

    public function rollback() {
        return $this->getConnection()->rollBack();
    }

    // Get a shop setting value from the shop_settings table
    public function getSetting($key, $default = '') {
        $row = $this->single("SELECT setting_value FROM shop_settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : $default;
    }

    // Get multiple settings as key=>value map
    public function getSettings(array $keys) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getSetting($key);
        }
        return $result;
    }
}

/**
 * Shop Helper Functions
 */

// Generate shop URL
function shopUrl($path = '') {
    return SHOP_URL . ltrim($path, '/');
}

// Redirect within shop
function shopRedirect($path = '') {
    header('Location: ' . shopUrl($path));
    exit;
}

// Check if shop user is logged in
function isShopLoggedIn() {
    if (isset($_SESSION['shop_user_id'])) return true;

    static $checked = false;
    if ($checked) return false;
    $checked = true;

    if (!empty($_COOKIE['sc_shop_remember'])) {
        $parts = explode(':', $_COOKIE['sc_shop_remember'], 2);
        if (count($parts) === 2) {
            $userId = (int)$parts[0];
            $token  = $parts[1];
            if ($userId > 0 && strlen($token) === 64) {
                $tokenHash = hash('sha256', $token);
                try {
                    $db   = new ShopDatabase();
                    $user = $db->single(
                        "SELECT * FROM general_users WHERE user_id = ? AND remember_token = ? AND remember_token_expires > NOW() AND is_active = 1",
                        [$userId, $tokenHash]
                    );
                    if ($user) {
                        session_regenerate_id(true);
                        $_SESSION['shop_user_id']   = $user['user_id'];
                        $_SESSION['shop_user_name'] = $user['first_name'];
                        return true;
                    }
                } catch (Exception $e) {
                    error_log('Shop remember-me check failed: ' . $e->getMessage());
                }
            }
        }
        setcookie('sc_shop_remember', '', time() - 3600, APP_COOKIE_PATH);
    }

    return false;
}

// Require shop login
function requireShopLogin() {
    if (!isShopLoggedIn()) {
        $_SESSION['shop_redirect_url'] = $_SERVER['REQUEST_URI'];
        shopRedirect('auth/login.php');
    }
}

// Get logged in shop user
function getShopUser() {
    if (!isShopLoggedIn()) return null;
    
    $db = new ShopDatabase();
    return $db->single(
        "SELECT * FROM general_users WHERE user_id = ?",
        [$_SESSION['shop_user_id']]
    );
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format price
function formatPrice($amount) {
    return '৳' . number_format($amount, 0);
}

// Generate order number
function generateOrderNumber() {
    return 'SC' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Get product image URL
function getProductImage($url) {
    global $base_url;
    if (empty($url)) {
        return $base_url . 'img/no-product.png';
    }
    if (strpos($url, 'http') === 0) {
        return $url;
    }
    // Check if path already starts with public/
    if (strpos($url, 'public/') === 0) {
        return $base_url . ltrim($url, '/');
    }
    // Check if path starts with uploads/
    if (strpos($url, 'uploads/') === 0) {
        return $base_url . 'public/' . ltrim($url, '/');
    }
    return $base_url . 'public/' . ltrim($url, '/');
}

// Get user avatar
function getUserAvatar($url) {
    global $base_url;
    if (empty($url)) {
        return $base_url . 'img/default-avatar.png';
    }
    // Check if path already starts with public/
    if (strpos($url, 'public/') === 0) {
        return $base_url . ltrim($url, '/');
    }
    return $base_url . 'public/' . ltrim($url, '/');
}

// Truncate text
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// Time ago format
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

// Get cart count
function getCartCount() {
    if (!isset($_SESSION)) return 0;
    
    $db = new ShopDatabase();
    
    if (isShopLoggedIn()) {
        $result = $db->single(
            "SELECT SUM(quantity) as count FROM shop_cart WHERE user_id = ?",
            [$_SESSION['shop_user_id']]
        );
    } else {
        $result = $db->single(
            "SELECT SUM(quantity) as count FROM shop_cart WHERE session_id = ? AND user_id IS NULL",
            [session_id()]
        );
    }
    
    return $result['count'] ?? 0;
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['shop_flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['shop_flash'])) {
        $flash = $_SESSION['shop_flash'];
        unset($_SESSION['shop_flash']);
        return $flash;
    }
    return null;
}

// CSRF Token
function generateShopCSRFToken() {
    if (empty($_SESSION['shop_csrf_token'])) {
        $_SESSION['shop_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['shop_csrf_token'];
}

function verifyShopCSRFToken($token) {
    return isset($_SESSION['shop_csrf_token']) && hash_equals($_SESSION['shop_csrf_token'], $token);
}

// JSON responses
function jsonSuccess($message, $data = []) {
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

function jsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Order status badge
function getOrderStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'processing' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $badges[$status] ?? 'badge-secondary';
}

// Payment status badge
function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'paid' => 'badge-success',
        'failed' => 'badge-danger',
        'refunded' => 'badge-info'
    ];
    return $badges[$status] ?? 'badge-secondary';
}

// Get unread notifications count
function getUnreadNotificationCount($userId, $userType = 'general') {
    $db = new ShopDatabase();
    $result = $db->single(
        "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND user_type = ? AND is_read = 0",
        [$userId, $userType]
    );
    return $result['count'] ?? 0;
}

// Get unread messages count
function getUnreadMessageCount($userId, $userType = 'customer') {
    $db = new ShopDatabase();
    if ($userType === 'farmer') {
        $result = $db->single(
            "SELECT SUM(farmer_unread) as count FROM shop_conversations WHERE farmer_id = ?",
            [$userId]
        );
    } else {
        $result = $db->single(
            "SELECT SUM(customer_unread) as count FROM shop_conversations WHERE customer_id = ? AND customer_type = ?",
            [$userId, $userType]
        );
    }
    return $result['count'] ?? 0;
}

// Create notification
function createNotification($userId, $userType, $title, $message, $type = 'system', $link = null, $referenceId = null, $icon = 'notifications') {
    $db = new ShopDatabase();
    return $db->insert('user_notifications', [
        'user_id' => $userId,
        'user_type' => $userType,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link,
        'reference_id' => $referenceId,
        'icon' => $icon
    ]);
}
