<?php
/**
 * Messages AJAX Handler
 * For both farmers and shop customers
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$db = new Database();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Determine user type and ID
$userId = null;
$userType = null;

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $userType = 'farmer'; // farmer in main system
} elseif (isset($_SESSION['shop_user_id'])) {
    $userId = $_SESSION['shop_user_id'];
    $userType = 'customer'; // shop customer
}

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    switch ($action) {
        case 'start_conversation':
            // Start a new conversation
            $farmerId = intval($_POST['farmer_id'] ?? 0);
            $orderId = intval($_POST['order_id'] ?? 0);
            $productId = intval($_POST['product_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if (!$farmerId || !$message) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            // Check if conversation exists
            $existingConvo = $db->query(
                "SELECT conversation_id FROM shop_conversations 
                 WHERE farmer_id = ? AND customer_id = ? AND customer_type = ?"
            )->bind(1, $farmerId)->bind(2, $userId)->bind(3, $userType === 'farmer' ? 'farmer' : 'general')->fetch();
            
            if ($existingConvo) {
                $conversationId = $existingConvo['conversation_id'];
            } else {
                // Create new conversation
                $db->query(
                    "INSERT INTO shop_conversations (farmer_id, customer_id, customer_type, order_id, product_id) 
                     VALUES (?, ?, ?, ?, ?)"
                )->bind(1, $farmerId)->bind(2, $userId)->bind(3, $userType === 'farmer' ? 'farmer' : 'general')
                 ->bind(4, $orderId ?: null)->bind(5, $productId ?: null)->execute();
                $conversationId = $db->lastInsertId();
            }
            
            // Add message
            $db->query(
                "INSERT INTO shop_messages (conversation_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)"
            )->bind(1, $conversationId)->bind(2, 'customer')->bind(3, $userId)->bind(4, $message)->execute();
            
            // Update conversation
            $db->query("UPDATE shop_conversations SET farmer_unread = farmer_unread + 1, last_message_at = NOW() WHERE conversation_id = ?")
                ->bind(1, $conversationId)->execute();
            
            // Notify farmer
            try {
                $db->query("INSERT INTO user_notifications (user_id, user_type, title, message, type, icon) VALUES (?, 'farmer', 'New Message', ?, 'message', 'chat')")
                    ->bind(1, $farmerId)->bind(2, 'You have a new message from a customer')->execute();
            } catch (Exception $e) {}
            
            echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
            break;
            
        case 'send':
            $conversationId = intval($_POST['conversation_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $senderType = $_POST['sender_type'] ?? ($userType === 'farmer' ? 'farmer' : 'customer');
            
            if (!$conversationId || !$message) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            // Verify access
            $convo = $db->query("SELECT * FROM shop_conversations WHERE conversation_id = ?")->bind(1, $conversationId)->fetch();
            
            if (!$convo) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                exit;
            }
            
            // Check user has access
            $hasAccess = ($senderType === 'farmer' && $convo['farmer_id'] == $userId) ||
                         ($senderType === 'customer' && $convo['customer_id'] == $userId);
                         
            if (!$hasAccess) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            // Insert message
            $db->query(
                "INSERT INTO shop_messages (conversation_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)"
            )->bind(1, $conversationId)->bind(2, $senderType)->bind(3, $userId)->bind(4, $message)->execute();
            
            $messageId = $db->lastInsertId();
            
            // Update unread counts
            if ($senderType === 'farmer') {
                $db->query("UPDATE shop_conversations SET customer_unread = customer_unread + 1, last_message_at = NOW() WHERE conversation_id = ?")
                    ->bind(1, $conversationId)->execute();
                    
                // Notify customer
                $notifyUserId = $convo['customer_id'];
                $notifyType = $convo['customer_type'];
                try {
                    $db->query("INSERT INTO user_notifications (user_id, user_type, title, message, type, icon) VALUES (?, ?, 'New Message', ?, 'message', 'chat')")
                        ->bind(1, $notifyUserId)->bind(2, $notifyType)->bind(3, 'You have a reply from the seller')->execute();
                } catch (Exception $e) {}
            } else {
                $db->query("UPDATE shop_conversations SET farmer_unread = farmer_unread + 1, last_message_at = NOW() WHERE conversation_id = ?")
                    ->bind(1, $conversationId)->execute();
            }
            
            echo json_encode(['success' => true, 'message_id' => $messageId]);
            break;
            
        case 'poll':
            $conversationId = intval($_GET['conversation_id'] ?? 0);
            $lastId = intval($_GET['last_id'] ?? 0);

            if (!$conversationId) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            if ($lastId > 0) {
                $messages = $db->query(
                    "SELECT * FROM shop_messages WHERE conversation_id = ? AND message_id > ? ORDER BY created_at ASC"
                )->bind(1, $conversationId)->bind(2, $lastId)->fetchAll();
            } else {
                $lastCheck = isset($_GET['since']) ? intval($_GET['since']) / 1000 : time() - 10;
                $messages = $db->query(
                    "SELECT * FROM shop_messages WHERE conversation_id = ? AND created_at > FROM_UNIXTIME(?) ORDER BY created_at ASC"
                )->bind(1, $conversationId)->bind(2, $lastCheck - 5)->fetchAll();
            }

            // Reset farmer unread when farmer polls
            if ($userType === 'farmer') {
                try {
                    $db->query("UPDATE shop_conversations SET farmer_unread = 0, seller_unread = 0 WHERE conversation_id = ? AND farmer_id = ?")
                       ->bind(1, $conversationId)->bind(2, $userId)->execute();
                } catch (Exception $e) {
                    $db->query("UPDATE shop_conversations SET farmer_unread = 0 WHERE conversation_id = ? AND farmer_id = ?")
                       ->bind(1, $conversationId)->bind(2, $userId)->execute();
                }
            }

            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_conversations':
            if ($userType !== 'farmer') {
                // Shop customer - get their conversations
                $conversations = $db->query(
                    "SELECT c.*, u.first_name as farmer_name,
                        (SELECT message FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message
                     FROM shop_conversations c
                     LEFT JOIN users u ON c.farmer_id = u.user_id
                     WHERE c.customer_id = ? AND c.customer_type = 'general'
                     ORDER BY c.last_message_at DESC"
                )->bind(1, $userId)->fetchAll();
            } else {
                // Farmer
                $conversations = $db->query(
                    "SELECT c.*, 
                        CASE 
                            WHEN c.customer_type = 'general' THEN (SELECT first_name FROM general_users WHERE user_id = c.customer_id)
                            ELSE (SELECT first_name FROM users WHERE user_id = c.customer_id)
                        END as customer_name,
                        (SELECT message FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message
                     FROM shop_conversations c
                     WHERE c.farmer_id = ?
                     ORDER BY c.last_message_at DESC"
                )->bind(1, $userId)->fetchAll();
            }
            
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;
            
        case 'get_messages':
            $conversationId = intval($_GET['conversation_id'] ?? 0);
            
            if (!$conversationId) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            $messages = $db->query(
                "SELECT * FROM shop_messages WHERE conversation_id = ? ORDER BY created_at ASC"
            )->bind(1, $conversationId)->fetchAll();
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
