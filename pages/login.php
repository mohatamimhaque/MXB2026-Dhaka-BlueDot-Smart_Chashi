<?php
include __DIR__ . '/../layouts/header.php';

if (isLoggedIn()) {
    redirect('dashboard');
}
?>

<!-- Hero Section - Modern Login -->
<section class="auth-hero-modern">
    <div class="auth-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="auth-hero-content">
        <div class="auth-badge">
            <span class="material-icons">lock</span>
            <span><?php echo __('secure_login'); ?></span>
        </div>
        <h1 class="auth-title">
            <span class="gradient-text"><?php echo __('welcome_back_login'); ?></span>
            <span class="wave-emoji">👋</span>
        </h1>
        <p class="auth-subtitle"><?php echo __('login_subtitle'); ?></p>
    </div>
</section>

<div class="auth-container-modern">
    <div class="auth-main">
        <!-- Login Form Card -->
        <div class="auth-card" id="loginCard">
            <div class="auth-card-header">
                <div class="auth-icon-wrap">
                    <span class="material-icons">login</span>
                </div>
                <h2><?php echo __('login'); ?></h2>
                <p><?php echo __('access_dashboard'); ?></p>
            </div>

            <form id="loginForm" method="POST" class="auth-form">
                <div class="form-group-modern">
                    <label for="email">
                        <span class="material-icons">email</span>
                        <?php echo __('email_or_phone'); ?>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="email" name="email" placeholder="<?php echo __('enter_email_phone'); ?>" required>
                        <span class="input-icon material-icons">person</span>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label for="password">
                        <span class="material-icons">lock</span>
                        <?php echo __('password'); ?>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="<?php echo __('enter_password'); ?>" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="Toggle password visibility">
                            <span class="material-icons eye-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-modern">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        <span><?php echo __('remember_me'); ?></span>
                    </label>
                    <a href="#" class="forgot-link" id="forgotPasswordLink"><?php echo __('forgot_password'); ?></a>
                </div>

                <button type="submit" class="btn-auth-primary" id="loginBtn">
                    <span class="btn-text">
                        <span class="material-icons">login</span>
                        <?php echo __('sign_in'); ?>
                    </span>
                    <span class="btn-loader" style="display:none;">
                        <span class="material-icons spinning">sync</span>
                        <?php echo __('signing_in'); ?>
                    </span>
                </button>

                <div class="auth-divider">
                    <span><?php echo __('dont_have_account'); ?></span>
                </div>

                <a href="<?php echo $base_url; ?>?page=register" class="btn-auth-secondary">
                    <span class="material-icons">person_add</span>
                    <?php echo __('sign_up_now'); ?>
                </a>
            </form>
        </div>

        <!-- Forgot Password Card -->
        <div class="auth-card" id="forgotPasswordCard" style="display: none;">
            <div class="auth-card-header">
                <div class="auth-icon-wrap auth-icon-warning">
                    <span class="material-icons">lock_reset</span>
                </div>
                <h2><?php echo __('reset_password'); ?></h2>
                <p><?php echo __('reset_password_subtitle'); ?></p>
            </div>

            <!-- Step 1: Enter Email -->
            <div id="forgotStep1">
                <form id="forgotEmailForm" class="auth-form">
                    <div class="form-group-modern">
                        <label for="forgotEmail">
                            <span class="material-icons">email</span>
                            <?php echo __('email_or_phone'); ?>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" id="forgotEmail" name="emailOrPhone" placeholder="<?php echo __('enter_email_phone'); ?>" required>
                            <span class="input-icon material-icons">mail</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-primary" id="sendResetCodeBtn">
                        <span class="btn-text">
                            <span class="material-icons">send</span>
                            <?php echo __('send_reset_code'); ?>
                        </span>
                            <span class="btn-loader" style="display:none;"><span class="material-icons spinning">sync</span></span>
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="#" class="btn-link-modern" id="backToLoginLink"><?php echo __('back_to_login'); ?></a>
                    </div>
                </form>
            </div>

            <!-- Step 2: Verify Code -->
            <div id="forgotStep2" style="display: none;">
                <div class="verification-content-modern">
                    <div class="verification-icon-modern">
                        <span class="material-icons">lock_reset</span>
                    </div>
                    <p class="verification-text"><?php echo __('enter_code_sent_to'); ?></p>
                    <p class="verification-email" id="forgotMaskedEmail"></p>
                    
                    <form id="forgotVerifyForm" class="auth-form">
                        <div class="form-group-modern">
                            <div class="code-input-container-modern">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="0" inputmode="numeric">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="1" inputmode="numeric">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="2" inputmode="numeric">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="3" inputmode="numeric">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="4" inputmode="numeric">
                                <input type="text" class="code-input-modern forgot-code" maxlength="1" data-index="5" inputmode="numeric">
                            </div>
                            <input type="hidden" id="forgotVerificationCode">
                        </div>

                        <button type="submit" class="btn-auth-primary" id="verifyResetCodeBtn">
                            <span class="btn-text">
                                <span class="material-icons">verified</span>
                                <?php echo __('verify_code'); ?>
                            </span>
                            <span class="btn-loader" style="display:none;"><span class="material-icons spinning">sync</span></span>
                        </button>
                    </form>

                    <div class="resend-section-modern">
                        <p><?php echo __('didnt_receive_code'); ?></p>
                        <button type="button" class="btn-link-modern" id="resendResetCodeBtn"><?php echo __('resend_code'); ?></button>
                        <p class="resend-timer" id="resetResendTimer" style="display:none;"><?php echo __('resend_in'); ?> <span id="resetTimerCount">60</span>s</p>
                    </div>
                </div>
            </div>

            <!-- Step 3: New Password -->
            <div id="forgotStep3" style="display: none;">
                <form id="newPasswordForm" class="auth-form">
                    <p class="form-description-modern"><?php echo __('create_new_password_desc'); ?></p>
                    
                    <div class="form-group-modern">
                        <label for="newPassword">
                            <span class="material-icons">lock</span>
                            <?php echo __('new_password'); ?>
                        </label>
                        <div class="input-wrapper">
                            <input type="password" id="newPassword" name="password" placeholder="<?php echo __('min_8_chars'); ?>" required>
                            <button type="button" class="password-toggle" data-target="newPassword" aria-label="Toggle">
                                <span class="material-icons eye-icon">visibility</span>
                            </button>
                        </div>
                        <small class="password-hint-modern"><?php echo __('password_requirements'); ?></small>
                    </div>

                    <div class="form-group-modern">
                        <label for="confirmNewPassword">
                            <span class="material-icons">lock_outline</span>
                            <?php echo __('confirm_new_password'); ?>
                        </label>
                        <div class="input-wrapper">
                            <input type="password" id="confirmNewPassword" name="confirmPassword" placeholder="<?php echo __('reenter_password'); ?>" required>
                            <button type="button" class="password-toggle" data-target="confirmNewPassword" aria-label="Toggle">
                                <span class="material-icons eye-icon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-primary" id="resetPasswordBtn">
                        <span class="btn-text">
                            <span class="material-icons">lock_reset</span>
                            <?php echo __('reset_password'); ?>
                        </span>
                        <span class="btn-loader" style="display:none;"><span class="material-icons spinning">sync</span></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Email Verification (for unverified users) -->
        <div class="auth-card" id="verificationCard" style="display: none;">
            <div class="auth-card-header">
                <div class="auth-icon-wrap auth-icon-info">
                    <span class="material-icons">mark_email_read</span>
                </div>
                <h2><?php echo __('verify_your_email'); ?></h2>
                <p><?php echo __('verify_email_subtitle'); ?></p>
            </div>

            <div class="verification-content-modern">
                <div class="verification-icon-modern">
                    <span class="material-icons">mark_email_read</span>
                </div>
                <p class="verification-text"><?php echo __('email_not_verified_sent_code'); ?></p>
                <p class="verification-email" id="loginMaskedEmail"></p>
                
                <form id="loginVerifyForm" class="auth-form" onsubmit="return false;">
                    <div class="form-group-modern">
                        <div class="code-input-container-modern">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="0" inputmode="numeric">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="1" inputmode="numeric">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="2" inputmode="numeric">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="3" inputmode="numeric">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="4" inputmode="numeric">
                            <input type="text" class="code-input-modern login-code" maxlength="1" data-index="5" inputmode="numeric">
                        </div>
                        <input type="hidden" id="loginVerificationCode">
                    </div>

                    <button type="button" class="btn-auth-primary" id="loginVerifyBtn" onclick="verifyLoginCode()">
                        <span class="btn-text">
                            <span class="material-icons">verified</span>
                            <?php echo __('verify_email'); ?>
                        </span>
                        <span class="btn-loader" style="display:none;"><span class="material-icons spinning">sync</span></span>
                    </button>
                </form>

                <div class="resend-section-modern">
                    <p><?php echo __('didnt_receive_code'); ?></p>
                    <button type="button" class="btn-link-modern" id="loginResendCodeBtn"><?php echo __('resend_code'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>


// Login form
document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('loginBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    
    const data = {
        action: 'login',
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
        remember: document.querySelector('[name="remember"]')?.checked || false
    };
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>' + result.redirect;
            }, 1000);
        } else if (result.needs_verification) {
            App.showAlert(result.message, 'warning');
            document.getElementById('loginMaskedEmail').textContent = result.email;
            document.getElementById('loginCard').style.display = 'none';
            document.getElementById('verificationCard').style.display = 'block';
            
            // Send verification code
            await sendLoginVerificationCode();
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        console.error('Login error:', error);
        App.showAlert(error.message || 'An error occurred. Please try again.', 'danger');
    } finally {
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    }
});

