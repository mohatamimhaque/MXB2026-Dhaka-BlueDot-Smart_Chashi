<?php
/**
 * My Orders Page
 * Shows all orders for the logged-in customer.
 * Redirects guests to register.
 */

require_once __DIR__ . '/../config/config.php';

if (!isShopLoggedIn()) {
    shopRedirect('auth/register.php?redirect=' . urlencode(SHOP_URL . 'pages/my-orders.php'));
}

$db     = new ShopDatabase();
$userId = $_SESSION['shop_user_id'];

// Filter params
$statusFilter = sanitize($_GET['status'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 8;
$offset  = ($page - 1) * $perPage;

$allowed = ['pending','confirmed','processing','shipped','delivered','cancelled','returned'];
if (!in_array($statusFilter, $allowed)) $statusFilter = '';

// Build WHERE
$where  = "WHERE o.buyer_id = ?";
$params = [$userId];
if ($statusFilter) {
    $where .= " AND o.order_status = ?";
    $params[] = $statusFilter;
}

$totalOrders = $db->single("SELECT COUNT(*) as cnt FROM shop_orders o $where", $params)['cnt'] ?? 0;
$totalPages  = max(1, (int)ceil($totalOrders / $perPage));

$orders = $db->resultSet(
    "SELECT o.*,
            (SELECT COUNT(*) FROM shop_order_items WHERE order_id = o.order_id) as item_count,
            (SELECT oi2.product_name  FROM shop_order_items oi2 WHERE oi2.order_id = o.order_id LIMIT 1) as first_product,
            (SELECT oi2.product_image FROM shop_order_items oi2 WHERE oi2.order_id = o.order_id LIMIT 1) as first_image
     FROM shop_orders o
     $where
     ORDER BY o.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Status counts for tabs
$rawCounts = $db->resultSet("SELECT order_status, COUNT(*) as cnt FROM shop_orders WHERE buyer_id = ? GROUP BY order_status", [$userId]);
$statusCounts = ['all' => 0];
foreach ($rawCounts as $r) {
    $statusCounts[$r['order_status']] = (int)$r['cnt'];
    $statusCounts['all'] += (int)$r['cnt'];
}

$tabs = [
    ''           => ['label' => __('all_orders_tab'),   'icon' => 'receipt_long'],
    'pending'    => ['label' => __('pending'),           'icon' => 'hourglass_empty'],
    'confirmed'  => ['label' => __('confirmed_status'), 'icon' => 'check_circle'],
    'processing' => ['label' => __('processing'),       'icon' => 'inventory_2'],
    'shipped'    => ['label' => __('shipped_status'),   'icon' => 'local_shipping'],
    'delivered'  => ['label' => __('delivered_status'), 'icon' => 'where_to_vote'],
    'cancelled'  => ['label' => __('cancelled_status'), 'icon' => 'cancel'],
];

$pageTitle = 'My Orders';
include __DIR__ . '/../layouts/header.php';
?>

<div class="mo-page container">

    <!-- Page Header -->
    <div class="mo-header">
        <div>
            <h1><span class="material-icons">receipt_long</span> <?php echo __('my_orders'); ?></h1>
            <p><?php echo __('track_manage_purchases'); ?></p>
        </div>
        <a href="<?php echo shopUrl('pages/track-order.php'); ?>" class="btn btn-outline">
            <span class="material-icons">search</span> <?php echo __('track_by_number'); ?>
        </a>
    </div>

    <!-- Status Tabs -->
    <div class="mo-tabs">
        <?php foreach ($tabs as $val => $tab):
            $count = $val === '' ? ($statusCounts['all'] ?? 0) : ($statusCounts[$val] ?? 0);
            $isActive = $statusFilter === $val;
        ?>
        <a href="?<?php echo $val ? 'status=' . $val : ''; ?>"
           class="mo-tab <?php echo $isActive ? 'active' : ''; ?>">
            <span class="material-icons"><?php echo $tab['icon']; ?></span>
            <span><?php echo $tab['label']; ?></span>
            <?php if ($count > 0): ?>
                <span class="mo-tab-count"><?php echo $count; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders List -->
    <?php if (empty($orders)): ?>
    <div class="mo-empty">
        <span class="material-icons">shopping_bag</span>
        <h3><?php echo $statusFilter ? __('no_orders_yet_msg') . ' (' . ucfirst($statusFilter) . ')' : __('no_orders_yet_msg'); ?></h3>
        <p><?php echo __('havent_added_yet'); ?></p>
        <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary">
            <span class="material-icons">storefront</span> <?php echo __('shop_now'); ?>
        </a>
    </div>
    <?php else: ?>

    <div class="mo-list">
        <?php foreach ($orders as $order):
            $statusInfo = [
                'pending'    => ['color' => 'warning', 'icon' => 'hourglass_empty'],
                'confirmed'  => ['color' => 'info',    'icon' => 'check_circle'],
                'processing' => ['color' => 'info',    'icon' => 'inventory_2'],
                'shipped'    => ['color' => 'primary',  'icon' => 'local_shipping'],
                'delivered'  => ['color' => 'success', 'icon' => 'where_to_vote'],
                'cancelled'  => ['color' => 'danger',  'icon' => 'cancel'],
                'returned'   => ['color' => 'secondary','icon' => 'keyboard_return'],
            ];
            $si = $statusInfo[$order['order_status']] ?? ['color' => 'secondary', 'icon' => 'help'];
        ?>
        <div class="mo-card" id="order-<?php echo $order['order_id']; ?>">
            <div class="mo-card-header">
                <div class="mo-order-meta">
                    <span class="mo-order-num">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                    <span class="mo-order-date">
                        <span class="material-icons">calendar_today</span>
                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                    </span>
                    <span class="mo-order-items">
                        <span class="material-icons">inventory_2</span>
                        <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?>
                    </span>
                </div>
                <div class="mo-order-status">
                    <span class="badge badge-<?php echo $si['color']; ?>">
                        <span class="material-icons"><?php echo $si['icon']; ?></span>
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </div>
            </div>

            <div class="mo-card-body">
                <div class="mo-product-preview">
                    <img src="<?php echo getProductImage($order['first_image'] ?? ''); ?>" alt="">
                    <div class="mo-product-info">
                        <h4><?php echo htmlspecialchars($order['first_product'] ?? 'Product'); ?></h4>
                        <?php if ($order['item_count'] > 1): ?>
                            <span class="mo-more-items">+<?php echo $order['item_count'] - 1; ?> more item<?php echo $order['item_count'] > 2 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <div class="mo-payment-info">
                            <span class="material-icons">credit_card</span>
                            <?php
                            $methods = ['cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank Transfer'];
                            echo $methods[$order['payment_method']] ?? ucfirst($order['payment_method']);
                            ?>
                            &nbsp;•&nbsp;
                            <span class="badge badge-<?php echo getPaymentStatusBadge($order['payment_status']); ?> badge-sm">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mo-total">
                        <span class="mo-total-label"><?php echo __('total_label'); ?></span>
                        <strong class="mo-total-amount"><?php echo formatPrice($order['total_amount']); ?></strong>
                    </div>
                </div>
            </div>

            <div class="mo-card-footer">
                <div class="mo-actions">
                    <a href="<?php echo shopUrl('pages/track-order.php?id=' . $order['order_id']); ?>"
                       class="btn btn-outline btn-sm">
                        <span class="material-icons">local_shipping</span> Track
                    </a>
                    <a href="<?php echo shopUrl('messages/?order=' . $order['order_id']); ?>"
                       class="btn btn-outline btn-sm">
                        <span class="material-icons">chat</span> <?php echo __('contact_seller_action'); ?>
                    </a>
                    <?php if ($order['order_status'] === 'pending'): ?>
                    <button class="btn btn-danger btn-sm"
                            onclick="cancelOrder(<?php echo $order['order_id']; ?>, this)">
                        <span class="material-icons">cancel</span> Cancel
                    </button>
                    <?php endif; ?>
                    <?php if ($order['order_status'] === 'delivered'): ?>
                    <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-sm">
                        <span class="material-icons">replay</span> <?php echo __('buy_again_btn'); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <span class="mo-updated">Updated <?php echo timeAgo($order['updated_at']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?<?php echo $statusFilter ? 'status=' . $statusFilter . '&' : ''; ?>page=<?php echo $i; ?>"
           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.mo-page { padding: var(--spacing-xl) var(--spacing-md); max-width: 900px; margin: 0 auto; }

.mo-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: var(--spacing-xl); flex-wrap: wrap; gap: var(--spacing-md);
}
.mo-header h1 {
    display: flex; align-items: center; gap: var(--spacing-sm);
    font-size: var(--font-size-xl); color: var(--gray-800); margin: 0;
}
.mo-header h1 .material-icons { color: var(--primary); }
.mo-header p { color: var(--gray-500); margin: 4px 0 0; font-size: var(--font-size-sm); }

/* Tabs */
.mo-tabs {
    display: flex; gap: 4px; margin-bottom: var(--spacing-xl);
    background: var(--white); border-radius: var(--radius-xl);
    padding: 6px; box-shadow: var(--shadow-sm);
    overflow-x: auto; -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.mo-tabs::-webkit-scrollbar { display: none; }
.mo-tab {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: var(--radius-lg);
    color: var(--gray-600); font-size: var(--font-size-sm); font-weight: 500;
    text-decoration: none; white-space: nowrap; transition: all 0.2s;
}
.mo-tab .material-icons { font-size: 1rem; }
.mo-tab:hover { background: var(--gray-100); color: var(--gray-800); }
.mo-tab.active { background: var(--primary); color: var(--white); }
.mo-tab-count {
    background: rgba(255,255,255,0.25); color: inherit;
    font-size: 11px; font-weight: 700; padding: 2px 7px;
    border-radius: 999px; min-width: 20px; text-align: center;
}
.mo-tab.active .mo-tab-count { background: rgba(255,255,255,0.3); }
.mo-tab:not(.active) .mo-tab-count { background: var(--gray-200); color: var(--gray-700); }

/* Order Cards */
.mo-list { display: flex; flex-direction: column; gap: var(--spacing-md); }
.mo-card {
    background: var(--white); border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);
    overflow: hidden; transition: box-shadow 0.2s;
}
.mo-card:hover { box-shadow: var(--shadow-md); }

.mo-card-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    background: var(--gray-50); border-bottom: 1px solid var(--gray-100);
    flex-wrap: wrap; gap: var(--spacing-sm);
}
.mo-order-meta { display: flex; align-items: center; gap: var(--spacing-md); flex-wrap: wrap; }
.mo-order-num { font-weight: 700; color: var(--gray-800); font-size: var(--font-size-sm); }
.mo-order-date, .mo-order-items {
    display: flex; align-items: center; gap: 4px;
    color: var(--gray-500); font-size: var(--font-size-xs);
}
.mo-order-date .material-icons, .mo-order-items .material-icons { font-size: 14px; }

.mo-card-body { padding: var(--spacing-lg); }
.mo-product-preview { display: flex; align-items: center; gap: var(--spacing-md); }
.mo-product-preview img {
    width: 64px; height: 64px; border-radius: var(--radius-md); object-fit: cover;
    border: 1px solid var(--gray-200); flex-shrink: 0;
}
.mo-product-info { flex: 1; min-width: 0; }
.mo-product-info h4 {
    font-size: var(--font-size-base); color: var(--gray-800); margin: 0 0 4px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mo-more-items { font-size: var(--font-size-xs); color: var(--gray-500); }
.mo-payment-info {
    display: flex; align-items: center; gap: 4px; margin-top: 6px;
    font-size: var(--font-size-xs); color: var(--gray-500);
}
.mo-payment-info .material-icons { font-size: 14px; }
.mo-total { text-align: right; flex-shrink: 0; }
.mo-total-label { display: block; font-size: var(--font-size-xs); color: var(--gray-500); margin-bottom: 2px; }
.mo-total-amount { font-size: var(--font-size-lg); font-weight: 700; color: var(--primary); }

.mo-card-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    border-top: 1px solid var(--gray-100); flex-wrap: wrap; gap: var(--spacing-sm);
}
.mo-actions { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
.mo-updated { font-size: var(--font-size-xs); color: var(--gray-400); }

/* Badge sizes */
.badge-sm { font-size: 10px; padding: 2px 8px; }

/* Empty */
.mo-empty {
    text-align: center; padding: var(--spacing-3xl) var(--spacing-xl);
    background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);
}
.mo-empty .material-icons { font-size: 4rem; color: var(--gray-300); margin-bottom: var(--spacing-md); }
.mo-empty h3 { color: var(--gray-600); margin-bottom: var(--spacing-sm); }
.mo-empty p { color: var(--gray-400); margin-bottom: var(--spacing-xl); }

/* Pagination */
.pagination { display: flex; justify-content: center; gap: var(--spacing-sm); margin-top: var(--spacing-xl); }
.page-btn {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: var(--radius-md);
    border: 1px solid var(--gray-200); color: var(--gray-600);
    text-decoration: none; font-size: var(--font-size-sm); transition: all 0.2s;
}
.page-btn:hover { background: var(--gray-100); }
.page-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }

@media (max-width: 640px) {
    .mo-product-preview { flex-wrap: wrap; }
    .mo-total { width: 100%; text-align: left; display: flex; align-items: center; gap: var(--spacing-sm); }
    .mo-total-label::after { content: ':'; }
    .mo-card-footer { flex-direction: column; align-items: flex-start; }
}
</style>

<script>
function cancelOrder(orderId, btn) {
    if (!confirm('<?php echo addslashes(__('cancel_order_confirm')); ?>')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons" style="animation:spin 1s linear infinite">sync</span>';

    fetch('<?php echo shopUrl('ajax/orders.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'cancel', order_id: orderId})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('<?php echo addslashes(__('order_cancelled_success')); ?>', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(res.message || 'Cannot cancel this order', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons">cancel</span> Cancel';
        }
    })
    .catch(() => { showToast('Network error', 'error'); btn.disabled = false; });
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
