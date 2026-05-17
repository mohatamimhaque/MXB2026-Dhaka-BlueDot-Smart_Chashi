<?php
/**
 * Farmer Public Profile Page
 * Shows farmer info, products, and embedded real-time chat.
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();

$farmerId = intval($_GET['id'] ?? 0);
if (!$farmerId) {
    setFlashMessage('error', 'Farmer not found.');
    shopRedirect('pages/products.php');
}

// Get farmer (must be a farmer role)
$farmer = $db->single(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.profile_img_url,
            u.is_verified, u.created_at as member_since,
            fp.district, fp.region, fp.sub_district, fp.village, fp.address,
            fp.farm_size, fp.experience_level, fp.primary_crops,
            fp.farming_type, fp.soil_type
     FROM users u
     LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id
     WHERE u.user_id = ? AND u.role = 'farmer' AND u.is_active = 1",
    [$farmerId]
);
if (!$farmer) {
    setFlashMessage('error', 'Farmer profile not found.');
    shopRedirect('pages/products.php');
}

// Products by this farmer
$products = $db->resultSet(
    "SELECT * FROM marketplace_products
     WHERE seller_id = ? AND status = 'available'
     ORDER BY is_featured DESC, created_at DESC",
    [$farmerId]
);

// Rating aggregates across farmer's products
$ratingStats = $db->single(
    "SELECT COUNT(r.review_id) as total_reviews, AVG(r.rating) as avg_rating
     FROM product_reviews r
     JOIN marketplace_products mp ON r.product_id = mp.product_id
     WHERE mp.seller_id = ? AND r.status = 'active' AND r.parent_review_id IS NULL",
    [$farmerId]
);

// Existing conversation for this customer + farmer (if logged in)
$existingConvo = null;
$messages      = [];
if (isShopLoggedIn()) {
    $existingConvo = $db->single(
        "SELECT * FROM shop_conversations
         WHERE farmer_id = ? AND customer_id = ? AND customer_type = 'general' AND product_id IS NULL
         ORDER BY last_message_at DESC LIMIT 1",
        [$farmerId, $_SESSION['shop_user_id']]
    );
    if ($existingConvo) {
        $messages = $db->resultSet(
            "SELECT * FROM shop_messages WHERE conversation_id = ? ORDER BY created_at ASC",
            [$existingConvo['conversation_id']]
        );
        // Mark as read
        $db->query("UPDATE shop_conversations SET customer_unread = 0 WHERE conversation_id = ?")
           ->bind(1, $existingConvo['conversation_id'])->execute();
        $db->query("UPDATE shop_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'farmer'")
           ->bind(1, $existingConvo['conversation_id'])->execute();
    }
}

$farmerName = trim($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? ''));
$pageTitle  = $farmerName . ' — Farmer Profile';

include __DIR__ . '/../layouts/header.php';
?>

<div class="fp-page container">

    <!-- Farmer Hero -->
    <div class="fp-hero">
        <div class="fp-hero-bg"></div>
        <div class="fp-hero-content">
            <div class="fp-avatar-wrap">
                <img src="<?php echo getUserAvatar($farmer['profile_img_url'] ?? ''); ?>"
                     alt="<?php echo htmlspecialchars($farmerName); ?>"
                     class="fp-avatar">
                <?php if ($farmer['is_verified']): ?>
                <span class="fp-verified" title="Verified Farmer">
                    <span class="material-icons">verified</span>
                </span>
                <?php endif; ?>
            </div>
            <div class="fp-hero-info">
                <h1 class="fp-name">
                    <?php echo htmlspecialchars($farmerName); ?>
                    <?php if ($farmer['is_verified']): ?>
                    <span class="verified-badge">
                        <span class="material-icons">verified</span> Verified
                    </span>
                    <?php endif; ?>
                </h1>
                <?php if ($farmer['district'] || $farmer['region']): ?>
                <p class="fp-location">
                    <span class="material-icons">location_on</span>
                    <?php
                    $loc = array_filter([$farmer['village'] ?? null, $farmer['sub_district'] ?? null, $farmer['district'] ?? null, $farmer['region'] ?? null]);
                    echo htmlspecialchars(implode(', ', $loc));
                    ?>
                </p>
                <?php endif; ?>
                <div class="fp-meta">
                    <span><span class="material-icons">agriculture</span> Farmer since <?php echo date('Y', strtotime($farmer['member_since'])); ?></span>
                    <?php if ($farmer['experience_level']): ?>
                    <span><span class="material-icons">stars</span> <?php echo ucfirst($farmer['experience_level']); ?> level</span>
                    <?php endif; ?>
                    <?php if ($farmer['farming_type']): ?>
                    <span><span class="material-icons">eco</span> <?php echo htmlspecialchars($farmer['farming_type']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Stats -->
            <div class="fp-stats">
                <div class="fp-stat">
                    <strong><?php echo count($products); ?></strong>
                    <span>Products</span>
                </div>
                <div class="fp-stat">
                    <strong><?php echo number_format($ratingStats['avg_rating'] ?? 0, 1); ?></strong>
                    <span>Rating</span>
                </div>
                <div class="fp-stat">
                    <strong><?php echo $ratingStats['total_reviews'] ?? 0; ?></strong>
                    <span>Reviews</span>
                </div>
            </div>
        </div>
    </div>

    <div class="fp-body">
        <!-- Left: Products -->
        <div class="fp-main">

            <!-- About -->
            <?php if ($farmer['primary_crops'] || $farmer['farm_size'] || $farmer['address']): ?>
            <div class="fp-section">
                <h2><span class="material-icons">info</span> About</h2>
                <div class="fp-about-grid">
                    <?php if ($farmer['primary_crops']): ?>
                    <div class="fp-about-item">
                        <span class="material-icons">grass</span>
                        <div>
                            <strong>Primary Crops</strong>
                            <span><?php echo htmlspecialchars($farmer['primary_crops']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($farmer['farm_size']): ?>
                    <div class="fp-about-item">
                        <span class="material-icons">landscape</span>
                        <div>
                            <strong>Farm Size</strong>
                            <span><?php echo htmlspecialchars($farmer['farm_size']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($farmer['soil_type']): ?>
                    <div class="fp-about-item">
                        <span class="material-icons">terrain</span>
                        <div>
                            <strong>Soil Type</strong>
                            <span><?php echo htmlspecialchars($farmer['soil_type']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($farmer['address']): ?>
                    <div class="fp-about-item">
                        <span class="material-icons">home</span>
                        <div>
                            <strong>Address</strong>
                            <span><?php echo htmlspecialchars($farmer['address']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Products -->
            <div class="fp-section">
                <h2><span class="material-icons">storefront</span> Products (<?php echo count($products); ?>)</h2>
                <?php if (empty($products)): ?>
                <div class="fp-no-products">
                    <span class="material-icons">inventory_2</span>
                    <p>No products listed yet.</p>
                </div>
                <?php else: ?>
                <div class="fp-product-grid">
                    <?php foreach ($products as $p): ?>
                    <div class="product-card">
                        <div class="product-card-image">
                            <a href="<?php echo shopUrl('product/' . $p['product_id']); ?>">
                                <img src="<?php echo getProductImage($p['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($p['product_name']); ?>" loading="lazy">
                            </a>
                            <?php if ($p['is_featured']): ?>
                                <span class="product-badge badge-accent">Featured</span>
                            <?php elseif (strtotime($p['created_at']) > strtotime('-7 days')): ?>
                                <span class="product-badge badge-success">New</span>
                            <?php endif; ?>
                            <button class="product-wishlist" data-wishlist-id="<?php echo $p['product_id']; ?>" title="Wishlist">
                                <span class="material-icons">favorite_border</span>
                            </button>
                        </div>
                        <div class="product-card-body">
                            <span class="product-category"><?php echo htmlspecialchars($p['category'] ?? $p['product_type']); ?></span>
                            <h3 class="product-title">
                                <a href="<?php echo shopUrl('product/' . $p['product_id']); ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?>
                                </a>
                            </h3>
                            <?php if (!empty($p['location'])): ?>
                            <div class="product-location">
                                <span class="material-icons">location_on</span>
                                <?php echo htmlspecialchars($p['location']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($p['average_rating'] > 0): ?>
                            <div class="product-rating">
                                <span class="material-icons" style="color:#f59e0b;font-size:14px;">star</span>
                                <span><?php echo number_format($p['average_rating'], 1); ?></span>
                                <span style="color:var(--gray-400);font-size:12px;">(<?php echo $p['review_count']; ?>)</span>
                            </div>
                            <?php endif; ?>
                            <div class="product-price">
                                <span class="product-price-current"><?php echo formatPrice($p['price']); ?></span>
                                <span class="product-price-unit">/ <?php echo htmlspecialchars($p['price_unit'] ?? 'kg'); ?></span>
                            </div>
                        </div>
                        <div class="product-card-footer">
                            <button class="btn btn-primary" onclick="addToCart(<?php echo $p['product_id']; ?>)">
                                <span class="material-icons">add_shopping_cart</span> Add to Cart
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Chat Sidebar -->
        <aside class="fp-sidebar">
            <div class="fp-chat-card" id="fpChatCard">
                <div class="fp-chat-header">
                    <img src="<?php echo getUserAvatar($farmer['profile_img_url'] ?? ''); ?>" alt="">
                    <div>
                        <strong><?php echo htmlspecialchars($farmerName); ?></strong>
                        <span>Farmer</span>
                    </div>
                    <?php if ($farmer['is_verified']): ?>
                    <span class="material-icons verified-icon" title="Verified">verified</span>
                    <?php endif; ?>
                </div>

                <?php if (!isShopLoggedIn()): ?>
                <div class="fp-chat-login">
                    <span class="material-icons">chat</span>
                    <p>Login to message this farmer directly</p>
                    <a href="<?php echo shopUrl('auth/login.php?redirect='); ?><?php echo urlencode(SHOP_URL . 'pages/farmer-profile.php?id=' . $farmerId); ?>"
                       class="btn btn-primary btn-block">
                        <span class="material-icons">login</span> Login to Chat
                    </a>
                    <a href="<?php echo shopUrl('auth/register.php'); ?>" class="btn btn-outline btn-block" style="margin-top:8px;">
                        Create Account
                    </a>
                </div>
                <?php else: ?>

                <!-- Messages Area -->
                <div class="fp-chat-messages" id="fpMessages">
                    <?php if (empty($messages) && !$existingConvo): ?>
                    <div class="fp-chat-intro">
                        <span class="material-icons">waving_hand</span>
                        <p>Say hello to <?php echo htmlspecialchars($farmer['first_name']); ?>!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="fp-msg <?php echo $msg['sender_type'] === 'customer' ? 'fp-msg-out' : 'fp-msg-in'; ?>">
                        <?php if (!empty($msg['attachment_url'])): ?>
                            <a href="<?php echo htmlspecialchars($msg['attachment_url']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($msg['attachment_url']); ?>"
                                     class="fp-msg-img" alt="attachment">
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($msg['message'])): ?>
                        <div class="fp-msg-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <?php endif; ?>
                        <div class="fp-msg-time"><?php echo timeAgo($msg['created_at']); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Image Preview -->
                <div class="fp-img-preview" id="fpImgPreview" style="display:none;">
                    <img id="fpPreviewImg" src="" alt="">
                    <button onclick="clearAttachment()" title="Remove">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <!-- Chat Input -->
                <div class="fp-chat-input">
                    <label class="fp-attach-btn" title="Attach image">
                        <span class="material-icons">image</span>
                        <input type="file" id="fpAttachment" accept="image/*" style="display:none;" onchange="previewAttachment(this)">
                    </label>
                    <textarea id="fpMsgText" placeholder="Type a message..."
                              rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendFpMessage();}"></textarea>
                    <button class="fp-send-btn" onclick="sendFpMessage()" id="fpSendBtn">
                        <span class="material-icons">send</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="fp-quick-links">
                <a href="<?php echo shopUrl('pages/products.php?seller=' . $farmerId); ?>">
                    <span class="material-icons">storefront</span>
                    All products by this farmer
                </a>
                <?php if (isShopLoggedIn()): ?>
                <a href="<?php echo shopUrl('messages/'); ?>">
                    <span class="material-icons">forum</span>
                    All my messages
                </a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<style>
.fp-page { padding: 0 var(--spacing-md) var(--spacing-2xl); max-width: 1200px; margin: 0 auto; }

/* Hero */
.fp-hero {
    position: relative; border-radius: var(--radius-xl); overflow: hidden;
    margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-md);
}
.fp-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, var(--primary) 0%, #2d5016 100%);
}
.fp-hero-bg::after {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.fp-hero-content {
    position: relative; z-index: 1;
    display: flex; align-items: center; gap: var(--spacing-xl);
    padding: var(--spacing-2xl); flex-wrap: wrap;
}
.fp-avatar-wrap { position: relative; flex-shrink: 0; }
.fp-avatar {
    width: 100px; height: 100px; border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3); object-fit: cover;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.fp-verified {
    position: absolute; bottom: 0; right: 0;
    background: #3b82f6; border-radius: 50%; width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid white;
}
.fp-verified .material-icons { font-size: 16px; color: white; }
.fp-hero-info { flex: 1; min-width: 200px; }
.fp-name { color: white; font-size: var(--font-size-2xl); margin: 0 0 8px; display: flex; align-items: center; gap: var(--spacing-sm); flex-wrap: wrap; }
.verified-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(59,130,246,0.9); color: white;
    font-size: var(--font-size-sm); font-weight: 500;
    padding: 2px 10px; border-radius: 999px;
}
.verified-badge .material-icons { font-size: 14px; }
.fp-location { color: rgba(255,255,255,0.85); margin: 0 0 10px; display: flex; align-items: center; gap: 4px; }
.fp-location .material-icons { font-size: 16px; }
.fp-meta { display: flex; gap: var(--spacing-md); flex-wrap: wrap; }
.fp-meta span { color: rgba(255,255,255,0.75); font-size: var(--font-size-sm); display: flex; align-items: center; gap: 4px; }
.fp-meta .material-icons { font-size: 16px; }
.fp-stats { display: flex; gap: var(--spacing-md); flex-shrink: 0; }
.fp-stat {
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border-radius: var(--radius-lg); padding: var(--spacing-md) var(--spacing-lg);
    text-align: center; border: 1px solid rgba(255,255,255,0.2); min-width: 70px;
}
.fp-stat strong { display: block; font-size: var(--font-size-xl); color: white; font-weight: 700; }
.fp-stat span { font-size: var(--font-size-xs); color: rgba(255,255,255,0.7); }

