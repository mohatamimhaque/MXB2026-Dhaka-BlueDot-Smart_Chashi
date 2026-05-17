<?php
/**
 * Shop Cart AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$db = new ShopDatabase();

// Support JSON body (sent by shopPost()) as well as form-encoded POST
$_jsonBody = json_decode(file_get_contents('php://input'), true);
if (is_array($_jsonBody) && !empty($_jsonBody)) {
    $_POST = array_merge($_POST, $_jsonBody);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = max(1, intval($_POST['quantity'] ?? 1));
            
            if (!$productId) {
                jsonError('Invalid product');
            }
            
            // Check product exists and is available
            $product = $db->single(
                "SELECT product_id, quantity_available, min_order_quantity FROM marketplace_products WHERE product_id = ? AND status = 'available'",
                [$productId]
            );
            
            if (!$product) {
                jsonError('Product not available');
            }
            
            // Check minimum order quantity
            if ($product['min_order_quantity'] && $quantity < $product['min_order_quantity']) {
                $quantity = $product['min_order_quantity'];
            }
            
            $userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
            $sessionId = $userId ? null : session_id();
            
            // Check if already in cart
            if ($userId) {
                $existing = $db->single(
                    "SELECT cart_id, quantity FROM shop_cart WHERE user_id = ? AND product_id = ?",
                    [$userId, $productId]
                );
            } else {
                $existing = $db->single(
                    "SELECT cart_id, quantity FROM shop_cart WHERE session_id = ? AND product_id = ? AND user_id IS NULL",
                    [$sessionId, $productId]
                );
            }
            
            if ($existing) {
                // Update quantity
                $newQty = $existing['quantity'] + $quantity;
                if ($product['quantity_available'] && $newQty > $product['quantity_available']) {
                    $newQty = $product['quantity_available'];
                }
                $db->update('shop_cart', ['quantity' => $newQty], 'cart_id = ?', [$existing['cart_id']]);
            } else {
                // Add new
                $db->insert('shop_cart', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'product_id' => $productId,
                    'quantity' => $quantity
                ]);
            }
            
            jsonSuccess('Added to cart!', ['cart_count' => getCartCount()]);
            break;
            
        case 'update':
            $cartId = intval($_POST['cart_id'] ?? 0);
            $quantity = max(1, intval($_POST['quantity'] ?? 1));
            
            if (!$cartId) {
                jsonError('Invalid cart item');
            }
            
            // Verify ownership
            $userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
            $sessionId = $userId ? null : session_id();
            
            if ($userId) {
                $cartItem = $db->single("SELECT c.*, p.quantity_available FROM shop_cart c JOIN marketplace_products p ON c.product_id = p.product_id WHERE c.cart_id = ? AND c.user_id = ?", [$cartId, $userId]);
            } else {
                $cartItem = $db->single("SELECT c.*, p.quantity_available FROM shop_cart c JOIN marketplace_products p ON c.product_id = p.product_id WHERE c.cart_id = ? AND c.session_id = ? AND c.user_id IS NULL", [$cartId, $sessionId]);
            }
            
            if (!$cartItem) {
                jsonError('Cart item not found');
            }
            
            // Check stock
            if ($cartItem['quantity_available'] && $quantity > $cartItem['quantity_available']) {
                $quantity = $cartItem['quantity_available'];
            }
            
            $db->update('shop_cart', ['quantity' => $quantity], 'cart_id = ?', [$cartId]);
            
            jsonSuccess('Cart updated', ['cart_count' => getCartCount()]);
            break;
            
        case 'remove':
            $cartId = intval($_POST['cart_id'] ?? 0);
            
            if (!$cartId) {
                jsonError('Invalid cart item');
            }
            
            // Verify ownership
            $userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
            $sessionId = $userId ? null : session_id();
            
            if ($userId) {
                $db->query("DELETE FROM shop_cart WHERE cart_id = ? AND user_id = ?")
                   ->bind(1, $cartId)->bind(2, $userId)->execute();
            } else {
                $db->query("DELETE FROM shop_cart WHERE cart_id = ? AND session_id = ? AND user_id IS NULL")
                   ->bind(1, $cartId)->bind(2, $sessionId)->execute();
            }
            
            jsonSuccess('Item removed', ['cart_count' => getCartCount()]);
            break;
            
        case 'get':
            $items = getCartItems();
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            jsonSuccess('Cart retrieved', [
                'items' => $items,
                'subtotal' => $subtotal,
                'cart_count' => getCartCount()
            ]);
            break;
            
        case 'clear':
            $userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
            $sessionId = $userId ? null : session_id();
            
            if ($userId) {
                $db->query("DELETE FROM shop_cart WHERE user_id = ?")->bind(1, $userId)->execute();
            } else {
                $db->query("DELETE FROM shop_cart WHERE session_id = ? AND user_id IS NULL")->bind(1, $sessionId)->execute();
            }
            
            jsonSuccess('Cart cleared', ['cart_count' => 0]);
            break;
            
        default:
            jsonError('Invalid action');
    }
} catch (Exception $e) {
    jsonError('An error occurred: ' . $e->getMessage());
}

/**
 * Get cart items for current user/session
 */
function getCartItems() {
    global $db;
    
    $userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
    $sessionId = $userId ? null : session_id();
    
    if ($userId) {
        return $db->resultSet(
            "SELECT c.*, p.product_name, p.price, p.price_unit, p.image_url, p.quantity_available, p.seller_id,
                    u.first_name as seller_name
             FROM shop_cart c
             JOIN marketplace_products p ON c.product_id = p.product_id
             LEFT JOIN users u ON p.seller_id = u.user_id
             WHERE c.user_id = ? AND p.status = 'available'
             ORDER BY c.created_at DESC",
            [$userId]
        );
    } else {
        return $db->resultSet(
            "SELECT c.*, p.product_name, p.price, p.price_unit, p.image_url, p.quantity_available, p.seller_id,
                    u.first_name as seller_name
             FROM shop_cart c
             JOIN marketplace_products p ON c.product_id = p.product_id
             LEFT JOIN users u ON p.seller_id = u.user_id
             WHERE c.session_id = ? AND c.user_id IS NULL AND p.status = 'available'
             ORDER BY c.created_at DESC",
            [$sessionId]
        );
    }
}
