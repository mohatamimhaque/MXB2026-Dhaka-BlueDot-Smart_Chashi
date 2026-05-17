<?php
/**
 */
function getSystemSettings() {
    global $SYSTEM_SETTINGS;
    
    // Return cached if available
    if ($SYSTEM_SETTINGS !== null) {
        return $SYSTEM_SETTINGS;
    }
    
    try {
        $db = new Database();
        $settings = $db->single("SELECT * FROM system_settings WHERE id = 1");
        
        if ($settings) {
            $SYSTEM_SETTINGS = $settings;
        } else {
            // Return defaults if not found
            $SYSTEM_SETTINGS = getDefaultSystemSettings();
        }
        
        return $SYSTEM_SETTINGS;
        
    } catch (Exception $e) {
        error_log("Error loading system settings: " . $e->getMessage());
        return getDefaultSystemSettings();
    }
}

/**
 * Get default system settings
 * 
 * @return array Default values
 */
function getDefaultSystemSettings() {
    return [
        'id' => 1,
        'site_name' => 'SmartChashi',
        'site_description' => 'Smart Agriculture Management System',
        'site_logo' => null,
        'site_favicon' => null,
        'default_language' => 'en',
        'timezone' => 'Asia/Dhaka',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'items_per_page' => 20,
        'currency' => 'BDT',
        'currency_symbol' => '৳',
        'contact_email' => null,
        'contact_phone' => null,
        'contact_address' => null,
        'facebook_url' => null,
        'twitter_url' => null,
        'youtube_url' => null,
        'instagram_url' => null,
        'enable_registration' => 1,
        'enable_comments' => 1,
        'enable_notifications' => 1,
        'google_analytics_id' => null,
        'facebook_pixel_id' => null,
        'seo_title' => null,
        'seo_description' => null,
        'seo_keywords' => null,
        'agent_api_url' => null,
        'disease_detection_api_url' => null,
    ];
}

/**
 * Get a single system setting value
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getSystemSetting($key, $default = null) {
    $settings = getSystemSettings();
    return $settings[$key] ?? $default;
}

/**
 * Output system settings as JavaScript global object
 * Call this in your HTML head or before scripts that need access
 * 
 * @param bool $includeAll Include all settings (false = only safe/public ones)
 */
