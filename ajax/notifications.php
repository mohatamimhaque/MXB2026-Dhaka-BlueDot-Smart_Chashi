<?php
/**
 * Notifications AJAX Handler
 * Handles notifications for all user types (farmer, officer, general)
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check authentication
$userId = null;
$userType = null;

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $userType = getCurrentUser()['role'] ?? 'farmer';
} elseif (isset($_SESSION['shop_user_id'])) {
    $userId = $_SESSION['shop_user_id'];
    $userType = 'general';
}

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get recent notifications
            $limit = intval($_GET['limit'] ?? 10);
            
            $notifications = $db->query(
                "SELECT * FROM user_notifications 
                 WHERE user_id = ? AND user_type = ? 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            )->bind(1, $userId)->bind(2, $userType)->bind(3, $limit)->fetchAll();
            
            // Get unread count
            $unreadCount = $db->query(
                "SELECT COUNT(*) as count FROM user_notifications 
                 WHERE user_id = ? AND user_type = ? AND is_read = 0"
            )->bind(1, $userId)->bind(2, $userType)->fetch()['count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            break;
            
        case 'mark_read':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            
            if ($notificationId) {
                $db->query(
                    "UPDATE user_notifications SET is_read = 1 
                     WHERE notification_id = ? AND user_id = ? AND user_type = ?"
                )->bind(1, $notificationId)->bind(2, $userId)->bind(3, $userType)->execute();
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $db->query(
                "UPDATE user_notifications SET is_read = 1 
                 WHERE user_id = ? AND user_type = ?"
            )->bind(1, $userId)->bind(2, $userType)->execute();
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            break;
            
        case 'clear_all':
            $db->query(
                "DELETE FROM user_notifications 
                 WHERE user_id = ? AND user_type = ? AND is_read = 1"
            )->bind(1, $userId)->bind(2, $userType)->execute();
            
            echo json_encode(['success' => true, 'message' => 'Read notifications cleared']);
            break;
            
        case 'delete':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            
            if ($notificationId) {
                $db->query(
                    "DELETE FROM user_notifications 
                     WHERE notification_id = ? AND user_id = ? AND user_type = ?"
                )->bind(1, $notificationId)->bind(2, $userId)->bind(3, $userType)->execute();
            }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
