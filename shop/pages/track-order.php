<?php
/**
 * Order Tracking Page
 * Shows live delivery status with timeline
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();

// Get order by number or ID
$orderNumber = sanitize($_GET['order'] ?? '');
$orderId = intval($_GET['id'] ?? 0);

$order = null;
$isOwner = false;

if ($orderNumber) {
    $order = $db->single("SELECT * FROM shop_orders WHERE order_number = ?", [$orderNumber]);
} elseif ($orderId && isShopLoggedIn()) {
    $order = $db->single(
        "SELECT * FROM shop_orders WHERE order_id = ? AND buyer_id = ?",
        [$orderId, $_SESSION['shop_user_id']]
    );
}

if ($order && isShopLoggedIn() && $order['buyer_id'] == $_SESSION['shop_user_id']) {
    $isOwner = true;
}

// Get order items if order found
$orderItems = [];
if ($order) {
    $orderItems = $db->resultSet(
        "SELECT oi.*, u.first_name as seller_name, u.phone as seller_phone
         FROM shop_order_items oi
         LEFT JOIN users u ON oi.seller_id = u.user_id
         WHERE oi.order_id = ?",
        [$order['order_id']]
    );
}

// Define tracking steps
$trackingSteps = [
    'pending'    => ['icon' => 'pending_actions', 'label' => __('order_placed_step'),   'description' => __('order_received_step_desc')],
    'confirmed'  => ['icon' => 'check_circle',    'label' => __('confirmed_status'),    'description' => __('seller_confirmed_step_desc')],
    'processing' => ['icon' => 'inventory_2',     'label' => __('processing'),          'description' => __('order_being_prepared_desc')],
    'shipped'    => ['icon' => 'local_shipping',  'label' => __('shipped_status'),      'description' => __('package_on_way_desc')],
    'delivered'  => ['icon' => 'where_to_vote',   'label' => __('delivered_status'),    'description' => __('delivered_successfully_desc')],
];

// Get current step index
$statusOrder = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$currentStepIndex = $order ? array_search($order['order_status'], $statusOrder) : -1;
$isCancelled = $order && $order['order_status'] === 'cancelled';

$pageTitle = 'Track Order';
include __DIR__ . '/../layouts/header.php';
?>

<div class="tracking-page container">
    <?php if (!$order): ?>
    <!-- Order Search Form -->
    <div class="tracking-search">
        <div class="search-card">
            <span class="search-icon material-icons">local_shipping</span>
            <h1><?php echo __('track_your_order_title'); ?></h1>
            <p><?php echo __('enter_order_number_desc'); ?></p>
            
            <form action="" method="GET" class="tracking-form">
                <div class="form-group">
                    <input type="text" name="order" class="form-control" 
                           placeholder="Enter Order Number (e.g., SC20260116XXXXXX)" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    <span class="material-icons">search</span>
                    <?php echo __('track_order_btn'); ?>
                </button>
            </form>
            
            <?php if (isShopLoggedIn()): ?>
            <div class="or-divider">
                <span>or</span>
            </div>
            <a href="<?php echo shopUrl('profile/orders.php'); ?>" class="btn btn-outline btn-block">
                <span class="material-icons">list_alt</span>
                <?php echo __('view_all_my_orders'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($isCancelled): ?>
    <!-- Cancelled Order -->
    <div class="tracking-result">
        <div class="order-header">
            <div class="order-info">
                <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
            </div>
            <span class="badge badge-danger"><?php echo __('cancelled_status'); ?></span>
        </div>

        <div class="cancelled-notice">
            <span class="material-icons">cancel</span>
            <h2><?php echo __('order_cancelled_title'); ?></h2>
            <p><?php echo __('order_cancelled_notice'); ?></p>
            <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary">
                <?php echo __('continue_shopping'); ?>
            </a>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Order Tracking Result -->
    <div class="tracking-result">
        <div class="order-header">
            <div class="order-info">
                <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                <span class="order-date"><?php echo __('ordered_on'); ?> <?php echo date('M j, Y, g:i A', strtotime($order['created_at'])); ?></span>
            </div>
            <span class="badge <?php echo getOrderStatusBadge($order['order_status']); ?>">
                <?php echo ucfirst($order['order_status']); ?>
            </span>
        </div>
        
        <!-- Tracking Timeline -->
        <div class="tracking-timeline">
            <?php foreach ($trackingSteps as $status => $step): 
                $stepIndex = array_search($status, $statusOrder);
                $isCompleted = $stepIndex <= $currentStepIndex;
                $isCurrent = $stepIndex === $currentStepIndex;
            ?>
            <div class="timeline-step <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                <div class="step-icon">
                    <span class="material-icons"><?php echo $step['icon']; ?></span>
                </div>
                <div class="step-content">
                    <h4><?php echo $step['label']; ?></h4>
                    <p><?php echo $step['description']; ?></p>
                    <?php if ($isCurrent && $order['updated_at']): ?>
                    <span class="step-time"><?php echo timeAgo($order['updated_at']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Delivery Info -->
        <?php if ($order['order_status'] === 'shipped'): ?>
        <div class="delivery-info">
            <span class="material-icons">info</span>
            <div>
                <strong><?php echo __('estimated_delivery_title'); ?></strong>
                <p><?php echo __('arrive_within_days'); ?></p>
            </div>
        </div>
        <?php elseif ($order['order_status'] === 'delivered'): ?>
        <div class="delivery-info success">
            <span class="material-icons">check_circle</span>
            <div>
                <strong><?php echo __('delivered_title'); ?></strong>
                <p><?php echo __('delivered_success_desc'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Order Details Accordion -->
        <div class="tracking-details">
            <details class="detail-section" open>
                <summary>
                    <span class="material-icons">inventory_2</span>
                    <?php echo __('order_items_section'); ?> (<?php echo count($orderItems); ?>)
                </summary>
                <div class="items-list">
                    <?php foreach ($orderItems as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo getProductImage($item['product_image']); ?>" alt="">
                        <div class="item-info">
                            <h5><?php echo htmlspecialchars($item['product_name']); ?></h5>
                            <span class="seller">From: <?php echo htmlspecialchars($item['seller_name']); ?></span>
                        </div>
                        <div class="item-qty">x<?php echo $item['quantity']; ?></div>
                        <div class="item-price"><?php echo formatPrice($item['total_price']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
            
            <details class="detail-section">
                <summary>
                    <span class="material-icons">local_shipping</span>
                    <?php echo __('shipping_address_section'); ?>
                </summary>
                <div class="address-info">
                    <p>
                        <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong><br>
                        <?php echo htmlspecialchars($order['shipping_phone']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                        <?php if ($order['shipping_city']): echo htmlspecialchars($order['shipping_city']) . ', '; endif; ?>
                        <?php echo htmlspecialchars($order['shipping_district']); ?>
                        <?php if ($order['shipping_postal_code']): echo ' - ' . htmlspecialchars($order['shipping_postal_code']); endif; ?>
                    </p>
                </div>
            </details>
            
            <details class="detail-section">
                <summary>
                    <span class="material-icons">payment</span>
                    <?php echo __('payment_summary_section'); ?>
                </summary>
                <div class="payment-summary">
                    <div class="summary-row">
                        <span><?php echo __('subtotal'); ?></span>
                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span><?php echo __('shipping_label'); ?></span>
                        <span><?php echo $order['shipping_cost'] == 0 ? __('shipping_free') : formatPrice($order['shipping_cost']); ?></span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="summary-row discount">
                        <span><?php echo __('discount_label'); ?></span>
                        <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span><?php echo __('total_label'); ?></span>
                        <span><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                    <div class="payment-method">
                        <span class="material-icons">credit_card</span>
                        <?php 
                        $methods = ['cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad'];
                        echo $methods[$order['payment_method']] ?? ucfirst($order['payment_method']); 
                        ?>
                        <span class="badge <?php echo getPaymentStatusBadge($order['payment_status']); ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </details>
        </div>
        
        <!-- Actions -->
        <div class="tracking-actions">
            <?php if ($isOwner): ?>
            <a href="<?php echo shopUrl('messages/?order=' . $order['order_id']); ?>" class="btn btn-outline">
                <span class="material-icons">chat</span>
                <?php echo __('contact_seller_action'); ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary">
                <span class="material-icons">storefront</span>
                <?php echo __('continue_shopping'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.tracking-page {
    padding: var(--spacing-xl) var(--spacing-md);
    max-width: 800px;
    margin: 0 auto;
}

/* Search Card */
.tracking-search {
    display: flex;
    justify-content: center;
    padding: var(--spacing-2xl) 0;
}

