<?php
/**
 * Shop Orders AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isShopLoggedIn()) {
    jsonError('Please login first', 401);
}

$db = new ShopDatabase();
$user = getShopUser();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'cancel':
            $orderId = intval($_POST['order_id'] ?? 0);
            
            if (!$orderId) {
                jsonError('Invalid order');
            }
            
            // Verify ownership and status
            $order = $db->single(
                "SELECT * FROM shop_orders WHERE order_id = ? AND buyer_id = ? AND order_status = 'pending'",
                [$orderId, $user['user_id']]
            );
            
            if (!$order) {
                jsonError('Cannot cancel this order');
            }
            
            // Update status
            $db->update('shop_orders', 
                ['order_status' => 'cancelled'],
                'order_id = ?',
                [$orderId]
            );
            
            // Restore product quantities
            $items = $db->resultSet(
                "SELECT product_id, quantity FROM shop_order_items WHERE order_id = ?",
                [$orderId]
            );
            
            foreach ($items as $item) {
                $db->query("UPDATE marketplace_products SET quantity_available = quantity_available + ? WHERE product_id = ?")
                   ->bind(1, $item['quantity'])->bind(2, $item['product_id'])->execute();
            }
            
            jsonSuccess('Order cancelled successfully');
            break;
            
        default:
            jsonError('Invalid action');
    }
} catch (Exception $e) {
    jsonError('An error occurred');
}
