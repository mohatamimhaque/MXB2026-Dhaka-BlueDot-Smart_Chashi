<?php
/**
 * Shop Login Page — AJAX-based
 */

require_once __DIR__ . '/../config/config.php';

if (isShopLoggedIn()) {
    shopRedirect();
}

$pageTitle = 'Login';
include __DIR__ . '/../layouts/header.php';
?>

<div class="auth-page">
    <div class="auth-container">

        <!-- Left Panel — Form -->
        <div class="auth-card">
            <div class="auth-logo">
                <span class="logo-icon">🌾</span>
            </div>
            <div class="auth-header">
                <h1><?php echo __('welcome_back_shop'); ?></h1>
                <p><?php echo __('sign_in_continue_shop'); ?></p>
            </div>

            <!-- Login Form -->
            <div id="loginSection">
                <form id="loginForm" novalidate>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('email_address_label'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">email</span>
                            <input type="email" name="email" id="loginEmail" class="form-control"
                                   placeholder="you@example.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('password_label'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">lock</span>
                            <input type="password" name="password" id="loginPassword" class="form-control"
                                   placeholder="Your password" required autocomplete="current-password">
                            <button type="button" class="input-icon-right btn-icon" onclick="togglePwd('loginPassword',this)">
                                <span class="material-icons">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-row-flex">
                        <label class="form-check">
                            <input type="checkbox" id="rememberMe">
                            <span><?php echo __('remember_me'); ?></span>
                        </label>
                        <a href="<?php echo shopUrl('auth/forgot-password.php'); ?>" class="link-primary"><?php echo __('forgot_password_link'); ?></a>
                    </div>

                    <div id="loginError" class="alert alert-error" style="display:none;"></div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="loginBtn">
                        <span class="material-icons">login</span>
                        <?php echo __('sign_in_btn'); ?>
                    </button>
                </form>

                <div class="auth-divider"><span>or</span></div>

                <div class="auth-footer-links">
                    <p><?php echo __('dont_have_account_q'); ?>
                        <a href="<?php echo shopUrl('auth/register.php'); ?>" class="link-primary font-600"><?php echo __('create_one_free'); ?></a>
                    </p>
                </div>
            </div>

            <!-- OTP Section (shown when email not verified) -->
            <div id="otpSection" style="display:none;">
                <div class="otp-info">
                    <span class="material-icons otp-icon">mark_email_read</span>
                    <p>A 6-digit code was sent to <strong id="otpEmailDisplay"></strong></p>
                    <p class="text-muted">Please verify your email to continue.</p>
                </div>
                <form id="otpForm" novalidate>
                    <div class="form-group">
                        <label class="form-label">Verification Code</label>
                        <div class="otp-inputs" id="otpInputs">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        </div>
                    </div>
                    <div id="otpError" class="alert alert-error" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="otpBtn">
                        <span class="material-icons">verified</span>
                        Verify & Login
                    </button>
                    <div class="resend-row">
                        <button type="button" class="btn btn-ghost btn-sm" id="resendBtn" onclick="resendOtp()">
                            <span class="material-icons">refresh</span> Resend Code
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="backToLogin()">
                            <span class="material-icons">arrow_back</span> Back
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Panel — Illustration -->
        <div class="auth-illustration">
            <div class="illustration-inner">
                <div class="ill-icon">🛒</div>
                <h2><?php echo __('shop_fresh_farm_title'); ?></h2>
                <ul class="feature-list">
                    <li><span class="material-icons">check_circle</span> <?php echo __('direct_from_local_farmers'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('fresh_quality_products'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('fast_delivery_door'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('secure_payment_options'); ?></li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
const SHOP_AJAX_AUTH = '<?php echo shopUrl("ajax/auth.php"); ?>';

// ─── Login form ───────────────────────────────────────────────────────────────
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn   = document.getElementById('loginBtn');
    const email = document.getElementById('loginEmail').value.trim();
    const pass  = document.getElementById('loginPassword').value;

    setLoading(btn, true);
    hideAlert('loginError');

    const res = await shopPost(SHOP_AJAX_AUTH, { action:'login', email, password:pass, remember: document.getElementById('rememberMe')?.checked || false });

    setLoading(btn, false);

    if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => window.location = res.redirect || '<?php echo SHOP_URL; ?>', 800);
    } else if (res.needs_verification) {
        showOtpSection(res.email);
    } else {
        showAlert('loginError', res.message);
    }
});

// ─── OTP form ─────────────────────────────────────────────────────────────────
initOtpInputs();

document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const code = getOtpCode();
    if (code.length !== 6) { showAlert('otpError','Please enter the 6-digit code'); return; }

    const btn = document.getElementById('otpBtn');
    setLoading(btn, true);
    hideAlert('otpError');

    const res = await shopPost(SHOP_AJAX_AUTH, { action:'verify_otp', code });
    setLoading(btn, false);

    if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => window.location = res.redirect || '<?php echo SHOP_URL; ?>', 800);
    } else {
        showAlert('otpError', res.message);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function showOtpSection(maskedEmail) {
    document.getElementById('loginSection').style.display = 'none';
    document.getElementById('otpSection').style.display   = 'block';
    document.getElementById('otpEmailDisplay').textContent = maskedEmail || 'your email';
    document.querySelector('.otp-digit').focus();
}

function backToLogin() {
    document.getElementById('otpSection').style.display   = 'none';
    document.getElementById('loginSection').style.display = 'block';
}

async function resendOtp() {
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    const res = await shopPost(SHOP_AJAX_AUTH, { action:'resend_otp' });
    showToast(res.message, res.success ? 'success' : 'error');
    setTimeout(() => { btn.disabled = false; }, 30000);
}

function togglePwd(id, btn) {
    const inp  = document.getElementById(id);
    const icon = btn.querySelector('.material-icons');
    if (inp.type === 'password') { inp.type = 'text';     icon.textContent = 'visibility_off'; }
    else                         { inp.type = 'password'; icon.textContent = 'visibility'; }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
