<?php
/**
 * Alerts AJAX Handler
 * Handles all alert-related AJAX operations
 */

session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = new Database();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_read':
        $alertId = $_POST['alert_id'] ?? null;
        if (!$alertId) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            exit;
        }
        
        // Verify ownership
        $alert = $db->single("SELECT * FROM alerts WHERE alert_id = ? AND user_id = ?", [$alertId, $userId]);
        if (!$alert) {
            echo json_encode(['success' => false, 'message' => 'Alert not found']);
            exit;
        }
        
        $db->query("UPDATE alerts SET is_read = 1, read_at = NOW() WHERE alert_id = ?", [$alertId]);
        echo json_encode(['success' => true, 'message' => 'Alert marked as read']);
        break;
        
    case 'mark_unread':
        $alertId = $_POST['alert_id'] ?? null;
        if (!$alertId) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            exit;
        }
        
        // Verify ownership
        $alert = $db->single("SELECT * FROM alerts WHERE alert_id = ? AND user_id = ?", [$alertId, $userId]);
        if (!$alert) {
            echo json_encode(['success' => false, 'message' => 'Alert not found']);
            exit;
        }
        
        $db->query("UPDATE alerts SET is_read = 0, read_at = NULL WHERE alert_id = ?", [$alertId]);
        echo json_encode(['success' => true, 'message' => 'Alert marked as unread']);
        break;
        
    case 'mark_all_read':
        $db->query("UPDATE alerts SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0", [$userId]);
        echo json_encode(['success' => true, 'message' => 'All alerts marked as read']);
        break;
        
    case 'delete':
        $alertId = $_POST['alert_id'] ?? null;
        if (!$alertId) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            exit;
        }
        
        // Verify ownership
        $alert = $db->single("SELECT * FROM alerts WHERE alert_id = ? AND user_id = ?", [$alertId, $userId]);
        if (!$alert) {
            echo json_encode(['success' => false, 'message' => 'Alert not found']);
            exit;
        }
        
        $db->query("DELETE FROM alerts WHERE alert_id = ?", [$alertId]);
        echo json_encode(['success' => true, 'message' => 'Alert deleted']);
        break;
        
    case 'delete_all':
        $db->query("DELETE FROM alerts WHERE user_id = ?", [$userId]);
        echo json_encode(['success' => true, 'message' => 'All alerts deleted']);
        break;
        
    case 'save_preferences':
        $weatherAlerts = isset($_POST['weather_alerts']) ? 1 : 0;
        $diseaseAlerts = isset($_POST['disease_alerts']) ? 1 : 0;
        $marketAlerts = isset($_POST['market_alerts']) ? 1 : 0;
        $communityAlerts = isset($_POST['community_alerts']) ? 1 : 0;
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
        
        try {
            // Check if preferences exist
            $existing = $db->single("SELECT * FROM user_preferences WHERE user_id = ?", [$userId]);
            
            if ($existing) {
                $db->query("UPDATE user_preferences SET 
                    weather_alerts = ?, 
                    disease_alerts = ?, 
                    market_alerts = ?, 
                    community_alerts = ?, 
                    email_notifications = ?, 
                    sms_notifications = ?,
                    updated_at = NOW()
                    WHERE user_id = ?", 
                    [$weatherAlerts, $diseaseAlerts, $marketAlerts, $communityAlerts, $emailNotifications, $smsNotifications, $userId]);
            } else {
                $db->query("INSERT INTO user_preferences 
                    (user_id, weather_alerts, disease_alerts, market_alerts, community_alerts, email_notifications, sms_notifications, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 
                    [$userId, $weatherAlerts, $diseaseAlerts, $marketAlerts, $communityAlerts, $emailNotifications, $smsNotifications]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Preferences saved successfully']);
        } catch (Exception $e) {
            // Table doesn't exist yet - preferences will use defaults
            echo json_encode(['success' => false, 'message' => 'Preferences table not available. Please run the SQL setup script.']);
        }
        break;
        
    case 'get_preferences':
        try {
            $prefs = $db->single("SELECT * FROM user_preferences WHERE user_id = ?", [$userId]);
        } catch (Exception $e) {
            $prefs = null;
        }
        if (!$prefs) {
            $prefs = [
                'weather_alerts' => 1,
                'disease_alerts' => 1,
                'market_alerts' => 1,
                'community_alerts' => 1,
                'email_notifications' => 1,
                'sms_notifications' => 0
            ];
        }
        echo json_encode(['success' => true, 'preferences' => $prefs]);
        break;
        
    case 'get_unread_count':
        $count = $db->single("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND is_read = 0", [$userId]);
        echo json_encode(['success' => true, 'count' => $count['count'] ?? 0]);
        break;
        
    case 'get_recent':
        $limit = min(intval($_GET['limit'] ?? 5), 20);
        $alerts = $db->resultSet("SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$userId, $limit]);
        echo json_encode(['success' => true, 'alerts' => $alerts]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
