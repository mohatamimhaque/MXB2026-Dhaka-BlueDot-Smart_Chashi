<?php
/**
 * Agent Conversations API
 * Actions: list, new, rename, delete, load
 */

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Login required']); exit;
}

$db     = new Database();
$userId = $_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {

    // ── List all conversations ──────────────────────────────────────
    case 'list':
        $archivedOnly = isset($_GET['archived']) && $_GET['archived'] === '1';
        try {
            $convos = $db->resultSet(
                "SELECT conversation_id, title, updated_at,
                        is_pinned, is_archived, share_token
                 FROM agent_conversations
                 WHERE user_id = ? AND is_archived = ?
                 ORDER BY is_pinned DESC, updated_at DESC
                 LIMIT 100",
                [$userId, $archivedOnly ? 1 : 0]
            );
        } catch (Exception $e) {
            // Fallback if migration_v4 hasn't been run yet
            $convos = $db->resultSet(
                "SELECT conversation_id, title, updated_at,
                        0 AS is_pinned, 0 AS is_archived, NULL AS share_token
                 FROM agent_conversations
                 WHERE user_id = ?
                 ORDER BY updated_at DESC LIMIT 100",
                [$userId]
            );
        }
        // Cast TINYINT fields to int so JSON encodes 0/1, not "0"/"1"
        foreach ($convos as &$c) {
            $c['is_pinned']   = (int)($c['is_pinned'] ?? 0);
            $c['is_archived'] = (int)($c['is_archived'] ?? 0);
        }
        unset($c);
        echo json_encode(['success' => true, 'conversations' => $convos]);
        break;

    // ── Create new conversation ─────────────────────────────────────
    case 'new':
        $cid = bin2hex(random_bytes(16));
        $db->query("INSERT INTO agent_conversations (conversation_id, user_id, title) VALUES (?,?,?)")
           ->bind(1, $cid)->bind(2, $userId)->bind(3, 'New Chat')->execute();
        echo json_encode(['success' => true, 'conversation_id' => $cid, 'title' => 'New Chat']);
        break;

    // ── Load messages for a conversation ───────────────────────────
    case 'load':
        $cid    = $body['conversation_id'] ?? '';
        $offset = max(0, (int)($body['offset'] ?? 0));
        $limit  = 20;
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }

        $convo = $db->single(
            "SELECT * FROM agent_conversations WHERE conversation_id = ? AND user_id = ?",
            [$cid, $userId]
        );
        if (!$convo) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        $total = (int)($db->single(
            "SELECT COUNT(*) AS cnt FROM agent_messages WHERE conversation_id = ?", [$cid]
        )['cnt'] ?? 0);

        // Load newest-first then reverse so output is chronological
        // LIMIT/OFFSET inlined as ints — PDO binds them as strings which MariaDB rejects
        $messages = $db->resultSet(
            "SELECT id, role, content, images, feedback, created_at
             FROM agent_messages WHERE conversation_id = ?
             ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            [$cid]
        );
        $messages = array_reverse($messages);
        $messages = array_map(function ($m) {
            $m['images'] = isset($m['images']) && $m['images'] ? json_decode($m['images'], true) : [];
            return $m;
        }, $messages);

        echo json_encode([
            'success'  => true,
            'conversation' => $convo,
            'messages' => $messages,
            'total'    => $total,
            'has_more' => ($offset + $limit) < $total,
        ]);
        break;

    // ── Rename ─────────────────────────────────────────────────────
    case 'rename':
        $cid   = $body['conversation_id'] ?? '';
        $title = trim(htmlspecialchars($body['title'] ?? '', ENT_QUOTES, 'UTF-8'));
        if (!$cid || !$title) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

        $db->query("UPDATE agent_conversations SET title = ? WHERE conversation_id = ? AND user_id = ?")
           ->bind(1, $title)->bind(2, $cid)->bind(3, $userId)->execute();
        echo json_encode(['success' => true]);
        break;

    // ── Delete ─────────────────────────────────────────────────────
    case 'delete':
        $cid = $body['conversation_id'] ?? '';
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }

        $db->query("DELETE FROM agent_messages WHERE conversation_id = ?")
           ->bind(1, $cid)->execute();
        $db->query("DELETE FROM agent_conversations WHERE conversation_id = ? AND user_id = ?")
           ->bind(1, $cid)->bind(2, $userId)->execute();
        echo json_encode(['success' => true]);
        break;

    // ── Message feedback (thumbs up/down) ──────────────────────────
    case 'feedback':
        $msgId    = (int)($body['message_id'] ?? 0);
        $fbValue  = (int)($body['value'] ?? 0);
        if (!$msgId || !in_array($fbValue, [1, -1, 0], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid feedback']); exit;
        }
        // Verify ownership through conversation join
        $db->query(
            "UPDATE agent_messages m
             JOIN agent_conversations c ON c.conversation_id = m.conversation_id
             SET m.feedback = ?
             WHERE m.id = ? AND c.user_id = ?"
        )->bind(1, $fbValue ?: null, $fbValue === 0 ? PDO::PARAM_NULL : PDO::PARAM_INT)
         ->bind(2, $msgId, PDO::PARAM_INT)
         ->bind(3, $userId, PDO::PARAM_INT)
         ->execute();
        echo json_encode(['success' => true]);
        break;

    // ── List user memory ────────────────────────────────────────────
    case 'memory_list':
        try {
            $mem = $db->resultSet(
                "SELECT id, memory_key, memory_value, source, updated_at FROM agent_user_memory WHERE user_id = ? ORDER BY updated_at DESC",
                [$userId]
            );
            echo json_encode(['success' => true, 'memory' => $mem]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'memory' => []]);
        }
        break;

    // ── Delete one memory item ──────────────────────────────────────
    case 'memory_delete':
        $memId = (int)($body['id'] ?? 0);
        if (!$memId) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        try {
            $db->query("DELETE FROM agent_user_memory WHERE id = ? AND user_id = ?")
               ->bind(1, $memId, PDO::PARAM_INT)->bind(2, $userId, PDO::PARAM_INT)->execute();
        } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        break;

    // ── Clear all memory for user ───────────────────────────────────
    case 'memory_clear':
        try {
            $db->query("DELETE FROM agent_user_memory WHERE user_id = ?")
               ->bind(1, $userId, PDO::PARAM_INT)->execute();
        } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        break;

    // ── Save a manual memory item ───────────────────────────────────
    case 'memory_save':
        $memKey = trim($body['key'] ?? '');
        $memVal = trim($body['value'] ?? '');
        if (!$memKey || !$memVal || mb_strlen($memKey) > 100 || mb_strlen($memVal) > 500) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']); exit;
        }
        try {
            $db->query(
                "INSERT INTO agent_user_memory (user_id, memory_key, memory_value, source)
                 VALUES (?, ?, ?, 'manual')
                 ON DUPLICATE KEY UPDATE memory_value = VALUES(memory_value), source = 'manual', updated_at = NOW()"
            )->bind(1, $userId)->bind(2, $memKey)->bind(3, $memVal)->execute();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Save failed']); exit;
        }
        echo json_encode(['success' => true]);
        break;

    // ── Pin / unpin ─────────────────────────────────────────────────
    case 'pin':
        $cid = $body['conversation_id'] ?? '';
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        try {
            $db->query("UPDATE agent_conversations SET is_pinned = NOT is_pinned WHERE conversation_id = ? AND user_id = ?")
               ->bind(1, $cid)->bind(2, $userId)->execute();
            $row = $db->single("SELECT is_pinned FROM agent_conversations WHERE conversation_id = ? AND user_id = ?", [$cid, $userId]);
            echo json_encode(['success' => true, 'is_pinned' => (int)($row['is_pinned'] ?? 0)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Run migration_v4.sql first.']);
        }
        break;

    // ── Archive / unarchive ─────────────────────────────────────────
    case 'archive':
        $cid = $body['conversation_id'] ?? '';
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        try {
            $row = $db->single("SELECT is_archived, is_pinned FROM agent_conversations WHERE conversation_id = ? AND user_id = ?", [$cid, $userId]);
            if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }
            $newArchived = $row['is_archived'] ? 0 : 1;
            $newPinned   = $newArchived ? 0 : $row['is_pinned']; // auto-unpin when archiving
            $db->query("UPDATE agent_conversations SET is_archived = ?, is_pinned = ? WHERE conversation_id = ? AND user_id = ?")
               ->bind(1, $newArchived)->bind(2, $newPinned)->bind(3, $cid)->bind(4, $userId)->execute();
            echo json_encode(['success' => true, 'is_archived' => (int)$newArchived, 'is_pinned' => (int)$newPinned]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Run migration_v4.sql first.']);
        }
        break;

    // ── Generate share token ────────────────────────────────────────
    case 'share_generate':
        $cid = $body['conversation_id'] ?? '';
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        try {
            $row = $db->single("SELECT share_token FROM agent_conversations WHERE conversation_id = ? AND user_id = ?", [$cid, $userId]);
            if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }
            $token = $row['share_token'];
            if (!$token) {
                $token = bin2hex(random_bytes(16));
                $db->query("UPDATE agent_conversations SET share_token = ? WHERE conversation_id = ? AND user_id = ?")
                   ->bind(1, $token)->bind(2, $cid)->bind(3, $userId)->execute();
            }
            echo json_encode(['success' => true, 'token' => $token]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Run migration_v4.sql first.']);
        }
        break;

    // ── Revoke share token ──────────────────────────────────────────
    case 'share_revoke':
        $cid = $body['conversation_id'] ?? '';
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        try {
            $db->query("UPDATE agent_conversations SET share_token = NULL WHERE conversation_id = ? AND user_id = ?")
               ->bind(1, $cid)->bind(2, $userId)->execute();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Run migration_v4.sql first.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
