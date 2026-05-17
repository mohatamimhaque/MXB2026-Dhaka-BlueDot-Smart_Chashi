<?php
/**
 * SmartCashi - Marketplace Page
 * Buy and sell agricultural products directly
 */

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Get farmer profile for default values
$farmerProfile = $db->single("SELECT * FROM farmer_profiles WHERE user_id = ?", [$userId]);

// Get products with filters from URL
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$region = $_GET['region'] ?? '';
$productType = $_GET['type'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$sellerId = $_GET['seller'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT mp.*, u.first_name, u.last_name, u.phone as seller_phone, fp.region as seller_region 
        FROM marketplace_products mp 
        LEFT JOIN users u ON mp.seller_id = u.user_id 
        LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id 
        WHERE mp.status = 'available'";
$countSql = "SELECT COUNT(*) as total FROM marketplace_products mp WHERE mp.status = 'available'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (mp.product_name LIKE ? OR mp.description LIKE ?)";
    $countSql .= " AND (mp.product_name LIKE ? OR mp.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $sql .= " AND mp.category = ?";
    $countSql .= " AND mp.category = ?";
    $params[] = $category;
}

if (!empty($productType)) {
    $sql .= " AND mp.product_type = ?";
    $countSql .= " AND mp.product_type = ?";
    $params[] = $productType;
}

if (!empty($region)) {
    $sql .= " AND mp.region = ?";
    $countSql .= " AND mp.region = ?";
    $params[] = $region;
}

if (!empty($sellerId)) {
    $sql .= " AND mp.seller_id = ?";
    $countSql .= " AND mp.seller_id = ?";
    $params[] = $sellerId;
}

// Sorting
switch ($sortBy) {
    case 'price_low':
        $sql .= " ORDER BY mp.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY mp.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY mp.views DESC";
        break;
    default:
        $sql .= " ORDER BY mp.is_featured DESC, mp.created_at DESC";
}

$sql .= " LIMIT $limit OFFSET $offset";

$products = $db->resultSet($sql, $params);
$totalResult = $db->single($countSql, $params);
$totalProducts = $totalResult['total'] ?? 0;
$totalPages = ceil($totalProducts / $limit);

// Get my products count
$myProductsCount = $db->single("SELECT COUNT(*) as count FROM marketplace_products WHERE seller_id = ?", [$userId])['count'] ?? 0;

// Get my orders count
$myOrdersCount = $db->single("SELECT COUNT(*) as count FROM marketplace_orders WHERE buyer_id = ? AND order_status != 'cancelled'", [$userId])['count'] ?? 0;

// Get seller orders count (orders to fulfill)
$sellerOrdersCount = $db->single("SELECT COUNT(*) as count FROM marketplace_orders WHERE seller_id = ? AND order_status = 'pending'", [$userId])['count'] ?? 0;

// Get wishlist count
$wishlistCount = $db->single("SELECT COUNT(*) as count FROM product_wishlist WHERE user_id = ?", [$userId])['count'] ?? 0;

// Get pending offers count (received)
$offersCount = $db->single("SELECT COUNT(*) as count FROM product_offers WHERE seller_id = ? AND status = 'pending'", [$userId])['count'] ?? 0;

// Get compare list count
$compareCount = $db->single("SELECT COUNT(*) as count FROM product_comparisons WHERE user_id = ?", [$userId])['count'] ?? 0;

// Get categories and regions for filters
$categories = $db->resultSet("SELECT DISTINCT category FROM marketplace_products WHERE category IS NOT NULL AND category != '' ORDER BY category");
$regions = ['Dhaka', 'Chittagong', 'Khulna', 'Rangpur', 'Sylhet', 'Barisal', 'Rajshahi', 'Mymensingh'];

// Get market prices
$marketPrices = $db->resultSet("SELECT * FROM market_prices ORDER BY recorded_date DESC LIMIT 10");
?>

<!-- Modern Hero Section -->
<section class="hero-modern">
    <div class="hero-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">storefront</span>
            <span><?php echo __('marketplace'); ?></span>
        </div>
        <h1>
            <span class="material-icons" style="font-size: 2rem;">storefront</span>
            <?php echo __('farm_marketplace'); ?>
        </h1>
        <p class="hero-subtitle"><?php echo __('buy_sell_direct'); ?></p>
        <div class="hero-quick-stats">
            <div class="quick-stat">
                <span class="material-icons">inventory_2</span>
                <span><?php echo $totalProducts; ?> <?php echo __('products'); ?></span>
            </div>
            <div class="quick-stat">
                <span class="material-icons">shopping_bag</span>
                <span><?php echo $myOrdersCount; ?> <?php echo __('orders'); ?></span>
            </div>
        </div>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">inventory_2</span>
            <span><?php echo $myProductsCount; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">favorite</span>
            <span><?php echo $wishlistCount; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">trending_up</span>
        </div>
    </div>
</section>

<!-- Quick Stats -->
<div class="marketplace-stats">
    <div class="stat-card" onclick="openModal('myProductsModal')">
        <span class="material-icons">inventory_2</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo $myProductsCount; ?></span>
            <span class="stat-label"><?php echo __('my_products'); ?></span>
        </div>
    </div>
    <div class="stat-card" onclick="openModal('myOrdersModal')">
        <span class="material-icons">shopping_bag</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo $myOrdersCount; ?></span>
            <span class="stat-label"><?php echo __('my_orders'); ?></span>
        </div>
    </div>
    <div class="stat-card" onclick="openModal('sellerOrdersModal')">
        <span class="material-icons">local_shipping</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo $sellerOrdersCount; ?></span>
            <span class="stat-label"><?php echo __('orders_to_fulfill'); ?></span>
        </div>
        <?php if ($sellerOrdersCount > 0): ?>
        <span class="badge badge-warning"><?php echo $sellerOrdersCount; ?> <?php echo __('pending'); ?></span>
        <?php endif; ?>
    </div>
    <div class="stat-card" onclick="openModal('wishlistModal')">
        <span class="material-icons">favorite</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo $wishlistCount; ?></span>
            <span class="stat-label"><?php echo __('wishlist'); ?></span>
        </div>
    </div>
    <div class="stat-card" onclick="openModal('offersModal')">
        <span class="material-icons">local_offer</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo $offersCount; ?></span>
            <span class="stat-label"><?php echo __('offers'); ?></span>
        </div>
        <?php if ($offersCount > 0): ?>
        <span class="badge badge-info"><?php echo $offersCount; ?> <?php echo __('new'); ?></span>
        <?php endif; ?>
    </div>
    <div class="stat-card" onclick="openModal('marketPricesModal')">
        <span class="material-icons">trending_up</span>
        <div class="stat-info">
            <span class="stat-value"><?php echo count($marketPrices); ?></span>
            <span class="stat-label"><?php echo __('market_prices'); ?></span>
        </div>
    </div>
</div>

<div class="marketplace-layout">
    <!-- Sidebar - Add Product & Filters -->
    <aside class="marketplace-sidebar">
        <!-- Add Product Card -->
        <div class="card">
            <div class="card-header">
                <h3><span class="material-icons">add_shopping_cart</span> <?php echo __('add_your_product'); ?></h3>
            </div>
            <form id="addProductForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="productName"><?php echo __('product_name'); ?> *</label>
                    <input type="text" id="productName" name="productName" placeholder="e.g., Fresh Tomatoes" required>
                </div>
                
                <div class="form-group">
                    <label for="productType"><?php echo __('product_type'); ?> *</label>
                    <select id="productType" name="productType" required>
                        <option value="crop"><?php echo __('crop_produce'); ?></option>
                        <option value="seed"><?php echo __('seeds'); ?></option>
                        <option value="fertilizer"><?php echo __('fertilizer'); ?></option>
                        <option value="equipment"><?php echo __('equipment_tools'); ?></option>
                        <option value="service"><?php echo __('service'); ?></option>
                        <option value="other"><?php echo __('other'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category"><?php echo __('category'); ?></label>
                    <select id="category" name="category">
                        <option value=""><?php echo __('select_category'); ?></option>
                        <option value="Vegetables"><?php echo __('vegetables'); ?></option>
                        <option value="Fruits"><?php echo __('fruits'); ?></option>
                        <option value="Grains"><?php echo __('grains'); ?></option>
                        <option value="Pulses"><?php echo __('pulses'); ?></option>
                        <option value="Spices"><?php echo __('spices'); ?></option>
                        <option value="Seeds"><?php echo __('seeds'); ?></option>
                        <option value="Fertilizers"><?php echo __('fertilizers'); ?></option>
                        <option value="Tools"><?php echo __('tools'); ?></option>
                        <option value="Others"><?php echo __('others'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description"><?php echo __('description'); ?></label>
                    <textarea id="description" name="description" rows="3" placeholder="Describe your product..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price"><?php echo __('price_per_unit'); ?> (৳) *</label>
                        <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="priceUnit"><?php echo __('per'); ?></label>
                        <select id="priceUnit" name="priceUnit">
                            <option value="kg"><?php echo __('kg'); ?></option>
                            <option value="piece"><?php echo __('piece'); ?></option>
                            <option value="bag"><?php echo __('bag'); ?></option>
                            <option value="ton"><?php echo __('ton'); ?></option>
                            <option value="liter"><?php echo __('liter'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity"><?php echo __('quantity_available'); ?> *</label>
                        <input type="number" id="quantity" name="quantity" placeholder="0" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="unit"><?php echo __('unit'); ?></label>
                        <select id="unit" name="unit">
                            <option value="kg"><?php echo __('kg'); ?></option>
                            <option value="piece"><?php echo __('pieces'); ?></option>
                            <option value="bag"><?php echo __('bags'); ?></option>
                            <option value="ton"><?php echo __('tons'); ?></option>
                            <option value="liter"><?php echo __('liters'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="qualityGrade"><?php echo __('quality_grade'); ?></label>
                <select id="qualityGrade" name="qualityGrade">
                        <option value="standard"><?php echo __('standard'); ?></option>
                        <option value="A"><?php echo __('grade_a_premium'); ?></option>
                        <option value="B"><?php echo __('grade_b_good'); ?></option>
                        <option value="C"><?php echo __('grade_c_fair'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="region"><?php echo __('region'); ?> *</label>
                    <select id="region" name="region" required>
                        <option value=""><?php echo __('select_region'); ?></option>
                        <?php foreach ($regions as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo ($farmerProfile['region'] ?? '') === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location"><?php echo __('location_address'); ?></label>
                    <input type="text" id="location" name="location" placeholder="<?php echo __('village_upazila'); ?>" value="<?php echo htmlspecialchars($farmerProfile['village'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="contactPhone"><?php echo __('contact_phone'); ?></label>
                    <input type="tel" id="contactPhone" name="contactPhone" placeholder="01XXXXXXXXX" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="productImage"><?php echo __('product_image'); ?></label>
                    <input type="file" id="productImage" name="productImage" accept="image/*">
                    <small class="form-text"><?php echo __('jpg_png_gif_5mb'); ?></small>
                </div>
                
                <!-- New Extra Features Fields -->
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="isNegotiable" name="isNegotiable" value="1">
                        <span class="checkbox-custom"></span>
                        <span><?php echo __('price_negotiable'); ?></span>
                    </label>
                    <small class="form-text"><?php echo __('allow_buyers_offers'); ?></small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="minOrderQuantity"><?php echo __('min_order_qty'); ?></label>
                        <input type="number" id="minOrderQuantity" name="minOrderQuantity" placeholder="1" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label for="bulkMinQuantity"><?php echo __('bulk_order_qty'); ?></label>
                        <input type="number" id="bulkMinQuantity" name="bulkMinQuantity" placeholder="e.g., 100" min="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bulkDiscountPercent"><?php echo __('bulk_discount'); ?></label>
                    <input type="number" id="bulkDiscountPercent" name="bulkDiscountPercent" placeholder="e.g., 10" min="0" max="50" step="0.5">
                    <small class="form-text"><?php echo __('bulk_discount_desc'); ?></small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-block">
                        <span class="material-icons">add</span> <?php echo __('list_product'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h3><span class="material-icons">filter_list</span> <?php echo __('search_filter'); ?></h3>
            </div>
            <form id="filterForm" method="GET" action="">
                <div class="form-group">
                    <label for="searchInput"><?php echo __('search'); ?></label>
                    <input type="text" id="searchInput" name="search" placeholder="<?php echo __('search_products'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label for="filterCategory"><?php echo __('filter_category'); ?></label>
                    <select id="filterCategory" name="category">
                        <option value=""><?php echo __('all_categories'); ?></option>
                        <option value="Vegetables" <?php echo $category === 'Vegetables' ? 'selected' : ''; ?>><?php echo __('vegetables'); ?></option>
                        <option value="Fruits" <?php echo $category === 'Fruits' ? 'selected' : ''; ?>><?php echo __('fruits'); ?></option>
                        <option value="Grains" <?php echo $category === 'Grains' ? 'selected' : ''; ?>><?php echo __('grains'); ?></option>
                        <option value="Pulses" <?php echo $category === 'Pulses' ? 'selected' : ''; ?>><?php echo __('pulses'); ?></option>
                        <option value="Spices" <?php echo $category === 'Spices' ? 'selected' : ''; ?>><?php echo __('spices'); ?></option>
                        <option value="Seeds" <?php echo $category === 'Seeds' ? 'selected' : ''; ?>><?php echo __('seeds'); ?></option>
                        <option value="Fertilizers" <?php echo $category === 'Fertilizers' ? 'selected' : ''; ?>><?php echo __('fertilizers'); ?></option>
                        <option value="Tools" <?php echo $category === 'Tools' ? 'selected' : ''; ?>><?php echo __('tools'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filterType"><?php echo __('product_type'); ?></label>
                    <select id="filterType" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <option value="crop" <?php echo $productType === 'crop' ? 'selected' : ''; ?>><?php echo __('crops'); ?></option>
                        <option value="seed" <?php echo $productType === 'seed' ? 'selected' : ''; ?>><?php echo __('seeds'); ?></option>
                        <option value="fertilizer" <?php echo $productType === 'fertilizer' ? 'selected' : ''; ?>><?php echo __('fertilizers'); ?></option>
                        <option value="equipment" <?php echo $productType === 'equipment' ? 'selected' : ''; ?>><?php echo __('equipment'); ?></option>
                        <option value="service" <?php echo $productType === 'service' ? 'selected' : ''; ?>><?php echo __('services'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterRegion"><?php echo __('filter_region'); ?></label>
                    <select id="filterRegion" name="region">
                        <option value=""><?php echo __('all_regions'); ?></option>
                        <?php foreach ($regions as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $region === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filterSort"><?php echo __('sort_by'); ?></label>
                    <select id="filterSort" name="sort">
                        <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>><?php echo __('newest_first'); ?></option>
                        <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>><?php echo __('price_low_high'); ?></option>
                        <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>><?php echo __('price_high_low'); ?></option>
                        <option value="popular" <?php echo $sortBy === 'popular' ? 'selected' : ''; ?>><?php echo __('most_popular'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-block btn-secondary">
                        <span class="material-icons">search</span> <?php echo __('apply_filters'); ?>
                    </button>
                </div>
                
                <?php if (!empty($search) || !empty($category) || !empty($region) || !empty($productType)): ?>
                <div class="form-group">
                    <a href="<?php echo $base_url; ?>marketplace" class="btn btn-block btn-outline">
                        <span class="material-icons">clear</span> <?php echo __('clear_filters'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </aside>

    <!-- Main Content - Products Grid -->
    <main class="marketplace-main">
        <div class="products-header">
            <h2>
                <span class="material-icons">storefront</span>
                <?php echo __('available_products'); ?>
                <span class="badge"><?php echo $totalProducts; ?> <?php echo __('products'); ?></span>
            </h2>
            <?php if (!empty($sellerId)): ?>
            <div class="filter-active">
                <span><?php echo __('showing_seller_products'); ?></span>
                <a href="<?php echo $base_url; ?>marketplace" class="btn btn-small"><?php echo __('show_all'); ?></a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($products): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="card product-card" onclick="viewProduct(<?php echo $product['product_id']; ?>)" data-product-id="<?php echo $product['product_id']; ?>">
                <div class="product-image">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo $base_url . htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <?php else: ?>
                    <div class="product-image-placeholder">
                        <span class="material-icons">eco</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($product['is_featured']): ?>
                    <span class="product-badge featured"><?php echo __('featured'); ?></span>
                    <?php endif; ?>
                    <?php if ($product['seller_id'] == $userId): ?>
                    <span class="product-badge owner-badge"><?php echo __('your_product'); ?></span>
                    <?php endif; ?>
                    <?php if ($product['quality_grade'] === 'A'): ?>
                    <span class="product-badge premium"><?php echo __('premium'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($product['is_negotiable'])): ?>
                    <span class="product-badge negotiable"><?php echo __('negotiable'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($product['bulk_discount_percent'])): ?>
                    <span class="product-badge bulk-deal"><?php echo __('bulk_deal'); ?></span>
                    <?php endif; ?>
                    
                    <!-- Quick Actions Overlay -->
                    <div class="product-quick-actions">
                        <button class="quick-action-btn wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['product_id']; ?>, this)" title="<?php echo __('add_to_wishlist'); ?>">
                            <span class="material-icons">favorite_border</span>
                        </button>
                        <button class="quick-action-btn compare-btn" onclick="event.stopPropagation(); toggleCompare(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', this)" title="<?php echo __('compare'); ?>">
                            <span class="material-icons">compare_arrows</span>
                        </button>
                        <button class="quick-action-btn share-btn" onclick="event.stopPropagation(); shareProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')" title="<?php echo __('share'); ?>">
                            <span class="material-icons">share</span>
                        </button>
                    </div>
                </div>
                
                <div class="product-info">
                    <div class="product-header">
                        <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                        <span class="badge badge-<?php echo $product['product_type'] === 'crop' ? 'success' : ($product['product_type'] === 'seed' ? 'info' : 'secondary'); ?>">
                            <?php echo ucfirst($product['product_type']); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($product['average_rating']) && $product['average_rating'] > 0): ?>
                    <div class="product-rating-display">
                        <span class="material-icons star-filled">star</span>
                        <span><?php echo format_number($product['average_rating'], 1); ?></span>
                        <span class="review-count">(<?php echo $product['review_count'] ?? 0; ?>)</span>
                    </div>
                    <?php endif; ?>
                    
                    <p class="product-description"><?php echo htmlspecialchars(substr($product['description'] ?? 'Quality farm product', 0, 80)); ?><?php echo strlen($product['description'] ?? '') > 80 ? '...' : ''; ?></p>
                    
                    <div class="product-price">
                        <span class="price">৳<?php echo format_number($product['price'], 2); ?></span>
                        <span class="unit">/ <?php echo htmlspecialchars($product['price_unit'] ?? 'kg'); ?></span>
                    </div>
                    
                    <div class="product-meta">
                        <span><span class="material-icons">inventory</span> <?php echo $product['quantity_available']; ?> <?php echo htmlspecialchars($product['unit'] ?? 'kg'); ?></span>
                        <span><span class="material-icons">location_on</span> <?php echo htmlspecialchars($product['region'] ?? $product['seller_region'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="product-seller">
                        <span class="material-icons">person</span>
                        <span><?php echo htmlspecialchars($product['first_name'] . ' ' . ($product['last_name'] ?? '')); ?></span>
                    </div>
                </div>
                
                <div class="product-actions">
                    <?php if ($product['seller_id'] == $userId): ?>
                    <!-- Owner Actions -->
                    <button class="btn btn-small btn-danger" onclick="event.stopPropagation(); deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')" title="Delete Product">
                        <span class="material-icons">delete</span> <?php echo __('delete'); ?>
                    </button>
                    <button class="btn btn-small btn-secondary" onclick="event.stopPropagation(); editProduct(<?php echo $product['product_id']; ?>)" title="Edit Product">
                        <span class="material-icons">edit</span> <?php echo __('edit'); ?>
                    </button>
                    <?php else: ?>
                    <!-- Buyer Actions -->
                    <button class="btn btn-small" onclick="event.stopPropagation(); contactSeller('<?php echo htmlspecialchars($product['seller_phone'] ?? $product['contact_phone'] ?? ''); ?>')">
                        <span class="material-icons">phone</span> <?php echo __('contact'); ?>
                    </button>
                    <button class="btn btn-small btn-success" onclick="event.stopPropagation(); orderProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', <?php echo $product['price']; ?>, <?php echo $product['quantity_available']; ?>)">
                        <span class="material-icons">shopping_cart</span> <?php echo __('order'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => 1])); ?>" class="pagination-btn" title="<?php echo __('first_page'); ?>">
                    <span class="material-icons">first_page</span>
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page - 1])); ?>" class="pagination-btn" title="<?php echo __('previous_page'); ?>">
                    <span class="material-icons">chevron_left</span>
                </a>
                <?php else: ?>
                <button class="pagination-btn" disabled><span class="material-icons">first_page</span></button>
                <button class="pagination-btn" disabled><span class="material-icons">chevron_left</span></button>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['p' => 1])) . '" class="page-number">1</a>';
                        if ($startPage > 2) echo '<span class="page-ellipsis">...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<span class="page-number active">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['p' => $i])) . '" class="page-number">' . $i . '</a>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span class="page-ellipsis">...</span>';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['p' => $totalPages])) . '" class="page-number">' . $totalPages . '</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page + 1])); ?>" class="pagination-btn" title="<?php echo __('next_page'); ?>">
                    <span class="material-icons">chevron_right</span>
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $totalPages])); ?>" class="pagination-btn" title="<?php echo __('last_page'); ?>">
                    <span class="material-icons">last_page</span>
                </a>
                <?php else: ?>
                <button class="pagination-btn" disabled><span class="material-icons">chevron_right</span></button>
                <button class="pagination-btn" disabled><span class="material-icons">last_page</span></button>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                <span><?php echo __('page'); ?> <?php echo $page; ?> <?php echo __('of'); ?> <?php echo $totalPages; ?></span>
                <span class="pagination-separator">•</span>
                <span><?php echo __('showing'); ?> <?php echo (($page - 1) * 20 + 1); ?>-<?php echo min($page * 20, $totalProducts); ?> <?php echo __('of'); ?> <?php echo $totalProducts; ?> <?php echo __('products'); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <span class="material-icons">inventory_2</span>
            <h3><?php echo __('no_products_found'); ?></h3>
            <p><?php echo __('no_products_match'); ?></p>
            <button class="btn" onclick="document.getElementById('productName').focus()">
                <span class="material-icons">add</span> <?php echo __('list_your_product'); ?>
            </button>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Product Details Modal -->
<div id="productModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">eco</span> <span id="modalProductName"><?php echo __('product_details'); ?></span></h3>
            <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
        </div>
        <div class="modal-body" id="productModalContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">shopping_cart</span> <?php echo __('place_order'); ?></h3>
            <button class="modal-close" onclick="closeModal('orderModal')">&times;</button>
        </div>
        <form id="orderForm">
            <input type="hidden" id="orderProductId" name="productId">
            <div class="modal-body">
                <div class="order-product-info">
                    <h4 id="orderProductName"></h4>
                    <p class="order-price">৳<span id="orderProductPrice"></span> <?php echo __('per_unit'); ?></p>
                </div>
                
                <div class="form-group">
                    <label for="orderQuantity"><?php echo __('quantity'); ?> *</label>
                    <input type="number" id="orderQuantity" name="quantity" min="1" required oninput="calculateTotal()">
                    <small><?php echo __('available'); ?>: <span id="orderAvailable"></span></small>
                </div>
                
                <div class="form-group">
                    <label><?php echo __('total_amount'); ?></label>
                    <div class="order-total">৳<span id="orderTotal">0.00</span></div>
                </div>
                
                <div class="form-group">
                    <label for="deliveryAddress"><?php echo __('delivery_address'); ?> *</label>
                    <textarea id="deliveryAddress" name="deliveryAddress" rows="2" required placeholder="<?php echo __('enter_delivery_address'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="paymentMethod"><?php echo __('payment_method'); ?></label>
                    <select id="paymentMethod" name="paymentMethod">
                        <option value="cash"><?php echo __('cash_on_delivery'); ?></option>
                        <option value="bkash"><?php echo __('bkash'); ?></option>
                        <option value="nagad"><?php echo __('nagad'); ?></option>
                        <option value="bank"><?php echo __('bank_transfer'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="orderNotes"><?php echo __('notes_optional'); ?></label>
                    <textarea id="orderNotes" name="notes" rows="2" placeholder="<?php echo __('special_instructions'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('orderModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-success">
                    <span class="material-icons">check</span> <?php echo __('confirm_order'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- My Products Modal -->
<div id="myProductsModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><span class="material-icons">inventory_2</span> <?php echo __('my_products'); ?></h3>
            <button class="modal-close" onclick="closeModal('myProductsModal')">&times;</button>
        </div>
        <div class="modal-body" id="myProductsContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading_products'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- My Orders Modal -->
<div id="myOrdersModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><span class="material-icons">shopping_bag</span> <?php echo __('my_orders'); ?></h3>
            <button class="modal-close" onclick="closeModal('myOrdersModal')">&times;</button>
        </div>
        <div class="modal-body" id="myOrdersContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading_orders'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Seller Orders Modal -->
<div id="sellerOrdersModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><span class="material-icons">local_shipping</span> <?php echo __('orders_to_fulfill'); ?></h3>
            <button class="modal-close" onclick="closeModal('sellerOrdersModal')">&times;</button>
        </div>
        <div class="modal-body" id="sellerOrdersContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading_orders'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Market Prices Modal -->
<div id="marketPricesModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">trending_up</span> <?php echo __('current_market_prices'); ?></h3>
            <button class="modal-close" onclick="closeModal('marketPricesModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="market-prices-filters">
                <input type="text" id="priceSearchInput" placeholder="<?php echo __('search_crop'); ?>" oninput="filterPrices()">
                <select id="priceRegionFilter" onchange="filterPrices()">
                    <option value=""><?php echo __('all_regions'); ?></option>
                    <?php foreach ($regions as $r): ?>
                    <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="table-container">
                <table class="data-table" id="marketPricesTable">
                    <thead>
                        <tr>
                            <th><?php echo __('crop'); ?></th>
                            <th><?php echo __('region'); ?></th>
                            <th><?php echo __('price_kg'); ?></th>
                            <th><?php echo __('min'); ?></th>
                            <th><?php echo __('max'); ?></th>
                            <th><?php echo __('demand'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marketPrices as $mp): ?>
                        <tr data-crop="<?php echo strtolower($mp['crop_name']); ?>" data-region="<?php echo $mp['region']; ?>">
                            <td><strong><?php echo htmlspecialchars($mp['crop_name']); ?></strong><br><small><?php echo htmlspecialchars($mp['variety'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($mp['region']); ?></td>
                            <td class="price-cell">৳<?php echo format_number($mp['price_per_unit'], 2); ?></td>
                            <td>৳<?php echo format_number($mp['min_price'] ?? 0, 2); ?></td>
                            <td>৳<?php echo format_number($mp['max_price'] ?? 0, 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $mp['demand_level'] === 'high' ? 'success' : ($mp['demand_level'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                    <?php echo ucfirst($mp['demand_level']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Write Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">rate_review</span> <?php echo __('write_review'); ?></h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form id="reviewForm" enctype="multipart/form-data">
            <input type="hidden" id="reviewProductId" name="productId">
            <input type="hidden" id="reviewRating" name="rating" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo __('your_rating'); ?> *</label>
                    <div class="rating-input" id="ratingStars">
                        <span class="material-icons star-select" onclick="setRating(1)">star_border</span>
                        <span class="material-icons star-select" onclick="setRating(2)">star_border</span>
                        <span class="material-icons star-select" onclick="setRating(3)">star_border</span>
                        <span class="material-icons star-select" onclick="setRating(4)">star_border</span>
                        <span class="material-icons star-select" onclick="setRating(5)">star_border</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reviewText"><?php echo __('your_review'); ?></label>
                    <textarea id="reviewText" name="reviewText" rows="4" placeholder="<?php echo __('share_experience_product'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="reviewImages"><?php echo __('add_photos_optional'); ?></label>
                    <input type="file" id="reviewImages" name="reviewImages[]" accept="image/*" multiple>
                    <small class="form-text"><?php echo __('upload_5_images'); ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn">
                    <span class="material-icons">send</span> <?php echo __('submit_review'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Wishlist Modal -->
<div id="wishlistModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">favorite</span> <?php echo __('my_wishlist'); ?></h3>
            <button class="modal-close" onclick="closeModal('wishlistModal')">&times;</button>
        </div>
        <div class="modal-body" id="wishlistContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading_wishlist'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Offers Modal -->
<div id="offersModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><span class="material-icons">local_offer</span> <?php echo __('offers_negotiations'); ?></h3>
            <button class="modal-close" onclick="closeModal('offersModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="offers-tabs">
                <button class="tab-btn active" onclick="switchOfferTab('received')">
                    <span class="material-icons">inbox</span> <?php echo __('received_offers'); ?>
                </button>
                <button class="tab-btn" onclick="switchOfferTab('sent')">
                    <span class="material-icons">send</span> <?php echo __('my_offers'); ?>
                </button>
            </div>
            <div id="offersContent">
                <div class="loading-spinner">
                    <span class="material-icons spinning">sync</span>
                    <p><?php echo __('loading_offers'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Make Offer Modal -->
<div id="makeOfferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">local_offer</span> <?php echo __('make_offer'); ?></h3>
            <button class="modal-close" onclick="closeModal('makeOfferModal')">&times;</button>
        </div>
        <form id="offerForm">
            <input type="hidden" id="offerProductId" name="productId">
            <div class="modal-body">
                <div class="offer-product-info">
                    <h4 id="offerProductName"></h4>
                    <p class="current-price"><?php echo __('listed_price'); ?>: ৳<span id="offerListedPrice"></span></p>
                </div>
                
                <div class="form-group">
                    <label for="offerPrice"><?php echo __('your_offer_price'); ?> (৳) *</label>
                    <input type="number" id="offerPrice" name="offerPrice" min="1" step="0.01" required>
                    <small><?php echo __('enter_best_offer'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="offerQuantity"><?php echo __('quantity'); ?> *</label>
                    <input type="number" id="offerQuantity" name="quantity" min="1" required>
                    <small><?php echo __('how_many_units'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="offerMessage"><?php echo __('message_to_seller'); ?></label>
                    <textarea id="offerMessage" name="message" rows="3" placeholder="<?php echo __('explain_offer'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('makeOfferModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn">
                    <span class="material-icons">send</span> <?php echo __('send_offer'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Compare Modal -->
<div id="compareModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><span class="material-icons">compare_arrows</span> <?php echo __('compare_products'); ?></h3>
            <button class="modal-close" onclick="closeModal('compareModal')">&times;</button>
        </div>
        <div class="modal-body" id="compareContent">
            <div class="compare-empty">
                <span class="material-icons">compare_arrows</span>
                <p><?php echo __('no_products_compare'); ?></p>
                <small><?php echo __('add_products_compare'); ?></small>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="clearCompareList()">
                <span class="material-icons">clear_all</span> <?php echo __('clear_all'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Report Product Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">flag</span> <?php echo __('report_product'); ?></h3>
            <button class="modal-close" onclick="closeModal('reportModal')">&times;</button>
        </div>
        <form id="reportForm">
            <input type="hidden" id="reportProductId" name="productId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="reportReason"><?php echo __('reason_for_report'); ?> *</label>
                    <select id="reportReason" name="reason" required>
                        <option value=""><?php echo __('select_reason'); ?></option>
                        <option value="misleading"><?php echo __('misleading_info'); ?></option>
                        <option value="fake"><?php echo __('fake_product'); ?></option>
                        <option value="scam"><?php echo __('suspected_scam'); ?></option>
                        <option value="inappropriate"><?php echo __('inappropriate_content'); ?></option>
                        <option value="wrong_category"><?php echo __('wrong_category'); ?></option>
                        <option value="duplicate"><?php echo __('duplicate_listing'); ?></option>
                        <option value="other"><?php echo __('other'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reportDescription"><?php echo __('description'); ?> *</label>
                    <textarea id="reportDescription" name="description" rows="4" required placeholder="<?php echo __('describe_issue'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reportModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-danger">
                    <span class="material-icons">flag</span> <?php echo __('submit_report'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Share Product Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">share</span> <?php echo __('share_product'); ?></h3>
            <button class="modal-close" onclick="closeModal('shareModal')">&times;</button>
        </div>
        <div class="modal-body">
            <h4 id="shareProductName" class="share-product-title"></h4>
            <div class="share-options">
                <button class="share-btn whatsapp" onclick="shareToWhatsApp()">
                    <span class="material-icons">chat</span>
                    WhatsApp
                </button>
                <button class="share-btn facebook" onclick="shareToFacebook()">
                    <span class="material-icons">facebook</span>
                    Facebook
                </button>
                <button class="share-btn twitter" onclick="shareToTwitter()">
                    <span class="material-icons">tag</span>
                    Twitter
                </button>
                <button class="share-btn email" onclick="shareViaEmail()">
                    <span class="material-icons">email</span>
                    Email
                </button>
            </div>
            <div class="form-group share-link-group">
                <label><?php echo __('or_copy_link'); ?>:</label>
                <div class="copy-link-wrapper">
                    <input type="text" id="shareLink" readonly>
                    <button class="btn btn-small" onclick="copyShareLink()">
                        <span class="material-icons">content_copy</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recently Viewed Modal -->
<div id="recentlyViewedModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">history</span> <?php echo __('recently_viewed'); ?></h3>
            <button class="modal-close" onclick="closeModal('recentlyViewedModal')">&times;</button>
        </div>
        <div class="modal-body" id="recentlyViewedContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading'); ?>...</p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">edit</span> <?php echo __('edit_product'); ?></h3>
            <button class="modal-close" onclick="closeModal('editProductModal')">&times;</button>
        </div>
        <form id="editProductForm" enctype="multipart/form-data">
            <input type="hidden" id="editProductId" name="product_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editProductName"><?php echo __('product_name'); ?> *</label>
                        <input type="text" id="editProductName" name="product_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editProductType"><?php echo __('product_type'); ?> *</label>
                        <select id="editProductType" name="product_type" required>
                            <option value="crop"><?php echo __('crop_produce'); ?></option>
                            <option value="seed"><?php echo __('seeds'); ?></option>
                            <option value="equipment"><?php echo __('equipment_tools'); ?></option>
                            <option value="fertilizer"><?php echo __('fertilizer'); ?></option>
                            <option value="other"><?php echo __('other'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editDescription"><?php echo __('description'); ?> *</label>
                    <textarea id="editDescription" name="description" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editPrice"><?php echo __('price'); ?> (৳) *</label>
                        <input type="number" id="editPrice" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editPriceUnit"><?php echo __('price_unit'); ?></label>
                        <input type="text" id="editPriceUnit" name="price_unit" placeholder="<?php echo __('eg_kg_piece'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editQuantity"><?php echo __('quantity_available'); ?> *</label>
                        <input type="number" id="editQuantity" name="quantity_available" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editUnit"><?php echo __('unit'); ?></label>
                        <input type="text" id="editUnit" name="unit" placeholder="<?php echo __('eg_kg_ton'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editCategory"><?php echo __('category'); ?></label>
                        <input type="text" id="editCategory" name="category" placeholder="<?php echo __('eg_vegetables_grains'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="editRegion"><?php echo __('region'); ?> *</label>
                        <select id="editRegion" name="region" required>
                            <option value=""><?php echo __('select_region'); ?></option>
                            <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region; ?>"><?php echo $region; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editQualityGrade"><?php echo __('quality_grade'); ?></label>
                        <select id="editQualityGrade" name="quality_grade">
                            <option value=""><?php echo __('select_grade'); ?></option>
                            <option value="A"><?php echo __('grade_a_premium'); ?></option>
                            <option value="B"><?php echo __('grade_b_standard'); ?></option>
                            <option value="C"><?php echo __('grade_c_economy'); ?></option>
                        </select>
                    </div>
                    <div class="form-group checkbox-group">
                        <label class="checkbox-inline">
                            <input type="checkbox" id="editIsNegotiable" name="is_negotiable" value="1">
                            <span><?php echo __('price_negotiable'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editImage"><?php echo __('product_image'); ?></label>
                    <input type="file" id="editImage" name="image" accept="image/*">
                    <div id="editCurrentImage" style="margin-top: 0.5rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-success">
                    <span class="material-icons">save</span> <?php echo __('update_product'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="marketplace-fab-container">
    <button class="fab fab-wishlist" onclick="openModal('wishlistModal'); loadWishlist();" title="<?php echo __('my_wishlist'); ?>">
        <span class="material-icons">favorite</span>
        <span class="fab-badge" id="wishlistBadge"><?php echo $wishlistCount; ?></span>
    </button>
    <button class="fab fab-compare" onclick="openModal('compareModal'); loadCompareList();" title="<?php echo __('compare_products'); ?>">
        <span class="material-icons">compare_arrows</span>
        <span class="fab-badge" id="compareBadge">0</span>
    </button>
    <button class="fab fab-history" onclick="openModal('recentlyViewedModal'); loadRecentlyViewed();" title="<?php echo __('recently_viewed'); ?>">
        <span class="material-icons">history</span>
    </button>
</div>

<script>
// Define baseUrl only if not already defined
if (typeof baseUrl === 'undefined') {
    var baseUrl = '<?php echo $base_url; ?>';
}
const currentUserId = <?php echo $userId; ?>;
let currentProductPrice = 0;
let currentViewingProductId = 0;
let compareList = JSON.parse(localStorage.getItem('compareProducts') || '[]');

// JavaScript translations object
const jsTranslations = {
    adding: '<?php echo addslashes(__('adding')); ?>',
    loading: '<?php echo addslashes(__('loading')); ?>',
    deleting: '<?php echo addslashes(__('deleting')); ?>',
    updating: '<?php echo addslashes(__('updating')); ?>',
    processing: '<?php echo addslashes(__('processing')); ?>',
    network_error: '<?php echo addslashes(__('network_error')); ?>',
    failed_add_product: '<?php echo addslashes(__('failed_add_product')); ?>',
    failed_place_order: '<?php echo addslashes(__('failed_place_order')); ?>',
    failed_update_product: '<?php echo addslashes(__('failed_update_product')); ?>',
    failed_delete_product: '<?php echo addslashes(__('failed_delete_product')); ?>',
    product_deleted: '<?php echo addslashes(__('product_deleted')); ?>',
    product_updated: '<?php echo addslashes(__('product_updated')); ?>',
    order_placed: '<?php echo addslashes(__('order_placed')); ?>',
    contact_not_available: '<?php echo addslashes(__('contact_not_available')); ?>',
    error_updating: '<?php echo addslashes(__('error_updating')); ?>',
    error_deleting: '<?php echo addslashes(__('error_deleting')); ?>',
    confirm_delete_product: '<?php echo addslashes(__('confirm_delete_product')); ?>',
    total: '<?php echo addslashes(__('total')); ?>',
    success: '<?php echo addslashes(__('success')); ?>',
    error: '<?php echo addslashes(__('error')); ?>',
    failed_load_product: '<?php echo addslashes(__('failed_load_product')); ?>',
    failed_cancel_order: '<?php echo addslashes(__('failed_cancel_order')); ?>',
    failed_update_order: '<?php echo addslashes(__('failed_update_order')); ?>',
    copied_to_clipboard: '<?php echo addslashes(__('copied_to_clipboard')); ?>',
    added_to_wishlist: '<?php echo addslashes(__('added_to_wishlist')); ?>',
    removed_from_wishlist: '<?php echo addslashes(__('removed_from_wishlist')); ?>',
    added_to_compare: '<?php echo addslashes(__('added_to_compare')); ?>',
    removed_from_compare: '<?php echo addslashes(__('removed_from_compare')); ?>',
    max_compare_items: '<?php echo addslashes(__('max_compare_items')); ?>',
    offer_sent: '<?php echo addslashes(__('offer_sent')); ?>',
    failed_send_offer: '<?php echo addslashes(__('failed_send_offer')); ?>',
    report_submitted: '<?php echo addslashes(__('report_submitted')); ?>',
    failed_submit_report: '<?php echo addslashes(__('failed_submit_report')); ?>',
    review_submitted: '<?php echo addslashes(__('review_submitted')); ?>',
    failed_submit_review: '<?php echo addslashes(__('failed_submit_review')); ?>',
    no_products_listed: '<?php echo addslashes(__('no_products_listed')); ?>',
    failed_load_products: '<?php echo addslashes(__('failed_load_products')); ?>',
    available: '<?php echo addslashes(__('available')); ?>',
    no_orders_yet: '<?php echo addslashes(__('no_orders_yet')); ?>',
    no_orders_to_fulfill: '<?php echo addslashes(__('no_orders_to_fulfill')); ?>',
    confirm_btn: '<?php echo addslashes(__('confirm')); ?>',
    reject: '<?php echo addslashes(__('reject')); ?>',
    mark_delivered: '<?php echo addslashes(__('mark_delivered')); ?>',
    ordered: '<?php echo addslashes(__('ordered')); ?>',
    buyer: '<?php echo addslashes(__('buyer')); ?>',
    phone: '<?php echo addslashes(__('phone')); ?>',
    address: '<?php echo addslashes(__('delivery_address')); ?>'
};

// Update badge visibility function
function updateBadge(badgeId, count) {
    const badge = document.getElementById(badgeId);
    if (badge) {
        badge.textContent = count;
        if (count <= 0) {
            badge.classList.add('hidden');
        } else {
            badge.classList.remove('hidden');
        }
    }
}

// Update compare badge on load
updateBadge('compareBadge', compareList.length);

// Update wishlist badge on load
const initialWishlistCount = <?php echo $wishlistCount; ?>;
updateBadge('wishlistBadge', initialWishlistCount);

// Check for product query parameter on page load and open product modal
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('product');
    if (productId && !isNaN(productId)) {
        // Use setTimeout to ensure the page and viewProduct function are fully loaded
        setTimeout(() => {
            if (typeof viewProduct === 'function') {
                viewProduct(parseInt(productId));
            }
        }, 100);
    }
})();

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    
    // Load data for specific modals
    if (modalId === 'myProductsModal') loadMyProducts();
    if (modalId === 'myOrdersModal') loadMyOrders();
    if (modalId === 'sellerOrdersModal') loadSellerOrders();
    if (modalId === 'wishlistModal') loadWishlist();
    if (modalId === 'offersModal') loadOffers('received');
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});

// Add Product Form
document.getElementById('addProductForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_product');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> ' + jsTranslations.adding;
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            this.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || jsTranslations.failed_add_product, 'error');
        }
    })
    .catch(error => {
        showNotification(jsTranslations.network_error, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// View Product
function viewProduct(productId) {
    openModal('productModal');
    document.getElementById('productModalContent').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span><p>' + jsTranslations.loading + '...</p></div>';
    currentViewingProductId = productId;
    
    // Increment views
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=increment_views&productId=' + productId
    });
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_product_details&productId=' + productId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.product;
            const profileUrl = p.seller_role === 'officer' ? 'officer-profile-view' : 'farmer-profile-view';
            document.getElementById('modalProductName').textContent = p.product_name;
            document.getElementById('productModalContent').innerHTML = `
                <div class="product-detail-grid">
                    <div class="product-detail-image">
                        ${p.image_url ? `<img src="${baseUrl}${p.image_url}" alt="${p.product_name}">` : `<div class="product-image-placeholder large"><span class="material-icons">eco</span></div>`}
                        ${p.images ? `<div class="product-gallery">${JSON.parse(p.images || '[]').map(img => `<img src="${baseUrl}${img}" onclick="showFullImage('${baseUrl}${img}')">`).join('')}</div>` : ''}
                    </div>
                    <div class="product-detail-info">
                        <div class="product-detail-header">
                            <span class="badge badge-${p.product_type === 'crop' ? 'success' : 'info'}">${p.product_type}</span>
                            ${p.quality_grade ? `<span class="badge badge-warning">Grade ${p.quality_grade}</span>` : ''}
                            ${p.average_rating > 0 ? `<span class="rating-badge"><span class="material-icons">star</span> ${parseFloat(p.average_rating).toFixed(1)} (${p.review_count || 0})</span>` : ''}
                        </div>
                        <div class="product-detail-price">
                            <span class="price-large">৳${parseFloat(p.price).toFixed(2)}</span>
                            <span class="price-unit">/ ${p.price_unit || 'kg'}</span>
                        </div>
                        <div class="product-detail-meta">
                            <p><span class="material-icons">inventory</span> <strong>Available:</strong> ${p.quantity_available} ${p.unit || 'kg'}</p>
                            <p><span class="material-icons">category</span> <strong>Category:</strong> ${p.category || 'N/A'}</p>
                            <p><span class="material-icons">location_on</span> <strong>Location:</strong> ${p.location || p.region || 'N/A'}</p>
                            <p><span class="material-icons">visibility</span> <strong>Views:</strong> ${p.views || 0}</p>
                        </div>
                        <div class="product-detail-description">
                            <h4>Description</h4>
                            <p>${p.description || 'No description provided'}</p>
                        </div>
                        <div class="product-detail-seller">
                            <h4><span class="material-icons">person</span> Seller Information</h4>
                            <div class="seller-info-card">
                                <div class="seller-avatar">
                                    ${p.seller_image ? `<img src="${baseUrl}public/${p.seller_image}" alt="">` : `<span class="material-icons">person</span>`}
                                </div>
                                <div class="seller-details">
                                    <p><strong>${p.first_name} ${p.last_name || ''}</strong></p>
                                    <span class="badge badge-${p.seller_role === 'officer' ? 'info' : 'success'}">${p.seller_role || 'farmer'}</span>
                                    <p><span class="material-icons">location_on</span> ${p.seller_region || 'N/A'}, ${p.seller_district || ''}</p>
                                    ${p.seller_phone ? `<p><span class="material-icons">phone</span> <a href="tel:${p.seller_phone}">${p.seller_phone}</a></p>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="product-detail-actions">
                            ${p.seller_id == <?php echo $userId; ?> ? `
                                <button class="btn btn-danger" onclick="handleDeleteFromModal(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}')">
                                    <span class="material-icons">delete</span> Delete Product
                                </button>
                                <button class="btn btn-secondary" onclick="handleEditFromModal(${p.product_id})">
                                    <span class="material-icons">edit</span> Edit Product
                                </button>
                            ` : `
                                <button class="btn btn-success" onclick="closeModal('productModal'); orderProduct(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}', ${p.price}, ${p.quantity_available})">
                                    <span class="material-icons">shopping_cart</span> Order Now
                                </button>
                                ${p.seller_phone ? `<a href="tel:${p.seller_phone}" class="btn"><span class="material-icons">phone</span> Call Seller</a>` : ''}
                                <a href="${baseUrl}${profileUrl}?id=${p.seller_id}" class="btn btn-secondary">
                                    <span class="material-icons">person</span> View Profile
                                </a>
                            `}
                        </div>
                        ${p.seller_id != <?php echo $userId; ?> ? `
                        <div class="product-detail-actions secondary-actions">
                            <button class="btn btn-outline wishlist-action" onclick="toggleWishlist(${p.product_id})">
                                <span class="material-icons">favorite_border</span> Add to Wishlist
                            </button>
                            ${p.is_negotiable == 1 ? `<button class="btn btn-outline offer-action" onclick="openMakeOfferModal(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}', ${p.price})">
                                <span class="material-icons">local_offer</span> Make Offer
                            </button>` : ''}
                            <button class="btn btn-outline compare-action" onclick="toggleCompare(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}')">
                                <span class="material-icons">compare_arrows</span> Compare
                            </button>
                            <button class="btn btn-outline share-action" onclick="shareProduct(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}')">
                                <span class="material-icons">share</span> Share
                            </button>
                            <button class="btn btn-outline report-action" onclick="openReportModal(${p.product_id})">
                                <span class="material-icons">flag</span> Report
                            </button>
                        </div>
                        ` : ''}
                        ${p.bulk_discount_percent && p.bulk_min_quantity ? `
                        <div class="bulk-discount-info">
                            <span class="material-icons">discount</span>
                            <strong>${p.bulk_discount_percent}% off</strong> when you order ${p.bulk_min_quantity}+ units!
                        </div>` : ''}
                        ${p.min_order_quantity && p.min_order_quantity > 1 ? `
                        <div class="min-order-info">
                            <span class="material-icons">info</span>
                            Minimum order: ${p.min_order_quantity} ${p.unit || 'units'}
                        </div>` : ''}
                    </div>
                </div>
                
                <!-- Reviews Section -->
                <div class="reviews-section" id="reviewsSection" data-product-id="${p.product_id}">
                    <div class="reviews-header">
                        <h4><span class="material-icons">rate_review</span> Reviews & Ratings</h4>
                        <button class="btn btn-small" onclick="openReviewForm(${p.product_id})">
                            <span class="material-icons">add</span> Write Review
                        </button>
                    </div>
                    <div id="reviewsContainer">
                        <div class="loading-spinner"><span class="material-icons spinning">sync</span></div>
                    </div>
                </div>
                
                ${data.related && data.related.length > 0 ? `
                <div class="related-products">
                    <h4>Related Products</h4>
                    <div class="related-products-grid">
                        ${data.related.map(r => `
                            <div class="related-product-card" onclick="viewProduct(${r.product_id})">
                                ${r.image_url ? `<img src="${baseUrl}${r.image_url}" alt="${r.product_name}">` : `<div class="related-placeholder"><span class="material-icons">eco</span></div>`}
                                <div class="related-info">
                                    <h5>${r.product_name}</h5>
                                    <p>৳${parseFloat(r.price).toFixed(2)}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            `;
            
            // Load reviews
            loadReviews(p.product_id);
        } else {
            document.getElementById('productModalContent').innerHTML = '<p class="text-danger text-center">Failed to load product details</p>';
        }
    })
    .catch(error => {
        document.getElementById('productModalContent').innerHTML = '<p class="text-danger text-center">Network error</p>';
    });
}

// Order Product
function orderProduct(productId, productName, price, available) {
    document.getElementById('orderProductId').value = productId;
    document.getElementById('orderProductName').textContent = productName;
    document.getElementById('orderProductPrice').textContent = parseFloat(price).toFixed(2);
    document.getElementById('orderAvailable').textContent = available;
    document.getElementById('orderQuantity').max = available;
    document.getElementById('orderQuantity').value = 1;
    currentProductPrice = parseFloat(price);
    calculateTotal();
    openModal('orderModal');
}

function calculateTotal() {
    const quantity = parseInt(document.getElementById('orderQuantity').value) || 0;
    const total = quantity * currentProductPrice;
    document.getElementById('orderTotal').textContent = total.toFixed(2);
}

// Order Form Submit
document.getElementById('orderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'place_order');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> ' + jsTranslations.processing;
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message + ' ' + jsTranslations.total + ': ৳' + data.total_price, 'success');
            closeModal('orderModal');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification(data.message || jsTranslations.failed_place_order, 'error');
        }
    })
    .catch(error => {
        showNotification(jsTranslations.network_error, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">check</span> <?php echo __('confirm_order'); ?>';
    });
});

// Edit Product Form Handler
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_product');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> ' + jsTranslations.updating;
    
    fetch(baseUrl + 'ajax/update-product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || jsTranslations.product_updated, 'success');
            closeModal('editProductModal');
            // Refresh the product display
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || jsTranslations.failed_update_product, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(jsTranslations.error_updating, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">check</span> <?php echo __('update_product'); ?>';
    });
});

// Load My Products
function loadMyProducts() {
    document.getElementById('myProductsContent').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_my_products')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.products.length > 0) {
            let html = '<div class="my-products-list">';
            data.products.forEach(p => {
                html += `
                    <div class="my-product-item">
                        <div class="my-product-image">
                            ${p.image_url ? `<img src="${baseUrl}${p.image_url}" alt="${p.product_name}">` : `<div class="product-placeholder-small"><span class="material-icons">eco</span></div>`}
                        </div>
                        <div class="my-product-info">
                            <h4>${p.product_name}</h4>
                            <p class="my-product-price">৳${parseFloat(p.price).toFixed(2)} / ${p.price_unit || 'kg'}</p>
                            <p><span class="material-icons">inventory</span> ${p.quantity_available} ${jsTranslations.available}</p>
                            <span class="badge badge-${p.status === 'available' ? 'success' : (p.status === 'sold' ? 'danger' : 'secondary')}">${p.status}</span>
                        </div>
                        <div class="my-product-actions">
                            <button class="btn btn-small" onclick="editProduct(${p.product_id})">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="btn btn-small btn-danger" onclick="deleteProduct(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}')">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('myProductsContent').innerHTML = html;
        } else {
            document.getElementById('myProductsContent').innerHTML = '<div class="empty-state"><span class="material-icons">inventory_2</span><p>' + jsTranslations.no_products_listed + '</p></div>';
        }
    })
    .catch(error => {
        document.getElementById('myProductsContent').innerHTML = '<p class="text-danger text-center">' + jsTranslations.failed_load_products + '</p>';
    });
}

// Edit Product
function editProduct(productId) {
    fetch(baseUrl + 'ajax/marketplace.php?action=get_product_details&productId=' + productId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.product;
            document.getElementById('editProductId').value = p.product_id;
            document.getElementById('editProductName').value = p.product_name;
            document.getElementById('editProductType').value = p.product_type;
            document.getElementById('editDescription').value = p.description || '';
            document.getElementById('editPrice').value = p.price;
            document.getElementById('editQuantity').value = p.quantity_available;
            document.getElementById('editRegion').value = p.region || '';
            document.getElementById('editStatus').value = p.status;
            closeModal('myProductsModal');
            openModal('editProductModal');
        }
    });
}

// Delete Product
function deleteProduct(productId, productName) {
    if (!confirm(jsTranslations.confirm_delete_product + ` "${productName}"?`)) return;
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete_product&productId=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadMyProducts();
        } else {
            showNotification(data.message || jsTranslations.failed_delete_product, 'error');
        }
    });
}

// Load My Orders
function loadMyOrders() {
    document.getElementById('myOrdersContent').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_my_orders')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.orders.length > 0) {
            let html = '<div class="orders-list">';
            data.orders.forEach(o => {
                html += `
                    <div class="order-item">
                        <div class="order-image">
                            ${o.image_url ? `<img src="${baseUrl}${o.image_url}" alt="">` : `<div class="product-placeholder-small"><span class="material-icons">eco</span></div>`}
                        </div>
                        <div class="order-info">
                            <h4>${o.product_name}</h4>
                            <p><?php echo __('quantity'); ?>: ${o.quantity} | ${jsTranslations.total}: ৳${parseFloat(o.total_price).toFixed(2)}</p>
                            <p><small><?php echo __('seller'); ?>: ${o.seller_first} ${o.seller_last || ''}</small></p>
                            <p><small>${jsTranslations.ordered}: ${new Date(o.created_at).toLocaleDateString()}</small></p>
                        </div>
                        <div class="order-status">
                            <span class="badge badge-${o.order_status === 'delivered' ? 'success' : (o.order_status === 'cancelled' ? 'danger' : (o.order_status === 'confirmed' ? 'info' : 'warning'))}">${o.order_status}</span>
                            <span class="badge badge-${o.payment_status === 'paid' ? 'success' : 'secondary'}">${o.payment_status}</span>
                        </div>
                        <div class="order-actions">
                            ${o.order_status === 'pending' ? `<button class="btn btn-small btn-danger" onclick="cancelOrder(${o.order_id})"><?php echo __('cancel'); ?></button>` : ''}
                            ${o.seller_phone ? `<a href="tel:${o.seller_phone}" class="btn btn-small"><span class="material-icons">phone</span></a>` : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('myOrdersContent').innerHTML = html;
        } else {
            document.getElementById('myOrdersContent').innerHTML = '<div class="empty-state"><span class="material-icons">shopping_bag</span><p>' + jsTranslations.no_orders_yet + '</p></div>';
        }
    });
}

// Cancel Order
function cancelOrder(orderId) {
    if (!confirm('<?php echo addslashes(__('confirm_cancel_order')); ?>')) return;
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=cancel_order&orderId=' + orderId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadMyOrders();
        } else {
            showNotification(data.message || jsTranslations.failed_cancel_order, 'error');
        }
    });
}

// Load Seller Orders
function loadSellerOrders() {
    document.getElementById('sellerOrdersContent').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_seller_orders')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.orders.length > 0) {
            let html = '<div class="orders-list">';
            data.orders.forEach(o => {
                html += `
                    <div class="order-item seller-order">
                        <div class="order-image">
                            ${o.image_url ? `<img src="${baseUrl}${o.image_url}" alt="">` : `<div class="product-placeholder-small"><span class="material-icons">eco</span></div>`}
                        </div>
                        <div class="order-info">
                            <h4>${o.product_name}</h4>
                            <p><?php echo __('quantity'); ?>: ${o.quantity} | ${jsTranslations.total}: ৳${parseFloat(o.total_price).toFixed(2)}</p>
                            <p><strong>${jsTranslations.buyer}:</strong> ${o.buyer_first} ${o.buyer_last || ''}</p>
                            <p><strong><?php echo __('phone'); ?>:</strong> <a href="tel:${o.buyer_phone}">${o.buyer_phone}</a></p>
                            <p><strong>${jsTranslations.address}:</strong> ${o.delivery_address || 'N/A'}</p>
                            <p><small>${jsTranslations.ordered}: ${new Date(o.created_at).toLocaleDateString()}</small></p>
                        </div>
                        <div class="order-status">
                            <span class="badge badge-${o.order_status === 'delivered' ? 'success' : (o.order_status === 'cancelled' ? 'danger' : (o.order_status === 'confirmed' ? 'info' : 'warning'))}">${o.order_status}</span>
                        </div>
                        <div class="order-actions seller-actions">
                            ${o.order_status === 'pending' ? `
                                <button class="btn btn-small btn-success" onclick="updateOrderStatus(${o.order_id}, 'confirmed')">${jsTranslations.confirm_btn}</button>
                                <button class="btn btn-small btn-danger" onclick="updateOrderStatus(${o.order_id}, 'cancelled')">${jsTranslations.reject}</button>
                            ` : ''}
                            ${o.order_status === 'confirmed' ? `
                                <button class="btn btn-small btn-success" onclick="updateOrderStatus(${o.order_id}, 'delivered')">${jsTranslations.mark_delivered}</button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('sellerOrdersContent').innerHTML = html;
        } else {
            document.getElementById('sellerOrdersContent').innerHTML = '<div class="empty-state"><span class="material-icons">local_shipping</span><p>' + jsTranslations.no_orders_to_fulfill + '</p></div>';
        }
    });
}

// Update Order Status
function updateOrderStatus(orderId, status) {
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_order_status&orderId=' + orderId + '&orderStatus=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadSellerOrders();
        } else {
            showNotification(data.message || jsTranslations.failed_update_order, 'error');
        }
    });
}

// Contact Seller
function contactSeller(phone) {
    if (phone) {
        window.location.href = 'tel:' + phone;
    } else {
        showNotification(jsTranslations.contact_not_available, 'error');
    }
}

// Delete Product
function deleteProduct(productId, productName) {
    if (!confirm(jsTranslations.confirm_delete_product + ` "${productName}"?`)) {
        return;
    }
    
    showNotification(jsTranslations.deleting, 'info');
    
    fetch('<?php echo $base_url; ?>ajax/delete-product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || jsTranslations.product_deleted, 'success');
            // Remove product card from display
            const productCard = document.querySelector(`[data-product-id="${productId}"]`);
            if (productCard) {
                productCard.style.opacity = '0';
                productCard.style.transform = 'scale(0.9)';
                setTimeout(() => productCard.remove(), 300);
            }
            // Reload page after short delay
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || jsTranslations.failed_delete_product, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(jsTranslations.error_deleting, 'error');
    });
}

// Edit Product
function editProduct(productId) {
    // Fetch product details and open edit modal
    fetch(baseUrl + 'ajax/marketplace.php?action=get_product_details&productId=' + productId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.product;
            // Populate form fields
            document.getElementById('editProductId').value = p.product_id;
            document.getElementById('editProductName').value = p.product_name;
            document.getElementById('editProductType').value = p.product_type;
            document.getElementById('editDescription').value = p.description || '';
            document.getElementById('editPrice').value = p.price;
            document.getElementById('editPriceUnit').value = p.price_unit || '';
            document.getElementById('editQuantity').value = p.quantity_available;
            document.getElementById('editUnit').value = p.unit || '';
            document.getElementById('editCategory').value = p.category || '';
            document.getElementById('editRegion').value = p.region || '';
            document.getElementById('editQualityGrade').value = p.quality_grade || '';
            document.getElementById('editIsNegotiable').checked = p.is_negotiable == 1;
            
            // Show current image
            if (p.image_url) {
                document.getElementById('editCurrentImage').innerHTML = `
                    <img src="${baseUrl}${p.image_url}" alt="Current Image" style="max-width: 200px; border-radius: 8px;">
                    <p style="color: #666; font-size: 0.85rem; margin-top: 0.5rem;">Upload new image to replace</p>
                `;
            } else {
                document.getElementById('editCurrentImage').innerHTML = '';
            }
            
            // Open modal
            openModal('editProductModal');
        } else {
            showNotification(jsTranslations.failed_load_product, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(jsTranslations.failed_load_product, 'error');
    });
}

// Wrapper functions for modal actions
function handleDeleteFromModal(productId, productName) {
    closeModal('productModal');
    setTimeout(() => {
        deleteProduct(productId, productName);
    }, 300);
}

function handleEditFromModal(productId) {
    closeModal('productModal');
    setTimeout(() => {
        editProduct(productId);
    }, 300);
}


// Filter Market Prices
function filterPrices() {
    const search = document.getElementById('priceSearchInput').value.toLowerCase();
    const region = document.getElementById('priceRegionFilter').value;
    
    document.querySelectorAll('#marketPricesTable tbody tr').forEach(row => {
        const crop = row.dataset.crop;
        const rowRegion = row.dataset.region;
        const matchSearch = !search || crop.includes(search);
        const matchRegion = !region || rowRegion === region;
        row.style.display = matchSearch && matchRegion ? '' : 'none';
    });
}

// ============ REVIEW FUNCTIONS ============

// Load Reviews for a Product
function loadReviews(productId) {
    const container = document.getElementById('reviewsContainer');
    if (!container) return;
    
    container.innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_reviews&productId=' + productId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderReviews(data.reviews, data.stats);
        } else {
            container.innerHTML = '<p class="text-center text-muted">Failed to load reviews</p>';
        }
    })
    .catch(error => {
        container.innerHTML = '<p class="text-center text-muted">Failed to load reviews</p>';
    });
}

// Render Reviews
function renderReviews(reviews, stats) {
    const container = document.getElementById('reviewsContainer');
    
    let html = '';
    
    // Rating Summary
    if (stats && stats.total_reviews > 0) {
        const avgRating = parseFloat(stats.avg_rating) || 0;
        html += `
            <div class="rating-summary">
                <div class="rating-big">
                    <span class="rating-number">${avgRating.toFixed(1)}</span>
                    <div class="rating-stars">${renderStars(avgRating)}</div>
                    <span class="rating-count">${stats.total_reviews} reviews</span>
                </div>
                <div class="rating-bars">
                    ${[5,4,3,2,1].map(star => {
                        const count = stats[['','one','two','three','four','five'][star] + '_star'] || 0;
                        const pct = stats.total_reviews > 0 ? (count / stats.total_reviews * 100) : 0;
                        return `
                            <div class="rating-bar-row">
                                <span>${star} <span class="material-icons">star</span></span>
                                <div class="rating-bar"><div class="rating-bar-fill" style="width: ${pct}%"></div></div>
                                <span class="rating-bar-count">${count}</span>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }
    
    // Reviews List
    if (reviews && reviews.length > 0) {
        html += '<div class="reviews-list">';
        reviews.forEach(review => {
            html += renderReviewItem(review);
        });
        html += '</div>';
    } else {
        html += '<div class="empty-reviews"><span class="material-icons">rate_review</span><p>No reviews yet. Be the first to review!</p></div>';
    }
    
    container.innerHTML = html;
}

// Render Single Review Item
function renderReviewItem(review) {
    const isConsumer = review.reviewer_source === 'consumer';
    const isOwner    = review.user_id == currentUserId;
    const badgeClass = isConsumer ? 'info' : (review.role === 'officer' ? 'info' : 'success');
    const badgeLabel = isConsumer ? 'Consumer' : review.role;
    const authorHref = isConsumer ? null
        : (review.role === 'officer' ? `${baseUrl}officer-profile-view?id=${review.user_id}`
                                      : `${baseUrl}farmer-profile-view?id=${review.user_id}`);

    const authorInner = `
        <div class="review-avatar">
            ${review.profile_image ? `<img src="${baseUrl}public/${review.profile_image}" alt="">` : `<span class="material-icons">person</span>`}
        </div>
        <div class="review-author-info">
            <strong>${review.first_name} ${review.last_name || ''}</strong>
            <span class="badge badge-${badgeClass}">${badgeLabel}</span>
            ${review.is_verified_purchase ? '<span class="badge badge-success"><span class="material-icons">verified</span> Verified Purchase</span>' : ''}
        </div>
    `;

    let html = `
        <div class="review-item" data-review-id="${review.review_id}">
            <div class="review-header">
                ${authorHref
                    ? `<a href="${authorHref}" class="review-author">${authorInner}</a>`
                    : `<div class="review-author">${authorInner}</div>`
                }
                <div class="review-meta">
                    ${review.rating ? `<div class="review-rating">${renderStars(review.rating)}</div>` : ''}
                    <span class="review-date">${formatDate(review.created_at)}</span>
                </div>
            </div>
            
            <div class="review-content">
                <p>${escapeHtml(review.review_text || '')}</p>
                ${review.images && review.images.length > 0 ? `
                    <div class="review-images">
                        ${review.images.map(img => `<img src="${baseUrl}public/${img}" onclick="showFullImage('${baseUrl}${img}')" alt="Review image">`).join('')}
                    </div>
                ` : ''}
            </div>
            
            <div class="review-actions">
                <button class="action-btn ${review.user_liked > 0 ? 'active' : ''}" onclick="likeReview(${review.review_id})">
                    <span class="material-icons">${review.user_liked > 0 ? 'favorite' : 'favorite_border'}</span>
                    <span class="count">${review.likes_count || 0}</span>
                </button>
                <button class="action-btn" onclick="toggleReplyForm(${review.review_id})">
                    <span class="material-icons">reply</span> Reply
                </button>
                <div class="helpful-actions">
                    <button class="action-btn ${review.user_helpful_vote === 'helpful' ? 'active' : ''}" onclick="voteHelpful(${review.review_id}, true)" title="Helpful">
                        <span class="material-icons">thumb_up</span>
                        <span class="count">${review.helpful_count || 0}</span>
                    </button>
                    <button class="action-btn ${review.user_helpful_vote === 'not_helpful' ? 'active' : ''}" onclick="voteHelpful(${review.review_id}, false)" title="Not Helpful">
                        <span class="material-icons">thumb_down</span>
                        <span class="count">${review.not_helpful_count || 0}</span>
                    </button>
                </div>
                ${isOwner ? `
                    <button class="action-btn btn-danger" onclick="deleteReview(${review.review_id})">
                        <span class="material-icons">delete</span>
                    </button>
                ` : ''}
            </div>
            
            <!-- Reply Form -->
            <div class="reply-form-container" id="replyForm-${review.review_id}" style="display: none;">
                <textarea placeholder="Write your reply..." id="replyText-${review.review_id}"></textarea>
                <div class="reply-form-actions">
                    <button class="btn btn-small btn-secondary" onclick="toggleReplyForm(${review.review_id})">Cancel</button>
                    <button class="btn btn-small" onclick="submitReply(${review.review_id})">
                        <span class="material-icons">send</span> Reply
                    </button>
                </div>
            </div>
            
            <!-- Replies -->
            ${review.replies && review.replies.length > 0 ? `
                <div class="review-replies">
                    ${review.replies.map(reply => renderReplyItem(reply)).join('')}
                </div>
            ` : ''}
        </div>
    `;
    
    return html;
}

// Render Reply Item
function renderReplyItem(reply) {
    const profileUrl = reply.role === 'officer' ? 'officer-profile-view' : 'farmer-profile-view';
    const isOwner = reply.user_id == currentUserId;
    
    return `
        <div class="reply-item" data-review-id="${reply.review_id}">
            <a href="${baseUrl}${profileUrl}?id=${reply.user_id}" class="reply-author">
                <div class="reply-avatar">
                    ${reply.profile_image ? `<img src="${baseUrl}public/${reply.profile_image}" alt="">` : `<span class="material-icons">person</span>`}
                </div>
                <strong>${reply.first_name} ${reply.last_name || ''}</strong>
                <span class="badge badge-${reply.role === 'officer' ? 'info' : 'success'}">${reply.role}</span>
            </a>
            <p>${escapeHtml(reply.review_text || '')}</p>
            <div class="reply-meta">
                <span>${formatDate(reply.created_at)}</span>
                <button class="action-btn ${reply.user_liked > 0 ? 'active' : ''}" onclick="likeReview(${reply.review_id})">
                    <span class="material-icons">${reply.user_liked > 0 ? 'favorite' : 'favorite_border'}</span>
                    <span class="count">${reply.likes_count || 0}</span>
                </button>
                ${isOwner ? `
                    <button class="action-btn btn-danger" onclick="deleteReview(${reply.review_id})">
                        <span class="material-icons">delete</span>
                    </button>
                ` : ''}
            </div>
        </div>
    `;
}

// Render Stars
function renderStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<span class="material-icons star-filled">star</span>';
        } else if (i - 0.5 <= rating) {
            stars += '<span class="material-icons star-half">star_half</span>';
        } else {
            stars += '<span class="material-icons star-empty">star_border</span>';
        }
    }
    return stars;
}

// Open Review Form Modal
function openReviewForm(productId) {
    document.getElementById('reviewProductId').value = productId;
    document.getElementById('reviewForm').reset();
    setRating(0);
    openModal('reviewModal');
}

// Set Rating Stars
function setRating(rating) {
    document.getElementById('reviewRating').value = rating;
    const starsContainer = document.getElementById('ratingStars');
    starsContainer.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('span');
        star.className = 'material-icons star-select' + (i <= rating ? ' selected' : '');
        star.textContent = i <= rating ? 'star' : 'star_border';
        star.onclick = () => setRating(i);
        starsContainer.appendChild(star);
    }
}

// Submit Review
document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_review');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> Submitting...';
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('reviewModal');
            loadReviews(currentViewingProductId);
        } else {
            showNotification(data.message || 'Failed to submit review', 'error');
        }
    })
    .catch(error => {
        showNotification('Network error', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">send</span> Submit Review';
    });
});

// Like Review
function likeReview(reviewId) {
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=like_review&reviewId=' + reviewId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadReviews(currentViewingProductId);
        } else {
            showNotification(data.message || 'Failed to like review', 'error');
        }
    });
}

// Vote Helpful
function voteHelpful(reviewId, isHelpful) {
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=vote_helpful&reviewId=' + reviewId + '&isHelpful=' + (isHelpful ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadReviews(currentViewingProductId);
        } else {
            showNotification(data.message || 'Failed to vote', 'error');
        }
    });
}

// Toggle Reply Form
function toggleReplyForm(reviewId) {
    const form = document.getElementById('replyForm-' + reviewId);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

// Submit Reply
function submitReply(reviewId) {
    const replyText = document.getElementById('replyText-' + reviewId).value.trim();
    if (!replyText) {
        showNotification('Please enter a reply', 'error');
        return;
    }
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=reply_review&reviewId=' + reviewId + '&replyText=' + encodeURIComponent(replyText)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadReviews(currentViewingProductId);
        } else {
            showNotification(data.message || 'Failed to submit reply', 'error');
        }
    });
}

// Delete Review
function deleteReview(reviewId) {
    if (!confirm('Are you sure you want to delete this review?')) return;
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete_review&reviewId=' + reviewId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadReviews(currentViewingProductId);
        } else {
            showNotification(data.message || 'Failed to delete review', 'error');
        }
    });
}

// Show Full Image
function showFullImage(src) {
    const overlay = document.createElement('div');
    overlay.className = 'image-overlay';
    overlay.innerHTML = `<img src="${src}" alt="Full image"><button class="close-overlay" onclick="this.parentElement.remove()">&times;</button>`;
    overlay.onclick = function(e) { if (e.target === this) this.remove(); };
    document.body.appendChild(overlay);
}

// Helper Functions
function formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return days + ' days ago';
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============ END REVIEW FUNCTIONS ============

// ============ WISHLIST FUNCTIONS ============

function toggleWishlist(productId, btn = null) {
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_wishlist&productId=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Update button icon
            if (btn) {
                const icon = btn.querySelector('.material-icons');
                if (data.in_wishlist) {
                    icon.textContent = 'favorite';
                    btn.classList.add('active');
                } else {
                    icon.textContent = 'favorite_border';
                    btn.classList.remove('active');
                }
            }
            
            // Update wishlist badge
            const badge = document.getElementById('wishlistBadge');
            if (badge) {
                let count = parseInt(badge.textContent) || 0;
                count = data.in_wishlist ? count + 1 : Math.max(0, count - 1);
                updateBadge('wishlistBadge', count);
            }
        } else {
            showNotification(data.message || 'Failed', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function loadWishlist() {
    const container = document.getElementById('wishlistContent');
    container.innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_wishlist')
    .then(response => response.json())
    .then(data => {
        const items = data.wishlist || data.items || [];
        if (data.success && items.length > 0) {
            container.innerHTML = `
                <div class="wishlist-grid">
                    ${items.map(item => `
                        <div class="wishlist-item">
                            <div class="wishlist-image" onclick="closeModal('wishlistModal'); viewProduct(${item.product_id})">
                                ${item.image_url ? `<img src="${baseUrl}${item.image_url}" alt="">` : `<div class="wishlist-placeholder"><span class="material-icons">eco</span></div>`}
                            </div>
                            <div class="wishlist-info">
                                <h4 onclick="closeModal('wishlistModal'); viewProduct(${item.product_id})">${item.product_name}</h4>
                                <p class="price">৳${parseFloat(item.price).toFixed(2)}</p>
                                <p class="meta"><span class="material-icons">location_on</span> ${item.region || 'N/A'}</p>
                                <p class="added-date">Added: ${formatDate(item.created_at || item.added_at)}</p>
                            </div>
                            <div class="wishlist-actions">
                                <button class="btn btn-small btn-success" onclick="closeModal('wishlistModal'); orderProduct(${item.product_id}, '${item.product_name.replace(/'/g, "\\'")}', ${item.price}, ${item.quantity_available})">
                                    <span class="material-icons">shopping_cart</span>
                                </button>
                                <button class="btn btn-small btn-danger" onclick="removeFromWishlist(${item.product_id}, this)">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">favorite_border</span>
                    <p>Your wishlist is empty</p>
                    <small>Browse products and save your favorites!</small>
                </div>
            `;
        }
    })
    .catch((err) => {
        console.error('Wishlist error:', err);
        container.innerHTML = `
            <div class="empty-state">
                <span class="material-icons">favorite_border</span>
                <p>Your wishlist is empty</p>
                <small>Browse products and save your favorites!</small>
            </div>
        `;
    });
}

function removeFromWishlist(productId, btn) {
    btn.disabled = true;
    toggleWishlist(productId);
    btn.closest('.wishlist-item').remove();
    
    // Check if wishlist is now empty
    const grid = document.querySelector('.wishlist-grid');
    if (grid && grid.children.length === 0) {
        loadWishlist();
    }
}

// ============ OFFERS / NEGOTIATION FUNCTIONS ============

let currentOfferTab = 'received';

function openMakeOfferModal(productId, productName, price) {
    document.getElementById('offerProductId').value = productId;
    document.getElementById('offerProductName').textContent = productName;
    document.getElementById('offerListedPrice').textContent = parseFloat(price).toFixed(2);
    document.getElementById('offerPrice').value = '';
    document.getElementById('offerQuantity').value = 1;
    document.getElementById('offerMessage').value = '';
    openModal('makeOfferModal');
}

document.getElementById('offerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'make_offer');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> Sending...';
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('makeOfferModal');
        } else {
            showNotification(data.message || 'Failed to send offer', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'))
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">send</span> Send Offer';
    });
});

function switchOfferTab(tab) {
    currentOfferTab = tab;
    document.querySelectorAll('.offers-tabs .tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tab));
    });
    loadOffers(tab);
}

function loadOffers(type = 'received') {
    const container = document.getElementById('offersContent');
    container.innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_offers&type=' + type)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.offers.length > 0) {
            container.innerHTML = `
                <div class="offers-list">
                    ${data.offers.map(offer => `
                        <div class="offer-item status-${offer.status}">
                            <div class="offer-product">
                                ${offer.image_url ? `<img src="${baseUrl}${offer.image_url}" alt="">` : `<div class="offer-placeholder"><span class="material-icons">eco</span></div>`}
                                <div class="offer-product-info">
                                    <h4>${offer.product_name}</h4>
                                    <p>Listed: ৳${parseFloat(offer.original_price).toFixed(2)}</p>
                                </div>
                            </div>
                            <div class="offer-details">
                                <p class="offer-price"><strong>Offer:</strong> ৳${parseFloat(offer.offer_price).toFixed(2)} × ${offer.quantity}</p>
                                <p class="offer-total">Total: ৳${(parseFloat(offer.offer_price) * offer.quantity).toFixed(2)}</p>
                                <p class="offer-user">${type === 'received' ? 'From' : 'To'}: ${offer.first_name} ${offer.last_name || ''}</p>
                                ${offer.message ? `<p class="offer-message">"${offer.message}"</p>` : ''}
                            </div>
                            <div class="offer-status">
                                <span class="badge badge-${offer.status === 'pending' ? 'warning' : offer.status === 'accepted' ? 'success' : offer.status === 'rejected' ? 'danger' : 'secondary'}">${offer.status}</span>
                                <span class="offer-date">${formatDate(offer.created_at)}</span>
                            </div>
                            ${type === 'received' && offer.status === 'pending' ? `
                                <div class="offer-actions">
                                    <button class="btn btn-small btn-success" onclick="respondToOffer(${offer.offer_id}, 'accepted')">
                                        <span class="material-icons">check</span> Accept
                                    </button>
                                    <button class="btn btn-small btn-danger" onclick="respondToOffer(${offer.offer_id}, 'rejected')">
                                        <span class="material-icons">close</span> Reject
                                    </button>
                                    <button class="btn btn-small" onclick="respondToOffer(${offer.offer_id}, 'countered', ${offer.offer_price})">
                                        <span class="material-icons">reply</span> Counter
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">local_offer</span>
                    <p>No ${type} offers</p>
                </div>
            `;
        }
    })
    .catch(() => {
        container.innerHTML = '<p class="text-danger text-center">Failed to load offers</p>';
    });
}

function respondToOffer(offerId, status, currentPrice = null) {
    if (status === 'countered') {
        const newPrice = prompt('Enter your counter-offer price:', currentPrice);
        if (!newPrice || isNaN(newPrice)) return;
        
        fetch(baseUrl + 'ajax/marketplace.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=respond_offer&offerId=${offerId}&status=${status}&counterPrice=${newPrice}`
        })
        .then(response => response.json())
        .then(data => {
            showNotification(data.message, data.success ? 'success' : 'error');
            if (data.success) loadOffers(currentOfferTab);
        })
        .catch(() => showNotification('Network error', 'error'));
    } else {
        if (!confirm(`Are you sure you want to ${status === 'accepted' ? 'accept' : 'reject'} this offer?`)) return;
        
        fetch(baseUrl + 'ajax/marketplace.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=respond_offer&offerId=${offerId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            showNotification(data.message, data.success ? 'success' : 'error');
            if (data.success) loadOffers(currentOfferTab);
        })
        .catch(() => showNotification('Network error', 'error'));
    }
}

// ============ COMPARE FUNCTIONS ============

function toggleCompare(productId, productName, btn = null) {
    const maxCompare = 4;
    const index = compareList.indexOf(productId);
    
    if (index > -1) {
        compareList.splice(index, 1);
        if (btn) {
            btn.classList.remove('active');
        }
        showNotification('Removed from compare list', 'success');
    } else {
        if (compareList.length >= maxCompare) {
            showNotification(`Maximum ${maxCompare} products can be compared`, 'error');
            return;
        }
        compareList.push(productId);
        if (btn) {
            btn.classList.add('active');
        }
        showNotification(`${productName} added to compare`, 'success');
    }
    
    localStorage.setItem('compareProducts', JSON.stringify(compareList));
    updateBadge('compareBadge', compareList.length);
}

function loadCompareList() {
    const container = document.getElementById('compareContent');
    
    if (!compareList || compareList.length === 0) {
        container.innerHTML = `
            <div class="compare-empty">
                <span class="material-icons">compare_arrows</span>
                <p>No products to compare</p>
                <small>Add products by clicking the compare button on product cards</small>
            </div>
        `;
        return;
    }
    
    container.innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    // Fetch details for all products in compare list
    Promise.all(compareList.map(id => 
        fetch(baseUrl + 'ajax/marketplace.php?action=get_product_details&productId=' + id)
            .then(r => r.json())
            .catch(err => {
                console.error('Fetch error for product', id, err);
                return { success: false };
            })
    ))
    .then(results => {
        const products = results.filter(r => r && r.success && r.product).map(r => r.product);
        
        // Clean up invalid product IDs from localStorage
        const validIds = results.filter(r => r && r.success && r.product).map((r, i) => compareList[i]);
        if (validIds.length !== compareList.length) {
            compareList = validIds;
            localStorage.setItem('compareProducts', JSON.stringify(compareList));
            updateBadge('compareBadge', compareList.length);
        }
        
        if (products.length === 0) {
            container.innerHTML = `
                <div class="compare-empty">
                    <span class="material-icons">compare_arrows</span>
                    <p>No valid products to compare</p>
                    <small>The products may have been removed. Add new products to compare.</small>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="compare-table-wrapper">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            ${products.map(p => `
                                <th>
                                    <div class="compare-header">
                                        ${p.image_url ? `<img src="${baseUrl}${p.image_url}" alt="">` : `<div class="compare-placeholder"><span class="material-icons">eco</span></div>`}
                                        <h4>${p.product_name}</h4>
                                        <button class="btn btn-small btn-danger" onclick="removeFromCompare(${p.product_id})">
                                            <span class="material-icons">close</span>
                                        </button>
                                    </div>
                                </th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Price</strong></td>
                            ${products.map(p => `<td class="price-cell">৳${parseFloat(p.price).toFixed(2)} / ${p.price_unit || 'kg'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Rating</strong></td>
                            ${products.map(p => `<td>${p.average_rating > 0 ? `${parseFloat(p.average_rating).toFixed(1)} ★ (${p.review_count || 0})` : 'No ratings'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Available</strong></td>
                            ${products.map(p => `<td>${p.quantity_available} ${p.unit || 'kg'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Type</strong></td>
                            ${products.map(p => `<td>${p.product_type}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Quality</strong></td>
                            ${products.map(p => `<td>${p.quality_grade ? 'Grade ' + p.quality_grade : 'N/A'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Location</strong></td>
                            ${products.map(p => `<td>${p.location || p.region || 'N/A'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Seller</strong></td>
                            ${products.map(p => `<td>${p.first_name} ${p.last_name || ''}</td>`).join('')}
                        </tr>
                        <tr>
                            <td><strong>Negotiable</strong></td>
                            ${products.map(p => `<td>${p.is_negotiable == 1 ? '<span class="material-icons text-success">check</span>' : '<span class="material-icons text-danger">close</span>'}</td>`).join('')}
                        </tr>
                        <tr>
                            <td></td>
                            ${products.map(p => `
                                <td>
                                    <button class="btn btn-success btn-small" onclick="closeModal('compareModal'); orderProduct(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}', ${p.price}, ${p.quantity_available})">
                                        <span class="material-icons">shopping_cart</span> Order
                                    </button>
                                </td>
                            `).join('')}
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    })
    .catch((err) => {
        console.error('Compare error:', err);
        container.innerHTML = `
            <div class="compare-empty">
                <span class="material-icons">compare_arrows</span>
                <p>No products to compare</p>
                <small>Add products by clicking the compare button on product cards</small>
            </div>
        `;
    });
}

function removeFromCompare(productId) {
    const index = compareList.indexOf(productId);
    if (index > -1) {
        compareList.splice(index, 1);
        localStorage.setItem('compareProducts', JSON.stringify(compareList));
        updateBadge('compareBadge', compareList.length);
        loadCompareList();
    }
}

function clearCompareList() {
    compareList = [];
    localStorage.setItem('compareProducts', JSON.stringify(compareList));
    updateBadge('compareBadge', 0);
    document.querySelectorAll('.compare-btn.active').forEach(btn => btn.classList.remove('active'));
    loadCompareList();
    showNotification('Compare list cleared', 'success');
}

// ============ REPORT FUNCTIONS ============

function openReportModal(productId) {
    document.getElementById('reportProductId').value = productId;
    document.getElementById('reportReason').value = '';
    document.getElementById('reportDescription').value = '';
    openModal('reportModal');
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'report_product');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> Submitting...';
    
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('reportModal');
        } else {
            showNotification(data.message || 'Failed to submit report', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'))
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">flag</span> Submit Report';
    });
});

// ============ SHARE FUNCTIONS ============

let currentShareProductId = 0;
let currentShareProductName = '';

function shareProduct(productId, productName) {
    currentShareProductId = productId;
    currentShareProductName = productName;
    document.getElementById('shareProductName').textContent = productName;
    document.getElementById('shareLink').value =  baseUrl + 'marketplace?product=' + productId;
    openModal('shareModal');
}

function shareToWhatsApp() {
    const url = document.getElementById('shareLink').value;
    const text = `Check out this product: ${currentShareProductName}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank');
    trackShare('whatsapp');
}

function shareToFacebook() {
    const url = document.getElementById('shareLink').value;
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
    trackShare('facebook');
}

function shareToTwitter() {
    const url = document.getElementById('shareLink').value;
    const text = `Check out this product: ${currentShareProductName}`;
    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`, '_blank');
    trackShare('twitter');
}

function shareViaEmail() {
    const url = document.getElementById('shareLink').value;
    const subject = `Check out this product: ${currentShareProductName}`;
    const body = `I found this interesting product on SmartCashi Marketplace:\n\n${currentShareProductName}\n\n${url}`;
    window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    trackShare('email');
}

function copyShareLink() {
    const input = document.getElementById('shareLink');
    input.select();
    document.execCommand('copy');
    showNotification('Link copied to clipboard!', 'success');
    trackShare('copy');
}

function trackShare(platform) {
    fetch(baseUrl + 'ajax/marketplace.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=share_product&productId=${currentShareProductId}&platform=${platform}`
    });
}

// ============ RECENTLY VIEWED FUNCTIONS ============

function loadRecentlyViewed() {
    const container = document.getElementById('recentlyViewedContent');
    container.innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
    
    fetch(baseUrl + 'ajax/marketplace.php?action=get_recently_viewed')
    .then(response => response.json())
    .then(data => {
        const items = data.products || data.items || [];
        if (data.success && items.length > 0) {
            container.innerHTML = `
                <div class="recently-viewed-grid">
                    ${items.map(item => `
                        <div class="recently-viewed-item" onclick="closeModal('recentlyViewedModal'); viewProduct(${item.product_id})">
                            ${item.image_url ? `<img src="${baseUrl}${item.image_url}" alt="">` : `<div class="recently-placeholder"><span class="material-icons">eco</span></div>`}
                            <div class="recently-info">
                                <h5>${item.product_name}</h5>
                                <p class="price">৳${parseFloat(item.price).toFixed(2)}</p>
                                <small>Viewed: ${formatDate(item.viewed_at)}</small>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">history</span>
                    <p>No recently viewed products</p>
                </div>
            `;
        }
    })
    .catch((err) => {
        console.error('Recently viewed error:', err);
        container.innerHTML = `
            <div class="empty-state">
                <span class="material-icons">history</span>
                <p>No recently viewed products</p>
            </div>
        `;
    });
}

// ============ END EXTRA FEATURES ============

// showNotification is now provided globally via footer.php
</script>

<style>
/* Marketplace Layout */
.marketplace-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.marketplace-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.marketplace-main {
    min-width: 0;
}

/* Stats Cards */
.marketplace-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.stat-card > .material-icons {
    font-size: 2.5rem;
    color: var(--primary);
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 0.85rem;
    color: #666;
}

.stat-card .badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

/* Products Header */
.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.products-header h2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.products-header .badge {
    font-size: 0.8rem;
    margin-left: 0.5rem;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

/* Product Card */
.product-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
    position: relative;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

/* Owner's product card styling */
.product-card:has(.owner-badge) {
    border: 2px solid #2196F3;
}

.product-card:has(.owner-badge):hover {
    box-shadow: 0 8px 24px rgba(33, 150, 243, 0.3);
}

.product-image {
    height: 180px;
    position: relative;
    overflow: hidden;
    background: #f5f5f5;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
}

.product-image-placeholder .material-icons {
    font-size: 4rem;
    color: var(--primary);
    opacity: 0.5;
}

.product-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}

.product-badge.featured {
    background: #ff9800;
}

.product-badge.owner-badge {
    background: #2196F3;
    right: 0.5rem;
    left: auto;
}

.product-badge.premium {
    background: #9c27b0;
    top: 2rem;
}

.product-badge.negotiable {
    background: #00bcd4;
    top: 3.5rem;
}

.product-badge.bulk-deal {
    background: #e91e63;
    top: 5rem;
}

.product-info {
    padding: 1rem;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.product-header h4 {
    margin: 0;
    font-size: 1.1rem;
    flex: 1;
}

.product-description {
    color: #666;
    font-size: 0.85rem;
    margin: 0 0 0.75rem;
    line-height: 1.4;
}

.product-rating-display {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.product-rating-display .star-filled {
    font-size: 1rem;
    color: #ffc107;
}

.product-rating-display .review-count {
    color: #999;
    font-size: 0.8rem;
}

.product-price {
    margin-bottom: 0.75rem;
}

.product-price .price {
    font-size: 1.3rem;
    font-weight: bold;
    color: var(--primary);
}

.product-price .unit {
    color: #666;
    font-size: 0.9rem;
}

.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.75rem;
}

.product-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.product-meta .material-icons {
    font-size: 1rem;
}

.product-seller {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #666;
    padding-top: 0.75rem;
    border-top: 1px solid #eee;
}

.product-seller .material-icons {
    font-size: 1rem;
}

.product-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

.product-actions .btn {
    flex: 1;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 12px;
}

.empty-state .material-icons {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 1rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
}

.pagination-info {
    color: #666;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-lg { max-width: 800px; }
.modal-xl { max-width: 1000px; }

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

/* Product Detail */
.product-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.product-detail-image img {
    width: 100%;
    border-radius: 8px;
}

.product-image-placeholder.large {
    height: 300px;
    border-radius: 8px;
}

.price-large {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary);
}

.product-detail-meta p {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
}

.product-detail-description {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.product-detail-seller {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.product-detail-seller h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.5rem;
}

.product-detail-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

/* Related Products */
.related-products {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #eee;
}

.related-products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.related-product-card {
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    background: #f9f9f9;
}

.related-product-card img {
    width: 100%;
    height: 80px;
    object-fit: cover;
}

.related-placeholder {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9e9e9;
}

.related-info {
    padding: 0.5rem;
}

.related-info h5 {
    margin: 0;
    font-size: 0.85rem;
}

.related-info p {
    margin: 0;
    color: var(--primary);
    font-weight: bold;
    font-size: 0.85rem;
}

/* Order Form */
.order-product-info {
    text-align: center;
    padding: 1rem;
    background: #f0f7f0;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.order-product-info h4 {
    margin: 0 0 0.5rem;
}

.order-price {
    font-size: 1.2rem;
    color: var(--primary);
    margin: 0;
}

.order-total {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
    padding: 1rem;
    background: #e8f5e9;
    border-radius: 8px;
    text-align: center;
}

/* My Products List */
.my-products-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.my-product-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
    align-items: center;
}

.my-product-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.my-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-placeholder-small {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9e9e9;
}

.my-product-info {
    flex: 1;
}

.my-product-info h4 {
    margin: 0 0 0.25rem;
}

.my-product-price {
    color: var(--primary);
    font-weight: bold;
    margin: 0 0 0.25rem;
}

.my-product-actions {
    display: flex;
    gap: 0.5rem;
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
    align-items: center;
}

.order-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.order-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.order-info {
    flex: 1;
}

.order-info h4 {
    margin: 0 0 0.25rem;
}

.order-info p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.order-status {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-end;
}

.order-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.seller-actions {
    flex-direction: row;
}

/* Market Prices */
.market-prices-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.market-prices-filters input,
.market-prices-filters select {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.price-cell {
    font-weight: bold;
    color: var(--primary);
}

/* Table */
.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background: #f9f9f9;
    font-weight: 600;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-group label input[type="checkbox"] {
    width: auto;
    margin-right: 0.5rem;
    vertical-align: middle;
    cursor: pointer;
}

.form-group label:has(input[type="checkbox"]) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 400;
}

.checkbox-group {
    display: flex;
    align-items: center;
    padding-top: 1.5rem;
}

.checkbox-inline {
    display: flex !important;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 400;
    margin-bottom: 0;
}

.checkbox-inline input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    accent-color: var(--primary);
}

.checkbox-inline span {
    color: var(--primary);
    font-size: 0.95rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary);
    outline: none;
}

.form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.85rem;
    color: #666;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

/* Button Outline */
.btn-outline {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

/* Loading Spinner */
.loading-spinner {
    text-align: center;
    padding: 2rem;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 10001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}

.notification-success { background: var(--primary); }
.notification-error { background: #dc3545; }

.notification.fade-out {
    opacity: 0;
    transition: opacity 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(100px); }
    to { opacity: 1; transform: translateX(0); }
}

/* ============ REVIEW STYLES ============ */

/* Reviews Section */
.reviews-section {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #eee;
}

.reviews-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.reviews-header h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

/* Rating Summary */
.rating-summary {
    display: flex;
    gap: 2rem;
    padding: 1.5rem;
    background: #f9f9f9;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.rating-big {
    text-align: center;
    min-width: 120px;
}

.rating-number {
    font-size: 3rem;
    font-weight: bold;
    color: #333;
}

.rating-stars {
    display: flex;
    justify-content: center;
    gap: 2px;
}

.rating-count {
    color: #666;
    font-size: 0.9rem;
}

.rating-bars {
    flex: 1;
}

.rating-bar-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.rating-bar-row > span:first-child {
    width: 40px;
    display: flex;
    align-items: center;
    gap: 2px;
    font-size: 0.85rem;
}

.rating-bar-row .material-icons {
    font-size: 0.9rem;
    color: #ffc107;
}

.rating-bar {
    flex: 1;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.rating-bar-fill {
    height: 100%;
    background: #ffc107;
    border-radius: 4px;
}

.rating-bar-count {
    width: 30px;
    text-align: right;
    font-size: 0.85rem;
    color: #666;
}

/* Star Styles */
.star-filled, .star-half { color: #ffc107; }
.star-empty { color: #ddd; }

.star-select {
    cursor: pointer;
    font-size: 2rem;
    color: #ddd;
    transition: color 0.2s;
}

.star-select:hover,
.star-select.selected {
    color: #ffc107;
}

.rating-input {
    display: flex;
    gap: 0.25rem;
}

.rating-badge {
    display: flex;
    align-items: center;
    gap: 2px;
    font-size: 0.9rem;
    color: #ffc107;
}

.rating-badge .material-icons {
    font-size: 1rem;
}

/* Reviews List */
.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.empty-reviews {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.empty-reviews .material-icons {
    font-size: 3rem;
    color: #ccc;
    margin-bottom: 0.5rem;
}

/* Review Item */
.review-item {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 1rem;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.review-author {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: inherit;
}

.review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9e9e9;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.review-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.review-avatar .material-icons {
    color: #999;
}

.review-author-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.review-author-info strong {
    font-size: 0.95rem;
}

.review-author-info .badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.35rem;
}

.review-meta {
    text-align: right;
}

.review-rating {
    display: flex;
    gap: 1px;
}

.review-rating .material-icons {
    font-size: 1rem;
}

.review-date {
    font-size: 0.8rem;
    color: #999;
}

.review-content {
    margin-bottom: 0.75rem;
}

.review-content p {
    margin: 0;
    line-height: 1.5;
}

.review-images {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}

.review-images img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
}

.review-images img:hover {
    transform: scale(1.05);
}

/* Review Actions */
.review-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f0f0f0;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.35rem 0.5rem;
    background: none;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    color: #666;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #f5f5f5;
    border-color: #ccc;
}

.action-btn.active {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #2196f3;
}

.action-btn .material-icons {
    font-size: 1rem;
}

.action-btn.btn-danger {
    color: #dc3545;
}

.action-btn.btn-danger:hover {
    background: #fee;
    border-color: #dc3545;
}

.helpful-actions {
    display: flex;
    gap: 0.25rem;
    margin-left: auto;
}

/* Reply Form */
.reply-form-container {
    margin-top: 1rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.reply-form-container textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    min-height: 80px;
    margin-bottom: 0.75rem;
}

.reply-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Review Replies */
.review-replies {
    margin-top: 1rem;
    padding-left: 1.5rem;
    border-left: 2px solid #e0e0e0;
}

.reply-item {
    padding: 0.75rem;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.reply-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: inherit;
    margin-bottom: 0.5rem;
}

.reply-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.reply-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.reply-avatar .material-icons {
    font-size: 1rem;
    color: #999;
}

.reply-item p {
    margin: 0 0 0.5rem;
    font-size: 0.9rem;
}

.reply-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: #999;
}

/* Image Overlay */
.image-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
    cursor: pointer;
}

.image-overlay img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.close-overlay {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
}

/* Seller Info Card */
.seller-info-card {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    background: #f0f7f0;
    border-radius: 8px;
    margin-top: 0.5rem;
}

.seller-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.seller-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.seller-avatar .material-icons {
    font-size: 1.5rem;
    color: #999;
}

.seller-details p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.seller-details .badge {
    font-size: 0.7rem;
}

/* Product Gallery */
.product-gallery {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}

.product-gallery img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}

.product-gallery img:hover {
    border-color: var(--primary);
}

/* ============ END REVIEW STYLES ============ */

/* ============ QUICK ACTIONS ON PRODUCT CARDS ============ */

.product-quick-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    opacity: 0;
    transform: translateX(10px);
    transition: all 0.3s ease;
}

.product-card:hover .product-quick-actions {
    opacity: 1;
    transform: translateX(0);
}

.quick-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.quick-action-btn:hover {
    transform: scale(1.1);
}

.quick-action-btn .material-icons {
    font-size: 18px;
    color: #666;
}

.quick-action-btn.wishlist-btn:hover .material-icons,
.quick-action-btn.wishlist-btn.active .material-icons {
    color: #e91e63;
}

.quick-action-btn.compare-btn:hover .material-icons,
.quick-action-btn.compare-btn.active .material-icons {
    color: var(--primary);
}

.quick-action-btn.share-btn:hover .material-icons {
    color: #2196F3;
}

/* ============ FLOATING ACTION BUTTONS ============ */

.marketplace-fab-container {
    position: fixed;
    bottom: 24px;
    right: 84px;
    display: flex;
    flex-direction: row;
    gap: 12px;
    z-index: 998;
}

.fab {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
    position: relative;
    outline: none;
    padding: 0;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.35);
}

.fab:active {
    transform: scale(0.95);
}

.fab .material-icons {
    font-size: 22px;
    color: white;
    line-height: 1;
}

.fab-wishlist {
    background: linear-gradient(135deg, #e91e63, #f06292);
}

.fab-compare {
    background: linear-gradient(135deg, #4CAF50, #8BC34A);
}

.fab-history {
    background: linear-gradient(135deg, #607D8B, #90A4AE);
}

.fab-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f44336;
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    border: 2px solid white;
}

/* Hide badge when empty or zero */
.fab-badge:empty {
    display: none !important;
}

.fab-badge.hidden {
    display: none !important;
}

/* ============ WISHLIST STYLES ============ */

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.wishlist-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #eee;
}

.wishlist-image {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    cursor: pointer;
}

.wishlist-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

.wishlist-placeholder {
    width: 100%;
    height: 100%;
    background: #e8f5e9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wishlist-placeholder .material-icons {
    color: var(--primary);
    font-size: 32px;
}

.wishlist-info {
    flex: 1;
    min-width: 0;
}

.wishlist-info h4 {
    margin: 0 0 0.5rem;
    font-size: 0.95rem;
    cursor: pointer;
    color: #333;
}

.wishlist-info h4:hover {
    color: var(--primary);
}

.wishlist-info .price {
    color: var(--primary);
    font-weight: 600;
    margin: 0.25rem 0;
}

.wishlist-info .meta {
    font-size: 0.8rem;
    color: #777;
    display: flex;
    align-items: center;
    gap: 4px;
}

.wishlist-info .meta .material-icons {
    font-size: 14px;
}

.wishlist-info .added-date {
    font-size: 0.75rem;
    color: #999;
    margin-top: 0.25rem;
}

.wishlist-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ============ OFFERS STYLES ============ */

.offers-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #eee;
    padding-bottom: 0.5rem;
}

.offers-tabs .tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s;
}

.offers-tabs .tab-btn:hover {
    background: #f5f5f5;
}

.offers-tabs .tab-btn.active {
    color: var(--primary);
    background: #e8f5e9;
    font-weight: 600;
}

.offers-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.offer-item {
    display: grid;
    grid-template-columns: auto 1fr auto auto;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #eee;
    align-items: center;
}

.offer-item.status-pending {
    border-left: 4px solid #ff9800;
}

.offer-item.status-accepted {
    border-left: 4px solid #4caf50;
    background: #f1f8e9;
}

.offer-item.status-rejected {
    border-left: 4px solid #f44336;
    background: #ffebee;
}

.offer-item.status-countered {
    border-left: 4px solid #2196F3;
    background: #e3f2fd;
}

.offer-product {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.offer-product img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
}

.offer-placeholder {
    width: 60px;
    height: 60px;
    background: #e8f5e9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.offer-product-info h4 {
    margin: 0;
    font-size: 0.9rem;
}

.offer-product-info p {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
    color: #777;
}

.offer-details .offer-price {
    margin: 0;
    font-size: 1rem;
}

.offer-details .offer-total {
    color: var(--primary);
    font-weight: 600;
    margin: 0.25rem 0;
}

.offer-details .offer-user {
    font-size: 0.85rem;
    color: #666;
    margin: 0.25rem 0 0;
}

.offer-details .offer-message {
    font-size: 0.85rem;
    font-style: italic;
    color: #777;
    margin: 0.25rem 0 0;
    background: #f5f5f5;
    padding: 0.5rem;
    border-radius: 4px;
}

.offer-status {
    text-align: center;
}

.offer-status .offer-date {
    display: block;
    font-size: 0.75rem;
    color: #999;
    margin-top: 0.25rem;
}

.offer-actions {
    display: flex;
    gap: 0.5rem;
}

/* Make Offer Modal */
.offer-product-info {
    text-align: center;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.offer-product-info h4 {
    margin: 0 0 0.5rem;
}

.offer-product-info .current-price {
    margin: 0;
    color: #666;
}

/* ============ COMPARE STYLES ============ */

.compare-table-wrapper {
    overflow-x: auto;
}

.compare-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.compare-table th,
.compare-table td {
    padding: 1rem;
    border: 1px solid #eee;
    text-align: center;
    vertical-align: middle;
}

.compare-table th {
    background: #f5f5f5;
}

.compare-table td:first-child {
    background: #fafafa;
    text-align: left;
    font-weight: 500;
}

.compare-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.compare-header img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
}

.compare-header h4 {
    margin: 0;
    font-size: 0.9rem;
}

.compare-placeholder {
    width: 100px;
    height: 100px;
    background: #e8f5e9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.compare-placeholder .material-icons {
    font-size: 40px;
    color: var(--primary);
}

.compare-empty {
    text-align: center;
    padding: 3rem;
}

.compare-empty .material-icons {
    font-size: 64px;
    color: #ccc;
    display: block;
    margin-bottom: 1rem;
}

.compare-empty p {
    font-size: 1.1rem;
    color: #666;
    margin: 0;
}

.compare-empty small {
    color: #999;
}

.price-cell {
    color: var(--primary);
    font-weight: 600;
    font-size: 1.1rem;
}

.text-success {
    color: #4caf50;
}

.text-danger {
    color: #f44336;
}

/* ============ REPORT STYLES ============ */

#reportModal .modal-content {
    max-width: 500px;
}

/* ============ SHARE STYLES ============ */

.share-product-title {
    text-align: center;
    margin: 0 0 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.share-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.share-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 1rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
}

.share-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.share-btn .material-icons {
    font-size: 28px;
}

.share-btn.whatsapp {
    background: #25D366;
    color: white;
}

.share-btn.facebook {
    background: #1877F2;
    color: white;
}

.share-btn.twitter {
    background: #1DA1F2;
    color: white;
}

.share-btn.email {
    background: #EA4335;
    color: white;
}

.share-link-group {
    margin-top: 1rem;
}

.copy-link-wrapper {
    display: flex;
    gap: 0.5rem;
}

.copy-link-wrapper input {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    background: #f9f9f9;
}

/* ============ RECENTLY VIEWED STYLES ============ */

.recently-viewed-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.recently-viewed-item {
    background: white;
    border-radius: 8px;
    border: 1px solid #eee;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
}

.recently-viewed-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.recently-viewed-item img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.recently-placeholder {
    width: 100%;
    height: 120px;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.recently-placeholder .material-icons {
    font-size: 40px;
    color: var(--primary);
}

.recently-info {
    padding: 0.75rem;
}

.recently-info h5 {
    margin: 0 0 0.25rem;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recently-info .price {
    color: var(--primary);
    font-weight: 600;
    margin: 0;
}

.recently-info small {
    color: #999;
    font-size: 0.75rem;
}

/* ============ PRODUCT DETAIL SECONDARY ACTIONS ============ */

.product-detail-actions.secondary-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #eee;
}

.btn-outline {
    background: transparent;
    border: 1px solid #ddd;
    color: #666;
}

.btn-outline:hover {
    background: #f5f5f5;
    border-color: #ccc;
}

.btn-outline .material-icons {
    font-size: 18px;
}

.wishlist-action:hover {
    border-color: #e91e63;
    color: #e91e63;
}

.offer-action:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.compare-action:hover {
    border-color: #2196F3;
    color: #2196F3;
}

.share-action:hover {
    border-color: #00bcd4;
    color: #00bcd4;
}

.report-action:hover {
    border-color: #f44336;
    color: #f44336;
}

/* Bulk Discount Info */
.bulk-discount-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border-radius: 8px;
    margin-top: 1rem;
    color: #e65100;
    font-size: 0.9rem;
}

.bulk-discount-info .material-icons {
    font-size: 20px;
}

/* Minimum Order Info */
.min-order-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0.5rem 1rem;
    background: #e3f2fd;
    border-radius: 8px;
    margin-top: 0.5rem;
    color: #1565c0;
    font-size: 0.85rem;
}

.min-order-info .material-icons {
    font-size: 18px;
}

/* Checkbox styling */
.checkbox-group {
    margin: 1rem 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.95rem;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 22px;
    height: 22px;
    border: 2px solid #ddd;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background: var(--primary);
    border-color: var(--primary);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
    content: '✓';
    color: white;
    font-size: 14px;
    font-weight: bold;
}

/* ============ END EXTRA FEATURE STYLES ============ */

/* Responsive */
@media (max-width: 900px) {
    .marketplace-layout {
        grid-template-columns: 1fr;
    }
    
    .marketplace-sidebar {
        order: 2;
    }
    
    .marketplace-main {
        order: 1;
    }
    
    .product-detail-grid {
        grid-template-columns: 1fr;
    }
    
    .related-products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .rating-summary {
        flex-direction: column;
        gap: 1rem;
    }
    
    .offer-item {
        grid-template-columns: 1fr;
    }
    
    .marketplace-fab-container {
        bottom: 72px;
        right: 16px;
        flex-direction: column;
        gap: 10px;
    }
    
    .fab {
        width: 44px;
        height: 44px;
    }
    
    .fab .material-icons {
        font-size: 20px;
    }
}

@media (max-width: 600px) {
    .marketplace-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .order-item {
        flex-wrap: wrap;
    }
    
    .my-product-item {
        flex-wrap: wrap;
    }
    
    .review-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .review-meta {
        text-align: left;
    }
    
    .review-actions {
        flex-wrap: wrap;
    }
    
    .helpful-actions {
        margin-left: 0;
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
