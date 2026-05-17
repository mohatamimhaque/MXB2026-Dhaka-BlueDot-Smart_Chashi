<?php
/**
 * Settings AJAX Handler
 * Handles all admin settings operations
 * 
 * Tables used:
 * - system_settings (id=1 row for site config)
 * - admin_settings (key-value pairs for security/maintenance)
 */

// Start output buffering to catch any accidental output
ob_start();

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

// Clean any output that might have happened during includes
ob_end_clean();

// Helper function for JSON responses
function jsonResponse($data, $statusCode = 200) {
    // Clean any remaining output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

set_exception_handler(function($exception) {
    error_log("Settings AJAX Exception: " . $exception->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()], 500);
});

header('Content-Type: application/json');


// Authentication check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Check if user is admin
$db = new Database();
if (isset($_SESSION['user_id'])) {
    $user = $db->single("SELECT role FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized - Not admin'], 403);
    }
    $adminId = $_SESSION['user_id'];
} else {
    $adminId = $_SESSION['admin_id'];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF token validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

// Action router
switch ($action) {
    // System settings (single row in system_settings table)
    case 'get_system_settings':
        getSystemSettings();
        break;
    case 'update_system_settings':
        updateSystemSettings();
        break;
    
    // Admin settings (key-value pairs in admin_settings table)
    case 'get_settings':
        getSettings();
        break;
    case 'update_settings':
        updateSettings();
        break;
    
    // Maintenance actions
    case 'clear_cache':
        clearCache();
        break;
    case 'clear_sessions':
        clearSessions();
        break;
    case 'clear_logs':
        clearLogs();
        break;
    
    // API testing
    case 'test_api_connection':
        testApiConnection();
        break;

    // AI settings
    case 'update_ai_settings':
        updateAiSettings();
        break;
    case 'get_ai_key':
        getAiKey();
        break;
    case 'test_ai':
        testAi();
        break;
    case 'get_ai_stats':
        getAiStats();
        break;
    case 'get_ai_logs':
        getAiLogs();
        break;
    case 'get_ai_chart':
        getAiChart();
        break;

    case 'ping_url':
        $url = filter_var(trim($_POST['url'] ?? ''), FILTER_VALIDATE_URL);
        if (!$url) {
            jsonResponse(['success' => false, 'message' => 'Invalid URL.']);
        }
        $ctx = stream_context_create(['http' => ['timeout' => 6, 'method' => 'HEAD', 'ignore_errors' => true]]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result !== false || isset($http_response_header[0])) {
            preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0] ?? '', $m);
            jsonResponse(['success' => true, 'http_code' => $m[1] ?? '200']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Could not reach the URL.']);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action: ' . $action], 400);
}

/**
 * Get system settings from system_settings table
 */