.search-card {
    background: var(--white);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    text-align: center;
    max-width: 450px;
    width: 100%;
}

.search-icon {
    font-size: 4rem;
    color: var(--primary);
    margin-bottom: var(--spacing-md);
}

.search-card h1 {
    font-size: var(--font-size-xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-sm);
}

.search-card > p {
    color: var(--gray-500);
    margin-bottom: var(--spacing-xl);
}

.tracking-form .form-control {
    text-align: center;
    font-size: var(--font-size-lg);
    padding: var(--spacing-md);
}

.or-divider {
    display: flex;
    align-items: center;
    margin: var(--spacing-lg) 0;
    color: var(--gray-400);
}

.or-divider::before,
.or-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--gray-200);
}

.or-divider span {
    padding: 0 var(--spacing-md);
}

/* Order Header */
.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--gray-200);
}

.order-number {
    display: block;
    font-size: var(--font-size-lg);
    font-weight: 700;
    color: var(--gray-800);
}

.order-date {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

/* Timeline */
.tracking-timeline {
    position: relative;
    padding: var(--spacing-lg) 0;
    margin-bottom: var(--spacing-xl);
}

.timeline-step {
    display: flex;
    gap: var(--spacing-md);
    padding-bottom: var(--spacing-xl);
    position: relative;
}

.timeline-step:last-child {
    padding-bottom: 0;
}

.timeline-step::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 44px;
    bottom: 0;
    width: 2px;
    background: var(--gray-200);
}

