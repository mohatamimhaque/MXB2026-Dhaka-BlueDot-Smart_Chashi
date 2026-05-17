<?php
/**
 * Shop User Profile Dashboard
 */

require_once __DIR__ . '/../config/config.php';

requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

// Get order stats
$stats = [
    'total_orders' => $db->single("SELECT COUNT(*) as count FROM shop_orders WHERE buyer_id = ?", [$user['user_id']])['count'] ?? 0,
    'pending_orders' => $db->single("SELECT COUNT(*) as count FROM shop_orders WHERE buyer_id = ? AND order_status IN ('pending', 'confirmed', 'processing')", [$user['user_id']])['count'] ?? 0,
    'delivered_orders' => $db->single("SELECT COUNT(*) as count FROM shop_orders WHERE buyer_id = ? AND order_status = 'delivered'", [$user['user_id']])['count'] ?? 0,
    'total_spent' => $db->single("SELECT SUM(total_amount) as total FROM shop_orders WHERE buyer_id = ? AND order_status != 'cancelled'", [$user['user_id']])['total'] ?? 0
];

// Get recent orders
$recentOrders = $db->resultSet(
    "SELECT * FROM shop_orders WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['user_id']]
);

$pageTitle = 'My Profile';
include __DIR__ . '/../layouts/header.php';
?>

<div class="profile-page container">
    <div class="profile-header">
        <div class="profile-avatar">
            <img src="<?php echo getUserAvatar($user['profile_img_url']); ?>" alt="Profile">
        </div>
        <div class="profile-info">
            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? '')); ?></h1>
            <p class="email"><?php echo htmlspecialchars($user['email']); ?></p>
            <p class="member-since">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
        </div>
        <div class="profile-actions">
            <a href="<?php echo shopUrl('profile/settings.php'); ?>" class="btn btn-outline">
                <span class="material-icons">settings</span>
                Edit Profile
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon material-icons">receipt_long</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total_orders']; ?></span>
                <span class="stat-label">Total Orders</span>
            </div>
        </div>
        <div class="stat-card pending">
            <span class="stat-icon material-icons">pending_actions</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['pending_orders']; ?></span>
                <span class="stat-label">In Progress</span>
            </div>
        </div>
        <div class="stat-card success">
            <span class="stat-icon material-icons">check_circle</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['delivered_orders']; ?></span>
                <span class="stat-label">Delivered</span>
            </div>
        </div>
        <div class="stat-card accent">
            <span class="stat-icon material-icons">payments</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo formatPrice($stats['total_spent']); ?></span>
                <span class="stat-label">Total Spent</span>
            </div>
        </div>
    </div>

    <div class="profile-layout">
        <!-- Quick Links -->
        <div class="profile-menu">
            <h3>Quick Links</h3>
            <nav>
                <a href="<?php echo shopUrl('profile/orders.php'); ?>">
                    <span class="material-icons">list_alt</span>
                    My Orders
                    <?php if ($stats['pending_orders'] > 0): ?>
                    <span class="badge badge-warning"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo shopUrl('profile/settings.php'); ?>">
                    <span class="material-icons">manage_accounts</span>
                    Account Settings
                </a>
                <a href="<?php echo shopUrl('pages/products.php'); ?>">
                    <span class="material-icons">storefront</span>
                    Browse Products
                </a>
                <a href="<?php echo shopUrl('auth/logout.php'); ?>" class="logout">
                    <span class="material-icons">logout</span>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Recent Orders -->
        <div class="recent-orders">
            <div class="section-header">
                <h3>Recent Orders</h3>
                <a href="<?php echo shopUrl('profile/orders.php'); ?>">View All</a>
            </div>
            
            <?php if (empty($recentOrders)): ?>
            <div class="empty-state-small">
                <span class="material-icons">receipt_long</span>
                <p>No orders yet</p>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-sm">Start Shopping</a>
            </div>
            <?php else: ?>
            <div class="orders-list">
                <?php foreach ($recentOrders as $order): ?>
                <a href="<?php echo shopUrl('profile/order-detail.php?id=' . $order['order_id']); ?>" class="order-item">
                    <div class="order-info">
                        <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                        <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="order-status">
                        <span class="badge <?php echo getOrderStatusBadge($order['order_status']); ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </div>
                    <span class="order-total"><?php echo formatPrice($order['total_amount']); ?></span>
                    <span class="material-icons arrow">chevron_right</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.profile-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.profile-header {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    align-items: center;
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--spacing-xl);
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: var(--radius-full);
    overflow: hidden;
    border: 4px solid var(--primary);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-info {
    flex: 1;
}

.profile-info h1 {
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.profile-info .email {
    color: var(--gray-600);
    margin-bottom: var(--spacing-xs);
}

.profile-info .member-since {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.stat-card {
    background: var(--white);
    padding: var(--spacing-lg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(85, 122, 70, 0.1);
    color: var(--primary);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-card.pending .stat-icon {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.stat-card.success .stat-icon {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.stat-card.accent .stat-icon {
    background: rgba(255, 140, 0, 0.1);
    color: var(--accent);
}

.stat-value {
    display: block;
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--gray-800);
}

.stat-label {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.profile-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .profile-layout {
        grid-template-columns: 280px 1fr;
    }
}

.profile-menu {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
    height: fit-content;
}

.profile-menu h3 {
    font-size: var(--font-size-sm);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-500);
    margin-bottom: var(--spacing-md);
}

.profile-menu nav a {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    color: var(--gray-700);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-xs);
    transition: all var(--transition-fast);
}

.profile-menu nav a:hover {
    background: var(--gray-100);
}

.profile-menu nav a .material-icons {
    color: var(--gray-500);
}

.profile-menu nav a.logout {
    color: var(--danger);
    margin-top: var(--spacing-md);
    border-top: 1px solid var(--gray-200);
    padding-top: var(--spacing-lg);
}

.profile-menu nav a.logout .material-icons {
    color: var(--danger);
}

.recent-orders {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.section-header h3 {
    font-size: var(--font-size-lg);
    color: var(--gray-800);
}

.section-header a {
    font-size: var(--font-size-sm);
    color: var(--primary);
}

.empty-state-small {
    text-align: center;
    padding: var(--spacing-xl);
    color: var(--gray-500);
}

.empty-state-small .material-icons {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: var(--spacing-sm);
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.order-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--gray-50);
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
}

.order-item:hover {
    background: var(--gray-100);
}

.order-info {
    flex: 1;
}

.order-number {
    display: block;
    font-weight: 600;
    color: var(--gray-800);
    font-size: var(--font-size-sm);
}

.order-date {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
}

.order-total {
    font-weight: 600;
    color: var(--primary);
}

.order-item .arrow {
    color: var(--gray-400);
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
