<?php
/**
 * Issue Alerts - For Agricultural Officers
 * Create and manage alerts for farmers with advanced features
 * Supports English and Bengali
 */

// Authentication and role check
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    redirect('home');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$officerId = $_SESSION['user_id'];

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$filterSeverity = $_GET['severity'] ?? 'all';
$filterRegion = $_GET['region'] ?? 'all';

// Build query for alerts
$whereClause = "WHERE a.created_by = ?";
$params = [$officerId];

if ($filterType !== 'all') {
    $whereClause .= " AND a.alert_type = ?";
    $params[] = $filterType;
}

if ($filterSeverity !== 'all') {
    $whereClause .= " AND a.priority = ?";
    $params[] = $filterSeverity;
}

// Get recent alerts with filters - group by title to show distinct advisories
$alerts = $db->resultSet("SELECT a.alert_id, a.title, a.alert_type, a.priority, a.message, a.category,
    a.action_url, a.sent_via, a.created_by, a.created_at, a.expires_at,
    DATE_FORMAT(a.created_at, '%M %d, %Y %h:%i %p') as formatted_date,
    (SELECT COUNT(*) FROM alerts WHERE alert_id = a.alert_id AND is_read = 1) as read_count
    FROM alerts a
    $whereClause
    GROUP BY a.alert_id, a.title, a.alert_type, a.priority, a.message, a.category,
             a.action_url, a.sent_via, a.created_by, a.created_at, a.expires_at
    ORDER BY a.created_at DESC LIMIT 50", $params);

// Get statistics - count distinct by title to avoid duplicates
$totalAlerts = $db->single("SELECT COUNT(DISTINCT title) as count FROM alerts WHERE created_by = ?", [$officerId])['count'] ?? 0;
$alertsThisMonth = $db->single("SELECT COUNT(DISTINCT title) as count FROM alerts WHERE created_by = ? AND MONTH(created_at) = MONTH(CURRENT_DATE())", [$officerId])['count'] ?? 0;
$criticalAlerts = $db->single("SELECT COUNT(DISTINCT title) as count FROM alerts WHERE created_by = ? AND priority = 'critical'", [$officerId])['count'] ?? 0;
$alertsToday = $db->single("SELECT COUNT(DISTINCT title) as count FROM alerts WHERE created_by = ? AND DATE(created_at) = CURDATE()", [$officerId])['count'] ?? 0;

// Get alert type distribution
$typeDistribution = $db->resultSet("SELECT alert_type, COUNT(*) as count FROM alerts WHERE created_by = ? GROUP BY alert_type", [$officerId]);

// Get farmers for targeted alerts
$farmers = $db->resultSet("SELECT user_id, first_name, last_name, phone FROM users WHERE role = 'farmer' AND is_active = 1 ORDER BY first_name");

// Get regions from farmer data
$regions = ['Dhaka', 'Chittagong', 'Khulna', 'Rangpur', 'Sylhet', 'Barisal', 'Rajshahi', 'Mymensingh'];

// Alert type icons mapping
$typeIcons = [
    'weather' => 'wb_cloudy',
    'disease' => 'coronavirus',
    'pest' => 'bug_report',
    'market' => 'trending_up',
    'government' => 'account_balance',
    'advisory' => 'assignment',
    'system' => 'settings',
    'general' => 'info'
];

// Severity colors
$severityColors = [
    'critical' => '#dc3545',
    'high' => '#fd7e14',
    'medium' => '#ffc107',
    'low' => '#28a745',
    'info' => '#17a2b8'
];
?>

<section class="hero">
    <h1><span class="material-icons">notifications_active</span> <?php echo __('issue_alerts'); ?></h1>
    <p><?php echo __('create_manage_alerts'); ?></p>
</section>

<!-- Statistics Cards -->
<div class="alert-stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-icon">
            <span class="material-icons">campaign</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalAlerts; ?></h3>
            <p><?php echo __('total_alerts_issued'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-month">
        <div class="stat-icon">
            <span class="material-icons">calendar_month</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $alertsThisMonth; ?></h3>
            <p><?php echo __('this_month'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-critical">
        <div class="stat-icon">
            <span class="material-icons">priority_high</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $criticalAlerts; ?></h3>
            <p><?php echo __('critical_alerts'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-today">
        <div class="stat-icon">
            <span class="material-icons">today</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $alertsToday; ?></h3>
            <p><?php echo __('today'); ?></p>
        </div>
    </div>
</div>

<!-- Alert Type Quick Buttons -->
<div class="alert-type-buttons mb-4">
    <button class="alert-type-btn weather" onclick="selectAlertType('weather')">
        <span class="material-icons">wb_cloudy</span>
        <?php echo __('weather'); ?>
    </button>
    <button class="alert-type-btn disease" onclick="selectAlertType('disease')">
        <span class="material-icons">coronavirus</span>
        <?php echo __('disease'); ?>
    </button>
    <button class="alert-type-btn pest" onclick="selectAlertType('pest')">
        <span class="material-icons">bug_report</span>
        <?php echo __('pest'); ?>
    </button>
    <button class="alert-type-btn market" onclick="selectAlertType('market')">
        <span class="material-icons">trending_up</span>
        <?php echo __('market'); ?>
    </button>
    <button class="alert-type-btn government" onclick="selectAlertType('government')">
        <span class="material-icons">account_balance</span>
        <?php echo __('government'); ?>
    </button>
    <button class="alert-type-btn general" onclick="selectAlertType('general')">
        <span class="material-icons">info</span>
        <?php echo __('general'); ?>
    </button>
</div>

<!-- Create Alert Form -->
<div class="card create-alert-card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">add_alert</span>
            <?php echo __('create_new_alert'); ?>
        </h3>
    </div>
    <form id="alertForm" class="card-body">
        <div class="form-row">
            <div class="form-group flex-2">
                <label for="alertTitle"><?php echo __('alert_title'); ?> *</label>
                <input type="text" id="alertTitle" name="title" placeholder="<?php echo __('eg_severe_weather'); ?>" required>
            </div>
            <div class="form-group flex-1">
                <label for="alertType"><?php echo __('alert_type'); ?> *</label>
                <select id="alertType" name="alert_type" required>
                    <option value=""><?php echo __('select_type'); ?></option>
                    <option value="weather"><?php echo __('weather_alert'); ?></option>
                    <option value="pest"><?php echo __('pest_outbreak'); ?></option>
                    <option value="disease"><?php echo __('disease_warning'); ?></option>
                    <option value="market"><?php echo __('market_update'); ?></option>
                    <option value="government"><?php echo __('government_notice'); ?></option>
                    <option value="advisory"><?php echo __('advisory'); ?></option>
                    <option value="general"><?php echo __('general_information'); ?></option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="severity"><?php echo __('severity_level'); ?> *</label>
                <select id="severity" name="severity" required>
                    <option value="low"><?php echo __('low'); ?></option>
                    <option value="medium" selected><?php echo __('medium'); ?></option>
                    <option value="high"><?php echo __('high'); ?></option>
                    <option value="critical"><?php echo __('critical'); ?></option>
                </select>
            </div>
            <div class="form-group flex-1">
                <label for="region"><?php echo __('target_region'); ?></label>
                <select id="region" name="region">
                    <option value="all"><?php echo __('all_regions'); ?></option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-1">
                <label for="targetFarmer"><?php echo __('target_farmer'); ?></label>
                <select id="targetFarmer" name="target_farmer">
                    <option value="all"><?php echo __('all_farmers'); ?></option>
                    <?php foreach ($farmers as $farmer): ?>
                        <option value="<?php echo $farmer['user_id']; ?>">
                            <?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="alertMessage"><?php echo __('alert_message'); ?> *</label>
            <textarea id="alertMessage" name="message" rows="4" placeholder="<?php echo __('detailed_alert_message'); ?>" required></textarea>
            <small class="char-count"><span id="messageCharCount">0</span>/500 <?php echo __('characters'); ?></small>
        </div>
        
        <div class="form-group">
            <label for="actionRequired"><?php echo __('action_required'); ?></label>
            <textarea id="actionRequired" name="action_required" rows="2" placeholder="<?php echo __('what_farmers_do'); ?>"></textarea>
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="validFrom"><?php echo __('valid_from'); ?></label>
                <input type="datetime-local" id="validFrom" name="valid_from">
            </div>
            <div class="form-group flex-1">
                <label for="validTo"><?php echo __('valid_to'); ?></label>
                <input type="datetime-local" id="validTo" name="valid_to">
            </div>
        </div>

        <div class="form-options">
            <label class="checkbox-option">
                <input type="checkbox" name="send_sms" id="sendSms">
                <span class="material-icons">sms</span>
                <?php echo __('send_sms'); ?>
            </label>
            <label class="checkbox-option">
                <input type="checkbox" name="send_email" id="sendEmail">
                <span class="material-icons">email</span>
                <?php echo __('send_email'); ?>
            </label>
            <label class="checkbox-option">
                <input type="checkbox" name="is_urgent" id="isUrgent">
                <span class="material-icons">bolt</span>
                <?php echo __('mark_urgent'); ?>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span class="material-icons">send</span>
                <?php echo __('send_alert'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="previewAlert()">
                <span class="material-icons">visibility</span>
                <?php echo __('preview'); ?>
            </button>
            <button type="reset" class="btn btn-outline">
                <span class="material-icons">clear</span>
                <?php echo __('clear'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter & Actions Bar -->
<div class="card filter-card mb-4">
    <div class="filter-bar">
        <div class="filters">
            <div class="filter-group">
                <label><?php echo __('filter_by_type'); ?></label>
                <select id="filterType" onchange="applyFilters()">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo __('all_types'); ?></option>
                    <option value="weather" <?php echo $filterType === 'weather' ? 'selected' : ''; ?>><?php echo __('weather'); ?></option>
                    <option value="disease" <?php echo $filterType === 'disease' ? 'selected' : ''; ?>><?php echo __('disease'); ?></option>
                    <option value="pest" <?php echo $filterType === 'pest' ? 'selected' : ''; ?>><?php echo __('pest'); ?></option>
                    <option value="market" <?php echo $filterType === 'market' ? 'selected' : ''; ?>><?php echo __('market'); ?></option>
                    <option value="government" <?php echo $filterType === 'government' ? 'selected' : ''; ?>><?php echo __('government'); ?></option>
                </select>
            </div>
            <div class="filter-group">
                <label><?php echo __('severity'); ?></label>
                <select id="filterSeverity" onchange="applyFilters()">
                    <option value="all" <?php echo $filterSeverity === 'all' ? 'selected' : ''; ?>><?php echo __('all'); ?></option>
                    <option value="critical" <?php echo $filterSeverity === 'critical' ? 'selected' : ''; ?>><?php echo __('critical'); ?></option>
                    <option value="high" <?php echo $filterSeverity === 'high' ? 'selected' : ''; ?>><?php echo __('high'); ?></option>
                    <option value="medium" <?php echo $filterSeverity === 'medium' ? 'selected' : ''; ?>><?php echo __('medium'); ?></option>
                    <option value="low" <?php echo $filterSeverity === 'low' ? 'selected' : ''; ?>><?php echo __('low'); ?></option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button class="btn btn-outline btn-sm" onclick="clearFilters()">
                <span class="material-icons">clear_all</span>
                <?php echo __('clear_filters'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Recent Alerts List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">history</span>
            <?php echo __('recent_alerts'); ?>
            <span class="badge"><?php echo count($alerts); ?></span>
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($alerts)): ?>
            <div class="empty-state">
                <span class="material-icons">notifications_none</span>
                <h3><?php echo __('no_alerts_created'); ?></h3>
                <p><?php echo __('create_first_alert'); ?></p>
            </div>
        <?php else: ?>
            <div class="alerts-list">
                <?php foreach ($alerts as $alert): ?>
                    <?php 
                    $priorityClass = $alert['priority'] ?? 'low';
                    $borderColor = $severityColors[$priorityClass] ?? '#17a2b8';
                    $typeIcon = $typeIcons[$alert['alert_type']] ?? 'notifications';
                    ?>
                    <div class="alert-item" data-alert-id="<?php echo $alert['alert_id']; ?>" style="border-left-color: <?php echo $borderColor; ?>;">
                        <div class="alert-item-header">
                            <div class="alert-title-section">
                                <span class="material-icons alert-icon" style="color: <?php echo $borderColor; ?>;">
                                    <?php echo $typeIcon; ?>
                                </span>
                                <div class="alert-title-info">
                                    <h4><?php echo htmlspecialchars($alert['title'] ?? 'Alert'); ?></h4>
                                    <span class="alert-type-badge"><?php echo ucfirst($alert['alert_type']); ?></span>
                                </div>
                            </div>
                            <div class="alert-badges">
                                <span class="badge badge-<?php echo $priorityClass === 'critical' ? 'danger' : ($priorityClass === 'high' ? 'warning' : 'info'); ?>">
                                    <?php echo __($priorityClass); ?>
                                </span>
                            </div>
                        </div>
                        
                        <p class="alert-message-text"><?php echo htmlspecialchars($alert['message']); ?></p>
                        
                        <div class="alert-item-footer">
                            <div class="alert-meta">
                                <span class="meta-item">
                                    <span class="material-icons">schedule</span>
                                    <?php echo $alert['formatted_date']; ?>
                                </span>
                                <?php if (!empty($alert['category'])): ?>
                                <span class="meta-item">
                                    <span class="material-icons">location_on</span>
                                    <?php echo htmlspecialchars($alert['category']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="alert-item-actions">
                                <button class="btn-icon" onclick="editAlert(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('edit'); ?>">
                                    <span class="material-icons">edit</span>
                                </button>
                                <button class="btn-icon" onclick="resendAlert(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('resend'); ?>">
                                    <span class="material-icons">refresh</span>
                                </button>
                                <button class="btn-icon btn-danger" onclick="deleteAlert(<?php echo $alert['alert_id']; ?>)" title="<?php echo __('delete'); ?>">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div id="issuedAlertsPaginationContainer" class="pagination-wrapper" style="display: none;">
                <div class="pagination">
                    <button id="issuedAlertsFirstPageBtn" class="pagination-btn" title="<?php echo __('first_page'); ?>">
                        <span class="material-icons">first_page</span>
                    </button>
                    <button id="issuedAlertsPrevPageBtn" class="pagination-btn" title="<?php echo __('previous_page'); ?>">
                        <span class="material-icons">chevron_left</span>
                    </button>
                    <div id="issuedAlertsPageNumbers" class="page-numbers"></div>
                    <button id="issuedAlertsNextPageBtn" class="pagination-btn" title="<?php echo __('next_page'); ?>">
                        <span class="material-icons">chevron_right</span>
                    </button>
                    <button id="issuedAlertsLastPageBtn" class="pagination-btn" title="<?php echo __('last_page'); ?>">
                        <span class="material-icons">last_page</span>
                    </button>
                </div>
                <div class="pagination-info">
                    <span id="issuedAlertsPageInfo"><?php echo __('page'); ?> 1 <?php echo __('of'); ?> 1</span>
                    <span class="pagination-separator">•</span>
                    <span id="issuedAlertsResultsInfo"><?php echo __('showing'); ?> 0 <?php echo __('alerts'); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">visibility</span> <?php echo __('alert_preview'); ?></h3>
            <button class="close-modal" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <!-- Preview content will be inserted here -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('previewModal')"><?php echo __('close'); ?></button>
            <button class="btn btn-primary" onclick="submitFromPreview()"><?php echo __('send_alert'); ?></button>
        </div>
    </div>
</div>

<!-- Edit Alert Modal -->
<div class="modal" id="editModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">edit</span> <?php echo __('edit_alert'); ?></h3>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editAlertForm">
            <input type="hidden" id="editAlertId" name="alert_id">
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo __('alert_title'); ?></label>
                    <input type="text" id="editTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label><?php echo __('alert_message'); ?></label>
                    <textarea id="editMessage" name="message" rows="4" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label><?php echo __('alert_type'); ?></label>
                        <select id="editType" name="alert_type">
                            <option value="weather"><?php echo __('weather'); ?></option>
                            <option value="disease"><?php echo __('disease'); ?></option>
                            <option value="pest"><?php echo __('pest'); ?></option>
                            <option value="market"><?php echo __('market'); ?></option>
                            <option value="government"><?php echo __('government'); ?></option>
                            <option value="general"><?php echo __('general'); ?></option>
                        </select>
                    </div>
                    <div class="form-group flex-1">
                        <label><?php echo __('severity'); ?></label>
                        <select id="editSeverity" name="priority">
                            <option value="low"><?php echo __('low'); ?></option>
                            <option value="medium"><?php echo __('medium'); ?></option>
                            <option value="high"><?php echo __('high'); ?></option>
                            <option value="critical"><?php echo __('critical'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
/* Issue Alert Page Styles */
.alert-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-icon .material-icons {
    font-size: 28px;
}

.stat-total .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-month .stat-icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-critical .stat-icon { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.stat-today .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.stat-info h3 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.stat-info p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Alert Type Buttons */
.alert-type-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.alert-type-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: 2px solid var(--border-color);
    border-radius: 25px;
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 500;
}

.alert-type-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.alert-type-btn.weather:hover, .alert-type-btn.weather.active { border-color: #17a2b8; background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.alert-type-btn.disease:hover, .alert-type-btn.disease.active { border-color: #dc3545; background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.alert-type-btn.pest:hover, .alert-type-btn.pest.active { border-color: #fd7e14; background: rgba(253, 126, 20, 0.1); color: #fd7e14; }
.alert-type-btn.market:hover, .alert-type-btn.market.active { border-color: #28a745; background: rgba(40, 167, 69, 0.1); color: #28a745; }
.alert-type-btn.government:hover, .alert-type-btn.government.active { border-color: #6f42c1; background: rgba(111, 66, 193, 0.1); color: #6f42c1; }
.alert-type-btn.general:hover, .alert-type-btn.general.active { border-color: #6c757d; background: rgba(108, 117, 125, 0.1); color: #6c757d; }

/* Create Alert Card */
.create-alert-card {
    border-top: 4px solid var(--primary-color);
}

.form-row {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #ccc;
    border-radius: 8px;
    background: #fff;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.15);
}

.form-group.flex-1 { flex: 1; min-width: 180px; }
.form-group.flex-2 { flex: 2; min-width: 280px; }

.form-options {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    padding: 1.25rem 1.5rem;
    background: var(--bg-hover);
    border-radius: 10px;
    margin-bottom: 1.25rem;
    border: 1px solid var(--border-color);
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    font-size: 0.95rem;
    color: var(--text-primary);
    padding: 0.5rem 0;
}

.checkbox-option input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary-color);
    cursor: pointer;
}

.checkbox-option .material-icons {
    font-size: 1.35rem;
    color: var(--primary-color);
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.form-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

.char-count {
    display: block;
    text-align: right;
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-top: 0.35rem;
}

/* Filter Bar */
.filter-card {
    padding: 1rem 1.5rem;
}

.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.filters {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.filter-group select {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid #ccc;
    background: #fff;
    min-width: 140px;
}

/* Global input/select styling */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="date"],
input[type="datetime-local"],
input[type="tel"],
input[type="url"],
select,
textarea {
    border: 2px solid #ccc !important;
    background: #fff !important;
}

/* Alert Items */
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.alert-item {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-left-width: 4px;
    border-radius: 10px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.alert-item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.alert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.alert-title-section {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-icon {
    font-size: 1.75rem;
}

.alert-title-info h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.alert-type-badge {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
}

.alert-message-text {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0.5rem 0;
}

.alert-item-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    margin-top: 0.75rem;
}

.alert-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.meta-item .material-icons {
    font-size: 1rem;
}

.alert-item-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-state .material-icons {
    font-size: 5rem;
    color: var(--text-muted);
    opacity: 0.4;
}

.empty-state h3 {
    margin: 1rem 0 0.5rem;
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-content {
    background: var(--bg-card, #ffffff);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

.modal-content.modal-lg {
    max-width: 700px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .form-group.flex-1,
    .form-group.flex-2 {
        min-width: 100%;
    }
    
    .alert-type-buttons {
        justify-content: center;
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filters {
        flex-direction: column;
    }
    
    .alert-item-footer {
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>

<script>
// Only define baseUrl if not already defined globally
var baseUrl = (typeof baseUrl !== 'undefined') ? baseUrl : '<?php echo $base_url; ?>';

// showNotification is now provided globally via footer.php

// Character counter
document.getElementById('alertMessage').addEventListener('input', function() {
    document.getElementById('messageCharCount').textContent = this.value.length;
});

// Select alert type from buttons
function selectAlertType(type) {
    document.getElementById('alertType').value = type;
    document.querySelectorAll('.alert-type-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.alert-type-btn.${type}`)?.classList.add('active');
}

// Filter functions
function applyFilters() {
    const type = document.getElementById('filterType').value;
    const severity = document.getElementById('filterSeverity').value;
    
    let url = baseUrl + 'issue-alert?';
    if (type !== 'all') url += 'type=' + type + '&';
    if (severity !== 'all') url += 'severity=' + severity + '&';
    
    window.location.href = url.replace(/&$/, '');
}

function clearFilters() {
    window.location.href = baseUrl + 'issue-alert';
}

// Form submission
document.getElementById('alertForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> <?php echo __('sending'); ?>...';
    
    const formData = new FormData(this);
    formData.append('action', 'create_alert');
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('alert_sent_success'); ?>', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="material-icons">send</span> <?php echo __('send_alert'); ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('<?php echo __('failed_send_alert'); ?>', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">send</span> <?php echo __('send_alert'); ?>';
    });
});

// Preview alert
function previewAlert() {
    const form = document.getElementById('alertForm');
    const title = form.title.value || '<?php echo __('no_title'); ?>';
    const type = form.alert_type.value || '<?php echo __('general'); ?>';
    const severity = form.severity.value || 'medium';
    const message = form.message.value || '<?php echo __('no_message'); ?>';
    
    const severityColors = {
        'low': '#28a745',
        'medium': '#ffc107',
        'high': '#fd7e14',
        'critical': '#dc3545'
    };
    
    const previewHtml = `
        <div class="alert-preview" style="border-left: 4px solid ${severityColors[severity]}; padding: 1rem; background: var(--bg-hover); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h4 style="margin: 0;">${title}</h4>
                <span class="badge badge-${severity === 'critical' ? 'danger' : (severity === 'high' ? 'warning' : 'info')}">${severity}</span>
            </div>
            <p style="color: var(--text-secondary); margin: 0.5rem 0;">${message}</p>
            <small style="color: var(--text-muted);"><?php echo __('type'); ?>: ${type}</small>
        </div>
    `;
    
    document.getElementById('previewContent').innerHTML = previewHtml;
    openModal('previewModal');
}

function submitFromPreview() {
    closeModal('previewModal');
    document.getElementById('alertForm').dispatchEvent(new Event('submit'));
}

// Edit alert
function editAlert(alertId) {
    fetch(baseUrl + 'ajax/officer.php?action=get_alert&alert_id=' + alertId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const alert = data.alert;
                document.getElementById('editAlertId').value = alert.alert_id;
                document.getElementById('editTitle').value = alert.title || '';
                document.getElementById('editMessage').value = alert.message || '';
                document.getElementById('editType').value = alert.alert_type || 'general';
                document.getElementById('editSeverity').value = alert.priority || 'medium';
                openModal('editModal');
            }
        })
        .catch(error => showNotification('<?php echo __('error_loading_alert'); ?>', 'error'));
}

// Edit form submission
document.getElementById('editAlertForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_alert');
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('alert_updated'); ?>', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
});

// Resend alert
function resendAlert(alertId) {
    if (!confirm('<?php echo __('confirm_resend_alert'); ?>')) return;
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=resend_alert&alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('alert_resent'); ?>', 'success');
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

// Delete alert
function deleteAlert(alertId) {
    if (!confirm('<?php echo __('confirm_delete_alert'); ?>')) return;
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_alert&alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-alert-id="${alertId}"]`).remove();
            showNotification('<?php echo __('alert_deleted'); ?>', 'success');
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

// ===== Pagination for Issued Alerts =====
const issuedAlertsPagination = {
    currentPage: 1,
    itemsPerPage: 10,
    totalItems: 0,
    totalPages: 0
};

document.addEventListener('DOMContentLoaded', function() {
    initIssuedAlertsPagination();
});

function initIssuedAlertsPagination() {
    const alertsList = document.querySelector('.alerts-list');
    if (!alertsList) return;
    
    const allAlerts = alertsList.querySelectorAll('.alert-item');
    issuedAlertsPagination.totalItems = allAlerts.length;
    issuedAlertsPagination.totalPages = Math.ceil(issuedAlertsPagination.totalItems / issuedAlertsPagination.itemsPerPage);
    
    const paginationContainer = document.getElementById('issuedAlertsPaginationContainer');
    if (paginationContainer && issuedAlertsPagination.totalPages > 1) {
        paginationContainer.style.display = 'flex';
        setupIssuedAlertsPaginationListeners();
        showIssuedAlertsPage(1);
    } else if (issuedAlertsPagination.totalItems > 0) {
        allAlerts.forEach(item => item.style.display = '');
    }
}

function setupIssuedAlertsPaginationListeners() {
    document.getElementById('issuedAlertsFirstPageBtn')?.addEventListener('click', () => showIssuedAlertsPage(1));
    document.getElementById('issuedAlertsPrevPageBtn')?.addEventListener('click', () => showIssuedAlertsPage(issuedAlertsPagination.currentPage - 1));
    document.getElementById('issuedAlertsNextPageBtn')?.addEventListener('click', () => showIssuedAlertsPage(issuedAlertsPagination.currentPage + 1));
    document.getElementById('issuedAlertsLastPageBtn')?.addEventListener('click', () => showIssuedAlertsPage(issuedAlertsPagination.totalPages));
}

function showIssuedAlertsPage(page) {
    page = Math.max(1, Math.min(page, issuedAlertsPagination.totalPages));
    issuedAlertsPagination.currentPage = page;
    
    const alertsList = document.querySelector('.alerts-list');
    const allAlerts = alertsList.querySelectorAll('.alert-item');
    
    const startIndex = (page - 1) * issuedAlertsPagination.itemsPerPage;
    const endIndex = startIndex + issuedAlertsPagination.itemsPerPage;
    
    allAlerts.forEach((item, index) => {
        item.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    updateIssuedAlertsPaginationControls();
}

function updateIssuedAlertsPaginationControls() {
    const { currentPage, totalPages, totalItems, itemsPerPage } = issuedAlertsPagination;
    
    document.getElementById('issuedAlertsFirstPageBtn').disabled = currentPage === 1;
    document.getElementById('issuedAlertsPrevPageBtn').disabled = currentPage === 1;
    document.getElementById('issuedAlertsNextPageBtn').disabled = currentPage === totalPages;
    document.getElementById('issuedAlertsLastPageBtn').disabled = currentPage === totalPages;
    
    document.getElementById('issuedAlertsPageInfo').textContent = `<?php echo __('page'); ?> ${currentPage} <?php echo __('of'); ?> ${totalPages}`;
    
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('issuedAlertsResultsInfo').textContent = `<?php echo __('showing'); ?> ${startItem}-${endItem} <?php echo __('of'); ?> ${totalItems}`;
    
    generateIssuedAlertsPageNumbers();
}

function generateIssuedAlertsPageNumbers() {
    const container = document.getElementById('issuedAlertsPageNumbers');
    const { currentPage, totalPages } = issuedAlertsPagination;
    container.innerHTML = '';
    
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        container.appendChild(createIssuedAlertsPageButton(1));
        if (startPage > 2) container.appendChild(createIssuedAlertsEllipsis());
    }
    
    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createIssuedAlertsPageButton(i));
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) container.appendChild(createIssuedAlertsEllipsis());
        container.appendChild(createIssuedAlertsPageButton(totalPages));
    }
}

function createIssuedAlertsPageButton(pageNum) {
    const btn = document.createElement('button');
    btn.className = 'page-number' + (pageNum === issuedAlertsPagination.currentPage ? ' active' : '');
    btn.textContent = pageNum;
    btn.addEventListener('click', () => showIssuedAlertsPage(pageNum));
    return btn;
}

function createIssuedAlertsEllipsis() {
    const span = document.createElement('span');
    span.className = 'page-ellipsis';
    span.textContent = '...';
    return span;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
