<?php
/**
 * Shop Product Detail Page — Professional v2
 * Reviews, message seller, full details
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();

$productId = intval($_GET['id'] ?? 0);
if (!$productId) {
    setFlashMessage('error', 'Product not found.');
    shopRedirect('pages/products.php');
}

$product = $db->single(
    "SELECT mp.*, u.user_id as farmer_user_id,
            u.first_name as seller_name, u.last_name as seller_last_name,
            u.profile_img_url as seller_avatar, u.phone as seller_phone,
            u.email as seller_email, u.created_at as seller_since,
            COALESCE(fp.district, mp.district) as seller_district
     FROM marketplace_products mp
     LEFT JOIN users u ON mp.seller_id = u.user_id
     LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id
     WHERE mp.product_id = ? AND mp.status = 'available'",
    [$productId]
);
if (!$product) {
    setFlashMessage('error', 'Product not found or no longer available.');
    shopRedirect('pages/products.php');
}

// Increment views
$db->query("UPDATE marketplace_products SET views = views + 1 WHERE product_id = ?")->bind(1, $productId)->execute();

// Related products
$relatedProducts = $db->resultSet(
    "SELECT mp.*, u.first_name as seller_name
     FROM marketplace_products mp
     LEFT JOIN users u ON mp.seller_id = u.user_id
     WHERE mp.status = 'available' AND mp.product_id != ?
       AND (mp.category = ? OR mp.product_type = ?)
     ORDER BY RAND() LIMIT 4",
    [$productId, $product['category'], $product['product_type']]
);

// Additional gallery images
$additionalImages = [];
if (!empty($product['images'])) {
    $decoded = json_decode($product['images'], true);
    if (is_array($decoded)) $additionalImages = $decoded;
}

// Reviews summary
$reviewSummary = $db->single(
    "SELECT COUNT(*) as total, AVG(rating) as average,
            SUM(rating=5) as five, SUM(rating=4) as four, SUM(rating=3) as three,
            SUM(rating=2) as two,  SUM(rating=1) as one
     FROM product_reviews WHERE product_id = ? AND status = 'active' AND parent_review_id IS NULL",
    [$productId]
);
$reviewTotal   = intval($reviewSummary['total'] ?? 0);
$reviewAverage = round(floatval($reviewSummary['average'] ?? 0), 1);

// Latest reviews (first page)
$reviews = $db->resultSet(
    "SELECT r.*, g.first_name, g.last_name
     FROM product_reviews r
     JOIN general_users g ON r.user_id = g.user_id
     WHERE r.product_id = ? AND r.status = 'active' AND r.parent_review_id IS NULL
     ORDER BY r.created_at DESC LIMIT 5",
    [$productId]
);

// Has current user already reviewed?
$userReview = null;
if (isShopLoggedIn()) {
    $userReview = $db->single(
        "SELECT review_id, rating FROM product_reviews WHERE product_id = ? AND user_id = ?",
        [$productId, $_SESSION['shop_user_id']]
    );
}

$shopUser  = isShopLoggedIn() ? getShopUser() : null;
$pageTitle = $product['product_name'];
include __DIR__ . '/../layouts/header.php';
?>

<div class="pd-page container">

    <!-- Breadcrumb -->
    <nav class="pd-breadcrumb">
        <a href="<?php echo shopUrl(); ?>"><?php echo __('home'); ?></a>
        <span class="material-icons">chevron_right</span>
        <a href="<?php echo shopUrl('pages/products.php'); ?>"><?php echo __('products_menu'); ?></a>
        <?php if (!empty($product['category'])): ?>
        <span class="material-icons">chevron_right</span>
        <a href="<?php echo shopUrl('pages/products.php?category=' . urlencode($product['category'])); ?>"><?php echo htmlspecialchars($product['category']); ?></a>
        <?php endif; ?>
        <span class="material-icons">chevron_right</span>
        <span><?php echo htmlspecialchars($product['product_name']); ?></span>
    </nav>

    <!-- ── Main Layout ───────────────────────────────────────── -->
    <div class="pd-layout">

        <!-- Gallery -->
        <div class="pd-gallery">
            <div class="pd-main-img" id="pdMainImg">
                <img src="<?php echo getProductImage($product['image_url']); ?>"
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                     id="pdMainImgEl">
                <?php if ($product['is_featured']): ?>
                <span class="pd-badge featured">⭐ <?php echo __('featured_badge'); ?></span>
                <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                <span class="pd-badge new"><?php echo __('new_badge'); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($additionalImages)): ?>
            <div class="pd-thumbs">
                <button class="pd-thumb active" onclick="pdChangeImg(this,'<?php echo getProductImage($product['image_url']); ?>')">
                    <img src="<?php echo getProductImage($product['image_url']); ?>" alt="">
                </button>
                <?php foreach ($additionalImages as $img): ?>
                <button class="pd-thumb" onclick="pdChangeImg(this,'<?php echo getProductImage($img); ?>')">
                    <img src="<?php echo getProductImage($img); ?>" alt="">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info Panel -->
        <div class="pd-info">

            <!-- Tags -->
            <div class="pd-tags">
                <span class="pd-tag type"><?php echo ucfirst(htmlspecialchars($product['product_type'])); ?></span>
                <?php if (!empty($product['category'])): ?>
                <span class="pd-tag cat"><?php echo htmlspecialchars($product['category']); ?></span>
                <?php endif; ?>
                <?php if ($product['is_negotiable']): ?>
                <span class="pd-tag nego"><span class="material-icons">handshake</span> <?php echo __('negotiable_label'); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="pd-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>

            <!-- Rating Row -->
            <div class="pd-rating-row">
                <div class="pd-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="material-icons pd-star <?php echo $i <= $reviewAverage ? 'filled' : ''; ?>">
                        <?php echo $i <= $reviewAverage ? 'star' : 'star_border'; ?>
                    </span>
                    <?php endfor; ?>
                </div>
                <a href="#reviews-section" class="pd-rating-link">
                    <?php echo $reviewAverage > 0 ? $reviewAverage . ' / 5' : __('no_ratings_yet'); ?>
                    (<?php echo $reviewTotal; ?> review<?php echo $reviewTotal !== 1 ? 's' : ''; ?>)
                </a>
                <span class="pd-views"><span class="material-icons">visibility</span><?php echo number_format($product['views']); ?></span>
            </div>

            <!-- Price -->
            <div class="pd-price-box">
                <span class="pd-price"><?php echo formatPrice($product['price']); ?></span>
                <span class="pd-unit">per <?php echo htmlspecialchars($product['price_unit'] ?? 'kg'); ?></span>
            </div>

            <?php if ($product['bulk_discount_percent'] && $product['bulk_min_quantity']): ?>
            <div class="pd-bulk-offer">
                <span class="material-icons">local_offer</span>
                Buy <strong><?php echo $product['bulk_min_quantity']; ?>+</strong>
                and save <strong><?php echo $product['bulk_discount_percent']; ?>%</strong>
            </div>
            <?php endif; ?>

            <!-- Stock -->
            <div class="pd-stock">
                <?php if ($product['quantity_available'] > 10): ?>
                <span class="stock-in"><span class="material-icons">check_circle</span>
                    <?php echo __('in_stock_label'); ?> — <?php echo $product['quantity_available'] . ' ' . ($product['unit'] ?? 'units'); ?>
                </span>
                <?php elseif ($product['quantity_available'] > 0): ?>
                <span class="stock-low"><span class="material-icons">warning</span>
                    <?php echo __('only_left_label'); ?> <?php echo $product['quantity_available']; ?> <?php echo __('left_stock'); ?>
                </span>
                <?php else: ?>
                <span class="stock-out"><span class="material-icons">remove_circle</span> <?php echo __('out_of_stock'); ?></span>
                <?php endif; ?>
            </div>

            <!-- Quantity + CTA -->
            <div class="pd-cta-box">
                <div class="pd-qty-row">
                    <label><?php echo __('qty_label'); ?></label>
                    <div class="pd-qty">
                        <button type="button" onclick="pdAdjQty(-1)"><span class="material-icons">remove</span></button>
                        <input type="number" id="pdQtyInput" value="<?php echo max(1, $product['min_order_quantity'] ?? 1); ?>"
                               min="<?php echo max(1, $product['min_order_quantity'] ?? 1); ?>"
                               max="<?php echo $product['quantity_available'] ?? 999; ?>" readonly>
                        <button type="button" onclick="pdAdjQty(1)"><span class="material-icons">add</span></button>
                    </div>
                    <?php if ($product['min_order_quantity'] > 1): ?>
                    <small class="pd-min-order">Min. <?php echo $product['min_order_quantity']; ?></small>
                    <?php endif; ?>
                </div>
                <div class="pd-btns">
                    <button class="btn btn-primary btn-lg" id="pdAddCartBtn"
                            onclick="pdAddToCart(<?php echo $product['product_id']; ?>)"
                            <?php echo $product['quantity_available'] <= 0 ? 'disabled' : ''; ?>>
                        <span class="material-icons">add_shopping_cart</span> <?php echo __('add_to_cart'); ?>
                    </button>
                    <button class="btn btn-accent btn-lg" id="pdBuyNowBtn"
                            onclick="pdBuyNow(<?php echo $product['product_id']; ?>)"
                            <?php echo $product['quantity_available'] <= 0 ? 'disabled' : ''; ?>>
                        <span class="material-icons">flash_on</span> <?php echo __('buy_now_btn'); ?>
                    </button>
                </div>
            </div>

            <!-- Delivery Info -->
            <div class="pd-delivery-info">
                <div class="pd-deli-item">
                    <span class="material-icons">local_shipping</span>
                    <span><?php echo __('delivery_available'); ?></span>
                </div>
                <div class="pd-deli-item">
                    <span class="material-icons">verified_user</span>
                    <span><?php echo __('secure_pay_options'); ?></span>
                </div>
                <div class="pd-deli-item">
                    <span class="material-icons">refresh</span>
                    <span><?php echo __('easy_return_policy'); ?></span>
                </div>
            </div>

            <!-- Seller Card -->
            <div class="pd-seller-card">
                <div class="pd-seller-top">
                    <img src="<?php echo getUserAvatar($product['seller_avatar']); ?>" class="pd-seller-avatar" alt="">
                    <div class="pd-seller-meta">
                        <span class="pd-seller-by"><?php echo __('sold_by'); ?></span>
                        <span class="pd-seller-name"><?php echo htmlspecialchars($product['seller_name'] . ' ' . ($product['seller_last_name'] ?? '')); ?></span>
                        <?php if (!empty($product['seller_district'])): ?>
                        <span class="pd-seller-loc"><span class="material-icons">location_on</span><?php echo htmlspecialchars($product['seller_district']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="pd-seller-actions">
                        <a href="<?php echo shopUrl('pages/farmer-profile.php?id=' . $product['farmer_user_id']); ?>"
                           class="btn btn-ghost btn-sm">
                            <span class="material-icons">person</span> Profile
                        </a>
                        <button class="btn btn-outline btn-sm pd-msg-btn"
                                onclick="openMsgModal(<?php echo $product['farmer_user_id']; ?>, '<?php echo addslashes($product['product_name']); ?>', <?php echo $productId; ?>)">
                            <span class="material-icons">chat</span> Message
                        </button>
                    </div>
                </div>
                <?php if (!empty($product['seller_since'])): ?>
                <div class="pd-seller-since">
                    <?php echo __('member_since'); ?> <?php echo date('M Y', strtotime($product['seller_since'])); ?>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- .pd-info -->
    </div><!-- .pd-layout -->


    <!-- ── Tabs ──────────────────────────────────────────────── -->
    <div class="pd-tabs-section">
        <div class="pd-tabs" role="tablist">
            <button class="pd-tab active" onclick="pdShowTab('description')" role="tab"><?php echo __('description_tab'); ?></button>
            <button class="pd-tab" onclick="pdShowTab('details')" role="tab"><?php echo __('details_tab'); ?></button>
            <button class="pd-tab" onclick="pdShowTab('reviews')" role="tab" id="reviewsTabBtn">
                <?php echo __('reviews_tab'); ?> <span class="pd-tab-count"><?php echo $reviewTotal; ?></span>
            </button>
        </div>

        <!-- Description -->
        <div class="pd-tab-pane active" id="pd-tab-description">
            <?php if (!empty($product['description'])): ?>
            <div class="pd-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
            <?php else: ?>
            <p class="pd-empty"><?php echo __('no_description_provided'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="pd-tab-pane" id="pd-tab-details">
            <table class="pd-details-table">
                <tr><th><?php echo __('product_type_label'); ?></th><td><?php echo ucfirst(htmlspecialchars($product['product_type'])); ?></td></tr>
                <?php if (!empty($product['category'])): ?>
                <tr><th><?php echo __('category'); ?></th><td><?php echo htmlspecialchars($product['category']); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($product['quality_grade'])): ?>
                <tr><th>Quality Grade</th><td>Grade <?php echo htmlspecialchars($product['quality_grade']); ?></td></tr>
                <?php endif; ?>
                <tr><th>Price Unit</th><td><?php echo htmlspecialchars($product['price_unit'] ?? 'kg'); ?></td></tr>
                <?php if (!empty($product['unit'])): ?>
                <tr><th>Measurement Unit</th><td><?php echo htmlspecialchars($product['unit']); ?></td></tr>
                <?php endif; ?>
                <?php if ($product['min_order_quantity'] > 1): ?>
                <tr><th>Minimum Order</th><td><?php echo $product['min_order_quantity']; ?> <?php echo $product['unit'] ?? 'units'; ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($product['location'])): ?>
                <tr><th>Origin</th><td><?php echo htmlspecialchars($product['location']); ?><?php echo !empty($product['district']) ? ', ' . htmlspecialchars($product['district']) : ''; ?></td></tr>
                <?php endif; ?>
                <tr><th>Listed On</th><td><?php echo date('F j, Y', strtotime($product['created_at'])); ?></td></tr>
                <tr><th><?php echo __('negotiable_label'); ?></th><td><?php echo $product['is_negotiable'] ? __('yes') : __('no_label'); ?></td></tr>
            </table>
        </div>

        <!-- Reviews -->
        <div class="pd-tab-pane" id="pd-tab-reviews" id="reviews-section">

            <!-- Summary Bar -->
            <div class="review-summary">
                <div class="review-score">
                    <span class="rs-number"><?php echo $reviewAverage > 0 ? $reviewAverage : '—'; ?></span>
                    <div class="rs-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="material-icons pd-star <?php echo $i <= $reviewAverage ? 'filled' : ''; ?>">
                            <?php echo $i <= $reviewAverage ? 'star' : 'star_border'; ?>
                        </span>
                        <?php endfor; ?>
                    </div>
                    <span class="rs-total"><?php echo $reviewTotal; ?> review<?php echo $reviewTotal !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="review-bars">
                    <?php
                    foreach ([5,4,3,2,1] as $star):
                        $count = intval($reviewSummary[$star === 5 ? 'five' : ($star === 4 ? 'four' : ($star === 3 ? 'three' : ($star === 2 ? 'two' : 'one')))] ?? 0);
                        $pct   = $reviewTotal > 0 ? round($count / $reviewTotal * 100) : 0;
                    ?>
                    <div class="rb-row">
                        <span><?php echo $star; ?> <span class="material-icons" style="font-size:.9rem;color:#fbbf24;">star</span></span>
                        <div class="rb-track"><div class="rb-fill" style="width:<?php echo $pct; ?>%"></div></div>
                        <span><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Review Form -->
            <div class="review-form-section">
                <?php if (!isShopLoggedIn()): ?>
                <div class="review-login-prompt">
                    <span class="material-icons">rate_review</span>
                    <p>Share your experience with this product</p>
                    <a href="<?php echo shopUrl('auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); ?>" class="btn btn-primary">
                        <?php echo __('login_to_review'); ?>
                    </a>
                </div>
                <?php elseif ($userReview): ?>
                <div class="review-done">
                    <span class="material-icons">check_circle</span>
                    You reviewed this product with <?php echo $userReview['rating']; ?> star<?php echo $userReview['rating'] != 1 ? 's' : ''; ?>.
                </div>
                <?php else: ?>
                <div class="review-write">
                    <h4><?php echo __('write_review_title'); ?></h4>
                    <div id="reviewAlert" class="alert" style="display:none;"></div>
                    <form id="reviewForm" onsubmit="submitReview(event)">
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">

                        <div class="rv-star-picker" id="rvStarPicker">
                            <label><?php echo __('your_rating_label'); ?></label>
                            <div class="rv-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="rv-star" data-v="<?php echo $i; ?>" onclick="setReviewStar(<?php echo $i; ?>)" title="<?php echo $i; ?> star<?php echo $i>1?'s':''; ?>">
                                    <span class="material-icons">star_border</span>
                                </button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="rvRatingInput" value="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?php echo __('your_review'); ?> *</label>
                            <textarea name="review_text" class="form-control" rows="4" required placeholder="Describe the product quality, delivery, and your overall experience..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" id="rvSubmitBtn">
                            <span class="material-icons">rate_review</span> <?php echo __('submit_review_btn'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Review List -->
            <div id="reviewList">
                <?php if (empty($reviews)): ?>
                <div class="pd-empty" id="noReviewsMsg">
                    <span class="material-icons">reviews</span>
                    <p><?php echo __('no_reviews_yet'); ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($reviews as $rv): ?>
                <div class="rv-card">
                    <div class="rv-header">
                        <div class="rv-user">
                            <div class="rv-avatar"><?php echo strtoupper(substr($rv['first_name'], 0, 1)); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($rv['first_name'] . ' ' . substr($rv['last_name'] ?? '', 0, 1) . '.'); ?></strong>
                                <?php if ($rv['is_verified_purchase']): ?>
                                <span class="rv-verified"><span class="material-icons">verified</span> <?php echo __('verified_purchase'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rv-meta">
                            <div class="rv-stars-small">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="material-icons" style="color:<?php echo $i <= $rv['rating'] ? '#fbbf24' : '#d1d5db'; ?>;font-size:1rem;">star</span>
                                <?php endfor; ?>
                            </div>
                            <span class="rv-date"><?php echo date('M j, Y', strtotime($rv['created_at'])); ?></span>
                        </div>
                    </div>
                    <p class="rv-body"><?php echo nl2br(htmlspecialchars($rv['review_text'])); ?></p>
                    <div class="rv-helpful">
                        <button class="rv-helpful-btn" onclick="markHelpful(<?php echo $rv['review_id']; ?>, this)">
                            <span class="material-icons">thumb_up</span>
                            Helpful (<?php echo $rv['helpful_count']; ?>)
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($reviewTotal > 5): ?>
                <div style="text-align:center;margin-top:1rem;">
                    <button class="btn btn-ghost" id="loadMoreReviews" onclick="loadMoreReviews()">
                        <?php echo __('load_more_reviews'); ?>
                    </button>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- reviews tab -->
    </div>


    <!-- ── Recently Viewed ───────────────────────────────────── -->
    <section class="pd-recently-viewed" id="recentlyViewedSection" style="display:none;margin-bottom:2rem;">
        <h2><?php echo __('recently_viewed'); ?></h2>
        <div class="product-grid" id="recentlyViewedGrid"></div>
    </section>

    <!-- ── Related Products ───────────────────────────────────── -->
    <?php if (!empty($relatedProducts)): ?>
    <section class="pd-related">
        <h2><?php echo __('you_might_also_like'); ?></h2>
        <div class="product-grid">
            <?php foreach ($relatedProducts as $r): ?>
            <div class="product-card">
                <div class="product-card-image">
                    <a href="<?php echo shopUrl('product/' . $r['product_id']); ?>">
                        <img src="<?php echo getProductImage($r['image_url']); ?>"
                             alt="<?php echo htmlspecialchars($r['product_name']); ?>">
                    </a>
                </div>
                <div class="product-card-body">
                    <span class="product-category"><?php echo htmlspecialchars($r['category'] ?? $r['product_type']); ?></span>
                    <h3 class="product-title">
                        <a href="<?php echo shopUrl('product/' . $r['product_id']); ?>"><?php echo htmlspecialchars($r['product_name']); ?></a>
                    </h3>
                    <div class="product-price">
                        <span class="product-price-current"><?php echo formatPrice($r['price']); ?></span>
                        <span class="product-price-unit">/ <?php echo htmlspecialchars($r['price_unit'] ?? 'kg'); ?></span>
                    </div>
                </div>
                <div class="product-card-footer">
                    <button class="btn btn-primary" onclick="addToCart(<?php echo $r['product_id']; ?>)">
                        <span class="material-icons">add_shopping_cart</span> <?php echo __('add_to_cart'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div><!-- .pd-page -->


<!-- ── Message Seller Modal ──────────────────────────────────── -->
<div class="pd-modal-overlay" id="msgModalOverlay" onclick="closeMsgModal()"></div>
<div class="pd-modal" id="msgModal">
    <div class="pd-modal-header">
        <h3><span class="material-icons">chat</span> <?php echo __('message_seller_title'); ?></h3>
        <button class="btn-icon" onclick="closeMsgModal()"><span class="material-icons">close</span></button>
    </div>
    <div class="pd-modal-body">
        <div id="msgModalAlert" class="alert" style="display:none;"></div>
        <p id="msgModalDesc" class="text-muted pd-msg-product-ref"></p>
        <div class="form-group">
            <label class="form-label">Your Message *</label>
            <textarea id="msgModalText" class="form-control" rows="4"
                      placeholder="Hi, I'm interested in this product. Can you tell me more about..."></textarea>
        </div>
    </div>
    <div class="pd-modal-footer">
        <button class="btn btn-ghost" onclick="closeMsgModal()"><?php echo __('cancel'); ?></button>
        <button class="btn btn-primary" id="msgModalSendBtn" onclick="sendMsgModal()">
            <span class="material-icons">send</span> Send Message
        </button>
    </div>
</div>


<style>
/* ─── Page Shell ─────────────────────────────────────────────────── */
.pd-page { padding: 1.5rem 1rem 3rem; }