// Send verification code for login
async function sendLoginVerificationCode() {
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_code' })
        });
        const result = await response.json();
        if (result.success) {
            App.showAlert('Verification code sent!', 'success');
        }
    } catch (error) {
        console.error('Error sending code:', error);
    }
}

// Verify login code function
async function verifyLoginCode() {
    const code = document.getElementById('loginVerificationCode').value;
    if (code.length !== 6) {
        App.showAlert('Please enter a valid 6-digit code', 'danger');
        return;
    }
    
    const btn = document.getElementById('loginVerifyBtn');
    setButtonLoading(btn, true);
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'verify_code', code: code })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert('Email verified! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>' + result.redirect;
            }, 1500);
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('An error occurred. Please try again.', 'danger');
    } finally {
        setButtonLoading(btn, false);
    }
}

// Login verification code inputs
setupCodeInputs('.login-code', 'loginVerificationCode', 'loginVerifyForm', verifyLoginCode);

document.getElementById('loginResendCodeBtn')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = 'Sending...';
    await sendLoginVerificationCode();
    this.disabled = false;
    this.textContent = 'Resend Code';
});

// Forgot Password Flow
document.getElementById('forgotPasswordLink')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginCard').style.display = 'none';
    document.getElementById('forgotPasswordCard').style.display = 'block';
});