.timeline-step:last-child::before {
    display: none;
}

.timeline-step.completed::before {
    background: var(--success);
}

.step-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-full);
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.step-icon .material-icons {
    font-size: 1.25rem;
    color: var(--gray-400);
}

.timeline-step.completed .step-icon {
    background: var(--success);
}

.timeline-step.completed .step-icon .material-icons {
    color: var(--white);
}

.timeline-step.current .step-icon {
    background: var(--primary);
    animation: pulse 2s infinite;
}

.timeline-step.current .step-icon .material-icons {
    color: var(--white);
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(85, 122, 70, 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(85, 122, 70, 0); }
}

.step-content h4 {
    font-size: var(--font-size-base);
    color: var(--gray-800);
    margin-bottom: 2px;
}

.timeline-step:not(.completed) .step-content h4 {
    color: var(--gray-400);
}

.step-content p {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
    margin: 0;
}

.step-time {
    font-size: var(--font-size-xs);
    color: var(--primary);
    font-weight: 500;
}

/* Delivery Info */
.delivery-info {
    display: flex;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: rgba(59, 130, 246, 0.1);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-xl);
}

.delivery-info .material-icons {
    color: var(--info);
    font-size: 1.5rem;
}

.delivery-info.success {
    background: rgba(16, 185, 129, 0.1);
}

.delivery-info.success .material-icons {
    color: var(--success);
}

.delivery-info strong {
    display: block;
    color: var(--gray-800);
    margin-bottom: 2px;
}

.delivery-info p {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    margin: 0;
}

/* Details Sections */
.tracking-details {
    margin-bottom: var(--spacing-xl);
}

.detail-section {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-sm);
    overflow: hidden;
}

.detail-section summary {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    cursor: pointer;
    font-weight: 600;
    color: var(--gray-700);
}

.detail-section summary .material-icons {
    color: var(--primary);
}

.detail-section summary::-webkit-details-marker {
    display: none;
}

.detail-section summary::after {
    content: 'expand_more';
    font-family: 'Material Icons';
    margin-left: auto;
    transition: transform 0.2s;
}

.detail-section[open] summary::after {
    transform: rotate(180deg);
}

/* Order Items in Tracking */
.items-list {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-100);
}

.order-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm) 0;
    border-bottom: 1px solid var(--gray-100);
}

.order-item:last-child {
    border-bottom: none;
}

.order-item img {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-sm);
    object-fit: cover;
}

.order-item .item-info {
    flex: 1;
}

.order-item h5 {
    font-size: var(--font-size-sm);
    color: var(--gray-800);
    margin: 0 0 2px;
}

.order-item .seller {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
}

.order-item .item-qty {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.order-item .item-price {
    font-weight: 600;
    color: var(--gray-800);
}

/* Address & Payment */
.address-info,
.payment-summary {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-100);
}

.address-info p {
    margin: 0;
    line-height: 1.6;
    color: var(--gray-600);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: var(--spacing-xs) 0;
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.summary-row.discount {
    color: var(--success);
}

.summary-row.total {
    font-size: var(--font-size-base);
    font-weight: 700;
    color: var(--gray-800);
    margin-top: var(--spacing-sm);
    padding-top: var(--spacing-sm);
    border-top: 1px solid var(--gray-200);
}

.payment-method {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--gray-100);
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.payment-method .material-icons {
    color: var(--gray-400);
}

.payment-method .badge {
    margin-left: auto;
}

/* Actions */
.tracking-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
}

/* Cancelled */
.cancelled-notice {
    text-align: center;
    padding: var(--spacing-2xl);
}

.cancelled-notice .material-icons {
    font-size: 4rem;
    color: var(--danger);
    margin-bottom: var(--spacing-md);
}

.cancelled-notice h2 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-sm);
}

.cancelled-notice p {
    color: var(--gray-500);
    margin-bottom: var(--spacing-lg);
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
