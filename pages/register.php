<?php
include __DIR__ . '/../layouts/header.php';

if (isLoggedIn()) {
    redirect('dashboard');
}

// Determine current step
$step = $_GET['step'] ?? 'register';
if ($step === 'profile' && !isset($_SESSION['user_id'])) {
    redirect('register');
}
?>

<!-- Hero Section - Modern Register -->
<section class="auth-hero-modern">
    <div class="auth-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="auth-hero-content">
        <div class="auth-badge">
            <span class="material-icons">verified_user</span>
            <span><?php echo __('secure_login'); ?></span>
        </div>
        <h1 class="auth-title">
            <span class="gradient-text"><?php echo __('create_your_account'); ?></span>
        </h1>
        <p class="auth-subtitle"><?php echo __('register_subtitle'); ?></p>
    </div>
</section>

<div class="auth-container-modern register-layout">
    <div class="auth-main">
        <!-- Progress Steps -->
        <div class="registration-steps-modern">
            <div class="step-modern <?php echo in_array($step, ['register', 'verify', 'profile']) ? 'active' : ''; ?> <?php echo in_array($step, ['verify', 'profile']) ? 'completed' : ''; ?>">
                <div class="step-number-modern">
                    <span class="step-num">1</span>
                    <span class="material-icons step-check">check</span>
                </div>
                <div class="step-label-modern"><?php echo __('step_account'); ?></div>
            </div>
            <div class="step-line-modern <?php echo in_array($step, ['verify', 'profile']) ? 'active' : ''; ?>"></div>
            <div class="step-modern <?php echo in_array($step, ['verify', 'profile']) ? 'active' : ''; ?> <?php echo $step === 'profile' ? 'completed' : ''; ?>">
                <div class="step-number-modern">
                    <span class="step-num">2</span>
                    <span class="material-icons step-check">check</span>
                </div>
                <div class="step-label-modern"><?php echo __('step_verify'); ?></div>
            </div>
            <div class="step-line-modern <?php echo $step === 'profile' ? 'active' : ''; ?>"></div>
            <div class="step-modern <?php echo $step === 'profile' ? 'active' : ''; ?>">
                <div class="step-number-modern">
                    <span class="step-num">3</span>
                    <span class="material-icons step-check">check</span>
                </div>
                <div class="step-label-modern"><?php echo __('step_profile'); ?></div>
            </div>
        </div>

        <!-- Step 1: Registration Form -->
        <div class="auth-card step-content" id="step1" style="<?php echo $step !== 'register' ? 'display:none;' : ''; ?>">
            <div class="auth-card-header">
                <div class="auth-icon-wrap">
                    <span class="material-icons">person_add</span>
                </div>
                <h2><?php echo __('create_account'); ?></h2>
                <p><?php echo __('fill_details_below'); ?></p>
            </div>

            <form id="registerForm" method="POST" class="auth-form">
                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label for="firstName">
                            <span class="material-icons">badge</span>
                            <?php echo __('first_name'); ?> *
                        </label>
                        <div class="input-wrapper">
                            <input type="text" id="firstName" name="firstName" placeholder="<?php echo __('enter_first_name'); ?>" required>
                            <span class="input-icon material-icons">person</span>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label for="lastName">
                            <span class="material-icons">badge</span>
                            <?php echo __('last_name'); ?>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" id="lastName" name="lastName" placeholder="<?php echo __('enter_last_name'); ?>">
                            <span class="input-icon material-icons">person_outline</span>
                        </div>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label for="email">
                        <span class="material-icons">email</span>
                        <?php echo __('email_address'); ?> *
                    </label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="<?php echo __('enter_email'); ?>" required>
                        <span class="input-icon material-icons">mail</span>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label for="phone">
                        <span class="material-icons">phone</span>
                        <?php echo __('phone'); ?> *
                    </label>
                    <div class="input-wrapper">
                        <input type="tel" id="phone" name="phone" placeholder="<?php echo __('enter_phone'); ?>" required>
                        <span class="input-icon material-icons">smartphone</span>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label>
                        <span class="material-icons">work</span>
                        <?php echo __('select_account_type'); ?> *
                    </label>
                    <div class="radio-group-modern">
                        <label class="radio-option-modern">
                            <input type="radio" name="role" value="farmer" checked required>
                            <span class="radio-box">
                                <span class="material-icons">agriculture</span>
                                <span class="radio-text"><?php echo __('farmer'); ?></span>
                            </span>
                        </label>
                        <label class="radio-option-modern">
                            <input type="radio" name="role" value="officer" required>
                            <span class="radio-box">
                                <span class="material-icons">support_agent</span>
                                <span class="radio-text"><?php echo __('agriculture_officer'); ?></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-row-modern">
                    <div class="form-group-modern">
                        <label for="regPassword">
                            <span class="material-icons">lock</span>
                            <?php echo __('password'); ?> *
                        </label>
                        <div class="input-wrapper">
                            <input type="password" id="regPassword" name="password" placeholder="<?php echo __('min_8_chars'); ?>" required>
                            <button type="button" class="password-toggle" data-target="regPassword" aria-label="Toggle password visibility">
                                <span class="material-icons eye-icon">visibility</span>
                            </button>
                        </div>
                        <small class="password-hint-modern"><?php echo __('password_requirements'); ?></small>
                    </div>

                    <div class="form-group-modern">
                        <label for="regPasswordConfirm">
                            <span class="material-icons">lock_outline</span>
                            <?php echo __('confirm_password'); ?> *
                        </label>
                        <div class="input-wrapper">
                            <input type="password" id="regPasswordConfirm" name="passwordConfirm" placeholder="<?php echo __('reenter_password'); ?>" required>
                            <button type="button" class="password-toggle" data-target="regPasswordConfirm" aria-label="Toggle password visibility">
                                <span class="material-icons eye-icon">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label class="checkbox-modern">
                        <input type="checkbox" id="agreeTerms" required>
                        <span class="checkmark"></span>
                        <span><?php echo __('agree_terms'); ?></span>
                    </label>
                </div>

                <button type="submit" class="btn-auth-primary" id="registerBtn">
                    <span class="btn-text">
                        <span class="material-icons">arrow_forward</span>
                        <?php echo __('continue'); ?>
                    </span>
                    <span class="btn-loader" style="display:none;">
                        <span class="material-icons spinning">sync</span>
                        <?php echo __('creating_account'); ?>
                    </span>
                </button>

                <div class="auth-divider">
                    <span><?php echo __('have_account'); ?></span>
                </div>

                <a href="<?php echo $base_url; ?>login" class="btn-auth-secondary">
                    <span class="material-icons">login</span>
                    <?php echo __('sign_in_here'); ?>
                </a>
            </form>
        </div>

        <!-- Step 2: Email Verification -->
        <div class="auth-card step-content" id="step2" style="<?php echo $step !== 'verify' ? 'display:none;' : ''; ?>">
            <div class="auth-card-header">
                <div class="auth-icon-wrap auth-icon-info">
                    <span class="material-icons">mark_email_read</span>
                </div>
                <h2><?php echo __('verify_your_email'); ?></h2>
                <p><?php echo __('verify_email_subtitle'); ?></p>
            </div>

            <div class="verification-content-modern">
                <div class="verification-icon-modern">
                    <span class="material-icons">mail</span>
                </div>
                <p class="verification-text"><?php echo __('code_sent_to'); ?></p>
                <p class="verification-email" id="maskedEmail"></p>
                
                <form id="verifyForm" class="auth-form" onsubmit="return false;">
                    <div class="form-group-modern">
                        <label class="code-label"><?php echo __('enter_verification_code'); ?></label>
                        <div class="code-input-container-modern">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="0" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="2" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="3" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="4" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="code-input-modern" maxlength="1" data-index="5" inputmode="numeric" pattern="[0-9]">
                        </div>
                        <input type="hidden" id="verificationCode" name="code">
                    </div>

                    <button type="button" class="btn-auth-primary" id="verifyBtn" onclick="verifyRegistrationCode()">
                        <span class="btn-text">
                            <span class="material-icons">verified</span>
                            <?php echo __('verify_email'); ?>
                        </span>
                        <span class="btn-loader" style="display:none;">
                            <span class="material-icons spinning">sync</span>
                            <?php echo __('verifying'); ?>
                        </span>
                    </button>
                </form>

                <div class="resend-section-modern">
                    <p><?php echo __('didnt_receive_code'); ?></p>
                    <button type="button" class="btn-link-modern" id="resendCodeBtn"><?php echo __('resend_code'); ?></button>
                    <p class="resend-timer" id="resendTimer" style="display:none;"><?php echo __('resend_in'); ?> <span id="timerCount">60</span>s</p>
                </div>
            </div>
        </div>

        <!-- Step 3: Profile Photo -->
        <div class="auth-card step-content" id="step3" style="<?php echo $step !== 'profile' ? 'display:none;' : ''; ?>">
            <div class="auth-card-header">
                <div class="auth-icon-wrap auth-icon-success">
                    <span class="material-icons">add_a_photo</span>
                </div>
                <h2><?php echo __('add_profile_photo'); ?></h2>
                <p><?php echo __('profile_photo_subtitle'); ?></p>
            </div>

            <form id="profileForm" enctype="multipart/form-data" class="auth-form">
                <div class="profile-photo-section-modern">
                    <div class="photo-preview-modern" id="photoPreview">
                        <span class="material-icons">person</span>
                    </div>
                    <label for="profilePhoto" class="photo-upload-btn-modern">
                        <span class="material-icons">cloud_upload</span>
                        <span><?php echo __('choose_photo'); ?></span>
                    </label>
                    <input type="file" id="profilePhoto" name="profile_photo" accept="image/*" style="display:none;">
                    <p class="photo-hint-modern"><?php echo __('profile_photo_required'); ?></p>
                    <p class="photo-formats-modern"><?php echo __('supported_formats'); ?></p>
                </div>

                <button type="submit" class="btn-auth-primary" id="completeBtn">
                    <span class="btn-text">
                        <span class="material-icons">check_circle</span>
                        <?php echo __('complete_registration'); ?>
                    </span>
                    <span class="btn-loader" style="display:none;">
                        <span class="material-icons spinning">sync</span>
                        <?php echo __('completing'); ?>
                    </span>
                </button>
            </form>
        </div>
    </div>

    <!-- Benefits Sidebar -->
    <div class="auth-sidebar" id="benefitsSidebar" style="<?php echo $step !== 'register' ? 'display:none;' : ''; ?>">
        <div class="benefits-card-modern">
            <div class="benefits-header">
                <span class="material-icons">rocket_launch</span>
                <h3><?php echo __('register_benefits_title'); ?></h3>
            </div>
            <p class="benefits-subtitle"><?php echo __('join_smart_chashi_desc'); ?></p>
            
            <ul class="benefits-list-modern">
                <li>
                    <span class="benefit-icon"><span class="material-icons">smartphone</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_1'); ?></span>
                </li>
                <li>
                    <span class="benefit-icon"><span class="material-icons">smart_toy</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_2'); ?></span>
                </li>
                <li>
                    <span class="benefit-icon"><span class="material-icons">agriculture</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_3'); ?></span>
                </li>
                <li>
                    <span class="benefit-icon"><span class="material-icons">chat</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_4'); ?></span>
                </li>
                <li>
                    <span class="benefit-icon"><span class="material-icons">bug_report</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_5'); ?></span>
                </li>
                <li>
                    <span class="benefit-icon"><span class="material-icons">cloud</span></span>
                    <span class="benefit-text"><?php echo __('register_benefit_6'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Trust Badges -->
        <div class="trust-badges-modern">
            <div class="trust-badge">
                <span class="material-icons">verified_user</span>
                <span><?php echo __('secure_platform'); ?></span>
            </div>
            <div class="trust-badge">
                <span class="material-icons">support_agent</span>
                <span><?php echo __('support_24_7'); ?></span>
            </div>
            <div class="trust-badge">
                <span class="material-icons">groups</span>
                <span><?php echo __('active_community'); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== Register Page Modern Styles ===== */
:root {
    --primary: #557a46;
    --primary-dark: #3d5a34;
    --primary-light: #6b9a56;
    --gradient-green: linear-gradient(135deg, #557a46 0%, #6b9a56 50%, #7cb668 100%);
    --gradient-blue: linear-gradient(135deg, #3498db 0%, #5dade2 100%);
    --gradient-orange: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
    --gradient-success: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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

@keyframes particleFloat {
    0%, 100% { transform: translateY(0) translateX(0); opacity: 0.5; }
    25% { transform: translateY(-30px) translateX(15px); opacity: 0.8; }
    50% { transform: translateY(-50px) translateX(-15px); opacity: 0.5; }
    75% { transform: translateY(-30px) translateX(25px); opacity: 0.8; }
}

.auth-hero-content {
    position: relative;
    z-index: 1;
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
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    max-width: 1100px;
    margin: -2rem auto 2rem;
    padding: 0 1rem;
    position: relative;
    z-index: 2;
}

.auth-container-modern.register-layout {
    max-width: 1100px;
}

/* Center layout for verify and profile steps */
.auth-container-modern.center-layout {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    max-width: 600px;
}

.auth-container-modern.center-layout .auth-card {
    width: 100%;
    max-width: 500px;
}

.auth-container-modern.center-layout .benefits-sidebar-modern {
    display: none;
}

@media (min-width: 768px) {
    .auth-container-modern {
        grid-template-columns: 1fr 320px;
        padding: 0 2rem;
    }
    
    .auth-container-modern.center-layout {
        grid-template-columns: 1fr;
    }
}

.auth-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Registration Steps Modern */
.registration-steps-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    padding: 1.5rem;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
}

.step-modern {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.step-number-modern {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e8e8e8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    color: #999;
    transition: var(--transition-medium);
    position: relative;
}

.step-number-modern .step-num {
    display: block;
}

.step-number-modern .step-check {
    display: none;
    font-size: 1.25rem;
}

.step-modern.active .step-number-modern {
    background: var(--gradient-green);
    color: white;
    box-shadow: 0 4px 15px rgba(85, 122, 70, 0.3);
}

.step-modern.completed .step-number-modern {
    background: var(--gradient-success);
    color: white;
}

.step-modern.completed .step-number-modern .step-num {
    display: none;
}

.step-modern.completed .step-number-modern .step-check {
    display: block;
}

.step-label-modern {
    font-size: 0.8rem;
    font-weight: 600;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: var(--transition-fast);
}

.step-modern.active .step-label-modern,
.step-modern.completed .step-label-modern {
    color: var(--primary);
}

.step-line-modern {
    width: 60px;
    height: 3px;
    background: #e8e8e8;
    border-radius: 2px;
    margin: 0 0.75rem;
    margin-bottom: 1.5rem;
    transition: var(--transition-medium);
}

.step-line-modern.active {
    background: var(--gradient-green);
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

.auth-icon-wrap.auth-icon-info {
    background: var(--gradient-blue);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
}

.auth-icon-wrap.auth-icon-success {
    background: var(--gradient-success);
    box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
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

.form-row-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 600px) {
    .form-row-modern {
        grid-template-columns: 1fr;
    }
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

.code-label {
    justify-content: center;
    margin-bottom: 1rem !important;
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

/* Radio Group Modern */
.radio-group-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}




.radio-option-modern {
    cursor: pointer;
}

.radio-option-modern input[type="radio"] {
    display: none;
}

.radio-option-modern .radio-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    border: 2px solid #e8e8e8;
    border-radius: var(--border-radius-sm);
    background: #fafafa;
    transition: var(--transition-fast);
    min-width: 180px;
}

.radio-option-modern .radio-box .material-icons {
    font-size: 2rem;
    color: #999;
    transition: var(--transition-fast);
}

.radio-option-modern .radio-text {
    font-weight: 600;
    color: #666;
    font-size: 0.95rem;
}

.radio-option-modern input[type="radio"]:checked + .radio-box {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(85, 122, 70, 0.08) 0%, rgba(85, 122, 70, 0.03) 100%);
}

.radio-option-modern input[type="radio"]:checked + .radio-box .material-icons {
    color: var(--primary);
}

.radio-option-modern input[type="radio"]:checked + .radio-box .radio-text {
    color: var(--primary);
}

.radio-option-modern:hover .radio-box {
    border-color: var(--primary-light);
}

/* Checkbox Modern */
.checkbox-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: #555;
}

.checkbox-modern input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
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

.verification-icon-modern {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(52, 152, 219, 0.05) 100%);
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
    color: #3498db;
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
    height: 60px;
    text-align: center;
    font-size: 1.5rem;
    font-weight: 700;
    border: 2px solid #e8e8e8;
    border-radius: var(--border-radius-sm);
    background: #fafafa;
    transition: var(--transition-fast);
}

.code-input-modern:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(85, 122, 70, 0.1);
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
}

/* Profile Photo Section Modern */
.profile-photo-section-modern {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 0 2rem;
}

.photo-preview-modern {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(85, 122, 70, 0.1) 0%, rgba(85, 122, 70, 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    transition: var(--transition-medium);
}

.photo-preview-modern .material-icons {
    font-size: 4rem;
    color: #ccc;
}

.photo-preview-modern img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-preview-modern:hover {
    transform: scale(1.02);
}

.photo-upload-btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
    border-radius: var(--border-radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-medium);
    margin-bottom: 1rem;
}

.photo-upload-btn-modern:hover {
    background: var(--primary);
    color: white;
}

.photo-upload-btn-modern .material-icons {
    font-size: 1.25rem;
}

.photo-hint-modern {
    color: #666;
    font-size: 0.9rem;
    margin: 0 0 0.25rem;
}

.photo-hint-modern strong {
    color: var(--primary);
}

.photo-formats-modern {
    color: #999;
    font-size: 0.8rem;
    margin: 0;
}

/* Auth Sidebar */
.auth-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Benefits Card Modern */
.benefits-card-modern {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
}

.benefits-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.benefits-header .material-icons {
    font-size: 1.5rem;
    color: var(--primary);
}

.benefits-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.benefits-subtitle {
    color: #666;
    font-size: 0.9rem;
    margin: 0 0 1.5rem;
}

/* Benefits List Modern */
.benefits-list-modern {
    list-style: none;
    padding: 0;
    margin: 0;
}

.benefits-list-modern li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.benefits-list-modern li:last-child {
    border-bottom: none;
}

.benefit-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(85, 122, 70, 0.1) 0%, rgba(85, 122, 70, 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.benefit-icon .material-icons {
    font-size: 1.1rem;
    color: var(--primary);
}

.benefit-text {
    font-size: 0.9rem;
    color: #555;
}

/* Trust Badges Modern */
.trust-badges-modern {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.trust-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: white;
    padding: 1rem 1.25rem;
    border-radius: var(--border-radius-sm);
    box-shadow: var(--shadow-soft);
    font-size: 0.9rem;
    color: #555;
}

.trust-badge .material-icons {
    font-size: 1.25rem;
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

    .registration-steps-modern {
        padding: 1rem;
    }

    .step-line-modern {
        width: 30px;
    }

    .step-label-modern {
        font-size: 0.7rem;
    }
    
    .radio-group-modern {
        grid-template-columns: 1fr;
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

    .step-number-modern {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
    }

    .step-line-modern {
        width: 20px;
        margin: 0 0.5rem;
    }
    
    .photo-preview-modern {
        width: 120px;
        height: 120px;
    }
}
</style>

<script>
// Apply center layout on page load if on verify or profile step
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const step = urlParams.get('step');
    
    if (step === 'verify' || step === 'profile') {
        const container = document.querySelector('.auth-container-modern');
        if (container) {
            container.classList.add('center-layout');
            container.classList.remove('register-layout');
        }
        const sidebar = document.getElementById('benefitsSidebar');
        if (sidebar) {
            sidebar.style.display = 'none';
        }
    }
});

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

// Store registration data
let registrationData = {};

// Registration form submission
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('registerBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    // Validate passwords match
    const password = document.getElementById('regPassword').value;
    const passwordConfirm = document.getElementById('regPasswordConfirm').value;
    
    if (password !== passwordConfirm) {
        App.showAlert('<?php echo __('passwords_not_match'); ?>', 'danger');
        return;
    }
    
    // Show loading
    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    
    const data = {
        action: 'send_code',
        firstName: document.getElementById('firstName').value,
        lastName: document.getElementById('lastName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        role: document.querySelector('input[name="role"]:checked').value,
        password: password
    };
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            registrationData = data;
            document.getElementById('maskedEmail').textContent = result.email;
            
            // Show verification step
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
            document.getElementById('benefitsSidebar').style.display = 'none';
            document.querySelector('.auth-container-modern').classList.add('center-layout');
            document.querySelector('.auth-container-modern').classList.remove('register-layout');
            
            // Focus first code input            // Update progress
            document.querySelectorAll('.registration-steps-modern .step-modern')[0].classList.add('completed');
            document.querySelectorAll('.registration-steps-modern .step-modern')[1].classList.add('active');
            document.querySelectorAll('.registration-steps-modern .step-line-modern')[0].classList.add('active');
            
            // Focus first code input
            document.querySelector('.code-input-modern').focus();
            startResendTimer();
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('<?php echo __('error_occurred'); ?>', 'danger');
    } finally {
        btn.disabled = false;
        btnText.style.display = 'inline-flex';
        btnLoader.style.display = 'none';
    }
});

