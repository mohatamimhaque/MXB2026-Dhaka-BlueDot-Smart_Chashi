<?php
/**
 * Shop Messages AJAX Handler
 * Actions: start_conversation, send, get_messages, list_conversations, mark_read
 * Used by both the customer messages page and the product-detail "Message Seller" flow.
 */

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Allow GET for polling, POST for everything else
$db = new ShopDatabase();

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (is_array($body) && !empty($body)) {
    $_POST = array_merge($_POST, $body);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// All message actions require shop login
if (!isShopLoggedIn()) {
    jsonError('Please login to use messaging', 401);
}

$customerId = $_SESSION['shop_user_id'];

switch ($action) {

    // ------------------------------------------------------------------
    // START CONVERSATION or get existing one
    // Used from product-detail page "Message Seller"
    // ------------------------------------------------------------------
    case 'start_conversation':
        $farmerId  = intval($_POST['farmer_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $subject   = trim(htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $firstMsg  = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (!$farmerId) jsonError('Farmer ID required');
        if (empty($firstMsg)) jsonError('Message cannot be empty');

        // Verify farmer exists
        $farmer = $db->single("SELECT user_id, first_name FROM users WHERE user_id = ? AND role = 'farmer'", [$farmerId]);
        if (!$farmer) jsonError('Farmer not found');

        // Find existing conversation for this customer + farmer (+ optional product)
        if ($productId) {
            $convo = $db->single(
                "SELECT conversation_id FROM shop_conversations
                 WHERE farmer_id = ? AND customer_id = ? AND customer_type = 'general' AND product_id = ?",
                [$farmerId, $customerId, $productId]
            );
        } else {
            $convo = $db->single(
                "SELECT conversation_id FROM shop_conversations
                 WHERE farmer_id = ? AND customer_id = ? AND customer_type = 'general' AND product_id IS NULL",
                [$farmerId, $customerId]
            );
        }

        if (!$convo) {
            // Get product name for subject
            $productName = null;
            if ($productId) {
                $prod = $db->single("SELECT product_name FROM marketplace_products WHERE product_id = ?", [$productId]);
                $productName = $prod ? $prod['product_name'] : null;
            }

            $convoId = $db->insert('shop_conversations', [
                'farmer_id'     => $farmerId,
                'customer_id'   => $customerId,
                'customer_type' => 'general',
                'product_id'    => $productId ?: null,
                'product_name'  => $productName,
                'subject'       => $subject ?: ($productName ? 'Re: ' . $productName : 'New inquiry'),
                'farmer_unread' => 1,
                'customer_unread' => 0,
            ]);
        } else {
            $convoId = $convo['conversation_id'];
            // Bump farmer unread
            $db->query("UPDATE shop_conversations SET farmer_unread = farmer_unread + 1, last_message_at = NOW() WHERE conversation_id = ?")
               ->bind(1, $convoId)->execute();
        }

        $attachUrl  = trim($_POST['attachment_url'] ?? '');
        $attachType = trim($_POST['attachment_type'] ?? '');

        // Insert message
        $db->insert('shop_messages', [
            'conversation_id' => $convoId,
            'sender_type'     => 'customer',
            'sender_id'       => $customerId,
            'message'         => $firstMsg ?: '',
            'attachment_url'  => $attachUrl  ?: null,
            'attachment_type' => $attachType ?: null,
            'is_read'         => 0,
        ]);

        jsonSuccess('Message sent!', ['conversation_id' => $convoId]);
        break;

    // ------------------------------------------------------------------
    // SEND — add a message to an existing conversation
    // ------------------------------------------------------------------
    case 'send':
        $convoId    = intval($_POST['conversation_id'] ?? 0);
        $message    = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));
        $sendAttach = trim($_POST['attachment_url'] ?? '');
        $sendType   = trim($_POST['attachment_type'] ?? '');

        if (!$convoId) jsonError('Conversation ID required');
        if (empty($message) && empty($sendAttach)) jsonError('Message or attachment required');

        // Verify customer owns or is part of this conversation
        $convo = $db->single(
            "SELECT * FROM shop_conversations WHERE conversation_id = ? AND customer_id = ? AND customer_type = 'general'",
            [$convoId, $customerId]
        );
        if (!$convo) jsonError('Conversation not found');

        $db->insert('shop_messages', [
            'conversation_id' => $convoId,
            'sender_type'     => 'customer',
            'sender_id'       => $customerId,
            'message'         => $message ?: '',
            'attachment_url'  => $sendAttach ?: null,
            'attachment_type' => $sendType   ?: null,
            'is_read'         => 0,
        ]);

        // Update conversation metadata
        $db->query(
            "UPDATE shop_conversations SET last_message_at = NOW(), farmer_unread = farmer_unread + 1 WHERE conversation_id = ?"
        )->bind(1, $convoId)->execute();

        jsonSuccess('Message sent');
        break;

    // ------------------------------------------------------------------
    // GET_MESSAGES — load/poll messages in a conversation
    // ------------------------------------------------------------------
    case 'get_messages':
        $convoId = intval($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
        $since   = intval($_GET['since'] ?? 0); // unix ms — only return newer messages

        if (!$convoId) jsonError('Conversation ID required');

        // Verify access
        $convo = $db->single(
            "SELECT * FROM shop_conversations WHERE conversation_id = ? AND customer_id = ? AND customer_type = 'general'",
            [$convoId, $customerId]
        );
        if (!$convo) jsonError('Conversation not found');

        if ($since > 0) {
            $sinceDateTime = date('Y-m-d H:i:s', intval($since / 1000));
            $messages = $db->resultSet(
                "SELECT * FROM shop_messages WHERE conversation_id = ? AND created_at > ? ORDER BY created_at ASC",
                [$convoId, $sinceDateTime]
            );
        } else {
            $messages = $db->resultSet(
                "SELECT * FROM shop_messages WHERE conversation_id = ? ORDER BY created_at ASC",
                [$convoId]
            );
        }

        // Mark farmer messages as read
        $db->query(
            "UPDATE shop_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'farmer' AND is_read = 0"
        )->bind(1, $convoId)->execute();

        // Reset customer unread count
        $db->query(
            "UPDATE shop_conversations SET customer_unread = 0 WHERE conversation_id = ?"
        )->bind(1, $convoId)->execute();

        jsonSuccess('Messages loaded', ['messages' => $messages]);
        break;

    // ------------------------------------------------------------------
    // LIST_CONVERSATIONS — all conversations for this customer
    // ------------------------------------------------------------------
    case 'list_conversations':
        $convos = $db->resultSet(
            "SELECT c.*, u.first_name as farmer_name, u.profile_img_url as farmer_avatar,
                    (SELECT message FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_at
             FROM shop_conversations c
             LEFT JOIN users u ON c.farmer_id = u.user_id
             WHERE c.customer_id = ? AND c.customer_type = 'general'
             ORDER BY c.last_message_at DESC",
            [$customerId]
        );

        jsonSuccess('Conversations loaded', ['conversations' => $convos]);
        break;

    // ------------------------------------------------------------------
    // POLL — lightweight poll for new messages by last seen ID
    // ------------------------------------------------------------------
    case 'poll':
        $convoId = intval($_GET['conversation_id'] ?? 0);
        $lastId  = intval($_GET['last_id'] ?? 0);

        if (!$convoId) jsonError('Conversation ID required');

        // Verify access
        $convo = $db->single(
            "SELECT conversation_id FROM shop_conversations WHERE conversation_id = ? AND customer_id = ? AND customer_type = 'general'",
            [$convoId, $customerId]
        );
        if (!$convo) jsonError('Conversation not found');

        $newMsgs = $db->resultSet(
            "SELECT * FROM shop_messages WHERE conversation_id = ? AND message_id > ? ORDER BY created_at ASC",
            [$convoId, $lastId]
        );

        // Reset customer unread when they poll
        try {
            $db->query("UPDATE shop_conversations SET customer_unread = 0, buyer_unread = 0 WHERE conversation_id = ?")
               ->bind(1, $convoId)->execute();
        } catch (Exception $e) {
            $db->query("UPDATE shop_conversations SET customer_unread = 0 WHERE conversation_id = ?")
               ->bind(1, $convoId)->execute();
        }

        jsonSuccess('OK', ['messages' => $newMsgs]);
        break;

    default:
        jsonError('Invalid action');
}