function getSystemSettings() {
    global $db;
    
    try {
        $settings = $db->single("SELECT * FROM system_settings WHERE id = 1");
        
        if (!$settings) {
            jsonResponse(['success' => false, 'message' => 'System settings not found'], 404);
        }
        
        jsonResponse(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Update system settings (system_settings table)
 * Handles: site_name, site_description, default_language, timezone, 
 * items_per_page, currency, contact info, social URLs, SEO, API URLs
 */
function updateSystemSettings() {
    global $db, $adminId;
    
    try {
        // Allowed fields from system_settings table
        $allowedFields = [
            'site_name', 'site_description', 'site_logo', 'site_favicon',
            'default_language', 'timezone', 'date_format', 'time_format',
            'items_per_page', 'currency', 'currency_symbol',
            'contact_email', 'contact_phone', 'contact_address',
            'facebook_url', 'twitter_url', 'youtube_url', 'instagram_url',
            'enable_registration', 'enable_comments', 'enable_notifications',
            'google_analytics_id', 'facebook_pixel_id',
            'seo_title', 'seo_description', 'seo_keywords',
            'agent_api_url', 'disease_detection_api_url'
        ];
        
        // Collect valid fields
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                
                // Sanitize based on field type
                if ($field === 'items_per_page') {
                    $value = max(10, min(100, intval($value)));
                } elseif (in_array($field, ['enable_registration', 'enable_comments', 'enable_notifications'])) {
                    $value = $value ? 1 : 0;
                } elseif ($field === 'contact_email' && !empty($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
                    }
                } elseif (in_array($field, ['facebook_url', 'twitter_url', 'youtube_url', 'instagram_url', 'agent_api_url', 'disease_detection_api_url']) && !empty($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        jsonResponse(['success' => false, 'message' => "Invalid URL format for $field"], 400);
                    }
                } else {
                    $value = trim($value);
                }
                
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }
        
        if (empty($updates)) {
            jsonResponse(['success' => false, 'message' => 'No valid fields provided'], 400);
        }
        
        // Add metadata
        $updates[] = "updated_by = :updated_by";
        $updates[] = "updated_at = NOW()";
        $params[':updated_by'] = $adminId;
        
        // Check if row exists, create if not
        $exists = $db->single("SELECT id FROM system_settings WHERE id = 1");
        
        if (!$exists) {
            // Insert default row first
            $db->query("INSERT INTO system_settings (id, site_name) VALUES (1, 'SmartChashi')");
            $db->execute();
        }
        
        // Update
        $sql = "UPDATE system_settings SET " . implode(', ', $updates) . " WHERE id = 1";
        $db->query($sql);
        
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }
        
        $db->execute();
        
        jsonResponse([
            'success' => true,
            'message' => 'System settings saved successfully',
            'fields_updated' => count($updates) - 2 // Exclude metadata fields
        ]);
        
    } catch (Exception $e) {
        error_log("System settings update error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Get admin settings (key-value pairs from admin_settings table)
 */
function getSettings() {
    global $db;
    
    $group = $_GET['group'] ?? null;
    
    try {
        if ($group) {
            $settings = $db->resultSet("SELECT * FROM admin_settings WHERE setting_group = ? ORDER BY setting_key", [$group]);
        } else {
            $settings = $db->resultSet("SELECT * FROM admin_settings ORDER BY setting_group, setting_key");
        }
        
        // Convert to key-value pairs
        $settingsArray = [];
        foreach ($settings as $setting) {
            $settingsArray[$setting['setting_key']] = $setting['setting_value'];
        }
        
        jsonResponse(['success' => true, 'settings' => $settingsArray]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Update admin settings (admin_settings table - key-value pairs)
 * Used for security settings, maintenance mode, etc.
 */
function updateSettings() {
    global $db, $adminId;
    
    // Get settings from POST
    $settingsJson = $_POST['settings'] ?? null;
    $settings = [];
    
    if ($settingsJson) {
        $settings = json_decode($settingsJson, true);
        if (!$settings) {
            jsonResponse(['success' => false, 'message' => 'Invalid settings format'], 400);
        }
    } else {
        // Individual POST fields
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'csrf_token'])) {
                $settings[$key] = $value;
            }
        }
    }
    
    if (empty($settings)) {
        jsonResponse(['success' => false, 'message' => 'No settings provided'], 400);
    }
    
    try {
        $updated = 0;
        $inserted = 0;
        
        foreach ($settings as $key => $value) {
            // Check if setting exists
            $existing = $db->single("SELECT setting_id FROM admin_settings WHERE setting_key = ?", [$key]);
            
            if ($existing) {
                // Update
                $db->query("UPDATE admin_settings SET setting_value = :value, updated_by = :admin_id, updated_at = NOW() WHERE setting_key = :key");
                $db->bind(':value', $value);
                $db->bind(':admin_id', $adminId);
                $db->bind(':key', $key);
                $db->execute();
                $updated++;
            } else {
                // Insert new setting
                $group = explode('_', $key)[0] ?: 'general';
                
                $db->query("INSERT INTO admin_settings (setting_key, setting_value, setting_group, updated_by, updated_at) VALUES (:key, :value, :group, :admin_id, NOW())");
                $db->bind(':key', $key);
                $db->bind(':value', $value);
                $db->bind(':group', $group);
                $db->bind(':admin_id', $adminId);
                $db->execute();
                $inserted++;
            }
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Settings saved successfully',
            'updated' => $updated,
            'inserted' => $inserted
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Clear cache files
 */
function clearCache() {
    try {
        $cacheDir = __DIR__ . '/../../cache/';
        $cleared = 0;
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleared++;
                }
            }
        }
        
        jsonResponse(['success' => true, 'message' => "Cleared $cleared cache file(s)"]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Clear expired sessions
 */
function clearSessions() {
    global $db;
    
    try {
        // Clear from database
        $db->query("DELETE FROM admin_sessions WHERE expires_at < NOW()");
        $db->execute();
        $count = $db->rowCount();
        
        // Clear PHP session files older than 24 hours
        $sessionPath = session_save_path();
        if ($sessionPath && is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            $oneDayAgo = time() - 86400;
            
            foreach ($files as $file) {
                if (filemtime($file) < $oneDayAgo) {
                    @unlink($file);
                    $count++;
                }
            }
        }
        
        jsonResponse(['success' => true, 'message' => "Cleared $count expired session(s)"]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Clear old logs
 */
function clearLogs() {
    global $db;
    
    try {
        $deleted = 0;
        
        // Clear old error logs
        try {
            $db->query("DELETE FROM error_logs WHERE first_seen < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $db->execute();
            $deleted += $db->rowCount();
        } catch (Exception $e) {}
        
        // Clear old activity logs
        try {
            $db->query("DELETE FROM admin_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $db->execute();
            $deleted += $db->rowCount();
        } catch (Exception $e) {}
        
        // Clear old login attempts
        try {
            $db->query("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $db->execute();
            $deleted += $db->rowCount();
        } catch (Exception $e) {}
        
        jsonResponse(['success' => true, 'message' => "Cleared $deleted old log record(s)"]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Test API connection
 */
function testApiConnection() {
    global $db;
    
    $apiType = $_POST['api_type'] ?? '';
    
    if (empty($apiType)) {
        jsonResponse(['success' => false, 'message' => 'API type is required'], 400);
    }
    
    try {
        // Get API URL from system_settings
        $settings = $db->single("SELECT agent_api_url, disease_detection_api_url FROM system_settings WHERE id = 1");
        
        if (!$settings) {
            jsonResponse(['success' => false, 'message' => 'System settings not found'], 404);
        }
        
        // Determine which API URL
        $apiUrl = '';
        if ($apiType === 'agent') {
            $apiUrl = $settings['agent_api_url'] ?? '';
        } elseif ($apiType === 'disease') {
            $apiUrl = $settings['disease_detection_api_url'] ?? '';
        }
        
        if (empty($apiUrl)) {
            jsonResponse(['success' => false, 'message' => 'API URL not configured. Save the URL first.'], 400);
        }
        
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            jsonResponse(['success' => false, 'message' => 'Invalid URL format'], 400);
        }
        
        // Test connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            jsonResponse(['success' => false, 'message' => 'Connection failed: ' . $error], 500);
        }
        
        if ($httpCode >= 200 && $httpCode < 400) {
            jsonResponse([
                'success' => true,
                'message' => 'API connection successful',
                'http_code' => $httpCode,
                'api_type' => $apiType
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => "API returned HTTP $httpCode"], 500);
        }

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Save AI provider settings
 * Stores provider, model, temperature, max_tokens, system_prompt, and encrypted API key
 */
function updateAiSettings() {
    global $db, $adminId;

    $provider      = trim($_POST['ai_provider']      ?? '');
    $apiKey        = trim($_POST['ai_api_key']        ?? '');
    $model         = trim($_POST['ai_model']          ?? '');
    $temperature   = trim($_POST['ai_temperature']    ?? '0.7');
    $maxTokens     = (int)($_POST['ai_max_tokens']    ?? 1024);
    $systemPrompt  = trim($_POST['ai_system_prompt']  ?? '');

    $allowed = ['groq','openai','gemini','claude','deepseek','openrouter'];
    if (!in_array($provider, $allowed)) {
        jsonResponse(['success' => false, 'message' => 'Invalid provider'], 400);
    }
    $temperature = max(0, min(2, (float)$temperature));
    $maxTokens   = max(256, min(32000, $maxTokens));

    $toSave = [
        'ai_provider'    => $provider,
        'ai_model'       => $model,
        'ai_temperature' => (string)$temperature,
        'ai_max_tokens'  => (string)$maxTokens,
        'ai_system_prompt' => $systemPrompt,
    ];
    if (!empty($apiKey)) {
        $toSave['ai_api_key_' . $provider] = $apiKey;
    }

    try {
        foreach ($toSave as $key => $value) {
            $isSensitive = str_starts_with($key, 'ai_api_key_') ? 1 : 0;
            $existing = $db->single("SELECT setting_id FROM admin_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                $db->query("UPDATE admin_settings SET setting_value = ?, is_sensitive = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $db->bind(1, $value); $db->bind(2, $isSensitive);
                $db->bind(3, $adminId); $db->bind(4, $key);
            } else {
                $db->query("INSERT INTO admin_settings (setting_key, setting_value, setting_group, is_sensitive, updated_by, updated_at) VALUES (?,?,'ai',?,?,NOW())");
                $db->bind(1, $key); $db->bind(2, $value);
                $db->bind(3, $isSensitive); $db->bind(4, $adminId);
            }
            $db->execute();
        }
        jsonResponse(['success' => true, 'message' => 'AI settings saved successfully.']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * AI usage statistics summary
 */
function getAiStats() {
    global $db;
    try {
        $today       = $db->single("SELECT COUNT(*) as cnt FROM ai_usage_logs WHERE DATE(created_at) = CURDATE()");
        $week        = $db->single("SELECT COUNT(*) as cnt FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $avgMs       = $db->single("SELECT AVG(response_time_ms) as avg FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND success = 1");
        $fails       = $db->single("SELECT COUNT(*) as cnt FROM ai_usage_logs WHERE success = 0 AND DATE(created_at) = CURDATE()");
        $activeProvider = $db->single("SELECT setting_value FROM admin_settings WHERE setting_key = 'ai_provider'");
        $activeModel    = $db->single("SELECT setting_value FROM admin_settings WHERE setting_key = 'ai_model'");
        $providerBreakdown = $db->resultSet("SELECT provider, COUNT(*) as cnt FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY provider ORDER BY cnt DESC");

        jsonResponse([
            'success'            => true,
            'calls_today'        => (int)($today['cnt'] ?? 0),
            'calls_week'         => (int)($week['cnt'] ?? 0),
            'avg_response_ms'    => (int)($avgMs['avg'] ?? 0),
            'fails_today'        => (int)($fails['cnt'] ?? 0),
            'active_provider'    => $activeProvider['setting_value'] ?? 'groq',
            'active_model'       => $activeModel['setting_value'] ?? '',
            'provider_breakdown' => $providerBreakdown,
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Recent AI usage logs
 */
function getAiLogs() {
    global $db;
    $limit  = min(100, max(10, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    try {
        $logs = $db->resultSet(
            "SELECT l.id, l.provider, l.model, l.response_time_ms, l.success, l.error_message, l.created_at,
                    CONCAT(u.first_name,' ',COALESCE(u.last_name,'')) as user_name
             FROM ai_usage_logs l
             LEFT JOIN users u ON u.user_id = l.user_id
             ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset"
        );
        $total = $db->single("SELECT COUNT(*) as cnt FROM ai_usage_logs")['cnt'] ?? 0;
        jsonResponse(['success' => true, 'logs' => $logs, 'total' => (int)$total]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * AI usage chart data — calls per day for last N days
 */
function getAiChart() {
    global $db;
    $days = min(90, max(7, (int)($_GET['days'] ?? 30)));
    try {
        $rows = $db->resultSet(
            "SELECT DATE(created_at) as day, COUNT(*) as calls, SUM(success=0) as failures,
                    AVG(response_time_ms) as avg_ms
             FROM ai_usage_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY day ASC",
            [$days]
        );
        // Fill missing days with zeros
        $byDay = [];
        foreach ($rows as $r) $byDay[$r['day']] = $r;
        $labels = $calls = $failures = $avgMs = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[]   = date('M j', strtotime($d));
            $calls[]    = (int)($byDay[$d]['calls']    ?? 0);
            $failures[] = (int)($byDay[$d]['failures'] ?? 0);
            $avgMs[]    = (int)($byDay[$d]['avg_ms']   ?? 0);
        }
        jsonResponse(['success' => true, 'labels' => $labels, 'calls' => $calls, 'failures' => $failures, 'avg_ms' => $avgMs]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Return the saved API key for the requested provider
 */
function getAiKey() {
    global $db;
    $provider = trim($_POST['provider'] ?? '');
    $allowed  = ['groq','openai','gemini','claude','deepseek','openrouter'];
    if (!in_array($provider, $allowed)) {
        jsonResponse(['success' => false, 'message' => 'Invalid provider']);
    }
    $row = $db->single("SELECT setting_value FROM admin_settings WHERE setting_key = ?", ['ai_api_key_' . $provider]);
    jsonResponse(['success' => true, 'key' => $row['setting_value'] ?? '']);
}

/**
 * Test the selected AI provider with a simple prompt
 */
function testAi() {
    global $db;

    $provider = trim($_POST['provider'] ?? 'groq');
    $model    = trim($_POST['model']    ?? '');
    $prompt   = trim($_POST['prompt']   ?? 'Hello! Can you respond in one short sentence?');

    // Load API key from DB
    $row    = $db->single("SELECT setting_value FROM admin_settings WHERE setting_key = ?", ['ai_api_key_' . $provider]);
    $apiKey = $row['setting_value'] ?? '';
    // Fallback to config constants for Groq
    if (empty($apiKey) && $provider === 'groq' && defined('GROQ_API_KEY')) {
        $apiKey = GROQ_API_KEY;
    }

    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'No API key saved for ' . ucfirst($provider) . '. Please save your key first.']);
    }

    $messages = [['role' => 'user', 'content' => $prompt]];

    // Build request per provider
    switch ($provider) {
        case 'groq':
            $url     = 'https://api.groq.com/openai/v1/chat/completions';
            $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
            $body    = json_encode(['model' => $model ?: 'llama-3.3-70b-versatile', 'messages' => $messages, 'max_tokens' => 256]);
            break;
        case 'openai':
            $url     = 'https://api.openai.com/v1/chat/completions';
            $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
            $body    = json_encode(['model' => $model ?: 'gpt-4o-mini', 'messages' => $messages, 'max_tokens' => 256]);
            break;
        case 'gemini':
            $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . ($model ?: 'gemini-2.0-flash') . ':generateContent?key=' . $apiKey;
            $headers = ['Content-Type: application/json'];
            $body    = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
            break;
        case 'claude':
            $url     = 'https://api.anthropic.com/v1/messages';
            $headers = ['x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
            $body    = json_encode(['model' => $model ?: 'claude-haiku-4-5-20251001', 'max_tokens' => 256, 'messages' => $messages]);
            break;
        case 'deepseek':
            $url     = 'https://api.deepseek.com/chat/completions';
            $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
            $body    = json_encode(['model' => $model ?: 'deepseek-chat', 'messages' => $messages, 'max_tokens' => 256]);
            break;
        case 'openrouter':
            $url     = 'https://openrouter.ai/api/v1/chat/completions';
            $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
            $body    = json_encode(['model' => $model, 'messages' => $messages, 'max_tokens' => 256]);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unsupported provider']);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) { jsonResponse(['success' => false, 'message' => 'cURL error: ' . $err]); }

    $json = json_decode($response, true);

    // Extract reply based on provider response format
    $reply = null;
    if ($provider === 'gemini') {
        $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
    } elseif ($provider === 'claude') {
        $reply = $json['content'][0]['text'] ?? null;
    } else {
        $reply = $json['choices'][0]['message']['content'] ?? null;
    }

    if ($reply !== null) {
        jsonResponse(['success' => true, 'reply' => trim($reply)]);
    } else {
        $errMsg = $json['error']['message'] ?? $json['error']['msg'] ?? 'Unexpected response format.';
        jsonResponse(['success' => false, 'message' => $errMsg . ' (Response: ' . substr($response, 0, 200) . ')']);
    }
}