document.getElementById('backToLoginLink')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('forgotPasswordCard').style.display = 'none';
    document.getElementById('loginCard').style.display = 'block';
    resetForgotPasswordForm();
});

function resetForgotPasswordForm() {
    document.getElementById('forgotStep1').style.display = 'block';
    document.getElementById('forgotStep2').style.display = 'none';
    document.getElementById('forgotStep3').style.display = 'none';
    document.getElementById('forgotEmailForm').reset();
    // Reset flags
    isVerifyingResetCode = false;
    // Clear code inputs
    document.querySelectorAll('.forgot-code').forEach(inp => inp.value = '');
    document.getElementById('forgotVerificationCode').value = '';
}

// Step 1: Send reset code
document.getElementById('forgotEmailForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('sendResetCodeBtn');
    setButtonLoading(btn, true);
    
    const emailOrPhone = document.getElementById('forgotEmail').value;
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/forgot-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_code', emailOrPhone: emailOrPhone })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            document.getElementById('forgotMaskedEmail').textContent = result.email;
            document.getElementById('forgotStep1').style.display = 'none';
            document.getElementById('forgotStep2').style.display = 'block';
            document.querySelector('.forgot-code').focus();
            startResetResendTimer();
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('An error occurred. Please try again.', 'danger');
    } finally {
        setButtonLoading(btn, false);
    }
});

