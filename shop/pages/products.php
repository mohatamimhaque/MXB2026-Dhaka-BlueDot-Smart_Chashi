<?php
/**
 * Shop Products Listing Page
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$productType = sanitize($_GET['type'] ?? '');
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$sort = sanitize($_GET['sort'] ?? 'latest');
$featured = isset($_GET['featured']) ? 1 : 0;
$district = sanitize($_GET['district'] ?? '');
$sellerId = intval($_GET['seller'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = SHOP_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build query
$baseQuery = "FROM marketplace_products mp
              LEFT JOIN users u ON mp.seller_id = u.user_id
              WHERE mp.status = 'available'";

$params = [];

if (!empty($search)) {
    $baseQuery .= " AND (mp.product_name LIKE ? OR mp.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $baseQuery .= " AND mp.category = ?";
    $params[] = $category;
}

if (!empty($productType)) {
    $baseQuery .= " AND mp.product_type = ?";
    $params[] = $productType;
}

// Get price range first so we can skip trivial filters
$priceRange = $db->single("SELECT MIN(price) as min_price, MAX(price) as max_price FROM marketplace_products WHERE status = 'available'");
$_actualMin = (int)floor($priceRange['min_price'] ?? 0);
$_actualMax = (int)ceil($priceRange['max_price'] ?? 0);

if ($minPrice !== null && $minPrice > $_actualMin) {
    $baseQuery .= " AND mp.price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice !== null && $maxPrice < $_actualMax) {
    $baseQuery .= " AND mp.price <= ?";
    $params[] = $maxPrice;
}

if (!empty($district)) {
    $baseQuery .= " AND (mp.district = ? OR mp.location LIKE ?)";
    $params[] = $district;
    $params[] = '%' . $district . '%';
}

if ($sellerId) {
    $baseQuery .= " AND mp.seller_id = ?";
    $params[] = $sellerId;
}

if ($featured) {
    $baseQuery .= " AND mp.is_featured = 1";
}

// Count total
$countResult = $db->single("SELECT COUNT(*) as total $baseQuery", $params);
$totalProducts = $countResult['total'] ?? 0;
$totalPages = ceil($totalProducts / $perPage);

// Sort options
$orderBy = match($sort) {
    'price_low' => 'mp.price ASC',
    'price_high' => 'mp.price DESC',
    'popular' => 'mp.views DESC',
    'rating' => 'mp.average_rating DESC',
    default => 'mp.created_at DESC'
};

// Get products
$products = $db->resultSet(
    "SELECT mp.*, u.first_name as seller_name, u.last_name as seller_last_name
     $baseQuery
     ORDER BY $orderBy
     LIMIT $perPage OFFSET $offset",
    $params
);

// Get all categories for filter
$allCategories = $db->resultSet(
    "SELECT category, COUNT(*) as count 
     FROM marketplace_products 
     WHERE status = 'available' AND category IS NOT NULL AND category != ''
     GROUP BY category 
     ORDER BY count DESC"
);

// Get product types
$productTypes = $db->resultSet(
    "SELECT product_type, COUNT(*) as count
     FROM marketplace_products
     WHERE status = 'available'
     GROUP BY product_type
     ORDER BY count DESC"
);

// Get distinct districts (from products table)
$allDistricts = $db->resultSet(
    "SELECT district, COUNT(*) as count
     FROM marketplace_products
     WHERE status = 'available' AND district IS NOT NULL AND district != ''
     GROUP BY district
     ORDER BY count DESC"
);

$pageTitle = 'Products';
if (!empty($category)) {
    $pageTitle = htmlspecialchars($category) . ' Products';
}
if (!empty($search)) {
    $pageTitle = 'Search: ' . htmlspecialchars($search);
}

// Get seller name for page title/pill
$_sellerName = '';
if ($sellerId) {
    $_sRow = $db->single("SELECT first_name, last_name FROM users WHERE user_id = ?", [$sellerId]);
    if ($_sRow) {
        $_sellerName = trim($_sRow['first_name'] . ' ' . ($_sRow['last_name'] ?? ''));
        $pageTitle = 'Products by ' . htmlspecialchars($_sellerName);
    }
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="products-page container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1><?php echo $pageTitle; ?></h1>
            <p><?php echo number_format($totalProducts); ?> <?php echo __('products_found'); ?></p>
        </div>
        <div class="page-header-actions">
            <div class="search-box">
                <form action="" method="GET" class="search-form">
                    <span class="material-icons">search</span>
                    <input type="text" name="search" placeholder="<?php echo __('search_products'); ?>"
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><?php echo __('search'); ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="products-layout">
        <!-- Sidebar Filters -->
        <aside class="filters-sidebar" id="filtersSidebar">
            <div class="filters-header">
                <h3><span class="material-icons">filter_list</span> <?php echo __('filters_label'); ?></h3>
                <button class="filters-close" id="filtersClose">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <form action="" method="GET" id="filtersForm">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <?php if ($featured): ?>
                    <input type="hidden" name="featured" value="1">
                <?php endif; ?>
                
                <!-- Category Filter -->
                <div class="filter-group">
                    <h4><?php echo __('category'); ?></h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="radio" name="category" value="" <?php echo $category === '' ? 'checked' : ''; ?>>
                            <span><?php echo __('all_categories_opt'); ?></span>
                        </label>
                        <?php foreach ($allCategories as $cat): ?>
                        <label class="filter-option">
                            <input type="radio" name="category" value="<?php echo htmlspecialchars($cat['category']); ?>"
                                   <?php echo $category === $cat['category'] ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Product Type Filter -->
                <div class="filter-group">
                    <h4><?php echo __('product_type_label'); ?></h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="radio" name="type" value="" <?php echo $productType === '' ? 'checked' : ''; ?>>
                            <span><?php echo __('all_types_opt'); ?></span>
                        </label>
                        <?php foreach ($productTypes as $type): ?>
                        <label class="filter-option">
                            <input type="radio" name="type" value="<?php echo htmlspecialchars($type['product_type']); ?>"
                                   <?php echo $productType === $type['product_type'] ? 'checked' : ''; ?>>
                            <span><?php echo ucfirst(htmlspecialchars($type['product_type'])); ?> (<?php echo $type['count']; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Location / District Filter -->
                <?php if (!empty($allDistricts)): ?>
                <div class="filter-group">
                    <h4><span class="material-icons" style="font-size:16px;vertical-align:middle;">location_on</span> <?php echo __('farmer_location'); ?></h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="radio" name="district" value="" <?php echo $district === '' ? 'checked' : ''; ?>>
                            <span><?php echo __('all_locations'); ?></span>
                        </label>
                        <?php foreach ($allDistricts as $dist): ?>
                        <label class="filter-option">
                            <input type="radio" name="district" value="<?php echo htmlspecialchars($dist['district']); ?>"
                                   <?php echo $district === $dist['district'] ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($dist['district']); ?> (<?php echo $dist['count']; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Price Range Filter -->
                <div class="filter-group">
                    <h4><?php echo __('price_range_label'); ?></h4>
                    <div class="price-inputs">
                        <input type="number" name="min_price"
                               placeholder="<?php echo $_actualMin; ?>"
                               value="<?php echo $minPrice !== null ? (int)$minPrice : $_actualMin; ?>"
                               min="<?php echo $_actualMin; ?>" max="<?php echo $_actualMax; ?>" step="1">
                        <span>–</span>
                        <input type="number" name="max_price"
                               placeholder="<?php echo $_actualMax; ?>"
                               value="<?php echo $maxPrice !== null ? (int)$maxPrice : $_actualMax; ?>"
                               min="<?php echo $_actualMin; ?>" max="<?php echo $_actualMax; ?>" step="1">
                    </div>
                    <div class="price-range-info">
                        <?php echo __('available_label'); ?> <?php echo formatPrice($_actualMin); ?> – <?php echo formatPrice($_actualMax); ?>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-block"><?php echo __('apply_filters_btn'); ?></button>
                    <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-ghost btn-block"><?php echo __('clear_all_btn'); ?></a>
                </div>
            </form>
        </aside>
        <div class="filters-overlay" id="filtersOverlay"></div>
        
        <!-- Products Grid -->
        <div class="products-main">
            <!-- Active Filter Pills -->
            <?php
            $activeFilters = [];
            if ($sellerId && $_sellerName) $activeFilters[] = ['label' => '👨‍🌾 ' . htmlspecialchars($_sellerName), 'param' => 'seller'];
            if (!empty($search))        $activeFilters[] = ['label' => 'Search: ' . htmlspecialchars($search),     'param' => 'search'];
            if (!empty($category))      $activeFilters[] = ['label' => htmlspecialchars($category),                 'param' => 'category'];
            if (!empty($productType))   $activeFilters[] = ['label' => ucfirst(htmlspecialchars($productType)),     'param' => 'type'];
            if (!empty($district))      $activeFilters[] = ['label' => '📍 ' . htmlspecialchars($district),         'param' => 'district'];
            if ($minPrice !== null && $minPrice > $_actualMin)  $activeFilters[] = ['label' => 'Min ৳' . number_format($minPrice, 0), 'param' => 'min_price'];
            if ($maxPrice !== null && $maxPrice < $_actualMax)  $activeFilters[] = ['label' => 'Max ৳' . number_format($maxPrice, 0), 'param' => 'max_price'];
            if ($featured)              $activeFilters[] = ['label' => 'Featured only',                             'param' => 'featured'];
            ?>
            <?php if (!empty($activeFilters)): ?>
            <div class="active-filters" id="activeFilters">
                <?php foreach ($activeFilters as $af): ?>
                <span class="filter-pill">
                    <?php echo $af['label']; ?>
                    <button type="button" onclick="removeFilter('<?php echo $af['param']; ?>')" title="Remove filter">×</button>
                </span>
                <?php endforeach; ?>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="filter-pill" style="background:rgba(239,68,68,0.1);color:#991b1b;">
                    Clear all ×
                </a>
            </div>
            <?php endif; ?>

            <!-- Sort & View Options -->
            <div class="products-toolbar">
                <div style="display:flex;align-items:center;gap:var(--spacing-md);flex-wrap:wrap;">
                    <button class="btn btn-ghost filter-toggle" id="filterToggle">
                        <span class="material-icons">filter_list</span>
                        <?php echo __('filters_label'); ?>
                    </button>
                    <span class="products-count"><?php echo number_format($totalProducts); ?> product<?php echo $totalProducts !== 1 ? 's' : ''; ?></span>
                </div>

                <div class="sort-dropdown">
                    <label><?php echo __('sort_by_label'); ?></label>
                    <select id="sortSelect" onchange="updateSort(this.value)">
                        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>><?php echo __('newest_first'); ?></option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>><?php echo __('price_low_high'); ?></option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>><?php echo __('price_high_low'); ?></option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>><?php echo __('most_popular_opt'); ?></option>
                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>><?php echo __('best_rating_opt'); ?></option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($products)): ?>
            <div class="empty-state">
                <span class="material-icons">inventory_2</span>
                <h3><?php echo __('no_products_found'); ?></h3>
                <p><?php echo __('try_adjust_filters'); ?></p>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary"><?php echo __('view_all_products'); ?></a>
            </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-card-image">
                        <a href="<?php echo shopUrl('product/' . $product['product_id']); ?>">
                            <img src="<?php echo getProductImage($product['image_url']); ?>"
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 loading="lazy">
                        </a>
                        <?php if ($product['is_featured']): ?>
                            <span class="product-badge badge-accent"><?php echo __('featured_badge'); ?></span>
                        <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                            <span class="product-badge badge-success"><?php echo __('new_badge'); ?></span>
                        <?php endif; ?>
                        <button class="product-wishlist" data-wishlist-id="<?php echo $product['product_id']; ?>" title="Add to wishlist">
                            <span class="material-icons">favorite_border</span>
                        </button>
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
                            <a href="<?php echo shopUrl('pages/farmer-profile.php?id=' . $product['seller_id']); ?>"
                               style="color:inherit;text-decoration:none;" onclick="event.stopPropagation()">
                                <?php echo htmlspecialchars($product['seller_name'] . ' ' . ($product['seller_last_name'] ?? '')); ?>
                            </a>
                        </div>
                        <?php if (!empty($product['location'])): ?>
                        <div class="product-location">
                            <span class="material-icons">location_on</span>
                            <?php echo htmlspecialchars($product['location']); ?>
                        </div>
                        <?php endif; ?>
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <span class="material-icons">chevron_left</span>
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        <span class="material-icons">chevron_right</span>
                    </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.products-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.page-header {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 768px) {
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.page-header h1 {
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-xs);
}

.page-header p {
    color: var(--gray-500);
}

.search-form {
    display: flex;
    align-items: center;
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-full);
    padding: var(--spacing-xs) var(--spacing-md);
    transition: border-color var(--transition-fast);
}

.search-form:focus-within {
    border-color: var(--primary);
}

.search-form .material-icons {
    color: var(--gray-400);
    margin-right: var(--spacing-sm);
}

.search-form input {
    border: none;
    outline: none;
    padding: var(--spacing-sm);
    min-width: 200px;
}

.search-form button {
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--radius-full);
    font-weight: 500;
    cursor: pointer;
}

.products-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .products-layout {
        grid-template-columns: 280px 1fr;
    }
}

/* Sidebar */
.filters-sidebar {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-md);
    height: fit-content;
    position: sticky;
    top: 100px;
    display: none;
}

