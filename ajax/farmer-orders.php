<?php
/**
 * Farmer Orders AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Require farmer login
if (!isLoggedIn() || getCurrentUser()['role'] !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$farmerId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_order':
            $orderId = intval($_GET['order_id'] ?? 0);
            $source = $_GET['source'] ?? 'marketplace';
            
            if (!$orderId) {
                echo json_encode(['success' => false, 'message' => 'Invalid order']);
                exit;
            }
            
            if ($source === 'marketplace') {
                $order = $db->query(
                    "SELECT mo.*, u.first_name, u.last_name, u.phone, u.email
                     FROM marketplace_orders mo
                     LEFT JOIN users u ON mo.buyer_id = u.user_id
                     WHERE mo.order_id = ? AND mo.seller_id = ?"
                )->bind(1, $orderId)->bind(2, $farmerId)->fetch();
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                
                // Get product info
                $product = $db->query(
                    "SELECT * FROM marketplace_products WHERE product_id = ?"
                )->bind(1, $order['product_id'])->fetch();
                
                $html = renderMarketplaceOrderDetails($order, $product);
            } else {
                $order = $db->query(
                    "SELECT so.* FROM shop_orders so
                     INNER JOIN shop_order_items soi ON so.order_id = soi.order_id AND soi.seller_id = ?
                     WHERE so.order_id = ?"
                )->bind(1, $farmerId)->bind(2, $orderId)->fetch();
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                
                // Get items for this farmer
                $items = $db->query(
                    "SELECT * FROM shop_order_items WHERE order_id = ? AND seller_id = ?"
                )->bind(1, $orderId)->bind(2, $farmerId)->fetchAll();
                
                $html = renderShopOrderDetails($order, $items);
            }
            
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        case 'update_status':
            $orderId = intval($_POST['order_id'] ?? 0);
            $source = $_POST['source'] ?? 'marketplace';
            $newStatus = $_POST['status'] ?? '';
            
            $validStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!$orderId || !in_array($newStatus, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            if ($source === 'marketplace') {
                // Verify ownership
                $order = $db->query("SELECT * FROM marketplace_orders WHERE order_id = ? AND seller_id = ?")
                    ->bind(1, $orderId)->bind(2, $farmerId)->fetch();
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                
                $db->query("UPDATE marketplace_orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?")
                    ->bind(1, $newStatus)->bind(2, $orderId)->execute();
                    
                // Create notification for buyer
                createNotificationForUser($order['buyer_id'], 'farmer', 
                    'Order ' . ucfirst($newStatus),
                    'Your order #' . $order['order_number'] . ' is now ' . $newStatus,
                    'order', '?page=marketplace', $orderId);
                    
            } else {
                // Verify this farmer has items in this order
                $orderItem = $db->query("SELECT * FROM shop_order_items WHERE order_id = ? AND seller_id = ?")
                    ->bind(1, $orderId)->bind(2, $farmerId)->fetch();
                
                if (!$orderItem) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                
                // Get order details
                $order = $db->query("SELECT * FROM shop_orders WHERE order_id = ?")->bind(1, $orderId)->fetch();
                
                $db->query("UPDATE shop_orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?")
                    ->bind(1, $newStatus)->bind(2, $orderId)->execute();
                    
                // Create notification for customer
                createNotificationForUser($order['buyer_id'], 'general',
                    'Order ' . ucfirst($newStatus),
                    'Your order #' . $order['order_number'] . ' is now ' . $newStatus,
                    'order', null, $orderId);
            }
            
            echo json_encode(['success' => true, 'message' => 'Status updated']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

/**
 * Create notification for user
 */
