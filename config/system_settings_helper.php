<?php
/**
 * System Settings Helper
 * Provides easy access to system settings throughout the application
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Get all system settings
 * @return array System settings as associative array
 */
function getSystemSettings() {
    static $cachedSettings = null;
    
    if ($cachedSettings !== null) {
        return $cachedSettings;
    }
    
    try {
        $db = new Database();
        $settings = $db->single("SELECT * FROM system_settings WHERE id = 1");
        
        if (!$settings) {
            // Return default settings if table doesn't exist or is empty
            $settings = [
                'site_name' => 'SmartChashi',
                'site_description' => 'Smart Agriculture Management System',
                'default_language' => 'en',
                'timezone' => 'Asia/Dhaka',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i:s',
                'items_per_page' => 20,
                'currency' => 'BDT',
                'currency_symbol' => '৳',
                'enable_registration' => 1,
                'enable_comments' => 1,
                'enable_notifications' => 1
            ];
        }
        
        $cachedSettings = $settings;
        return $settings;
        
    } catch (Exception $e) {
        error_log("Error fetching system settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific system setting
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSystemSetting($key, $default = null) {
    $settings = getSystemSettings();
    return $settings[$key] ?? $default;
}

/**
 * Get site name
 * @return string
 */
function getSiteName() {
    return getSystemSetting('site_name', 'SmartChashi');
}

/**
 * Get site description
 * @return string
 */
function getSiteDescription() {
    return getSystemSetting('site_description', 'Smart Agriculture Management System');
}

/**
 * Get default language
 * @return string Language code (en, bn, etc.)
 */
function getDefaultLanguage() {
    return getSystemSetting('default_language', 'en');
}

/**
 * Get system timezone
 * @return string Timezone identifier
 */
function getSystemTimezone() {
    return getSystemSetting('timezone', 'Asia/Dhaka');
}

/**
 * Get items per page for pagination
 * @return int
 */
function getItemsPerPage() {
    return (int) getSystemSetting('items_per_page', 20);
}

/**
 * Get currency code
 * @return string
 */
function getCurrency() {
    return getSystemSetting('currency', 'BDT');
}

/**
 * Get currency symbol
 * @return string
 */
function getCurrencySymbol() {
    return getSystemSetting('currency_symbol', '৳');
}

/**
 * Check if user registration is enabled
 * @return bool
 */
function isRegistrationEnabled() {
    return (bool) getSystemSetting('enable_registration', 1);
}

/**
 * Check if comments are enabled
 * @return bool
 */
function areCommentsEnabled() {
    return (bool) getSystemSetting('enable_comments', 1);
}

/**
 * Check if notifications are enabled
 * @return bool
 */
function areNotificationsEnabled() {
    return (bool) getSystemSetting('enable_notifications', 1);
}

/**
 * Get contact email
 * @return string|null
 */
function getContactEmail() {
    return getSystemSetting('contact_email');
}

/**
 * Get contact phone
 * @return string|null
 */
function getContactPhone() {
    return getSystemSetting('contact_phone');
}

/**
 * Get contact address
 * @return string|null
 */
function getContactAddress() {
    return getSystemSetting('contact_address');
}

/**
 * Get social media URLs
 * @return array
 */
function getSocialMediaUrls() {
    return [
        'facebook' => getSystemSetting('facebook_url'),
        'twitter' => getSystemSetting('twitter_url'),
        'youtube' => getSystemSetting('youtube_url'),
        'instagram' => getSystemSetting('instagram_url')
    ];
}

/**
 * Get SEO metadata
 * @return array
 */
function getSeoMetadata() {
    return [
        'title' => getSystemSetting('seo_title', getSiteName()),
        'description' => getSystemSetting('seo_description', getSiteDescription()),
        'keywords' => getSystemSetting('seo_keywords', '')
    ];
}

/**
 * Format date according to system settings
 * @param string|int $date Date string or timestamp
 * @return string Formatted date
 */
function formatSystemDate($date) {
    $format = getSystemSetting('date_format', 'Y-m-d');
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format time according to system settings
 * @param string|int $time Time string or timestamp
 * @return string Formatted time
 */
function formatSystemTime($time) {
    $format = getSystemSetting('time_format', 'H:i:s');
    $timestamp = is_numeric($time) ? $time : strtotime($time);
    return date($format, $timestamp);
}

/**
 * Format date and time according to system settings
 * @param string|int $datetime DateTime string or timestamp
 * @return string Formatted datetime
 */
function formatSystemDateTime($datetime) {
    $dateFormat = getSystemSetting('date_format', 'Y-m-d');
    $timeFormat = getSystemSetting('time_format', 'H:i:s');
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date("$dateFormat $timeFormat", $timestamp);
}

/**
 * Format currency amount
 * @param float $amount Amount to format
 * @param bool $includeSymbol Include currency symbol
 * @return string Formatted amount
 */
function formatCurrency($amount, $includeSymbol = true) {
    $formatted = format_number($amount, 2);
    
    if ($includeSymbol) {
        $symbol = getCurrencySymbol();
        return $symbol . ' ' . $formatted;
    }
    
    return $formatted;
}

/**
 * Clear system settings cache
 * Call this after updating settings
 */
function clearSystemSettingsCache() {
    // Reset static cache
    getSystemSettings();
}
