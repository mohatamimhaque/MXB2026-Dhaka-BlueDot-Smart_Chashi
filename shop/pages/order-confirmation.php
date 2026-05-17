<?php
/**
 * Order Confirmation Page
 */

require_once __DIR__ . '/../config/config.php';

requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

$orderNumber = sanitize($_GET['order'] ?? '');

if (empty($orderNumber)) {
    shopRedirect('profile/orders.php');
}

// Get order details
$order = $db->single(
    "SELECT * FROM shop_orders WHERE order_number = ? AND buyer_id = ?",
    [$orderNumber, $user['user_id']]
);

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    shopRedirect('profile/orders.php');
}

// Get order items
$orderItems = $db->resultSet(
    "SELECT oi.*, u.first_name as seller_name
     FROM shop_order_items oi
     LEFT JOIN users u ON oi.seller_id = u.user_id
     WHERE oi.order_id = ?",
    [$order['order_id']]
);

$pageTitle = 'Order Confirmed';
include __DIR__ . '/../layouts/header.php';
?>

<div class="confirmation-page container">
    <div class="confirmation-card">
        <div class="success-icon">
            <span class="material-icons">check_circle</span>
        </div>
        
        <h1>Thank You for Your Order!</h1>
        <p class="subtitle">Your order has been placed successfully.</p>
        
        <div class="order-number">
            <span>Order Number</span>
            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
        </div>
        
        <div class="confirmation-details">
            <div class="detail-section">
                <h3><span class="material-icons">local_shipping</span> Shipping Address</h3>
                <p>
                    <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong><br>
                    <?php echo htmlspecialchars($order['shipping_phone']); ?><br>
                    <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                    <?php if ($order['shipping_city']): echo htmlspecialchars($order['shipping_city']) . ', '; endif; ?>
                    <?php echo htmlspecialchars($order['shipping_district']); ?>
                    <?php if ($order['shipping_postal_code']): echo ' - ' . htmlspecialchars($order['shipping_postal_code']); endif; ?>
                </p>
            </div>
            
            <div class="detail-section">
                <h3><span class="material-icons">payment</span> Payment</h3>
                <p>
                    <strong>Method:</strong> 
                    <?php 
                    $paymentLabels = ['cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank Transfer'];
                    echo $paymentLabels[$order['payment_method']] ?? ucfirst($order['payment_method']); 
                    ?><br>
                    <strong>Status:</strong> 
                    <span class="badge <?php echo getPaymentStatusBadge($order['payment_status']); ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <div class="order-items-summary">
            <h3>Order Items</h3>
            <div class="items-list">
                <?php foreach ($orderItems as $item): ?>
                <div class="item">
                    <img src="<?php echo getProductImage($item['product_image']); ?>" alt="">
                    <div class="item-info">
                        <span class="name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                        <span class="meta">Qty: <?php echo $item['quantity']; ?> | Seller: <?php echo htmlspecialchars($item['seller_name']); ?></span>
                    </div>
                    <span class="price"><?php echo formatPrice($item['total_price']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span><?php echo formatPrice($order['subtotal']); ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping</span>
                    <span><?php echo $order['shipping_cost'] == 0 ? 'FREE' : formatPrice($order['shipping_cost']); ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                <div class="total-row discount">
                    <span>Discount</span>
                    <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row grand">
                    <span>Total</span>
                    <span><?php echo formatPrice($order['total_amount']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="<?php echo shopUrl('profile/orders.php'); ?>" class="btn btn-primary">
                <span class="material-icons">list_alt</span>
                View My Orders
            </a>
            <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-outline">
                <span class="material-icons">storefront</span>
                Continue Shopping
            </a>
        </div>
        
        <div class="info-note">
            <span class="material-icons">info</span>
            <p>A confirmation email has been sent to <strong><?php echo htmlspecialchars($user['email']); ?></strong>. 
               You can also track your order status in <a href="<?php echo shopUrl('profile/orders.php'); ?>">My Orders</a>.</p>
        </div>
    </div>
</div>

<style>
.confirmation-page {
    padding: var(--spacing-xl) var(--spacing-md);
    display: flex;
    justify-content: center;
}

.confirmation-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: var(--spacing-2xl);
    box-shadow: var(--shadow-lg);
    max-width: 700px;
    width: 100%;
    text-align: center;
}

.success-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--success), #059669);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--spacing-lg);
}

.success-icon .material-icons {
    font-size: 3rem;
    color: var(--white);
}

.confirmation-card h1 {
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-sm);
}

.subtitle {
    color: var(--gray-500);
    margin-bottom: var(--spacing-xl);
}

.order-number {
    background: var(--gray-50);
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-md);
    display: inline-block;
    margin-bottom: var(--spacing-xl);
}

.order-number span {
    display: block;
    font-size: var(--font-size-sm);
    color: var(--gray-500);
    margin-bottom: var(--spacing-xs);
}

.order-number strong {
    font-size: var(--font-size-xl);
    color: var(--primary);
    letter-spacing: 1px;
}

.confirmation-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-lg);
    text-align: left;
    margin-bottom: var(--spacing-xl);
}

@media (max-width: 576px) {
    .confirmation-details {
        grid-template-columns: 1fr;
    }
}

.detail-section {
    background: var(--gray-50);
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
}

.detail-section h3 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-sm);
    color: var(--gray-700);
    margin-bottom: var(--spacing-sm);
}

.detail-section h3 .material-icons {
    font-size: 1.25rem;
    color: var(--primary);
}

.detail-section p {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    line-height: 1.6;
}

.order-items-summary {
    text-align: left;
    margin-bottom: var(--spacing-xl);
}

.order-items-summary h3 {
    font-size: var(--font-size-base);
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.items-list {
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--gray-100);
}

.item:last-child {
    border-bottom: none;
}

.item img {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-sm);
    object-fit: cover;
}

.item-info {
    flex: 1;
}

.item-info .name {
    display: block;
    font-weight: 500;
    color: var(--gray-800);
    font-size: var(--font-size-sm);
}

.item-info .meta {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
}

.item .price {
    font-weight: 600;
    color: var(--gray-700);
}

.totals {
    padding: var(--spacing-md);
    background: var(--gray-50);
    border-radius: var(--radius-md);
    margin-top: var(--spacing-md);
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: var(--spacing-xs) 0;
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.total-row.discount {
    color: var(--success);
}

.total-row.grand {
    font-size: var(--font-size-lg);
    font-weight: 700;
    color: var(--gray-800);
    padding-top: var(--spacing-sm);
    margin-top: var(--spacing-sm);
    border-top: 1px solid var(--gray-200);
}

.total-row.grand span:last-child {
    color: var(--primary);
}

.confirmation-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: var(--spacing-xl);
}

.info-note {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-sm);
    text-align: left;
    background: rgba(59, 130, 246, 0.1);
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
}

.info-note .material-icons {
    color: var(--info);
    flex-shrink: 0;
}

.info-note p {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    margin: 0;
}

.info-note a {
    font-weight: 600;
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
