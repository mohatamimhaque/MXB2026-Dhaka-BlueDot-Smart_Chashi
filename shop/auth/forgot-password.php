<?php
/**
 * Shop Forgot Password — AJAX + OTP flow
 */

require_once __DIR__ . '/../config/config.php';

if (isShopLoggedIn()) {
    shopRedirect();
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/../layouts/header.php';
?>

<div class="auth-page">
    <div class="auth-container-centered">
        <div class="auth-card auth-card-sm">

            <!-- Step 1: Enter email -->
            <div id="emailSection">
                <div class="auth-icon-circle" style="background:linear-gradient(135deg,#557A46,#8FBC46)">
                    <span class="material-icons">lock_reset</span>
                </div>
                <div class="auth-header">
                    <h1><?php echo __('forgot_password'); ?></h1>
                    <p><?php echo __('forgot_password_subtitle'); ?></p>
                </div>

                <form id="forgotForm" novalidate>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('email_address_label'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">email</span>
                            <input type="email" id="forgotEmail" class="form-control"
                                   placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <div id="forgotError" class="alert alert-error" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="forgotBtn">
                        <span class="material-icons">send</span>
                        <?php echo __('send_reset_code'); ?>
                    </button>
                </form>
            </div>

            <!-- Step 2: OTP verification -->
            <div id="otpSection" style="display:none;">
                <div class="auth-icon-circle" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                    <span class="material-icons">mark_email_read</span>
                </div>
                <div class="auth-header">
                    <h1><?php echo __('check_your_email_title'); ?></h1>
                    <p><?php echo __('we_sent_code_to'); ?> <strong id="otpEmailDisplay"></strong></p>
                </div>

                <form id="otpForm" novalidate>
                    <div class="form-group">
                        <label class="form-label text-center"><?php echo __('verification_code_label'); ?></label>
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
                        <?php echo __('verify_code'); ?>
                    </button>
                    <div class="resend-row">
                        <button type="button" class="btn btn-ghost btn-sm" id="resendBtn" onclick="resendOtp()">
                            <span class="material-icons">refresh</span> <?php echo __('resend'); ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="backToEmail()">
                            <span class="material-icons">arrow_back</span> <?php echo __('back_btn'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 3: New password -->
            <div id="resetSection" style="display:none;">
                <div class="auth-icon-circle" style="background:linear-gradient(135deg,#10b981,#059669)">
                    <span class="material-icons">lock_open</span>
                </div>
                <div class="auth-header">
                    <h1><?php echo __('set_new_password_title'); ?></h1>
                    <p><?php echo __('set_new_password_subtitle'); ?></p>
                </div>

                <form id="resetForm" novalidate>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('new_password'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">lock</span>
                            <input type="password" id="newPassword" class="form-control"
                                   placeholder="Minimum 6 characters" required>
                            <button type="button" class="input-icon-right btn-icon" onclick="togglePwd('newPassword',this)">
                                <span class="material-icons">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('confirm_new_password'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">lock_outline</span>
                            <input type="password" id="confirmPassword" class="form-control"
                                   placeholder="Repeat password" required>
                        </div>
                    </div>
                    <div id="resetError" class="alert alert-error" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="resetBtn">
                        <span class="material-icons">save</span>
                        <?php echo __('reset_password'); ?>
                    </button>
                </form>
            </div>

            <div class="auth-footer-links">
                <p><?php echo __('remember_password_q'); ?>
                    <a href="<?php echo shopUrl('auth/login.php'); ?>" class="link-primary font-600"><?php echo __('sign_in_btn'); ?></a>
                </p>
            </div>

        </div>
    </div>
</div>

<script>
const SHOP_AJAX_AUTH = '<?php echo shopUrl("ajax/auth.php"); ?>';

// ─── Step 1: Request reset code ───────────────────────────────────────────────
document.getElementById('forgotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = document.getElementById('forgotEmail').value.trim();
    if (!email) { showAlert('forgotError', 'Please enter your email'); return; }

    const btn = document.getElementById('forgotBtn');
    setLoading(btn, true);
    hideAlert('forgotError');

    const res = await shopPost(SHOP_AJAX_AUTH, { action:'forgot_password', email });
    setLoading(btn, false);

    if (res.success) {
        showToast(res.message, 'success');
        showOtpSection(res.email);
    } else {
        showAlert('forgotError', res.message);
    }
});

// ─── Step 2: Verify OTP ───────────────────────────────────────────────────────
initOtpInputs();

document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const code = getOtpCode();
    if (code.length !== 6) { showAlert('otpError', 'Please enter all 6 digits'); return; }

    const btn = document.getElementById('otpBtn');
    setLoading(btn, true);
    hideAlert('otpError');

    const res = await shopPost(SHOP_AJAX_AUTH, { action:'verify_otp', code });
    setLoading(btn, false);

    if (res.success) {
        showToast(res.message, 'success');
        showResetSection();
    } else {
        showAlert('otpError', res.message);
    }
});

// ─── Step 3: New password ─────────────────────────────────────────────────────
document.getElementById('resetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const pw  = document.getElementById('newPassword').value;
    const cpw = document.getElementById('confirmPassword').value;

    if (pw.length < 6) { showAlert('resetError', 'Password must be at least 6 characters'); return; }
    if (pw !== cpw)    { showAlert('resetError', 'Passwords do not match'); return; }

    const btn = document.getElementById('resetBtn');
    setLoading(btn, true);
    hideAlert('resetError');

    const res = await shopPost(SHOP_AJAX_AUTH, { action:'reset_password', password:pw, confirm_password:cpw });
    setLoading(btn, false);

    if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => window.location = res.redirect || '<?php echo shopUrl("auth/login.php"); ?>', 1000);
    } else {
        showAlert('resetError', res.message);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function showOtpSection(maskedEmail) {
    document.getElementById('emailSection').style.display = 'none';
    document.getElementById('otpSection').style.display   = 'block';
    document.getElementById('otpEmailDisplay').textContent = maskedEmail || 'your email';
    document.querySelector('#otpInputs .otp-digit').focus();
}

function showResetSection() {
    document.getElementById('otpSection').style.display   = 'none';
    document.getElementById('resetSection').style.display = 'block';
    document.getElementById('newPassword').focus();
}

function backToEmail() {
    document.getElementById('otpSection').style.display   = 'none';
    document.getElementById('emailSection').style.display = 'block';
}

async function resendOtp() {
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    const res = await shopPost(SHOP_AJAX_AUTH, { action:'resend_otp' });
    showToast(res.message, res.success ? 'success' : 'error');
    setTimeout(() => { btn.disabled = false; }, 30000);
}

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('.material-icons');
    inp.type = (inp.type === 'password') ? 'text' : 'password';
    icon.textContent = (inp.type === 'text') ? 'visibility_off' : 'visibility';
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