// Code input handling
const codeInputs = document.querySelectorAll('.code-input-modern');
codeInputs.forEach((input, index) => {
    input.addEventListener('input', function(e) {
        const value = e.target.value.replace(/[^0-9]/g, '');
        e.target.value = value;
        
        if (value && index < codeInputs.length - 1) {
            codeInputs[index + 1].focus();
        }
        
        // Update hidden input
        let fullCode = '';
        codeInputs.forEach(inp => fullCode += inp.value);
        document.getElementById('verificationCode').value = fullCode;
        
        // Auto-submit when complete
        if (fullCode.length === 6) {
            setTimeout(() => verifyRegistrationCode(), 150);
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
            if (codeInputs[i]) {
                codeInputs[i].value = char;
            }
        });
        document.getElementById('verificationCode').value = pastedData;
        if (pastedData.length === 6) {
            verifyRegistrationCode();
        }
    });
});

// Verify form submission - named function for onclick
async function verifyRegistrationCode() {
    const code = document.getElementById('verificationCode').value;
    if (code.length !== 6) {
        App.showAlert('<?php echo __('enter_valid_code'); ?>', 'danger');
        return;
    }
    
    const btn = document.getElementById('verifyBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'verify_code', code: code })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            
            // Show profile step
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'block';
            document.querySelector('.auth-container-modern').classList.add('center-layout');
            
            // Update progress
            document.querySelectorAll('.registration-steps-modern .step-modern')[1].classList.add('completed');
            document.querySelectorAll('.registration-steps-modern .step-modern')[2].classList.add('active');
            document.querySelectorAll('.registration-steps-modern .step-line-modern')[1].classList.add('active');
        } else {
            App.showAlert(result.message, 'danger');
            // Clear code inputs
            codeInputs.forEach(inp => inp.value = '');
            codeInputs[0].focus();
        }
    } catch (error) {
        App.showAlert('<?php echo __('error_occurred'); ?>', 'danger');
    } finally {
        btn.disabled = false;
        btnText.style.display = 'inline-flex';
        btnLoader.style.display = 'none';
    }
}

