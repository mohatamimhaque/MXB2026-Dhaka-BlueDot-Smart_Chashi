<?php

if (!isLoggedIn()) {
    redirect('login');
}

// Redirect to role-specific dashboards
$user = getCurrentUser();
if ($user['role'] === 'officer') {
    redirect('officer-dashboard');
} elseif ($user['role'] === 'admin') {
    redirect('admin-dashboard');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$crops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 3", [$_SESSION['user_id']]);
$recent_alerts = $db->resultSet("SELECT * FROM alerts WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 3", [$_SESSION['user_id']]);
?>

<section class="hero">
    <h1><?php echo __('hi'); ?>, <?php echo htmlspecialchars($user['first_name']); ?></h1>
    <p><?php echo __('welcome_dashboard'); ?></p>
</section>

<div class="dashboard-stats-grid">
        <div class="stat-box">
            <span class="material-icons stat-icon">agriculture</span>
            <div class="stat-number"><?php echo count($crops); ?></div>
            <div class="stat-label"><?php echo __('crops'); ?></div>
        </div>
        <div class="stat-box">
            <span class="material-icons stat-icon">notifications_active</span>
            <div class="stat-number"><?php echo count($recent_alerts); ?></div>
            <div class="stat-label"><?php echo __('alerts'); ?></div>
        </div>
        <div class="stat-box">
            <span class="material-icons stat-icon">health_and_safety</span>
            <div class="stat-number">Good</div>
            <div class="stat-label"><?php echo __('health'); ?></div>
        </div>
        <div class="stat-box">
            <span class="material-icons stat-icon">location_on</span>
            <div class="stat-number">Active</div>
            <div class="stat-label"><?php echo __('location'); ?></div>
        </div>
    </div>

    <h2 class="mt-3"><?php echo __('quick_links'); ?></h2>
    <div class="quick-links-grid">
        <a href="<?php echo $base_url; ?>crops" class="quick-link-card">
            <span class="material-icons">agriculture</span>
            <h4><?php echo __('crops'); ?></h4>
            <p><?php echo __('manage'); ?></p>
        </a>
        <a href="<?php echo $base_url; ?>disease" class="quick-link-card">
            <span class="material-icons">bug_report</span>
            <h4><?php echo __('disease_detection'); ?></h4>
            <p><?php echo __('check'); ?></p>
        </a>
        <a href="<?php echo $base_url; ?>chat" class="quick-link-card">
            <span class="material-icons">chat</span>
            <h4><?php echo __('chat'); ?></h4>
            <p><?php echo __('ask_questions'); ?></p>
        </a>
        <a href="<?php echo $base_url; ?>weather" class="quick-link-card">
            <span class="material-icons">wb_sunny</span>
            <h4><?php echo __('weather'); ?></h4>
            <p><?php echo __('view'); ?></p>
        </a>
        <a href="<?php echo $base_url; ?>marketplace" class="quick-link-card">
            <span class="material-icons">shopping_cart</span>
            <h4><?php echo __('marketplace'); ?></h4>
            <p><?php echo __('browse'); ?></p>
        </a>
        <a href="<?php echo $base_url; ?>community" class="quick-link-card">
            <span class="material-icons">people</span>
            <h4><?php echo __('community'); ?></h4>
            <p><?php echo __('join'); ?></p>
        </a>
    </div>

    <?php if ($crops): ?>
        <h2 class="mt-3"><?php echo __('your_crops'); ?></h2>
        <div class="dashboard-crops-grid">
        <?php foreach ($crops as $crop): ?>
            <div class="card crop-summary-card">
                <div class="crop-header">
                    <span class="material-icons">eco</span>
                    <h4><?php echo htmlspecialchars($crop['crop_name']); ?></h4>
                </div>
                <p class="text-muted"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">landscape</span> Area: <?php echo $crop['area_hectares']; ?> hectares</p>
                <p class="text-muted">Status: <span class="badge badge-<?php echo $crop['status'] === 'growing' ? 'success' : 'info'; ?>"><?php echo ucfirst($crop['status']); ?></span></p>
                <a href="<?php echo $base_url; ?>crops" class="btn btn-small mt-2">View All</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card mt-3 text-center">
            <span class="material-icons" style="font-size: 48px; color: var(--text-light);">agriculture</span>
            <h4><?php echo __('no_crops_yet'); ?></h4>
            <p class="text-muted"><?php echo __('add_first_crop'); ?></p>
            <a href="<?php echo $base_url; ?>crops" class="btn btn-small mt-2">Add Crop</a>
        </div>
    <?php endif; ?>

    <?php if ($recent_alerts): ?>
        <h2 class="mt-3">Alerts</h2>
        <?php foreach ($recent_alerts as $alert): ?>
            <div class="notice notice-<?php echo $alert['priority'] === 'high' ? 'danger' : 'warning'; ?>">
                <strong><?php echo ucfirst($alert['alert_type']); ?></strong>
                <p><?php echo htmlspecialchars($alert['message']); ?></p>
                <small><?php echo date('M d H:i', strtotime($alert['created_at'])); ?></small>
            </div>
        <?php endforeach; ?>
        <a href="<?php echo $base_url; ?>alerts" class="btn mt-2">View All Alerts</a>
    <?php endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