// Forgot password code inputs - NO auto-submit, handle manually
const forgotCodeInputs = document.querySelectorAll('.forgot-code');
forgotCodeInputs.forEach((input, index) => {
    input.addEventListener('input', function(e) {
        const value = e.target.value.replace(/[^0-9]/g, '');
        e.target.value = value;
        
        if (value && index < forgotCodeInputs.length - 1) {
            forgotCodeInputs[index + 1].focus();
        }
        
        // Update hidden input
        let fullCode = '';
        forgotCodeInputs.forEach(inp => fullCode += inp.value);
        document.getElementById('forgotVerificationCode').value = fullCode;
        
        // Auto-verify when 6 digits (call function directly, not form submit)
        if (fullCode.length === 6 && !isVerifyingResetCode) {
            verifyForgotPasswordCode();
        }
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
            forgotCodeInputs[index - 1].focus();
        }
    });
    
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
        pastedData.split('').forEach((char, i) => {
            if (forgotCodeInputs[i]) forgotCodeInputs[i].value = char;
        });
        document.getElementById('forgotVerificationCode').value = pastedData;
        if (pastedData.length === 6 && !isVerifyingResetCode) {
            verifyForgotPasswordCode();
        }
    });
});

// Step 2: Verify reset code
let isVerifyingResetCode = false;

async function verifyForgotPasswordCode() {
    if (isVerifyingResetCode) return;
    
    const code = document.getElementById('forgotVerificationCode').value;
    if (code.length !== 6) {
        App.showAlert('Please enter a valid 6-digit code', 'danger');
        return;
    }
    
    isVerifyingResetCode = true;
    const btn = document.getElementById('verifyResetCodeBtn');
    setButtonLoading(btn, true);
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/forgot-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'verify_code', code: code })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            // Show step 3
            document.getElementById('forgotStep2').style.display = 'none';
            document.getElementById('forgotStep3').style.display = 'block';
        } else {
            App.showAlert(result.message, 'danger');
            document.querySelectorAll('.forgot-code').forEach(inp => inp.value = '');
            document.getElementById('forgotVerificationCode').value = '';
            document.querySelectorAll('.forgot-code')[0]?.focus();
            isVerifyingResetCode = false;
        }
    } catch (error) {
        App.showAlert('An error occurred. Please try again.', 'danger');
        isVerifyingResetCode = false;
    } finally {
        setButtonLoading(btn, false);
    }
}

// Form submit handler (for button click)
document.getElementById('forgotVerifyForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    verifyForgotPasswordCode();
});

// Resend reset code
let resetResendTimer = null;
function startResetResendTimer() {
    let seconds = 60;
    document.getElementById('resendResetCodeBtn').style.display = 'none';
    document.getElementById('resetResendTimer').style.display = 'block';
    document.getElementById('resetTimerCount').textContent = seconds;
    
    resetResendTimer = setInterval(() => {
        seconds--;
        document.getElementById('resetTimerCount').textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(resetResendTimer);
            document.getElementById('resendResetCodeBtn').style.display = 'inline';
            document.getElementById('resetResendTimer').style.display = 'none';
        }
    }, 1000);
}

document.getElementById('resendResetCodeBtn')?.addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/forgot-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_code' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            startResetResendTimer();
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('An error occurred. Please try again.', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Resend Code';
    }
});

