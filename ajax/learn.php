<?php
header('Content-Type: application/json; charset=utf-8');
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function jsonOut($data) { ob_end_clean(); echo json_encode($data); exit; }

set_error_handler(fn($n,$s,$f,$l) => jsonOut(['success'=>false,'message'=>'PHP Error: '.$s]));
set_exception_handler(fn($e) => jsonOut(['success'=>false,'message'=>$e->getMessage()]));

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    jsonOut(['success' => false, 'message' => 'Login required']);
}

$db     = new Database();
$userId = $_SESSION['user_id'];
$role   = getCurrentUser()['role'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? ''));
$body   = json_decode(file_get_contents('php://input'), true) ?: [];

// ── Helper ────────────────────────────────────────────────────────────────
function isOfficer(): bool {
    $u = getCurrentUser();
    return in_array($u['role'], ['officer', 'admin']);
}

function extractYouTubeId(string $url): string {
    preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $url, $m);
    return $m[1] ?? '';
}

// ── Route ─────────────────────────────────────────────────────────────────
switch ($action) {

    // ── List content with filters ─────────────────────────────────────────
    case 'list':
        $type       = $_GET['type']     ?? 'all';
        $catId      = (int)($_GET['cat'] ?? 0);
        $season     = $_GET['season']   ?? 'all';
        $difficulty = $_GET['diff']     ?? 'all';
        $search     = trim($_GET['q']   ?? '');
        $page       = max(1, (int)($_GET['p'] ?? 1));
        $limit      = 12;
        $offset     = ($page - 1) * $limit;

        $where  = ['lc.is_published = 1'];
        $params = [];

        if ($type !== 'all')       { $where[] = 'lc.type = ?';           $params[] = $type; }
        if ($catId)                { $where[] = 'lc.category_id = ?';    $params[] = $catId; }
        if ($season !== 'all')     { $where[] = '(lc.season = ? OR lc.season = "all")'; $params[] = $season; }
        if ($difficulty !== 'all' && !empty($difficulty)) { $where[] = 'lc.difficulty = ?';     $params[] = $difficulty; }
        if ($search)               {
            $where[] = '(lc.title LIKE ? OR lc.description LIKE ? OR lc.crop_tags LIKE ?)';
            $like = "%$search%";
            $params = array_merge($params, [$like, $like, $like]);
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);

        $sort = $_GET['sort'] ?? 'latest';
        $orderBy = match($sort) {
            'popular' => 'lc.is_featured DESC, lc.views DESC',
            'liked'   => 'lc.is_featured DESC, (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id) DESC',
            default   => 'lc.is_featured DESC, lc.created_at DESC',
        };

        $countRow = $db->single(
            "SELECT COUNT(*) as cnt FROM learn_content lc $whereStr", $params
        );
        $total = $countRow['cnt'] ?? 0;

        $rows = $db->resultSet(
            "SELECT lc.*, cat.name as cat_name, cat.icon as cat_icon, cat.color as cat_color,
                    u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id) as like_count,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id AND ll.user_id = ?) as user_liked,
                    (SELECT completed FROM learn_progress lp WHERE lp.content_id = lc.id AND lp.user_id = ?) as user_completed
             FROM learn_content lc
             LEFT JOIN learn_categories cat ON lc.category_id = cat.id
             LEFT JOIN users u ON lc.created_by = u.user_id
             $whereStr
             ORDER BY $orderBy
             LIMIT $limit OFFSET $offset",
            array_merge([$userId, $userId], $params)
        );

        jsonOut(['success' => true, 'items' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);

    // ── Get single content with full data ─────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'Invalid ID']);

        $item = $db->single(
            "SELECT lc.*, cat.name as cat_name, cat.icon as cat_icon, cat.color as cat_color,
                    u.first_name, u.last_name, u.profile_img_url,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id) as like_count,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id AND ll.user_id = ?) as user_liked,
                    (SELECT completed FROM learn_progress lp WHERE lp.content_id = lc.id AND lp.user_id = ?) as user_completed,
                    (SELECT certificate_code FROM learn_certificates lc2 WHERE lc2.content_id = lc.id AND lc2.user_id = ?) as cert_code
             FROM learn_content lc
             LEFT JOIN learn_categories cat ON lc.category_id = cat.id
             LEFT JOIN users u ON lc.created_by = u.user_id
             WHERE lc.id = ? AND (lc.is_published = 1 OR lc.created_by = ?)",
            [$userId, $userId, $userId, $id, $userId]
        );
        if (!$item) jsonOut(['success' => false, 'message' => 'Content not found']);

        // Playlist items
        if (in_array($item['type'], ['video','playlist'])) {
            $item['playlist_items'] = $db->resultSet(
                "SELECT * FROM learn_playlist_items WHERE content_id = ? ORDER BY sort_order ASC", [$id]
            );
        }

        // Quiz questions + options
        if ($item['type'] === 'quiz') {
            $questions = $db->resultSet(
                "SELECT * FROM learn_quiz_questions WHERE content_id = ? ORDER BY sort_order ASC", [$id]
            );
            foreach ($questions as &$q) {
                $q['options'] = $db->resultSet(
                    "SELECT id, option_text FROM learn_quiz_options WHERE question_id = ? ORDER BY id ASC", [$q['id']]
                );
            }
            unset($q);
            $item['questions'] = $questions;

            // Best attempt
            $item['best_attempt'] = $db->single(
                "SELECT score, total, passed FROM learn_quiz_attempts WHERE user_id = ? AND content_id = ? ORDER BY score DESC LIMIT 1",
                [$userId, $id]
            );
        }

        // Increment view
        $db->query("UPDATE learn_content SET views = views + 1 WHERE id = ?")->bind(1,$id)->execute();

        // Track access
        $db->query("INSERT INTO learn_progress (user_id, content_id) VALUES (?,?) ON DUPLICATE KEY UPDATE last_accessed = NOW()")
           ->bind(1,$userId)->bind(2,$id)->execute();

        jsonOut(['success' => true, 'item' => $item]);

    // ── Create content (officer/admin) ────────────────────────────────────
    case 'create':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);

        $type        = $body['type']        ?? '';
        $title       = trim($body['title']  ?? '');
        $titleBn     = trim($body['title_bn'] ?? '');
        $desc        = trim($body['description'] ?? '');
        $catId       = (int)($body['category_id'] ?? 0);
        $season      = $body['season']      ?? 'all';
        $cropTags    = trim($body['crop_tags'] ?? '');
        $difficulty  = $body['difficulty']  ?? 'beginner';
        $duration    = (int)($body['duration_min'] ?? 0);
        $body_text   = $body['content_body'] ?? '';
        $ytUrl       = trim($body['youtube_url'] ?? '');
        $webUrl      = trim($body['webinar_url'] ?? '');
        $webDate     = trim($body['webinar_scheduled_at'] ?? '');
        $passScore   = (int)($body['pass_score'] ?? 70);
        $thumb       = trim($body['thumbnail_url'] ?? '');
        $published   = (int)($body['is_published'] ?? 0);
        $featured    = (int)($body['is_featured'] ?? 0);

        if (!$title || !$type) jsonOut(['success' => false, 'message' => 'Title and type required']);

        $db->query("INSERT INTO learn_content (type,title,title_bn,description,thumbnail_url,category_id,season,crop_tags,difficulty,duration_min,created_by,content_body,youtube_url,webinar_url,webinar_scheduled_at,pass_score,is_published,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->bind(1,$type)->bind(2,$title)->bind(3,$titleBn)->bind(4,$desc)->bind(5,$thumb)
           ->bind(6,$catId ?: null)->bind(7,$season)->bind(8,$cropTags)->bind(9,$difficulty)
           ->bind(10,$duration ?: null)->bind(11,$userId)->bind(12,$body_text)->bind(13,$ytUrl)
           ->bind(14,$webUrl)->bind(15,$webDate ?: null)->bind(16,$passScore)
           ->bind(17,$published)->bind(18,$featured)->execute();

        $newId = $db->lastInsertId();

        // Insert playlist items if provided
        if (isset($body['playlist_items']) && is_array($body['playlist_items'])) {
            foreach ($body['playlist_items'] as $i => $vi) {
                $vtitle = trim($vi['title'] ?? '');
                $vurl   = trim($vi['youtube_url'] ?? '');
                if (!$vtitle || !$vurl) continue;
                $db->query("INSERT INTO learn_playlist_items (content_id,title,youtube_url,duration_min,sort_order) VALUES (?,?,?,?,?)")
                   ->bind(1,$newId)->bind(2,$vtitle)->bind(3,$vurl)->bind(4,(int)($vi['duration_min']??0))->bind(5,$i)->execute();
            }
        }

        // Insert quiz questions if provided
        if ($type === 'quiz' && isset($body['questions']) && is_array($body['questions'])) {
            foreach ($body['questions'] as $qi => $q) {
                $qtext = trim($q['question'] ?? '');
                if (!$qtext) continue;
                $db->query("INSERT INTO learn_quiz_questions (content_id,question,explanation,sort_order) VALUES (?,?,?,?)")
                   ->bind(1,$newId)->bind(2,$qtext)->bind(3,trim($q['explanation']??''))->bind(4,$qi)->execute();
                $qId = $db->lastInsertId();
                foreach ($q['options'] ?? [] as $opt) {
                    $otext = trim($opt['text'] ?? '');
                    if (!$otext) continue;
                    $db->query("INSERT INTO learn_quiz_options (question_id,option_text,is_correct) VALUES (?,?,?)")
                       ->bind(1,$qId)->bind(2,$otext)->bind(3,(int)($opt['correct']??0))->execute();
                }
            }
        }

        jsonOut(['success' => true, 'id' => $newId, 'message' => 'Content created']);

    // ── Update content ────────────────────────────────────────────────────
    case 'update':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);

        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID required']);

        $check = $db->single("SELECT created_by FROM learn_content WHERE id = ?", [$id]);
        if (!$check || ($check['created_by'] != $userId && $role !== 'admin')) {
            jsonOut(['success' => false, 'message' => 'Permission denied']);
        }

        $db->query("UPDATE learn_content SET title=?,title_bn=?,description=?,thumbnail_url=?,category_id=?,season=?,crop_tags=?,difficulty=?,duration_min=?,content_body=?,youtube_url=?,webinar_url=?,webinar_scheduled_at=?,pass_score=?,is_published=?,is_featured=? WHERE id=?")
           ->bind(1,$body['title']??'')->bind(2,$body['title_bn']??'')->bind(3,$body['description']??'')
           ->bind(4,$body['thumbnail_url']??'')->bind(5,$body['category_id']??null)->bind(6,$body['season']??'all')
           ->bind(7,$body['crop_tags']??'')->bind(8,$body['difficulty']??'beginner')
           ->bind(9,$body['duration_min']??null)->bind(10,$body['content_body']??'')
           ->bind(11,$body['youtube_url']??'')->bind(12,$body['webinar_url']??'')
           ->bind(13,$body['webinar_scheduled_at']??null)->bind(14,$body['pass_score']??70)
           ->bind(15,(int)($body['is_published']??0))->bind(16,(int)($body['is_featured']??0))->bind(17,$id)->execute();

        jsonOut(['success' => true, 'message' => 'Updated']);

    // ── Delete content ────────────────────────────────────────────────────
    case 'delete':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);

        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        $check = $db->single("SELECT created_by FROM learn_content WHERE id = ?", [$id]);
        if (!$check || ($check['created_by'] != $userId && $role !== 'admin')) {
            jsonOut(['success' => false, 'message' => 'Permission denied']);
        }
        $db->query("DELETE FROM learn_content WHERE id = ?")->bind(1,$id)->execute();
        jsonOut(['success' => true, 'message' => 'Deleted']);

    // ── Toggle publish ────────────────────────────────────────────────────
    case 'toggle_publish':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $id = (int)($body['id'] ?? 0);
        $check = $db->single("SELECT created_by, is_published FROM learn_content WHERE id = ?", [$id]);
        if (!$check || ($check['created_by'] != $userId && $role !== 'admin')) {
            jsonOut(['success' => false, 'message' => 'Permission denied']);
        }
        $newVal = $check['is_published'] ? 0 : 1;
        $db->query("UPDATE learn_content SET is_published = ? WHERE id = ?")->bind(1,$newVal)->bind(2,$id)->execute();
        jsonOut(['success' => true, 'is_published' => $newVal]);

    // ── Add playlist item ─────────────────────────────────────────────────
    case 'add_playlist_item':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $cid   = (int)($body['content_id'] ?? 0);
        $title = trim($body['title'] ?? '');
        $url   = trim($body['youtube_url'] ?? '');
        if (!$cid || !$title || !$url) jsonOut(['success' => false, 'message' => 'Fields required']);

        $order = $db->single("SELECT COUNT(*) as c FROM learn_playlist_items WHERE content_id=?",[$cid])['c'] ?? 0;
        $db->query("INSERT INTO learn_playlist_items (content_id,title,youtube_url,duration_min,sort_order) VALUES (?,?,?,?,?)")
           ->bind(1,$cid)->bind(2,$title)->bind(3,$url)->bind(4,(int)($body['duration_min']??0))->bind(5,$order)->execute();
        jsonOut(['success' => true, 'id' => $db->lastInsertId()]);

    // ── Delete playlist item ──────────────────────────────────────────────
    case 'delete_playlist_item':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $id = (int)($body['id'] ?? 0);
        $db->query("DELETE FROM learn_playlist_items WHERE id = ?")->bind(1,$id)->execute();
        jsonOut(['success' => true]);

    // ── Toggle like ───────────────────────────────────────────────────────
    case 'toggle_like':
        $id = (int)($body['id'] ?? 0);
        $existing = $db->single("SELECT 1 FROM learn_likes WHERE user_id=? AND content_id=?",[$userId,$id]);
        if ($existing) {
            $db->query("DELETE FROM learn_likes WHERE user_id=? AND content_id=?")->bind(1,$userId)->bind(2,$id)->execute();
            $liked = false;
        } else {
            $db->query("INSERT IGNORE INTO learn_likes (user_id,content_id) VALUES (?,?)")->bind(1,$userId)->bind(2,$id)->execute();
            $liked = true;
        }
        $count = $db->single("SELECT COUNT(*) as c FROM learn_likes WHERE content_id=?",[$id])['c'] ?? 0;
        jsonOut(['success' => true, 'liked' => $liked, 'count' => $count]);

    // ── Mark complete ─────────────────────────────────────────────────────
    case 'mark_complete':
        $id = (int)($body['id'] ?? 0);
        $db->query("INSERT INTO learn_progress (user_id,content_id,completed) VALUES (?,?,1) ON DUPLICATE KEY UPDATE completed=1, last_accessed=NOW()")
           ->bind(1,$userId)->bind(2,$id)->execute();
        jsonOut(['success' => true]);

    // ── Submit quiz ───────────────────────────────────────────────────────
    case 'submit_quiz':
        $cid     = (int)($body['content_id'] ?? 0);
        $answers = $body['answers'] ?? []; // {question_id: option_id}
        if (!$cid || empty($answers)) jsonOut(['success' => false, 'message' => 'Answers required']);

        $questions = $db->resultSet("SELECT id FROM learn_quiz_questions WHERE content_id=?",[$cid]);
        $total     = count($questions);
        $correct   = 0;

        foreach ($questions as $q) {
            $qid      = $q['id'];
            $selected = (int)($answers[$qid] ?? 0);
            $ans      = $db->single("SELECT is_correct FROM learn_quiz_options WHERE id=? AND question_id=?",[$selected,$qid]);
            if ($ans && $ans['is_correct']) $correct++;
        }

        $score    = $total > 0 ? round($correct / $total * 100) : 0;
        $passReq  = $db->single("SELECT pass_score FROM learn_content WHERE id=?",[$cid])['pass_score'] ?? 70;
        $passed   = $score >= $passReq;

        $db->query("INSERT INTO learn_quiz_attempts (user_id,content_id,score,total,passed) VALUES (?,?,?,?,?)")
           ->bind(1,$userId)->bind(2,$cid)->bind(3,$score)->bind(4,$total)->bind(5,(int)$passed)->execute();

        $certCode = null;
        if ($passed) {
            $existing = $db->single("SELECT certificate_code FROM learn_certificates WHERE user_id=? AND content_id=?",[$userId,$cid]);
            if (!$existing) {
                $certCode = strtoupper(bin2hex(random_bytes(8)));
                $db->query("INSERT INTO learn_certificates (user_id,content_id,certificate_code,score) VALUES (?,?,?,?)")
                   ->bind(1,$userId)->bind(2,$cid)->bind(3,$certCode)->bind(4,$score)->execute();
                $db->query("INSERT INTO learn_progress (user_id,content_id,completed) VALUES (?,?,1) ON DUPLICATE KEY UPDATE completed=1")
                   ->bind(1,$userId)->bind(2,$cid)->execute();
            } else {
                $certCode = $existing['certificate_code'];
            }
        }

        jsonOut(['success' => true, 'score' => $score, 'correct' => $correct, 'total' => $total,
                 'passed' => $passed, 'pass_score' => $passReq, 'certificate_code' => $certCode]);

    // ── My learning progress ──────────────────────────────────────────────
    case 'my_progress':
        $completed = $db->resultSet(
            "SELECT lp.content_id, lp.completed, lp.last_accessed, lc.title, lc.type, lc.thumbnail_url
             FROM learn_progress lp
             JOIN learn_content lc ON lp.content_id = lc.id
             WHERE lp.user_id = ?
             ORDER BY lp.last_accessed DESC LIMIT 12",
            [$userId]
        );
        $certs = $db->resultSet(
            "SELECT lce.*, lc.title, lc.type
             FROM learn_certificates lce
             JOIN learn_content lc ON lce.content_id = lc.id
             WHERE lce.user_id = ?
             ORDER BY lce.issued_at DESC",
            [$userId]
        );
        jsonOut(['success' => true, 'progress' => $completed, 'certificates' => $certs]);

    // ── Get categories ────────────────────────────────────────────────────
    case 'categories':
        $cats = $db->resultSet("SELECT * FROM learn_categories ORDER BY sort_order ASC", []);
        jsonOut(['success' => true, 'categories' => $cats]);

    // ── Officer: my content list ──────────────────────────────────────────
    case 'my_content':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $rows = $db->resultSet(
            "SELECT lc.*, cat.name as cat_name,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id = lc.id) as like_count,
                    (SELECT COUNT(*) FROM learn_progress lp WHERE lp.content_id = lc.id AND lp.completed = 1) as completions
             FROM learn_content lc
             LEFT JOIN learn_categories cat ON lc.category_id = cat.id
             WHERE lc.created_by = ?
             ORDER BY lc.created_at DESC",
            [$userId]
        );
        jsonOut(['success' => true, 'items' => $rows]);

    // ── AI-generated farming tips ─────────────────────────────────────────
    case 'ai_tips':
        $crop    = trim($body['crop'] ?? $_GET['crop'] ?? 'general farming');
        $season  = trim($body['season'] ?? $_GET['season'] ?? '');
        $langPfx = $body['lang'] ?? 'en';

        $langNote = $langPfx === 'bn'
            ? 'Respond entirely in Bangla (Bengali script).'
            : 'Respond in English.';

        $prompt = "Give 5 practical, actionable farming tips for a Bangladesh farmer growing $crop"
                . ($season ? " in the $season season" : '')
                . ". Format as a numbered list with bold headings. Each tip should be 1–2 sentences. $langNote";

        $groqKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
        $tips    = 'AI tips unavailable.';

        if ($groqKey) {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'model'       => 'llama-3.1-8b-instant',
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You are Chashi Bhai, an expert agricultural assistant for Bangladesh farmers.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 600,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $groqKey],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $res  = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            $tips = $data['choices'][0]['message']['content'] ?? $tips;
        }

        jsonOut(['success' => true, 'tips' => $tips]);

    // ── Add quiz question (officer) ───────────────────────────────────────
    case 'add_question':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $cid  = (int)($body['content_id'] ?? 0);
        $q    = trim($body['question'] ?? '');
        $expl = trim($body['explanation'] ?? '');
        $opts = $body['options'] ?? [];
        if (!$cid || !$q || count($opts) < 2) jsonOut(['success' => false, 'message' => 'Question, content_id and ≥2 options required']);

        $order = $db->single("SELECT COUNT(*) as c FROM learn_quiz_questions WHERE content_id=?",[$cid])['c'] ?? 0;
        $db->query("INSERT INTO learn_quiz_questions (content_id,question,explanation,sort_order) VALUES (?,?,?,?)")
           ->bind(1,$cid)->bind(2,$q)->bind(3,$expl)->bind(4,$order)->execute();
        $qId = $db->lastInsertId();

        foreach ($opts as $opt) {
            $otext = trim($opt['text'] ?? '');
            if (!$otext) continue;
            $db->query("INSERT INTO learn_quiz_options (question_id,option_text,is_correct) VALUES (?,?,?)")
               ->bind(1,$qId)->bind(2,$otext)->bind(3,(int)($opt['correct']??0))->execute();
        }
        jsonOut(['success' => true, 'question_id' => $qId]);

    // ── Delete quiz question ──────────────────────────────────────────────
    case 'delete_question':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $id = (int)($body['id'] ?? 0);
        $db->query("DELETE FROM learn_quiz_questions WHERE id = ?")->bind(1,$id)->execute();
        jsonOut(['success' => true]);

    // ── Toggle featured flag (officer/admin) ──────────────────────────────
    case 'toggle_featured':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Access denied']);
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID required']);
        $item = $db->single("SELECT id, is_featured FROM learn_content WHERE id = ?", [$id]);
        if (!$item) jsonOut(['success' => false, 'message' => 'Not found']);
        $newVal = $item['is_featured'] ? 0 : 1;
        $db->query("UPDATE learn_content SET is_featured = ? WHERE id = ?")->bind(1,$newVal)->bind(2,$id)->execute();
        jsonOut(['success' => true, 'is_featured' => $newVal]);

    // ── Admin: list all content with author info ──────────────────────────
    case 'admin_list':
        if ($role !== 'admin') jsonOut(['success' => false, 'message' => 'Admin only']);
        $type   = $_GET['type']   ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $search = trim($_GET['q'] ?? '');
        $pg     = max(1, (int)($_GET['p'] ?? 1));
        $limit  = 20;
        $offset = ($pg - 1) * $limit;
        $where  = ['1=1'];
        $params = [];
        if ($type !== 'all')        { $where[] = 'lc.type = ?';       $params[] = $type; }
        if ($status === 'published') { $where[] = 'lc.is_published=1'; }
        if ($status === 'draft')     { $where[] = 'lc.is_published=0'; }
        if ($search) {
            $where[] = '(lc.title LIKE ? OR lc.title_bn LIKE ?)';
            $params = array_merge($params, ["%$search%", "%$search%"]);
        }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $total = $db->single("SELECT COUNT(*) as c FROM learn_content lc $ws", $params)['c'] ?? 0;
        $rows  = $db->resultSet(
            "SELECT lc.id, lc.type, lc.title, lc.is_published, lc.is_featured, lc.views, lc.created_at,
                    u.first_name, u.last_name, u.role as author_role,
                    (SELECT COUNT(*) FROM learn_likes ll WHERE ll.content_id=lc.id) as likes,
                    (SELECT COUNT(*) FROM learn_progress lp WHERE lp.content_id=lc.id AND lp.completed=1) as completions,
                    (SELECT COUNT(*) FROM learn_certificates lc2 WHERE lc2.content_id=lc.id) as certs
             FROM learn_content lc
             LEFT JOIN users u ON lc.created_by=u.user_id
             $ws ORDER BY lc.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );
        jsonOut(['success'=>true,'items'=>$rows,'total'=>$total,'pages'=>ceil($total/$limit),'page'=>$pg]);

    // ── Admin: learning center stats ──────────────────────────────────────
    case 'admin_stats':
        if ($role !== 'admin') jsonOut(['success' => false, 'message' => 'Admin only']);
        $stats = [
            'total'       => (int)($db->single("SELECT COUNT(*) as c FROM learn_content")['c']            ?? 0),
            'published'   => (int)($db->single("SELECT COUNT(*) as c FROM learn_content WHERE is_published=1")['c'] ?? 0),
            'featured'    => (int)($db->single("SELECT COUNT(*) as c FROM learn_content WHERE is_featured=1")['c']  ?? 0),
            'views'       => (int)($db->single("SELECT COALESCE(SUM(views),0) as c FROM learn_content")['c']        ?? 0),
            'completions' => (int)($db->single("SELECT COUNT(*) as c FROM learn_progress WHERE completed=1")['c']   ?? 0),
            'certs'       => (int)($db->single("SELECT COUNT(*) as c FROM learn_certificates")['c']                 ?? 0),
        ];
        $typeBreak = $db->resultSet("SELECT type, COUNT(*) as cnt FROM learn_content GROUP BY type ORDER BY cnt DESC");
        jsonOut(['success'=>true,'stats'=>$stats,'type_breakdown'=>$typeBreak]);

    case 'upload_thumbnail':
        if (!isOfficer()) jsonOut(['success' => false, 'message' => 'Officers only']);
        $file = $_FILES['thumbnail'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonOut(['success' => false, 'message' => 'No file uploaded']);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            jsonOut(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, GIF or WebP.']);
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            jsonOut(['success' => false, 'message' => 'File too large (max 3 MB)']);
        }
        $dir = __DIR__ . '/../public/uploads/learn/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = 'thumb_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
            jsonOut(['success' => true, 'url' => 'public/uploads/learn/' . $name]);
        }
        jsonOut(['success' => false, 'message' => 'Failed to save file']);

    default:
        jsonOut(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
