<?php

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$products = $db->resultSet("SELECT mp.*, u.first_name, u.last_name FROM marketplace_products mp LEFT JOIN users u ON mp.seller_id = u.user_id WHERE mp.status = 'available' ORDER BY mp.created_at DESC LIMIT 20");
?>

<section class="hero">
    <h1><?php echo __('farm_marketplace'); ?></h1>
    <p><?php echo __('buy_sell_direct'); ?></p>
</section>

<div class="marketplace-grid">
    <div class="marketplace-form-section">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">add_shopping_cart</span>
                    <?php echo __('add_your_product'); ?>
                </h3>
            </div>

            <form id="productForm" method="POST">
                <div class="form-group">
                    <label for="productName"><?php echo __('product_name'); ?> *</label>
                    <input type="text" id="productName" name="productName" placeholder="e.g., Fresh Tomatoes" required>
                </div>

                <div class="form-group">
                    <label for="description"><?php echo __('description'); ?></label>
                    <textarea id="description" name="description" placeholder=""></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price"><?php echo __('price_per_unit'); ?> *</label>
                        <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="quantity"><?php echo __('quantity_available'); ?> *</label>
                        <input type="number" id="quantity" name="quantity" placeholder="kg/bags" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-block"><?php echo __('list_product'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="marketplace-filter-section">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">search</span>
                    <?php echo __('search_filter'); ?>
                </h3>
            </div>

            <div class="form-group">
                <input type="text" placeholder="<?php echo __('search_products'); ?>" class="search-input">
            </div>

            <div class="form-group">
                <label><?php echo __('filter_category'); ?></label>
                <select>
                    <option><?php echo __('all_categories'); ?></option>
                    <option>Vegetables</option>
                    <option>Fruits</option>
                    <option>Grains</option>
                    <option>Seeds</option>
                    <option>Tools</option>
                </select>
            </div>

            <div class="form-group">
                <label><?php echo __('filter_region'); ?></label>
                <select>
                    <option><?php echo __('all_regions'); ?></option>
                    <option>Dhaka</option>
                    <option>Chittagong</option>
                    <option>Khulna</option>
                </select>
            </div>
        </div>
    </div>
</div>

<h2 class="section-title">
    <span class="material-icons" style="vertical-align: middle;">storefront</span>
    <?php echo __('available_products'); ?>
</h2>
<?php if ($products): ?>
    <div class="products-grid">
        <?php foreach ($products as $product): ?>
        <div class="card product-card">
            <div class="card-header">
                <h4 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                <span class="badge badge-success">Available</span>
            </div>

            <div class="card-content">
                <p class="product-description"><?php echo htmlspecialchars($product['description'] ?? 'Premium quality farm product'); ?></p>
                <div class="product-details">
                    <p><span class="material-icons">payments</span><strong>Price:</strong> ৳<?php echo $product['price']; ?>/unit</p>
                    <p><span class="material-icons">inventory</span><strong>Quantity:</strong> <?php echo $product['quantity_available']; ?> kg</p>
                    <p><span class="material-icons">person</span><strong>Seller:</strong> <?php echo htmlspecialchars($product['first_name'] . ' ' . ($product['last_name'] ?? '')); ?></p>
                </div>
            </div>

            <div class="card-footer">
                <button class="btn btn-small"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">phone</span> Contact</button>
                <button class="btn btn-small btn-secondary"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">add_shopping_cart</span> Add to Cart</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="notice notice-info">
        <p>No products available right now. Check back later!</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
