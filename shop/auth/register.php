<?php
/**
 * Shop Registration Page — Multi-step AJAX + OTP
 */

require_once __DIR__ . '/../config/config.php';

if (isShopLoggedIn()) {
    shopRedirect();
}

$pageTitle = 'Create Account';
include __DIR__ . '/../layouts/header.php';
?>

<style>
.avatar-upload-wrap { display: flex; align-items: center; gap: 14px; }
.avatar-preview {
    width: 70px; height: 70px; border-radius: 50%;
    background: var(--bg-secondary, #f3f4f6); border: 2px dashed var(--border-color, #d1d5db);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;
}
.avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder { color: #9ca3af; font-size: 32px; }
.avatar-upload-actions { flex: 1; }
</style>

<div class="auth-page">
    <div class="auth-container auth-container-wide">

        <!-- Left Panel — Form -->
        <div class="auth-card">
            <div class="auth-logo">
                <span class="logo-icon">🌾</span>
            </div>

            <!-- Step 1: Registration Form -->
            <div id="step1Section">
                <div class="auth-header">
                    <h1><?php echo __('create_account'); ?></h1>
                    <p>Join <?php echo SHOP_NAME; ?> — <?php echo __('its_free'); ?></p>
                </div>

                <div class="step-indicator">
                    <div class="step active">
                        <span class="step-num">1</span>
                        <span class="step-label"><?php echo __('your_info_step'); ?></span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" id="step2Dot">
                        <span class="step-num">2</span>
                        <span class="step-label"><?php echo __('verify_email_step'); ?></span>
                    </div>
                </div>

                <form id="registerForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo __('first_name'); ?> *</label>
                            <input type="text" id="firstName" class="form-control" placeholder="First name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('last_name'); ?></label>
                            <input type="text" id="lastName" class="form-control" placeholder="Last name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('email_address_label'); ?> *</label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">email</span>
                            <input type="email" id="regEmail" class="form-control"
                                   placeholder="you@example.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('phone_number_label'); ?></label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">phone</span>
                            <input type="tel" id="regPhone" class="form-control" placeholder="01XXXXXXXXX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('password_label'); ?> *</label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">lock</span>
                            <input type="password" id="regPassword" class="form-control"
                                   placeholder="Minimum 6 characters" required autocomplete="new-password"
                                   oninput="updateStrength(this.value)">
                            <button type="button" class="input-icon-right btn-icon" onclick="togglePwd('regPassword',this)">
                                <span class="material-icons">visibility</span>
                            </button>
                        </div>
                        <div class="strength-bar" id="strengthBar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('confirm_password_label'); ?> *</label>
                        <div class="input-wrapper">
                            <span class="material-icons input-icon-left">lock_outline</span>
                            <input type="password" id="regConfirm" class="form-control"
                                   placeholder="Repeat your password" required autocomplete="new-password">
                        </div>
                    </div>

                    <!-- Profile photo (optional) -->
                    <div class="form-group">
                        <label class="form-label"><?php echo __('profile_photo_label'); ?> <span class="text-muted">(<?php echo __('optional'); ?>)</span></label>
                        <div class="avatar-upload-wrap">
                            <div class="avatar-preview" id="avatarPreview">
                                <span class="material-icons avatar-placeholder">person</span>
                                <img id="avatarImg" src="" alt="" style="display:none;">
                            </div>
                            <div class="avatar-upload-actions">
                                <label for="avatarFile" class="btn btn-outline btn-sm" style="cursor:pointer;">
                                    <span class="material-icons">upload</span> <?php echo __('choose_photo_btn'); ?>
                                </label>
                                <input type="file" id="avatarFile" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                                <button type="button" class="btn btn-ghost btn-sm" id="removeAvatarBtn" style="display:none;" onclick="removeAvatar()">
                                    <span class="material-icons">close</span> <?php echo __('remove_btn'); ?>
                                </button>
                                <p class="text-muted text-xs" style="margin-top:4px;">JPG, PNG or GIF · Max 2MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" id="termsCheck" required>
                            <span><?php echo __('agree_to'); ?>
                                <a href="#" class="link-primary"><?php echo __('terms_of_service'); ?></a> &amp;
                                <a href="#" class="link-primary"><?php echo __('privacy_policy'); ?></a>
                            </span>
                        </label>
                    </div>

                    <div id="regError" class="alert alert-error" style="display:none;"></div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="regBtn">
                        <span class="material-icons">person_add</span>
                        <?php echo __('create_account'); ?>
                    </button>
                </form>

                <div class="auth-footer-links">
                    <p><?php echo __('already_account'); ?>
                        <a href="<?php echo shopUrl('auth/login.php'); ?>" class="link-primary font-600"><?php echo __('sign_in_btn'); ?></a>
                    </p>
                </div>
            </div>

            <!-- Step 2: OTP Verification -->
            <div id="step2Section" style="display:none;">
                <div class="auth-header">
                    <h1><?php echo __('verify_your_email'); ?></h1>
                </div>

                <div class="step-indicator">
                    <div class="step completed">
                        <span class="step-num"><span class="material-icons" style="font-size:14px;">check</span></span>
                        <span class="step-label"><?php echo __('your_info_step'); ?></span>
                    </div>
                    <div class="step-line active"></div>
                    <div class="step active">
                        <span class="step-num">2</span>
                        <span class="step-label"><?php echo __('verify_email_step'); ?></span>
                    </div>
                </div>

                <div class="otp-info">
                    <span class="material-icons otp-icon">mark_email_read</span>
                    <p><?php echo __('we_sent_code_to'); ?> <strong id="regEmailDisplay"></strong></p>
                    <p class="text-muted"><?php echo __('enter_code_below'); ?></p>
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
                        <?php echo __('verify_complete_reg_btn'); ?>
                    </button>

                    <div class="resend-row">
                        <span class="text-muted text-sm"><?php echo __('didnt_receive_code'); ?></span>
                        <button type="button" class="btn btn-ghost btn-sm" id="resendBtn" onclick="resendOtp()">
                            <span class="material-icons">refresh</span> <?php echo __('resend'); ?>
                        </button>
                    </div>

                    <button type="button" class="btn btn-ghost btn-block" onclick="backToRegister()">
                        <span class="material-icons">arrow_back</span> <?php echo __('back_btn'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="auth-illustration">
            <div class="illustration-inner">
                <div class="ill-icon">🌾</div>
                <h2><?php echo __('why_join_us'); ?></h2>
                <ul class="feature-list">
                    <li><span class="material-icons">check_circle</span> <?php echo __('reg_feature_1'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('reg_feature_2'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('reg_feature_3'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('reg_feature_4'); ?></li>
                    <li><span class="material-icons">check_circle</span> <?php echo __('reg_feature_5'); ?></li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
const SHOP_AJAX_AUTH = '<?php echo shopUrl("ajax/auth.php"); ?>';

// ─── Step 1: Register ──────────────────────────────────────────────────────────
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideAlert('regError');

    const firstName = document.getElementById('firstName').value.trim();
    const lastName  = document.getElementById('lastName').value.trim();
    const email     = document.getElementById('regEmail').value.trim();
    const phone     = document.getElementById('regPhone').value.trim();
    const password  = document.getElementById('regPassword').value;
    const confirm   = document.getElementById('regConfirm').value;

    if (!firstName) { showAlert('regError', 'First name is required'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAlert('regError', 'Please enter a valid email address'); return;
    }
    if (password.length < 6) { showAlert('regError', 'Password must be at least 6 characters'); return; }
    if (password !== confirm) { showAlert('regError', 'Passwords do not match'); return; }
    if (!document.getElementById('termsCheck').checked) {
        showAlert('regError', 'You must agree to the terms'); return;
    }

    const btn = document.getElementById('regBtn');
    setLoading(btn, true);

    const avatarData = document.getElementById('avatarImg').dataset.b64 || null;

    const res = await shopPost(SHOP_AJAX_AUTH, {
        action: 'register_step1', first_name: firstName, last_name: lastName,
        email, phone, password,
        profile_image: avatarData
    });

    setLoading(btn, false);

    if (res.success) {
        showStep2(res.email);
    } else {
        showAlert('regError', res.message);
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
        setTimeout(() => window.location = res.redirect || '<?php echo SHOP_URL; ?>', 800);
    } else {
        showAlert('otpError', res.message);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function showStep2(maskedEmail) {
    document.getElementById('step1Section').style.display = 'none';
    document.getElementById('step2Section').style.display = 'block';
    document.getElementById('regEmailDisplay').textContent = maskedEmail || 'your email';
    document.querySelector('#otpInputs .otp-digit').focus();
}

function backToRegister() {
    document.getElementById('step2Section').style.display = 'none';
    document.getElementById('step1Section').style.display = 'block';
}

async function resendOtp() {
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    const res = await shopPost(SHOP_AJAX_AUTH, { action:'resend_otp' });
    showToast(res.message, res.success ? 'success' : 'error');
    setTimeout(() => { btn.disabled = false; }, 30000);
}

function previewAvatar(input) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        showAlert('regError', 'Image must be smaller than 2MB');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('avatarImg');
        img.src = e.target.result;
        img.dataset.b64 = e.target.result; // store base64
        img.style.display = 'block';
        document.querySelector('.avatar-placeholder').style.display = 'none';
        document.getElementById('removeAvatarBtn').style.display = 'inline-flex';
    };
    reader.readAsDataURL(file);
}

function removeAvatar() {
    const img = document.getElementById('avatarImg');
    img.src = '';
    img.dataset.b64 = '';
    img.style.display = 'none';
    document.querySelector('.avatar-placeholder').style.display = 'block';
    document.getElementById('removeAvatarBtn').style.display = 'none';
    document.getElementById('avatarFile').value = '';
}

function togglePwd(id, btn) {
    const inp  = document.getElementById(id);
    const icon = btn.querySelector('.material-icons');
    inp.type   = (inp.type === 'password') ? 'text' : 'password';
    icon.textContent = (inp.type === 'text') ? 'visibility_off' : 'visibility';
}

function updateStrength(pw) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/\d/.test(pw))   score++;
    if (/[^a-zA-Z0-9]/.test(pw)) score++;

    const levels = [
        { pct:'20%', color:'#ef4444', text:'Very Weak' },
        { pct:'40%', color:'#f97316', text:'Weak' },
        { pct:'60%', color:'#f59e0b', text:'Fair' },
        { pct:'80%', color:'#22c55e', text:'Good' },
        { pct:'100%',color:'#16a34a', text:'Strong' }
    ];
    const lvl = levels[Math.min(score, levels.length) - 1] || levels[0];
    fill.style.width        = pw ? lvl.pct : '0';
    fill.style.background   = lvl.color;
    label.textContent       = pw ? lvl.text : '';
    label.style.color       = lvl.color;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
