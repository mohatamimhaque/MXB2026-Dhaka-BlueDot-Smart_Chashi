<?php
/**
 * Extension Officer Dashboard
 * Dedicated dashboard for agricultural extension officers
 */

// Authentication and role check
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer') {
    // Redirect to appropriate dashboard based on role
    if ($currentUser['role'] === 'admin') {
        header('Location: ' . $base_url . 'admin-secure/pages/admin-dashboard.php');
        exit;
    } elseif ($currentUser['role'] === 'farmer') {
        redirect('farmer-dashboard');
    } else {
        redirect('home');
    }
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();

// Get statistics
$totalFarmers = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
$activeCrops = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE status = 'growing'", [])['count'] ?? 0;
$diseaseReports30 = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;
$alertsIssued7 = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$_SESSION['user_id']])['count'] ?? 0;

// Get upcoming visits
$upcomingVisits = $db->resultSet("SELECT fv.*, u.first_name, u.last_name, u.phone
    FROM field_visits fv 
    JOIN users u ON fv.farmer_id = u.user_id 
    WHERE fv.officer_id = ? AND fv.status = 'scheduled' AND fv.visit_date >= CURDATE()
    ORDER BY fv.visit_date ASC, fv.visit_time ASC LIMIT 5", [$_SESSION['user_id']]);

// Get recent disease detections
$recentDetections = $db->resultSet("SELECT dr.*, u.first_name, u.last_name, u.phone, c.crop_name
    FROM disease_reports dr 
    JOIN users u ON dr.user_id = u.user_id 
    LEFT JOIN crop_data c ON dr.crop_id = c.crop_id 
    ORDER BY dr.created_at DESC LIMIT 10", []);

// Get farmers needing attention (high severity issues in last 7 days)
$farmersNeedingAttention = $db->resultSet("SELECT u.user_id, u.first_name, u.last_name, u.phone,
    COUNT(dr.detection_id) as issue_count,
    MAX(dr.created_at) as last_issue
    FROM users u 
    JOIN disease_reports dr ON u.user_id = dr.user_id 
    WHERE u.role = 'farmer' AND dr.severity = 'high' AND dr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY u.user_id 
    ORDER BY issue_count DESC, last_issue DESC
    LIMIT 5", []);

// Get crops by region - Using crop_data directly since farmer_profiles doesn't exist
$cropsByRegion = [];

// Get recent advisories created by this officer
$recentAdvisories = $db->resultSet("SELECT * FROM advisories WHERE created_by = ? ORDER BY created_at DESC LIMIT 5", [$_SESSION['user_id']]);

// Get regions list
$regions = ['Dhaka', 'Chittagong', 'Khulna', 'Rangpur', 'Sylhet', 'Barisal', 'Rajshahi', 'Mymensingh'];

// Get all farmers for dropdowns
$allFarmers = $db->resultSet("SELECT user_id, first_name, last_name, phone
    FROM users 
    WHERE role = 'farmer' 
    ORDER BY first_name ASC", []);
?>

<section class="officer-hero">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">verified_user</span>
            <span><?php echo __('agriculture_officer'); ?></span>
        </div>
        <h1>
            <span class="wave-emoji">👋</span> 
            <?php echo __('welcome_officer'); ?>, <?php echo htmlspecialchars($user['first_name']); ?>!
        </h1>
        <p class="hero-subtitle"><?php echo __('monitor_support_farmers'); ?></p>
        <div class="hero-quick-stats">
            <div class="quick-stat">
                <span class="material-icons">schedule</span>
                <span><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div class="quick-stat">
                <span class="material-icons">location_on</span>
                <span>Bangladesh</span>
            </div>
        </div>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">agriculture</span>
            <span><?php echo $activeCrops; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">people</span>
            <span><?php echo $totalFarmers; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">eco</span>
        </div>
    </div>
</section>

<!-- Live Status Bar -->
<div class="live-status-bar">
    <div class="status-item status-online">
        <span class="status-dot pulse"></span>
        <span><?php echo __('system_online'); ?></span>
    </div>
    <div class="status-item">
        <span class="material-icons">update</span>
        <span><?php echo __('last_sync'); ?>: <span id="lastSyncTime"><?php echo date('H:i'); ?></span></span>
    </div>
    <div class="status-item">
        <span class="material-icons">cloud_done</span>
        <span><?php echo __('data_synced'); ?></span>
    </div>
    <button class="btn-icon-small" onclick="refreshDashboard()" title="<?php echo __('refresh'); ?>">
        <span class="material-icons" id="refreshIcon">refresh</span>
    </button>
</div>

<!-- Officer Statistics - Advanced Cards -->
<div class="stats-section">
    <h2 class="section-title-modern">
        <div class="title-icon"><span class="material-icons">analytics</span></div>
        <div class="title-text">
            <span><?php echo __('your_oversight_stats'); ?></span>
            <small><?php echo __('realtime_overview'); ?></small>
        </div>
    </h2>
    <div class="officer-stats-grid-modern">
        <div class="stat-card-modern stat-gradient-green">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon-wrap">
                    <span class="material-icons">people</span>
                </div>
                <div class="stat-info">
                    <div class="stat-number-modern" data-count="<?php echo $totalFarmers; ?>">0</div>
                    <div class="stat-label-modern"><?php echo __('total_farmers'); ?></div>
                </div>
                <div class="stat-trend trend-up">
                    <span class="material-icons">trending_up</span>
                    <span>+12%</span>
                </div>
            </div>
            <div class="stat-chart-mini" id="farmersChart"></div>
        </div>
        
        <div class="stat-card-modern stat-gradient-blue">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon-wrap">
                    <span class="material-icons">agriculture</span>
                </div>
                <div class="stat-info">
                    <div class="stat-number-modern" data-count="<?php echo $activeCrops; ?>">0</div>
                    <div class="stat-label-modern"><?php echo __('active_crops'); ?></div>
                </div>
                <div class="stat-trend trend-up">
                    <span class="material-icons">trending_up</span>
                    <span>+8%</span>
                </div>
            </div>
            <div class="stat-chart-mini" id="cropsChart"></div>
        </div>
        
        <div class="stat-card-modern stat-gradient-orange">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon-wrap">
                    <span class="material-icons">bug_report</span>
                </div>
                <div class="stat-info">
                    <div class="stat-number-modern" data-count="<?php echo $diseaseReports30; ?>">0</div>
                    <div class="stat-label-modern"><?php echo __('reports_30d'); ?></div>
                </div>
                <div class="stat-trend trend-down">
                    <span class="material-icons">trending_down</span>
                    <span>-5%</span>
                </div>
            </div>
            <div class="stat-chart-mini" id="reportsChart"></div>
        </div>
        
        <div class="stat-card-modern stat-gradient-red">
            <div class="stat-card-bg"></div>
            <div class="stat-card-content">
                <div class="stat-icon-wrap">
                    <span class="material-icons">notifications_active</span>
                </div>
                <div class="stat-info">
                    <div class="stat-number-modern" data-count="<?php echo $alertsIssued7; ?>">0</div>
                    <div class="stat-label-modern"><?php echo __('alerts_7d'); ?></div>
                </div>
                <div class="stat-trend trend-neutral">
                    <span class="material-icons">trending_flat</span>
                    <span>0%</span>
                </div>
            </div>
            <div class="stat-chart-mini" id="alertsChart"></div>
        </div>
    </div>
</div>

<!-- Quick Actions - Modern Design -->
<div class="actions-section">
    <h2 class="section-title-modern">
        <div class="title-icon"><span class="material-icons">bolt</span></div>
        <div class="title-text">
            <span><?php echo __('officer_actions'); ?></span>
            <small><?php echo __('quick_access_tools'); ?></small>
        </div>
    </h2>
    <div class="officer-actions-grid-modern">
        <div class="action-card-modern action-warning" onclick="openModal('alertModal')">
            <div class="action-glow"></div>
            <div class="action-icon-container">
                <div class="action-icon-bg"></div>
                <span class="material-icons">campaign</span>
            </div>
            <div class="action-content">
                <h3><?php echo __('issue_alert'); ?></h3>
                <p><?php echo __('send_alerts_farmers'); ?></p>
            </div>
            <div class="action-arrow">
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>
        
        <div class="action-card-modern action-info" onclick="openModal('advisoryModal')">
            <div class="action-glow"></div>
            <div class="action-icon-container">
                <div class="action-icon-bg"></div>
                <span class="material-icons">menu_book</span>
            </div>
            <div class="action-content">
                <h3><?php echo __('create_advisory'); ?></h3>
                <p><?php echo __('create_publish_advisories'); ?></p>
            </div>
            <div class="action-arrow">
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>
        
        <div class="action-card-modern action-primary" onclick="openModal('visitModal')">
            <div class="action-glow"></div>
            <div class="action-icon-container">
                <div class="action-icon-bg"></div>
                <span class="material-icons">event_available</span>
            </div>
            <div class="action-content">
                <h3><?php echo __('schedule_visit'); ?></h3>
                <p><?php echo __('schedule_field_visits'); ?></p>
            </div>
            <div class="action-arrow">
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>
        
        <div class="action-card-modern action-success" onclick="openModal('farmerSearchModal')">
            <div class="action-glow"></div>
            <div class="action-icon-container">
                <div class="action-icon-bg"></div>
                <span class="material-icons">person_search</span>
            </div>
            <div class="action-content">
                <h3><?php echo __('find_farmer'); ?></h3>
                <p><?php echo __('search_view_farmers'); ?></p>
            </div>
            <div class="action-arrow">
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Field Visits - Modern Timeline -->
<div class="visits-section">
    <h2 class="section-title-modern">
        <div class="title-icon"><span class="material-icons">event_available</span></div>
        <div class="title-text">
            <span><?php echo __('upcoming_field_visits'); ?></span>
            <small><?php echo __('scheduled_visits_overview'); ?></small>
        </div>
        <button class="btn-add-new" onclick="openModal('visitModal')">
            <span class="material-icons">add</span>
            <span><?php echo __('add_new'); ?></span>
        </button>
    </h2>
    <div class="visits-card-modern">
        <?php if ($upcomingVisits): ?>
        <div class="visits-timeline">
            <?php foreach ($upcomingVisits as $index => $visit): ?>
            <div class="timeline-item <?php echo $index === 0 ? 'timeline-next' : ''; ?>">
                <div class="timeline-marker">
                    <span class="material-icons"><?php echo $index === 0 ? 'schedule' : 'event'; ?></span>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <div class="timeline-date">
                            <span class="day"><?php echo date('d', strtotime($visit['visit_date'])); ?></span>
                            <span class="month"><?php echo date('M', strtotime($visit['visit_date'])); ?></span>
                        </div>
                        <div class="timeline-info">
                            <h4><?php echo htmlspecialchars($visit['first_name'] . ' ' . ($visit['last_name'] ?? '')); ?></h4>
                            <div class="timeline-meta">
                                <span><span class="material-icons">location_on</span> <?php echo __('na'); ?></span>
                                <?php if ($visit['visit_time']): ?>
                                <span><span class="material-icons">schedule</span> <?php echo date('h:i A', strtotime($visit['visit_time'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="timeline-purpose"><?php echo htmlspecialchars(substr($visit['purpose'] ?? __('general_inspection'), 0, 60)); ?><?php echo strlen($visit['purpose'] ?? '') > 60 ? '...' : ''; ?></p>
                        </div>
                    </div>
                    <div class="timeline-actions">
                        <a href="tel:<?php echo $visit['phone']; ?>" class="btn-icon-modern" title="<?php echo __('call'); ?>">
                            <span class="material-icons">call</span>
                        </a>
                        <button class="btn-icon-modern btn-success" onclick="completeVisitModal(<?php echo $visit['visit_id']; ?>)" title="<?php echo __('mark_complete'); ?>">
                            <span class="material-icons">check_circle</span>
                        </button>
                        <button class="btn-icon-modern btn-danger" onclick="cancelVisit(<?php echo $visit['visit_id']; ?>)" title="<?php echo __('cancel_visit'); ?>">
                            <span class="material-icons">cancel</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state-modern">
            <div class="empty-illustration">
                <span class="material-icons">event_busy</span>
            </div>
            <h3><?php echo __('no_upcoming_visits'); ?></h3>
            <p><?php echo __('schedule_visit_message'); ?></p>
            <button class="btn-modern btn-primary" onclick="openModal('visitModal')">
                <span class="material-icons">add</span>
                <?php echo __('schedule_a_visit'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Farmers Needing Attention - Priority Alert Cards -->
<?php if ($farmersNeedingAttention): ?>
<div class="attention-section">
    <h2 class="section-title-modern section-alert">
        <div class="title-icon pulse-alert"><span class="material-icons">priority_high</span></div>
        <div class="title-text">
            <span><?php echo __('farmers_needing_attention'); ?></span>
            <small><?php echo __('high_priority_cases'); ?></small>
        </div>
        <span class="attention-count"><?php echo count($farmersNeedingAttention); ?></span>
    </h2>
    <div class="attention-cards-grid">
        <?php foreach ($farmersNeedingAttention as $farmer): ?>
        <div class="attention-card">
            <div class="attention-severity">
                <span class="material-icons">warning</span>
                <span><?php echo $farmer['issue_count']; ?> <?php echo __('issues'); ?></span>
            </div>
            <div class="attention-farmer">
                <div class="farmer-avatar">
                    <?php echo strtoupper(substr($farmer['first_name'], 0, 1)); ?>
                </div>
                <div class="farmer-details">
                    <h4><?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?></h4>
                    <p><span class="material-icons">location_on</span> <?php echo __('na'); ?></p>
                    <p><span class="material-icons">schedule</span> <?php echo __('last_issue'); ?>: <?php echo date('M d', strtotime($farmer['last_issue'])); ?></p>
                </div>
            </div>
            <div class="attention-actions">
                <a href="tel:<?php echo $farmer['phone']; ?>" class="btn-action-pill btn-call">
                    <span class="material-icons">call</span>
                    <?php echo __('call'); ?>
                </a>
                <a href="<?php echo $base_url; ?>?page=farmer-profile-view&id=<?php echo $farmer['user_id']; ?>" class="btn-action-pill btn-view">
                    <span class="material-icons">visibility</span>
                    <?php echo __('view'); ?>
                </a>
                <button class="btn-action-pill btn-schedule" onclick="scheduleVisitFor(<?php echo $farmer['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? ''))); ?>')">
                    <span class="material-icons">event</span>
                    <?php echo __('visit'); ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Crops by Region & Recent Advisories - Modern Dashboard Grid -->
<div class="dashboard-grid-modern">
    <!-- Crops by Region - Chart Card -->
    <div class="dashboard-card-modern">
        <div class="card-header-modern">
            <div class="card-title">
                <span class="material-icons">map</span>
                <span><?php echo __('crops_by_region'); ?></span>
            </div>
            <div class="card-actions">
                <button class="btn-icon-mini" title="<?php echo __('expand'); ?>">
                    <span class="material-icons">open_in_full</span>
                </button>
            </div>
        </div>
        <div class="card-body-modern">
            <?php if ($cropsByRegion): ?>
            <div class="region-chart-container">
                <canvas id="regionChart"></canvas>
            </div>
            <div class="region-legend">
                <?php foreach (array_slice($cropsByRegion, 0, 5) as $region): ?>
                <div class="legend-item">
                    <span class="legend-color" style="background: hsl(<?php echo rand(100, 200); ?>, 70%, 50%);"></span>
                    <span class="legend-label"><?php echo htmlspecialchars($region['region']); ?></span>
                    <span class="legend-value"><?php echo $region['crop_count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-mini">
                <span class="material-icons">landscape</span>
                <p><?php echo __('no_crop_data_available'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Advisories - Timeline Card -->
    <div class="dashboard-card-modern">
        <div class="card-header-modern">
            <div class="card-title">
                <span class="material-icons">campaign</span>
                <span><?php echo __('your_recent_advisories'); ?></span>
            </div>
            <div class="card-actions">
                <button class="btn-add-mini" onclick="openModal('advisoryModal')" title="<?php echo __('create_new'); ?>">
                    <span class="material-icons">add</span>
                </button>
            </div>
        </div>
        <div class="card-body-modern">
            <?php if ($recentAdvisories): ?>
            <div class="advisory-timeline">
                <?php foreach ($recentAdvisories as $advisory): ?>
                <div class="advisory-timeline-item">
                    <div class="advisory-icon priority-<?php echo $advisory['priority'] ?? 'low'; ?>">
                        <span class="material-icons">
                            <?php 
                            $iconMap = ['weather' => 'cloud', 'pest_control' => 'bug_report', 'irrigation' => 'water_drop', 'market' => 'storefront'];
                            echo $iconMap[$advisory['advisory_type']] ?? 'info';
                            ?>
                        </span>
                    </div>
                    <div class="advisory-content">
                        <h5><?php echo htmlspecialchars($advisory['title']); ?></h5>
                        <p><?php echo htmlspecialchars(substr($advisory['content'], 0, 60)); ?>...</p>
                        <span class="advisory-time"><?php echo date('M d, Y', strtotime($advisory['created_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-mini">
                <span class="material-icons">post_add</span>
                <p><?php echo __('no_advisories_created'); ?></p>
                <button class="btn-mini" onclick="openModal('advisoryModal')">
                    <?php echo __('create_first'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Stats Mini Cards -->
    <div class="dashboard-card-modern stats-mini-card">
        <div class="card-header-modern">
            <div class="card-title">
                <span class="material-icons">insights</span>
                <span><?php echo __('quick_insights'); ?></span>
            </div>
        </div>
        <div class="card-body-modern">
            <div class="insights-grid">
                <div class="insight-item">
                    <div class="insight-icon success">
                        <span class="material-icons">check_circle</span>
                    </div>
                    <div class="insight-info">
                        <span class="insight-value"><?php echo count($upcomingVisits); ?></span>
                        <span class="insight-label"><?php echo __('pending_visits'); ?></span>
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon warning">
                        <span class="material-icons">warning</span>
                    </div>
                    <div class="insight-info">
                        <span class="insight-value"><?php echo count($farmersNeedingAttention); ?></span>
                        <span class="insight-label"><?php echo __('need_attention'); ?></span>
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon info">
                        <span class="material-icons">article</span>
                    </div>
                    <div class="insight-info">
                        <span class="insight-value"><?php echo count($recentAdvisories); ?></span>
                        <span class="insight-label"><?php echo __('advisories'); ?></span>
                    </div>
                </div>
                <div class="insight-item">
                    <div class="insight-icon primary">
                        <span class="material-icons">public</span>
                    </div>
                    <div class="insight-info">
                        <span class="insight-value"><?php echo count($cropsByRegion); ?></span>
                        <span class="insight-label"><?php echo __('active_regions'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<div class="fab-container">
    <button class="fab-main" id="fabMain" onclick="toggleFabMenu()">
        <span class="material-icons">add</span>
    </button>
    <div class="fab-menu" id="fabMenu">
        <button class="fab-item fab-alert" onclick="openModal('alertModal'); toggleFabMenu();" title="<?php echo __('issue_alert'); ?>">
            <span class="material-icons">campaign</span>
        </button>
        <button class="fab-item fab-advisory" onclick="openModal('advisoryModal'); toggleFabMenu();" title="<?php echo __('create_advisory'); ?>">
            <span class="material-icons">menu_book</span>
        </button>
        <button class="fab-item fab-visit" onclick="openModal('visitModal'); toggleFabMenu();" title="<?php echo __('schedule_visit'); ?>">
            <span class="material-icons">event</span>
        </button>
    </div>
</div>

<!-- Issue Alert Modal -->
<div id="alertModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">warning</span> <?php echo __('issue_alert'); ?></h3>
            <button class="modal-close" onclick="closeModal('alertModal')">&times;</button>
        </div>
        <form id="alertForm">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="alertType"><?php echo __('alert_type'); ?></label>
                        <select id="alertType" name="alertType" required>
                            <option value="weather"><?php echo __('weather_alert'); ?></option>
                            <option value="disease"><?php echo __('disease_alert'); ?></option>
                            <option value="market"><?php echo __('market_alert'); ?></option>
                            <option value="advisory"><?php echo __('advisory'); ?></option>
                            <option value="system"><?php echo __('system_alert'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alertPriority"><?php echo __('priority'); ?></label>
                        <select id="alertPriority" name="priority" required>
                            <option value="low"><?php echo __('low'); ?></option>
                            <option value="medium" selected><?php echo __('medium'); ?></option>
                            <option value="high"><?php echo __('high'); ?></option>
                            <option value="critical"><?php echo __('critical'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="alertTitle"><?php echo __('title'); ?></label>
                    <input type="text" id="alertTitle" name="title" required placeholder="<?php echo __('enter_alert_title'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="alertMessage"><?php echo __('message'); ?></label>
                    <textarea id="alertMessage" name="message" rows="4" required placeholder="<?php echo __('enter_alert_message'); ?>"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="alertTargetRegion"><?php echo __('target_region'); ?></label>
                        <select id="alertTargetRegion" name="targetRegion">
                            <option value="all"><?php echo __('all_regions'); ?></option>
                            <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region; ?>"><?php echo $region; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alertTargetFarmer"><?php echo __('specific_farmer_optional'); ?></label>
                        <select id="alertTargetFarmer" name="targetFarmer">
                            <option value="all"><?php echo __('all_farmers_in_region'); ?></option>
                            <?php foreach ($allFarmers as $farmer): ?>
                            <option value="<?php echo $farmer['user_id']; ?>">
                                <?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '') . ' - ' . $farmer['phone']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="alertSentVia"><?php echo __('send_via'); ?></label>
                    <select id="alertSentVia" name="sentVia">
                        <option value="app"><?php echo __('app_notification'); ?></option>
                        <option value="email"><?php echo __('email'); ?></option>
                        <option value="sms"><?php echo __('sms'); ?></option>
                        <option value="all"><?php echo __('all_channels'); ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('alertModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-warning">
                    <span class="material-icons">send</span> <?php echo __('issue_alert'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Create Advisory Modal -->
<div id="advisoryModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">campaign</span> <?php echo __('create_advisory'); ?></h3>
            <button class="modal-close" onclick="closeModal('advisoryModal')">&times;</button>
        </div>
        <form id="advisoryForm">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="advisoryType"><?php echo __('alert_type'); ?></label>
                        <select id="advisoryType" name="advisoryType" required>
                            <option value="general"><?php echo __('general'); ?></option>
                            <option value="weather"><?php echo __('weather'); ?></option>
                            <option value="seasonal"><?php echo __('seasonal'); ?></option>
                            <option value="pest_control"><?php echo __('pest_control'); ?></option>
                            <option value="irrigation"><?php echo __('irrigation'); ?></option>
                            <option value="market"><?php echo __('market'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="advisoryPriority"><?php echo __('priority'); ?></label>
                        <select id="advisoryPriority" name="priority" required>
                            <option value="low"><?php echo __('low'); ?></option>
                            <option value="medium" selected><?php echo __('medium'); ?></option>
                            <option value="high"><?php echo __('high'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="advisoryTitle"><?php echo __('title'); ?></label>
                    <input type="text" id="advisoryTitle" name="title" required placeholder="<?php echo __('enter_advisory_title'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="advisoryContent"><?php echo __('content'); ?></label>
                    <textarea id="advisoryContent" name="content" rows="6" required placeholder="<?php echo __('enter_advisory_content'); ?>"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="advisoryTargetCrops"><?php echo __('target_crops_comma'); ?></label>
                        <input type="text" id="advisoryTargetCrops" name="targetCrops" placeholder="<?php echo __('eg_rice_wheat'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="advisoryTargetRegion"><?php echo __('target_region'); ?></label>
                        <select id="advisoryTargetRegion" name="targetRegion">
                            <option value=""><?php echo __('all_regions'); ?></option>
                            <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region; ?>"><?php echo $region; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="advisoryValidFrom"><?php echo __('valid_from'); ?></label>
                        <input type="date" id="advisoryValidFrom" name="validFrom">
                    </div>
                    <div class="form-group">
                        <label for="advisoryValidTo"><?php echo __('valid_to'); ?></label>
                        <input type="date" id="advisoryValidTo" name="validTo">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('advisoryModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-info">
                    <span class="material-icons">publish</span> <?php echo __('publish_advisory'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Visit Modal -->
<div id="visitModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">event</span> <?php echo __('schedule_field_visit'); ?></h3>
            <button class="modal-close" onclick="closeModal('visitModal')">&times;</button>
        </div>
        <form id="visitForm">
            <div class="modal-body">
                <div class="form-group">
                    <label for="visitFarmer"><?php echo __('select_farmer'); ?></label>
                    <select id="visitFarmer" name="farmerId" required>
                        <option value=""><?php echo __('select_farmer_to_visit'); ?></option>
                        <?php foreach ($allFarmers as $farmer): ?>
                        <option value="<?php echo $farmer['user_id']; ?>">
                            <?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?> 
                            (<?php echo htmlspecialchars($farmer['region'] ?? __('no_region')); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="visitDate"><?php echo __('visit_date'); ?></label>
                        <input type="date" id="visitDate" name="visitDate" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="visitTime"><?php echo __('visit_time'); ?> (<?php echo __('optional'); ?>)</label>
                        <input type="time" id="visitTime" name="visitTime">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="visitPurpose"><?php echo __('visit_purpose'); ?></label>
                    <textarea id="visitPurpose" name="purpose" rows="3" placeholder="<?php echo __('enter_visit_purpose'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('visitModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn">
                    <span class="material-icons">event_available</span> <?php echo __('schedule_visit'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Complete Visit Modal -->
<div id="completeVisitModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">check_circle</span> <?php echo __('complete_field_visit'); ?></h3>
            <button class="modal-close" onclick="closeModal('completeVisitModal')">&times;</button>
        </div>
        <form id="completeVisitForm">
            <input type="hidden" id="completeVisitId" name="visitId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="visitObservations"><?php echo __('observations'); ?></label>
                    <textarea id="visitObservations" name="observations" rows="4" placeholder="<?php echo __('observation_placeholder'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="visitRecommendations"><?php echo __('recommendations'); ?></label>
                    <textarea id="visitRecommendations" name="recommendations" rows="4" placeholder="<?php echo __('recommendations_placeholder'); ?>"></textarea>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="visitFollowUp" name="followUpRequired" onchange="toggleFollowUpDate()">
                        <span><?php echo __('followup_required'); ?></span>
                    </label>
                </div>
                
                <div class="form-group" id="followUpDateGroup" style="display: none;">
                    <label for="visitFollowUpDate"><?php echo __('followup_date'); ?></label>
                    <input type="date" id="visitFollowUpDate" name="followUpDate" min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('completeVisitModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-success">
                    <span class="material-icons">check</span> <?php echo __('mark_complete'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Farmer Search Modal -->
<div id="farmerSearchModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">person_search</span> <?php echo __('find_farmer'); ?></h3>
            <button class="modal-close" onclick="closeModal('farmerSearchModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" id="farmerSearch" placeholder="<?php echo __('search_by_name_phone'); ?>" oninput="searchFarmers()">
                </div>
                <div class="form-group">
                    <select id="farmerRegionFilter" onchange="searchFarmers()">
                        <option value=""><?php echo __('all_regions'); ?></option>
                        <?php foreach ($regions as $region): ?>
                        <option value="<?php echo $region; ?>"><?php echo $region; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div id="farmerSearchResults" class="farmer-search-results">
                <p class="text-center text-muted"><?php echo __('enter_search_term'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Detection Detail Modal -->
<div id="detectionModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">coronavirus</span> <?php echo __('disease_detection_details'); ?></h3>
            <button class="modal-close" onclick="closeModal('detectionModal')">&times;</button>
        </div>
        <div class="modal-body" id="detectionModalContent">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                <p><?php echo __('loading'); ?></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('detectionModal')"><?php echo __('close'); ?></button>
            <button type="button" class="btn" id="detectionContactBtn">
                <span class="material-icons">phone</span> <?php echo __('contact_farmer'); ?>
            </button>
        </div>
    </div>
</div>

<script>
// Set base URL for JavaScript (only if not already defined)
if (typeof baseUrl === 'undefined') {
    var baseUrl = '<?php echo $base_url; ?>';
}

// Translations for JavaScript
const translations = {
    alertIssued: '<?php echo __('alert_issued_success'); ?>',
    advisoryCreated: '<?php echo __('advisory_created_success'); ?>',
    visitScheduled: '<?php echo __('visit_scheduled_success'); ?>',
    visitCompleted: '<?php echo __('visit_completed_success'); ?>',
    visitCancelled: '<?php echo __('visit_cancelled_success'); ?>',
    processing: '<?php echo __('processing'); ?>',
    networkError: '<?php echo __('network_error_retry'); ?>',
    errorOccurred: '<?php echo __('error_occurred'); ?>',
    cancelVisitConfirm: '<?php echo __('confirm_cancel_visit'); ?>',
    enterMinChars: '<?php echo __('enter_min_2_chars'); ?>',
    noFarmersFound: '<?php echo __('no_farmers_found'); ?>',
    errorLoadingFarmers: '<?php echo __('error_loading_farmers'); ?>',
    loading: '<?php echo __('loading'); ?>',
    cropInfo: '<?php echo __('crop_information'); ?>',
    diseaseInfo: '<?php echo __('disease_information'); ?>',
    farmerInfo: '<?php echo __('farmer_information'); ?>',
    detectionInfo: '<?php echo __('detection_info'); ?>',
    cropLabel: '<?php echo __('crop'); ?>',
    varietyLabel: '<?php echo __('variety'); ?>',
    diseaseLabel: '<?php echo __('disease'); ?>',
    severityLabel: '<?php echo __('severity'); ?>',
    confidenceLabel: '<?php echo __('confidence'); ?>',
    nameLabel: '<?php echo __('name'); ?>',
    phoneLabel: '<?php echo __('phone'); ?>',
    regionLabel: '<?php echo __('region'); ?>',
    detectedLabel: '<?php echo __('detected'); ?>',
    treatmentSuggestions: '<?php echo __('treatment_suggestions'); ?>',
    unknownCrop: '<?php echo __('unknown'); ?>',
    unknown: '<?php echo __('unknown'); ?>',
    na: '<?php echo __('na'); ?>',
    failedLoadDetails: '<?php echo __('failed_load_details'); ?>',
    noRegion: '<?php echo __('no_region'); ?>',
    cropsLabel: '<?php echo __('crops'); ?>'
};

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// Toggle follow-up date field
function toggleFollowUpDate() {
    const followUp = document.getElementById('visitFollowUp').checked;
    document.getElementById('followUpDateGroup').style.display = followUp ? 'block' : 'none';
}

// Issue Alert Form
document.getElementById('alertForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'issue_alert');
    
    submitForm('alertForm', formData, 'alertModal', translations.alertIssued);
});

// Create Advisory Form
document.getElementById('advisoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'create_advisory');
    
    submitForm('advisoryForm', formData, 'advisoryModal', translations.advisoryCreated);
});

// Schedule Visit Form
document.getElementById('visitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'schedule_visit');
    
    submitForm('visitForm', formData, 'visitModal', translations.visitScheduled);
});

// Complete Visit Form
document.getElementById('completeVisitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'complete_visit');
    
    submitForm('completeVisitForm', formData, 'completeVisitModal', translations.visitCompleted);
});

// Generic form submission
function submitForm(formId, formData, modalId, successMessage) {
    const form = document.getElementById(formId);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> ' + translations.processing;
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || successMessage, 'success');
            closeModal(modalId);
            form.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || translations.errorOccurred, 'error');
        }
    })
    .catch(error => {
        showNotification(translations.networkError, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Complete Visit Modal
function completeVisitModal(visitId) {
    document.getElementById('completeVisitId').value = visitId;
    openModal('completeVisitModal');
}

// Cancel Visit
function cancelVisit(visitId) {
    if (!confirm(translations.cancelVisitConfirm)) return;
    
    const formData = new FormData();
    formData.append('action', 'cancel_visit');
    formData.append('visitId', visitId);
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(translations.visitCancelled, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || translations.errorOccurred, 'error');
        }
    })
    .catch(error => {
        showNotification(translations.networkError, 'error');
    });
}

// Schedule visit for specific farmer
function scheduleVisitFor(farmerId, farmerName) {
    document.getElementById('visitFarmer').value = farmerId;
    openModal('visitModal');
}

// Search farmers
let searchTimeout;
function searchFarmers() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const search = document.getElementById('farmerSearch').value;
        const region = document.getElementById('farmerRegionFilter').value;
        
        if (search.length < 2 && !region) {
            document.getElementById('farmerSearchResults').innerHTML = '<p class="text-center text-muted">' + translations.enterMinChars + '</p>';
            return;
        }
        
        document.getElementById('farmerSearchResults').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span></div>';
        
        fetch(baseUrl + 'ajax/officer.php?action=get_farmers&search=' + encodeURIComponent(search) + '&region=' + encodeURIComponent(region))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.farmers.length > 0) {
                let html = '<div class="farmer-list">';
                data.farmers.forEach(farmer => {
                    html += `
                        <div class="farmer-item">
                            <div class="farmer-info">
                                <strong>${farmer.first_name} ${farmer.last_name || ''}</strong>
                                <span class="badge badge-info">${farmer.region || translations.noRegion}</span>
                                <br><small>${farmer.phone}</small>
                                ${farmer.primary_crops ? '<br><small>' + translations.cropsLabel + ': ' + farmer.primary_crops + '</small>' : ''}
                            </div>
                            <div class="farmer-actions">
                                <a href="${baseUrl}?page=farmer-profile-view&id=${farmer.user_id}" class="btn btn-small btn-info">
                                    <span class="material-icons">visibility</span>
                                </a>
                                <a href="tel:${farmer.phone}" class="btn btn-small">
                                    <span class="material-icons">phone</span>
                                </a>
                                <button class="btn btn-small btn-secondary" onclick="scheduleVisitFor(${farmer.user_id}, '${farmer.first_name}'); closeModal('farmerSearchModal');">
                                    <span class="material-icons">event</span>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('farmerSearchResults').innerHTML = html;
            } else {
                document.getElementById('farmerSearchResults').innerHTML = '<p class="text-center text-muted">' + translations.noFarmersFound + '</p>';
            }
        })
        .catch(error => {
            document.getElementById('farmerSearchResults').innerHTML = '<p class="text-center text-danger">' + translations.errorLoadingFarmers + '</p>';
        });
    }, 300);
}

// View detection details
function viewDetection(detectionId) {
    openModal('detectionModal');
    document.getElementById('detectionModalContent').innerHTML = '<div class="loading-spinner"><span class="material-icons spinning">sync</span><p>' + translations.loading + '</p></div>';
    
    fetch(baseUrl + 'ajax/officer.php?action=get_detection_details&detectionId=' + detectionId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const d = data.detection;
            document.getElementById('detectionModalContent').innerHTML = `
                <div class="detection-detail-grid">
                    <div class="detection-info-section">
                        <h4><span class="material-icons">eco</span> ${translations.cropInfo}</h4>
                        <p><strong>${translations.cropLabel}:</strong> ${d.crop_name || translations.unknown}</p>
                        <p><strong>${translations.varietyLabel}:</strong> ${d.variety || translations.na}</p>
                    </div>
                    <div class="detection-info-section">
                        <h4><span class="material-icons">coronavirus</span> ${translations.diseaseInfo}</h4>
                        <p><strong>${translations.diseaseLabel}:</strong> ${d.disease_name || translations.unknown}</p>
                        <p><strong>${translations.severityLabel}:</strong> <span class="badge badge-${d.severity === 'high' ? 'danger' : (d.severity === 'medium' ? 'warning' : 'success')}">${d.severity || translations.na}</span></p>
                        <p><strong>${translations.confidenceLabel}:</strong> ${d.confidence_score ? (d.confidence_score * 100).toFixed(1) + '%' : translations.na}</p>
                    </div>
                    <div class="detection-info-section">
                        <h4><span class="material-icons">person</span> ${translations.farmerInfo}</h4>
                        <p><strong>${translations.nameLabel}:</strong> ${d.first_name} ${d.last_name || ''}</p>
                        <p><strong>${translations.phoneLabel}:</strong> <a href="tel:${d.phone}">${d.phone}</a></p>
                        <p><strong>${translations.regionLabel}:</strong> ${d.region || translations.na}</p>
                    </div>
                    <div class="detection-info-section">
                        <h4><span class="material-icons">schedule</span> ${translations.detectionInfo}</h4>
                        <p><strong>${translations.detectedLabel}:</strong> ${new Date(d.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                </div>
                ${d.treatment_suggestions ? `
                <div class="detection-treatment">
                    <h4><span class="material-icons">medical_services</span> ${translations.treatmentSuggestions}</h4>
                    <p>${d.treatment_suggestions}</p>
                </div>
                ` : ''}
            `;
            document.getElementById('detectionContactBtn').onclick = function() {
                window.location.href = 'tel:' + d.phone;
            };
        } else {
            document.getElementById('detectionModalContent').innerHTML = '<p class="text-center text-danger">' + translations.failedLoadDetails + '</p>';
        }
    })
    .catch(error => {
        document.getElementById('detectionModalContent').innerHTML = '<p class="text-center text-danger">' + translations.networkError + '</p>';
    });
}

// showNotification is now provided globally via footer.php
</script>

<style>
/* ============================================
   OFFICER DASHBOARD - MODERN DESIGN SYSTEM
   ============================================ */

:root {
    --gradient-green: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --gradient-blue: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    --gradient-orange: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
    --gradient-red: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    --gradient-purple: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    --shadow-soft: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-strong: 0 15px 40px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --border-radius-sm: 12px;
    --transition-fast: 0.2s ease;
    --transition-medium: 0.3s ease;
}

/* Hero Section - Modern */
.officer-hero {
    position: relative;
    background: linear-gradient(135deg, var(--primary) 0%, #2d5a27 100%);
    border-radius: var(--border-radius);
    padding: 2.5rem;
    margin-bottom: 2rem;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 200px;
}

.hero-particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    pointer-events: none;
}

.hero-particles::before,
.hero-particles::after {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    animation: float 6s ease-in-out infinite;
}

.hero-particles::before {
    top: -100px;
    right: -50px;
}

.hero-particles::after {
    bottom: -150px;
    left: 10%;
    animation-delay: -3s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(5deg); }
}

.hero-content {
    position: relative;
    z-index: 2;
    color: white;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.hero-badge .material-icons {
    font-size: 1rem;
}

.officer-hero h1 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color:white;
}

.wave-emoji {
    display: inline-block;
    animation: wave 2s ease-in-out infinite;
    transform-origin: 70% 70%;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(20deg); }
    75% { transform: rotate(-10deg); }
}

.hero-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0 0 1.5rem;
}

.hero-quick-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.quick-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    opacity: 0.9;
}

.quick-stat .material-icons {
    font-size: 1.1rem;
}

.hero-illustration {
    position: relative;
    width: 200px;
    height: 150px;
}

.floating-card {
    position: absolute;
    background: white;
    border-radius: var(--border-radius-sm);
    padding: 1rem;
    box-shadow: var(--shadow-medium);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: floatCard 3s ease-in-out infinite;
}

.floating-card .material-icons {
    color: var(--primary);
    font-size: 1.5rem;
}

.floating-card span:last-child {
    font-weight: 700;
    font-size: 1.2rem;
    color: #333;
}

.fc-1 { top: 0; right: 0; animation-delay: 0s; }
.fc-2 { top: 50%; left: 0; animation-delay: -1s; }
.fc-3 { bottom: 0; right: 20%; animation-delay: -2s; }

@keyframes floatCard {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Live Status Bar */
.live-status-bar {
    display: flex;
    align-items: center;
    gap: 2rem;
    background: white;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius-sm);
    box-shadow: var(--shadow-soft);
    margin-bottom: 2rem;
    overflow-x: auto;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #666;
    white-space: nowrap;
}

.status-item .material-icons {
    font-size: 1rem;
    color: var(--primary);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
}

.status-dot.pulse {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
}

.status-online {
    color: #28a745;
    font-weight: 500;
}

.btn-icon-small {
    background: #f5f5f5;
    border: none;
    border-radius: 8px;
    padding: 0.5rem;
    cursor: pointer;
    transition: var(--transition-fast);
    margin-left: auto;
}

.btn-icon-small:hover {
    background: var(--primary);
    color: white;
}

.btn-icon-small .material-icons {
    font-size: 1.2rem;
}

/* Section Title Modern */
.section-title-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.title-icon {
    width: 48px;
    height: 48px;
    background: var(--gradient-green);
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.title-icon .material-icons {
    font-size: 1.5rem;
}

.section-alert .title-icon {
    background: var(--gradient-red);
}

.pulse-alert {
    animation: pulseAlert 2s ease-in-out infinite;
}

@keyframes pulseAlert {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.title-text {
    flex: 1;
}

.title-text span {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: #333;
}

.title-text small {
    color: #888;
    font-size: 0.85rem;
    font-weight: 400;
}

.btn-add-new {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.6rem 1.2rem;
    border-radius: 50px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition-fast);
}

.btn-add-new:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.btn-view-all {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    font-size: 0.9rem;
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition-fast);
}

.btn-view-all:hover {
    gap: 0.5rem;
}

.attention-count {
    background: #dc3545;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
}

/* Stats Cards Modern */
.stats-section {
    margin-bottom: 2.5rem;
}

.officer-stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}

.stat-card-modern {
    position: relative;
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    transition: var(--transition-medium);
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-strong);
}

.stat-card-bg {
    position: absolute;
    top: -50%;
    right: -30%;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    opacity: 0.1;
}

.stat-gradient-green .stat-card-bg { background: var(--gradient-green); }
.stat-gradient-blue .stat-card-bg { background: var(--gradient-blue); }
.stat-gradient-orange .stat-card-bg { background: var(--gradient-orange); }
.stat-gradient-red .stat-card-bg { background: var(--gradient-red); }

.stat-card-content {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.stat-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-gradient-green .stat-icon-wrap { background: var(--gradient-green); }
.stat-gradient-blue .stat-icon-wrap { background: var(--gradient-blue); }
.stat-gradient-orange .stat-icon-wrap { background: var(--gradient-orange); }
.stat-gradient-red .stat-icon-wrap { background: var(--gradient-red); }

.stat-icon-wrap .material-icons {
    font-size: 1.75rem;
}

.stat-info {
    flex: 1;
}

.stat-number-modern {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    line-height: 1.2;
}

.stat-label-modern {
    font-size: 0.9rem;
    color: #888;
    margin-top: 0.25rem;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 50px;
}

.trend-up {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.trend-down {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.trend-neutral {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.stat-trend .material-icons {
    font-size: 1rem;
}

.stat-chart-mini {
    height: 40px;
    margin-top: 1rem;
    opacity: 0.5;
}

/* Action Cards Modern */
.actions-section {
    margin-bottom: 2.5rem;
}

.officer-actions-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.action-card-modern {
    position: relative;
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    transition: var(--transition-medium);
    border-left: 4px solid transparent;
}

.action-warning { border-left-color: #fd7e14; }
.action-info { border-left-color: #17a2b8; }
.action-primary { border-left-color: var(--primary); }
.action-success { border-left-color: #28a745; }

.action-card-modern:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-medium);
}

.action-glow {
    position: absolute;
    top: 50%;
    left: -50px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0;
    transition: var(--transition-medium);
}

.action-warning .action-glow { background: radial-gradient(circle, rgba(253, 126, 20, 0.3) 0%, transparent 70%); }
.action-info .action-glow { background: radial-gradient(circle, rgba(23, 162, 184, 0.3) 0%, transparent 70%); }
.action-primary .action-glow { background: radial-gradient(circle, rgba(85, 122, 70, 0.3) 0%, transparent 70%); }
.action-success .action-glow { background: radial-gradient(circle, rgba(40, 167, 69, 0.3) 0%, transparent 70%); }

.action-card-modern:hover .action-glow {
    opacity: 1;
    transform: scale(2);
}

.action-icon-container {
    position: relative;
    width: 56px;
    height: 56px;
    flex-shrink: 0;
}

.action-icon-bg {
    position: absolute;
    inset: 0;
    border-radius: var(--border-radius-sm);
    opacity: 0.1;
}

.action-warning .action-icon-bg { background: #fd7e14; }
.action-info .action-icon-bg { background: #17a2b8; }
.action-primary .action-icon-bg { background: var(--primary); }
.action-success .action-icon-bg { background: #28a745; }

.action-icon-container .material-icons {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.75rem;
}

.action-warning .action-icon-container .material-icons { color: #fd7e14; }
.action-info .action-icon-container .material-icons { color: #17a2b8; }
.action-primary .action-icon-container .material-icons { color: var(--primary); }
.action-success .action-icon-container .material-icons { color: #28a745; }

.action-content {
    flex: 1;
}

.action-content h3 {
    margin: 0 0 0.25rem;
    font-size: 1.05rem;
    font-weight: 600;
    color: #333;
}

.action-content p {
    margin: 0;
    font-size: 0.85rem;
    color: #888;
}

.action-arrow {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}

.action-card-modern:hover .action-arrow {
    background: var(--primary);
    color: white;
}

.action-arrow .material-icons {
    font-size: 1.2rem;
}

/* Visits Timeline */
.visits-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    margin-bottom: 2.5rem;
}

.visits-timeline {
    position: relative;
    padding-left: 2rem;
    margin-top: 1rem;
}

.visits-timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary), transparent);
}

.visits-section .timeline-item {
    position: relative;
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-left: 0;
}

.visits-section .timeline-marker {
    position: relative;
    left: 0;
    top: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: 3px solid var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    z-index: 1;
}

.visits-section .timeline-marker .material-icons {
    font-size: 1rem;
    color: var(--primary);
}

.visits-section .timeline-item.timeline-next .timeline-marker {
    background: var(--primary);
    border-color: var(--primary);
}

.visits-section .timeline-item.timeline-next .timeline-marker .material-icons {
    color: white;
}

.visits-section .timeline-content {
    flex: 1;
    background: #f8f9fa;
    border-radius: var(--border-radius-sm);
    padding: 1.25rem;
    border: 1px solid #eee;
    transition: var(--transition-fast);
}

.visits-section .timeline-content:hover {
    background: white;
    border-color: var(--primary);
    box-shadow: var(--shadow-soft);
}

.visits-section .timeline-item.timeline-next .timeline-content {
    background: white;
    border-color: var(--primary);
    box-shadow: var(--shadow-soft);
}

.visits-section .timeline-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0;
}

.visits-section .timeline-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--primary);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    min-width: 60px;
    text-align: center;
}

.visits-section .timeline-date .day {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.visits-section .timeline-date .month {
    font-size: 0.75rem;
    text-transform: uppercase;
    opacity: 0.9;
}

.visits-section .timeline-info {
    flex: 1;
}

.visits-section .timeline-info h4 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary);
}

.visits-section .timeline-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.visits-section .timeline-meta > span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: #666;
}

.visits-section .timeline-meta .material-icons {
    font-size: 0.95rem;
    color: var(--primary);
}

.visits-section .timeline-purpose {
    margin: 0;
    font-size: 0.9rem;
    color: #555;
    background: rgba(85, 122, 70, 0.05);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    border-left: 3px solid var(--primary);
}

.visits-section .timeline-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

/* Old timeline styles for other sections */
.timeline-item-old {
    position: relative;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1rem;
    transition: var(--transition-fast);
    border: 1px solid transparent;
}

.timeline-item-old:hover {
    background: white;
    border-color: var(--primary);
    box-shadow: var(--shadow-soft);
}

.timeline-marker-old {
    position: absolute;
    left: -2.25rem;
    top: 1.25rem;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 3px solid var(--primary);
}

.timeline-marker.scheduled {
    background: #fff3cd;
    border-color: #ffc107;
}

.timeline-marker.completed {
    background: #d4edda;
    border-color: #28a745;
}

.farmer-avatar-small {
    width: 36px;
    height: 36px;
    background: var(--gradient-green);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.timeline-farmer-name {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}

.timeline-date-old {
    font-size: 0.8rem;
    color: #888;
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.timeline-date-old .material-icons {
    font-size: 0.9rem;
}

.timeline-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: #666;
}

.timeline-detail-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.timeline-detail-item .material-icons {
    font-size: 1rem;
    color: var(--primary);
}
}

.farmer-avatar-small {
    width: 36px;
    height: 36px;
    background: var(--gradient-green);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.timeline-farmer-name {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}

.timeline-date {
    font-size: 0.8rem;
    color: #888;
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.timeline-date .material-icons {
    font-size: 0.9rem;
}

.timeline-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: #666;
}

.timeline-detail-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.timeline-detail-item .material-icons {
    font-size: 1rem;
    color: var(--primary);
}

.no-visits-message {
    text-align: center;
    padding: 2rem;
    color: #888;
}

.no-visits-message .material-icons {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    color: #ddd;
}

.no-visits-message p {
    margin: 0;
}

/* Attention Cards */
.attention-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    margin-bottom: 2.5rem;
    border-left: 4px solid #dc3545;
}

.attention-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.attention-card {
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border-radius: var(--border-radius-sm);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid #ffe6e6;
    transition: var(--transition-fast);
}

.attention-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-soft);
}

.attention-card.severity-high {
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border-color: #ffcccc;
}

.attention-card.severity-medium {
    background: linear-gradient(135deg, #fff8e1 0%, #fff 100%);
    border-color: #ffecb3;
}

.attention-card.severity-low {
    background: linear-gradient(135deg, #e8f5e9 0%, #fff 100%);
    border-color: #c8e6c9;
}

.farmer-avatar {
    width: 50px;
    height: 50px;
    background: var(--gradient-red);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.severity-medium .farmer-avatar {
    background: var(--gradient-orange);
}

.severity-low .farmer-avatar {
    background: var(--gradient-green);
}

.attention-info {
    flex: 1;
}

.attention-info h4 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    color: #333;
}

.attention-reason {
    font-size: 0.8rem;
    color: #666;
    margin: 0 0 0.5rem;
}

.attention-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.attention-tags .tag {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 50px;
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.severity-medium .attention-tags .tag {
    background: rgba(253, 126, 20, 0.1);
    color: #fd7e14;
}

.severity-low .attention-tags .tag {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.priority-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #dc3545;
    color: white;
}

.severity-medium .priority-badge {
    background: #fd7e14;
}

.severity-low .priority-badge {
    background: #28a745;
}

.priority-badge .material-icons {
    font-size: 1.1rem;
}

.all-clear-message {
    text-align: center;
    padding: 2rem;
    color: #28a745;
}

.all-clear-message .material-icons {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.all-clear-message p {
    margin: 0;
    font-weight: 500;
}

/* Detection Cards Modern */
.detections-section {
    margin-bottom: 2.5rem;
}

.detections-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.detection-card-modern {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    transition: var(--transition-medium);
    cursor: pointer;
}

.detection-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-strong);
}

.detection-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f0f0f0;
}

.crop-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
}

.crop-badge .material-icons {
    color: var(--primary);
}

.confidence-badge {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 50px;
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.confidence-badge .material-icons {
    font-size: 0.9rem;
}

.detection-body {
    padding: 1.25rem;
}

.disease-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 0.75rem;
}

.detection-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}

.meta-item .material-icons {
    font-size: 1rem;
    color: var(--primary);
}

.detection-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: #f8f9fa;
}

.severity-bar {
    flex: 1;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-right: 1rem;
}

.severity-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.severity-fill.high { background: var(--gradient-red); }
.severity-fill.medium { background: var(--gradient-orange); }
.severity-fill.low { background: var(--gradient-green); }

.view-btn {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    background: none;
    border: none;
    color: var(--primary);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition-fast);
}

.view-btn:hover {
    gap: 0.5rem;
}

.view-btn .material-icons {
    font-size: 1rem;
}

.no-detections-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
}

.no-detections-message .material-icons {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.no-detections-message h3 {
    margin: 0 0 0.5rem;
    color: #333;
}

.no-detections-message p {
    color: #888;
    margin: 0;
}

/* Dashboard Grid Modern */
.dashboard-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.grid-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
}

.grid-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.grid-card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.grid-card-header h3 .material-icons {
    color: var(--primary);
}

.chart-container {
    height: 250px;
    position: relative;
}

.chart-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #888;
}

.chart-placeholder .material-icons {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    color: #ddd;
}

/* Insights Grid */
.insights-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.insight-item {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: var(--border-radius-sm);
    text-align: center;
    transition: var(--transition-fast);
}

.insight-item:hover {
    background: var(--primary);
    color: white;
}

.insight-item .material-icons {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--primary);
}

.insight-item:hover .material-icons {
    color: white;
}

.insight-value {
    font-size: 1.5rem;
    font-weight: 700;
    display: block;
}

.insight-label {
    font-size: 0.8rem;
    opacity: 0.8;
}

/* Dashboard Card Modern */
.dashboard-card-modern {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    transition: var(--transition-medium);
}

.dashboard-card-modern:hover {
    box-shadow: var(--shadow-medium);
}

.card-header-modern {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.card-title .material-icons {
    color: var(--primary);
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon-mini {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: #f5f5f5;
    color: #666;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}

.btn-icon-mini:hover {
    background: var(--primary);
    color: white;
}

.btn-add-mini {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: var(--primary);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}

.btn-add-mini:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-soft);
}

.card-body-modern {
    padding: 1.5rem;
}

/* Region Chart Container */
.region-chart-container {
    height: 200px;
    margin-bottom: 1rem;
}

.region-legend {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border-radius: 8px;
    background: #f8f9fa;
    font-size: 0.9rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex-shrink: 0;
}

.legend-label {
    flex: 1;
    color: #333;
}

.legend-value {
    font-weight: 600;
    color: var(--primary);
}

/* Advisory Timeline */
.advisory-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.advisory-timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    background: #f8f9fa;
    transition: var(--transition-fast);
}

.advisory-timeline-item:hover {
    background: #f0f0f0;
}

.advisory-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: var(--gradient-blue);
    color: white;
}

.advisory-icon.priority-high {
    background: var(--gradient-red);
}

.advisory-icon.priority-medium {
    background: var(--gradient-orange);
}

.advisory-icon.priority-low {
    background: var(--gradient-green);
}

.advisory-content {
    flex: 1;
    min-width: 0;
}

.advisory-content h5 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.advisory-content p {
    margin: 0 0 0.5rem;
    font-size: 0.85rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.advisory-time {
    font-size: 0.75rem;
    color: #999;
}

/* Empty State Mini */
.empty-state-mini {
    text-align: center;
    padding: 2rem 1rem;
    color: #888;
}

.empty-state-mini .material-icons {
    font-size: 3rem;
    color: #ddd;
    margin-bottom: 0.75rem;
}

.empty-state-mini p {
    margin: 0 0 1rem;
    font-size: 0.9rem;
}

.btn-mini {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    border: none;
    background: var(--primary);
    color: white;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition-fast);
}

.btn-mini:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-soft);
}

/* Stats Mini Card */
.stats-mini-card .insights-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stats-mini-card .insight-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: var(--border-radius-sm);
    text-align: left;
    transition: var(--transition-fast);
}

.stats-mini-card .insight-item:hover {
    background: #e8f5e9;
    transform: translateY(-2px);
}

.insight-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.insight-icon.success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.insight-icon.warning {
    background: rgba(253, 126, 20, 0.1);
    color: #fd7e14;
}

.insight-icon.info {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.insight-icon.primary {
    background: rgba(85, 122, 70, 0.1);
    color: var(--primary);
}

.insight-icon .material-icons {
    font-size: 1.5rem;
}

.insight-info {
    display: flex;
    flex-direction: column;
}

.stats-mini-card .insight-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    line-height: 1.2;
}

.stats-mini-card .insight-label {
    font-size: 0.8rem;
    color: #888;
}

/* Timeline Modern - Visits */
.visits-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    margin-bottom: 2.5rem;
}

.timeline-card {
    background: #f8f9fa;
    border-radius: var(--border-radius-sm);
    padding: 1.25rem;
    border: 1px solid #eee;
    transition: var(--transition-fast);
}

.timeline-card:hover {
    background: white;
    border-color: var(--primary);
    box-shadow: var(--shadow-soft);
}

.timeline-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.farmer-info-timeline h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary);
}

.farmer-info-timeline p {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

.farmer-info-timeline .material-icons {
    font-size: 0.9rem;
}

.visit-datetime {
    text-align: right;
    font-size: 0.85rem;
    color: #888;
}

.visit-date {
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.visit-time {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.visit-time .material-icons,
.visit-date .material-icons {
    font-size: 0.9rem;
    color: var(--primary);
}

.timeline-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon-modern {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: #f5f5f5;
    color: var(--primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}

.btn-icon-modern:hover {
    transform: scale(1.1);
}

.btn-icon-modern.btn-success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.btn-icon-modern.btn-success:hover {
    background: #28a745;
    color: white;
}

.btn-icon-modern.btn-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.btn-icon-modern.btn-danger:hover {
    background: #dc3545;
    color: white;
}

/* Empty State Modern */
.empty-state-modern {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-illustration {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: rgba(108, 117, 125, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-illustration .material-icons {
    font-size: 2.5rem;
    color: #6c757d;
}

.empty-state-modern h3 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    color: #333;
}

.empty-state-modern p {
    margin: 0 0 1.5rem;
    color: #888;
    font-size: 0.9rem;
}

.btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    border: none;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition-fast);
}

.btn-modern.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-modern.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

/* Attention Section */
.attention-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    margin-bottom: 2.5rem;
    border-left: 4px solid #dc3545;
}

.attention-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.attention-card {
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border-radius: var(--border-radius-sm);
    padding: 1.25rem;
    border: 1px solid #ffe6e6;
    transition: var(--transition-fast);
}

.attention-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-soft);
}

.attention-severity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.5rem 0.75rem;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 50px;
    width: fit-content;
    font-size: 0.85rem;
    font-weight: 500;
    color: #dc3545;
}

.attention-severity .material-icons {
    font-size: 1rem;
}

.attention-farmer {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.farmer-avatar {
    width: 50px;
    height: 50px;
    background: var(--gradient-red);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.farmer-details h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    color: #333;
}

.farmer-details p {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0.25rem 0;
    font-size: 0.85rem;
    color: #666;
}

.farmer-details .material-icons {
    font-size: 0.9rem;
    color: var(--primary);
}

.attention-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    border-radius: 50px;
    border: none;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition-fast);
    text-decoration: none;
}

.btn-action-pill .material-icons {
    font-size: 1rem;
}

.btn-call {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.btn-call:hover {
    background: #28a745;
    color: white;
}

.btn-view {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.btn-view:hover {
    background: #17a2b8;
    color: white;
}

.btn-schedule {
    background: rgba(85, 122, 70, 0.1);
    color: var(--primary);
}

.btn-schedule:hover {
    background: var(--primary);
    color: white;
}

/* FAB Container */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 24px;
    z-index: 1000;
}



.fab-main {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--gradient-green);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: var(--shadow-medium);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}



.fab-main:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-strong);
}

.fab-main .material-icons {
    transition: transform 0.3s ease;
}

.fab-container.open .fab-main .material-icons {
    transform: rotate(45deg);
}

.fab-menu {
    position: absolute;
    bottom: 70px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: var(--transition-medium);
}

.fab-container.open .fab-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.fab-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 50px;
    box-shadow: var(--shadow-soft);
    cursor: pointer;
    transition: var(--transition-fast);
    text-decoration: none;
    color: #333;
    white-space: nowrap;
}

.fab-item:hover {
    transform: translateX(-5px);
    box-shadow: var(--shadow-medium);
}

.fab-item .material-icons {
    color: var(--primary);
}
@media (max-width: 900px) {
    .fab-container {
        bottom: 72px;
        right: 16px;
    }
    .fab-main{
        width: 44px;
        height: 44px;
    }

}
/* Responsive Design */
@media (max-width: 992px) {
    .officer-hero {
        flex-direction: column;
        text-align: center;
        padding: 2rem 1.5rem;
    }

    .hero-quick-stats {
        justify-content: center;
    }

    .hero-illustration {
        display: none;
    }

    .live-status-bar {
        flex-wrap: wrap;
        gap: 1rem;
    }

    .dashboard-grid-modern {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .officer-hero h1 {
        font-size: 1.5rem;
    }

    .officer-stats-grid-modern {
        grid-template-columns: repeat(2, 1fr);
    }

    .stat-number-modern {
        font-size: 1.5rem;
    }

    .officer-actions-grid-modern {
        grid-template-columns: 1fr;
    }

    .detections-grid-modern {
        grid-template-columns: 1fr;
    }

    .attention-cards-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .officer-stats-grid-modern {
        grid-template-columns: 1fr;
    }

    .insights-grid {
        grid-template-columns: 1fr;
    }
}

/* Region stats */
.region-stats-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.region-stat-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
}

.region-icon {
    color: var(--primary);
}

.region-name {
    flex: 1;
}

.region-badges {
    display: flex;
    gap: 0.5rem;
}

/* Advisory list */
.advisory-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.advisory-item {
    padding: 0.75rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.advisory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.advisory-item h4 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
}

.advisory-item p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem;
}

.empty-state-icon {
    font-size: 3rem;
    color: #ccc;
    margin-bottom: 0.5rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000 !important;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-lg {
    max-width: 700px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    background: #f9f9f9;
    border-radius: 0 0 12px 12px;
}

/* Form styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.1);
}

/* Farmer search */
.farmer-search-results {
    max-height: 400px;
    overflow-y: auto;
}

.farmer-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.farmer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.farmer-info {
    flex: 1;
}

.farmer-actions {
    display: flex;
    gap: 0.5rem;
}

/* Detection detail */
.detection-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.detection-info-section h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem;
    color: var(--primary);
    font-size: 0.95rem;
}

.detection-info-section p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.detection-treatment {
    grid-column: 1 / -1;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

/* Loading spinner */
.loading-spinner {
    text-align: center;
    padding: 2rem;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 950 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}

.notification-success {
    background: var(--primary);
}

.notification-error {
    background: #dc3545;
}

.notification.fade-out {
    opacity: 0;
    transition: opacity 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

/* Table */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background: #f9f9f9;
    font-weight: 600;
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

/* Button styles */
.btn-outline {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.mt-2 {
    margin-top: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .detection-detail-grid {
        grid-template-columns: 1fr;
    }
    
    .officer-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .officer-two-col-grid {
        grid-template-columns: 1fr;
    }
    
    .farmer-item {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}
</style>

<script>
// Officer Dashboard - Advanced Interactive Features
document.addEventListener('DOMContentLoaded', function() {
    // Animated Counter for Stats
    function animateCounter(element, target, duration = 1500) {
        let start = 0;
        const increment = target / (duration / 16);
        
        function updateCounter() {
            start += increment;
            if (start < target) {
                element.textContent = Math.floor(start);
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = target;
            }
        }
        
        updateCounter();
    }
    
    // Trigger counter animation when stats are visible
    const statNumbers = document.querySelectorAll('.stat-number-modern');
    const observerOptions = { threshold: 0.5 };
    
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.getAttribute('data-value') || entry.target.textContent);
                entry.target.textContent = '0';
                animateCounter(entry.target, target);
                statsObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    statNumbers.forEach(stat => {
        stat.setAttribute('data-value', stat.textContent);
        statsObserver.observe(stat);
    });
    
    // FAB Menu Toggle
    const fabContainer = document.querySelector('.fab-container');
    const fabMain = document.querySelector('.fab-main');
    
    if (fabMain && fabContainer) {
        fabMain.addEventListener('click', function() {
            fabContainer.classList.toggle('open');
        });
        
        // Close FAB when clicking outside
        document.addEventListener('click', function(e) {
            if (!fabContainer.contains(e.target)) {
                fabContainer.classList.remove('open');
            }
        });
    }
    
    // Refresh Dashboard Function
    window.refreshDashboard = function() {
        const refreshBtn = document.querySelector('.btn-icon-small');
        if (refreshBtn) {
            refreshBtn.classList.add('spinning');
            
            // Simulate refresh
            setTimeout(() => {
                refreshBtn.classList.remove('spinning');
                showNotification(translations.data_synced || 'Dashboard refreshed!', 'success');
            }, 1500);
        }
    };
    
    // Add sparkle effect to stat cards
    document.querySelectorAll('.stat-card-modern').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Smooth scroll for timeline items
    document.querySelectorAll('.timeline-item').forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in-up');
    });
    
    // Initialize Charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
    
    // Particle animation for hero
    createParticles();
});

// Create floating particles in hero section
function createParticles() {
    const heroParticles = document.querySelector('.hero-particles');
    if (!heroParticles) return;
    
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.cssText = `
            position: absolute;
            width: ${Math.random() * 10 + 5}px;
            height: ${Math.random() * 10 + 5}px;
            background: rgba(255, 255, 255, ${Math.random() * 0.3 + 0.1});
            border-radius: 50%;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: particleFloat ${Math.random() * 4 + 4}s ease-in-out infinite;
            animation-delay: ${Math.random() * 2}s;
        `;
        heroParticles.appendChild(particle);
    }
}

// Initialize Charts
function initializeCharts() {
    const regionChartCanvas = document.getElementById('regionChart');
    if (regionChartCanvas) {
        new Chart(regionChartCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['<?php echo __('healthy'); ?>', '<?php echo __('diseased'); ?>', '<?php echo __('pending_review'); ?>'],
                datasets: [{
                    data: [65, 25, 10],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Add particle float animation to head
const particleStyle = document.createElement('style');
particleStyle.textContent = `
    @keyframes particleFloat {
        0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0.5; }
        25% { transform: translateY(-20px) translateX(10px) rotate(90deg); opacity: 0.8; }
        50% { transform: translateY(-40px) translateX(-10px) rotate(180deg); opacity: 0.5; }
        75% { transform: translateY(-20px) translateX(20px) rotate(270deg); opacity: 0.8; }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(particleStyle);
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
