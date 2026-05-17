<?php
/**
 * Farmer Orders Management Page
 * Unified view for both marketplace and shop orders
 */

require_once __DIR__ . '/../config/config.php';

// Require farmer login
if (!isLoggedIn() || getCurrentUser()['role'] !== 'farmer') {
    header('Location: ' . $base_url . '?page=login');
    exit;
}

$db = new Database();
$farmerId = $_SESSION['user_id'];

// Get filter parameters
$source = $_GET['source'] ?? 'all'; // all, marketplace, shop
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build queries for both sources
$marketplaceOrders = [];
$shopOrders = [];
$allOrders = [];

// Marketplace orders (where farmer is seller)
if ($source === 'all' || $source === 'marketplace') {
    $mpQuery = "SELECT 
        mo.order_id, mo.order_number, mo.total_amount, mo.order_status, mo.payment_status,
        mo.created_at, mo.updated_at, mo.notes,
        u.first_name as buyer_name, u.phone as buyer_phone, u.email as buyer_email,
        'marketplace' as source
    FROM marketplace_orders mo
    LEFT JOIN users u ON mo.buyer_id = u.user_id
    WHERE mo.seller_id = ?";
    
    $mpParams = [$farmerId];
    
    if ($status) {
        $mpQuery .= " AND mo.order_status = ?";
        $mpParams[] = $status;
    }
    
    $db->query($mpQuery . " ORDER BY mo.created_at DESC");
    foreach ($mpParams as $i => $p) {
        $db->bind($i + 1, $p);
    }
    $marketplaceOrders = $db->fetchAll();
}

// Shop orders (items sold by this farmer)
if ($source === 'all' || $source === 'shop') {
    $shopQuery = "SELECT DISTINCT
        so.order_id, so.order_number, so.total_amount, so.order_status, so.payment_status,
        so.shipping_name as buyer_name, so.shipping_phone as buyer_phone,
        so.shipping_address, so.shipping_district,
        so.created_at, so.updated_at, so.notes,
        'shop' as source,
        (SELECT SUM(soi.total_price) FROM shop_order_items soi WHERE soi.order_id = so.order_id AND soi.seller_id = ?) as my_items_total
    FROM shop_orders so
    INNER JOIN shop_order_items soi ON so.order_id = soi.order_id AND soi.seller_id = ?
    WHERE 1=1";
    
    $shopParams = [$farmerId, $farmerId];
    
    if ($status) {
        $shopQuery .= " AND so.order_status = ?";
        $shopParams[] = $status;
    }
    
    $db->query($shopQuery . " ORDER BY so.created_at DESC");
    foreach ($shopParams as $i => $p) {
        $db->bind($i + 1, $p);
    }
    $shopOrders = $db->fetchAll();
}

