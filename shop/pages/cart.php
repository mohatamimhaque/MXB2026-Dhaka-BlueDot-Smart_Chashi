<?php
/**
 * Shop Cart Page
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();

// Get cart items
$userId = isShopLoggedIn() ? $_SESSION['shop_user_id'] : null;
$sessionId = $userId ? null : session_id();

if ($userId) {
    $cartItems = $db->resultSet(
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
    $cartItems = $db->resultSet(
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

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$pageTitle = 'Shopping Cart';
include __DIR__ . '/../layouts/header.php';
?>

<div class="cart-page container">
    <div class="page-header">
        <h1><span class="material-icons">shopping_cart</span> <?php echo __('shopping_cart_title'); ?></h1>
        <p><?php echo count($cartItems); ?> <?php echo __('items_in_cart'); ?></p>
    </div>

    <?php if (empty($cartItems)): ?>
    <div class="empty-state">
        <span class="material-icons">remove_shopping_cart</span>
        <h3><?php echo __('cart_is_empty'); ?></h3>
        <p><?php echo __('havent_added_yet'); ?></p>
        <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-lg">
            <span class="material-icons">storefront</span>
            <?php echo __('start_shopping'); ?>
        </a>
    </div>
    <?php else: ?>
    <div class="cart-layout">
        <!-- Cart Items -->
        <div class="cart-items">
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item" id="cart-item-<?php echo $item['cart_id']; ?>">
                <div class="cart-item-image">
                    <a href="<?php echo shopUrl('product/' . $item['product_id']); ?>">
                        <img src="<?php echo getProductImage($item['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                    </a>
                </div>
                <div class="cart-item-details">
                    <h3>
                        <a href="<?php echo shopUrl('product/' . $item['product_id']); ?>">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </a>
                    </h3>
                    <div class="cart-item-meta">
                        <span class="seller">
                            <span class="material-icons">person</span>
                            <?php echo htmlspecialchars($item['seller_name']); ?>
                        </span>
                        <span class="unit-price">
                            <?php echo formatPrice($item['price']); ?> / <?php echo htmlspecialchars($item['price_unit'] ?? 'kg'); ?>
                        </span>
                    </div>
                </div>
                <div class="cart-item-quantity">
                    <div class="quantity-control">
                        <button type="button" onclick="updateCartItem(<?php echo $item['cart_id']; ?>, -1)">
                            <span class="material-icons">remove</span>
                        </button>
                        <input type="number" value="<?php echo $item['quantity']; ?>" 
                               id="qty-<?php echo $item['cart_id']; ?>"
                               min="1" max="<?php echo $item['quantity_available'] ?? 999; ?>"
                               onchange="setCartQuantity(<?php echo $item['cart_id']; ?>, this.value)">
                        <button type="button" onclick="updateCartItem(<?php echo $item['cart_id']; ?>, 1)">
                            <span class="material-icons">add</span>
                        </button>
                    </div>
                </div>
                <div class="cart-item-total">
                    <span class="total-price" id="total-<?php echo $item['cart_id']; ?>">
                        <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                    </span>
                </div>
                <button class="cart-item-remove" onclick="removeCartItem(<?php echo $item['cart_id']; ?>)">
                    <span class="material-icons">delete</span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Cart Summary -->
        <div class="cart-summary">
            <div class="summary-card">
                <h3><?php echo __('order_summary_title'); ?></h3>

                <div class="summary-row">
                    <span><?php echo __('subtotal'); ?></span>
                    <span id="cart-subtotal"><?php echo formatPrice($subtotal); ?></span>
                </div>

                <div class="summary-row">
                    <span><?php echo __('shipping_label'); ?></span>
                    <span class="text-success"><?php echo __('calculated_at_checkout'); ?></span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row total">
                    <span><?php echo __('estimated_total'); ?></span>
                    <span id="cart-total"><?php echo formatPrice($subtotal); ?></span>
                </div>

                <?php if (isShopLoggedIn()): ?>
                <a href="<?php echo shopUrl('pages/checkout.php'); ?>" class="btn btn-primary btn-lg btn-block">
                    <span class="material-icons">payment</span>
                    <?php echo __('proceed_to_checkout'); ?>
                </a>
                <?php else: ?>
                <a href="<?php echo shopUrl('auth/login.php'); ?>" class="btn btn-primary btn-lg btn-block">
                    <span class="material-icons">login</span>
                    <?php echo __('login_to_checkout'); ?>
                </a>
                <p class="login-note">
                    <?php echo __('dont_have_account_msg'); ?>
                    <a href="<?php echo shopUrl('auth/register.php'); ?>"><?php echo __('register_now_link'); ?></a>
                </p>
                <?php endif; ?>

                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-ghost btn-block">
                    <span class="material-icons">arrow_back</span>
                    <?php echo __('continue_shopping'); ?>
                </a>
            </div>
            
            <!-- Secure Payment -->
            <div class="secure-info">
                <span class="material-icons">verified_user</span>
                <div>
                    <strong><?php echo __('secure_checkout'); ?></strong>
                    <p><?php echo __('info_protected'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.cart-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.page-header {
    margin-bottom: var(--spacing-xl);
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

.cart-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .cart-layout {
        grid-template-columns: 1fr 380px;
    }
}

/* Cart Items */
.cart-items {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.cart-item {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: var(--spacing-md);
    background: var(--white);
    padding: var(--spacing-md);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    position: relative;
}

@media (min-width: 768px) {
    .cart-item {
        grid-template-columns: 100px 1fr auto auto auto;
        align-items: center;
    }
}

.cart-item-image {
    width: 100%;
    aspect-ratio: 1;
    border-radius: var(--radius-md);
    overflow: hidden;
}

.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-details h3 {
    font-size: var(--font-size-base);
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.cart-item-details h3 a {
    color: inherit;
}

.cart-item-details h3 a:hover {
    color: var(--primary);
}

.cart-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.cart-item-meta .seller {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.cart-item-meta .material-icons {
    font-size: 1rem;
}

.cart-item-quantity {
    display: flex;
    align-items: center;
}

.quantity-control {
    display: flex;
    align-items: center;
    background: var(--gray-100);
    border-radius: var(--radius-md);
}

.quantity-control button {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
}

.quantity-control button:hover {
    background: var(--gray-200);
}

.quantity-control input {
    width: 50px;
    text-align: center;
    border: none;
    background: transparent;
    font-weight: 600;
}

.cart-item-total {
    text-align: right;
}

.total-price {
    font-size: var(--font-size-lg);
    font-weight: 700;
    color: var(--primary);
}

.cart-item-remove {
    position: absolute;
    top: var(--spacing-sm);
    right: var(--spacing-sm);
    color: var(--gray-400);
    padding: var(--spacing-xs);
}

@media (min-width: 768px) {
    .cart-item-remove {
        position: static;
    }
}

.cart-item-remove:hover {
    color: var(--danger);
}

/* Summary */
.cart-summary {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.summary-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--spacing-md);
}

.summary-card h3 {
    font-size: var(--font-size-lg);
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--gray-200);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.summary-row.total {
    font-size: var(--font-size-lg);
    font-weight: 700;
    color: var(--gray-800);
}

.summary-row.total span:last-child {
    color: var(--primary);
}

.summary-divider {
    height: 1px;
    background: var(--gray-200);
    margin: var(--spacing-md) 0;
}

.text-success {
    color: var(--success);
}

.login-note {
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--gray-500);
    margin-top: var(--spacing-sm);
}

.login-note a {
    font-weight: 600;
}

.secure-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: rgba(16, 185, 129, 0.1);
    border-radius: var(--radius-md);
}

