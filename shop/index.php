<?php
/**
 * Shop Home Page / Landing
 */

require_once __DIR__ . '/config/config.php';

$db = new ShopDatabase();

// Get featured products
$featuredProducts = $db->resultSet(
    "SELECT mp.*, u.first_name as seller_name, u.last_name as seller_last_name
     FROM marketplace_products mp
     LEFT JOIN users u ON mp.seller_id = u.user_id
     WHERE mp.status = 'available' AND mp.is_featured = 1
     ORDER BY mp.created_at DESC
     LIMIT 8"
);

// Get latest products
$latestProducts = $db->resultSet(
    "SELECT mp.*, u.first_name as seller_name, u.last_name as seller_last_name
     FROM marketplace_products mp
     LEFT JOIN users u ON mp.seller_id = u.user_id
     WHERE mp.status = 'available'
     ORDER BY mp.created_at DESC
     LIMIT 8"
);

// Get product categories (from existing products)
$categories = $db->resultSet(
    "SELECT category, COUNT(*) as count 
     FROM marketplace_products 
     WHERE status = 'available' AND category IS NOT NULL AND category != ''
     GROUP BY category 
     ORDER BY count DESC 
     LIMIT 6"
);

// Get stats
$stats = [
    'products' => $db->single("SELECT COUNT(*) as count FROM marketplace_products WHERE status = 'available'")['count'] ?? 0,
    'farmers' => $db->single("SELECT COUNT(DISTINCT seller_id) as count FROM marketplace_products WHERE status = 'available'")['count'] ?? 0,
    'orders' => $db->single("SELECT COUNT(*) as count FROM shop_orders WHERE order_status = 'delivered'")['count'] ?? 0
];

$pageTitle = 'Home';
include __DIR__ . '/layouts/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <span class="hero-badge">🌱 <?php echo __('fresh_from_farm'); ?></span>
            <h1><?php echo __('shop_hero_title1'); ?> <br><span class="highlight"><?php echo __('shop_hero_title2'); ?></span></h1>
            <p><?php echo __('shop_hero_subtitle'); ?></p>
            <div class="hero-actions">
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-lg">
                    <span class="material-icons">storefront</span>
                    <?php echo __('shop_now'); ?>
                </a>
                <a href="#how-it-works" class="btn btn-outline btn-lg">
                    <?php echo __('learn_more'); ?>
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-number"><?php echo number_format($stats['products']); ?>+</span>
                    <span class="stat-label"><?php echo __('products_menu'); ?></span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?php echo number_format($stats['farmers']); ?>+</span>
                    <span class="stat-label"><?php echo __('farmers'); ?></span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?php echo number_format($stats['orders']); ?>+</span>
                    <span class="stat-label"><?php echo __('orders_delivered'); ?></span>
                </div>
            </div>
        </div>
        <div class="hero-illustration">
            <div class="hero-image-wrapper">
                <div class="hero-circle"></div>
                <div class="floating-item item-1">🥕</div>
                <div class="floating-item item-2">🍅</div>
                <div class="floating-item item-3">🌽</div>
                <div class="floating-item item-4">🥬</div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<?php if (!empty($categories)): ?>
<section class="categories-section">
    <div class="container">
        <div class="section-header">
            <h2><?php echo __('shop_by_category'); ?></h2>
            <a href="<?php echo shopUrl('pages/products.php'); ?>" class="view-all">
                <?php echo __('view_all'); ?> <span class="material-icons">arrow_forward</span>
            </a>
        </div>
        <div class="categories-grid">
            <?php 
            $categoryIcons = [
                'Vegetables' => '🥕',
                'Fruits' => '🍎',
                'Grains' => '🌾',
                'Rice' => '🍚',
                'Seeds' => '🌱',
                'Fertilizer' => '🧪',
                'Equipment' => '🚜',
                'Other' => '📦'
            ];
            foreach ($categories as $cat): 
                $icon = $categoryIcons[$cat['category']] ?? '📦';
            ?>
            <a href="<?php echo shopUrl('pages/products.php?category=' . urlencode($cat['category'])); ?>" class="category-card">
                <span class="category-icon"><?php echo $icon; ?></span>
                <span class="category-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                <span class="category-count"><?php echo $cat['count']; ?> <?php echo __('items_label'); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<?php if (!empty($featuredProducts)): ?>