// Resend code
let resendTimer = null;
function startResendTimer() {
    let seconds = 60;
    document.getElementById('resendCodeBtn').style.display = 'none';
    document.getElementById('resendTimer').style.display = 'block';
    document.getElementById('timerCount').textContent = seconds;
    
    resendTimer = setInterval(() => {
        seconds--;
        document.getElementById('timerCount').textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(resendTimer);
            document.getElementById('resendCodeBtn').style.display = 'inline';
            document.getElementById('resendTimer').style.display = 'none';
        }
    }, 1000);
}

document.getElementById('resendCodeBtn')?.addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '<?php echo __('sending'); ?>...';
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_code' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            startResendTimer();
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        App.showAlert('<?php echo __('error_occurred'); ?>', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = '<?php echo __('resend_code'); ?>';
    }
});

// Profile photo preview
document.getElementById('profilePhoto')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            App.showAlert('<?php echo __('file_too_large'); ?>', 'danger');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Profile Preview">';
        };
        reader.readAsDataURL(file);
    }
});

// Profile form submission
document.getElementById('profileForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Check if photo is selected
    const photoInput = document.getElementById('profilePhoto');
    if (!photoInput.files[0]) {
        App.showAlert('<?php echo __('please_upload_photo'); ?>', 'danger');
        return;
    }
    
    await completeProfile();
});

