/**
 * SmartChashi Shop - Main JavaScript
 * Handles common interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize components
    initMobileMenu();
    initUserDropdown();
    initFlashMessages();
});

/**
 * Mobile Menu Toggle
 */
function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.add('active');
            mobileMenuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        const closeMenu = () => {
            mobileMenu.classList.remove('active');
            mobileMenuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', closeMenu);
        }

        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', closeMenu);
        }
    }
}

/**
 * User Dropdown Toggle
 */
function initUserDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdown = userDropdownBtn?.closest('.user-dropdown');

    if (userDropdownBtn && userDropdown) {
        userDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    }
}

/**
 * Auto-hide Flash Messages
 */
function initFlashMessages() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => flashMessage.remove(), 300);
        }, 5000);
    }
}

/**
 * Show loading overlay
 */
function showLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Format currency
 */
function formatPrice(amount) {
    return '৳' + parseFloat(amount).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;

    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };

    toast.innerHTML = `
        <span class="material-icons">${icons[type] || 'info'}</span>
        <span>${message}</span>
    `;

    document.body.appendChild(toast);

    // Add styles if not already present
    if (!document.getElementById('toastStyles')) {
        const style = document.createElement('style');
        style.id = 'toastStyles';
        style.textContent = `
            .toast-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                animation: slideInRight 0.3s ease;
            }
            .toast-success { background: #d1fae5; color: #065f46; }
            .toast-error { background: #fee2e2; color: #991b1b; }
            .toast-warning { background: #fef3c7; color: #92400e; }
            .toast-info { background: #dbeafe; color: #1e40af; }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                to { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);
    }

    // Auto remove
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * AJAX request helper
 */
async function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = { ...defaultOptions, ...options };

    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }

    try {
        const response = await fetch(url, config);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('AJAX Error:', error);
        return { success: false, message: 'An error occurred. Please try again.' };
    }
}

/**
 * Add to cart
 */
async function addToCart(productId, quantity = 1) {
    const cartUrl = window.SHOP_CART_AJAX || '/smartchashi/shop/ajax/cart.php';

    const result = await shopPost(cartUrl, {
        action: 'add',
        product_id: productId,
        quantity: quantity
    });

    if (result.success) {
        showToast(result.message, 'success');
        updateCartBadge(result.cart_count);
        loadCartDrawer();
        openCartDrawer();
    } else {
        showToast(result.message, 'error');
    }

    return result;
}

/**
 * Update cart badge
 */
function updateCartBadge(count) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    });
}

/**
 * Confirm dialog
 */
function confirmAction(message) {
    return new Promise((resolve) => {
        const confirmed = window.confirm(message);
        resolve(confirmed);
    });
}

/**
 * Quantity input controls
 */
function initQuantityControls() {
    document.querySelectorAll('.quantity-control').forEach(control => {
        const minusBtn = control.querySelector('.qty-minus');
        const plusBtn = control.querySelector('.qty-plus');
        const input = control.querySelector('.qty-input');

        if (minusBtn && plusBtn && input) {
            minusBtn.addEventListener('click', () => {
                const value = parseInt(input.value) || 1;
                if (value > 1) {
                    input.value = value - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });

            plusBtn.addEventListener('click', () => {
                const value = parseInt(input.value) || 1;
                const max = parseInt(input.max) || 999;
                if (value < max) {
                    input.value = value + 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    });
}

// ─── AJAX Helper ─────────────────────────────────────────────────────────────

/**
 * POST JSON data to a URL and return parsed JSON response.
 */
async function shopPost(url, data = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });
        return await res.json();
    } catch (err) {
        console.error('shopPost error:', err);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// ─── Button Loading State ─────────────────────────────────────────────────────

/**
 * Toggle a button between its normal state and a spinner/disabled loading state.
 */
function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn._origHTML = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="btn-spinner"></span>';
    } else {
        btn.disabled  = false;
        btn.innerHTML = btn._origHTML || btn.innerHTML;
    }
}

// ─── Inline Alert Helpers ─────────────────────────────────────────────────────

function showAlert(id, message) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent   = message;
        el.style.display = 'block';
    }
}

function hideAlert(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

// ─── OTP Input Helpers ────────────────────────────────────────────────────────

/**
 * Wire up keyboard navigation for a group of single-digit OTP inputs.
 * Expects inputs with class `.otp-digit` inside a container `#otpInputs`.
 */
function initOtpInputs() {
    const container = document.getElementById('otpInputs');
    if (!container) return;

    const digits = Array.from(container.querySelectorAll('.otp-digit'));

    digits.forEach((input, idx) => {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace') {
                e.preventDefault();
                if (this.value) {
                    this.value = '';
                } else if (idx > 0) {
                    digits[idx - 1].value = '';
                    digits[idx - 1].focus();
                }
            } else if (e.key === 'ArrowLeft' && idx > 0) {
                e.preventDefault();
                digits[idx - 1].focus();
            } else if (e.key === 'ArrowRight' && idx < digits.length - 1) {
                e.preventDefault();
                digits[idx + 1].focus();
            } else if (e.key === 'Delete') {
                e.preventDefault();
                this.value = '';
            }
        });

        input.addEventListener('input', function () {
            const digit = this.value.replace(/\D/g, '').slice(-1);
            this.value = digit;
            if (digit && idx < digits.length - 1) {
                digits[idx + 1].focus();
            }
        });

        input.addEventListener('focus', function () {
            this.select();
        });

        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            pasted.split('').slice(0, digits.length).forEach((ch, i) => {
                if (digits[i]) digits[i].value = ch;
            });
            const last = Math.min(pasted.length, digits.length) - 1;
            if (digits[last]) digits[last].focus();
        });
    });
}