<section class="products-section">
    <div class="container">
        <div class="section-header">
            <h2><?php echo __('featured_products_title'); ?></h2>
            <a href="<?php echo shopUrl('pages/products.php?featured=1'); ?>" class="view-all">
                <?php echo __('view_all'); ?> <span class="material-icons">arrow_forward</span>
            </a>
        </div>
        <div class="product-grid">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="product-card">
                <div class="product-card-image">
                    <a href="<?php echo shopUrl('product/' . $product['product_id']); ?>">
                        <img src="<?php echo getProductImage($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </a>
                    <span class="product-badge badge-accent"><?php echo __('featured_badge'); ?></span>
                </div>
                <div class="product-card-body">
                    <span class="product-category"><?php echo htmlspecialchars($product['category'] ?? $product['product_type']); ?></span>
                    <h3 class="product-title">
                        <a href="<?php echo shopUrl('product/' . $product['product_id']); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </a>
                    </h3>
                    <div class="product-seller">
                        <span class="material-icons">person</span>
                        <?php echo htmlspecialchars($product['seller_name'] . ' ' . ($product['seller_last_name'] ?? '')); ?>
                    </div>
                    <div class="product-price">
                        <span class="product-price-current"><?php echo formatPrice($product['price']); ?></span>
                        <span class="product-price-unit">/ <?php echo htmlspecialchars($product['price_unit'] ?? 'kg'); ?></span>
                    </div>
                </div>
                <div class="product-card-footer">
                    <button class="btn btn-primary" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                        <span class="material-icons">add_shopping_cart</span>
                        <?php echo __('add_to_cart'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- How It Works -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header text-center">
            <h2><?php echo __('how_it_works'); ?></h2>
            <p><?php echo __('how_it_works_subtitle'); ?></p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-icon">🔍</div>
                <h3><?php echo __('browse_products_step'); ?></h3>
                <p><?php echo __('browse_products_step_desc'); ?></p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-icon">🛒</div>
                <h3><?php echo __('add_to_cart_step'); ?></h3>
                <p><?php echo __('add_to_cart_step_desc'); ?></p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-icon">🚚</div>
                <h3><?php echo __('get_delivered_step'); ?></h3>
                <p><?php echo __('get_delivered_step_desc'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Latest Products -->
<?php if (!empty($latestProducts)): ?>
<section class="products-section bg-gray">
    <div class="container">
        <div class="section-header">
            <h2>🆕 <?php echo __('latest_products_title'); ?></h2>
            <a href="<?php echo shopUrl('pages/products.php?sort=latest'); ?>" class="view-all">
                <?php echo __('view_all'); ?> <span class="material-icons">arrow_forward</span>
            </a>
        </div>
        <div class="product-grid">
            <?php foreach ($latestProducts as $product): ?>
            <div class="product-card">
                <div class="product-card-image">
                    <a href="<?php echo shopUrl('product/' . $product['product_id']); ?>">
                        <img src="<?php echo getProductImage($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </a>
                    <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                        <span class="product-badge badge-success"><?php echo __('new_badge'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="product-card-body">
                    <span class="product-category"><?php echo htmlspecialchars($product['category'] ?? $product['product_type']); ?></span>
                    <h3 class="product-title">
                        <a href="<?php echo shopUrl('product/' . $product['product_id']); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </a>
                    </h3>
                    <div class="product-seller">
                        <span class="material-icons">person</span>
                        <?php echo htmlspecialchars($product['seller_name'] . ' ' . ($product['seller_last_name'] ?? '')); ?>
                    </div>
                    <div class="product-price">
                        <span class="product-price-current"><?php echo formatPrice($product['price']); ?></span>
                        <span class="product-price-unit">/ <?php echo htmlspecialchars($product['price_unit'] ?? 'kg'); ?></span>
                    </div>
                </div>
                <div class="product-card-footer">
                    <button class="btn btn-primary" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                        <span class="material-icons">add_shopping_cart</span>
                        <?php echo __('add_to_cart'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2><?php echo __('ready_to_shop'); ?></h2>
            <p><?php echo __('cta_shop_desc'); ?></p>
            <div class="cta-actions">
                <a href="<?php echo shopUrl('auth/register.php'); ?>" class="btn btn-secondary btn-lg">
                    <?php echo __('create_free_account'); ?>
                </a>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-outline-white btn-lg">
                    <?php echo __('products_menu'); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
    padding: var(--spacing-2xl) 0;
    overflow: hidden;
}

.hero-section .container {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
    align-items: center;
}

@media (min-width: 1024px) {
    .hero-section .container {
        grid-template-columns: 1fr 1fr;
    }
}

.hero-badge {
    display: inline-block;
    background: rgba(85, 122, 70, 0.1);
    color: var(--primary);
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 600;
    margin-bottom: var(--spacing-md);
}

.hero-content h1 {
    font-size: clamp(2rem, 5vw, 3.5rem);
    color: var(--gray-900);
    line-height: 1.2;
    margin-bottom: var(--spacing-lg);
}

.hero-content h1 .highlight {
    color: var(--primary);
}

.hero-content > p {
    font-size: var(--font-size-lg);
    color: var(--gray-600);
    margin-bottom: var(--spacing-xl);
    max-width: 500px;
}

.hero-actions {
    display: flex;
    gap: var(--spacing-md);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-xl);
}

.hero-stats {
    display: flex;
    gap: var(--spacing-xl);
}

.stat {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: var(--font-size-2xl);
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.hero-illustration {
    display: none;
    justify-content: center;
    align-items: center;
}

@media (min-width: 1024px) {
    .hero-illustration {
        display: flex;
    }
}

.hero-image-wrapper {
    position: relative;
    width: 400px;
    height: 400px;
}

.hero-circle {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-radius: 50%;
    opacity: 0.2;
}

.floating-item {
    position: absolute;
    font-size: 3rem;
    animation: float 3s ease-in-out infinite;
}

.item-1 { top: 10%; left: 10%; animation-delay: 0s; }
.item-2 { top: 20%; right: 10%; animation-delay: 0.5s; }
.item-3 { bottom: 20%; left: 5%; animation-delay: 1s; }
.item-4 { bottom: 10%; right: 20%; animation-delay: 1.5s; }

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

/* Section Styles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.section-header h2 {
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
}

.section-header.text-center {
    flex-direction: column;
    text-align: center;
}

.section-header p {
    color: var(--gray-500);
}

.view-all {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--primary);
    font-weight: 500;
}

.view-all:hover {
    color: var(--primary-dark);
}

/* Categories */
.categories-section {
    padding: var(--spacing-2xl) 0;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: var(--spacing-md);
}

.category-card {
    background: var(--white);
    border: 2px solid var(--gray-100);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    text-align: center;
    transition: all var(--transition-fast);
}

.category-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.category-icon {
    font-size: 2.5rem;
    display: block;
    margin-bottom: var(--spacing-sm);
}

.category-name {
    display: block;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.category-count {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

/* Products Section */
.products-section {
    padding: var(--spacing-2xl) 0;
}

.products-section.bg-gray {
    background: var(--gray-50);
}

/* How It Works */
.how-it-works {
    padding: var(--spacing-2xl) 0;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: var(--white);
}

.how-it-works .section-header h2 {
    color: var(--white);
}

.how-it-works .section-header p {
    color: rgba(255,255,255,0.8);
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-xl);
}

.step-card {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    text-align: center;
    position: relative;
}

.step-number {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 30px;
    background: var(--secondary);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: var(--font-size-sm);
}

.step-icon {
    font-size: 3rem;
    margin-bottom: var(--spacing-md);
}

.step-card h3 {
    font-size: var(--font-size-lg);
    margin-bottom: var(--spacing-sm);
}

.step-card p {
    color: rgba(255,255,255,0.8);
    font-size: var(--font-size-sm);
}

/* CTA Section */
.cta-section {
    padding: var(--spacing-2xl) 0;
    background: var(--gray-900);
    color: var(--white);
}

.cta-content {
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
}

.cta-content h2 {
    font-size: var(--font-size-3xl);
    margin-bottom: var(--spacing-md);
}

.cta-content p {
    color: var(--gray-400);
    margin-bottom: var(--spacing-xl);
}

.cta-actions {
    display: flex;
    justify-content: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.btn-outline-white {
    border: 2px solid var(--white);
    color: var(--white);
}

.btn-outline-white:hover {
    background: var(--white);
    color: var(--gray-900);
}

.badge-accent {
    background: var(--accent);
}
</style>

<?php include __DIR__ . '/layouts/footer.php'; ?>