function outputSystemSettingsJS($includeAll = false) {
    $settings = getSystemSettings();
    
    // Public settings safe to expose to JavaScript
    $publicSettings = [
        'site_name' => $settings['site_name'] ?? 'SmartChashi',
        'site_description' => $settings['site_description'] ?? '',
        'default_language' => $settings['default_language'] ?? 'en',
        'timezone' => $settings['timezone'] ?? 'Asia/Dhaka',
        'date_format' => $settings['date_format'] ?? 'Y-m-d',
        'time_format' => $settings['time_format'] ?? 'H:i:s',
        'items_per_page' => (int)($settings['items_per_page'] ?? 20),
        'currency' => $settings['currency'] ?? 'BDT',
        'currency_symbol' => $settings['currency_symbol'] ?? '৳',
        'contact_email' => $settings['contact_email'] ?? '',
        'contact_phone' => $settings['contact_phone'] ?? '',
        'facebook_url' => $settings['facebook_url'] ?? '',
        'twitter_url' => $settings['twitter_url'] ?? '',
        'youtube_url' => $settings['youtube_url'] ?? '',
        'instagram_url' => $settings['instagram_url'] ?? '',
        'enable_registration' => (bool)($settings['enable_registration'] ?? true),
        'enable_comments' => (bool)($settings['enable_comments'] ?? true),
        'enable_notifications' => (bool)($settings['enable_notifications'] ?? true),
    ];
    
    if ($includeAll) {
        // Add all settings (use with caution - for admin pages only)
        $publicSettings = array_merge($publicSettings, [
            'site_logo' => $settings['site_logo'] ?? '',
            'site_favicon' => $settings['site_favicon'] ?? '',
            'contact_address' => $settings['contact_address'] ?? '',
            'google_analytics_id' => $settings['google_analytics_id'] ?? '',
            'facebook_pixel_id' => $settings['facebook_pixel_id'] ?? '',
            'seo_title' => $settings['seo_title'] ?? '',
            'seo_description' => $settings['seo_description'] ?? '',
            'seo_keywords' => $settings['seo_keywords'] ?? '',
            'agent_api_url' => $settings['agent_api_url'] ?? '',
            'disease_detection_api_url' => $settings['disease_detection_api_url'] ?? '',
        ]);
    }
    
    echo '<script>window.SYSTEM_SETTINGS = ' . json_encode($publicSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>' . PHP_EOL;
}

// ============================================
// Initialize global system settings
// ============================================
$SYSTEM_SETTINGS = null;

// ============================================
// ADMIN SETTINGS (from admin_settings table)
// Key-value pairs for security, maintenance, etc.
// ============================================

/**
 * Get a single admin setting value (from admin_settings table)
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    // Load settings once and cache
    if ($settings === null) {
        $settings = getAllSettings();
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Get all admin settings as key-value array
 * 
 * @return array All settings
 */
function getAllSettings() {
    try {
        $db = new Database();
        $rows = $db->resultSet("SELECT setting_key, setting_value FROM admin_settings");
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Error loading admin settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Update an admin setting value
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param int $userId User making the change
 * @return bool Success
 */
function updateSetting($key, $value, $userId = null) {
    try {
        $db = new Database();
        
        // Check if exists
        $existing = $db->single("SELECT setting_id FROM admin_settings WHERE setting_key = ?", [$key]);
        
        if ($existing) {
            // Update
            $db->query("UPDATE admin_settings SET setting_value = :value, updated_by = :user_id, updated_at = NOW() WHERE setting_key = :key");
            $db->bind(':value', $value);
            $db->bind(':user_id', $userId);
            $db->bind(':key', $key);
            $db->execute();
        } else {
            // Insert new
            $group = explode('_', $key)[0] ?? 'general';
            
            $db->query("INSERT INTO admin_settings (setting_key, setting_value, setting_group, updated_by, updated_at) VALUES (:key, :value, :group, :user_id, NOW())");
            $db->bind(':key', $key);
            $db->bind(':value', $value);
            $db->bind(':group', $group);
            $db->bind(':user_id', $userId);
            $db->execute();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}

// ============================================
// CONVENIENCE FUNCTIONS
// ============================================

/**
 * Check if site is in maintenance mode
 */
function isMaintenanceMode() {
    return getSetting('maintenance_mode', '0') === '1';
}

/**
 * Check if current IP is allowed during maintenance
 */
function isAllowedDuringMaintenance() {
    $allowedIps = getSetting('maintenance_allowed_ips', '');
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($allowedIps)) {
        return false;
    }
    
    $allowedList = array_map('trim', explode("\n", $allowedIps));
    return in_array($currentIp, $allowedList);
}

/**
 * Get site name from system_settings
 */
function getSiteName() {
    return getSystemSetting('site_name', 'SmartChashi');
}

/**
 * Get site timezone from system_settings
 */
function getSiteTimezone() {
    return getSystemSetting('timezone', 'Asia/Dhaka');
}

/**
 * Check if 2FA is required (from admin_settings)
 */
function is2FARequired() {
    return getSetting('require_2fa', '0') === '1';
}

/**
 * Get session timeout in minutes (from admin_settings)
 */
function getSessionTimeout() {
    return (int) getSetting('session_timeout', 30);
}

/**
 * Get max failed login attempts
 */
function getMaxFailedLogins() {
    return (int) getSetting('max_failed_logins', 5);
}

/**
 * Get password minimum length
 */
function getPasswordMinLength() {
    return (int) getSetting('password_min_length', 8);
}

/**
 * Check if mixed case is required in passwords
 */
function isPasswordMixedCaseRequired() {
    return getSetting('require_mixed_case', '1') === '1';
}

/**
 * Check if numbers are required in passwords
 */
function isPasswordNumbersRequired() {
    return getSetting('require_numbers', '1') === '1';
}

/**
 * Validate password against policy
 */
function validatePassword($password) {
    $errors = [];
    
    $minLength = getPasswordMinLength();
    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least $minLength characters";
    }
    
    if (isPasswordMixedCaseRequired()) {
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain both uppercase and lowercase letters";
        }
    }
    
    if (isPasswordNumbersRequired()) {
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Get items per page for pagination (from system_settings)
 */
function getItemsPerPage() {
    return (int) getSystemSetting('items_per_page', 20);
}

/**
 * Get currency symbol
 */
function getCurrencySymbol() {
    return getSystemSetting('currency_symbol', '৳');
}

/**
 * Get currency code
 */
function getCurrency() {
    return getSystemSetting('currency', 'BDT');
}

/**
 * Check if user registration is enabled
 */
function isRegistrationEnabled() {
    return (bool) getSystemSetting('enable_registration', 1);
}

/**
 * Check if comments are enabled
 */
function areCommentsEnabled() {
    return (bool) getSystemSetting('enable_comments', 1);
}

/**
 * Get contact email
 */
function getContactEmail() {
    return getSystemSetting('contact_email', '');
}

/**
 * Get contact phone
 */
function getContactPhone() {
    return getSystemSetting('contact_phone', '');
}