/**
 * Read all OTP digit inputs and return their combined value.
 */
function getOtpCode() {
    const container = document.getElementById('otpInputs');
    if (!container) return '';
    return Array.from(container.querySelectorAll('.otp-digit'))
                .map(i => i.value.trim())
                .join('');
}

// ─── Cart Drawer ──────────────────────────────────────────────────────────────

function openCartDrawer() {
    const drawer  = document.getElementById('cartDrawer');
    const overlay = document.getElementById('cartDrawerOverlay');
    if (drawer)  drawer.classList.add('active');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    loadCartDrawer();
}

function closeCartDrawer() {
    const drawer  = document.getElementById('cartDrawer');
    const overlay = document.getElementById('cartDrawerOverlay');
    if (drawer)  drawer.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Fetch cart contents via AJAX and populate the drawer.
 */
async function loadCartDrawer() {
    const cartUrl = window.SHOP_CART_AJAX || '/smartchashi/shop/ajax/cart.php';
    const body    = document.getElementById('drawerCartBody');
    const footer  = document.getElementById('drawerCartFooter');
    const counter = document.getElementById('drawerCartCount');
    const total   = document.getElementById('drawerTotal');

    if (!body) return;

    body.innerHTML = '<div class="drawer-loading"><span class="btn-spinner"></span></div>';

    const res = await shopPost(cartUrl, { action: 'get' });

    if (!res.success) {
        body.innerHTML = '<div class="cart-drawer-empty"><span class="material-icons">error_outline</span><p>Could not load cart.</p></div>';
        return;
    }

    const items    = res.items || [];
    const subtotal = res.subtotal || 0;

    if (counter) counter.textContent = items.length;
    updateCartBadge(res.cart_count || items.length);

    if (items.length === 0) {
        body.innerHTML = `
            <div class="cart-drawer-empty">
                <span class="material-icons">remove_shopping_cart</span>
                <p>Your cart is empty</p>
                <a href="${window.SHOP_URL || ''}pages/products.php" class="btn btn-primary btn-sm" onclick="closeCartDrawer()">Shop Now</a>
            </div>`;
        if (footer) footer.style.display = 'none';
        return;
    }

    body.innerHTML = items.map(item => {
        const img   = item.image_url
            ? `<img src="${item.image_url}" alt="${escHtml(item.product_name)}" class="drawer-item-img">`
            : `<div class="drawer-item-img drawer-item-noimg"><span class="material-icons">image_not_supported</span></div>`;
        const price = formatPrice(item.price * item.quantity);
        return `
            <div class="drawer-cart-item" data-cart-id="${item.cart_id}">
                ${img}
                <div class="drawer-item-info">
                    <div class="drawer-item-name">${escHtml(item.product_name)}</div>
                    <div class="drawer-item-meta">${formatPrice(item.price)} / ${escHtml(item.price_unit || 'unit')}</div>
                    <div class="drawer-item-qty-row">
                        <div class="drawer-qty-ctrl">
                            <button class="drawer-qty-btn" onclick="drawerUpdateQty(${item.cart_id}, ${item.quantity - 1})">−</button>
                            <span class="drawer-qty-val">${item.quantity}</span>
                            <button class="drawer-qty-btn" onclick="drawerUpdateQty(${item.cart_id}, ${item.quantity + 1})">+</button>
                        </div>
                        <span class="drawer-item-price">${price}</span>
                        <button class="drawer-remove-btn" onclick="drawerRemoveItem(${item.cart_id})" title="Remove">
                            <span class="material-icons">delete_outline</span>
                        </button>
                    </div>
                </div>
            </div>`;
    }).join('');

    if (footer) {
        footer.style.display = 'flex';
        if (total) total.textContent = formatPrice(subtotal);
    }
}

async function drawerUpdateQty(cartId, newQty) {
    const cartUrl = window.SHOP_CART_AJAX || '/smartchashi/shop/ajax/cart.php';
    if (newQty < 1) {
        drawerRemoveItem(cartId);
        return;
    }
    const res = await shopPost(cartUrl, { action: 'update', cart_id: cartId, quantity: newQty });
    if (res.success) {
        updateCartBadge(res.cart_count);
        loadCartDrawer();
    } else {
        showToast(res.message, 'error');
    }
}

async function drawerRemoveItem(cartId) {
    const cartUrl = window.SHOP_CART_AJAX || '/smartchashi/shop/ajax/cart.php';
    const res = await shopPost(cartUrl, { action: 'remove', cart_id: cartId });
    if (res.success) {
        updateCartBadge(res.cart_count);
        loadCartDrawer();
        showToast('Item removed', 'info');
    } else {
        showToast(res.message, 'error');
    }
}

// ─── Checkout Gate ───────────────────────────────────────────────────────────

/**
 * Redirect to checkout if logged in, otherwise to login page with return URL.
 */
function requireLoginCheckout() {
    const checkoutUrl = (window.SHOP_URL || '') + 'pages/checkout.php';
    if (window.SHOP_LOGGED_IN) {
        window.location.href = checkoutUrl;
    } else {
        const loginUrl = window.SHOP_LOGIN_URL || (window.SHOP_URL + 'auth/login.php');
        window.location.href = loginUrl + '?redirect=' + encodeURIComponent(checkoutUrl);
    }
}

// ─── Utility ──────────────────────────────────────────────────────────────────

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Form Validation ──────────────────────────────────────────────────────────

/**
 * Form validation
 */
// ─── Wishlist (localStorage) ──────────────────────────────────────────────────

function getWishlist() {
    try { return JSON.parse(localStorage.getItem('sc_wishlist') || '[]'); } catch { return []; }
}
function saveWishlist(list) {
    localStorage.setItem('sc_wishlist', JSON.stringify(list));
}
function isWishlisted(id) {
    return getWishlist().includes(String(id));
}
function toggleWishlist(id, btn) {
    id = String(id);
    const list = getWishlist();
    const idx  = list.indexOf(id);
    if (idx === -1) {
        list.push(id);
        showToast('Added to wishlist', 'success');
    } else {
        list.splice(idx, 1);
        showToast('Removed from wishlist', 'info');
    }
    saveWishlist(list);
    renderWishlistBtn(id, btn);
}
function renderWishlistBtn(id, btn) {
    if (!btn) return;
    const on = isWishlisted(id);
    btn.querySelector('.material-icons').textContent = on ? 'favorite' : 'favorite_border';
    btn.style.color = on ? '#ef4444' : '';
    btn.title = on ? 'Remove from wishlist' : 'Add to wishlist';
}
function initWishlistBtns() {
    document.querySelectorAll('[data-wishlist-id]').forEach(btn => {
        renderWishlistBtn(btn.dataset.wishlistId, btn);
        btn.addEventListener('click', (e) => {
            e.preventDefault(); e.stopPropagation();
            toggleWishlist(btn.dataset.wishlistId, btn);
        });
    });
}
document.addEventListener('DOMContentLoaded', initWishlistBtns);

// ─── Recently Viewed (localStorage) ──────────────────────────────────────────

function trackRecentlyViewed(id, name, img, url) {
    try {
        let list = JSON.parse(localStorage.getItem('sc_recently_viewed') || '[]');
        list = list.filter(i => String(i.id) !== String(id));
        list.unshift({ id, name, img, url });
        list = list.slice(0, 10);
        localStorage.setItem('sc_recently_viewed', JSON.stringify(list));
    } catch {}
}
function getRecentlyViewed(excludeId) {
    try {
        const list = JSON.parse(localStorage.getItem('sc_recently_viewed') || '[]');
        return list.filter(i => String(i.id) !== String(excludeId));
    } catch { return []; }
}

// ─── Back to Top ──────────────────────────────────────────────────────────────

(function initBackToTop() {
    const btn = document.createElement('button');
    btn.id        = 'backToTop';
    btn.title     = 'Back to top';
    btn.innerHTML = '<span class="material-icons">keyboard_arrow_up</span>';
    btn.style.cssText = `
        position:fixed;bottom:80px;right:20px;z-index:900;
        width:44px;height:44px;border-radius:50%;border:none;
        background:var(--primary,#557a46);color:#fff;cursor:pointer;
        box-shadow:0 2px 10px rgba(0,0,0,.2);opacity:0;transition:opacity .3s;
        display:flex;align-items:center;justify-content:center;
    `;
    document.body.appendChild(btn);
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    window.addEventListener('scroll', () => {
        btn.style.opacity = window.scrollY > 400 ? '1' : '0';
        btn.style.pointerEvents = window.scrollY > 400 ? 'auto' : 'none';
    }, { passive: true });
})();

// ─── Form Validation ──────────────────────────────────────────────────────────

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    // Clear previous errors
    form.querySelectorAll('.form-error').forEach(el => el.remove());
    form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');

            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.textContent = 'This field is required';
            field.parentNode.appendChild(errorDiv);
        }

        // Email validation
        if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                field.classList.add('error');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'form-error';
                errorDiv.textContent = 'Please enter a valid email';
                field.parentNode.appendChild(errorDiv);
            }
        }
    });

    return isValid;
}