function createNotificationForUser($userId, $userType, $title, $message, $type, $link, $refId) {
    global $db;
    try {
        $db->query("INSERT INTO user_notifications (user_id, user_type, title, message, type, link, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->bind(1, $userId)
            ->bind(2, $userType)
            ->bind(3, $title)
            ->bind(4, $message)
            ->bind(5, $type)
            ->bind(6, $link)
            ->bind(7, $refId)
            ->execute();
    } catch (Exception $e) {
        // Silently fail for notifications
    }
}

/**
 * Render marketplace order details HTML
 */
function renderMarketplaceOrderDetails($order, $product) {
    $statusClass = $order['order_status'];
    $productImg = !empty($product['image_url']) ? '../public/' . $product['image_url'] : '../img/no-product.png';
    
    $html = '<div class="order-detail">';
    $html .= '<div class="order-detail-header">';
    $html .= '<span class="order-number">#' . htmlspecialchars($order['order_number']) . '</span>';
    $html .= '<span class="status-badge ' . $statusClass . '">' . ucfirst($order['order_status']) . '</span>';
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<h4>Product</h4>';
    $html .= '<div class="product-item">';
    $html .= '<img src="' . $productImg . '" alt="">';
    $html .= '<div class="product-info">';
    $html .= '<span class="name">' . htmlspecialchars($product['product_name'] ?? 'Product') . '</span>';
    $html .= '<span class="qty">Qty: ' . $order['quantity'] . '</span>';
    $html .= '</div>';
    $html .= '<span class="price">৳' . number_format($order['total_amount']) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<h4>Customer</h4>';
    $html .= '<p><strong>' . htmlspecialchars($order['first_name'] . ' ' . ($order['last_name'] ?? '')) . '</strong></p>';
    $html .= '<p>' . htmlspecialchars($order['phone'] ?? '') . '</p>';
    $html .= '<p>' . htmlspecialchars($order['email'] ?? '') . '</p>';
    $html .= '</div>';
    
    if (!empty($order['notes'])) {
        $html .= '<div class="order-section">';
        $html .= '<h4>Notes</h4>';
        $html .= '<p>' . nl2br(htmlspecialchars($order['notes'])) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '<div class="order-section">';
    $html .= '<p><small>Ordered: ' . date('M j, Y g:i A', strtotime($order['created_at'])) . '</small></p>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render shop order details HTML
 */
function renderShopOrderDetails($order, $items) {
    $statusClass = $order['order_status'];
    
    $html = '<div class="order-detail">';
    $html .= '<div class="order-detail-header">';
    $html .= '<span class="order-number">#' . htmlspecialchars($order['order_number']) . '</span>';
    $html .= '<span class="status-badge ' . $statusClass . '">' . ucfirst($order['order_status']) . '</span>';
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<h4>Your Items (' . count($items) . ')</h4>';
    foreach ($items as $item) {
        $itemImg = !empty($item['product_image']) ? '../public/' . $item['product_image'] : '../img/no-product.png';
        $html .= '<div class="product-item">';
        $html .= '<img src="' . $itemImg . '" alt="">';
        $html .= '<div class="product-info">';
        $html .= '<span class="name">' . htmlspecialchars($item['product_name']) . '</span>';
        $html .= '<span class="qty">Qty: ' . $item['quantity'] . ' × ৳' . number_format($item['unit_price']) . '</span>';
        $html .= '</div>';
        $html .= '<span class="price">৳' . number_format($item['total_price']) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<h4>Shipping Address</h4>';
    $html .= '<p><strong>' . htmlspecialchars($order['shipping_name']) . '</strong></p>';
    $html .= '<p>' . htmlspecialchars($order['shipping_phone']) . '</p>';
    $html .= '<p>' . htmlspecialchars($order['shipping_address']) . '</p>';
    $html .= '<p>' . htmlspecialchars($order['shipping_district']) . '</p>';
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<h4>Payment</h4>';
    $html .= '<p>Method: ' . strtoupper($order['payment_method']) . '</p>';
    $html .= '<p>Status: <span class="status-badge ' . $order['payment_status'] . '">' . ucfirst($order['payment_status']) . '</span></p>';
    $html .= '</div>';
    
    $html .= '<div class="order-section">';
    $html .= '<p><small>Ordered: ' . date('M j, Y g:i A', strtotime($order['created_at'])) . '</small></p>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Add some inline styles for the detail view
    $html .= '<style>
        .order-detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
        .order-detail .order-number { font-size: 1.2rem; font-weight: 700; }
        .order-section { margin-bottom: 1.5rem; }
        .order-section h4 { font-size: 0.9rem; color: #6b7280; margin-bottom: 0.75rem; text-transform: uppercase; }
        .product-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6; }
        .product-item:last-child { border-bottom: none; }
        .product-item img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .product-info { flex: 1; }
        .product-info .name { display: block; font-weight: 500; color: #1f2937; }
        .product-info .qty { font-size: 0.85rem; color: #6b7280; }
        .product-item .price { font-weight: 600; color: #557A46; }
    </style>';
    
    return $html;
}