// Step 3: Reset password
document.getElementById('newPasswordForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const password = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmNewPassword').value;
    
    if (password !== confirmPassword) {
        App.showAlert('Passwords do not match', 'danger');
        return;
    }
    
    const btn = document.getElementById('resetPasswordBtn');
    setButtonLoading(btn, true);
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/forgot-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'reset_password', 
                password: password,
                confirmPassword: confirmPassword
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>login';
            }, 2000);
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('An error occurred. Please try again.', 'danger');
    } finally {
        setButtonLoading(btn, false);
    }
});

// Helper functions
let forgotCodeVerified = false; // Flag to prevent re-submission after success

function setupCodeInputs(selector, hiddenInputId, formId, submitCallback) {
    const codeInputs = document.querySelectorAll(selector);
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            const value = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = value;
            
            if (value && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
            
            let fullCode = '';
            codeInputs.forEach(inp => fullCode += inp.value);
            document.getElementById(hiddenInputId).value = fullCode;
            
            // Only auto-submit if not already verified (for forgot password)
            if (fullCode.length === 6 && !forgotCodeVerified) {
                if (typeof submitCallback === 'function') {
                    submitCallback();
                }
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            pastedData.split('').forEach((char, i) => {
                if (codeInputs[i]) codeInputs[i].value = char;
            });
            document.getElementById(hiddenInputId).value = pastedData;
            if (pastedData.length === 6 && !forgotCodeVerified) {
                if (typeof submitCallback === 'function') {
                    submitCallback();
                }
            }
        });
    });
}

function setButtonLoading(btn, loading) {
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    btn.disabled = loading;
    btnText.style.display = loading ? 'none' : 'inline';
    btnLoader.style.display = loading ? 'inline-flex' : 'none';
}

// Password toggle functionality
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('.eye-icon');
        
        if (input && input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else if (input) {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    });
});

// Create floating particles in hero
const authParticles = document.querySelector('.auth-particles');
if (authParticles) {
    for (let i = 0; i < 12; i++) {
        const particle = document.createElement('div');
        particle.style.cssText = `
            position: absolute;
            width: ${Math.random() * 10 + 5}px;
            height: ${Math.random() * 10 + 5}px;
            background: rgba(255, 255, 255, ${Math.random() * 0.2 + 0.05});
            border-radius: 50%;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: particleFloat ${Math.random() * 4 + 4}s ease-in-out infinite;
            animation-delay: ${Math.random() * 2}s;
        `;
        authParticles.appendChild(particle);
    }
}

// Add particle float animation
const particleStyle = document.createElement('style');
particleStyle.textContent = `
    @keyframes particleFloat {
        0%, 100% { transform: translateY(0) translateX(0); opacity: 0.5; }
        25% { transform: translateY(-30px) translateX(15px); opacity: 0.8; }
        50% { transform: translateY(-50px) translateX(-15px); opacity: 0.5; }
        75% { transform: translateY(-30px) translateX(25px); opacity: 0.8; }
    }
`;
document.head.appendChild(particleStyle);
</script>

<style>
/* ===== Login Page Modern Styles ===== */
:root {
    --primary: #557a46;
    --primary-dark: #3d5a34;
    --primary-light: #6b9a56;
    --gradient-green: linear-gradient(135deg, #557a46 0%, #6b9a56 50%, #7cb668 100%);
    --gradient-blue: linear-gradient(135deg, #3498db 0%, #5dade2 100%);
    --gradient-orange: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
    --shadow-soft: 0 4px 15px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-strong: 0 15px 50px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --border-radius-sm: 10px;
    --transition-fast: 0.2s ease;
    --transition-medium: 0.3s ease;
}

/* Auth Hero Section */
.auth-hero-modern {
    background: var(--gradient-green);
    color: white;
    padding: 3rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin: -1rem -1rem 0 -1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.auth-particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.auth-particles .particle {
    position: absolute;
    width: 8px;
    height: 8px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
}

.auth-particles .particle:nth-child(1) { top: 20%; left: 10%; animation: particleFloat 6s ease-in-out infinite; }
.auth-particles .particle:nth-child(2) { top: 60%; left: 80%; animation: particleFloat 8s ease-in-out infinite 1s; }
.auth-particles .particle:nth-child(3) { top: 40%; left: 50%; animation: particleFloat 7s ease-in-out infinite 2s; }

.auth-hero-content {
    position: relative;
    z-index: 1;
    max-width: 800px;
    margin: 0 auto;
}

.auth-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.auth-badge .material-icons {
    font-size: 1rem;
}

.auth-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.gradient-text {
    background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.8) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.wave-emoji {
    display: inline-block;
    animation: wave 2s ease-in-out infinite;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(20deg); }
    75% { transform: rotate(-20deg); }
}

.auth-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}

/* Auth Container */
.auth-container-modern {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 2rem;
    max-width: 1200px;
    margin: -2rem auto 2rem;
    padding: 0 1rem;
    position: relative;
    z-index: 2;
}

.auth-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
    max-width: 500px;
}

