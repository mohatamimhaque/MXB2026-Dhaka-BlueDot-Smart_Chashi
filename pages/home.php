<?php
include __DIR__ . '/../layouts/header.php';
?>

<section class="hero">
    <h1><?php echo __('smart_chashi'); ?></h1>
    <p><span class="material-icons" style="vertical-align: middle;">agriculture</span> <?php echo __('ai_farming_assistant'); ?> <span class="material-icons" style="vertical-align: middle;">smart_toy</span></p>
    <p><?php echo __('get_smart_farming'); ?></p>
    
    <div class="hero-buttons">
        <?php if (!isLoggedIn()): ?>
            <a href="<?php echo $base_url; ?>login" class="btn"><?php echo __('login'); ?></a>
            <a href="<?php echo $base_url; ?>register" class="btn btn-secondary"><?php echo __('register'); ?></a>
        <?php else: ?>
            <a href="<?php echo $base_url; ?>dashboard" class="btn"><?php echo __('dashboard'); ?></a>
        <?php endif; ?>
    </div>
</section>

<div class="grid">
        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">agriculture</span> <?php echo __('crops'); ?></h3>
            <p><?php echo __('track_manage_crops'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>crops" class="btn btn-small"><?php echo __('manage'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">bug_report</span> <?php echo __('disease_check'); ?></h3>
            <p><?php echo __('detect_crop_diseases'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>disease" class="btn btn-small"><?php echo __('check'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">chat</span> <?php echo __('chat'); ?></h3>
            <p><?php echo __('ask_farming_questions'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>cashibhai" class="btn btn-small"><?php echo __('chat'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">wb_sunny</span> <?php echo __('weather'); ?></h3>
            <p><?php echo __('realtime_weather'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>weather" class="btn btn-small"><?php echo __('view'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">shopping_cart</span> <?php echo __('marketplace'); ?></h3>
            <p><?php echo __('buy_sell_products'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>marketplace" class="btn btn-small"><?php echo __('browse'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><span class="material-icons" style="vertical-align: middle; color: var(--primary);">people</span> <?php echo __('community'); ?></h3>
            <p><?php echo __('connect_farmers'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>community" class="btn btn-small"><?php echo __('join'); ?></a>
            <?php endif; ?>
        </div>
</div>

<section class="mt-4">
    <h2 class="text-center"><?php echo __('why_choose_smart_chashi'); ?></h2>
    
    <div class="grid mt-3">
        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">smartphone</span> <?php echo __('mobile_first_design'); ?></h4>
            <p><?php echo __('mobile_first_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">lock</span> <?php echo __('secure_private'); ?></h4>
            <p><?php echo __('secure_private_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">cloud_off</span> <?php echo __('works_offline'); ?></h4>
            <p><?php echo __('works_offline_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">attach_money</span> <?php echo __('free_open'); ?></h4>
            <p><?php echo __('free_open_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">translate</span> <?php echo __('bangla_support'); ?></h4>
            <p><?php echo __('bangla_support_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">update</span> <?php echo __('always_updated'); ?></h4>
            <p><?php echo __('always_updated_desc'); ?></p>
        </div>
    </div>
</section>

<section class="mt-4 mb-4">
    <h2 class="text-center">Success Stories</h2>
    
    <div class="grid mt-3">
        <div class="card">
            <h4>Increased Yield by 30%</h4>
            <p><strong>Farmer Rahman, Rangpur</strong></p>
            <p>"Using Chashi's recommendations, I increased my rice yield by 30%. The fertilizer guidance was spot on!"</p>
            <p class="text-muted">- Using for 6 months</p>
        </div>

        <div class="card">
            <h4>Saved Entire Crop</h4>
            <p><strong>Farmer Fatema, Sylhet</strong></p>
            <p>"The disease detection caught a fungal infection early. I saved my entire tomato crop!"</p>
            <p class="text-muted">- Using for 3 months</p>
        </div>

        <div class="card">
            <h4>Reduced Costs by 25%</h4>
            <p><strong>Farmer Ahmed, Dhaka</strong></p>
            <p>"Smart irrigation recommendations reduced my water usage and costs significantly."</p>
            <p class="text-muted">- Using for 4 months</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
