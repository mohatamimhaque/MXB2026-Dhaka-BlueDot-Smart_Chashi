<?php
include __DIR__ . '/../layouts/header.php';

if (isLoggedIn()) {
    redirect('dashboard');
}
?>

<section class="hero">
    <h1><?php echo __('join_smart_chashi'); ?></h1>
    <p><?php echo __('create_farming_journey'); ?></p>
</section>

<div class="register-container mt-4 mb-4">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo __('create_account'); ?></h3>
        </div>

        <form id="registerForm" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName"><?php echo __('first_name'); ?> *</label>
                    <input type="text" id="firstName" name="firstName" placeholder="" required>
                </div>

                <div class="form-group">
                    <label for="lastName"><?php echo __('last_name'); ?></label>
                    <input type="text" id="lastName" name="lastName" placeholder="">
                </div>
            </div>

            <div class="form-group">
                <label for="email"><?php echo __('email_address'); ?> *</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-group">
                <label for="phone"><?php echo __('phone'); ?> *</label>
                <input type="tel" id="phone" name="phone" placeholder="+880 1234 567890" required>
            </div>

            <div class="form-group">
                <label><?php echo __('account_type'); ?> *</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="role" value="farmer" checked required>
                        <span><?php echo __('farmer'); ?></span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="role" value="officer" required>
                        <span><?php echo __('agriculture_officer'); ?></span>
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password"><?php echo __('password'); ?> *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="<?php echo __('minimum_8_chars'); ?>" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="Toggle password visibility">
                            <span class="material-icons eye-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="passwordConfirm"><?php echo __('confirm_password'); ?> *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="passwordConfirm" name="passwordConfirm" placeholder="<?php echo __('reenter_password'); ?>" required>
                        <button type="button" class="password-toggle" data-target="passwordConfirm" aria-label="Toggle password visibility">
                            <span class="material-icons eye-icon">visibility</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" required>
                    <span><?php echo __('agree_terms'); ?></span>
                </label>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-block"><?php echo __('create_account'); ?></button>
            </div>

            <div class="text-center mt-3">
                <p>Already have an account? <a href="<?php echo $base_url; ?>login">Login here</a></p>
            </div>
        </form>
    </div>

    <div class="card mt-3 text-center">
        <h4>Why Join Smart Chashi?</h4>
        <ul class="benefits-list">
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">smartphone</span> Free mobile-friendly platform</li>
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">smart_toy</span> AI-powered farming advice</li>
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">agriculture</span> Connect with other farmers</li>
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">chat</span> Chat with Chashi Bhai (AI assistant)</li>
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">bug_report</span> Disease detection with AI</li>
            <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">cloud</span> Real-time weather & alerts</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
