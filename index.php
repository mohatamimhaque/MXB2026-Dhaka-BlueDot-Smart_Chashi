<?php
/**
 * SmartCashi - Agricultural Management System
 * Main Router / Entry Point
 */

include __DIR__ . '/config/config.php';

$page = $_GET['page'] ?? 'home';
$id = $_GET['id'] ?? null;

// Sanitize page input
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

// ── Maintenance mode enforcement ────────────────────────────────────────────
// Admin pages, login, and logout are always accessible.
// All other pages are blocked when maintenance_mode = '1' in admin_settings,
// unless the visitor's IP is in the allowed-IPs list.
try {
    $_mDb = new Database();
    $_mRow = $_mDb->single("SELECT setting_value FROM admin_settings WHERE setting_key = ?", ['maintenance_mode']);
    if ($_mRow && $_mRow['setting_value'] === '1') {
        $_allowedIpsRow = $_mDb->single("SELECT setting_value FROM admin_settings WHERE setting_key = ?", ['maintenance_allowed_ips']);
        $_allowedIps = array_filter(array_map('trim', explode("\n", $_allowedIpsRow['setting_value'] ?? '')));
        $_isAdminPage = (strncmp($page, 'admin', 5) === 0);
        $_isPublicPage = in_array($page, ['login', 'admin-login', 'logout']);
        if (!$_isAdminPage && !$_isPublicPage && !in_array($_SERVER['REMOTE_ADDR'], $_allowedIps)) {
            $_mMsgRow = $_mDb->single("SELECT setting_value FROM admin_settings WHERE setting_key = ?", ['maintenance_message']);
            $_mMsg = htmlspecialchars($_mMsgRow['setting_value'] ?? 'We are currently performing scheduled maintenance. Please check back soon.');
            http_response_code(503);
            include __DIR__ . '/pages/maintenance.php';
            exit;
        }
    }
} catch (Exception $_mEx) {
    // DB not ready yet — proceed normally, don't block the site
}
unset($_mDb, $_mRow, $_allowedIpsRow, $_allowedIps, $_isAdminPage, $_isPublicPage, $_mMsgRow, $_mMsg, $_mEx);
// ─────────────────────────────────────────────────────────────────────────────

// Map pages to files
$pages = [
    // Public pages
    'home' => 'pages/home.php',
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'logout' => 'pages/logout.php',
    
    // Admin login (public but separate)
    'admin-login' => 'admin-secure/pages/admin-login.php',
    
    // Authenticated user pages - role-specific dashboards
    'dashboard' => 'pages/farmer-dashboard.php',
    'farmer-dashboard' => 'pages/farmer-dashboard.php',
    'profile' => 'pages/profile.php',
    'crops' => 'pages/crops.php',
    'disease' => 'pages/disease.php',
    'weather' => 'pages/weather.php',
    'marketplace' => 'pages/marketplace.php',
    'community' => 'pages/community.php',
    'alerts' => 'pages/alerts.php',
    'create-report' => 'pages/create-report.php',
    'my-reports' => 'pages/my-reports.php',
    'agent' => 'agent/index.php',
    
    // Profile views
    'farmer-profile-view' => 'pages/farmer-profile-view.php',
    'officer-profile-view' => 'pages/officer-profile-view.php',
    
    // Learning Center
    'learn'         => 'pages/learn.php',
    'learn-view'    => 'pages/learn-view.php',
    'officer-learn' => 'pages/officer-learn.php',

    // Officer pages
    'officer-dashboard' => 'pages/officer-dashboard.php',
    'farmer-reports' => 'pages/farmer-reports.php',
    'issue-alert' => 'pages/issue-alert.php',
    'advisory' => 'pages/advisory.php',
    'farmer-messages' => 'pages/farmer-messages.php',
    
    // Admin pages (moved to secure folder)
    'admin-dashboard' => 'admin-secure/pages/admin-dashboard.php',
    'admin-users' => 'admin-secure/pages/admin-users.php',
    'admin-security' => 'admin-secure/pages/admin-security.php',
    'admin-monitoring' => 'admin-secure/pages/admin-monitoring.php',
    'admin-analytics' => 'admin-secure/pages/admin-analytics.php',
    'admin-content' => 'admin-secure/pages/admin-content.php',
    'admin-reports' => 'admin-secure/pages/admin-reports.php',
    'admin-backup' => 'admin-secure/pages/admin-backup.php',
    'admin-settings'      => 'admin-secure/pages/admin-settings.php',
    'admin-ai'            => 'admin-secure/pages/admin-ai.php',
    'admin-learning'      => 'admin-secure/pages/admin-learning.php',
    'admin-notifications' => 'admin-secure/pages/admin-notifications.php',
    'admin-login' => 'admin-secure/pages/admin-login.php',
    
    // Legacy admin routes (redirect to new)
    'user-management' => 'admin-secure/pages/admin-users.php',
    'system-settings' => 'admin-secure/pages/admin-settings.php',
    'analytics' => 'admin-secure/pages/admin-analytics.php',
];