.auth-sidebar {
    display: none;
}

@media (min-width: 768px) {
    .auth-container-modern {
        padding: 0 2rem;
    }
}

@media (min-width: 992px) {
    .auth-container-modern {
        justify-content: center;
    }
    
    .auth-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        width: 100%;
        max-width: 320px;
        flex-shrink: 0;
    }
}

/* Auth Card */
.auth-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-medium);
    overflow: hidden;
}

.auth-card-header {
    text-align: center;
    padding: 2rem 2rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.auth-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--gradient-green);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 25px rgba(85, 122, 70, 0.3);
}

.auth-icon-wrap .material-icons {
    font-size: 1.75rem;
    color: white;
}

.auth-icon-wrap.auth-icon-warning {
    background: var(--gradient-orange);
    box-shadow: 0 8px 25px rgba(230, 126, 34, 0.3);
}

.auth-icon-wrap.auth-icon-info {
    background: var(--gradient-blue);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
}

.auth-card-header h2 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    color: #333;
}

.auth-card-header p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

/* Auth Form */
.auth-form {
    padding: 1.5rem 2rem 2rem;
}

.form-group-modern {
    margin-bottom: 1.25rem;
}

.form-group-modern label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 0.5rem;
}

.form-group-modern label .material-icons {
    font-size: 1.1rem;
    color: var(--primary);
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper input {
    width: 100%;
    padding: 0.875rem 1rem;
    padding-right: 3rem;
    border: 2px solid #e8e8e8;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    transition: var(--transition-fast);
    background: #fafafa;
}

.input-wrapper input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(85, 122, 70, 0.1);
}

.input-wrapper input::placeholder {
    color: #aaa;
}

.input-wrapper .input-icon {
    position: absolute;
    right: 1rem;
    color: #999;
    pointer-events: none;
}

.input-wrapper .password-toggle {
    position: absolute;
    right: 0.5rem;
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    color: #666;
    transition: var(--transition-fast);
}

.input-wrapper .password-toggle:hover {
    color: var(--primary);
}

.password-hint-modern {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: #888;
}

.form-description-modern {
    text-align: center;
    color: #666;
    margin-bottom: 1.5rem;
}

/* Form Options */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.checkbox-modern {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: #555;
}

.checkbox-modern input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}

.forgot-link {
    font-size: 0.9rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition-fast);
}

.forgot-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Auth Buttons */
.btn-auth-primary {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: var(--gradient-green);
    color: white;
    border: none;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-medium);
    box-shadow: 0 4px 15px rgba(85, 122, 70, 0.3);
}

.btn-auth-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(85, 122, 70, 0.4);
}

.btn-auth-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-auth-primary .btn-text,
.btn-auth-primary .btn-loader {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-auth-primary .material-icons {
    font-size: 1.25rem;
}

.btn-auth-secondary {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition-medium);
}

.btn-auth-secondary:hover {
    background: var(--primary);
    color: white;
}

.btn-full {
    width: 100%;
}

.auth-divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.5rem 0;
    color: #999;
    font-size: 0.85rem;
}

.auth-divider::before,
.auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e8e8e8;
}

