<?php
/**
 * Shared Conversation — Public Read-Only View
 * URL: agent/share.php?token=<share_token>
 * No login required.
 */
require_once __DIR__ . '/../config/config.php';

$token = trim($_GET['token'] ?? '');
if (!$token || strlen($token) > 128 || !preg_match('/^[a-f0-9]+$/i', $token)) {
    http_response_code(404);
    showNotFound();
}

$db    = new Database();
$convo = null;
$messages = [];

try {
    $convo = $db->single(
        "SELECT c.conversation_id, c.title, c.created_at, u.first_name, u.last_name
         FROM agent_conversations c
         JOIN users u ON u.user_id = c.user_id
         WHERE c.share_token = ?",
        [$token]
    );
    if ($convo) {
        $messages = $db->resultSet(
            "SELECT role, content, images, created_at
             FROM agent_messages
             WHERE conversation_id = ? ORDER BY created_at ASC",
            [$convo['conversation_id']]
        );
        $messages = array_map(function ($m) {
            $m['images'] = (!empty($m['images'])) ? json_decode($m['images'], true) : [];
            return $m;
        }, $messages);
    }
} catch (Exception $e) {}

if (!$convo) {
    http_response_code(404);
    showNotFound();
}

$pageTitle  = htmlspecialchars($convo['title'] ?? 'Conversation');
$authorName = htmlspecialchars(trim(($convo['first_name'] ?? '') . ' ' . ($convo['last_name'] ?? '')));
$createdAt  = date('F j, Y', strtotime($convo['created_at'] ?? 'now'));