// Pages that require authentication
$protected_pages = [
    'dashboard', 'profile', 'crops', 'disease', 'weather',
    'marketplace', 'community', 'alerts', 'create-report', 'my-reports',
    'farmer-profile-view', 'learn', 'learn-view', 'officer-learn',
    'officer-profile-view', 'officer-dashboard', 'farmer-reports',
    'issue-alert', 'advisory', 'admin-dashboard', 'admin-users',
    'admin-security', 'admin-monitoring', 'admin-analytics',
    'admin-content', 'admin-reports', 'admin-backup', 'admin-settings', 'admin-ai',
    'admin-learning', 'admin-notifications', 'user-management', 'system-settings', 'analytics'
];

// Pages that require officer role
$officer_pages = [
    'officer-dashboard', 'farmer-reports', 'issue-alert', 'advisory', 'officer-learn'
];

// Pages that require admin role
$admin_pages = [
    'admin-dashboard', 'admin-users', 'admin-security', 'admin-monitoring',
    'admin-analytics', 'admin-content', 'admin-reports', 'admin-backup',
    'admin-settings', 'admin-ai', 'admin-learning', 'admin-notifications',
    'user-management', 'system-settings', 'analytics'
];

// Check if page exists in routes
if (!isset($pages[$page])) {
    include __DIR__ . '/404.php';
    exit;
}

// Authentication checks
if (in_array($page, $protected_pages) && !isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $page;
    redirect('login');
}

// Pages that only farmers/officers can access (admins are blocked)
$farmer_officer_pages = [
    'dashboard', 'farmer-dashboard', 'profile', 'crops', 'disease',
    'weather', 'marketplace', 'community', 'alerts', 'create-report',
    'my-reports', 'agent', 'farmer-profile-view', 'officer-profile-view',
    'learn', 'learn-view', 'officer-dashboard', 'farmer-reports',
    'issue-alert', 'advisory', 'officer-learn', 'farmer-messages',
];

// Role-based access control
if (isLoggedIn()) {
    $user = getCurrentUser();
    $role = $user['role'] ?? 'farmer';

    // Admin must stay in admin panel — block access to farmer/officer pages
    if ($role === 'admin' && in_array($page, $farmer_officer_pages)) {
        redirect('admin-dashboard');
    }

    // Check officer pages
    if (in_array($page, $officer_pages) && !in_array($role, ['officer', 'admin'])) {
        redirect('dashboard');
    }

    // Check admin pages
    if (in_array($page, $admin_pages) && $role !== 'admin') {
        redirect('dashboard');
    }
}

$page_file = __DIR__ . '/' . $pages[$page];

// Include the page
if (file_exists($page_file)) {
    include $page_file;
} else {
    // Show 404 page if file doesn't exist
    http_response_code(404);
    include __DIR__ . '/pages/404.php';
}
?>
