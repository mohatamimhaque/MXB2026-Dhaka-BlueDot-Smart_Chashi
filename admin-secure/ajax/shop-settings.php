<?php
/**
 * Admin Shop Settings AJAX Handler
 * Saves key-value pairs to the shop_settings table.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../shop/config/config.php';
ob_end_clean();

header('Content-Type: application/json');

function shopJsonResponse(array $data): void {
    echo json_encode($data);
    exit;
}

// Admin auth
if (empty($_SESSION['user_id'])) {
    shopJsonResponse(['success' => false, 'message' => 'Unauthorized']);
}

$user = (new Database())->single("SELECT role FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
if (!$user || $user['role'] !== 'admin') {
    shopJsonResponse(['success' => false, 'message' => 'Forbidden']);
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    shopJsonResponse(['success' => false, 'message' => 'Invalid request']);
}

// CSRF check
$csrfToken = $body['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    shopJsonResponse(['success' => false, 'message' => 'Invalid CSRF token']);
}

// Keys we allow to be saved via this endpoint
$allowedKeys = [
    'delivery_note', 'delivery_charge',
    'footer_about', 'footer_email', 'footer_phone', 'footer_address',
    'footer_facebook', 'footer_instagram', 'footer_youtube', 'footer_twitter',
    'footer_copyright', 'shop_name_override'
];

$db = new ShopDatabase();

try {
    $updated = 0;
    foreach ($allowedKeys as $key) {
        if (!array_key_exists($key, $body)) continue;
        $value = trim((string)$body[$key]);

        // Upsert into shop_settings
        $existing = $db->single("SELECT setting_id FROM shop_settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->update('shop_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('shop_settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_type'  => 'text',
                'setting_group' => 'shop'
            ]);
        }
        $updated++;
    }

    shopJsonResponse(['success' => true, 'message' => "Saved $updated setting(s) successfully."]);

} catch (Exception $e) {
    error_log('shop-settings ajax error: ' . $e->getMessage());
    shopJsonResponse(['success' => false, 'message' => 'Database error. Please try again.']);
}
