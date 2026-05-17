<?php
/**
 * Shop Checkout Page
 */

require_once __DIR__ . '/../config/config.php';

// Require login for checkout
requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

// Get cart items
$cartItems = $db->resultSet(
    "SELECT c.*, p.product_name, p.price, p.price_unit, p.image_url, p.quantity_available, p.seller_id
     FROM shop_cart c
     JOIN marketplace_products p ON c.product_id = p.product_id
     WHERE c.user_id = ? AND p.status = 'available'
     ORDER BY c.created_at DESC",
    [$user['user_id']]
);

// Redirect if cart is empty
if (empty($cartItems)) {
    setFlashMessage('warning', 'Your cart is empty. Add some products first.');
    shopRedirect('pages/products.php');
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shippingCost = $subtotal >= 500 ? 0 : 50; // Free shipping over 500
$total = $subtotal + $shippingCost;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyShopCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        shopRedirect('pages/checkout.php');
    }
    
    // Validate fields
    $errors = [];
    $shippingName = sanitize($_POST['shipping_name'] ?? '');
    $shippingPhone = sanitize($_POST['shipping_phone'] ?? '');
    $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
    $shippingCity = sanitize($_POST['shipping_city'] ?? '');
    $shippingDistrict = sanitize($_POST['shipping_district'] ?? '');
    $shippingPostal = sanitize($_POST['shipping_postal'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($shippingName)) $errors[] = 'Name is required';
    if (empty($shippingPhone)) $errors[] = 'Phone number is required';
    if (empty($shippingAddress)) $errors[] = 'Address is required';
    if (empty($shippingDistrict)) $errors[] = 'District is required';
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Create order
            $orderNumber = generateOrderNumber();
            $orderId = $db->insert('shop_orders', [
                'order_number' => $orderNumber,
                'buyer_id' => $user['user_id'],
                'total_amount' => $total,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'shipping_name' => $shippingName,
                'shipping_phone' => $shippingPhone,
                'shipping_address' => $shippingAddress,
                'shipping_city' => $shippingCity,
                'shipping_district' => $shippingDistrict,
                'shipping_postal_code' => $shippingPostal,
                'payment_method' => $paymentMethod,
                'notes' => $notes
            ]);
            
            // Create order items
            foreach ($cartItems as $item) {
                $db->insert('shop_order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'seller_id' => $item['seller_id'],
                    'product_name' => $item['product_name'],
                    'product_image' => $item['image_url'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['price'] * $item['quantity']
                ]);
                
                // Update product quantity
                $db->query("UPDATE marketplace_products SET quantity_available = quantity_available - ? WHERE product_id = ? AND quantity_available >= ?")
                   ->bind(1, $item['quantity'])->bind(2, $item['product_id'])->bind(3, $item['quantity'])->execute();
            }
            
            // Clear cart
            $db->query("DELETE FROM shop_cart WHERE user_id = ?")->bind(1, $user['user_id'])->execute();
            
            $db->commit();
            
            // Notify all sellers about the new order
            try {
                $sellerIds = array_unique(array_column($cartItems, 'seller_id'));
                foreach ($sellerIds as $sellerId) {
                    $db->query("INSERT INTO user_notifications (user_id, user_type, title, message, type, icon, link, reference_id) VALUES (?, 'farmer', ?, ?, 'order', 'shopping_cart', '?page=farmer-orders', ?)")
                       ->bind(1, $sellerId)
                       ->bind(2, 'New Shop Order')
                       ->bind(3, $user['first_name'] . ' placed a new order #' . $orderNumber)
                       ->bind(4, $orderId)
                       ->execute();
                }
            } catch (Exception $e) {
                // Notification failure shouldn't break checkout
            }
            
            // Redirect to confirmation
            setFlashMessage('success', 'Order placed successfully!');
            shopRedirect('pages/order-confirmation.php?order=' . $orderNumber);
            
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to place order. Please try again.');
        }
    } else {
        setFlashMessage('error', implode(', ', $errors));
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/../layouts/header.php';
?>

<div class="checkout-page container">
    <div class="page-header">
        <h1><span class="material-icons">payment</span> <?php echo __('checkout'); ?></h1>
    </div>

    <form method="POST" action="" id="checkoutForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateShopCSRFToken(); ?>">
        
        <div class="checkout-layout">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <!-- Shipping Info -->
                <div class="checkout-section">
                    <h2><span class="material-icons">local_shipping</span> <?php echo __('shipping_information'); ?></h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="shipping_name" class="form-label"><?php echo __('full_name_label'); ?></label>
                            <input type="text" id="shipping_name" name="shipping_name" class="form-control"
                                   value="<?php echo htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? '')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="shipping_phone" class="form-label"><?php echo __('phone_number_label'); ?></label>
                            <input type="tel" id="shipping_phone" name="shipping_phone" class="form-control"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="01XXXXXXXXX" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping_address" class="form-label"><?php echo __('street_address_label'); ?></label>
                        <textarea id="shipping_address" name="shipping_address" class="form-control" rows="2"
                                  placeholder="House no, Road, Area" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="shipping_city" class="form-label"><?php echo __('city_label'); ?></label>
                            <input type="text" id="shipping_city" name="shipping_city" class="form-control"
                                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="shipping_district" class="form-label"><?php echo __('district_label'); ?></label>
                            <select id="shipping_district" name="shipping_district" class="form-control" required>
                                <option value=""><?php echo __('select_district'); ?></option>
                                <?php
                                $districts = ['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet', 'Barisal', 'Rangpur', 'Mymensingh', 'Comilla', 'Gazipur', 'Narayanganj'];
                                foreach ($districts as $d):
                                ?>
                                <option value="<?php echo $d; ?>" <?php echo ($user['district'] ?? '') === $d ? 'selected' : ''; ?>>
                                    <?php echo $d; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="shipping_postal" class="form-label"><?php echo __('postal_code_label'); ?></label>
                            <input type="text" id="shipping_postal" name="shipping_postal" class="form-control"
                                   value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="checkout-section">
                    <h2><span class="material-icons">credit_card</span> <?php echo __('payment_method_title'); ?></h2>
                    
                    <div class="payment-options">
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="cod" checked>
                            <div class="payment-content">
                                <span class="payment-icon">💵</span>
                                <div>
                                    <strong><?php echo __('cash_on_delivery'); ?></strong>
                                    <span><?php echo __('pay_when_receive'); ?></span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="bkash">
                            <div class="payment-content">
                                <span class="payment-icon">📱</span>
                                <div>
                                    <strong>bKash</strong>
                                    <span><?php echo __('pay_via_bkash'); ?></span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="nagad">
                            <div class="payment-content">
                                <span class="payment-icon">📱</span>
                                <div>
                                    <strong>Nagad</strong>
                                    <span><?php echo __('pay_via_nagad'); ?></span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Order Notes -->
                <div class="checkout-section">
                    <h2><span class="material-icons">note</span> <?php echo __('order_notes_title'); ?></h2>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="<?php echo __('special_delivery_instructions'); ?>"></textarea>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-card">
                    <h3><?php echo __('order_summary_title'); ?></h3>
                    
                    <div class="order-items">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo getProductImage($item['image_url']); ?>" alt="">
                            <div class="order-item-info">
                                <span class="name"><?php echo htmlspecialchars(truncateText($item['product_name'], 30)); ?></span>
                                <span class="qty">x<?php echo $item['quantity']; ?></span>
                            </div>
                            <span class="price"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row">
                        <span><?php echo __('subtotal'); ?></span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>

                    <div class="summary-row">
                        <span><?php echo __('shipping_label'); ?></span>
                        <span class="<?php echo $shippingCost === 0 ? 'text-success' : ''; ?>">
                            <?php echo $shippingCost === 0 ? __('shipping_free') : formatPrice($shippingCost); ?>
                        </span>
                    </div>
                    
                    <?php if ($shippingCost > 0): ?>
                    <div class="free-shipping-note">
                        <span class="material-icons">info</span>
                        Add <?php echo formatPrice(500 - $subtotal); ?> more for free shipping!
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row total">
                        <span><?php echo __('total_label'); ?></span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="placeOrderBtn">
                        <span class="material-icons">check_circle</span>
                        <?php echo __('place_order_btn'); ?>
                    </button>

                    <p class="terms-note">
                        <?php echo __('agree_terms_text'); ?>
                        <a href="#"><?php echo __('terms_of_service'); ?></a> &amp; <a href="#"><?php echo __('return_policy'); ?></a>.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.checkout-page {
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
}

.checkout-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .checkout-layout {
        grid-template-columns: 1fr 400px;
    }
}