.secure-info .material-icons {
    color: var(--success);
    font-size: 2rem;
}

.secure-info strong {
    display: block;
    color: var(--gray-800);
    font-size: var(--font-size-sm);
}

.secure-info p {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
    margin: 0;
}
</style>

<script>
// Store item prices for calculations
const itemPrices = {
    <?php foreach ($cartItems as $item): ?>
    <?php echo $item['cart_id']; ?>: <?php echo $item['price']; ?>,
    <?php endforeach; ?>
};

async function updateCartItem(cartId, delta) {
    const input = document.getElementById('qty-' + cartId);
    let newQty = parseInt(input.value) + delta;
    if (newQty < 1) newQty = 1;
    if (newQty > parseInt(input.max)) newQty = parseInt(input.max);
    
    input.value = newQty;
    await setCartQuantity(cartId, newQty);
}

async function setCartQuantity(cartId, quantity) {
    const result = await ajaxRequest('<?php echo shopUrl('ajax/cart.php'); ?>', {
        body: { action: 'update', cart_id: cartId, quantity: quantity }
    });
    
    if (result.success) {
        // Update item total
        const price = itemPrices[cartId];
        const total = price * quantity;
        document.getElementById('total-' + cartId).textContent = formatPrice(total);
        
        // Recalculate cart total
        updateCartTotals();
        updateCartBadge(result.cart_count);
    } else {
        showToast(result.message, 'error');
    }
}

async function removeCartItem(cartId) {
    if (!await confirmAction('Remove this item from cart?')) return;
    
    const result = await ajaxRequest('<?php echo shopUrl('ajax/cart.php'); ?>', {
        body: { action: 'remove', cart_id: cartId }
    });
    
    if (result.success) {
        document.getElementById('cart-item-' + cartId)?.remove();
        delete itemPrices[cartId];
        updateCartTotals();
        updateCartBadge(result.cart_count);
        showToast('Item removed', 'success');
        
        // If cart is empty, reload page
        if (Object.keys(itemPrices).length === 0) {
            location.reload();
        }
    } else {
        showToast(result.message, 'error');
    }
}

function updateCartTotals() {
    let subtotal = 0;
    document.querySelectorAll('.cart-item').forEach(item => {
        const cartId = item.id.replace('cart-item-', '');
        const qty = parseInt(document.getElementById('qty-' + cartId)?.value || 0);
        const price = itemPrices[cartId] || 0;
        subtotal += price * qty;
    });
    
    document.getElementById('cart-subtotal').textContent = formatPrice(subtotal);
    document.getElementById('cart-total').textContent = formatPrice(subtotal);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