/* Verification Section Modern */
.verification-content-modern {
    text-align: center;
    padding: 1.5rem 2rem 2rem;
}

/* Verification Card - Expanded Width */
#verificationCard {
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
}

#verificationCard .verification-content-modern {
    padding: 2rem 2.5rem 2.5rem;
}

.verification-icon-modern {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(85, 122, 70, 0.1) 0%, rgba(85, 122, 70, 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.verification-icon-modern .material-icons {
    font-size: 2.5rem;
    color: var(--primary);
}

.verification-text {
    color: #666;
    margin-bottom: 0.5rem;
}

.verification-email {
    font-weight: 600;
    color: #333;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

/* Code Input Modern */
.code-input-container-modern {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.code-input-modern {
    width: 52px;
    height: 62px;
    text-align: center;
    font-size: 1.75rem;
    font-weight: 700;
    border: 2px solid #d1d5db;
    border-radius: 12px;
    background: #ffffff;
    color: #1f2937;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.code-input-modern:focus {
    outline: none;
    border-color: #16a34a;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.15);
    transform: scale(1.05);
}

.code-input-modern:not(:placeholder-shown),
.code-input-modern.filled {
    border-color: #16a34a;
    background: #f0fdf4;
}

/* Resend Section Modern */
.resend-section-modern {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f0f0f0;
}

.resend-section-modern p {
    color: #666;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.resend-timer {
    color: #888;
    font-size: 0.85rem;
}

/* Link Button Modern */
.btn-link-modern {
    background: none;
    border: none;
    color: var(--primary);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition-fast);
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-sm);
}

.btn-link-modern:hover {
    color: var(--primary-dark);
    background: rgba(85, 122, 70, 0.08);
    text-decoration: none;
}

/* Auth Sidebar */
.auth-sidebar {
    display: none;
}

@media (min-width: 992px) {
    .auth-sidebar {
        display: flex;
    }
}

/* Benefits Card Modern */
.benefits-card-modern {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    box-shadow: var(--shadow-medium);
}

.benefits-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.benefits-header .material-icons {
    font-size: 2rem;
    color: var(--primary);
}

.benefits-header h3 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: #333;
}

.benefits-subtitle {
    color: #666;
    font-size: 1rem;
    margin: 0 0 2rem;
    line-height: 1.6;
}

.features-preview-modern {
    background: linear-gradient(135deg, rgba(85, 122, 70, 0.05) 0%, rgba(85, 122, 70, 0.02) 100%);
    border-radius: var(--border-radius-sm);
    padding: 1.75rem;
    margin-bottom: 2rem;
}

.features-preview-modern h4 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 1.5rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #333;
}

.features-preview-modern h4 .material-icons {
    font-size: 1.5rem;
    color: #f39c12;
}

.features-grid-mini {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.feature-mini {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
    font-weight: 500;
    color: #444;
}

.feature-mini .material-icons {
    font-size: 1.5rem;
    color: var(--primary);
}

/* Trust Badges Modern */
.trust-badges-modern {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.trust-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1.25rem 1.5rem;
    border-radius: var(--border-radius-sm);
    box-shadow: var(--shadow-soft);
    font-size: 1rem;
    font-weight: 500;
    color: #444;
}

.trust-badge .material-icons {
    font-size: 1.75rem;
    color: var(--primary);
}

/* Spinning Animation */
.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .auth-hero-modern {
        padding: 2rem 1.5rem;
    }

    .auth-title {
        font-size: 1.5rem;
    }

    .auth-container-modern {
        margin-top: -1.5rem;
    }

    .auth-form {
        padding: 1.25rem 1.5rem 1.5rem;
    }

    .code-input-modern {
        width: 44px;
        height: 52px;
        font-size: 1.25rem;
    }

    .code-input-container-modern {
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .auth-hero-modern {
        padding: 1.5rem 1rem;
    }

    .auth-title {
        font-size: 1.3rem;
    }

    .code-input-modern {
        width: 40px;
        height: 48px;
        font-size: 1.1rem;
    }

    .features-grid-mini {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
