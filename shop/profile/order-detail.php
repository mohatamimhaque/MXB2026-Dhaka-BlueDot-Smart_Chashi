<?php
/**
 * Order Detail Page
 */

require_once __DIR__ . '/../config/config.php';

requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

$orderId = intval($_GET['id'] ?? 0);

if (!$orderId) {
    shopRedirect('profile/orders.php');
}

// Get order
$order = $db->single(
    "SELECT * FROM shop_orders WHERE order_id = ? AND buyer_id = ?",
    [$orderId, $user['user_id']]
);

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    shopRedirect('profile/orders.php');
}

// Get order items
$items = $db->resultSet(
    "SELECT oi.*, u.first_name as seller_name, u.phone as seller_phone
     FROM shop_order_items oi
     LEFT JOIN users u ON oi.seller_id = u.user_id
     WHERE oi.order_id = ?",
    [$orderId]
);

$pageTitle = 'Order #' . $order['order_number'];
include __DIR__ . '/../layouts/header.php';
?>

<div class="order-detail-page container">
    <div class="page-header">
        <a href="<?php echo shopUrl('profile/orders.php'); ?>" class="back-link">
            <span class="material-icons">arrow_back</span>
            Back to Orders
        </a>
        <h1>Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
        <span class="order-date"><?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></span>
    </div>

    <!-- Status -->
    <div class="order-status-bar">
        <div class="status-badges">
            <span class="badge badge-lg <?php echo getOrderStatusBadge($order['order_status']); ?>">
                <?php echo ucfirst($order['order_status']); ?>
            </span>
            <span class="badge badge-lg <?php echo getPaymentStatusBadge($order['payment_status']); ?>">
                Payment: <?php echo ucfirst($order['payment_status']); ?>
            </span>
        </div>
        <?php if ($order['order_status'] === 'pending'): ?>
        <button class="btn btn-ghost btn-sm" onclick="cancelOrder(<?php echo $orderId; ?>)">
            <span class="material-icons">cancel</span>
            Cancel Order
        </button>
        <?php endif; ?>
    </div>

    <div class="order-layout">
        <!-- Order Items -->
        <div class="order-items-section">
            <h2>Order Items (<?php echo count($items); ?>)</h2>
            <div class="items-list">
                <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <img src="<?php echo getProductImage($item['product_image']); ?>" alt="">
                    <div class="item-details">
                        <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                        <p class="seller">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></p>
                        <p class="qty">Qty: <?php echo $item['quantity']; ?> × <?php echo formatPrice($item['unit_price']); ?></p>
                    </div>
                    <div class="item-total">
                        <?php echo formatPrice($item['total_price']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <div class="order-totals">
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

        <!-- Sidebar -->
        <div class="order-sidebar">
            <!-- Shipping Address -->
            <div class="info-card">
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

            <!-- Payment Info -->
            <div class="info-card">
                <h3><span class="material-icons">payment</span> Payment</h3>
                <p>
                    <strong>Method:</strong> 
                    <?php 
                    $paymentLabels = ['cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank Transfer'];
                    echo $paymentLabels[$order['payment_method']] ?? ucfirst($order['payment_method']); 
                    ?><br>
                    <strong>Status:</strong> <?php echo ucfirst($order['payment_status']); ?>
                    <?php if ($order['payment_transaction_id']): ?>
                    <br><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['payment_transaction_id']); ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!empty($order['notes'])): ?>
            <!-- Order Notes -->
            <div class="info-card">
                <h3><span class="material-icons">note</span> Order Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Help -->
            <div class="info-card help">
                <h3><span class="material-icons">help</span> Need Help?</h3>
                <p>If you have any questions about your order, please contact us.</p>
                <a href="mailto:info@smartchashi.com" class="btn btn-outline btn-sm btn-block">
                    <span class="material-icons">email</span>
                    Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.order-detail-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.page-header {
    margin-bottom: var(--spacing-lg);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--gray-500);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-md);
}

.back-link:hover {
    color: var(--primary);
}

.page-header h1 {
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.order-date {
    color: var(--gray-500);
}

.order-status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--white);
    padding: var(--spacing-md);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.status-badges {
    display: flex;
    gap: var(--spacing-sm);
}

.badge-lg {
    padding: var(--spacing-sm) var(--spacing-md);
    font-size: var(--font-size-sm);
}

.order-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .order-layout {
        grid-template-columns: 1fr 350px;
    }
}

.order-items-section {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.order-items-section h2 {
    padding: var(--spacing-lg);
    font-size: var(--font-size-lg);
    color: var(--gray-800);
    border-bottom: 1px solid var(--gray-100);
}

.items-list {
    padding: var(--spacing-md);
}

.order-item {
    display: flex;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--gray-100);
}

.order-item:last-child {
    border-bottom: none;
}

.order-item img {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-md);
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    font-size: var(--font-size-base);
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.item-details .seller,
.item-details .qty {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
    margin: 0;
}

.item-total {
    font-weight: 600;
    color: var(--gray-800);
}

.order-totals {
    padding: var(--spacing-lg);
    background: var(--gray-50);
    border-top: 1px solid var(--gray-100);
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

.order-sidebar {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.info-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}

.info-card h3 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-sm);
    color: var(--gray-700);
    margin-bottom: var(--spacing-md);
}

.info-card h3 .material-icons {
    color: var(--primary);
}

.info-card p {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    line-height: 1.6;
}

.info-card.help {
    background: rgba(85, 122, 70, 0.05);
    border: 1px solid rgba(85, 122, 70, 0.2);
}
</style>

<script>
async function cancelOrder(orderId) {
    if (!await confirmAction('Are you sure you want to cancel this order?')) return;
    
    showLoading();
    
    const result = await ajaxRequest('<?php echo shopUrl('ajax/orders.php'); ?>', {
        body: { action: 'cancel', order_id: orderId }
    });
    
    hideLoading();
    
    if (result.success) {
        showToast('Order cancelled successfully', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(result.message, 'error');
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