/* Body Layout */
.fp-body { display: grid; grid-template-columns: 1fr 340px; gap: var(--spacing-xl); align-items: start; }
@media (max-width: 900px) { .fp-body { grid-template-columns: 1fr; } }

/* Sections */
.fp-section { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); padding: var(--spacing-xl); margin-bottom: var(--spacing-lg); }
.fp-section h2 { display: flex; align-items: center; gap: var(--spacing-sm); font-size: var(--font-size-lg); color: var(--gray-800); margin: 0 0 var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--gray-100); }
.fp-section h2 .material-icons { color: var(--primary); }

/* About Grid */
.fp-about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); }
@media (max-width: 600px) { .fp-about-grid { grid-template-columns: 1fr; } }
.fp-about-item { display: flex; gap: var(--spacing-sm); align-items: flex-start; }
.fp-about-item > .material-icons { color: var(--primary); font-size: 1.2rem; margin-top: 2px; flex-shrink: 0; }
.fp-about-item strong { display: block; font-size: var(--font-size-xs); color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; }
.fp-about-item span { color: var(--gray-700); font-size: var(--font-size-sm); }

/* Product Grid */
.fp-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--spacing-md); }
.fp-no-products { text-align: center; padding: var(--spacing-2xl); color: var(--gray-400); }
.fp-no-products .material-icons { font-size: 3rem; }