// Merge and sort all orders by date
$allOrders = array_merge($marketplaceOrders, $shopOrders);
usort($allOrders, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Paginate
$totalOrders = count($allOrders);
$totalPages = ceil($totalOrders / $perPage);
$orders = array_slice($allOrders, $offset, $perPage);

// Get statistics
$mpPending = $db->query("SELECT COUNT(*) as c FROM marketplace_orders WHERE seller_id = ? AND order_status = 'pending'")->bind(1, $farmerId)->fetch()['c'] ?? 0;
$shopPending = $db->query("SELECT COUNT(DISTINCT so.order_id) as c FROM shop_orders so INNER JOIN shop_order_items soi ON so.order_id = soi.order_id AND soi.seller_id = ? WHERE so.order_status = 'pending'")->bind(1, $farmerId)->fetch()['c'] ?? 0;

$mpDelivered = $db->query("SELECT COUNT(*) as c FROM marketplace_orders WHERE seller_id = ? AND order_status = 'delivered'")->bind(1, $farmerId)->fetch()['c'] ?? 0;
$shopDelivered = $db->query("SELECT COUNT(DISTINCT so.order_id) as c FROM shop_orders so INNER JOIN shop_order_items soi ON so.order_id = soi.order_id AND soi.seller_id = ? WHERE so.order_status = 'delivered'")->bind(1, $farmerId)->fetch()['c'] ?? 0;

$totalRevenue = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM marketplace_orders WHERE seller_id = ? AND order_status = 'delivered'")->bind(1, $farmerId)->fetch()['total'] ?? 0;

$stats = [
    'pending' => $mpPending + $shopPending,
    'delivered' => $mpDelivered + $shopDelivered,
    'total' => $totalOrders,
    'revenue' => $totalRevenue
];

$pageTitle = __('my_orders') ?: 'My Orders';
include __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>public/css/farmer-orders.css">

<div class="farmer-orders-page">
    <div class="page-header-section">
        <h1><span class="material-icons">receipt_long</span> <?php echo __('my_orders') ?: 'My Orders'; ?></h1>
        <p><?php echo __('manage_orders_desc') ?: 'Manage orders from marketplace and shop customers'; ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="order-stats-grid">
        <div class="stat-card pending">
            <span class="stat-icon material-icons">pending_actions</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['pending']; ?></span>
                <span class="stat-label"><?php echo __('pending') ?: 'Pending'; ?></span>
            </div>
        </div>
        <div class="stat-card delivered">
            <span class="stat-icon material-icons">check_circle</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['delivered']; ?></span>
                <span class="stat-label"><?php echo __('delivered') ?: 'Delivered'; ?></span>
            </div>
        </div>
        <div class="stat-card total">
            <span class="stat-icon material-icons">shopping_bag</span>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total']; ?></span>
                <span class="stat-label"><?php echo __('total_orders') ?: 'Total Orders'; ?></span>
            </div>
        </div>
        <div class="stat-card revenue">
            <span class="stat-icon material-icons">payments</span>
            <div class="stat-content">
                <span class="stat-value">৳<?php echo number_format($stats['revenue']); ?></span>
                <span class="stat-label"><?php echo __('revenue') ?: 'Revenue'; ?></span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="orders-filters">
        <div class="source-tabs">
            <a href="?page=farmer-orders&source=all" class="tab <?php echo $source === 'all' ? 'active' : ''; ?>">
                All Orders
            </a>
            <a href="?page=farmer-orders&source=marketplace" class="tab <?php echo $source === 'marketplace' ? 'active' : ''; ?>">
                <span class="material-icons">store</span> Marketplace
            </a>
            <a href="?page=farmer-orders&source=shop" class="tab <?php echo $source === 'shop' ? 'active' : ''; ?>">
                <span class="material-icons">shopping_cart</span> Shop
            </a>
        </div>
        
        <div class="status-filter">
            <select onchange="filterByStatus(this.value)">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>

    <!-- Orders Table -->
    <?php if (empty($orders)): ?>
    <div class="empty-orders">
        <span class="material-icons">inbox</span>
        <h3><?php echo __('no_orders') ?: 'No orders found'; ?></h3>
        <p><?php echo __('no_orders_desc') ?: 'Orders from your products will appear here.'; ?></p>
    </div>
    <?php else: ?>
    <div class="orders-table-wrapper">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Source</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr data-order-id="<?php echo $order['order_id']; ?>" data-source="<?php echo $order['source']; ?>">
                    <td>
                        <span class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                    </td>
                    <td>
                        <div class="customer-info">
                            <span class="customer-name"><?php echo htmlspecialchars($order['buyer_name']); ?></span>
                            <span class="customer-phone"><?php echo htmlspecialchars($order['buyer_phone'] ?? ''); ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="source-badge <?php echo $order['source']; ?>">
                            <span class="material-icons"><?php echo $order['source'] === 'marketplace' ? 'store' : 'shopping_cart'; ?></span>
                            <?php echo ucfirst($order['source']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="amount">৳<?php echo number_format($order['my_items_total'] ?? $order['total_amount']); ?></span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $order['order_status']; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                        <span class="order-time"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon" onclick="viewOrder(<?php echo $order['order_id']; ?>, '<?php echo $order['source']; ?>')" title="View">
                                <span class="material-icons">visibility</span>
                            </button>
                            <?php if (!in_array($order['order_status'], ['delivered', 'cancelled'])): ?>
                            <button class="btn-icon" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, '<?php echo $order['source']; ?>')" title="Update Status">
                                <span class="material-icons">update</span>
                            </button>
                            <?php endif; ?>
                            <button class="btn-icon" onclick="messageCustomer(<?php echo $order['order_id']; ?>, '<?php echo $order['source']; ?>')" title="Message">
                                <span class="material-icons">chat</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=farmer-orders&source=<?php echo $source; ?>&status=<?php echo $status; ?>&page=<?php echo $page - 1; ?>" class="page-link">
            <span class="material-icons">chevron_left</span>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?page=farmer-orders&source=<?php echo $source; ?>&status=<?php echo $status; ?>&page=<?php echo $i; ?>" 
           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=farmer-orders&source=<?php echo $source; ?>&status=<?php echo $status; ?>&page=<?php echo $page + 1; ?>" class="page-link">
            <span class="material-icons">chevron_right</span>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Order Detail Modal -->
<div class="modal" id="orderModal">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <button class="modal-close" onclick="closeModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body" id="orderModalBody">
            <div class="loading-spinner"></div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal" id="statusModal">
    <div class="modal-backdrop" onclick="closeStatusModal()"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3>Update Order Status</h3>
            <button class="modal-close" onclick="closeStatusModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="statusOrderId">
            <input type="hidden" id="statusSource">
            <div class="form-group">
                <label>New Status</label>
                <select id="newStatus" class="form-control">
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <button class="btn btn-primary btn-block" onclick="submitStatusUpdate()">
                <span class="material-icons">check</span> Update Status
            </button>
        </div>
    </div>
</div>

<script>
function filterByStatus(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function viewOrder(orderId, source) {
    const modal = document.getElementById('orderModal');
    const body = document.getElementById('orderModalBody');
    modal.classList.add('show');
    body.innerHTML = '<div class="loading-spinner"></div>';
    
    fetch(`${window.BASE_URL}ajax/farmer-orders.php?action=get_order&order_id=${orderId}&source=${source}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                body.innerHTML = data.html;
            } else {
                body.innerHTML = '<p class="error">Failed to load order details</p>';
            }
        })
        .catch(() => {
            body.innerHTML = '<p class="error">Failed to load order details</p>';
        });
}

function closeModal() {
    document.getElementById('orderModal').classList.remove('show');
}

function updateOrderStatus(orderId, source) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('statusSource').value = source;
    document.getElementById('statusModal').classList.add('show');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('show');
}

function submitStatusUpdate() {
    const orderId = document.getElementById('statusOrderId').value;
    const source = document.getElementById('statusSource').value;
    const newStatus = document.getElementById('newStatus').value;
    
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', orderId);
    formData.append('source', source);
    formData.append('status', newStatus);
    
    fetch(`${window.BASE_URL}ajax/farmer-orders.php`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Order status updated!', 'success');
            closeStatusModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Update failed', 'error');
        }
    })
    .catch(() => {
        showNotification('Failed to update status', 'error');
    });
}

function messageCustomer(orderId, source) {
    window.location.href = `${window.BASE_URL}?page=farmer-messages&order=${orderId}&source=${source}`;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
