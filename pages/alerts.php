<?php
/**
 * Alerts & Notifications Page - Fully Dynamic with Advanced Features
 * Supports both English and Bengali
 */

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$currentUser = getCurrentUser();

// Check if viewing a specific farmer's alerts (for officers)
$viewingFarmerId = $_GET['farmer_id'] ?? null;
$viewingFarmer = null;
$userId = $_SESSION['user_id'];

if ($viewingFarmerId && ($currentUser['role'] === 'officer' || $currentUser['role'] === 'admin')) {
    $viewingFarmer = $db->single("SELECT user_id, first_name, last_name FROM users WHERE user_id = ? AND role = 'farmer'", [$viewingFarmerId]);
    if ($viewingFarmer) {
        $userId = $viewingFarmerId;
    }
}

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$filterPriority = $_GET['priority'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';

// Build query based on filters - exclude system notifications (only show alerts and advisories)
$whereClause = "WHERE user_id = ? AND alert_type != 'system'";
$params = [$userId];

if ($filterType !== 'all') {
    $whereClause .= " AND alert_type = ?";
    $params[] = $filterType;
}

if ($filterPriority !== 'all') {
    $whereClause .= " AND priority = ?";
    $params[] = $filterPriority;
}

if ($filterStatus === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filterStatus === 'read') {
    $whereClause .= " AND is_read = 1";
}

// Get alerts with filters (excluding system notifications)
$alerts = $db->resultSet("SELECT * FROM alerts $whereClause ORDER BY created_at DESC", $params);

// Get advisories from advisories table (for all farmers or by region)
$advisories = [];
try {
    $advisories = $db->resultSet("SELECT advisory_id as alert_id, title, content as message, advisory_type as alert_type, 
        priority, target_region as category, created_at, is_active, 0 as is_read 
        FROM advisories WHERE is_active = 1 ORDER BY created_at DESC LIMIT 50");
} catch (Exception $e) {
    // Table may not exist
}

// Merge advisories into alerts list (convert to same format)
foreach ($advisories as $advisory) {
    $advisory['from_advisories'] = true; // Mark as from advisories table
    $alerts[] = $advisory;
}

// Sort merged array by created_at
usort($alerts, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get statistics (excluding system notifications)
$totalAlerts = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND alert_type != 'system'", [$userId])['count'] ?? 0;
$unreadCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND is_read = 0 AND alert_type != 'system'", [$userId])['count'] ?? 0;
$highPriorityCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND priority = 'high' AND is_read = 0 AND alert_type != 'system'", [$userId])['count'] ?? 0;
$todayCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND DATE(created_at) = CURDATE() AND alert_type != 'system'", [$userId])['count'] ?? 0;

// Add advisories counts to totals
$advisoriesStat = $db->single("SELECT COUNT(*) as count FROM advisories WHERE is_active = 1") ?? ['count' => 0];
$totalAlerts += $advisoriesStat['count'];

// Get category counts
$weatherCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND alert_type = 'weather'", [$userId])['count'] ?? 0;
$diseaseCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND alert_type = 'disease'", [$userId])['count'] ?? 0;
$marketCount = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND alert_type = 'market'", [$userId])['count'] ?? 0;
$advisoryCount = ($db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND alert_type = 'advisory'", [$userId])['count'] ?? 0) + ($advisoriesStat['count'] ?? 0);

// Get upcoming field visits for farmers
$upcomingVisits = [];
if ($currentUser['role'] === 'farmer' || $viewingFarmer) {
    $targetId = $viewingFarmer ? $viewingFarmerId : $userId;
    $upcomingVisits = $db->resultSet("SELECT fv.*, u.first_name as officer_first, u.last_name as officer_last 
        FROM field_visits fv 
        JOIN users u ON fv.officer_id = u.user_id 
        WHERE fv.farmer_id = ? AND fv.status = 'scheduled' AND fv.visit_date >= CURDATE()
        ORDER BY fv.visit_date ASC LIMIT 5", [$targetId]);
}

// Get user preferences (with fallback if table doesn't exist)
$userPrefs = null;
try {
    $userPrefs = $db->single("SELECT * FROM user_preferences WHERE user_id = ?", [$userId]);
} catch (Exception $e) {
    // Table may not exist yet
    $userPrefs = null;
}
if (!$userPrefs) {
    $userPrefs = [
        'weather_alerts' => 1,
        'disease_alerts' => 1,
        'market_alerts' => 1,
        'community_alerts' => 1,
        'email_notifications' => 1,
        'sms_notifications' => 0
    ];
}
?>

<!-- Modern Hero Section -->
<section class="hero-modern">
    <div class="hero-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">notifications_active</span>
            <span><?php echo __('notifications'); ?></span>
        </div>
        <h1>
            <span class="material-icons" style="font-size: 2rem;">notifications_active</span>
            <?php if ($viewingFarmer): ?>
                <?php echo __('alerts_for'); ?> <?php echo htmlspecialchars($viewingFarmer['first_name'] . ' ' . ($viewingFarmer['last_name'] ?? '')); ?>
            <?php else: ?>
                <?php echo __('alerts_notifications'); ?>
            <?php endif; ?>
        </h1>
        <p class="hero-subtitle">
            <?php if ($viewingFarmer): ?>
                <?php echo __('viewing_farmer_alerts'); ?>
                <a href="<?php echo $base_url; ?>farmer-profile-view?id=<?php echo $viewingFarmerId; ?>" class="btn btn-small btn-secondary" style="margin-left: 10px;">
                    <span class="material-icons">arrow_back</span> <?php echo __('back_to_profile'); ?>
                </a>
            <?php else: ?>
                <?php echo __('alerts_description'); ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">mark_email_unread</span>
            <span><?php echo $unreadCount; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">priority_high</span>
            <span><?php echo $highPriorityCount; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">today</span>
        </div>
    </div>
</section>

<!-- Statistics Cards -->
<div class="alerts-stats-grid mt-4 mb-4">
    <div class="alert-stat-card stat-unread">
        <div class="alert-stat-icon">
            <span class="material-icons">mark_email_unread</span>
        </div>
        <div class="alert-stat-info">
            <h3><?php echo $unreadCount; ?></h3>
            <p><?php echo __('unread_alerts'); ?></p>
        </div>
    </div>

    <div class="alert-stat-card stat-total">
        <div class="alert-stat-icon">
            <span class="material-icons">notifications</span>
        </div>
        <div class="alert-stat-info">
            <h3><?php echo $totalAlerts; ?></h3>
            <p><?php echo __('total_alerts'); ?></p>
        </div>
    </div>

    <div class="alert-stat-card stat-critical <?php echo $highPriorityCount > 0 ? 'has-critical' : ''; ?>">
        <div class="alert-stat-icon">
            <span class="material-icons">priority_high</span>
        </div>
        <div class="alert-stat-info">
            <h3><?php echo $highPriorityCount; ?></h3>
            <p><?php echo __('high_priority'); ?></p>
        </div>
    </div>

    <div class="alert-stat-card stat-today">
        <div class="alert-stat-icon">
            <span class="material-icons">today</span>
        </div>
        <div class="alert-stat-info">
            <h3><?php echo $todayCount; ?></h3>
            <p><?php echo __('today'); ?></p>
        </div>
    </div>
</div>

<!-- Upcoming Field Visits (for farmers) -->
<?php if (!empty($upcomingVisits)): ?>
<div class="card mb-4 upcoming-visits-card">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">event</span> <?php echo __('upcoming_visits'); ?></h3>
    </div>
    <div class="card-body">
        <div class="visits-timeline">
            <?php foreach ($upcomingVisits as $visit): ?>
            <div class="visit-item">
                <div class="visit-date-badge">
                    <span class="visit-day"><?php echo date('d', strtotime($visit['visit_date'])); ?></span>
                    <span class="visit-month"><?php echo date('M', strtotime($visit['visit_date'])); ?></span>
                </div>
                <div class="visit-info">
                    <h4><?php echo __('field_visit'); ?></h4>
                    <p><span class="material-icons">person</span> <?php echo htmlspecialchars($visit['officer_first'] . ' ' . ($visit['officer_last'] ?? '')); ?></p>
                    <?php if ($visit['purpose']): ?>
                    <p><span class="material-icons">description</span> <?php echo htmlspecialchars($visit['purpose']); ?></p>
                    <?php endif; ?>
                    <?php if ($visit['visit_time']): ?>
                    <p><span class="material-icons">schedule</span> <?php echo date('h:i A', strtotime($visit['visit_time'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Alert Categories -->
<h2><span class="material-icons">category</span> <?php echo __('alert_categories'); ?></h2>
<div class="alert-categories-grid mt-3 mb-4">
    <div class="alert-category-card <?php echo $filterType === 'weather' ? 'active' : ''; ?>" onclick="filterByType('weather')">
        <span class="material-icons alert-category-icon">wb_cloudy</span>
        <h4><?php echo __('weather_alerts'); ?></h4>
        <p><?php echo $weatherCount; ?> <?php echo __('active'); ?></p>
    </div>

    <div class="alert-category-card <?php echo $filterType === 'disease' ? 'active' : ''; ?>" onclick="filterByType('disease')">
        <span class="material-icons alert-category-icon">coronavirus</span>
        <h4><?php echo __('disease_alerts'); ?></h4>
        <p><?php echo $diseaseCount; ?> <?php echo __('active'); ?></p>
    </div>

    <div class="alert-category-card <?php echo $filterType === 'market' ? 'active' : ''; ?>" onclick="filterByType('market')">
        <span class="material-icons alert-category-icon">trending_up</span>
        <h4><?php echo __('market_alerts'); ?></h4>
        <p><?php echo $marketCount; ?> <?php echo __('active'); ?></p>
    </div>

    <div class="alert-category-card <?php echo $filterType === 'advisory' ? 'active' : ''; ?>" onclick="filterByType('advisory')">
        <span class="material-icons alert-category-icon">assignment</span>
        <h4><?php echo __('advisory_alerts'); ?></h4>
        <p><?php echo $advisoryCount; ?> <?php echo __('active'); ?></p>
    </div>

</div>

<!-- Filter & Actions Bar -->
<div class="card mb-4 filter-actions-card">
    <div class="filter-actions-bar">
        <div class="filters-section">
            <div class="filter-group">
                <label><span class="material-icons">filter_list</span> <?php echo __('filter_by'); ?></label>
                <select id="filterType" onchange="applyFilters()">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo __('all_types'); ?></option>
                    <option value="weather" <?php echo $filterType === 'weather' ? 'selected' : ''; ?>><?php echo __('weather'); ?></option>
                    <option value="disease" <?php echo $filterType === 'disease' ? 'selected' : ''; ?>><?php echo __('disease'); ?></option>
                    <option value="market" <?php echo $filterType === 'market' ? 'selected' : ''; ?>><?php echo __('market'); ?></option>
                    <option value="advisory" <?php echo $filterType === 'advisory' ? 'selected' : ''; ?>><?php echo __('advisory'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label><span class="material-icons">priority_high</span> <?php echo __('priority'); ?></label>
                <select id="filterPriority" onchange="applyFilters()">
                    <option value="all" <?php echo $filterPriority === 'all' ? 'selected' : ''; ?>><?php echo __('all_priorities'); ?></option>
                    <option value="high" <?php echo $filterPriority === 'high' ? 'selected' : ''; ?>><?php echo __('high'); ?></option>
                    <option value="medium" <?php echo $filterPriority === 'medium' ? 'selected' : ''; ?>><?php echo __('medium'); ?></option>
                    <option value="low" <?php echo $filterPriority === 'low' ? 'selected' : ''; ?>><?php echo __('low'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label><span class="material-icons">visibility</span> <?php echo __('status'); ?></label>
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>><?php echo __('all_status'); ?></option>
                    <option value="unread" <?php echo $filterStatus === 'unread' ? 'selected' : ''; ?>><?php echo __('unread'); ?></option>
                    <option value="read" <?php echo $filterStatus === 'read' ? 'selected' : ''; ?>><?php echo __('read'); ?></option>
                </select>
            </div>
        </div>

        <div class="actions-section">
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-secondary" onclick="markAllAsRead()">
                <span class="material-icons">done_all</span> <?php echo __('mark_all_read'); ?>
            </button>
            <?php endif; ?>
            <button class="btn btn-outline" onclick="clearFilters()">
                <span class="material-icons">clear_all</span> <?php echo __('clear_filters'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Alerts List -->
<h2>
    <span class="material-icons">list</span> 
    <?php echo __('all_alerts'); ?>
    <span class="badge"><?php echo count($alerts); ?> <?php echo __('results'); ?></span>
</h2>

<?php if ($alerts): ?>
    <div class="alerts-list-container" id="alertsList">
        <?php foreach ($alerts as $alert): ?>
            <div class="alert-item notice notice-<?php echo $alert['priority'] === 'high' ? 'danger' : ($alert['priority'] === 'medium' ? 'warning' : 'info'); ?> <?php echo !$alert['is_read'] ? 'unread' : ''; ?>" 
                 data-alert-id="<?php echo $alert['alert_id']; ?>">
                <div class="alert-content">
                    <div class="alert-header">
                        <div class="alert-title-section">
                            <span class="material-icons alert-type-icon">
                                <?php 
                                $icon = match($alert['alert_type']) {
                                    'weather' => 'wb_cloudy',
                                    'disease' => 'coronavirus',
                                    'market' => 'trending_up',
                                    'advisory' => 'assignment',
                                    default => 'settings'
                                };
                                echo $icon;
                                ?>
                            </span>
                            <div class="alert-title-info">
                                <strong class="alert-type-title">
                                    <?php 
                                    $typeKey = $alert['alert_type'] . '_alert';
                                    echo __($typeKey) ?: ucfirst($alert['alert_type']) . ' Alert'; 
                                    ?>
                                </strong>
                                <?php if (!empty($alert['title'])): ?>
                                <span class="alert-title-text"><?php echo htmlspecialchars($alert['title']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="alert-badges">
                            <span class="badge badge-<?php echo $alert['priority'] === 'high' ? 'danger' : ($alert['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                <?php echo __($alert['priority']); ?>
                            </span>
                            <?php if (!$alert['is_read']): ?>
                                <span class="alert-new-badge"><?php echo __('new'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></p>
                    
                    <div class="alert-footer">
                        <div class="alert-meta">
                            <span class="material-icons">schedule</span>
                            <span class="alert-time"><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></span>
                            <?php 
                            $timeDiff = time() - strtotime($alert['created_at']);
                            if ($timeDiff < 3600) {
                                $minutes = floor($timeDiff / 60);
                                echo '<span class="time-ago">(' . $minutes . ' ' . __('minutes_ago') . ')</span>';
                            } elseif ($timeDiff < 86400) {
                                $hours = floor($timeDiff / 3600);
                                echo '<span class="time-ago">(' . $hours . ' ' . __('hours_ago') . ')</span>';
                            }
                            ?>
                        </div>
                        <div class="alert-actions">
                            <?php if (!$alert['is_read']): ?>
                            <button class="btn-icon" onclick="markAsRead(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('mark_as_read'); ?>">
                                <span class="material-icons">done</span>
                            </button>
                            <?php else: ?>
                            <button class="btn-icon" onclick="markAsUnread(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('mark_as_unread'); ?>">
                                <span class="material-icons">mark_email_unread</span>
                            </button>
                            <?php endif; ?>
                            <button class="btn-icon btn-danger" onclick="deleteAlert(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('delete'); ?>">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination Controls -->
    <div id="alertsPaginationContainer" class="pagination-wrapper" style="display: none;">
        <div class="pagination">
            <button id="alertsFirstPageBtn" class="pagination-btn" title="<?php echo __('first_page'); ?>">
                <span class="material-icons">first_page</span>
            </button>
            <button id="alertsPrevPageBtn" class="pagination-btn" title="<?php echo __('previous_page'); ?>">
                <span class="material-icons">chevron_left</span>
            </button>
            <div id="alertsPageNumbers" class="page-numbers">
                <!-- Page numbers will be generated here -->
            </div>
            <button id="alertsNextPageBtn" class="pagination-btn" title="<?php echo __('next_page'); ?>">
                <span class="material-icons">chevron_right</span>
            </button>
            <button id="alertsLastPageBtn" class="pagination-btn" title="<?php echo __('last_page'); ?>">
                <span class="material-icons">last_page</span>
            </button>
        </div>
        <div class="pagination-info">
            <span id="alertsPageInfo"><?php echo __('page'); ?> 1 <?php echo __('of'); ?> 1</span>
            <span class="pagination-separator">•</span>
            <span id="alertsResultsInfo"><?php echo __('showing'); ?> 0 <?php echo __('alerts'); ?></span>
        </div>
    </div>
<?php else: ?>
    <div class="card text-center mt-4 empty-alerts-card">
        <span class="material-icons empty-alerts-icon">notifications_off</span>
        <h3><?php echo __('no_alerts'); ?></h3>
        <p><?php echo __('no_alerts_message'); ?></p>
        <a href="<?php echo $base_url; ?>dashboard" class="btn mt-2">
            <span class="material-icons">dashboard</span> <?php echo __('go_to_dashboard'); ?>
        </a>
    </div>
<?php endif; ?>

<!-- Alert Preferences Card -->
<div class="card mt-4 alert-preferences-card">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">tune</span> <?php echo __('alert_preferences'); ?></h3>
    </div>

    <form id="preferencesForm">
        <div class="alert-preferences-grid">
            <label class="checkbox-label">
                <input type="checkbox" name="weather_alerts" <?php echo ($userPrefs['weather_alerts'] ?? 1) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">wb_cloudy</span>
                <?php echo __('receive_weather_alerts'); ?>
            </label>

            <label class="checkbox-label">
                <input type="checkbox" name="disease_alerts" <?php echo ($userPrefs['disease_alerts'] ?? 1) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">coronavirus</span>
                <?php echo __('receive_disease_alerts'); ?>
            </label>

            <label class="checkbox-label">
                <input type="checkbox" name="market_alerts" <?php echo ($userPrefs['market_alerts'] ?? 1) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">trending_up</span>
                <?php echo __('receive_market_alerts'); ?>
            </label>

            <label class="checkbox-label">
                <input type="checkbox" name="community_alerts" <?php echo ($userPrefs['community_alerts'] ?? 1) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">people</span>
                <?php echo __('receive_community_alerts'); ?>
            </label>

            <label class="checkbox-label">
                <input type="checkbox" name="email_notifications" <?php echo ($userPrefs['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">email</span>
                <?php echo __('email_notifications'); ?>
            </label>

            <label class="checkbox-label">
                <input type="checkbox" name="sms_notifications" <?php echo ($userPrefs['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                <span class="material-icons checkbox-icon">sms</span>
                <?php echo __('sms_notifications'); ?>
            </label>
        </div>

        <button type="submit" class="btn mt-3">
            <span class="material-icons">save</span> <?php echo __('save_preferences'); ?>
        </button>
    </form>
</div>

<style>
/* Enhanced Alerts Page Styles */
.alerts-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.alert-stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--border-color);
}

.alert-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.alert-stat-card.stat-unread .alert-stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.alert-stat-card.stat-total .alert-stat-icon {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.alert-stat-card.stat-critical .alert-stat-icon {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
}

.alert-stat-card.stat-critical.has-critical {
    animation: pulse 2s infinite;
    border-color: #eb3349;
}

.alert-stat-card.stat-today .alert-stat-icon {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.alert-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.alert-stat-icon .material-icons {
    font-size: 28px;
}

.alert-stat-info h3 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.alert-stat-info p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Category Cards */
.alert-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.alert-category-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.alert-category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.alert-category-card.active {
    border-color: var(--primary-color);
    background: rgba(85, 122, 70, 0.1);
}

.alert-category-icon {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.alert-category-card h4 {
    margin: 0.5rem 0;
    font-size: 1rem;
}

.alert-category-card p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Filter Actions Bar */
.filter-actions-card {
    padding: 1.25rem 1.5rem;
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.filter-actions-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.filters-section {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group label .material-icons {
    font-size: 1rem;
    color: var(--primary-color);
}

.filter-group select {
    padding: 0.65rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-input);
    color: var(--text-primary);
    min-width: 150px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-group select:hover {
    border-color: var(--primary-color);
}

.filter-group select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.15);
}

.actions-section {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

/* Alert Items */
.alert-item {
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.alert-item.unread {
    border-left-width: 4px;
    background: rgba(85, 122, 70, 0.05);
}

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.alert-title-section {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-type-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    color: white;
    flex-shrink: 0;
}

.alert-title-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.alert-type-title {
    font-weight: 600;
    color: var(--text-primary);
}

.alert-title-text {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.alert-badges {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.alert-new-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    animation: pulse 2s infinite;
}

.alert-message {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0.75rem 0;
}

.alert-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}

.alert-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.875rem;
}

.alert-meta .material-icons {
    font-size: 1rem;
}

.time-ago {
    color: var(--primary-color);
    font-size: 0.75rem;
}

.alert-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: var(--bg-hover);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: var(--primary-color);
    color: white;
}

.btn-icon.btn-danger:hover {
    background: #dc3545;
}

/* Upcoming Visits */
.upcoming-visits-card {
    border-left: 4px solid var(--primary-color);
}

.visits-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.visit-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
}

.visit-date-badge {
    min-width: 60px;
    background: var(--primary-color);
    color: white;
    border-radius: 8px;
    padding: 0.5rem;
    text-align: center;
}

.visit-day {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
}

.visit-month {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.visit-info h4 {
    margin: 0 0 0.5rem;
    color: var(--text-primary);
}

.visit-info p {
    margin: 0.25rem 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.visit-info .material-icons {
    font-size: 1rem;
    color: var(--primary-color);
}

/* Empty State */
.empty-alerts-card {
    padding: 3rem;
}

.empty-alerts-icon {
    font-size: 5rem;
    color: var(--text-muted);
    opacity: 0.5;
}

/* Preferences */
.alert-preferences-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkbox-label:hover {
    background: rgba(85, 122, 70, 0.1);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: var(--primary-color);
}

.checkbox-icon {
    color: var(--primary-color);
}

/* Animations */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Responsive */
@media (max-width: 768px) {
    .filter-actions-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .filters-section {
        flex-direction: column;
    }

    .filter-group {
        width: 100%;
    }

    .filter-group select {
        width: 100%;
    }

    .actions-section {
        justify-content: stretch;
    }

    .actions-section .btn {
        flex: 1;
        justify-content: center;
    }

    .alert-header {
        flex-direction: column;
    }

    .alert-footer {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
var baseUrl = '<?php echo $base_url; ?>';
const currentFilters = {
    type: '<?php echo $filterType; ?>',
    priority: '<?php echo $filterPriority; ?>',
    status: '<?php echo $filterStatus; ?>'
};

// Pagination state
const alertsPagination = {
    currentPage: 1,
    itemsPerPage: 10,
    totalItems: 0,
    totalPages: 0
};

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
    initAlertsPagination();
});

function initAlertsPagination() {
    const alertsList = document.getElementById('alertsList');
    if (!alertsList) return;
    
    const allAlerts = alertsList.querySelectorAll('.alert-item');
    alertsPagination.totalItems = allAlerts.length;
    alertsPagination.totalPages = Math.ceil(alertsPagination.totalItems / alertsPagination.itemsPerPage);
    
    // Show pagination only if more than one page
    const paginationContainer = document.getElementById('alertsPaginationContainer');
    if (paginationContainer && alertsPagination.totalPages > 1) {
        paginationContainer.style.display = 'flex';
        setupAlertsPaginationListeners();
        showAlertsPage(1);
    } else if (alertsPagination.totalItems > 0) {
        // Show all items if only one page
        allAlerts.forEach(item => item.style.display = '');
    }
}

function setupAlertsPaginationListeners() {
    document.getElementById('alertsFirstPageBtn')?.addEventListener('click', () => showAlertsPage(1));
    document.getElementById('alertsPrevPageBtn')?.addEventListener('click', () => showAlertsPage(alertsPagination.currentPage - 1));
    document.getElementById('alertsNextPageBtn')?.addEventListener('click', () => showAlertsPage(alertsPagination.currentPage + 1));
    document.getElementById('alertsLastPageBtn')?.addEventListener('click', () => showAlertsPage(alertsPagination.totalPages));
}

function showAlertsPage(page) {
    page = Math.max(1, Math.min(page, alertsPagination.totalPages));
    alertsPagination.currentPage = page;
    
    const alertsList = document.getElementById('alertsList');
    const allAlerts = alertsList.querySelectorAll('.alert-item');
    
    const startIndex = (page - 1) * alertsPagination.itemsPerPage;
    const endIndex = startIndex + alertsPagination.itemsPerPage;
    
    allAlerts.forEach((item, index) => {
        item.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    updateAlertsPaginationControls();
    
    // Scroll to top of alerts list
    alertsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function updateAlertsPaginationControls() {
    const { currentPage, totalPages, totalItems, itemsPerPage } = alertsPagination;
    
    // Update button states
    document.getElementById('alertsFirstPageBtn').disabled = currentPage === 1;
    document.getElementById('alertsPrevPageBtn').disabled = currentPage === 1;
    document.getElementById('alertsNextPageBtn').disabled = currentPage === totalPages;
    document.getElementById('alertsLastPageBtn').disabled = currentPage === totalPages;
    
    // Update page info
    document.getElementById('alertsPageInfo').textContent = `<?php echo __('page'); ?> ${currentPage} <?php echo __('of'); ?> ${totalPages}`;
    
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('alertsResultsInfo').textContent = `<?php echo __('showing'); ?> ${startItem}-${endItem} <?php echo __('of'); ?> ${totalItems}`;
    
    // Generate page numbers
    generateAlertsPageNumbers();
}

function generateAlertsPageNumbers() {
    const container = document.getElementById('alertsPageNumbers');
    const { currentPage, totalPages } = alertsPagination;
    container.innerHTML = '';
    
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    // First page + ellipsis
    if (startPage > 1) {
        container.appendChild(createAlertsPageButton(1));
        if (startPage > 2) {
            container.appendChild(createAlertsEllipsis());
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createAlertsPageButton(i));
    }
    
    // Ellipsis + last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            container.appendChild(createAlertsEllipsis());
        }
        container.appendChild(createAlertsPageButton(totalPages));
    }
}

function createAlertsPageButton(pageNum) {
    const btn = document.createElement('button');
    btn.className = 'page-number' + (pageNum === alertsPagination.currentPage ? ' active' : '');
    btn.textContent = pageNum;
    btn.addEventListener('click', () => showAlertsPage(pageNum));
    return btn;
}

function createAlertsEllipsis() {
    const span = document.createElement('span');
    span.className = 'page-ellipsis';
    span.textContent = '...';
    return span;
}

function filterByType(type) {
    document.getElementById('filterType').value = type;
    applyFilters();
}

function applyFilters() {
    const type = document.getElementById('filterType').value;
    const priority = document.getElementById('filterPriority').value;
    const status = document.getElementById('filterStatus').value;
    
    let url = baseUrl + 'alerts?';
    if (type !== 'all') url += 'type=' + type + '&';
    if (priority !== 'all') url += 'priority=' + priority + '&';
    if (status !== 'all') url += 'status=' + status + '&';
    
    <?php if ($viewingFarmerId): ?>
    url += 'farmer_id=<?php echo $viewingFarmerId; ?>';
    <?php endif; ?>
    
    window.location.href = url.replace(/&$/, '');
}

function clearFilters() {
    let url = baseUrl + 'alerts';
    <?php if ($viewingFarmerId): ?>
    url += '?farmer_id=<?php echo $viewingFarmerId; ?>';
    <?php endif; ?>
    window.location.href = url;
}

function markAsRead(alertId) {
    fetch(baseUrl + 'ajax/alerts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const alertItem = document.querySelector(`[data-alert-id="${alertId}"]`);
            alertItem.classList.remove('unread');
            alertItem.querySelector('.alert-new-badge')?.remove();
            showNotification('<?php echo __('marked_as_read'); ?>', 'success');
            updateUnreadCount(-1);
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

function markAsUnread(alertId) {
    fetch(baseUrl + 'ajax/alerts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_unread&alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

function markAllAsRead() {
    if (!confirm('<?php echo __('confirm_mark_all_read'); ?>')) return;
    
    fetch(baseUrl + 'ajax/alerts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('all_marked_as_read'); ?>', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

function deleteAlert(alertId) {
    if (!confirm('<?php echo __('confirm_delete_alert'); ?>')) return;
    
    fetch(baseUrl + 'ajax/alerts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const alertItem = document.querySelector(`[data-alert-id="${alertId}"]`);
            alertItem.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => alertItem.remove(), 300);
            showNotification('<?php echo __('alert_deleted'); ?>', 'success');
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

function updateUnreadCount(change) {
    const unreadEl = document.querySelector('.stat-unread h3');
    if (unreadEl) {
        let count = parseInt(unreadEl.textContent) + change;
        unreadEl.textContent = Math.max(0, count);
    }
}

// Preferences Form
document.getElementById('preferencesForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_preferences');
    
    fetch(baseUrl + 'ajax/alerts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('preferences_saved'); ?>', 'success');
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
});

// Animation for deleted items
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(-100%);
            height: 0;
            margin: 0;
            padding: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