@media (min-width: 1024px) {
    .filters-sidebar {
        display: block;
    }
}

.filters-sidebar.active {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    width: 300px;
    height: 100vh;
    z-index: 2000;
    border-radius: 0;
    overflow-y: auto;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--gray-200);
}

.filters-header h3 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-lg);
}

.filters-close {
    display: block;
}

@media (min-width: 1024px) {
    .filters-close {
        display: none;
    }
}

.filters-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1999;
    display: none;
}

.filters-overlay.active {
    display: block;
}

.filter-group {
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--gray-100);
}

.filter-group:last-of-type {
    border-bottom: none;
}

.filter-group h4 {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: var(--spacing-md);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-options {
    max-height: 200px;
    overflow-y: auto;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) 0;
    cursor: pointer;
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.filter-option:hover {
    color: var(--primary);
}

.filter-option input {
    accent-color: var(--primary);
}

.price-inputs {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.price-inputs input {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
}

.price-range-info {
    font-size: var(--font-size-xs);
    color: var(--gray-500);
    margin-top: var(--spacing-sm);
}

.filter-actions {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-lg);
}

/* Products Main */
.products-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.filter-toggle {
    display: flex;
}

@media (min-width: 1024px) {
    .filter-toggle {
        display: none;
    }
}

.sort-dropdown {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.sort-dropdown label {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.sort-dropdown select {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    background: var(--white);
    cursor: pointer;
}

.product-location {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-sm);
    color: var(--gray-500);
    margin-bottom: var(--spacing-sm);
}

.product-location .material-icons {
    font-size: 1rem;
}

.pagination-ellipsis {
    padding: 0 var(--spacing-sm);
    color: var(--gray-400);
}

.badge-accent {
    background: var(--accent);
}
</style>

<script>
// Filter toggle for mobile
document.getElementById('filterToggle')?.addEventListener('click', () => {
    document.getElementById('filtersSidebar').classList.add('active');
    document.getElementById('filtersOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
});

document.getElementById('filtersClose')?.addEventListener('click', closeFilters);
document.getElementById('filtersOverlay')?.addEventListener('click', closeFilters);

function closeFilters() {
    document.getElementById('filtersSidebar').classList.remove('active');
    document.getElementById('filtersOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Sort update
function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    window.location = url.toString();
}

// Remove a single filter param
function removeFilter(param) {
    const url = new URL(window.location);
    url.searchParams.delete(param);
    url.searchParams.delete('page');
    window.location = url.toString();
}

// Auto-submit sidebar on radio change
document.querySelectorAll('#filtersForm input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', () => document.getElementById('filtersForm').submit());
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