/* Sidebar */
.fp-sidebar { position: sticky; top: 80px; }
.fp-chat-card { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); overflow: hidden; margin-bottom: var(--spacing-md); }

.fp-chat-header {
    display: flex; align-items: center; gap: var(--spacing-sm);
    padding: var(--spacing-md) var(--spacing-lg);
    background: var(--primary); color: white;
}
.fp-chat-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); }
.fp-chat-header strong { display: block; font-size: var(--font-size-sm); }
.fp-chat-header span { font-size: var(--font-size-xs); opacity: 0.8; }
.verified-icon { margin-left: auto; color: #93c5fd; font-size: 1.2rem; }

.fp-chat-login { padding: var(--spacing-xl); text-align: center; }
.fp-chat-login .material-icons { font-size: 3rem; color: var(--gray-300); margin-bottom: var(--spacing-md); }
.fp-chat-login p { color: var(--gray-500); margin-bottom: var(--spacing-lg); font-size: var(--font-size-sm); }

/* Messages */
.fp-chat-messages {
    height: 320px; overflow-y: auto; padding: var(--spacing-md);
    display: flex; flex-direction: column; gap: 8px;
    background: #f8faf8;
}
.fp-chat-intro { text-align: center; margin: auto; color: var(--gray-400); }
.fp-chat-intro .material-icons { font-size: 2rem; display: block; margin-bottom: 8px; }
.fp-chat-intro p { font-size: var(--font-size-sm); margin: 0; }

.fp-msg { display: flex; flex-direction: column; max-width: 80%; }
.fp-msg-in { align-self: flex-start; }
.fp-msg-out { align-self: flex-end; }
.fp-msg-bubble {
    padding: 8px 12px; border-radius: 12px;
    font-size: var(--font-size-sm); line-height: 1.4; word-break: break-word;
}
.fp-msg-in .fp-msg-bubble { background: white; color: var(--gray-800); border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
.fp-msg-out .fp-msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 4px; }
.fp-msg-img { max-width: 200px; border-radius: 8px; display: block; cursor: pointer; }
.fp-msg-time { font-size: 11px; color: var(--gray-400); margin-top: 2px; padding: 0 4px; }
.fp-msg-in .fp-msg-time { align-self: flex-start; }
.fp-msg-out .fp-msg-time { align-self: flex-end; }

/* Image Preview */
.fp-img-preview {
    padding: 8px var(--spacing-md); border-top: 1px solid var(--gray-100);
    display: flex; align-items: center; gap: 8px; background: var(--gray-50);
}
.fp-img-preview img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
.fp-img-preview button { background: var(--gray-200); border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.fp-img-preview button .material-icons { font-size: 14px; color: var(--gray-600); }

/* Chat Input */
.fp-chat-input {
    display: flex; align-items: flex-end; gap: 8px;
    padding: var(--spacing-md); border-top: 1px solid var(--gray-100);
    background: white;
}
.fp-attach-btn {
    width: 36px; height: 36px; border-radius: 50%; background: var(--gray-100);
    display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;
    transition: background 0.2s;
}
.fp-attach-btn:hover { background: var(--gray-200); }
.fp-attach-btn .material-icons { font-size: 18px; color: var(--gray-500); }
.fp-chat-input textarea {
    flex: 1; border: 1px solid var(--gray-200); border-radius: 20px;
    padding: 8px 14px; font-size: var(--font-size-sm); resize: none;
    outline: none; font-family: inherit; max-height: 100px; line-height: 1.4;
    transition: border-color 0.2s;
}
.fp-chat-input textarea:focus { border-color: var(--primary); }
.fp-send-btn {
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: var(--primary); color: white; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; transition: opacity 0.2s;
}
.fp-send-btn:hover { opacity: 0.85; }
.fp-send-btn .material-icons { font-size: 18px; }

/* Quick Links */
.fp-quick-links { background: white; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); overflow: hidden; }
.fp-quick-links a {
    display: flex; align-items: center; gap: var(--spacing-sm);
    padding: var(--spacing-md) var(--spacing-lg); color: var(--gray-700);
    text-decoration: none; font-size: var(--font-size-sm); transition: background 0.15s;
    border-bottom: 1px solid var(--gray-100);
}
.fp-quick-links a:last-child { border-bottom: none; }
.fp-quick-links a:hover { background: var(--gray-50); color: var(--primary); }
.fp-quick-links .material-icons { color: var(--primary); font-size: 1.1rem; }
</style>

<script>
const FP_FARMER_ID  = <?php echo $farmerId; ?>;
const FP_CONVO_ID   = <?php echo $existingConvo ? $existingConvo['conversation_id'] : 'null'; ?>;
let   fpCurrentConvo = FP_CONVO_ID;
let   fpPendingImg   = null;
let   fpPolling      = null;
let   fpLastTime     = <?php echo $messages ? strtotime(end($messages)['created_at']) * 1000 : 0; ?>;

function scrollFpChat() {
    const el = document.getElementById('fpMessages');
    if (el) el.scrollTop = el.scrollHeight;
}
scrollFpChat();

function previewAttachment(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) { showToast('Image too large (max 5 MB)', 'warning'); input.value = ''; return; }
    fpPendingImg = file;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('fpPreviewImg').src = e.target.result;
        document.getElementById('fpImgPreview').style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function clearAttachment() {
    fpPendingImg = null;
    document.getElementById('fpImgPreview').style.display = 'none';
    document.getElementById('fpAttachment').value = '';
}

function appendFpMessage(msg) {
    const wrap = document.getElementById('fpMessages');
    const intro = wrap.querySelector('.fp-chat-intro');
    if (intro) intro.remove();

    const div = document.createElement('div');
    div.className = 'fp-msg ' + (msg.sender_type === 'customer' ? 'fp-msg-out' : 'fp-msg-in');

    let html = '';
    if (msg.attachment_url) {
        html += `<a href="${msg.attachment_url}" target="_blank"><img src="${msg.attachment_url}" class="fp-msg-img" alt="image"></a>`;
    }
    if (msg.message) {
        html += `<div class="fp-msg-bubble">${msg.message.replace(/\n/g,'<br>')}</div>`;
    }
    html += `<div class="fp-msg-time">just now</div>`;
    div.innerHTML = html;
    wrap.appendChild(div);
    scrollFpChat();
}

async function sendFpMessage() {
    const text = (document.getElementById('fpMsgText').value || '').trim();
    const btn  = document.getElementById('fpSendBtn');

    if (!text && !fpPendingImg) return;

    btn.disabled = true;

    let attachmentUrl = null;

    // Upload image first
    if (fpPendingImg) {
        const formData = new FormData();
        formData.append('file', fpPendingImg);
        try {
            const res = await fetch('<?php echo shopUrl('ajax/upload.php'); ?>', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { attachmentUrl = data.url; }
            else { showToast(data.message || 'Image upload failed', 'error'); btn.disabled = false; return; }
        } catch { showToast('Upload failed', 'error'); btn.disabled = false; return; }
    }

    // Send via existing messages AJAX
    const payload = {
        farmer_id: FP_FARMER_ID,
        message: text || ' ',
        subject: 'Chat with <?php echo addslashes($farmerName); ?>',
    };
    if (attachmentUrl) payload.attachment_url = attachmentUrl;

    let endpoint, action;
    if (fpCurrentConvo) {
        action   = 'send';
        endpoint = window.SHOP_MSG_AJAX;
        payload.action          = 'send';
        payload.conversation_id = fpCurrentConvo;
    } else {
        action   = 'start_conversation';
        endpoint = window.SHOP_MSG_AJAX;
        payload.action    = 'start_conversation';
        payload.farmer_id = FP_FARMER_ID;
    }

    try {
        const res  = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            if (!fpCurrentConvo && data.conversation_id) {
                fpCurrentConvo = data.conversation_id;
                startPolling();
            }
            appendFpMessage({ sender_type: 'customer', message: text, attachment_url: attachmentUrl });
            document.getElementById('fpMsgText').value = '';
            clearAttachment();
        } else {
            showToast(data.message || 'Failed to send', 'error');
        }
    } catch { showToast('Network error', 'error'); }

    btn.disabled = false;
}

// Send attachment with message — update messages handler to persist attachment_url
// (we pass it in the JSON body, messages.php 'send' action needs to save it)

function startPolling() {
    if (fpPolling) return;
    fpPolling = setInterval(pollMessages, 5000);
}

async function pollMessages() {
    if (!fpCurrentConvo) return;
    try {
        const res  = await fetch(`${window.SHOP_MSG_AJAX}?action=get_messages&conversation_id=${fpCurrentConvo}&since=${fpLastTime}`);
        const data = await res.json();
        if (data.success && data.messages && data.messages.length) {
            data.messages.forEach(m => {
                if (m.sender_type === 'farmer') {
                    appendFpMessage(m);
                    fpLastTime = Math.max(fpLastTime, new Date(m.created_at).getTime());
                }
            });
        }
    } catch {}
}

<?php if ($existingConvo): ?>
startPolling();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
