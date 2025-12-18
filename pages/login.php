<?php
include __DIR__ . '/../layouts/header.php';

if (isLoggedIn()) {
    redirect('dashboard');
}
?>

<section class="hero">
    <h1><?php echo __('login_to_smart_chashi'); ?></h1>
    <p><?php echo __('access_dashboard'); ?></p>
</section>

<div class="auth-container mt-4 mb-4">
    <div class="auth-form">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo __('login'); ?></h3>
            </div>

            <form id="loginForm" method="POST">
                <div class="form-group">
                    <label for="email"><?php echo __('email_address'); ?></label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password"><?php echo __('password'); ?></label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="" required>
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <span class="material-icons eye-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-block"><?php echo __('login'); ?></button>
                </div>

                <div class="text-center mt-3">
                    <p><?php echo __('no_account'); ?> <a href="<?php echo $base_url; ?>register"><?php echo __('register_here'); ?></a></p>
                </div>
            </form>
        </div>
    </div>

    <div class="auth-features">
        <h3><?php echo __('new_to_smart_chashi'); ?></h3>
        <p><?php echo __('join_thousands'); ?></p>
        
        <div class="mt-4">
            <h4><span class="material-icons" style="vertical-align: middle;">star</span> <?php echo __('key_features'); ?></h4>
            <ul class="features-list">
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">agriculture</span> <?php echo __('crops'); ?></li>
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">bug_report</span> <?php echo __('disease_detection'); ?></li>
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">chat</span> <?php echo __('chat'); ?></li>
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">wb_sunny</span> <?php echo __('weather'); ?></li>
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">shopping_cart</span> <?php echo __('marketplace'); ?></li>
                <li><span class="material-icons" style="vertical-align: middle; font-size: 20px;">people</span> <?php echo __('community'); ?></li>
            </ul>
        </div>

        <div class="mt-4">
            <a href="<?php echo $base_url; ?>register" class="btn btn-secondary btn-block"><?php echo __('create_account'); ?></a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