function showNotFound(): never {
    global $base_url;
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Not Found — Smart Chashi</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background:#111827; color:#e5e7eb;
       display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
.box { text-align:center; padding:40px 24px; }
.box h2 { font-size:22px; margin-bottom:10px; color:#f9fafb; }
.box p { font-size:14px; color:#6b7280; margin-bottom:24px; }
.btn { display:inline-block; background:linear-gradient(135deg,#1a9c50,#2ecc71);
       color:#fff; text-decoration:none; padding:10px 24px; border-radius:8px; font-size:13px; }
</style>
</head>
<body>
<div class="box">
    <h2>Conversation not found</h2>
    <p>This share link may have been revoked or is invalid.</p>
    <a href="<?php echo htmlspecialchars($base_url); ?>" class="btn">Go to Smart Chashi</a>
</div>
</body>
</html><?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> — Chashi Bhai AI</title>
<meta name="description" content="Shared conversation with Chashi Bhai — Smart Chashi Agricultural AI">
<link rel="icon" href="<?php echo $base_url; ?>agent/assets/logo.png">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #111827; color: #e5e7eb; font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }

/* ── Top bar ── */
.share-topbar {
    background: rgba(13,17,23,0.95); border-bottom: 1px solid rgba(255,255,255,0.06);
    backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 100;
    padding: 12px 20px; display: flex; align-items: center; gap: 12px;
}
.share-logo { width: 28px; height: 28px; border-radius: 7px; }
.share-brand { font-size: 14px; font-weight: 700; color: #2ecc71; }
.share-cta {
    margin-left: auto;
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: #fff; text-decoration: none; font-size: 13px; font-weight: 500;
    padding: 7px 18px; border-radius: 8px; display: flex; align-items: center; gap: 5px;
    transition: opacity 0.15s;
}
.share-cta:hover { opacity: 0.88; }
.share-cta .material-icons { font-size: 16px; }

/* ── Meta ── */
.share-meta {
    max-width: 800px; margin: 28px auto 6px; padding: 0 20px;
}
.share-meta h1 { font-size: 20px; font-weight: 700; color: #f9fafb; margin-bottom: 6px; }
.share-meta-info { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.share-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);
    color: #4ade80; font-size: 10px; font-weight: 600; padding: 3px 8px;
    border-radius: 12px; text-transform: uppercase; letter-spacing: 0.05em;
}
.share-badge .material-icons { font-size: 12px; }

/* ── Messages ── */
.share-messages { max-width: 800px; margin: 0 auto; padding: 16px 20px 60px; }

.msg-row {
    display: flex; gap: 12px; align-items: flex-start;
    margin-bottom: 16px;
}
.msg-row.user { flex-direction: row-reverse; }

.msg-icon {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: center;
    margin-top: 2px;
}
.msg-icon img { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; }
.msg-icon svg { width: 18px; height: 18px; fill: #2ecc71; }
.msg-row.user .msg-icon { background: rgba(46,204,113,0.2); }

.msg-bubble {
    flex: 1; max-width: calc(100% - 44px);
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 4px 18px 18px 18px;
    padding: 12px 16px; font-size: 14px; line-height: 1.65;
    color: #e5e7eb; word-break: break-word;
}
.msg-row.user .msg-bubble {
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: white; border-radius: 18px 4px 18px 18px;
}
.msg-bubble p { margin: 0 0 8px; color: #d1d5db; }
.msg-bubble p:last-child { margin-bottom: 0; }
.msg-row.user .msg-bubble p { color: rgba(255,255,255,0.92); }
.msg-bubble h2, .msg-bubble h3, .msg-bubble h4 { color: #f0fdf4; margin: 12px 0 6px; }
.msg-bubble ul, .msg-bubble ol { padding-left: 20px; margin: 6px 0; }
.msg-bubble li { margin: 3px 0; color: #d1d5db; }
.msg-bubble strong { color: #f3f4f6; }
.msg-bubble hr { border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 12px 0; }
.msg-bubble pre {
    background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; padding: 12px 14px; margin: 10px 0; overflow-x: auto;
    font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.5;
}
.msg-bubble code {
    background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
    padding: 1px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #86efac;
}

.msg-meta { margin-top: 4px; font-size: 11px; color: #6b7280; }
.msg-row.user .msg-meta { text-align: right; }

/* Images */
.msg-imgs-wrap { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.msg-img {
    max-width: 180px; max-height: 160px; border-radius: 10px;
    display: block; border: 1px solid rgba(255,255,255,0.1); object-fit: cover;
    cursor: zoom-in; transition: transform 0.15s;
}
.msg-img:hover { transform: scale(1.03); }

/* Disease card */
.sc-disease-card { border-radius: 12px; overflow: hidden; margin-bottom: 8px; }

/* ── Footer CTA ── */
.share-footer {
    text-align: center; padding: 32px 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    color: #6b7280; font-size: 13px;
}
.share-footer p { margin-bottom: 16px; }
.share-footer a {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: #fff; text-decoration: none; font-size: 14px; font-weight: 500;
    padding: 10px 24px; border-radius: 10px; transition: opacity 0.15s;
}
.share-footer a:hover { opacity: 0.88; }
.share-footer a .material-icons { font-size: 18px; }

/* Lightbox */
.lightbox {
    position: fixed; inset: 0; background: rgba(0,0,0,0.88); z-index: 9999;
    display: none; align-items: center; justify-content: center; cursor: zoom-out;
}
.lightbox.show { display: flex; }
.lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }

@media (max-width: 600px) {
    .share-brand { display: none; }
    .msg-bubble { font-size: 13px; }
}
</style>
</head>
<body>

<div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="">
</div>

<nav class="share-topbar">
    <img src="<?php echo $base_url; ?>agent/assets/logo.png" class="share-logo" alt="Logo">
    <span class="share-brand">Chashi Bhai AI</span>
    <a href="<?php echo $base_url; ?>agent/chat/" class="share-cta">
        <span class="material-icons">chat</span>
        Chat with Chashi Bhai
    </a>
</nav>

<div class="share-meta">
    <h1><?php echo $pageTitle; ?></h1>
    <div class="share-meta-info">
        <span class="share-badge"><span class="material-icons">share</span> Shared</span>
        <?php if ($authorName): ?>
        <span>By <?php echo $authorName; ?></span>
        <?php endif; ?>
        <span><?php echo $createdAt; ?></span>
        <span><?php echo count($messages); ?> messages</span>
    </div>
</div>

<div class="share-messages">
<?php foreach ($messages as $m):
    $isUser = $m['role'] === 'user';
    $timeStr = date('g:i A', strtotime($m['created_at']));
?>
<div class="msg-row <?php echo $isUser ? 'user' : 'assistant'; ?>">
    <div class="msg-icon">
        <?php if ($isUser): ?>
        <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 4v2h16v-2c0-1.33-2.67-4-8-4z"/></svg>
        <?php else: ?>
        <img src="<?php echo $base_url; ?>agent/assets/logo.png" alt="AI">
        <?php endif; ?>
    </div>
    <div style="flex:1;min-width:0;">
        <?php if (!empty($m['images'])): ?>
        <div class="msg-imgs-wrap">
            <?php foreach (array_slice($m['images'], 0, 4) as $imgPath): ?>
            <img src="<?php echo htmlspecialchars($base_url . $imgPath); ?>"
                 class="msg-img" loading="lazy"
                 onclick="document.getElementById('lightboxImg').src=this.src;document.getElementById('lightbox').classList.add('show')">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($m['content']): ?>
        <div class="msg-bubble">
            <?php echo $isUser ? nl2br(htmlspecialchars($m['content'])) : $m['content']; ?>
        </div>
        <?php endif; ?>
        <div class="msg-meta"><?php echo $timeStr; ?></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="share-footer">
    <p>This conversation was shared from <strong>Smart Chashi</strong> — AI-powered agricultural assistant for Bangladesh farmers.</p>
    <a href="<?php echo $base_url; ?>agent/chat">
        <span class="material-icons">agriculture</span>
        Ask Chashi Bhai your farming questions
    </a>
</div>

</body>
</html>