.checkout-section {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-lg);
}

.checkout-section h2 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-lg);
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--gray-200);
}

.checkout-section h2 .material-icons {
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
}

@media (min-width: 640px) {
    .form-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row:has(.form-group:nth-child(3)) {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Payment Options */
.payment-options {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.payment-option {
    display: block;
    cursor: pointer;
}

.payment-option input {
    display: none;
}

.payment-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
}

.payment-option input:checked + .payment-content {
    border-color: var(--primary);
    background: rgba(85, 122, 70, 0.05);
}

.payment-icon {
    font-size: 2rem;
}

.payment-content strong {
    display: block;
    color: var(--gray-800);
}

.payment-content span {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

/* Order Summary */
.order-summary {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.summary-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.summary-card h3 {
    font-size: var(--font-size-lg);
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
}

.order-items {
    max-height: 250px;
    overflow-y: auto;
    margin-bottom: var(--spacing-md);
}

.order-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-md);
}

.order-item img {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-sm);
    object-fit: cover;
}

.order-item-info {
    flex: 1;
}

.order-item-info .name {
    display: block;
    font-size: var(--font-size-sm);
    color: var(--gray-700);
}

.order-item-info .qty {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
}

.order-item .price {
    font-weight: 600;
    color: var(--gray-800);
}

.summary-divider {
    height: 1px;
    background: var(--gray-200);
    margin: var(--spacing-md) 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.summary-row.total {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--gray-800);
    margin-top: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.summary-row.total span:last-child {
    color: var(--primary);
}

.text-success {
    color: var(--success);
    font-weight: 600;
}

.free-shipping-note {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-xs);
    color: var(--accent);
    background: rgba(255, 140, 0, 0.1);
    padding: var(--spacing-sm);
    border-radius: var(--radius-sm);
    margin-top: var(--spacing-sm);
}

.free-shipping-note .material-icons {
    font-size: 1rem;
}

.terms-note {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
    text-align: center;
    margin-top: var(--spacing-md);
}

.terms-note a {
    color: var(--primary);
}
</style>

<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;margin-right:8px;"></span> Processing...';
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
