<?php
/**
 * Admin Settings
 * System configuration and preferences
 */
$currPage = "Settings";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layouts/admin-header.php';

// Get active section
$activeSection = $_GET['section'] ?? 'general';

// Load settings from database with all details
$settingsRows = $db->resultSet("SELECT setting_id, setting_key, setting_value, setting_type, setting_group, 
    description, is_sensitive, updated_by, updated_at FROM admin_settings ORDER BY setting_group, setting_key");

$settings = [];
$settingsByGroup = [];

foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
    if (!isset($settingsByGroup[$row['setting_group']])) {
        $settingsByGroup[$row['setting_group']] = [];
    }
    $settingsByGroup[$row['setting_group']][] = $row;
}

// Load system settings
$systemSettings = $db->single("SELECT * FROM system_settings WHERE id = 1");
if (!$systemSettings) {
    // Create default system settings if not exists
    $db->query("INSERT INTO system_settings (id, site_name, site_description, default_language, timezone, items_per_page) 
                VALUES (1, 'SmartChashi', 'Smart Agriculture Management System', 'en', 'Asia/Dhaka', 20)");
    $db->execute();
    $systemSettings = $db->single("SELECT * FROM system_settings WHERE id = 1");
}

// Load AI provider settings
$aiSettings = [];
$aiRows = $db->resultSet("SELECT setting_key, setting_value FROM admin_settings WHERE setting_group = 'ai'");
foreach ($aiRows as $row) {
    $aiSettings[$row['setting_key']] = $row['setting_value'];
}
$aiProvider    = $aiSettings['ai_provider']    ?? 'groq';
$aiModel       = $aiSettings['ai_model']       ?? 'llama-3.3-70b-versatile';
$aiTemperature = $aiSettings['ai_temperature'] ?? '0.7';
$aiMaxTokens   = $aiSettings['ai_max_tokens']  ?? '1024';
$aiSysPrompt   = $aiSettings['ai_system_prompt'] ?? '';

// Get last modified info
$lastModified = $db->single("SELECT u.first_name, u.last_name, s.updated_at 
    FROM admin_settings s 
    LEFT JOIN users u ON s.updated_by = u.user_id 
    ORDER BY s.updated_at DESC LIMIT 1");

$lastModifiedBy = !empty($lastModified['first_name'])
    ? $lastModified['first_name'] . ' ' . $lastModified['last_name']
    : 'System';
$lastModifiedTime = !empty($lastModified['updated_at'])
    ? date('M d, Y H:i', strtotime($lastModified['updated_at']))
    : 'Never';

// Load admin/moderator users for admin management section
$adminUsers = $db->resultSet(
    "SELECT user_id, first_name, last_name, email, role, profile_img_url, created_at,
            (SELECT MAX(created_at) FROM admin_activity_logs WHERE user_id = users.user_id) as last_active
     FROM users WHERE role IN ('admin','moderator') ORDER BY role, first_name"
) ?: [];
?>

<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle">Configure system preferences and security options</p>
        </div>
        <div class="page-meta">
            <span class="meta-item">
                <span class="material-icons">person</span>
                Last modified by: <strong><?php echo htmlspecialchars($lastModifiedBy); ?></strong>
            </span>
            <span class="meta-item">
                <span class="material-icons">schedule</span>
                <strong><?php echo $lastModifiedTime; ?></strong>
            </span>
        </div>
    </div>
</div>

<div class="settings-layout">
    <!-- Settings Navigation -->
    <nav class="settings-nav">
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=profile" class="settings-nav-item <?php echo $activeSection === 'profile' ? 'active' : ''; ?>">
            <span class="material-icons">person</span>
            My Profile
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=general" class="settings-nav-item <?php echo $activeSection === 'general' ? 'active' : ''; ?>">
            <span class="material-icons">settings</span>
            General
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=security" class="settings-nav-item <?php echo $activeSection === 'security' ? 'active' : ''; ?>">
            <span class="material-icons">security</span>
            Security
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=maintenance" class="settings-nav-item <?php echo $activeSection === 'maintenance' ? 'active' : ''; ?>">
            <span class="material-icons">engineering</span>
            Maintenance
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=api" class="settings-nav-item <?php echo $activeSection === 'api' ? 'active' : ''; ?>">
            <span class="material-icons">api</span>
            API Settings
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=ai" class="settings-nav-item <?php echo $activeSection === 'ai' ? 'active' : ''; ?>">
            <span class="material-icons">smart_toy</span>
            AI Settings
        </a>
        <a href="<?php echo $base_url; ?>?page=admin-settings&section=admins" class="settings-nav-item <?php echo $activeSection === 'admins' ? 'active' : ''; ?>">
            <span class="material-icons">admin_panel_settings</span>
            Admin Management
        </a>
    </nav>

    <!-- Settings Content -->
    <div class="settings-content">
        <!-- Profile Settings -->
        <div class="settings-section <?php echo $activeSection === 'profile' ? 'active' : ''; ?>" id="section-profile">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">person</span>
                        My Profile
                    </h3>
                </div>
                <div class="card-body">
                    <form id="profileForm" class="settings-form">
                        <div class="profile-section">
                            <div class="profile-avatar-section">
                                <div class="current-avatar">
                                    <?php if (!empty($adminUser['profile_img_url'])): ?>
                                        <img src="<?php echo $base_url . 'public/' . $adminUser['profile_img_url']; ?>" alt="Profile" id="profilePreview">
                                    <?php else: ?>
                                        <div class="avatar-placeholder" id="profilePreview">
                                            <?php echo strtoupper(substr($adminUser['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-actions">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('profileImageInput').click()">
                                        <span class="material-icons">upload</span>
                                        Upload Photo
                                    </button>
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;">
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="settings-subtitle">Personal Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($adminUser['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($adminUser['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($adminUser['email'] ?? ''); ?>" required>
                            <span class="form-hint">This will be your login email</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($adminUser['phone'] ?? ''); ?>">
                        </div>
                        
                        <h4 class="settings-subtitle">Change Password</h4>
                        <p class="form-hint">Leave blank to keep current password</p>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- General Settings -->
        <div class="settings-section <?php echo $activeSection === 'general' ? 'active' : ''; ?>" id="section-general">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">settings</span>
                        General Settings
                    </h3>
                </div>
                <div class="card-body">
                    <form id="generalForm" class="settings-form" data-settings-type="system">
                        <div class="form-group">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site_name" class="form-input" 
                                value="<?php echo htmlspecialchars($systemSettings['site_name'] ?? 'SmartChashi'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Site Description</label>
                            <textarea name="site_description" class="form-input" rows="3"><?php echo htmlspecialchars($systemSettings['site_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Default Language</label>
                                <select name="default_language" class="form-input">
                                    <option value="en" <?php echo ($systemSettings['default_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="bn" <?php echo ($systemSettings['default_language'] ?? 'en') === 'bn' ? 'selected' : ''; ?>>Bengali</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select name="timezone" class="form-input">
                                    <option value="Asia/Dhaka" <?php echo ($systemSettings['timezone'] ?? 'Asia/Dhaka') === 'Asia/Dhaka' ? 'selected' : ''; ?>>Asia/Dhaka</option>
                                    <option value="UTC" <?php echo ($systemSettings['timezone'] ?? 'Asia/Dhaka') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="Asia/Kolkata" <?php echo ($systemSettings['timezone'] ?? 'Asia/Dhaka') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Items Per Page</label>
                                <input type="number" name="items_per_page" class="form-input" 
                                    value="<?php echo $systemSettings['items_per_page'] ?? 20; ?>" min="10" max="100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Currency</label>
                                <input type="text" name="currency" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['currency'] ?? 'BDT'); ?>" maxlength="10">
                            </div>
                        </div>
                        
                        <h4 class="settings-subtitle">Contact Information</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" class="form-input" 
                                value="<?php echo htmlspecialchars($systemSettings['contact_email'] ?? ''); ?>" 
                                placeholder="info@smartchashi.com">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" name="contact_phone" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['contact_phone'] ?? ''); ?>" 
                                    placeholder="+880 1XXX-XXXXXX">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Address</label>
                                <input type="text" name="contact_address" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['contact_address'] ?? ''); ?>" 
                                    placeholder="Dhaka, Bangladesh">
                            </div>
                        </div>
                        
                        <h4 class="settings-subtitle">Social Media</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Facebook URL</label>
                                <input type="url" name="facebook_url" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['facebook_url'] ?? ''); ?>" 
                                    placeholder="https://facebook.com/yourpage">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Twitter URL</label>
                                <input type="url" name="twitter_url" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['twitter_url'] ?? ''); ?>" 
                                    placeholder="https://twitter.com/yourpage">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">YouTube URL</label>
                                <input type="url" name="youtube_url" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['youtube_url'] ?? ''); ?>" 
                                    placeholder="https://youtube.com/yourchannel">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Instagram URL</label>
                                <input type="url" name="instagram_url" class="form-input" 
                                    value="<?php echo htmlspecialchars($systemSettings['instagram_url'] ?? ''); ?>" 
                                    placeholder="https://instagram.com/yourpage">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="settings-section <?php echo $activeSection === 'security' ? 'active' : ''; ?>" id="section-security">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">security</span>
                        Security Settings
                    </h3>
                </div>
                <div class="card-body">
                    <form id="securityForm" class="settings-form">
                        <h4 class="settings-subtitle">Authentication</h4>
                        
                        <div class="form-group">
                            <label class="toggle-setting">
                                <input type="checkbox" name="require_2fa" value="1" <?php echo ($settings['require_2fa'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <strong>Require 2FA for Admin Login</strong>
                                    <span>All admins must verify with email code</span>
                                </span>
                            </label>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" class="form-input" value="<?php echo $settings['session_timeout'] ?? 30; ?>" min="5" max="1440">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Max Failed Logins</label>
                                <input type="number" name="max_failed_logins" class="form-input" value="<?php echo $settings['max_failed_logins'] ?? 5; ?>" min="3" max="20">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Lockout Duration (minutes)</label>
                            <input type="number" name="lockout_duration" class="form-input" value="<?php echo $settings['lockout_duration'] ?? 15; ?>" min="5" max="60">
                        </div>
                        
                        <h4 class="settings-subtitle">Password Policy</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Minimum Length</label>
                                <input type="number" name="password_min_length" class="form-input" value="<?php echo $settings['password_min_length'] ?? 8; ?>" min="6" max="32">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Expiry (days, 0=never)</label>
                                <input type="number" name="password_expiry_days" class="form-input" value="<?php echo $settings['password_expiry_days'] ?? 0; ?>" min="0" max="365">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-setting">
                                <input type="checkbox" name="require_mixed_case" value="1" <?php echo ($settings['require_mixed_case'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <strong>Require Mixed Case</strong>
                                    <span>Passwords must contain upper and lower case</span>
                                </span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-setting">
                                <input type="checkbox" name="require_numbers" value="1" <?php echo ($settings['require_numbers'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <strong>Require Numbers</strong>
                                    <span>Passwords must contain at least one number</span>
                                </span>
                            </label>
                        </div>
                        
                        <h4 class="settings-subtitle">Rate Limiting</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">API Rate Limit (requests/min)</label>
                                <input type="number" name="api_rate_limit" class="form-input" value="<?php echo $settings['api_rate_limit'] ?? 100; ?>" min="10" max="1000">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Login Rate Limit (attempts/min)</label>
                                <input type="number" name="login_rate_limit" class="form-input" value="<?php echo $settings['login_rate_limit'] ?? 10; ?>" min="3" max="30">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Security Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Maintenance Settings -->
        <div class="settings-section <?php echo $activeSection === 'maintenance' ? 'active' : ''; ?>" id="section-maintenance">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">engineering</span>
                        Maintenance Mode
                    </h3>
                </div>
                <div class="card-body">
                    <form id="maintenanceForm" class="settings-form">
                        <div class="maintenance-toggle">
                            <div class="maintenance-status <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'enabled' : ''; ?>">
                                <span class="material-icons"><?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'build' : 'check_circle'; ?></span>
                                <span><?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'Maintenance Mode Active' : 'Site is Online'; ?></span>
                            </div>
                            <label class="toggle-setting large">
                                <input type="checkbox" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Maintenance Message</label>
                            <textarea name="maintenance_message" class="form-input" rows="4"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back soon.'); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Allowed IPs (one per line)</label>
                            <textarea name="maintenance_allowed_ips" class="form-input" rows="4" placeholder="Enter IP addresses that can access the site during maintenance"><?php echo htmlspecialchars($settings['maintenance_allowed_ips'] ?? ''); ?></textarea>
                            <span class="form-hint">Your current IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Maintenance Settings</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">delete_sweep</span>
                        Data Cleanup
                    </h3>
                </div>
                <div class="card-body">
                    <div class="cleanup-actions">
                        <div class="cleanup-item">
                            <div class="cleanup-info">
                                <strong>Clear Cache</strong>
                                <span>Remove all cached data and temporary files</span>
                            </div>
                            <button class="btn btn-secondary" onclick="clearCache()">Clear Cache</button>
                        </div>
                        <div class="cleanup-item">
                            <div class="cleanup-info">
                                <strong>Clear Old Sessions</strong>
                                <span>Remove expired user sessions from database</span>
                            </div>
                            <button class="btn btn-secondary" onclick="clearSessions()">Clear Sessions</button>
                        </div>
                        <div class="cleanup-item">
                            <div class="cleanup-info">
                                <strong>Clear Old Logs</strong>
                                <span>Remove logs older than 90 days</span>
                            </div>
                            <button class="btn btn-secondary" onclick="clearLogs()">Clear Logs</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Provider Settings — redirect to dedicated page -->
        <div class="settings-section <?php echo $activeSection === 'ai' ? 'active' : ''; ?>" id="section-ai">
            <div class="card">
                <div class="card-body" style="text-align:center;padding:48px 24px">
                    <span class="material-icons" style="font-size:56px;color:var(--primary);display:block;margin-bottom:16px">smart_toy</span>
                    <h3 style="font-size:20px;font-weight:700;margin:0 0 8px">AI Settings have moved</h3>
                    <p style="color:var(--text-muted);margin:0 0 24px;max-width:420px;margin-left:auto;margin-right:auto">
                        All AI provider configuration, model selection, API keys, disease detection, usage stats and logs are now managed from the dedicated <strong>AI Management</strong> page.
                    </p>
                    <a href="<?php echo $base_url; ?>?page=admin-ai" class="btn btn-primary">
                        <span class="material-icons">open_in_new</span> Go to AI Management
                    </a>
                </div>
            </div>
        </div><!-- /section-ai -->

        <!-- API Settings -->
        <div class="settings-section <?php echo $activeSection === 'api' ? 'active' : ''; ?>" id="section-api">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons">api</span>
                        API Configuration
                    </h3>
                </div>
                <div class="card-body">
                    <form id="apiForm" class="settings-form" data-settings-type="system">
                        <div class="form-group">
                            <label class="form-label">
                                <span class="material-icons">smart_toy</span>
                                AI Agent API URL
                            </label>
                            <input type="url" name="agent_api_url" class="form-input"
                                value="<?php echo htmlspecialchars($systemSettings['agent_api_url'] ?? ''); ?>"
                                placeholder="https://api.example.com/agent">
                            <span class="form-hint">External proxy endpoint for the Chashi Bhai chatbot (if used)</span>
                        </div>
                        <div class="alert-bar info" style="margin-bottom:16px">
                            <span class="material-icons">info</span>
                            Disease Detection API URL is now managed from
                            <a href="<?php echo $base_url; ?>?page=admin-ai" style="font-weight:600">AI Management → Disease Detection</a>.
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save API Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Admin Management Section -->
        <div class="settings-section <?php echo $activeSection === 'admins' ? 'active' : ''; ?>" id="section-admins">

            <!-- Add New Admin/Moderator -->
            <div class="card" style="margin-bottom:24px">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">person_add</span> Add New Admin / Moderator</h3>
                </div>
                <div class="card-body">
                    <form id="addAdminForm" class="settings-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="first_name" class="form-input" required placeholder="John">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" placeholder="Doe">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-input" required placeholder="admin@smartchashi.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-input" placeholder="+880 1XXX-XXXXXX">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Role <span style="color:var(--danger)">*</span></label>
                                <select name="role" class="form-input" required>
                                    <option value="admin">Admin</option>
                                    <option value="moderator">Moderator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span style="color:var(--danger)">*</span></label>
                                <input type="password" name="password" class="form-input" required placeholder="Min. 8 characters">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><span class="material-icons">person_add</span> Create Account</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Manage Admins/Moderators -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">manage_accounts</span> Admin &amp; Moderator Accounts</h3>
                </div>
                <div class="card-body" style="padding:0">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Last Active</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminUsers as $au): ?>
                                <tr data-uid="<?php echo $au['user_id']; ?>">
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <?php if (!empty($au['profile_img_url'])): ?>
                                                <img src="<?php echo $base_url.'public/'.$au['profile_img_url']; ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                                            <?php else: ?>
                                                <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px">
                                                    <?php echo strtoupper(substr($au['first_name'],0,1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:600"><?php echo htmlspecialchars($au['first_name'].' '.$au['last_name']); ?></div>
                                                <?php if ($au['user_id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge badge-primary" style="font-size:10px">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($au['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $au['role']==='admin' ? 'badge-danger' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($au['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($au['last_active']) ? date('M d, Y H:i', strtotime($au['last_active'])) : 'Never'; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($au['created_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-icon btn-primary edit-admin-btn" title="Edit"
                                                data-uid="<?php echo $au['user_id']; ?>"
                                                data-first="<?php echo htmlspecialchars($au['first_name']); ?>"
                                                data-last="<?php echo htmlspecialchars($au['last_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($au['email']); ?>"
                                                data-role="<?php echo $au['role']; ?>">
                                                <span class="material-icons">edit</span>
                                            </button>
                                            <?php if ($au['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn-icon btn-danger delete-admin-btn" title="Remove"
                                                data-uid="<?php echo $au['user_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($au['first_name'].' '.$au['last_name']); ?>">
                                                <span class="material-icons">delete</span>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminUsers)): ?>
                                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No admin accounts found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div><!-- /section-admins -->

    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal-overlay" id="editAdminModal" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3><span class="material-icons">edit</span> Edit Admin Account</h3>
            <button class="modal-close" id="editAdminClose"><span class="material-icons">close</span></button>
        </div>
        <div class="modal-body">
            <form id="editAdminForm">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editRole" class="form-input">
                        <option value="admin">Admin</option>
                        <option value="moderator">Moderator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span class="form-hint" style="display:inline">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" class="form-input" placeholder="New password…">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="editAdminClose2">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header {
    margin-bottom: 30px;
}

.page-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.page-subtitle {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
}

.page-meta {
    display: flex;
    gap: 20px;
    font-size: 13px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
}

.meta-item .material-icons {
    font-size: 16px;
}

.meta-item strong {
    color: var(--text-primary);
}

/* Profile Section */
.profile-section {
    margin-bottom: 30px;
}

.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 24px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
    margin-bottom: 30px;
}

.current-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid var(--border);
}

.current-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    color: white;
    font-size: 48px;
    font-weight: 600;
}

.avatar-actions {
    flex: 1;
}

.avatar-actions p {
    margin: 0 0 16px 0;
    color: var(--text-muted);
    font-size: 14px;
}

/* Settings Layout */
.settings-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 24px;
}

.settings-nav {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    padding: 8px;
    height: fit-content;
    position: sticky;
    top: 88px;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--border-radius);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}

.settings-nav-item:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.settings-nav-item.active {
    background: var(--primary);
    color: white;
}

.settings-nav-item .material-icons {
    font-size: 20px;
}

/* Settings Content */
.settings-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.settings-section {
    display: none;
}

.settings-section.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.settings-section .card {
    margin-bottom: 20px;
}

/* Cards */
.card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.card-header {
    padding: 20px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-title .material-icons {
    font-size: 20px;
    color: var(--primary);
}

.card-body {
    padding: 20px;
}

.card-body.no-padding {
    padding: 0;
}

/* Form Styles */
.settings-form {
    max-width: 100%;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    color: var(--text-primary);
    font-family: inherit;
    transition: all 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input::placeholder {
    color: var(--text-muted);
}

textarea.form-input {
    resize: vertical;
    min-height: 100px;
}

.form-hint {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 6px;
}

/* Input Group for API keys */
.input-group {
    display: flex;
    gap: 8px;
}

.input-group .form-input {
    flex: 1;
}

.input-group .btn {
    flex-shrink: 0;
    padding: 0 12px;
}

.form-label .material-icons {
    font-size: 18px;
    vertical-align: middle;
    margin-right: 6px;
    color: var(--primary);
}

/* Settings Subtitle */
.settings-subtitle {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 24px 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

.settings-subtitle:first-child {
    margin-top: 0;
}

/* Toggle Setting */
.toggle-setting {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
    transition: all 0.2s;
}

.toggle-setting:hover {
    background: var(--bg-hover);
}

.toggle-setting input {
    display: none;
}

.toggle-setting .toggle-slider {
    position: relative;
    width: 48px;
    height: 26px;
    background: var(--border);
    border-radius: 26px;
    transition: all 0.3s;
    flex-shrink: 0;
}

.toggle-setting .toggle-slider::before {
    content: '';
    position: absolute;
    width: 22px;
    height: 22px;
    left: 2px;
    top: 2px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s;
}

.toggle-setting input:checked + .toggle-slider {
    background: var(--secondary);
}

.toggle-setting input:checked + .toggle-slider::before {
    transform: translateX(22px);
}

.toggle-label {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.toggle-label strong {
    font-size: 14px;
    color: var(--text-primary);
}

.toggle-label span {
    font-size: 12px;
    color: var(--text-muted);
}

/* Large Toggle */
.toggle-setting.large .toggle-slider {
    width: 60px;
    height: 32px;
}

.toggle-setting.large .toggle-slider::before {
    width: 28px;
    height: 28px;
}

.toggle-setting.large input:checked + .toggle-slider::before {
    transform: translateX(28px);
}

/* Maintenance Section */
.maintenance-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
    margin-bottom: 24px;
    gap: 20px;
}

.maintenance-status {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    font-weight: 600;
    color: var(--secondary);
}

.maintenance-status .material-icons {
    font-size: 28px;
}

.maintenance-status.enabled {
    color: var(--warning);
}

/* Cleanup Actions */
.cleanup-actions {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cleanup-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
    gap: 16px;
}

.cleanup-info {
    flex: 1;
}

.cleanup-info strong {
    display: block;
    font-size: 14px;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.cleanup-info span {
    font-size: 12px;
    color: var(--text-muted);
}

/* Input Group */
.input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.input-group .form-input {
    flex: 1;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn .material-icons {
    font-size: 18px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    background: var(--bg-tertiary);
    color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-warning {
    background: var(--warning);
    color: #000;
    font-weight: 600;
}

.btn-warning:hover {
    background: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

/* Responsive */
@media (max-width: 1024px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }
    
    .settings-nav {
        position: static;
        display: flex;
        overflow-x: auto;
        gap: 8px;
    }
    
    .settings-nav-item {
        white-space: nowrap;
    }
}

@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-meta {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .settings-layout {
        gap: 16px;
    }
    
    .settings-nav {
        padding: 8px 0;
        display: flex;
        border: none;
        border-bottom: 1px solid var(--border);
        background: transparent;
    }
    
    .settings-nav-item {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .maintenance-toggle {
        flex-direction: column;
        text-align: center;
    }
    
    .cleanup-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}

/* ── AI Provider Cards ─────────────────────────────────────────────── */
.ai-provider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.ai-provider-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border-radius: 10px;
    border: 2px solid var(--border); background: var(--bg-tertiary);
    cursor: pointer; transition: all 0.2s; position: relative;
}
.ai-provider-card:hover { border-color: var(--primary); background: var(--bg-hover); }
.ai-provider-card.selected { border-color: var(--primary); background: rgba(99,102,241,0.08); }
.ai-provider-icon { font-size: 24px; flex-shrink: 0; }
.ai-provider-info { flex: 1; min-width: 0; }
.ai-provider-info strong { display: block; font-size: 13px; color: var(--text-primary); }
.ai-provider-info span { font-size: 11px; color: var(--text-muted); }
.ai-provider-check { font-size: 18px; color: var(--primary); display: none; }
.ai-provider-card.selected .ai-provider-check { display: block; }
.ai-test-result {
    margin-top: 16px; padding: 16px; border-radius: 8px;
    background: var(--bg-tertiary); border: 1px solid var(--border);
    font-size: 13px; line-height: 1.6; white-space: pre-wrap;
    max-height: 300px; overflow-y: auto;
}
.ai-test-result.error { border-color: var(--danger); color: var(--danger); }

@media (max-width: 480px) {
    .page-title {
        font-size: 20px;
    }
    
    .page-meta {
        gap: 12px;
        font-size: 12px;
    }
    
    .meta-item .material-icons {
        font-size: 14px;
    }
    
    .card-header {
        padding: 16px;
    }
    
    .card-body {
        padding: 16px;
    }
    
    .toggle-setting {
        padding: 12px;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
}

/* Modal styles */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 9990;
    display: flex; align-items: center; justify-content: center;
}
.modal-box {
    background: var(--bg-card); border-radius: var(--border-radius-lg);
    border: 1px solid var(--border); width: 100%; max-width: 520px;
    box-shadow: var(--shadow-xl); overflow: hidden;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 24px; border-bottom: 1px solid var(--border);
}
.modal-header h3 { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 600; margin: 0; }
.modal-body { padding: 24px; }
.modal-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; }
.modal-close:hover { color: var(--text-primary); }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px;
    border-radius: 8px; border: none; cursor: pointer; }
.btn-icon .material-icons { font-size: 16px; }
.btn-icon.btn-primary { background: rgba(99,102,241,.12); color: var(--primary); }
.btn-icon.btn-primary:hover { background: var(--primary); color: #fff; }
.btn-icon.btn-danger { background: rgba(239,68,68,.12); color: var(--danger); }
.btn-icon.btn-danger:hover { background: var(--danger); color: #fff; }
.table-actions { display: flex; gap: 6px; }
</style>

<script>
// Settings API Configuration
const SETTINGS_API = (window.BASE_URL || '<?php echo $base_url; ?>') + 'admin-secure/ajax/settings.php';

function getBaseUrl() {
    return window.BASE_URL || '<?php echo $base_url; ?>';
}

function getCsrfToken() {
    return window.CSRF_TOKEN || '<?php echo $csrf_token; ?>';
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'info') {
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}

/**
 * Save settings from admin_settings table (key-value pairs)
 */
async function saveSettings(form) {
    const formData = new FormData(form);
    const settings = {};
    
    form.querySelectorAll('input, select, textarea').forEach(input => {
        if (!input.name) return;
        if (input.type === 'checkbox') {
            settings[input.name] = input.checked ? '1' : '0';
        } else if (input.type !== 'button' && input.type !== 'submit') {
            settings[input.name] = input.value;
        }
    });
    
    try {
        const response = await fetch(SETTINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update_settings',
                csrf_token: getCsrfToken(),
                settings: JSON.stringify(settings)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Settings saved successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to save settings', 'error');
        }
        return data.success;
    } catch (error) {
        console.error('Save error:', error);
        showNotification('Error saving settings', 'error');
        return false;
    }
}

/**
 * Save system settings from system_settings table
 */
async function saveSystemSettings(form) {
    const formData = new FormData(form);
    formData.append('action', 'update_system_settings');
    formData.append('csrf_token', getCsrfToken());
    
    try {
        const response = await fetch(SETTINGS_API, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Settings saved successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to save settings', 'error');
        }
        return data.success;
    } catch (error) {
        console.error('Save error:', error);
        showNotification('Error saving settings', 'error');
        return false;
    }
}

/**
 * Test API connection
 */
async function testApiConnection(type) {
    showNotification('Testing API connection...', 'info');
    
    try {
        const response = await fetch(SETTINGS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'test_api_connection',
                csrf_token: getCsrfToken(),
                api_type: type
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`${type === 'agent' ? 'Agent' : 'Disease Detection'} API connection successful!`, 'success');
        } else {
            showNotification(data.message || 'API connection failed', 'error');
        }
    } catch (error) {
        console.error('API test error:', error);
        showNotification('Error testing API connection', 'error');
    }
}

/**
 * Clear cache
 */
async function clearCache() {
    if (!confirm('Clear all cached data?')) return;
    
    showNotification('Clearing cache...', 'info');
    
    try {
        const response = await fetch(SETTINGS_API + '?action=clear_cache&csrf_token=' + getCsrfToken());
        const data = await response.json();
        showNotification(data.message, data.success ? 'success' : 'error');
    } catch (error) {
        showNotification('Error clearing cache', 'error');
    }
}

/**
 * Clear old sessions
 */
async function clearSessions() {
    if (!confirm('Remove all expired sessions?')) return;
    
    showNotification('Clearing sessions...', 'info');
    
    try {
        const response = await fetch(SETTINGS_API + '?action=clear_sessions&csrf_token=' + getCsrfToken());
        const data = await response.json();
        showNotification(data.message, data.success ? 'success' : 'error');
    } catch (error) {
        showNotification('Error clearing sessions', 'error');
    }
}

/**
 * Clear old logs
 */
async function clearLogs() {
    if (!confirm('Remove logs older than 90 days? This cannot be undone.')) return;
    
    showNotification('Clearing logs...', 'info');
    
    try {
        const response = await fetch(SETTINGS_API + '?action=clear_logs&csrf_token=' + getCsrfToken());
        const data = await response.json();
        showNotification(data.message, data.success ? 'success' : 'error');
    } catch (error) {
        showNotification('Error clearing logs', 'error');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Settings page initialized');
    
    // Load current settings into forms
    const settings = <?php echo json_encode($settings); ?>;
    for (const [key, value] of Object.entries(settings)) {
        const input = document.querySelector(`[name="${key}"]`);
        if (!input) continue;
        if (input.type === 'checkbox') {
            input.checked = value === '1' || value === 'true';
        } else {
            input.value = value;
        }
    }
    
    // Profile image preview
    const profileImageInput = document.getElementById('profileImageInput');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        preview.outerHTML = '<img src="' + e.target.result + '" alt="Profile" id="profilePreview">';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Profile form
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_profile');
            formData.append('csrf_token', getCsrfToken());
            
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');
            
            if (newPassword || confirmPassword) {
                if (newPassword !== confirmPassword) {
                    showNotification('Passwords do not match', 'error');
                    return;
                }
                if (newPassword.length < 6) {
                    showNotification('Password must be at least 6 characters', 'error');
                    return;
                }
                if (!formData.get('current_password')) {
                    showNotification('Current password is required', 'error');
                    return;
                }
            }
            
            try {
                const response = await fetch(getBaseUrl() + 'admin-secure/ajax/admin.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Profile updated successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                showNotification('Error updating profile', 'error');
            }
        });
    }
    
    // General settings form (uses system_settings table)
    const generalForm = document.getElementById('generalForm');
    if (generalForm) {
        generalForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await saveSystemSettings(this);
        });
    }
    
    // Security settings form (uses admin_settings table)
    const securityForm = document.getElementById('securityForm');
    if (securityForm) {
        securityForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await saveSettings(this);
        });
    }
    
    // Maintenance settings form (uses admin_settings table)
    const maintenanceForm = document.getElementById('maintenanceForm');
    if (maintenanceForm) {
        maintenanceForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await saveSettings(this);
        });
    }
    
    // API settings form (uses system_settings table)
    const apiForm = document.getElementById('apiForm');
    if (apiForm) {
        apiForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await saveSystemSettings(this);
        });
    }
    
    // ── AI Settings ─────────────────────────────────────────────────
    const AI_PROVIDERS = {
        groq:       { label:'Groq',       keyUrl:'https://console.groq.com',            keyHint:'Free API key from console.groq.com',
                      models:['llama-3.3-70b-versatile','llama-3.1-8b-instant','llama-3.1-70b-versatile','mixtral-8x7b-32768','gemma2-9b-it'] },
        openai:     { label:'OpenAI',     keyUrl:'https://platform.openai.com/api-keys', keyHint:'API key from platform.openai.com',
                      models:['gpt-4o','gpt-4o-mini','gpt-4-turbo','gpt-3.5-turbo'] },
        gemini:     { label:'Gemini',     keyUrl:'https://aistudio.google.com/apikey',   keyHint:'Free key from Google AI Studio',
                      models:['gemini-2.0-flash','gemini-1.5-flash','gemini-1.5-pro'] },
        claude:     { label:'Claude',     keyUrl:'https://console.anthropic.com',        keyHint:'API key from console.anthropic.com',
                      models:['claude-opus-4-7','claude-sonnet-4-6','claude-haiku-4-5-20251001'] },
        deepseek:   { label:'DeepSeek',   keyUrl:'https://platform.deepseek.com/api_keys', keyHint:'API key from platform.deepseek.com',
                      models:['deepseek-chat','deepseek-reasoner'] },
        openrouter: { label:'OpenRouter', keyUrl:'https://openrouter.ai/keys',           keyHint:'API key from openrouter.ai',
                      models:['openai/gpt-4o','anthropic/claude-3-5-sonnet','meta-llama/llama-3.1-8b-instruct:free','google/gemini-2.0-flash-exp:free'] },
    };
    const savedAiProvider = <?php echo json_encode($aiProvider); ?>;
    const savedAiModel    = <?php echo json_encode($aiModel); ?>;
    let currentAiProvider = savedAiProvider;

    function selectProvider(key) {
        currentAiProvider = key;
        document.getElementById('aiProviderInput').value = key;
        document.querySelectorAll('.ai-provider-card').forEach(c => c.classList.toggle('selected', c.dataset.key === key));
        const p = AI_PROVIDERS[key];
        if (p) {
            document.getElementById('aiProviderLabel').textContent = p.label;
            document.getElementById('aiKeyHint').innerHTML = 'Get your key at: <a href="https://' + p.keyUrl.replace('https://','') + '" target="_blank" class="link-primary" rel="noopener">' + p.keyUrl + '</a>';
            // Populate model select
            const sel = document.getElementById('aiModelSelect');
            sel.innerHTML = '';
            p.models.forEach(m => sel.appendChild(new Option(m, m)));
            // Restore saved model if it matches this provider
            if (key === savedAiProvider && savedAiModel && p.models.includes(savedAiModel)) sel.value = savedAiModel;
        }
        // Load saved key for this provider via AJAX
        loadCurrentAiKey(key);
    }

    async function loadCurrentAiKey(provider) {
        const key = provider || currentAiProvider;
        try {
            const res = await fetch(SETTINGS_API, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action:'get_ai_key', csrf_token:getCsrfToken(), provider:key })
            });
            const data = await res.json();
            if (data.success && data.key) {
                document.getElementById('aiApiKeyInput').value = data.key;
            } else {
                document.getElementById('aiApiKeyInput').value = '';
            }
        } catch(e) {}
    }

    function toggleAiKeyVis() {
        const inp = document.getElementById('aiApiKeyInput');
        const icon = document.getElementById('aiKeyVisIcon');
        if (inp.type === 'password') { inp.type = 'text'; icon.textContent = 'visibility_off'; }
        else { inp.type = 'password'; icon.textContent = 'visibility'; }
    }

    async function runAiTest() {
        const prompt = document.getElementById('aiTestPrompt').value.trim();
        if (!prompt) { showNotification('Please enter a test prompt', 'error'); return; }
        const result = document.getElementById('aiTestResult');
        const btn    = document.getElementById('aiTestBtn');
        result.style.display = 'block';
        result.className = 'ai-test-result';
        result.textContent = 'Sending test request…';
        btn.disabled = true;
        try {
            const res = await fetch(SETTINGS_API, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'test_ai',
                    csrf_token: getCsrfToken(),
                    provider: currentAiProvider,
                    model: document.getElementById('aiModelSelect').value,
                    prompt: prompt
                })
            });
            const data = await res.json();
            if (data.success) {
                result.textContent = data.reply;
            } else {
                result.className = 'ai-test-result error';
                result.textContent = '⚠ ' + (data.message || 'Test failed');
            }
        } catch(e) {
            result.className = 'ai-test-result error';
            result.textContent = '⚠ Network error: ' + e.message;
        }
        btn.disabled = false;
    }

    // Wire up provider card data attributes and form submit
    document.querySelectorAll('.ai-provider-card').forEach(card => {
        const key = card.querySelector('.ai-provider-info strong')?.textContent.toLowerCase();
        // Map label back to key
        const keyMap = {groq:'groq',openai:'openai',gemini:'gemini',claude:'claude',deepseek:'deepseek',openrouter:'openrouter'};
        const pKey = Object.keys(keyMap).find(k => AI_PROVIDERS[k]?.label.toLowerCase() === key);
        if (pKey) card.dataset.key = pKey;
    });

    // Init AI section
    selectProvider(savedAiProvider);

    const aiForm = document.getElementById('aiSettingsForm');
    if (aiForm) {
        aiForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const body = new URLSearchParams({
                action: 'update_ai_settings',
                csrf_token: getCsrfToken(),
                ai_provider: document.getElementById('aiProviderInput').value,
                ai_api_key: document.getElementById('aiApiKeyInput').value,
                ai_model: document.getElementById('aiModelSelect').value,
                ai_temperature: document.querySelector('[name="ai_temperature"]').value,
                ai_max_tokens: document.querySelector('[name="ai_max_tokens"]').value,
                ai_system_prompt: document.querySelector('[name="ai_system_prompt"]').value,
            });
            try {
                const res = await fetch(SETTINGS_API, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
                const data = await res.json();
                showNotification(data.message || (data.success ? 'AI settings saved!' : 'Failed'), data.success ? 'success' : 'error');
            } catch(e) { showNotification('Error saving AI settings', 'error'); }
        });
    }

    // Maintenance status toggle visual update
    const maintenanceToggle = document.querySelector('[name="maintenance_mode"]');
    if (maintenanceToggle) {
        maintenanceToggle.addEventListener('change', function() {
            const statusDiv = document.querySelector('.maintenance-status');
            const icon = statusDiv?.querySelector('.material-icons');
            const text = statusDiv?.querySelector('span:last-child');
            
            if (this.checked) {
                statusDiv?.classList.add('enabled');
                if (icon) icon.textContent = 'build';
                if (text) text.textContent = 'Maintenance Mode Active';
            } else {
                statusDiv?.classList.remove('enabled');
                if (icon) icon.textContent = 'check_circle';
                if (text) text.textContent = 'Site is Online';
            }
        });
    }

    // ─── Admin Management ─────────────────────────────────────────────────────
    const ADMIN_API = (window.BASE_URL || '<?php echo $base_url; ?>') + 'admin-secure/ajax/admin.php';

    // Add new admin/moderator
    const addAdminForm = document.getElementById('addAdminForm');
    if (addAdminForm) {
        addAdminForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'create_user');
            fd.append('csrf_token', getCsrfToken());
            try {
                const res = await fetch(ADMIN_API, { method: 'POST', body: fd });
                const data = await res.json();
                showNotification(data.message || (data.success ? 'Account created!' : 'Failed'), data.success ? 'success' : 'error');
                if (data.success) { this.reset(); setTimeout(() => location.reload(), 1200); }
            } catch(err) { showNotification('Error creating account', 'error'); }
        });
    }

    // Edit admin modal
    const editModal   = document.getElementById('editAdminModal');
    const editForm    = document.getElementById('editAdminForm');
    const closeModal  = () => { if (editModal) editModal.style.display = 'none'; };

    document.querySelectorAll('.edit-admin-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editUserId').value   = btn.dataset.uid;
            document.getElementById('editFirstName').value = btn.dataset.first;
            document.getElementById('editLastName').value  = btn.dataset.last;
            document.getElementById('editEmail').value     = btn.dataset.email;
            document.getElementById('editRole').value      = btn.dataset.role;
            editModal.style.display = 'flex';
        });
    });

    document.getElementById('editAdminClose')?.addEventListener('click', closeModal);
    document.getElementById('editAdminClose2')?.addEventListener('click', closeModal);
    editModal?.addEventListener('click', e => { if (e.target === editModal) closeModal(); });

    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'update_user');
            fd.append('csrf_token', getCsrfToken());
            try {
                const res = await fetch(ADMIN_API, { method: 'POST', body: fd });
                const data = await res.json();
                showNotification(data.message || (data.success ? 'Account updated!' : 'Failed'), data.success ? 'success' : 'error');
                if (data.success) { closeModal(); setTimeout(() => location.reload(), 1200); }
            } catch(err) { showNotification('Error updating account', 'error'); }
        });
    }

    // Delete admin/moderator
    document.querySelectorAll('.delete-admin-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Remove "${btn.dataset.name}" from admin panel? This cannot be undone.`)) return;
            const fd = new FormData();
            fd.append('action', 'delete_user');
            fd.append('user_id', btn.dataset.uid);
            fd.append('csrf_token', getCsrfToken());
            try {
                const res = await fetch(ADMIN_API, { method: 'POST', body: fd });
                const data = await res.json();
                showNotification(data.message || (data.success ? 'Account removed' : 'Failed'), data.success ? 'success' : 'error');
                if (data.success) { btn.closest('tr').remove(); }
            } catch(err) { showNotification('Error deleting account', 'error'); }
        });
    });
});
</script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