.pd-breadcrumb {
    display: flex; align-items: center; flex-wrap: wrap; gap: 4px;
    font-size: .85rem; color: var(--gray-500); margin-bottom: 1.5rem;
}
.pd-breadcrumb a { color: var(--gray-500); }
.pd-breadcrumb a:hover { color: var(--primary); }
.pd-breadcrumb .material-icons { font-size: .95rem; }

/* ─── Main Layout ────────────────────────────────────────────────── */
.pd-layout {
    display: grid; grid-template-columns: 1fr;
    gap: 2rem; margin-bottom: 2.5rem;
}
@media (min-width: 1024px) {
    .pd-layout { grid-template-columns: 1fr 1fr; }
}

/* ─── Gallery ────────────────────────────────────────────────────── */
.pd-gallery { position: sticky; top: 90px; height: fit-content; }
.pd-main-img {
    position: relative; border-radius: var(--radius-lg);
    overflow: hidden; background: var(--white);
    box-shadow: var(--shadow-md); margin-bottom: .75rem;
}
.pd-main-img img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.pd-badge {
    position: absolute; top: .75rem; left: .75rem;
    padding: .3rem .75rem; border-radius: 999px;
    font-size: .8rem; font-weight: 600;
}
.pd-badge.featured { background: #fff3cd; color: #856404; }
.pd-badge.new      { background: #d1fae5; color: #065f46; }
.pd-thumbs {
    display: flex; gap: .5rem; overflow-x: auto; padding: .25rem;
}
.pd-thumb {
    flex-shrink: 0; width: 68px; height: 68px;
    border-radius: var(--radius-md); overflow: hidden;
    border: 2px solid transparent; cursor: pointer;
    transition: border-color .15s;
}
.pd-thumb.active, .pd-thumb:hover { border-color: var(--primary); }
.pd-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* ─── Info Panel ─────────────────────────────────────────────────── */
.pd-tags { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .75rem; }
.pd-tag {
    padding: .2rem .65rem; border-radius: 999px;
    font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
}
.pd-tag.type { background: var(--primary-light); color: #fff; }
.pd-tag.cat  { background: var(--gray-100);       color: var(--gray-600); }
.pd-tag.nego { background: rgba(255,140,0,.12); color: var(--accent);
               display: inline-flex; align-items: center; gap: 3px; }
.pd-tag.nego .material-icons { font-size: .95rem; }

.pd-title { font-size: 1.6rem; font-weight: 700; color: var(--gray-800); line-height: 1.3; margin-bottom: .75rem; }

.pd-rating-row { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; font-size: .875rem; }
.pd-stars { display: flex; }
.pd-star { font-size: 1.15rem; color: var(--gray-200); }
.pd-star.filled { color: #fbbf24; }
.pd-rating-link { color: var(--gray-500); text-decoration: underline; }
.pd-rating-link:hover { color: var(--primary); }
.pd-views { display: flex; align-items: center; gap: 3px; color: var(--gray-400); font-size: .8rem; margin-left: auto; }
.pd-views .material-icons { font-size: 1rem; }

.pd-price-box { display: flex; align-items: baseline; gap: .5rem; margin-bottom: .75rem; }
.pd-price { font-size: 2rem; font-weight: 800; color: var(--primary); }
.pd-unit  { color: var(--gray-400); font-size: 1rem; }

.pd-bulk-offer {
    display: flex; align-items: center; gap: .5rem;
    background: rgba(143,188,70,.1); color: var(--primary-dark);
    padding: .6rem 1rem; border-radius: var(--radius-md);
    font-size: .875rem; margin-bottom: .75rem;
}
.pd-bulk-offer .material-icons { color: var(--secondary); }

.pd-stock { margin-bottom: 1rem; }
.stock-in  { display: inline-flex; align-items: center; gap: .4rem; background: #d1fae5; color: #065f46; padding: .4rem .9rem; border-radius: 999px; font-size: .875rem; font-weight: 500; }
.stock-low { display: inline-flex; align-items: center; gap: .4rem; background: #fef3c7; color: #92400e; padding: .4rem .9rem; border-radius: 999px; font-size: .875rem; font-weight: 500; }
.stock-out { display: inline-flex; align-items: center; gap: .4rem; background: #fee2e2; color: #991b1b; padding: .4rem .9rem; border-radius: 999px; font-size: .875rem; font-weight: 500; }
.stock-in .material-icons, .stock-low .material-icons, .stock-out .material-icons { font-size: 1rem; }

.pd-cta-box { background: var(--gray-50); padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1rem; }
.pd-qty-row { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.pd-qty-row label { font-weight: 600; color: var(--gray-700); }
.pd-qty {
    display: flex; align-items: center;
    background: #fff; border: 2px solid var(--gray-200); border-radius: var(--radius-md);
}
.pd-qty button { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; color: var(--gray-600); }
.pd-qty button:hover { background: var(--gray-100); }
.pd-qty input { width: 56px; text-align: center; border: none; font-size: 1.1rem; font-weight: 600; }
.pd-min-order { font-size: .8rem; color: var(--gray-500); }
.pd-btns { display: flex; gap: .75rem; flex-wrap: wrap; }
.pd-btns .btn { flex: 1; min-width: 130px; justify-content: center; }

.pd-delivery-info {
    display: flex; flex-direction: column; gap: .4rem;
    padding: .9rem 1rem; border: 1px solid var(--gray-100);
    border-radius: var(--radius-md); margin-bottom: 1rem;
}
.pd-deli-item { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--gray-600); }
.pd-deli-item .material-icons { font-size: 1rem; color: var(--primary); }

.pd-seller-card {
    border: 1px solid var(--gray-200); border-radius: var(--radius-lg);
    padding: 1rem; background: #fff;
}
.pd-seller-top { display: flex; align-items: center; gap: .75rem; margin-bottom: .5rem; }
.pd-seller-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.pd-seller-meta { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.pd-seller-by  { font-size: .75rem; color: var(--gray-400); }
.pd-seller-name { font-weight: 700; color: var(--gray-800); font-size: .95rem; }
.pd-seller-loc { display: flex; align-items: center; gap: 2px; font-size: .8rem; color: var(--gray-500); }
.pd-seller-loc .material-icons { font-size: .9rem; }
.pd-seller-since { font-size: .78rem; color: var(--gray-400); }
.pd-msg-btn { white-space: nowrap; flex-shrink: 0; }
.pd-seller-actions { display: flex; gap: .5rem; flex-shrink: 0; flex-wrap: wrap; }

/* ─── Tabs ───────────────────────────────────────────────────────── */
.pd-tabs-section { margin-bottom: 3rem; }
.pd-tabs {
    display: flex; gap: 0; border-bottom: 2px solid var(--gray-200);
    margin-bottom: 0; overflow-x: auto;
}
.pd-tab {
    padding: .75rem 1.5rem; font-weight: 600; color: var(--gray-500);
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    white-space: nowrap; transition: all .15s; display: flex; align-items: center; gap: .4rem;
}
.pd-tab:hover  { color: var(--primary); }
.pd-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.pd-tab-count { background: var(--primary); color: #fff; font-size: .7rem; padding: .1rem .4rem; border-radius: 999px; }
.pd-tab-pane {
    display: none; padding: 1.5rem;
    background: #fff; border: 1px solid var(--gray-100);
    border-top: none; border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}
.pd-tab-pane.active { display: block; }
.pd-description { line-height: 1.85; color: var(--gray-700); }
.pd-details-table { width: 100%; border-collapse: collapse; }
.pd-details-table th, .pd-details-table td { padding: .75rem 1rem; text-align: left; border-bottom: 1px solid var(--gray-100); font-size: .9rem; }
.pd-details-table th { width: 40%; color: var(--gray-500); font-weight: 500; }
.pd-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; color: var(--gray-400); padding: 2rem; text-align: center; }
.pd-empty .material-icons { font-size: 2.5rem; }

/* ─── Review Summary ─────────────────────────────────────────────── */
.review-summary { display: flex; gap: 2rem; flex-wrap: wrap; align-items: flex-start; padding: 1.5rem 0; border-bottom: 1px solid var(--gray-100); margin-bottom: 1.5rem; }
.review-score { display: flex; flex-direction: column; align-items: center; gap: .3rem; min-width: 100px; }
.rs-number { font-size: 3rem; font-weight: 800; color: var(--gray-800); line-height: 1; }
.rs-stars  { display: flex; }
.rs-total  { font-size: .8rem; color: var(--gray-500); text-align: center; }
.review-bars { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: .4rem; }
.rb-row { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--gray-600); }
.rb-track { flex: 1; height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden; }
.rb-fill  { height: 100%; background: #fbbf24; border-radius: 4px; }

/* ─── Review Form ────────────────────────────────────────────────── */
.review-form-section { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--gray-100); }
.review-login-prompt, .review-done {
    display: flex; flex-direction: column; align-items: center; gap: .75rem;
    background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg);
    text-align: center; color: var(--gray-600);
}
.review-login-prompt .material-icons { font-size: 2rem; color: var(--primary); }
.review-done { flex-direction: row; justify-content: flex-start; background: #d1fae5; color: #065f46; padding: .75rem 1rem; }
.review-done .material-icons { color: #059669; }
.review-write h4 { margin-bottom: 1rem; font-size: 1.1rem; }
.rv-star-picker { margin-bottom: 1rem; }
.rv-star-picker label { display: block; font-size: .875rem; font-weight: 500; color: var(--gray-700); margin-bottom: .4rem; }
.rv-stars { display: flex; gap: .25rem; }
.rv-star { font-size: 0; padding: 0; }
.rv-star .material-icons { font-size: 2rem; color: var(--gray-300); transition: color .1s; }
.rv-star.active .material-icons, .rv-star:hover .material-icons,
.rv-star:hover ~ .rv-star .material-icons { color: #fbbf24 !important; }

/* ─── Review Cards ───────────────────────────────────────────────── */
.rv-card { border-bottom: 1px solid var(--gray-100); padding: 1rem 0; }
.rv-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
.rv-user   { display: flex; align-items: center; gap: .6rem; }
.rv-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
.rv-verified { display: inline-flex; align-items: center; gap: 3px; color: #059669; font-size: .75rem; }
.rv-verified .material-icons { font-size: .85rem; }
.rv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.rv-stars-small { display: flex; }
.rv-date { font-size: .78rem; color: var(--gray-400); }
.rv-title { font-weight: 600; margin-bottom: .3rem; color: var(--gray-800); }
.rv-body  { color: var(--gray-600); line-height: 1.7; font-size: .9rem; }
.rv-helpful { margin-top: .5rem; }
.rv-helpful-btn { display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem; color: var(--gray-400); padding: .2rem .6rem; border-radius: 999px; transition: all .15s; }
.rv-helpful-btn:hover { background: var(--gray-100); color: var(--gray-700); }
.rv-helpful-btn .material-icons { font-size: .95rem; }

/* ─── Related ────────────────────────────────────────────────────── */
.pd-related { margin-bottom: 2rem; }
.pd-related h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 1.25rem; color: var(--gray-800); }

/* ─── Message Modal ──────────────────────────────────────────────── */
.pd-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 3000; display: none; opacity: 0; transition: opacity .2s;
}
.pd-modal-overlay.active { display: block; opacity: 1; }
.pd-modal {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%) scale(.95);
    background: #fff; border-radius: var(--radius-xl, 1rem);
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    width: min(520px, 94vw); z-index: 3001;
    display: none; opacity: 0; transition: all .2s;
}
.pd-modal.active { display: block; opacity: 1; transform: translate(-50%,-50%) scale(1); }
.pd-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-100);
}
.pd-modal-header h3 { display: flex; align-items: center; gap: .4rem; font-size: 1.1rem; }
.pd-modal-body   { padding: 1.5rem; }
.pd-modal-footer { display: flex; gap: .75rem; justify-content: flex-end; padding: 1rem 1.5rem; border-top: 1px solid var(--gray-100); }
.pd-msg-product-ref { font-size: .85rem; color: var(--gray-500); margin-bottom: .75rem; }
</style>


<script>
// Track this product as recently viewed
trackRecentlyViewed(
    <?php echo $productId; ?>,
    <?php echo json_encode($product['product_name']); ?>,
    <?php echo json_encode(getProductImage($product['image_url'])); ?>,
    '<?php echo shopUrl('product/' . $productId); ?>'
);

// ─── Image Gallery ────────────────────────────────────────────────
function pdChangeImg(btn, url) {
    document.getElementById('pdMainImgEl').src = url;
    document.querySelectorAll('.pd-thumb').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
}

// ─── Quantity ─────────────────────────────────────────────────────
function pdAdjQty(delta) {
    const input = document.getElementById('pdQtyInput');
    let v = parseInt(input.value) + delta;
    v = Math.max(parseInt(input.min)||1, Math.min(v, parseInt(input.max)||999));
    input.value = v;
}

// ─── Add to Cart / Buy Now ────────────────────────────────────────
async function pdAddToCart(productId) {
    const qty = parseInt(document.getElementById('pdQtyInput').value) || 1;
    const btn = document.getElementById('pdAddCartBtn');
    setLoading(btn, true);
    const result = await addToCart(productId, qty);
    setLoading(btn, false);
}

async function pdBuyNow(productId) {
    const qty = parseInt(document.getElementById('pdQtyInput').value) || 1;
    const btn = document.getElementById('pdBuyNowBtn');
    setLoading(btn, true);
    const result = await addToCart(productId, qty);
    setLoading(btn, false);
    if (result && result.success) {
        requireLoginCheckout();
    }
}

// ─── Tabs ─────────────────────────────────────────────────────────
function pdShowTab(name) {
    document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pd-tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelector(`.pd-tab[onclick="pdShowTab('${name}')"]`).classList.add('active');
    document.getElementById('pd-tab-' + name).classList.add('active');
}

// ─── Review Star Picker ───────────────────────────────────────────
function setReviewStar(v) {
    document.getElementById('rvRatingInput').value = v;
    document.querySelectorAll('.rv-star').forEach((btn, idx) => {
        const icon = btn.querySelector('.material-icons');
        icon.textContent = idx < v ? 'star' : 'star_border';
        icon.style.color  = idx < v ? '#fbbf24' : '';
    });
}

// ─── Submit Review ────────────────────────────────────────────────
async function submitReview(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = document.getElementById('rvSubmitBtn');
    const alertEl = document.getElementById('reviewAlert');

    const rating = parseInt(form.rating.value);
    if (rating < 1) {
        showAlertEl(alertEl, 'Please select a star rating.', 'error');
        return;
    }

    setLoading(btn, true);
    const data = {
        action:      'submit',
        product_id:  form.product_id.value,
        rating:      rating,
        review_text: form.review_text.value
    };

    const res = await shopPost(window.SHOP_REV_AJAX, data);
    setLoading(btn, false);

    if (res.success) {
        showAlertEl(alertEl, res.message, 'success');
        form.closest('.review-write').innerHTML = `
            <div class="review-done">
                <span class="material-icons">check_circle</span> ${escHtml(res.message)}
            </div>`;
        // Update tab count
        const countEl = document.querySelector('.pd-tab-count');
        if (countEl) countEl.textContent = parseInt(countEl.textContent||0) + 1;
    } else {
        showAlertEl(alertEl, res.message, 'error');
    }
}

// ─── Mark Helpful ─────────────────────────────────────────────────
async function markHelpful(reviewId, btn) {
    btn.disabled = true;
    const res = await shopPost(window.SHOP_REV_AJAX, { action: 'helpful', review_id: reviewId });
    if (res.success) {
        const match = btn.innerHTML.match(/\((\d+)\)/);
        if (match) btn.innerHTML = btn.innerHTML.replace(/\(\d+\)/, `(${parseInt(match[1])+1})`);
    }
}

// ─── Load More Reviews ────────────────────────────────────────────
let rvPage = 1;
async function loadMoreReviews() {
    rvPage++;
    const btn = document.getElementById('loadMoreReviews');
    if (btn) setLoading(btn, true);

    const url = window.SHOP_REV_AJAX + '?action=list&product_id=<?php echo $productId; ?>&page=' + rvPage;
    const res = await fetch(url).then(r => r.json());

    if (btn) setLoading(btn, false);
    if (!res.success || !res.reviews.length) {
        if (btn) { btn.textContent = 'No more reviews'; btn.disabled = true; }
        return;
    }

    const list = document.getElementById('reviewList');
    res.reviews.forEach(rv => {
        const stars = Array.from({length:5},(_,i)=>`<span class="material-icons" style="color:${i<rv.rating?'#fbbf24':'#d1d5db'};font-size:1rem;">star</span>`).join('');
        const verified = rv.is_verified_purchase ? `<span class="rv-verified"><span class="material-icons">verified</span> Verified</span>` : '';
        const div = document.createElement('div');
        div.className = 'rv-card';
        div.innerHTML = `
            <div class="rv-header">
                <div class="rv-user">
                    <div class="rv-avatar">${escHtml((rv.first_name||'?')[0].toUpperCase())}</div>
                    <div><strong>${escHtml(rv.first_name + ' ' + (rv.last_name||'')[0] + '.')}</strong>${verified}</div>
                </div>
                <div class="rv-meta"><div class="rv-stars-small">${stars}</div>
                <span class="rv-date">${new Date(rv.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span></div>
            </div>
            <p class="rv-body">${escHtml(rv.review_text).replace(/\n/g,'<br>')}</p>
            <div class="rv-helpful">
                <button class="rv-helpful-btn" onclick="markHelpful(${rv.review_id},this)">
                    <span class="material-icons">thumb_up</span> Helpful (${rv.helpful_count||0})
                </button>
            </div>`;
        list.insertBefore(div, btn?.parentElement || null);
    });
}

// ─── Message Seller Modal ─────────────────────────────────────────
let _msgFarmerId = 0, _msgProductId = 0;

function openMsgModal(farmerId, productName, productId) {
    _msgFarmerId  = farmerId;
    _msgProductId = productId;

    if (!window.SHOP_LOGGED_IN) {
        const ret = encodeURIComponent(window.location.href);
        window.location.href = (window.SHOP_LOGIN_URL||'') + '?redirect=' + ret;
        return;
    }

    document.getElementById('msgModalDesc').textContent = 'Re: ' + productName;
    document.getElementById('msgModalText').value = '';
    const alertEl = document.getElementById('msgModalAlert');
    alertEl.style.display = 'none';

    document.getElementById('msgModalOverlay').classList.add('active');
    document.getElementById('msgModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('msgModalText').focus(), 100);
}

function closeMsgModal() {
    document.getElementById('msgModalOverlay').classList.remove('active');
    document.getElementById('msgModal').classList.remove('active');
    document.body.style.overflow = '';
}

async function sendMsgModal() {
    const text    = document.getElementById('msgModalText').value.trim();
    const btn     = document.getElementById('msgModalSendBtn');
    const alertEl = document.getElementById('msgModalAlert');

    if (!text) { showAlertEl(alertEl, 'Please enter a message.', 'error'); return; }

    setLoading(btn, true);
    const res = await shopPost(window.SHOP_MSG_AJAX, {
        action:     'start_conversation',
        farmer_id:  _msgFarmerId,
        product_id: _msgProductId,
        message:    text
    });
    setLoading(btn, false);

    if (res.success) {
        showAlertEl(alertEl, 'Message sent! The farmer will reply soon.', 'success');
        setTimeout(() => {
            closeMsgModal();
            const msgUrl = window.SHOP_URL + 'messages/' + res.conversation_id;
            window.location.href = msgUrl;
        }, 1200);
    } else {
        showAlertEl(alertEl, res.message || 'Failed to send message.', 'error');
    }
}

// ─── Utility ──────────────────────────────────────────────────────
function showAlertEl(el, msg, type) {
    el.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
    el.textContent = msg;
    el.style.display = 'block';
}

// Jump to reviews tab from rating link
document.querySelector('.pd-rating-link')?.addEventListener('click', (e) => {
    e.preventDefault();
    pdShowTab('reviews');
    document.querySelector('.pd-tabs-section')?.scrollIntoView({ behavior: 'smooth' });
});

// Recently Viewed
(function renderRecentlyViewed() {
    const items = getRecentlyViewed(<?php echo $productId; ?>).slice(0, 4);
    if (!items.length) return;
    const section = document.getElementById('recentlyViewedSection');
    const grid    = document.getElementById('recentlyViewedGrid');
    if (!section || !grid) return;
    grid.innerHTML = items.map(item => `
        <div class="product-card">
            <div class="product-card-image">
                <a href="${item.url}"><img src="${item.img}" alt="${escHtml(item.name)}" loading="lazy"></a>
            </div>
            <div class="product-card-body">
                <h3 class="product-title"><a href="${item.url}">${escHtml(item.name)}</a></h3>
            </div>
            <div class="product-card-footer">
                <a href="${item.url}" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">View</a>
            </div>
        </div>`).join('');
    section.style.display = 'block';
})();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