async function completeProfile() {
    const btn = document.getElementById('completeBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    
    const formData = new FormData();
    formData.append('action', 'complete_profile');
    
    const photoInput = document.getElementById('profilePhoto');
    if (photoInput.files[0]) {
        formData.append('profile_photo', photoInput.files[0]);
    }
    
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.error('Response text:', text);
            throw new Error('Invalid server response. Please try again.');
        }
        
        if (result.success) {
            App.showAlert(result.message, 'success');
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>' + result.redirect;
            }, 1500);
        } else {
            App.showAlert(result.message, 'danger');
        }
    } catch (error) {
        console.error('Complete profile error:', error);
        App.showAlert(error.message || '<?php echo __('error_occurred'); ?>', 'danger');
    } finally {
        btn.disabled = false;
        btnText.style.display = 'inline-flex';
        btnLoader.style.display = 'none';
    }
}

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

// Check if coming back to profile step
<?php if ($step === 'profile'): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.registration-steps-modern .step-modern')[0].classList.add('completed');
    document.querySelectorAll('.registration-steps-modern .step-modern')[1].classList.add('completed');
    document.querySelectorAll('.registration-steps-modern .step-modern')[2].classList.add('active');
    document.querySelectorAll('.registration-steps-modern .step-line-modern')[0].classList.add('active');
    document.querySelectorAll('.registration-steps-modern .step-line-modern')[1].classList.add('active');
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
