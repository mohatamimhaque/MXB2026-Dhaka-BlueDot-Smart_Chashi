<?php
/**
 * Shop Header Layout
 */

$cartCount    = getCartCount();
$shopUser     = isShopLoggedIn() ? getShopUser() : null;
$flash        = getFlashMessage();
$shopLang     = get_language();
$shopLangName = $shopLang === 'bn' ? 'বাংলা' : 'EN';

// Load delivery note from settings
$_hDb         = new ShopDatabase();
$_deliveryNote = $_hDb->getSetting('delivery_note', 'Free delivery on orders over ৳500');
?>
<!DOCTYPE html>
<html lang="<?php echo $shopLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SHOP_TAGLINE; ?> - Buy fresh agricultural products directly from farmers.">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?><?php echo SHOP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url; ?>img/logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>img/logo.png">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>img/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo shopUrl('assets/css/style.css'); ?>">

    <script>
        window.SHOP_URL        = '<?php echo SHOP_URL; ?>';
        window.SHOP_CART_AJAX  = '<?php echo shopUrl("ajax/cart.php"); ?>';
        window.SHOP_LOGGED_IN  = <?php echo isShopLoggedIn() ? 'true' : 'false'; ?>;
        window.SHOP_LOGIN_URL  = '<?php echo shopUrl("auth/login.php"); ?>';
        window.SHOP_AUTH_AJAX  = '<?php echo shopUrl("ajax/auth.php"); ?>';
        window.SHOP_MSG_AJAX   = '<?php echo shopUrl("ajax/messages.php"); ?>';
        window.SHOP_REV_AJAX   = '<?php echo shopUrl("ajax/reviews.php"); ?>';
    </script>

    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo shopUrl('assets/css/' . $css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?php echo shopUrl('assets/js/main.js'); ?>"></script>
</head>
<body>

    <!-- Top Bar -->
    <div class="shop-topbar">
        <div class="container">
            <div class="topbar-left">
                <span class="material-icons" style="font-size:16px;">local_shipping</span>
                <span><?php echo htmlspecialchars($_deliveryNote); ?></span>
            </div>
            <div class="topbar-right">
                <a href="<?php echo MAIN_SITE_URL; ?>" target="_blank">
                    <span class="material-icons" style="font-size:16px;">agriculture</span>
                    <?php echo __('are_you_farmer_join'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="shop-header" id="shopHeader">
        <div class="container">
            <!-- Logo -->
            <a href="<?php echo shopUrl(); ?>" class="shop-logo">
                <span class="logo-icon">🌾</span>
                <div class="logo-text-wrap">
                    <span class="logo-text"><?php echo SHOP_NAME; ?></span>
                    <span class="logo-sub"><?php echo __('fresh_from_farm'); ?></span>
                </div>
            </a>

            <!-- Nav -->
            <nav class="shop-nav" id="shopNav">
                <a href="<?php echo shopUrl(); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['SCRIPT_FILENAME'], '/shop/') !== false ? 'active' : ''; ?>">
                    <span class="material-icons">home</span>
                    <span><?php echo __('home'); ?></span>
                </a>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="nav-link">
                    <span class="material-icons">storefront</span>
                    <span><?php echo __('products_menu'); ?></span>
                </a>
            </nav>

            <!-- Header Actions -->
            <div class="shop-header-actions">
                <!-- Messages Icon -->
                <?php if ($shopUser):
                    $unreadShopMsgs = 0;
                    try {
                        $msgCountDb2 = new ShopDatabase();
                        $msgRow2 = $msgCountDb2->single(
                            "SELECT COALESCE(SUM(buyer_unread), 0) as cnt
                             FROM shop_conversations
                             WHERE customer_id = ? AND customer_type = 'general'",
                            [$shopUser['user_id']]
                        );
                        $unreadShopMsgs = (int)($msgRow2['cnt'] ?? 0);
                    } catch (Exception $e) { $unreadShopMsgs = 0; }
                ?>
                <a href="<?php echo shopUrl('messages/'); ?>" class="header-icon-btn" title="Messages" style="position:relative;display:flex;align-items:center;justify-content:center;">
                    <span class="material-icons">chat</span>
                    <?php if ($unreadShopMsgs > 0): ?>
                        <span class="cart-badge" style="background:#ef4444;"><?php echo $unreadShopMsgs > 99 ? '99+' : $unreadShopMsgs; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- Cart Icon -->
                <button class="header-icon-btn cart-btn" onclick="openCartDrawer()" title="Cart">
                    <span class="material-icons">shopping_cart</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
                    <?php else: ?>
                        <span class="cart-badge" id="cartBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </button>

                <!-- User Menu or Auth Buttons -->
                <?php if ($shopUser): ?>
                    <div class="user-dropdown" id="userDropdown">
                        <button class="user-dropdown-btn" id="userDropdownBtn">
                            <img src="<?php echo getUserAvatar($shopUser['profile_img_url'] ?? ''); ?>"
                                 alt="<?php echo htmlspecialchars($shopUser['first_name']); ?>"
                                 class="user-avatar">
                            <span class="user-name"><?php echo htmlspecialchars($shopUser['first_name']); ?></span>
                            <span class="material-icons">expand_more</span>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="dropdown-user-info">
                                <img src="<?php echo getUserAvatar($shopUser['profile_img_url'] ?? ''); ?>" alt="">
                                <div>
                                    <strong><?php echo htmlspecialchars($shopUser['first_name'] . ' ' . ($shopUser['last_name'] ?? '')); ?></strong>
                                    <small><?php echo htmlspecialchars($shopUser['email']); ?></small>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo shopUrl('profile/'); ?>">
                                <span class="material-icons">person</span> <?php echo __('my_profile'); ?>
                            </a>
                            <a href="<?php echo shopUrl('pages/my-orders.php'); ?>">
                                <span class="material-icons">receipt_long</span> <?php echo __('my_orders'); ?>
                            </a>
                            <a href="<?php echo shopUrl('pages/cart.php'); ?>">
                                <span class="material-icons">shopping_cart</span> <?php echo __('cart'); ?>
                                <?php if ($cartCount > 0): ?>
                                    <span class="dropdown-badge"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo shopUrl('profile/settings.php'); ?>">
                                <span class="material-icons">settings</span> <?php echo __('settings'); ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo shopUrl('auth/logout.php'); ?>" class="logout-link">
                                <span class="material-icons">logout</span> <?php echo __('logout'); ?>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo shopUrl('auth/login.php'); ?>" class="btn btn-outline btn-sm"><?php echo __('login'); ?></a>
                    <a href="<?php echo shopUrl('auth/register.php'); ?>" class="btn btn-primary btn-sm"><?php echo __('register'); ?></a>
                <?php endif; ?>

                <!-- Language Toggle -->
                <div class="shop-lang-wrap" style="position:relative;display:inline-flex;">
                    <button class="header-icon-btn" id="shopLangBtn" title="Change Language" style="font-size:12px;font-weight:600;gap:3px;">
                        <span class="material-icons" style="font-size:18px;">language</span>
                        <span><?php echo $shopLangName; ?></span>
                    </button>
                    <div id="shopLangMenu" style="display:none;position:absolute;top:calc(100%+6px);right:0;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);min-width:130px;z-index:9999;padding:6px 0;">
                        <button class="shop-lang-opt<?php echo $shopLang==='en'?' active':''; ?>" data-lang="en" style="display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border:none;background:none;cursor:pointer;font-size:13px;text-align:left;">
                            🇬🇧 English
                        </button>
                        <button class="shop-lang-opt<?php echo $shopLang==='bn'?' active':''; ?>" data-lang="bn" style="display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border:none;background:none;cursor:pointer;font-size:13px;text-align:left;">
                            🇧🇩 বাংলা
                        </button>
                    </div>
                </div>
                <script>
                (function(){
                    const btn  = document.getElementById('shopLangBtn');
                    const menu = document.getElementById('shopLangMenu');
                    btn.addEventListener('click', e => { e.stopPropagation(); menu.style.display = menu.style.display==='none'?'block':'none'; });
                    document.addEventListener('click', () => menu.style.display='none');
                    document.querySelectorAll('.shop-lang-opt').forEach(b => {
                        b.addEventListener('click', function() {
                            fetch('<?php echo MAIN_SITE_URL; ?>api/set-language.php', {
                                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                body:'language='+this.dataset.lang
                            }).then(() => location.reload());
                        });
                    });
                })();
                </script>

                <!-- Mobile menu toggle -->
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                    <span class="material-icons">menu</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <span class="logo-icon" style="font-size:1.5rem;">🌾</span>
            <strong><?php echo SHOP_NAME; ?></strong>
            <button class="btn-icon" id="mobileMenuClose">
                <span class="material-icons">close</span>
            </button>
        </div>

        <!-- Mobile Search -->
        <form action="<?php echo shopUrl('pages/products.php'); ?>" method="GET" class="mobile-search">
            <input type="text" name="search" placeholder="<?php echo __('search_products'); ?>">
            <button type="submit"><span class="material-icons">search</span></button>
        </form>

        <nav class="mobile-menu-nav">
            <a href="<?php echo shopUrl(); ?>"><span class="material-icons">home</span> <?php echo __('home'); ?></a>
            <a href="<?php echo shopUrl('pages/products.php'); ?>"><span class="material-icons">storefront</span> <?php echo __('products_menu'); ?></a>
            <a href="<?php echo shopUrl('pages/cart.php'); ?>">
                <span class="material-icons">shopping_cart</span> <?php echo __('cart'); ?>
                <?php if ($cartCount > 0): ?>
                    <span class="mobile-badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </a>
            <?php if ($shopUser): ?>
                <a href="<?php echo shopUrl('profile/'); ?>"><span class="material-icons">person</span> <?php echo __('my_profile'); ?></a>
                <a href="<?php echo shopUrl('pages/my-orders.php'); ?>"><span class="material-icons">receipt_long</span> <?php echo __('my_orders'); ?></a>
                <a href="<?php echo shopUrl('auth/logout.php'); ?>"><span class="material-icons">logout</span> <?php echo __('logout'); ?></a>
            <?php else: ?>
                <a href="<?php echo shopUrl('auth/login.php'); ?>"><span class="material-icons">login</span> <?php echo __('login'); ?></a>
                <a href="<?php echo shopUrl('auth/register.php'); ?>"><span class="material-icons">person_add</span> <?php echo __('register'); ?></a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo htmlspecialchars($flash['type']); ?>" id="flashMessage">
            <span class="material-icons">
                <?php echo match($flash['type']) {
                    'success' => 'check_circle',
                    'error'   => 'error',
                    'warning' => 'warning',
                    default   => 'info'
                }; ?>
            </span>
            <span><?php echo htmlspecialchars($flash['message']); ?></span>
            <button class="btn-icon flash-close" onclick="this.parentElement.remove()">
                <span class="material-icons">close</span>
            </button>
        </div>
    <?php endif; ?>

    <main class="shop-main">
