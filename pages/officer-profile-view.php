<?php
/**
 * Officer Profile View Page
 * Fully dynamic with Bengali/English support
 */

// Authentication check
include __DIR__ . '/../layouts/header.php';

if (!isLoggedIn()) {
    redirect('login');
}

$db = new Database();
$currentUser = getCurrentUser();
$officerId = $_GET['id'] ?? null;

if (!$officerId) {
    redirect('dashboard');
}

// Get officer information
$officer = $db->single("SELECT * FROM users WHERE user_id = ? AND role = 'officer'", [$officerId]);

if (!$officer) {
    redirect('dashboard');
}

// Get officer's statistics
$farmersInRegion = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;

$alertsIssued = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_by = ?", [$officerId])['count'] ?? 0;
$alertsThisMonth = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$officerId])['count'] ?? 0;

// Field visits stats
$totalVisits = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ?", [$officerId])['count'] ?? 0;
$completedVisits = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND status = 'completed'", [$officerId])['count'] ?? 0;
$scheduledVisits = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND status = 'scheduled'", [$officerId])['count'] ?? 0;

// Advisories stats
$advisoriesCreated = $db->single("SELECT COUNT(*) as count FROM advisories WHERE created_by = ?", [$officerId])['count'] ?? 0;

// Recent field visits
$recentVisits = $db->resultSet("SELECT fv.*, u.first_name as farmer_first, u.last_name as farmer_last
    FROM field_visits fv
    LEFT JOIN users u ON fv.farmer_id = u.user_id
    WHERE fv.officer_id = ?
    ORDER BY fv.visit_date DESC LIMIT 5", [$officerId]);

// Recent alerts issued by this officer
$recentAlerts = $db->resultSet("SELECT * FROM alerts WHERE created_by = ? ORDER BY created_at DESC LIMIT 5", [$officerId]);

// Recent advisories
$recentAdvisories = $db->resultSet("SELECT * FROM advisories WHERE created_by = ? ORDER BY created_at DESC LIMIT 5", [$officerId]);

// Disease reports handled/reviewed
$reportsReviewed = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE verified_by = ?", [$officerId])['count'] ?? 0;

// Check user roles
$isFarmer = ($currentUser['role'] === 'farmer');
$isAdmin = ($currentUser['role'] === 'admin');
$isOwnProfile = ($currentUser['user_id'] == $officerId);

// Expertise areas mapping
$expertiseMap = [
    'crop_management' => __('crop_management') ?: 'Crop Management',
    'pest_control' => __('pest_control') ?: 'Pest Control',
    'soil_health' => __('soil_health') ?: 'Soil Health',
    'irrigation' => __('irrigation') ?: 'Irrigation',
    'organic_farming' => __('organic_farming') ?: 'Organic Farming',
    'livestock' => __('livestock') ?: 'Livestock',
    'fisheries' => __('fisheries') ?: 'Fisheries',
    'general' => __('general_agriculture') ?: 'General Agriculture'
];
?>

<section class="hero">
    <h1><span class="material-icons">badge</span> <?php echo __('officer_profile') ?: 'Officer Profile'; ?></h1>
    <p><?php echo __('viewing_profile_of') ?: 'Viewing profile of'; ?> <?php echo htmlspecialchars($officer['first_name'] . ' ' . ($officer['last_name'] ?? '')); ?></p>
</section>

<div class="profile-view-container">
    <!-- Left Column - Officer Info -->
    <div class="profile-view-left">
        <!-- Officer Information Card -->
        <div class="card profile-view-card">
            <div class="profile-view-header">
                <div class="profile-view-avatar officer-avatar">
                    <?php if (!empty($officer['profile_img_url'])): ?>
                        <img src="<?php echo $base_url . 'public/' . htmlspecialchars($officer['profile_img_url']); ?>" alt="Profile">
                    <?php else: ?>
                        <span class="material-icons">admin_panel_settings</span>
                    <?php endif; ?>
                </div>
                <div class="profile-view-info">
                    <h2><?php echo htmlspecialchars($officer['first_name'] . ' ' . ($officer['last_name'] ?? '')); ?></h2>
                    <p class="profile-view-role">
                        <span class="material-icons">badge</span>
                        <?php echo __('agriculture_officer') ?: 'Agriculture Officer'; ?>
                        <?php if ($officer['is_verified']): ?>
                        <span class="badge badge-success"><span class="material-icons" style="font-size: 0.8rem;">verified</span> <?php echo __('verified') ?: 'Verified'; ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($officer['designation'])): ?>
                    <p class="profile-view-designation">
                        <span class="badge badge-info"><?php echo htmlspecialchars($officer['designation']); ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-view-details">
                <?php if (!empty($officer['department'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">business</span>
                    <span><strong><?php echo __('department') ?: 'Department'; ?>:</strong> <?php echo htmlspecialchars($officer['department']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($officer['expertise_area'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">psychology</span>
                    <span><strong><?php echo __('expertise') ?: 'Expertise'; ?>:</strong> <?php echo htmlspecialchars($expertiseMap[$officer['expertise_area']] ?? ucfirst(str_replace('_', ' ', $officer['expertise_area']))); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($officer['region']) || !empty($officer['district'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">location_on</span>
                    <span><strong><?php echo __('coverage_area') ?: 'Coverage Area'; ?>:</strong> 
                        <?php 
                        $location = [];
                        if (!empty($officer['district'])) $location[] = $officer['district'];
                        if (!empty($officer['region'])) $location[] = $officer['region'];
                        echo htmlspecialchars(implode(', ', $location));
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($officer['office_location'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">apartment</span>
                    <span><strong><?php echo __('office_location') ?: 'Office Location'; ?>:</strong> <?php echo htmlspecialchars($officer['office_location']); ?></span>
                </div>
                <?php endif; ?>

                <div class="profile-detail-item">
                    <span class="material-icons">email</span>
                    <span><strong><?php echo __('email') ?: 'Email'; ?>:</strong> <?php echo htmlspecialchars($officer['email']); ?></span>
                </div>

                <div class="profile-detail-item">
                    <span class="material-icons">phone</span>
                    <span><strong><?php echo __('phone') ?: 'Phone'; ?>:</strong> <?php echo htmlspecialchars($officer['phone']); ?></span>
                </div>

                <?php if (!empty($officer['license_number'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">card_membership</span>
                    <span><strong><?php echo __('license_no') ?: 'License No'; ?>:</strong> <?php echo htmlspecialchars($officer['license_number']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($officer['joining_date'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">work_history</span>
                    <span><strong><?php echo __('joined_service') ?: 'Joined Service'; ?>:</strong> <?php echo date('M Y', strtotime($officer['joining_date'])); ?></span>
                </div>
                <?php endif; ?>

                <div class="profile-detail-item">
                    <span class="material-icons">event</span>
                    <span><strong><?php echo __('member_since') ?: 'Member Since'; ?>:</strong> <?php echo date('M Y', strtotime($officer['created_at'])); ?></span>
                </div>
                
                <?php if (!empty($officer['last_login'])): ?>
                <div class="profile-detail-item">
                    <span class="material-icons">access_time</span>
                    <span><strong><?php echo __('last_active') ?: 'Last Active'; ?>:</strong> <?php echo date('M d, Y H:i', strtotime($officer['last_login'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Contact Actions for Farmers -->
            <?php if ($isFarmer): ?>
            <div class="profile-actions">
                <a href="tel:<?php echo htmlspecialchars($officer['phone']); ?>" class="btn btn-success">
                    <span class="material-icons">phone</span> <?php echo __('call') ?: 'Call'; ?>
                </a>
                <a href="mailto:<?php echo htmlspecialchars($officer['email']); ?>" class="btn btn-secondary">
                    <span class="material-icons">email</span> <?php echo __('email_btn') ?: 'Email'; ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div class="profile-actions" style="margin-top: 1rem;">
                <a href="<?php echo $base_url; ?>community?tab=officers" class="btn btn-outline">
                    <span class="material-icons">arrow_back</span> <?php echo __('back_to_officers') ?: 'Back to Officers'; ?>
                </a>
            </div>
        </div>

        <!-- Statistics Card -->
        <div class="card profile-view-card">
            <h3><span class="material-icons">analytics</span> <?php echo __('officer_statistics') ?: 'Officer Statistics'; ?></h3>
            <div class="profile-stats-grid">
                <div class="stat-box">
                    <span class="material-icons stat-icon text-primary">people</span>
                    <div class="stat-value"><?php echo $farmersInRegion; ?></div>
                    <div class="stat-label"><?php echo __('farmers_in_region') ?: 'Farmers'; ?></div>
                </div>
                <div class="stat-box">
                    <span class="material-icons stat-icon text-warning">notifications</span>
                    <div class="stat-value"><?php echo $alertsIssued; ?></div>
                    <div class="stat-label"><?php echo __('alerts_issued') ?: 'Alerts'; ?></div>
                </div>
                <div class="stat-box">
                    <span class="material-icons stat-icon text-success">event_available</span>
                    <div class="stat-value"><?php echo $completedVisits; ?></div>
                    <div class="stat-label"><?php echo __('visits_completed') ?: 'Visits Done'; ?></div>
                </div>
                <div class="stat-box">
                    <span class="material-icons stat-icon text-info">schedule</span>
                    <div class="stat-value"><?php echo $scheduledVisits; ?></div>
                    <div class="stat-label"><?php echo __('visits_scheduled') ?: 'Scheduled'; ?></div>
                </div>
                <div class="stat-box">
                    <span class="material-icons stat-icon text-secondary">article</span>
                    <div class="stat-value"><?php echo $advisoriesCreated; ?></div>
                    <div class="stat-label"><?php echo __('advisories') ?: 'Advisories'; ?></div>
                </div>
                <div class="stat-box">
                    <span class="material-icons stat-icon text-danger">coronavirus</span>
                    <div class="stat-value"><?php echo $reportsReviewed; ?></div>
                    <div class="stat-label"><?php echo __('reports_reviewed') ?: 'Reports'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Responsibilities Card -->
        <div class="card profile-view-card">
            <h3><span class="material-icons">work</span> <?php echo __('responsibilities') ?: 'Responsibilities'; ?></h3>
            <div class="officer-responsibilities">
                <div class="responsibility-item">
                    <span class="material-icons text-success">check_circle</span>
                    <span><?php echo __('resp_monitor_crops') ?: 'Monitor crop health and disease reports'; ?></span>
                </div>
                <div class="responsibility-item">
                    <span class="material-icons text-success">check_circle</span>
                    <span><?php echo __('resp_issue_alerts') ?: 'Issue weather and agricultural alerts'; ?></span>
                </div>
                <div class="responsibility-item">
                    <span class="material-icons text-success">check_circle</span>
                    <span><?php echo __('resp_provide_guidance') ?: 'Provide farming guidance and support'; ?></span>
                </div>
                <div class="responsibility-item">
                    <span class="material-icons text-success">check_circle</span>
                    <span><?php echo __('resp_field_visits') ?: 'Coordinate field visits and inspections'; ?></span>
                </div>
                <div class="responsibility-item">
                    <span class="material-icons text-success">check_circle</span>
                    <span><?php echo __('resp_advisories') ?: 'Create and distribute advisories'; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Activities -->
    <div class="profile-view-right">
        
        <!-- Recent Field Visits -->
        <?php if ($recentVisits): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><span class="material-icons">event_note</span> <?php echo __('recent_field_visits') ?: 'Recent Field Visits'; ?></h3>
                <span class="badge"><?php echo $totalVisits; ?> <?php echo __('total') ?: 'total'; ?></span>
            </div>
            <div class="visit-timeline">
                <?php foreach ($recentVisits as $visit): ?>
                <div class="visit-item visit-<?php echo $visit['status']; ?>">
                    <div class="visit-date">
                        <span class="visit-day"><?php echo date('d', strtotime($visit['visit_date'])); ?></span>
                        <span class="visit-month"><?php echo date('M', strtotime($visit['visit_date'])); ?></span>
                    </div>
                    <div class="visit-content">
                        <div class="visit-header">
                            <strong><?php echo htmlspecialchars(($visit['farmer_first'] ?? __('unknown')) . ' ' . ($visit['farmer_last'] ?? '')); ?></strong>
                            <span class="badge badge-<?php echo $visit['status'] === 'completed' ? 'success' : ($visit['status'] === 'scheduled' ? 'info' : 'secondary'); ?>">
                                <?php 
                                $statusMap = ['completed' => __('completed') ?: 'Completed', 'scheduled' => __('scheduled') ?: 'Scheduled', 'cancelled' => __('cancelled') ?: 'Cancelled'];
                                echo $statusMap[$visit['status']] ?? ucfirst($visit['status']); 
                                ?>
                            </span>
                        </div>
                        <?php if ($visit['purpose']): ?>
                        <p class="visit-purpose"><?php echo htmlspecialchars(substr($visit['purpose'], 0, 100)); ?><?php echo strlen($visit['purpose']) > 100 ? '...' : ''; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <span class="material-icons">event_busy</span>
                <p><?php echo __('no_field_visits') ?: 'No field visits recorded yet'; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Alerts -->
        <?php if ($recentAlerts): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><span class="material-icons">notifications_active</span> <?php echo __('recent_alerts') ?: 'Recent Alerts'; ?></h3>
                <span class="badge badge-warning"><?php echo $alertsThisMonth; ?> <?php echo __('this_month') ?: 'this month'; ?></span>
            </div>
            <div class="alerts-list">
                <?php foreach ($recentAlerts as $alert): ?>
                <div class="alert-item alert-<?php echo $alert['priority'] ?? 'medium'; ?>">
                    <div class="alert-icon">
                        <span class="material-icons">
                            <?php 
                            $iconMap = ['weather' => 'cloud', 'disease' => 'coronavirus', 'market' => 'store', 'advisory' => 'info'];
                            echo $iconMap[$alert['alert_type']] ?? 'notification_important';
                            ?>
                        </span>
                    </div>
                    <div class="alert-content">
                        <div class="alert-header">
                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                            <span class="badge badge-<?php echo $alert['priority'] === 'high' || $alert['priority'] === 'critical' ? 'danger' : ($alert['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                <?php 
                                $priorityMap = ['high' => __('high') ?: 'High', 'critical' => __('critical') ?: 'Critical', 'medium' => __('medium') ?: 'Medium', 'low' => __('low') ?: 'Low'];
                                echo $priorityMap[$alert['priority']] ?? ucfirst($alert['priority'] ?? 'Medium'); 
                                ?>
                            </span>
                        </div>
                        <p class="alert-message"><?php echo htmlspecialchars(substr($alert['message'], 0, 120)); ?><?php echo strlen($alert['message']) > 120 ? '...' : ''; ?></p>
                        <span class="alert-date"><span class="material-icons">schedule</span> <?php echo date('M d, Y', strtotime($alert['created_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <span class="material-icons">notifications_off</span>
                <p><?php echo __('no_alerts_issued') ?: 'No alerts issued yet'; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Advisories -->
        <?php if ($recentAdvisories): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><span class="material-icons">article</span> <?php echo __('recent_advisories') ?: 'Recent Advisories'; ?></h3>
                <span class="badge"><?php echo $advisoriesCreated; ?> <?php echo __('total') ?: 'total'; ?></span>
            </div>
            <div class="advisories-list">
                <?php foreach ($recentAdvisories as $advisory): ?>
                <div class="advisory-item">
                    <div class="advisory-header">
                        <h4><?php echo htmlspecialchars($advisory['title']); ?></h4>
                        <span class="badge badge-<?php echo ($advisory['priority'] ?? 'medium') === 'high' ? 'danger' : 'info'; ?>">
                            <?php echo ucfirst($advisory['advisory_type'] ?? 'General'); ?>
                        </span>
                    </div>
                    <p class="advisory-content"><?php echo htmlspecialchars(substr($advisory['content'], 0, 150)); ?><?php echo strlen($advisory['content']) > 150 ? '...' : ''; ?></p>
                    <div class="advisory-meta">
                        <span><span class="material-icons">schedule</span> <?php echo date('M d, Y', strtotime($advisory['created_at'])); ?></span>
                        <?php if (!empty($advisory['target_region'])): ?>
                        <span><span class="material-icons">location_on</span> <?php echo htmlspecialchars($advisory['target_region']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <span class="material-icons">article</span>
                <p><?php echo __('no_advisories') ?: 'No advisories published yet'; ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Officer Profile View Styles */
.profile-view-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

@media (min-width: 992px) {
    .profile-view-container {
        grid-template-columns: 400px 1fr;
    }
}

.profile-view-card {
    padding: 1.5rem;
}

.profile-view-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

@media (min-width: 576px) {
    .profile-view-header {
        flex-direction: row;
        text-align: left;
    }
}

.profile-view-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.profile-view-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-view-avatar .material-icons {
    font-size: 48px;
    color: white;
}

.profile-view-avatar.officer-avatar {
    background: linear-gradient(135deg, var(--info), #2980b9);
}

.profile-view-info h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
}

.profile-view-role {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    font-weight: 600;
    flex-wrap: wrap;
}

.profile-view-role .material-icons {
    font-size: 20px;
}

.profile-view-designation {
    margin-top: 0.5rem;
}

.profile-view-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.profile-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.5rem;
    background: var(--light);
    border-radius: 8px;
}

.profile-detail-item .material-icons {
    color: var(--primary);
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.profile-detail-item span {
    word-break: break-word;
}

.profile-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.profile-actions .btn {
    flex: 1;
    min-width: 100px;
    justify-content: center;
}

.profile-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (min-width: 576px) {
    .profile-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.stat-box {
    text-align: center;
    padding: 1rem;
    background: var(--light);
    border-radius: 12px;
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
}

.officer-responsibilities {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.responsibility-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--light);
    border-radius: 8px;
}

.responsibility-item .material-icons {
    font-size: 20px;
}

/* Cards in Right Column */
.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-header-flex h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

/* Visit Timeline */
.visit-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.visit-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--light);
    border-radius: 12px;
    border-left: 4px solid var(--border);
}

.visit-item.visit-completed {
    border-left-color: var(--success);
}

.visit-item.visit-scheduled {
    border-left-color: var(--info);
}

.visit-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 50px;
}

.visit-day {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.visit-month {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
}

.visit-content {
    flex: 1;
}

.visit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.visit-location {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #666;
    font-size: 0.85rem;
    margin: 0.25rem 0;
}

.visit-location .material-icons {
    font-size: 16px;
}

.visit-purpose {
    color: #555;
    font-size: 0.9rem;
    margin: 0;
}

/* Alert Items */
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.alert-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--light);
    border-radius: 12px;
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--warning);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.alert-icon .material-icons {
    color: white;
    font-size: 20px;
}

.alert-content {
    flex: 1;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.alert-message {
    color: #555;
    font-size: 0.9rem;
    margin: 0 0 0.5rem;
}

.alert-date {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #888;
    font-size: 0.8rem;
}

.alert-date .material-icons {
    font-size: 14px;
}

/* Advisory Items */
.advisories-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.advisory-item {
    padding: 1rem;
    background: var(--light);
    border-radius: 12px;
}

.advisory-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.advisory-header h4 {
    margin: 0;
    font-size: 1rem;
}

.advisory-content {
    color: #555;
    font-size: 0.9rem;
    margin: 0 0 0.75rem;
}

.advisory-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    color: #888;
    font-size: 0.8rem;
}

.advisory-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.advisory-meta .material-icons {
    font-size: 14px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #888;
}

.empty-state .material-icons {
    font-size: 48px;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Badge Styles */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: var(--success);
    color: white;
}

.badge-info {
    background: var(--info);
    color: white;
}

.badge-warning {
    background: var(--warning);
    color: white;
}

.badge-danger {
    background: var(--danger);
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

/* Button Styles */
.btn-outline {
    background: transparent;
    border: 2px solid var(--primary);
    color: var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

/* Text Colors */
.text-primary { color: var(--primary); }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }
.text-info { color: var(--info); }
.text-secondary { color: #6c757d; }
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
