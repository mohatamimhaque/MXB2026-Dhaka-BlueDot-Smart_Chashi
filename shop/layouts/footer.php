    </main>

<?php
// Load shop settings from DB (with fallbacks)
$_footerDb = new ShopDatabase();
$_fSettings = $_footerDb->getSettings([
    'footer_about', 'footer_email', 'footer_phone', 'footer_address',
    'footer_facebook', 'footer_instagram', 'footer_youtube', 'footer_twitter',
    'footer_copyright', 'delivery_note'
]);

$_fAbout     = $_fSettings['footer_about']     ?: 'Buy fresh agricultural products directly from farmers across Bangladesh.';
$_fEmail     = $_fSettings['footer_email']     ?: 'info@smartchashi.com';
$_fPhone     = $_fSettings['footer_phone']     ?: '+880 1700-000000';
$_fAddress   = $_fSettings['footer_address']   ?: 'Dhaka, Bangladesh';
$_fFacebook  = $_fSettings['footer_facebook']  ?: '';
$_fInstagram = $_fSettings['footer_instagram'] ?: '';
$_fYoutube   = $_fSettings['footer_youtube']   ?: '';
$_fTwitter   = $_fSettings['footer_twitter']   ?: '';
$_fCopyright = $_fSettings['footer_copyright'] ?: SHOP_NAME;
?>

    <!-- Footer -->
    <footer class="shop-footer">
        <div class="container">
            <div class="footer-grid">

                <!-- Brand -->
                <div class="footer-section footer-brand">
                    <h4>
                        <span class="logo-icon">🌾</span>
                        <?php echo htmlspecialchars(SHOP_NAME); ?>
                    </h4>
                    <p class="footer-about"><?php echo htmlspecialchars($_fAbout); ?></p>

                    <?php if ($_fFacebook || $_fInstagram || $_fYoutube || $_fTwitter): ?>
                    <div class="footer-social">
                        <?php if ($_fFacebook): ?>
                            <a href="<?php echo htmlspecialchars($_fFacebook); ?>" target="_blank" rel="noopener" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if ($_fInstagram): ?>
                            <a href="<?php echo htmlspecialchars($_fInstagram); ?>" target="_blank" rel="noopener" aria-label="Instagram">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if ($_fYoutube): ?>
                            <a href="<?php echo htmlspecialchars($_fYoutube); ?>" target="_blank" rel="noopener" aria-label="YouTube">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58a2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if ($_fTwitter): ?>
                            <a href="<?php echo htmlspecialchars($_fTwitter); ?>" target="_blank" rel="noopener" aria-label="X / Twitter">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h4><?php echo __('quick_links'); ?></h4>
                    <ul>
                        <li><a href="<?php echo shopUrl(); ?>"><?php echo __('home'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/products.php'); ?>"><?php echo __('all_products'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/products.php?category=Vegetables'); ?>"><?php echo __('vegetables'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/products.php?category=Fruits'); ?>"><?php echo __('fruits'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/products.php?category=Grains'); ?>"><?php echo __('grains'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/products.php?category=Rice'); ?>"><?php echo __('rice_cat'); ?></a></li>
                    </ul>
                </div>

                <!-- Customer Service -->
                <div class="footer-section">
                    <h4><?php echo __('customer_service'); ?></h4>
                    <ul>
                        <li><a href="<?php echo shopUrl('pages/my-orders.php'); ?>"><?php echo __('my_orders'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/track-order.php'); ?>"><?php echo __('track_order_btn'); ?></a></li>
                        <li><a href="<?php echo shopUrl('pages/cart.php'); ?>"><?php echo __('shopping_cart_title'); ?></a></li>
                        <li><a href="<?php echo shopUrl('auth/register.php'); ?>"><?php echo __('create_account'); ?></a></li>
                        <li><a href="<?php echo shopUrl('auth/login.php'); ?>"><?php echo __('login'); ?></a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="footer-section">
                    <h4><?php echo __('contact_info_label'); ?></h4>
                    <ul class="contact-list">
                        <?php if ($_fEmail): ?>
                        <li>
                            <span class="material-icons">email</span>
                            <a href="mailto:<?php echo htmlspecialchars($_fEmail); ?>"><?php echo htmlspecialchars($_fEmail); ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($_fPhone): ?>
                        <li>
                            <span class="material-icons">phone</span>
                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $_fPhone)); ?>"><?php echo htmlspecialchars($_fPhone); ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($_fAddress): ?>
                        <li>
                            <span class="material-icons">location_on</span>
                            <span><?php echo htmlspecialchars($_fAddress); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="payment-methods">
                        <span class="payment-label"><?php echo __('we_accept'); ?></span>
                        <div class="payment-icons">
                            <span class="payment-icon" title="Cash on Delivery">💵 COD</span>
                            <span class="payment-icon" title="bKash">📱 bKash</span>
                            <span class="payment-icon" title="Nagad">📱 Nagad</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_fCopyright); ?>. <?php echo __('rights_reserved'); ?></p>
                <p><?php echo __('part_of'); ?> <a href="<?php echo MAIN_SITE_URL; ?>" target="_blank">Smart Chashi</a> <?php echo __('ecosystem'); ?></p>
            </div>
        </div>
    </footer>

    <!-- Cart Drawer -->
    <div class="cart-drawer" id="cartDrawer">
        <div class="cart-drawer-header">
            <h3><span class="material-icons">shopping_cart</span> <?php echo __('cart_drawer_title'); ?> (<span id="drawerCartCount">0</span>)</h3>
            <button class="btn-icon" onclick="closeCartDrawer()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="cart-drawer-body" id="drawerCartBody">
            <div class="cart-drawer-empty">
                <span class="material-icons">remove_shopping_cart</span>
                <p><?php echo __('cart_is_empty'); ?></p>
                <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-sm" onclick="closeCartDrawer()"><?php echo __('shop_now'); ?></a>
            </div>
        </div>
        <div class="cart-drawer-footer" id="drawerCartFooter" style="display:none;">
            <div class="drawer-total">
                <span><?php echo __('total_label'); ?></span>
                <strong id="drawerTotal">৳0</strong>
            </div>
            <a href="<?php echo shopUrl('pages/cart.php'); ?>" class="btn btn-outline btn-block"><?php echo __('view_cart'); ?></a>
            <button onclick="requireLoginCheckout()" class="btn btn-primary btn-block">
                <span class="material-icons">payment</span> <?php echo __('checkout'); ?>
            </button>
        </div>
    </div>
    <div class="cart-drawer-overlay" id="cartDrawerOverlay" onclick="closeCartDrawer()"></div>

    <!-- Scripts -->
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo shopUrl('assets/js/' . $js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
