<?php
/**
 * Shop User Orders List
 */

require_once __DIR__ . '/../config/config.php';

requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

// Filters
$status = sanitize($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$baseQuery = "FROM shop_orders WHERE buyer_id = ?";
$params = [$user['user_id']];

if (!empty($status)) {
    $baseQuery .= " AND order_status = ?";
    $params[] = $status;
}

// Count total
$total = $db->single("SELECT COUNT(*) as count $baseQuery", $params)['count'] ?? 0;
$totalPages = ceil($total / $perPage);

// Get orders
$orders = $db->resultSet(
    "SELECT * $baseQuery ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Get item counts for each order
$orderIds = array_column($orders, 'order_id');
$itemCounts = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $counts = $db->resultSet(
        "SELECT order_id, COUNT(*) as count FROM shop_order_items WHERE order_id IN ($placeholders) GROUP BY order_id",
        $orderIds
    );
    foreach ($counts as $c) {
        $itemCounts[$c['order_id']] = $c['count'];
    }
}

$pageTitle = 'My Orders';
include __DIR__ . '/../layouts/header.php';
?>

<div class="orders-page container">
    <div class="page-header">
        <h1><span class="material-icons">receipt_long</span> My Orders</h1>
        <p><?php echo $total; ?> order(s)</p>
    </div>

    <!-- Filters -->
    <div class="order-filters">
        <a href="<?php echo shopUrl('profile/orders.php'); ?>" 
           class="filter-chip <?php echo empty($status) ? 'active' : ''; ?>">All</a>
        <a href="<?php echo shopUrl('profile/orders.php?status=pending'); ?>" 
           class="filter-chip <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="<?php echo shopUrl('profile/orders.php?status=confirmed'); ?>" 
           class="filter-chip <?php echo $status === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
        <a href="<?php echo shopUrl('profile/orders.php?status=shipped'); ?>" 
           class="filter-chip <?php echo $status === 'shipped' ? 'active' : ''; ?>">Shipped</a>
        <a href="<?php echo shopUrl('profile/orders.php?status=delivered'); ?>" 
           class="filter-chip <?php echo $status === 'delivered' ? 'active' : ''; ?>">Delivered</a>
        <a href="<?php echo shopUrl('profile/orders.php?status=cancelled'); ?>" 
           class="filter-chip <?php echo $status === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    </div>

    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <span class="material-icons">receipt_long</span>
        <h3>No orders found</h3>
        <p><?php echo $status ? "You don't have any $status orders." : "You haven't placed any orders yet."; ?></p>
        <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary">Start Shopping</a>
    </div>
    <?php else: ?>
    <div class="orders-list">
        <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div class="order-meta">
                    <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                    <span class="order-date"><?php echo date('M j, Y, g:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="order-badges">
                    <span class="badge <?php echo getOrderStatusBadge($order['order_status']); ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                    <span class="badge <?php echo getPaymentStatusBadge($order['payment_status']); ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="order-body">
                <div class="order-details">
                    <div class="detail">
                        <span class="label">Items</span>
                        <span class="value"><?php echo $itemCounts[$order['order_id']] ?? 0; ?> product(s)</span>
                    </div>
                    <div class="detail">
                        <span class="label">Payment</span>
                        <span class="value"><?php echo strtoupper($order['payment_method']); ?></span>
                    </div>
                    <div class="detail">
                        <span class="label">Shipping</span>
                        <span class="value"><?php echo htmlspecialchars($order['shipping_district']); ?></span>
                    </div>
                </div>
                
                <div class="order-total">
                    <span class="label">Total</span>
                    <span class="amount"><?php echo formatPrice($order['total_amount']); ?></span>
                </div>
            </div>
            
            <div class="order-footer">
                <a href="<?php echo shopUrl('profile/order-detail.php?id=' . $order['order_id']); ?>" class="btn btn-outline btn-sm">
                    <span class="material-icons">visibility</span>
                    View Details
                </a>
                <?php if ($order['order_status'] === 'pending'): ?>
                <button class="btn btn-ghost btn-sm" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                    <span class="material-icons">cancel</span>
                    Cancel
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                <span class="material-icons">chevron_left</span>
            </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                <span class="material-icons">chevron_right</span>
            </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.orders-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.page-header {
    margin-bottom: var(--spacing-lg);
}

.page-header h1 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.page-header p {
    color: var(--gray-500);
}

.order-filters {
    display: flex;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-xl);
    overflow-x: auto;
    padding-bottom: var(--spacing-sm);
}

.filter-chip {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    white-space: nowrap;
    transition: all var(--transition-fast);
}

.filter-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-chip.active {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--white);
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.order-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: var(--spacing-md);
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-100);
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.order-number {
    display: block;
    font-weight: 600;
    color: var(--gray-800);
}

.order-date {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.order-badges {
    display: flex;
    gap: var(--spacing-xs);
}

.order-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.order-details {
    display: flex;
    gap: var(--spacing-xl);
    flex-wrap: wrap;
}

.detail {
    display: flex;
    flex-direction: column;
}

.detail .label {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
    text-transform: uppercase;
}

.detail .value {
    font-weight: 500;
    color: var(--gray-700);
}

.order-total {
    text-align: right;
}

.order-total .label {
    display: block;
    font-size: var(--font-size-xs);
    color: var(--gray-500);
}

.order-total .amount {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--primary);
}

.order-footer {
    display: flex;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--gray-50);
    border-top: 1px solid var(--gray-100);
}
</style>

<script>
async function cancelOrder(orderId) {
    if (!await confirmAction('Are you sure you want to cancel this order?')) return;
    
    const result = await ajaxRequest('<?php echo shopUrl('ajax/orders.php'); ?>', {
        body: { action: 'cancel', order_id: orderId }
    });
    
    if (result.success) {
        showToast('Order cancelled', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(result.message, 'error');
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
