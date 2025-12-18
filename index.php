<?php
include __DIR__ . '/config/config.php';

$page = $_GET['page'] ?? 'home';
$id = $_GET['id'] ?? null;

// Sanitize page input
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

// Map pages to files
$pages = [
    'home' => 'pages/home.php',
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'dashboard' => 'pages/dashboard.php',
    'profile' => 'pages/profile.php',
    'crops' => 'pages/crops.php',
    'disease' => 'pages/disease.php',
    'chat' => 'pages/chat.php',
    'weather' => 'pages/weather.php',
    'marketplace' => 'pages/marketplace.php',
    'community' => 'pages/community.php',
    'alerts' => 'pages/alerts.php',
    'logout' => 'pages/logout.php',
    
    // Admin pages
    'admin-dashboard' => 'pages/admin-dashboard.php',
    'user-management' => 'pages/user-management.php',
    'system-settings' => 'pages/system-settings.php',
    'analytics' => 'pages/analytics.php',
    
    // Officer pages
    'officer-dashboard' => 'pages/officer-dashboard.php',
    'farmer-reports' => 'pages/farmer-reports.php',
    'issue-alert' => 'pages/issue-alert.php',
    'advisory' => 'pages/advisory.php',
];

// Check if page exists
if (!isset($pages[$page])) {
    $page = 'home';
}

$page_file = __DIR__ . '/' . $pages[$page];

// Include the page
if (file_exists($page_file)) {
    include $page_file;
} else {
    include __DIR__ . '/pages/home.php';
}
?>
