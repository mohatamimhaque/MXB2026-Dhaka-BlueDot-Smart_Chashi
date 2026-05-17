<?php
/**
 * Chashi Bhai AI Chat — ChatGPT-style full page
 * URL: agent/chat.php              → new chat
 * URL: agent/chat.php?cid=xxxx     → load conversation
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . $base_url . '?page=login');
    exit;
}

$currentLang = get_language();
$currentUser = getCurrentUser();
$cid         = trim($_GET['cid'] ?? '');

// Validate conversation ownership
$activeConvo  = null;
$initMessages = [];
if ($cid) {
    $db = new Database();
    $activeConvo = $db->single(
        "SELECT * FROM agent_conversations WHERE conversation_id = ? AND user_id = ?",
        [$cid, $_SESSION['user_id']]
    );
    if ($activeConvo) {
        $MSGS_PER_PAGE   = 20;
        $totalInitMsgs   = (int)($db->single("SELECT COUNT(*) AS cnt FROM agent_messages WHERE conversation_id = ?", [$cid])['cnt'] ?? 0);
        $initMessages    = $db->resultSet(
            "SELECT id, role, content, images, feedback, created_at FROM agent_messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT {$MSGS_PER_PAGE}",
            [$cid]
        );
        $initMessages    = array_reverse($initMessages);
        $initMessages    = array_map(function ($m) {
            $m['images'] = isset($m['images']) && $m['images'] ? json_decode($m['images'], true) : [];
            return $m;
        }, $initMessages);
        $initHasMore     = $totalInitMsgs > $MSGS_PER_PAGE;
        $initMsgOffset   = $MSGS_PER_PAGE; // next page starts here
    } else {
        $cid = '';
    }
}

$pageTitle = $activeConvo ? htmlspecialchars($activeConvo['title']) : 'Chashi Bhai AI';
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> — Smart Chashi</title>
<meta name="theme-color" content="#1a2634">

<link rel="icon" href="<?php echo $base_url; ?>agent/assets/logo.png">
<link rel="stylesheet" href="<?php echo $base_url; ?>agent/assets/css/google-font.css">
<link
  rel="stylesheet"
  href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
  <!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
/* ── Reset & base ────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body {
    font-family: 'Poppins', sans-serif;
    background: #111827;
    color: #e5e7eb;
    display: flex;
    height: 100vh;
}

/* ── App shell ───────────────────────────────────────────────────── */
.chat-app { display: flex; width: 100%; height: 100vh; overflow: hidden; }

/* ── Sidebar ─────────────────────────────────────────────────────── */
.sidebar {
    width: 260px; flex-shrink: 0;
    background: #0d1117;
    display: flex; flex-direction: column;
    border-right: 1px solid rgba(255,255,255,0.06);
    transition: width 0.25s ease, transform 0.25s ease;
    overflow: hidden;
    position: relative; z-index: 20;
}
.sidebar.collapsed { width: 0; transform: translateX(-260px); }

.sidebar-top {
    padding: 14px 12px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}
.brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
.brand img { width: 28px; height: 28px; border-radius: 6px; }
.brand-name { font-size: 14px; font-weight: 600; color: #2ecc71; white-space: nowrap; }

.btn-new-chat {
    display: flex; align-items: center; justify-content: center;
    background: rgba(46,204,113,0.08); border: 1px solid rgba(46,204,113,0.25);
    color: #2ecc71; width: 32px; height: 32px;
    border-radius: 8px; cursor: pointer;
    transition: all 0.15s; flex-shrink: 0;
}
.btn-new-chat:hover { background: rgba(46,204,113,0.18); border-color: rgba(46,204,113,0.5); }
.btn-new-chat .material-icons { font-size: 18px; }
.sidebar-conv-count {
    font-size: 10px; color: #4b5563; font-weight: 500; white-space: nowrap;
}

.conv-list {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 8px 0;
    scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.conv-list::-webkit-scrollbar { width: 4px; }
.conv-list::-webkit-scrollbar-track { background: transparent; }
.conv-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

.conv-group-label {
    font-size: 10px; font-weight: 600; color: #4b5563;
    text-transform: uppercase; letter-spacing: 0.09em;
    padding: 12px 14px 3px;
    display: flex; align-items: center; gap: 8px;
}
.conv-group-label::after {
    content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.05);
}
.conv-item {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 8px 7px 10px;
    border-radius: 8px; margin: 1px 6px;
    border: 1px solid transparent;
    cursor: pointer; position: relative;
    transition: background 0.12s, border-color 0.12s;
    text-decoration: none;
}
.conv-item:hover { background: rgba(255,255,255,0.06); }
.conv-item.active { background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.18); }
.conv-item.active::before {
    content: ''; position: absolute; left: -1px; top: 6px; bottom: 6px;
    width: 3px; background: #2ecc71; border-radius: 0 3px 3px 0;
}
.conv-icon {
    width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0;
    background: rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    transition: background 0.12s;
}
.conv-item:hover .conv-icon { background: rgba(255,255,255,0.08); }
.conv-item.active .conv-icon { background: rgba(46,204,113,0.15); }
.conv-icon .material-icons { font-size: 14px; color: #6b7280; }
.conv-item.active .conv-icon .material-icons { color: #4ade80; }
.conv-body {
    flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px;
}
.conv-title {
    font-size: 12.5px; color: #c9d1da;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.35; font-weight: 400;
}
.conv-item.active .conv-title { color: #ecfdf5; font-weight: 500; }
.conv-time {
    font-size: 10px; color: #4b5563; line-height: 1;
}
.conv-item.active .conv-time { color: #6b8c7a; }
.conv-actions {
    display: none; gap: 1px; flex-shrink: 0;
}
.conv-item:hover .conv-actions { display: flex; }
.conv-btn {
    width: 22px; height: 22px; border: none; background: none;
    color: #9ca3af; cursor: pointer; border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.12s;
}
.conv-btn:hover { background: rgba(255,255,255,0.1); color: #d1d5db; }
.conv-btn.del:hover { color: #ef4444; background: rgba(239,68,68,0.1); }
.conv-btn .material-icons { font-size: 13px; }

.sidebar-user {
    padding: 12px 14px;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.user-avatar-sm {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #2ecc71, #1a9c50);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 600; color: white; flex-shrink: 0;
}
.user-info { flex: 1; min-width: 0; }
.user-name-sm { font-size: 12px; font-weight: 600; color: #e5e7eb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role-sm { font-size: 11px; color: #6b7280; }
.btn-back {
    background: none; border: none; color: #6b7280; cursor: pointer;
    display: flex; align-items: center; padding: 4px;
    border-radius: 4px; transition: color 0.15s;
}
.btn-back:hover { color: #2ecc71; }
.btn-back .material-icons { font-size: 18px; }

/* ── Main area ───────────────────────────────────────────────────── */
.chat-main {
    flex: 1; display: flex; flex-direction: column;
    min-width: 0; background: #111827; position: relative;
}

/* ── Topbar ──────────────────────────────────────────────────────── */
.chat-topbar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px;
    background: rgba(13,17,23,0.8); backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0; min-height: 52px;
}
.btn-toggle-sidebar {
    background: none; border: none; color: #9ca3af; cursor: pointer;
    display: flex; align-items: center; padding: 5px;
    border-radius: 6px; transition: all 0.15s; flex-shrink: 0;
}
.btn-toggle-sidebar:hover { background: rgba(255,255,255,0.08); color: #e5e7eb; }
.btn-toggle-sidebar .material-icons { font-size: 22px; }

.chat-title-wrap { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
#chatTitleText {
    font-size: 15px; font-weight: 600; color: #f3f4f6;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px;
}
.title-edit-input {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(46,204,113,0.4);
    color: #f3f4f6; font-size: 14px; font-family: inherit; font-weight: 600;
    padding: 4px 10px; border-radius: 6px; outline: none; width: 300px; max-width: 60vw;
}

.topbar-actions { display: flex; gap: 3px; flex-shrink: 0; align-items: center; }
.topbar-btn {
    display: flex; align-items: center; justify-content: center;
    background: none; border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-family: inherit;
    width: 32px; height: 32px; padding: 0;
    border-radius: 7px; cursor: pointer;
    transition: all 0.15s; flex-shrink: 0;
}
.topbar-btn .material-icons { font-size: 18px; }
/* Hide text-only children (labels) inside icon buttons */
.topbar-btn > span:not(.material-icons) { display: none; }
.topbar-btn:hover { background: rgba(255,255,255,0.08); color: #e5e7eb; }
.topbar-btn.del:hover { border-color: rgba(239,68,68,0.4); color: #ef4444; }
.topbar-btn.new { border-color: rgba(46,204,113,0.35); color: #2ecc71; }
.topbar-btn.new:hover { background: rgba(46,204,113,0.1); }

/* ── Messages area ───────────────────────────────────────────────── */
.messages-wrap {
    flex: 1; overflow-y: auto; padding: 24px 0;
    scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.messages-wrap::-webkit-scrollbar { width: 5px; }
.messages-wrap::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

/* ── Welcome screen ──────────────────────────────────────────────── */
.welcome-screen {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; height: 100%; padding: 40px 20px; text-align: center;
}
.welcome-logo { width: 72px; height: 72px; border-radius: 20px; margin-bottom: 20px; animation: logoPulse 3s ease-in-out infinite; }
@keyframes logoPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
.welcome-title { font-size: 26px; font-weight: 700; color: #f9fafb; margin-bottom: 8px; }
.welcome-sub { font-size: 14px; color: #6b7280; margin-bottom: 32px; max-width: 400px; line-height: 1.6; }
.suggestions-label {
    font-size: 11px; color: #4b5563; font-weight: 600; letter-spacing: 0.08em;
    text-transform: uppercase; margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
}
.suggestions-label .material-icons { font-size: 14px; }
.suggestions {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 10px; max-width: 600px; width: 100%;
}
.suggestion-card {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px; padding: 14px 16px; cursor: pointer; text-align: left;
    transition: all 0.22s; display: flex; flex-direction: column; gap: 10px;
    animation: suggFadeUp 0.4s ease both;
}
.suggestion-card:nth-child(1) { animation-delay: 0.0s; }
.suggestion-card:nth-child(2) { animation-delay: 0.07s; }
.suggestion-card:nth-child(3) { animation-delay: 0.14s; }
.suggestion-card:nth-child(4) { animation-delay: 0.21s; }
@keyframes suggFadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
.suggestion-card:hover {
    background: rgba(46,204,113,0.08); border-color: rgba(46,204,113,0.3);
    transform: translateY(-2px); box-shadow: 0 4px 16px rgba(46,204,113,0.08);
}
.suggestion-icon-wrap {
    width: 38px; height: 38px; border-radius: 10px;
    background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.18);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.suggestion-icon-wrap .material-icons { font-size: 19px; color: #4ade80; }
.suggestion-text { font-size: 13px; color: #9ca3af; line-height: 1.4; font-weight: 500; }
@media (max-width: 480px) { .suggestions { grid-template-columns: 1fr; } }

/* ── Message rows ────────────────────────────────────────────────── */
.msg-row {
    max-width: 800px; margin: 0 auto; padding: 6px 20px;
    display: flex; gap: 12px; align-items: flex-start;
}
.msg-bubble-wrap { flex: 1; min-width: 0; width: 100%; }
.msg-row.user { flex-direction: row-reverse; }
.msg-row.user .msg-bubble {
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: white; border-radius: 18px 4px 18px 18px;
}
.msg-row.user .msg-icon { background: rgba(46,204,113,0.2); }

.msg-icon {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center;
    margin-top: 2px;
}
.msg-icon img { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; }
.msg-icon svg { width: 18px; height: 18px; fill: #2ecc71; }

.msg-bubble {
    flex: 1; max-width: calc(100% - 44px);
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 4px 18px 18px 18px;
    padding: 14px 18px; font-size: 14px; line-height: 1.72;
    color: #e5e7eb; word-break: break-word;
}

/* ── AI bubble — clean borderless look ──────────────────────────── */
.msg-row.assistant .msg-bubble {
    background: none;
    border: none;
    box-shadow: none;
    padding-left: 4px;
}

/* Paragraphs */
.msg-bubble p { margin: 0 0 9px; color: #d1d5db; }
.msg-bubble p:last-child { margin-bottom: 0; }

/* Headings — green hierarchy */
.msg-bubble h2 {
    font-size: 16px; font-weight: 700; color: #4ade80 !important;
    margin: 16px 0 7px; padding-bottom: 6px;
    border-bottom: 1px solid rgba(74,222,128,0.22);
}
.msg-bubble h3 {
    font-size: 15px; font-weight: 600; color: #34d399 !important;
    margin: 13px 0 5px;
}
.msg-bubble h4 {
    font-size: 14px; font-weight: 600; color: #6ee7b7 !important;
    margin: 11px 0 4px;
}
.msg-bubble h5 {
    font-size: 13px; font-weight: 600; color: #a7f3d0 !important;
    margin: 9px 0 4px;
}

/* Emphasis */
.msg-bubble strong { color: #f9fafb !important; font-weight: 600; }
.msg-bubble em { color: #93c5fd; font-style: italic; }

/* Lists — custom bullets */
.msg-bubble ul { list-style: none; padding-left: 6px; margin: 6px 0; }
.msg-bubble ul li {
    padding: 2px 0 2px 18px; position: relative; color: #e2e8f0; line-height: 1.65;
}
.msg-bubble ul li::before {
    content: '▸'; position: absolute; left: 2px; color: #2ecc71;
    font-size: 10px; top: 6px; line-height: 1;
}
.msg-bubble ol {
    list-style: none; padding-left: 6px; margin: 6px 0;
    counter-reset: ol-counter;
}
.msg-bubble ol li {
    padding: 2px 0 2px 26px; position: relative; color: #e2e8f0;
    counter-increment: ol-counter; line-height: 1.65;
}
.msg-bubble ol li::before {
    content: counter(ol-counter) '.'; position: absolute; left: 2px;
    color: #4ade80; font-size: 12px; font-weight: 700; min-width: 18px;
}

/* Nested lists */
.msg-bubble li li { color: #cbd5e1; }
.msg-bubble li li::before { color: #86efac; }

/* HR */
.msg-bubble hr {
    border: none; height: 1px; margin: 14px 0;
    background: linear-gradient(90deg, transparent, rgba(46,204,113,0.5), transparent);
}

/* Inline code */
.msg-bubble code.inline-code {
    background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.28);
    padding: 1px 7px; border-radius: 5px; font-family: 'Courier New', monospace;
    font-size: 12px; color: #86efac;
}

/* Code block */
.msg-bubble pre.code-block {
    background: #0d1117; border: 1px solid rgba(255,255,255,0.08);
    border-top: 2px solid #2ecc71; border-radius: 8px;
    padding: 13px 15px; margin: 12px 0; overflow-x: auto;
    line-height: 1.6;
}
.msg-bubble pre.code-block code {
    font-family: 'Courier New', monospace; font-size: 12px; color: #e6edf3;
}
.msg-meta {
    margin-top: 4px; font-size: 11px; color: #6b7280;
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.msg-row.user .msg-meta { justify-content: flex-end; }
.meta-lang {
    background: rgba(46,204,113,0.12); color: #4ade80;
    padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 500;
}
.meta-voice {
    background: rgba(99,102,241,0.15); color: #a5b4fc;
    padding: 2px 7px; border-radius: 10px; font-size: 10px;
    display: flex; align-items: center; gap: 3px;
}
.msg-actions {
    display: none; gap: 4px; align-items: center; margin-left: auto;
}
.msg-row:hover .msg-actions { display: flex; }
.msg-act-btn {
    background: none; border: none; color: #6b7280; cursor: pointer;
    padding: 2px 4px; border-radius: 4px; transition: color 0.15s;
    display: flex; align-items: center;
}
.msg-act-btn:hover { color: #2ecc71; }
.msg-act-btn .material-icons { font-size: 15px; }

/* Typing indicator */
.typing-row {
    max-width: 800px; margin: 0 auto; padding: 6px 20px;
    display: flex; gap: 12px; align-items: flex-start;
}
.typing-bubble {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 4px 18px 18px 18px; padding: 14px 16px;
    display: flex; gap: 5px; align-items: center;
}
.typing-dot {
    width: 7px; height: 7px; background: #4ade80; border-radius: 50%;
    animation: typingBounce 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce {
    0%,80%,100% { transform: translateY(0); opacity: 0.4; }
    40%          { transform: translateY(-6px); opacity: 1; }
}

/* ── Input area ──────────────────────────────────────────────────── */
.input-area {
    padding: 12px 20px 16px; background: #111827;
    border-top: 1px solid rgba(255,255,255,0.06); flex-shrink: 0;
}
.input-box-wrap {
    max-width: 800px; margin: 0 auto;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; display: flex; align-items: flex-end; gap: 0;
    transition: border-color 0.2s;
}
.input-box-wrap:focus-within { border-color: rgba(46,204,113,0.5); }
.input-textarea {
    flex: 1; background: none; border: none; outline: none; resize: none;
    color: #f3f4f6; font-size: 14px; font-family: inherit; line-height: 1.5;
    padding: 12px 14px; max-height: 160px; min-height: 44px;
    scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.input-textarea::placeholder { color: #4b5563; }
.input-textarea::-webkit-scrollbar { width: 4px; }
.input-textarea::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }

.input-actions {
    display: flex; align-items: flex-end; padding: 6px 8px; gap: 4px;
}
.input-btn {
    width: 34px; height: 34px; border-radius: 8px; border: none;
    background: none; color: #6b7280; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.input-btn:hover { background: rgba(255,255,255,0.08); color: #d1d5db; }
.input-btn.voice { color: #6b7280; }
.input-btn.voice.recording { color: #ef4444; background: rgba(239,68,68,0.1); animation: pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.5;} }
.input-btn.send {
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: white;
}
.input-btn.send:hover { opacity: 0.9; transform: scale(1.05); }
.input-btn.send:disabled { opacity: 0.4; transform: none; cursor: not-allowed; }
.input-btn .material-icons { font-size: 18px; }
.input-hint { text-align: center; font-size: 11px; color: #6b7280; margin-top: 6px; }

/* ── Voice indicator ─────────────────────────────────────────────── */
.voice-indicator {
    position: fixed; top: 16px; right: 16px; z-index: 999;
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    border-radius: 30px; padding: 8px 14px;
    display: none; align-items: center; gap: 8px;
    box-shadow: 0 4px 20px rgba(46,204,113,0.4);
    animation: voicePulse 2s infinite;
}
.voice-indicator.active { display: flex; }
@keyframes voicePulse { 0%,100%{box-shadow: 0 4px 20px rgba(46,204,113,0.4);} 50%{box-shadow: 0 4px 30px rgba(46,204,113,0.7);} }
.voice-indicator .material-icons { font-size: 16px; color: white; }
.voice-indicator span { font-size: 12px; font-weight: 500; color: white; }
.stop-voice-btn {
    background: rgba(0,0,0,0.2); border: none; color: white; cursor: pointer;
    width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
}
.stop-voice-btn .material-icons { font-size: 14px; }

/* ── Mobile ──────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .sidebar {
        position: fixed; left: 0; top: 0; bottom: 0;
        transform: translateX(-260px); box-shadow: 4px 0 20px rgba(0,0,0,0.5);
    }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 19;
    }
    .sidebar-overlay.active { display: block; }
    .suggestions { grid-template-columns: 1fr; max-width: 340px; }
    .msg-row { padding: 4px 12px; }
    #chatTitleText { font-size: 13px; max-width: 130px; }
    /* Icon-only topbar on mobile */
    .topbar-btn { padding: 6px; min-width: 34px; min-height: 34px; border-radius: 8px; border: none; background: rgba(255,255,255,0.06); justify-content: center; }
    .topbar-btn span:not(.material-icons) { display: none; }
    .topbar-btn .material-icons { font-size: 18px; }
    .topbar-actions { gap: 3px; }
    .personality-select, .lang-toggle-btn { display: none; }
    .topbar-mobile-hide { display: none !important; }
    .chat-topbar { padding: 8px 10px; }
}
@media (max-width: 480px) {
    .topbar-mobile-secondary { display: none !important; }
    .topbar-btn { min-width: 32px; min-height: 32px; padding: 5px; }
}

/* ── Rename modal ────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; padding: 24px; width: 340px; max-width: 90vw;
}
.modal-title { font-size: 15px; font-weight: 600; margin-bottom: 14px; color: #f3f4f6; }
.modal-input {
    width: 100%; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #f3f4f6; font-size: 14px; font-family: inherit;
    padding: 10px 12px; border-radius: 8px; outline: none; margin-bottom: 14px;
}
.modal-input:focus { border-color: rgba(46,204,113,0.5); }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
.modal-btn {
    padding: 8px 18px; border-radius: 8px; border: none;
    font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit;
}
.modal-btn.cancel { background: rgba(255,255,255,0.06); color: #9ca3af; }
.modal-btn.confirm { background: linear-gradient(135deg,#1a9c50,#2ecc71); color: white; }

/* ── Custom alert toast ──────────────────────────────────────────── */
.custom-alert {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
    background: #1f2937; border: 1px solid rgba(239,68,68,0.35); border-radius: 12px;
    padding: 14px 20px; max-width: 380px; width: 90%; z-index: 99999;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    opacity: 0; transition: all 0.3s ease; pointer-events: none;
}
.custom-alert.show { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: all; }
.custom-alert-inner { display: flex; align-items: flex-start; gap: 10px; }
.custom-alert .material-icons { color: #f87171; font-size: 20px; flex-shrink: 0; margin-top: 1px; }
.custom-alert-msg { flex: 1; font-size: 13px; color: #e5e7eb; line-height: 1.5; }
.custom-alert-close { background: none; border: none; color: #6b7280; cursor: pointer; display: flex; padding: 0; }

/* ── Voice speak toggle btn ──────────────────────────────────────── */
.topbar-btn.voice-on { border-color: rgba(99,102,241,0.4) !important; color: #a5b4fc !important; }
.topbar-btn.voice-on:hover { background: rgba(99,102,241,0.1) !important; }

/* ── Mic modal ───────────────────────────────────────────────────── */
.mic-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9998;
    display: none; align-items: center; justify-content: center;
}
.mic-modal.show { display: flex; }
.mic-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; padding: 32px; width: 300px; max-width: 90vw;
    text-align: center;
}
.mic-card h3 { font-size: 16px; margin-bottom: 6px; color: #f3f4f6; }
.mic-card p { font-size: 12px; color: #6b7280; margin-bottom: 20px; }
#recognizedText {
    min-height: 48px; background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08); border-radius: 10px;
    padding: 10px 12px; font-size: 13px; color: #d1d5db; margin-bottom: 16px; line-height: 1.5;
}
.mic-lang-select {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #d1d5db; padding: 6px 10px; border-radius: 8px; font-family: inherit;
    font-size: 13px; margin-bottom: 16px; width: 100%;
}
.mic-record-btn {
    width: 60px; height: 60px; border-radius: 50%; border: none;
    background: linear-gradient(135deg,#1a9c50,#2ecc71);
    color: white; cursor: pointer; margin: 0 auto; display: flex;
    align-items: center; justify-content: center; transition: all 0.2s;
}
.mic-record-btn.recording {
    background: linear-gradient(135deg,#c0392b,#e74c3c);
    animation: pulse 1s infinite;
}
.mic-record-btn .material-icons { font-size: 26px; }
.mic-close { margin-top: 14px; background: none; border: none; color: #6b7280; font-size: 12px; cursor: pointer; font-family: inherit; }

/* ── Personality selector ────────────────────────────────────────── */
.personality-select {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
    color: #9ca3af; font-size: 11px; font-family: inherit;
    padding: 0 4px; border-radius: 6px; cursor: pointer; outline: none;
    transition: all 0.15s; max-width: 110px; height: 32px;
}
.personality-select:hover { border-color: rgba(46,204,113,0.35); color: #d1d5db; }
.personality-select option { background: #1f2937; }

/* ── Lang toggle ─────────────────────────────────────────────────── */
.lang-toggle-btn {
    display: flex; align-items: center; justify-content: center;
    background: none; border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-size: 11px; font-family: inherit;
    height: 32px; padding: 0 7px; border-radius: 6px; cursor: pointer;
    transition: all 0.15s; white-space: nowrap; flex-shrink: 0;
}
.lang-toggle-btn:hover { background: rgba(255,255,255,0.06); color: #d1d5db; }
.lang-toggle-btn.active { border-color: rgba(46,204,113,0.4); color: #2ecc71; background: rgba(46,204,113,0.08); }

/* ── Emoji picker ────────────────────────────────────────────────── */
.emoji-wrap { position: relative; }
.emoji-panel {
    position: absolute; bottom: calc(100% + 8px); right: 0;
    background: #1f2937; border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px; padding: 10px;
    display: none; flex-wrap: wrap; gap: 2px;
    width: 252px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    z-index: 200; max-height: 196px; overflow-y: auto;
}
.emoji-panel.show { display: flex; }
.emoji-btn-item {
    background: none; border: none; font-size: 18px; cursor: pointer;
    width: 34px; height: 34px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.1s;
}
.emoji-btn-item:hover { background: rgba(255,255,255,0.1); }

/* ── Keyboard shortcuts overlay ──────────────────────────────────── */
.shortcuts-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.shortcuts-overlay.show { display: flex; }
.shortcuts-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; padding: 24px; width: 360px; max-width: 90vw;
}
.shortcuts-title { font-size: 15px; font-weight: 600; color: #f3f4f6; margin-bottom: 16px; }
.shortcut-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 13px;
}
.shortcut-row:last-child { border-bottom: none; }
.shortcut-desc { color: #9ca3af; }
.shortcut-key {
    font-family: monospace; font-size: 11px; color: #d1d5db;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15);
    padding: 3px 8px; border-radius: 4px; white-space: nowrap;
}
.shortcuts-close-btn {
    margin-top: 16px; width: 100%; background: rgba(255,255,255,0.06);
    border: none; color: #9ca3af; padding: 9px; border-radius: 8px;
    cursor: pointer; font-family: inherit; font-size: 13px; transition: background 0.15s;
}
.shortcuts-close-btn:hover { background: rgba(255,255,255,0.1); }

/* ── Regenerate button ───────────────────────────────────────────── */
.msg-act-btn.regen:hover { color: #f59e0b !important; }

/* ── Per-message speak button active state ───────────────────────── */
.msg-act-btn.speak-btn.speaking {
    color: #4ade80 !important;
    background: rgba(46,204,113,0.12);
    border-radius: 4px;
    animation: speakPulse 1.4s ease-in-out infinite;
}
@keyframes speakPulse { 0%,100%{opacity:1} 50%{opacity:0.55} }

/* ── Hide new topbar elements on small screens ───────────────────── */
@media (max-width: 768px) {
    .personality-select, .lang-toggle-btn { display: none; }
}

/* ── Sidebar search ──────────────────────────────────────────────── */
.sidebar-search {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}
.sidebar-search input {
    width: 100%; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1); color: #d1d5db;
    font-size: 12px; font-family: inherit; padding: 6px 10px;
    border-radius: 7px; outline: none; transition: border-color 0.15s;
}
.sidebar-search input:focus { border-color: rgba(46,204,113,0.4); }
.sidebar-search input::placeholder { color: #4b5563; }

/* ── Follow-up chips ─────────────────────────────────────────────── */
.followup-wrap {
    max-width: 800px; margin: 4px auto 0; padding: 0 20px 4px 64px;
    display: flex; flex-wrap: wrap; gap: 6px;
}
.followup-chip {
    background: rgba(46,204,113,0.08); border: 1px solid rgba(46,204,113,0.25);
    color: #4ade80; font-size: 12px; font-family: inherit; padding: 5px 12px;
    border-radius: 20px; cursor: pointer; transition: all 0.15s; line-height: 1.4;
}
.followup-chip:hover { background: rgba(46,204,113,0.18); border-color: rgba(46,204,113,0.5); color: #86efac; }

/* ── Message feedback buttons ────────────────────────────────────── */
.msg-feedback { display: flex; gap: 2px; align-items: center; }
.fb-btn {
    width: 24px; height: 24px; border: none; background: none;
    color: #4b5563; cursor: pointer; border-radius: 4px;
    display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.fb-btn:hover { background: rgba(255,255,255,0.07); color: #9ca3af; }
.fb-btn.up.active { color: #4ade80; background: rgba(46,204,113,0.1); }
.fb-btn.down.active { color: #f87171; background: rgba(239,68,68,0.1); }
.fb-btn .material-icons { font-size: 14px; }

/* ── Code block header + copy ────────────────────────────────────── */
.code-block-wrap { position: relative; margin: 10px 0; }
.code-block-header {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);
    border-bottom: none; border-radius: 8px 8px 0 0;
    padding: 5px 12px;
}
.code-lang-label { font-size: 11px; color: #6b7280; font-family: monospace; text-transform: uppercase; }
.code-copy-btn {
    background: none; border: none; color: #6b7280; cursor: pointer; font-size: 11px;
    font-family: inherit; display: flex; align-items: center; gap: 4px;
    padding: 2px 6px; border-radius: 4px; transition: all 0.15s;
}
.code-copy-btn:hover { color: #d1d5db; background: rgba(255,255,255,0.08); }
.code-copy-btn .material-icons { font-size: 13px; }
.code-block-wrap pre.code-block {
    margin: 0 !important; border-radius: 0 0 8px 8px !important;
    border-top: none !important;
}

/* ── Load more messages ─────────────────────────────────────────── */
.load-more-wrap {
    text-align: center; padding: 10px 0 4px;
}
.load-more-btn {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-size: 12px; font-family: inherit;
    padding: 6px 16px; border-radius: 20px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
    transition: all 0.15s;
}
.load-more-btn:hover { background: rgba(255,255,255,0.1); color: #e5e7eb; }
.load-more-btn .material-icons { font-size: 14px; }
.load-more-btn:disabled { opacity: 0.5; cursor: default; }

/* ── AI message fade-in ──────────────────────────────────────────── */
@keyframes msgFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.msg-bubble.msg-new { animation: msgFadeIn 0.35s ease both; }

/* ── Typing label ────────────────────────────────────────────────── */
.typing-label {
    font-size: 11px; color: #6b7280; margin-left: 6px;
    animation: typingPulse 1.6s ease-in-out infinite;
}
@keyframes typingPulse { 0%,100%{opacity:0.4} 50%{opacity:1} }

/* ── Character counter ───────────────────────────────────────────── */
.char-counter {
    font-size: 11px; color: #4b5563; text-align: right;
    padding: 0 4px; transition: color 0.2s;
}
.char-counter.warn { color: #f59e0b; }
.char-counter.over { color: #ef4444; }

/* ── Scroll-to-bottom button ─────────────────────────────────────── */
.scroll-to-bottom {
    position: absolute; bottom: 90px; right: 20px; z-index: 50;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(46,204,113,0.15); border: 1px solid rgba(46,204,113,0.35);
    color: #4ade80; cursor: pointer; display: none; align-items: center; justify-content: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.3); transition: all 0.2s;
}
.scroll-to-bottom.visible { display: flex; }
.scroll-to-bottom:hover { background: rgba(46,204,113,0.28); }
.scroll-to-bottom .material-icons { font-size: 20px; }

/* ── Memory panel modal ──────────────────────────────────────────── */
.memory-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.memory-modal.show { display: flex; }
.memory-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 24px; width: 440px; max-width: 92vw;
    max-height: 80vh; display: flex; flex-direction: column;
}
.memory-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-shrink: 0;
}
.memory-header h3 { font-size: 15px; font-weight: 600; color: #f3f4f6; display: flex; align-items: center; gap: 8px; }
.memory-header h3 .material-icons { font-size: 18px; color: #4ade80; }
.memory-add-row {
    display: flex; gap: 6px; margin-bottom: 12px; flex-shrink: 0;
}
.memory-add-row input {
    flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #f3f4f6; font-size: 12px; font-family: inherit; padding: 7px 10px; border-radius: 7px; outline: none;
}
.memory-add-row input:focus { border-color: rgba(46,204,113,0.4); }
.memory-add-row button {
    background: linear-gradient(135deg,#1a9c50,#2ecc71); border: none; color: white;
    font-size: 12px; font-family: inherit; padding: 7px 14px; border-radius: 7px; cursor: pointer;
}
.memory-list-scroll { flex: 1; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }
.memory-empty { text-align: center; color: #4b5563; font-size: 13px; padding: 24px; }
.memory-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06);
}
.memory-item:last-child { border-bottom: none; }
.memory-item-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.memory-item-body { flex: 1; min-width: 0; }
.memory-item-key { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
.memory-item-val { font-size: 13px; color: #d1d5db; word-break: break-word; }
.memory-item-src { font-size: 10px; color: #6b7280; margin-top: 2px; }
.memory-del-btn {
    background: none; border: none; color: #4b5563; cursor: pointer; padding: 2px;
    border-radius: 4px; display: flex; align-items: center; flex-shrink: 0; transition: color 0.15s;
}
.memory-del-btn:hover { color: #ef4444; }
.memory-del-btn .material-icons { font-size: 16px; }
.memory-footer {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 14px; flex-shrink: 0; padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.memory-clear-btn {
    background: none; border: 1px solid rgba(239,68,68,0.35); color: #f87171;
    font-size: 12px; font-family: inherit; padding: 6px 12px; border-radius: 7px; cursor: pointer; transition: all 0.15s;
}
.memory-clear-btn:hover { background: rgba(239,68,68,0.1); }
.memory-close-btn {
    background: rgba(255,255,255,0.06); border: none; color: #9ca3af;
    font-size: 12px; font-family: inherit; padding: 6px 16px; border-radius: 7px; cursor: pointer;
}

/* ── Toast notification (success variant) ────────────────────────── */
.custom-alert.success { border-color: rgba(46,204,113,0.4); }
.custom-alert.success .material-icons { color: #4ade80; }

/* ── In-conversation search bar ──────────────────────────────────── */
@keyframes slideDown { from{transform:translateY(-8px);opacity:0} to{transform:none;opacity:1} }
.search-bar {
    position: absolute; top: 52px; left: 0; right: 0; z-index: 100;
    background: rgba(13,17,23,0.97); backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 9px 16px; display: none; align-items: center; gap: 10px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.35);
    animation: slideDown 0.18s ease;
}
.search-bar.show { display: flex; }
.search-bar-icon { color: #4ade80; font-size: 18px; flex-shrink: 0; }
.search-bar-input {
    flex: 1; max-width: 420px; background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12); color: #f3f4f6;
    font-family: inherit; font-size: 13px; padding: 7px 14px;
    border-radius: 8px; outline: none; transition: border-color 0.15s, box-shadow 0.15s;
}
.search-bar-input:focus { border-color: rgba(46,204,113,0.5); box-shadow: 0 0 0 3px rgba(46,204,113,0.1); }
.search-bar-input::placeholder { color: #6b7280; }
.search-count {
    font-size: 11px; font-weight: 600; color: #4ade80;
    min-width: 68px; text-align: center;
    background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.2);
    border-radius: 12px; padding: 3px 10px; transition: all 0.15s;
}
.search-count:empty { visibility: hidden; }
.search-nav-btn {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; cursor: pointer; padding: 5px 8px; border-radius: 7px;
    transition: all 0.15s; display: flex; align-items: center;
}
.search-nav-btn:hover { background: rgba(255,255,255,0.12); color: #d1d5db; border-color: rgba(255,255,255,0.2); }
.search-nav-btn .material-icons { font-size: 16px; }
.search-close-btn {
    background: none; border: none; color: #6b7280; cursor: pointer;
    padding: 5px; border-radius: 6px; transition: all 0.15s; display: flex; align-items: center;
}
.search-close-btn:hover { color: #f87171; background: rgba(239,68,68,0.1); }
.search-close-btn .material-icons { font-size: 18px; }
.search-highlight { background: rgba(250,204,21,0.25); border-radius: 3px; padding: 0 2px; }
.search-highlight.current { background: rgba(250,204,21,0.55); outline: 2px solid rgba(250,204,21,0.45); border-radius: 3px; }

/* ── Image attachment preview ────────────────────────────────────── */
.img-preview-strip {
    max-width: 800px; margin: 0 auto 4px; padding: 6px 16px;
    display: none; align-items: flex-end; gap: 8px; overflow-x: auto;
    scrollbar-width: none;
}
.img-preview-strip::-webkit-scrollbar { display: none; }
.img-preview-strip.has-images {
    display: flex;
    animation: previewSlideIn 0.2s ease;
}
@keyframes previewSlideIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }

.img-thumb {
    position: relative; width: 84px; height: 84px; flex-shrink: 0;
    border-radius: 10px; overflow: hidden;
    border: 1.5px solid rgba(46,204,113,0.3);
    cursor: pointer; transition: transform 0.15s, border-color 0.15s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.img-thumb:hover { transform: scale(1.05); border-color: rgba(46,204,113,0.65); }
.img-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-thumb-num {
    position: absolute; bottom: 4px; left: 5px;
    background: rgba(0,0,0,0.65); color: white;
    font-size: 9px; font-weight: 700; border-radius: 4px; padding: 1px 5px;
    letter-spacing: 0.02em;
}
.img-thumb-remove {
    position: absolute; top: 4px; right: 4px;
    background: rgba(0,0,0,0.75); border: none; color: #fff; cursor: pointer;
    border-radius: 50%; width: 20px; height: 20px;
    display: flex; align-items: center; justify-content: center; padding: 0;
    opacity: 0; transition: opacity 0.15s, background 0.15s;
}
.img-thumb-remove .material-icons { font-size: 13px; }
.img-thumb-remove:hover { background: rgba(239,68,68,0.85); }
.img-thumb:hover .img-thumb-remove { opacity: 1; }

.img-add-more-btn {
    width: 84px; height: 84px; flex-shrink: 0;
    border: 1.5px dashed rgba(255,255,255,0.18); border-radius: 10px;
    background: rgba(255,255,255,0.03); color: #4b5563;
    cursor: pointer; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px;
    transition: all 0.18s; font-size: 10px; font-family: inherit; font-weight: 500;
}
.img-add-more-btn:hover { border-color: rgba(46,204,113,0.45); color: #4ade80; background: rgba(46,204,113,0.07); }
.img-add-more-btn .material-icons { font-size: 24px; }
.img-preview-info {
    font-size: 10px; color: #4b5563; white-space: nowrap;
    align-self: flex-end; padding-bottom: 5px; margin-left: 2px;
}

/* Images in chat bubbles */
.msg-imgs-wrap { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.msg-img {
    max-width: 200px; max-height: 180px; border-radius: 10px;
    display: block; cursor: zoom-in; border: 1px solid rgba(255,255,255,0.1);
    object-fit: cover; transition: transform 0.15s, box-shadow 0.15s;
}
.msg-img:hover { transform: scale(1.03); box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
.msg-imgs-more {
    display: flex; align-items: center; justify-content: center;
    min-width: 80px; height: 80px; background: rgba(255,255,255,0.06);
    border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);
    font-size: 16px; font-weight: 700; color: #6b7280; cursor: pointer; flex-shrink: 0;
}

/* Image attach button badge */
.img-attach-btn { position: relative; }
.img-attach-btn.has-images {
    border-color: rgba(46,204,113,0.5) !important;
    color: #4ade80 !important;
    background: rgba(46,204,113,0.08) !important;
}
.img-badge {
    position: absolute; top: -5px; right: -5px;
    background: #2ecc71; color: white; font-size: 9px; font-weight: 700;
    border-radius: 50%; width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    border: 1.5px solid #111827; pointer-events: none;
}

/* ── Quick prompts panel ─────────────────────────────────────────── */
.quick-panel-wrap { position: relative; }
.quick-panel {
    position: absolute; bottom: 100%; left: 0; width: 320px; z-index: 200;
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px; padding: 12px 14px; display: none;
    max-height: 240px; overflow-y: auto; margin-bottom: 6px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.quick-panel.show { display: block; }
.quick-panel-title { font-size: 10px; color: #4b5563; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }
.quick-cat-label { font-size: 10px; color: #6b7280; font-weight: 600;
    text-transform: uppercase; margin: 8px 0 4px; }
.quick-chip {
    display: inline-block; background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08); color: #9ca3af;
    font-size: 12px; font-family: inherit; padding: 4px 10px;
    border-radius: 16px; cursor: pointer; margin: 2px 2px 2px 0;
    transition: all 0.15s; text-align: left; line-height: 1.4;
}
.quick-chip:hover { background: rgba(46,204,113,0.1); border-color: rgba(46,204,113,0.35); color: #4ade80; }
.quick-trigger-btn {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.09);
    color: #6b7280; cursor: pointer; padding: 5px 7px; border-radius: 7px;
    display: flex; align-items: center; transition: all 0.15s;
}
.quick-trigger-btn:hover { background: rgba(255,255,255,0.1); color: #d1d5db; }
.quick-trigger-btn .material-icons { font-size: 20px; }

/* ── Image attach btn ────────────────────────────────────────────── */
.img-attach-btn {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.09);
    color: #6b7280; cursor: pointer; padding: 5px 7px; border-radius: 7px;
    display: flex; align-items: center; transition: all 0.15s;
}
.img-attach-btn:hover { background: rgba(255,255,255,0.1); color: #d1d5db; }
.img-attach-btn .material-icons { font-size: 20px; }

/* ── Export modal ────────────────────────────────────────────────── */
.export-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.export-modal.show { display: flex; }
.export-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 24px; width: 340px; max-width: 92vw;
}
.export-card h3 { font-size: 15px; font-weight: 600; color: #f3f4f6; margin-bottom: 16px; }
.export-fmt-row { display: flex; gap: 8px; margin-bottom: 18px; }
.export-fmt-btn {
    flex: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-family: inherit; font-size: 12px; padding: 10px 6px;
    border-radius: 8px; cursor: pointer; transition: all 0.15s; text-align: center;
}
.export-fmt-btn.active, .export-fmt-btn:hover {
    background: rgba(46,204,113,0.12); border-color: rgba(46,204,113,0.4); color: #4ade80;
}
.export-actions { display: flex; gap: 8px; justify-content: flex-end; }
.export-cancel { padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-family: inherit; font-size: 13px; background: rgba(255,255,255,0.06); color: #9ca3af; }
.export-confirm { padding: 8px 20px; border-radius: 8px; border: none; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 500;
    background: linear-gradient(135deg,#1a9c50,#2ecc71); color: white; }

/* ── Stats indicator ─────────────────────────────────────────────── */
.chat-stats { font-size: 11px; color: #6b7280; margin-left: auto; padding-right: 4px; white-space: nowrap; }
@media (max-width: 600px) { .chat-stats { display: none; } }

/* ── Image lightbox ──────────────────────────────────────────────── */
.img-lightbox {
    position: fixed; inset: 0; background: rgba(0,0,0,0.88);
    z-index: 99999; display: none; align-items: center; justify-content: center; cursor: zoom-out;
}
.img-lightbox.show { display: flex; }
.img-lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 8px; box-shadow: 0 0 40px rgba(0,0,0,0.5); }

/* ── Input box local drag-over ───────────────────────────────────── */
.input-box-wrap.drag-over { border-color: rgba(46,204,113,0.6) !important; background: rgba(46,204,113,0.05) !important; }

/* ── Full-page drop overlay ──────────────────────────────────────── */
.drop-overlay {
    position: fixed; inset: 0; z-index: 99990;
    background: rgba(10,14,20,0.92); backdrop-filter: blur(6px);
    display: none; flex-direction: column; align-items: center; justify-content: center;
    gap: 14px; pointer-events: none;
    border: 3px dashed rgba(46,204,113,0); transition: border-color 0.15s;
}
.drop-overlay.active {
    display: flex; pointer-events: all;
    border-color: rgba(46,204,113,0.55);
    animation: dropOverlayIn 0.18s ease;
}
@keyframes dropOverlayIn { from{opacity:0;transform:scale(0.98)} to{opacity:1;transform:none} }
.drop-overlay-icon {
    font-size: 72px; line-height: 1;
    animation: dropBounce 0.7s ease-in-out infinite alternate;
    filter: drop-shadow(0 0 24px rgba(46,204,113,0.5));
}
@keyframes dropBounce { from{transform:translateY(0)} to{transform:translateY(-14px)} }
.drop-overlay h2 { font-size: 22px; font-weight: 700; color: #f9fafb; margin: 0; }
.drop-overlay-sub { font-size: 13px; color: #6b7280; margin: 0; }
.drop-overlay-badge {
    display: flex; align-items: center; gap: 8px; margin-top: 4px;
    background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);
    border-radius: 20px; padding: 6px 16px; font-size: 12px; color: #4ade80;
}
.drop-overlay-badge .material-icons { font-size: 15px; }

/* ── Font size variants ──────────────────────────────────────────── */
.messages-wrap[data-fs="sm"] .msg-bubble { font-size: 12px !important; line-height: 1.55; }
.messages-wrap[data-fs="lg"] .msg-bubble { font-size: 16px !important; line-height: 1.72; }
.font-size-btn {
    background: none; border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-size: 12px; font-family: inherit; font-weight: 700;
    width: 32px; height: 32px; padding: 0; border-radius: 6px; cursor: pointer;
    transition: all 0.15s; display: flex; align-items: center; justify-content: center;
}
.font-size-btn:hover { background: rgba(255,255,255,0.06); color: #e5e7eb; }
.font-size-btn.sm-active { color: #9ca3af; }
.font-size-btn.lg-active { color: #f9fafb; font-size: 14px; }

/* ── Collapsible long AI messages ────────────────────────────────── */
.msg-bubble.collapsible { position: relative; }
.msg-bubble.collapsed { max-height: 200px; overflow: hidden; }
.msg-bubble.collapsed::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 64px;
    background: linear-gradient(transparent, rgba(17,24,39,0.96));
    pointer-events: none; border-radius: 0 0 18px 18px;
}
.show-more-btn {
    background: none; border: none; color: #4ade80; font-size: 12px;
    font-family: inherit; cursor: pointer; padding: 5px 0 2px;
    display: block; transition: color 0.15s;
}
.show-more-btn:hover { color: #86efac; }

/* ── Reading time badge ──────────────────────────────────────────── */
.read-time {
    font-size: 10px; color: #6b7280;
    display: inline-flex; align-items: center; gap: 3px;
}
.read-time .material-icons { font-size: 11px; }

/* ── Bookmark button ─────────────────────────────────────────────── */
.msg-act-btn.bookmark-btn.bookmarked .material-icons { color: #fbbf24; }

/* ── Bookmarks modal ─────────────────────────────────────────────── */
.bookmarks-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.bookmarks-modal.show { display: flex; }
.bookmarks-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 24px; width: 500px; max-width: 94vw;
    max-height: 80vh; display: flex; flex-direction: column;
}
.bookmarks-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
}
.bookmarks-header h3 { font-size: 15px; font-weight: 600; color: #f3f4f6; display: flex; align-items: center; gap: 8px; }
.bookmarks-header h3 .material-icons { font-size: 18px; color: #fbbf24; }
.bookmarks-list { flex: 1; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }
.bookmark-item {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px; padding: 12px 36px 12px 14px; margin-bottom: 8px; position: relative;
    transition: border-color 0.15s;
}
.bookmark-item:hover { border-color: rgba(255,255,255,0.12); }
.bookmark-item-text { font-size: 13px; color: #d1d5db; line-height: 1.5; max-height: 80px; overflow: hidden; }
.bookmark-item-meta { font-size: 11px; color: #4b5563; margin-top: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.bookmark-copy-btn {
    background: none; border: none; color: #4b5563; cursor: pointer; font-size: 11px;
    font-family: inherit; padding: 0; transition: color 0.15s; display: flex; align-items: center; gap: 3px;
}
.bookmark-copy-btn:hover { color: #4ade80; }
.bookmark-copy-btn .material-icons { font-size: 13px; }
.bookmark-remove-btn {
    position: absolute; top: 8px; right: 8px;
    background: none; border: none; color: #4b5563; cursor: pointer; padding: 2px;
    border-radius: 4px; transition: color 0.15s; display: flex; align-items: center;
}
.bookmark-remove-btn:hover { color: #ef4444; }
.bookmark-remove-btn .material-icons { font-size: 15px; }
.bookmarks-empty { text-align: center; color: #4b5563; font-size: 13px; padding: 32px; line-height: 1.6; }
.bookmarks-footer {
    display: flex; justify-content: flex-end; padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,0.06); margin-top: 14px;
}

/* ── Scroll progress bar ─────────────────────────────────────────── */
.scroll-progress-bar {
    position: absolute; top: 0; left: 0; height: 2px; z-index: 10;
    background: linear-gradient(90deg, #1a9c50, #2ecc71, #4ade80);
    width: 0%; pointer-events: none; border-radius: 0 2px 2px 0;
    transition: width 0.08s linear;
}

/* ── Pinned conversation indicator ───────────────────────────────── */
.conv-item.pinned .conv-icon { background: rgba(251,191,36,0.1); }
.conv-item.pinned .conv-icon .material-icons { color: #fbbf24; }
.conv-item.pinned .conv-title { color: #f3f4f6; }
.conv-item.pinned.active .conv-icon { background: rgba(251,191,36,0.2); }
.conv-item.pinned .conv-btn[title="Unpin"] .material-icons { color: #fbbf24; }

/* ── Archived section toggle ─────────────────────────────────────── */
.archived-toggle-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; cursor: pointer; width: 100%;
    font-size: 12px; color: #6b7280; font-family: inherit;
    border: none; background: none; text-align: left;
    border-top: 1px solid rgba(255,255,255,0.05); margin-top: 6px;
    transition: color 0.15s;
}
.archived-toggle-btn:hover { color: #d1d5db; }
.archived-toggle-btn .material-icons { font-size: 15px; }
.archived-count {
    margin-left: auto; background: rgba(255,255,255,0.07);
    color: #6b7280; font-size: 10px; padding: 1px 7px; border-radius: 10px;
}
#archivedList { display: none; }

/* ── Share modal ─────────────────────────────────────────────────── */
.share-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.share-modal.show { display: flex; }
.share-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 24px; width: 460px; max-width: 94vw;
}
.share-card h3 {
    font-size: 15px; font-weight: 600; color: #f3f4f6; margin-bottom: 4px;
    display: flex; align-items: center; gap: 8px;
}
.share-card h3 .material-icons { font-size: 18px; color: #4ade80; }
.share-desc { font-size: 12px; color: #6b7280; margin-bottom: 16px; line-height: 1.5; }
.share-link-row { display: flex; gap: 8px; margin-bottom: 16px; }
.share-link-input {
    flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #d1d5db; font-size: 12px; font-family: inherit; padding: 9px 12px;
    border-radius: 8px; outline: none; min-width: 0;
}
.share-copy-btn {
    background: linear-gradient(135deg,#1a9c50,#2ecc71); border: none; color: #fff;
    font-size: 12px; font-family: inherit; font-weight: 500; padding: 9px 14px;
    border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 4px;
    white-space: nowrap; flex-shrink: 0; transition: opacity 0.15s;
}
.share-copy-btn:hover { opacity: 0.88; }
.share-copy-btn .material-icons { font-size: 15px; }
.share-actions-row { display: flex; justify-content: space-between; align-items: center; }
.share-revoke-btn {
    background: none; border: 1px solid rgba(239,68,68,0.35); color: #f87171;
    font-size: 12px; font-family: inherit; padding: 7px 14px; border-radius: 7px;
    cursor: pointer; transition: all 0.15s;
}
.share-revoke-btn:hover { background: rgba(239,68,68,0.1); }
.share-close-btn {
    background: rgba(255,255,255,0.06); border: none; color: #9ca3af;
    font-size: 12px; font-family: inherit; padding: 7px 18px; border-radius: 7px; cursor: pointer;
}
.share-loading { text-align: center; color: #6b7280; font-size: 13px; padding: 14px 0; }

/* ── Share preview ───────────────────────────────────────────────── */
.share-preview {
    background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;
    max-height: 180px; overflow-y: auto;
}
.share-preview-title {
    font-size: 11px; font-weight: 600; color: #4ade80; text-transform: uppercase;
    letter-spacing: 0.06em; margin-bottom: 10px;
    display: flex; align-items: center; gap: 5px;
}
.share-preview-title .material-icons { font-size: 13px; }
.share-prev-msg { margin-bottom: 8px; }
.share-prev-msg:last-child { margin-bottom: 0; }
.share-prev-role {
    font-size: 10px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; display: block; margin-bottom: 2px;
}
.share-prev-msg.user .share-prev-role { color: #2ecc71; }
.share-prev-msg.ai   .share-prev-role { color: #9ca3af; }
.share-prev-text { font-size: 12px; color: #d1d5db; line-height: 1.5; }

/* ── Custom confirm dialog ───────────────────────────────────────── */
.confirm-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.7);
    z-index: 10000; display: none; align-items: center; justify-content: center;
}
.confirm-modal.show { display: flex; }
.confirm-card {
    background: #1f2937; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 28px 24px 20px; width: 340px; max-width: 92vw;
    text-align: center; animation: msgFadeIn 0.2s ease both;
}
.confirm-icon-wrap {
    width: 52px; height: 52px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; background: rgba(255,255,255,0.06);
}
.confirm-icon-wrap .material-icons { font-size: 26px; }
.confirm-card h3 { font-size: 16px; font-weight: 700; color: #f3f4f6; margin-bottom: 8px; }
.confirm-card p  { font-size: 13px; color: #9ca3af; line-height: 1.55; margin-bottom: 22px; }
.confirm-btns { display: flex; gap: 10px; justify-content: center; }
.confirm-cancel-btn {
    flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #9ca3af; font-size: 13px; font-family: inherit;
    padding: 9px 0; border-radius: 9px; cursor: pointer; transition: all 0.15s;
}
.confirm-cancel-btn:hover { background: rgba(255,255,255,0.1); color: #e5e7eb; }
.confirm-ok-btn {
    flex: 1; border: none; font-size: 13px; font-weight: 600; font-family: inherit;
    padding: 9px 0; border-radius: 9px; cursor: pointer; transition: opacity 0.15s;
}
.confirm-ok-btn:hover { opacity: 0.88; }
.confirm-danger { background: linear-gradient(135deg,#dc2626,#ef4444); color: #fff; }
.confirm-warning { background: linear-gradient(135deg,#b45309,#f59e0b); color: #fff; }
.confirm-success { background: linear-gradient(135deg,#1a9c50,#2ecc71); color: #fff; }
</style>
</head>
<body>

<!-- Full-page drop overlay (shown when dragging images over the window) -->
<div class="drop-overlay" id="dropOverlay">
    <div class="drop-overlay-icon">🌿</div>
    <h2>Drop crop images here</h2>
    <p class="drop-overlay-sub">Chashi Bhai will analyze each image for diseases &amp; health</p>
    <div class="drop-overlay-badge">
        <span class="material-icons">image</span>
        JPEG · PNG · WebP &nbsp;·&nbsp; max 8 MB each &nbsp;·&nbsp; up to 3 images
    </div>
</div>

<!-- Memory panel modal -->
<div class="memory-modal" id="memoryModal" onclick="if(event.target===this)closeMemoryModal()">
    <div class="memory-card">
        <div class="memory-header">
            <h3><span class="material-icons">psychology</span> AI Memory</h3>
            <button class="btn-back" onclick="closeMemoryModal()" title="Close">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="memory-add-row">
            <input type="text" id="memKeyInput" placeholder="Key (e.g. farm_size)" maxlength="60">
            <input type="text" id="memValInput" placeholder="Value (e.g. 5 bigha)" maxlength="200" style="flex:1.5">
            <button onclick="saveManualMemory()">Add</button>
        </div>
        <div class="memory-list-scroll" id="memoryList">
            <div class="memory-empty">Loading…</div>
        </div>
        <div class="memory-footer">
            <button class="memory-clear-btn" onclick="clearAllMemory()">🗑️ Clear All</button>
            <button class="memory-close-btn" onclick="closeMemoryModal()">Close</button>
        </div>
    </div>
</div>

<!-- Bookmarks modal -->
<div class="bookmarks-modal" id="bookmarksModal" onclick="if(event.target===this)closeBookmarksModal()">
    <div class="bookmarks-card">
        <div class="bookmarks-header">
            <h3><span class="material-icons">bookmark</span> Saved Messages</h3>
            <button class="btn-back" onclick="closeBookmarksModal()" title="Close">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="bookmarks-list" id="bookmarksList">
            <div class="bookmarks-empty">Loading…</div>
        </div>
        <div class="bookmarks-footer">
            <button class="memory-close-btn" onclick="closeBookmarksModal()">Close</button>
        </div>
    </div>
</div>

<!-- Message search bar (injected into chat-main via JS) -->
<div class="search-bar" id="searchBar">
    <span class="material-icons search-bar-icon">search</span>
    <input type="text" class="search-bar-input" id="searchInput" placeholder="Search messages…"
        oninput="performSearch(this.value)" onkeydown="searchKeyNav(event)" autocomplete="off">
    <span class="search-count" id="searchCount"></span>
    <button class="search-nav-btn" onclick="navigateSearch(-1)" title="Previous (Shift+Enter)">
        <span class="material-icons">keyboard_arrow_up</span>
    </button>
    <button class="search-nav-btn" onclick="navigateSearch(1)" title="Next (Enter)">
        <span class="material-icons">keyboard_arrow_down</span>
    </button>
    <button class="search-close-btn" onclick="closeSearch()" title="Close (Esc)">
        <span class="material-icons">close</span>
    </button>
</div>

<!-- Export modal -->
<div class="export-modal" id="exportModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="export-card">
        <h3>📤 Export Conversation</h3>
        <div class="export-fmt-row">
            <button class="export-fmt-btn active" id="fmtTxt" onclick="selectExportFmt('txt')">📄 Plain Text</button>
            <button class="export-fmt-btn" id="fmtMd"  onclick="selectExportFmt('md')">⬇️ Markdown</button>
            <button class="export-fmt-btn" id="fmtJson" onclick="selectExportFmt('json')">🔧 JSON</button>
        </div>
        <div class="export-actions">
            <button class="export-cancel" onclick="document.getElementById('exportModal').classList.remove('show')">Cancel</button>
            <button class="export-confirm" onclick="doExport()">Download</button>
        </div>
    </div>
</div>

<!-- Share modal -->
<div class="share-modal" id="shareModal" onclick="if(event.target===this)closeShareModal()">
    <div class="share-card">
        <h3><span class="material-icons">share</span> Share Conversation</h3>
        <p class="share-desc">Anyone with this link can view this conversation in read-only mode — no login required.</p>
        <div class="share-preview" id="sharePreview" style="display:none;">
            <div class="share-preview-title"><span class="material-icons">preview</span> Preview</div>
            <div id="sharePreviewMsgs"></div>
        </div>
        <div id="shareModalBody"><div class="share-loading">Generating link…</div></div>
        <div class="share-actions-row" id="shareActionsRow" style="display:none;">
            <button class="share-revoke-btn" onclick="revokeShareLink()">Disable Link</button>
            <button class="share-close-btn" onclick="closeShareModal()">Close</button>
        </div>
    </div>
</div>

<!-- Custom confirm dialog -->
<div class="confirm-modal" id="confirmModal" onclick="if(event.target===this)_confirmCancel()">
    <div class="confirm-card">
        <div class="confirm-icon-wrap" id="confirmIconWrap">
            <span class="material-icons" id="confirmIcon">help_outline</span>
        </div>
        <h3 id="confirmTitle">Confirm</h3>
        <p id="confirmMsg">Are you sure?</p>
        <div class="confirm-btns">
            <button class="confirm-cancel-btn" onclick="_confirmCancel()">Cancel</button>
            <button class="confirm-ok-btn confirm-danger" id="confirmOkBtn" onclick="_confirmOk()">Confirm</button>
        </div>
    </div>
</div>

<!-- Image lightbox -->
<div class="img-lightbox" id="imgLightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="">
</div>

<!-- Hidden file input for images -->
<input type="file" id="imgFileInput" accept="image/*" multiple style="display:none" onchange="handleImageFile(this.files)">

<!-- Keyboard shortcuts overlay -->
<div class="shortcuts-overlay" id="shortcutsOverlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="shortcuts-card">
        <p class="shortcuts-title">⌨️ <?php echo __('keyboard_shortcuts_title'); ?></p>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_send_msg'); ?></span><span class="shortcut-key">Enter</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_new_line'); ?></span><span class="shortcut-key">Shift + Enter</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_new_conv'); ?></span><span class="shortcut-key">Ctrl + K</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_export'); ?></span><span class="shortcut-key">Ctrl + E</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_sidebar'); ?></span><span class="shortcut-key">Ctrl + B</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_show'); ?></span><span class="shortcut-key">Ctrl + /</span></div>
        <div class="shortcut-row"><span class="shortcut-desc">Open AI Memory</span><span class="shortcut-key">Ctrl + M</span></div>
        <div class="shortcut-row"><span class="shortcut-desc">Search messages</span><span class="shortcut-key">Ctrl + F</span></div>
        <div class="shortcut-row"><span class="shortcut-desc">Attach image</span><span class="shortcut-key">Ctrl + I</span></div>
        <div class="shortcut-row"><span class="shortcut-desc"><?php echo __('shortcut_close_modal'); ?></span><span class="shortcut-key">Esc</span></div>
        <button class="shortcuts-close-btn" onclick="document.getElementById('shortcutsOverlay').classList.remove('show')"><?php echo __('close'); ?></button>
    </div>
</div>

<!-- Voice indicator -->
<div class="voice-indicator" id="voiceIndicator">
    <span class="material-icons">record_voice_over</span>
    <span><?php echo __('voice_text_reading'); ?></span>
    <button class="stop-voice-btn" id="stopVoiceBtn" title="Stop">
        <span class="material-icons">stop</span>
    </button>
</div>

<!-- Rename modal -->
<div class="modal-overlay" id="renameModal">
    <div class="modal-card">
        <p class="modal-title"><?php echo __('rename_conv_title'); ?></p>
        <input type="text" class="modal-input" id="renameInput" placeholder="<?php echo __('enter_new_title_ph'); ?>" maxlength="100">
        <div class="modal-actions">
            <button class="modal-btn cancel" onclick="closeRenameModal()"><?php echo __('cancel'); ?></button>
            <button class="modal-btn confirm" onclick="confirmRename()"><?php echo __('save_btn'); ?></button>
        </div>
    </div>
</div>

<!-- Custom alert toast -->
<div class="custom-alert" id="customAlert">
    <div class="custom-alert-inner">
        <span class="material-icons">warning</span>
        <span class="custom-alert-msg" id="customAlertMsg"></span>
        <button class="custom-alert-close" onclick="hideCustomAlert()">
            <span class="material-icons" style="font-size:18px;">close</span>
        </button>
    </div>
</div>

<!-- Mic modal -->
<div class="mic-modal" id="micModal">
    <div class="mic-card">
        <h3>🎤 <?php echo __('voice_input_title'); ?></h3>
        <p><?php echo __('voice_choose_press'); ?></p>
        <select class="mic-lang-select" id="micLang">
            <option value="bn-BD">🇧🇩 বাংলা</option>
            <option value="en-US">🇬🇧 English</option>
        </select>
        <div id="recognizedText"><?php echo __('press_mic_to_speak'); ?></div>
        <button class="mic-record-btn" id="micRecordBtn" onclick="toggleRecording()">
            <span class="material-icons">mic</span>
        </button>
        <button class="mic-close" onclick="closeMicModal()"><?php echo __('mic_close_btn'); ?></button>
    </div>
</div>

<div class="chat-app">
    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ── Sidebar ──────────────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a class="brand" href="<?php echo $base_url; ?>agent/chat">
                <img src="<?php echo $base_url; ?>agent/assets/logo.png" alt="Logo">
                <span class="brand-name">Chashi Bhai</span>
            </a>
            <button class="btn-new-chat" onclick="newChat()" title="New chat (Ctrl+K)">
                <span class="material-icons">edit_note</span>
            </button>
        </div>

        <div class="sidebar-search">
            <input type="text" id="convSearch" placeholder="🔍 Search conversations…" oninput="filterConversations(this.value)">
        </div>

        <div class="conv-list" id="convList">
            <div style="padding:16px;text-align:center;color:#4b5563;font-size:13px;"><?php echo __('loading_label'); ?></div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar-sm">
                <?php echo strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name-sm"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></div>
                <div class="user-role-sm"><?php echo ucfirst($currentUser['role'] ?? 'Farmer'); ?></div>
            </div>
            <a href="<?php echo $base_url; ?>" class="btn-back" title="Back to Dashboard">
                <span class="material-icons">home</span>
            </a>
        </div>
    </aside>

    <!-- ── Main ─────────────────────────────────────────────────── -->
    <div class="chat-main">
        <!-- Topbar -->
        <div class="chat-topbar">
            <button class="btn-toggle-sidebar" onclick="toggleSidebar()" title="Toggle sidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="chat-title-wrap">
                <span id="chatTitleText"><?php echo $activeConvo ? htmlspecialchars($activeConvo['title']) : __('new_chat_btn'); ?></span>
            </div>
            <div class="topbar-actions">

                <button class="topbar-btn" id="speakToggleBtn" onclick="toggleAlwaysSpeak()" title="Auto-speak AI responses">
                    <span class="material-icons">volume_off</span>
                    <span><?php echo __('speak_btn'); ?></span>
                </button>
                <select class="personality-select" id="personalitySelect" title="AI mode" onchange="setPersonality(this.value)">
                    <option value="general">🌾 General</option>
                    <option value="pest">🐛 Pest Expert</option>
                    <option value="soil">🧪 Soil Scientist</option>
                    <option value="market">💰 Market Advisor</option>
                    <option value="weather">☀️ Weather</option>
                </select>
                <button class="lang-toggle-btn" id="langToggleBtn" onclick="toggleLang()" title="Force response language">🌐 Auto</button>
                <button class="lang-toggle-btn topbar-mobile-hide" id="uiLangBtn" onclick="toggleUiLang()" title="UI Language"><?php echo $currentLang === 'bn' ? '🇧🇩 বাংলা' : '🇬🇧 EN'; ?></button>
                <button class="font-size-btn topbar-mobile-hide" id="fontSizeBtn" onclick="toggleFontSize()" title="Adjust font size">A</button>
                <button class="topbar-btn topbar-mobile-hide" onclick="openBookmarks()" title="Saved messages">
                    <span class="material-icons">bookmark</span>
                    <span>Saved</span>
                </button>
                <button class="topbar-btn topbar-mobile-secondary" onclick="openMemoryPanel()" title="AI Memory (Ctrl+M)">
                    <span class="material-icons">psychology</span>
                    <span>Memory</span>
                </button>
                <button class="topbar-btn topbar-mobile-secondary" onclick="exportConversation()" title="Export (Ctrl+E)">
                    <span class="material-icons">download</span>
                    <span><?php echo __('export_btn'); ?></span>
                </button>
                <button class="topbar-btn topbar-mobile-hide" id="shareBtn" onclick="openShareModal()" style="display:none;" title="Share conversation">
                    <span class="material-icons">share</span>
                    <span>Share</span>
                </button>
                <button class="topbar-btn topbar-mobile-hide" id="pinBtn" onclick="pinCurrentChat()" style="display:none;" title="Pin conversation">
                    <span class="material-icons">push_pin</span>
                    <span id="pinBtnLabel">Pin</span>
                </button>
                <button class="topbar-btn topbar-mobile-hide" id="archiveBtn" onclick="archiveCurrentChat()" style="display:none;" title="Archive conversation">
                    <span class="material-icons">archive</span>
                    <span id="archiveBtnLabel">Archive</span>
                </button>
                <button class="topbar-btn topbar-mobile-hide" id="renameBtn" onclick="openRenameModal()" style="display:none;" title="Rename">
                    <span class="material-icons">edit</span>
                    <span><?php echo __('rename_btn'); ?></span>
                </button>
                <button class="topbar-btn del topbar-mobile-hide" id="deleteBtn" onclick="deleteCurrentChat()" style="display:none;" title="Delete">
                    <span class="material-icons">delete_outline</span>
                    <span><?php echo __('delete'); ?></span>
                </button>
            </div>
        </div>

        <!-- Scroll to bottom -->
        <button class="scroll-to-bottom" id="scrollToBottomBtn" onclick="scrollToBottom(true)" title="Scroll to latest">
            <span class="material-icons">keyboard_arrow_down</span>
        </button>

        <!-- Messages -->
        <div class="messages-wrap" id="messagesWrap">
            <div class="scroll-progress-bar" id="scrollProgressBar"></div>
            <?php if (empty($initMessages)): ?>
            <!-- Welcome screen -->
            <div class="welcome-screen" id="welcomeScreen">
                <img src="<?php echo $base_url; ?>agent/assets/logo.png" class="welcome-logo" alt="Chashi Bhai">
                <h2 class="welcome-title"><?php echo __('ai_welcome_title'); ?></h2>
                <p class="welcome-sub">
                    <?php echo __('ai_welcome_sub'); ?>
                </p>
                <div class="suggestions-label" id="suggestionsLabel">
                    <span class="material-icons">tips_and_updates</span>
                    Get started
                </div>
                <div class="suggestions" id="suggestionsGrid">
                    <div class="suggestion-card" onclick="sendSuggestion('বোরো ধান কাটার পর আউশ মৌসুমের জন্য জমি কীভাবে তৈরি করবো?')">
                        <div class="suggestion-icon-wrap">
                            <span class="material-icons">grass</span>
                        </div>
                        <div class="suggestion-text">বোরো কাটার পর জমি তৈরি</div>
                    </div>
                    <div class="suggestion-card" onclick="sendSuggestion('Best fertilizer schedule for vegetable garden in Bangladesh')">
                        <div class="suggestion-icon-wrap">
                            <span class="material-icons">science</span>
                        </div>
                        <div class="suggestion-text">Fertilizer schedule for vegetables</div>
                    </div>
                    <div class="suggestion-card" onclick="sendSuggestion('এই মৌসুমে কোন সবজি লাগানো সবচেয়ে লাভজনক?')">
                        <div class="suggestion-icon-wrap">
                            <span class="material-icons">eco</span>
                        </div>
                        <div class="suggestion-text">এই মৌসুমে কোন সবজি লাগাবো?</div>
                    </div>
                    <div class="suggestion-card" onclick="sendSuggestion('How to control pests organically without chemicals?')">
                        <div class="suggestion-icon-wrap">
                            <span class="material-icons">bug_report</span>
                        </div>
                        <div class="suggestion-text">Organic pest control methods</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Load more button (shown when there are older messages) -->
            <?php if (!empty($initHasMore)): ?>
            <div class="load-more-wrap" id="loadMoreWrap">
                <button class="load-more-btn" id="loadMoreBtn" onclick="loadMoreMessages()">
                    <span class="material-icons">expand_less</span> Load earlier messages
                </button>
            </div>
            <?php endif; ?>
            <!-- Load existing messages (last <?php echo $MSGS_PER_PAGE ?? 20; ?>) -->
            <?php foreach ($initMessages as $msg): ?>
            <?php if ($msg['role'] === 'user'): ?>
            <div class="msg-row user">
                <div class="msg-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 4v2h16v-2c0-1.33-2.67-4-8-4z"/></svg>
                </div>
                <div class="msg-bubble-wrap">
                    <?php if (!empty($msg['images'])): ?>
                    <div class="msg-imgs-wrap">
                        <?php foreach (array_slice($msg['images'], 0, 3) as $imgPath): ?>
                        <img src="<?php echo htmlspecialchars($base_url . $imgPath); ?>" class="msg-img" loading="lazy" onclick="openLightbox(this.src)">
                        <?php endforeach; ?>
                        <?php if (count($msg['images']) > 3): ?>
                        <div class="msg-imgs-more" onclick="openLightbox('<?php echo htmlspecialchars($base_url . $msg['images'][3]); ?>')">+<?php echo count($msg['images']) - 3; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($msg['content']): ?>
                    <div class="msg-bubble"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <?php $fb = (int)($msg['feedback'] ?? 0); ?>
            <div class="msg-row assistant" data-msg-id="<?php echo (int)$msg['id']; ?>">
                <div class="msg-icon">
                    <img src="<?php echo $base_url; ?>agent/assets/logo.png" alt="AI">
                </div>
                <div>
                    <div class="msg-bubble"><?php echo $msg['content']; ?></div>
                    <div class="msg-meta">
                        <span class="msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                        <div class="msg-actions">
                            <button class="msg-act-btn" onclick="copyMsg(this)" title="Copy">
                                <span class="material-icons">content_copy</span>
                            </button>
                            <button class="msg-act-btn speak-btn" onclick="speakMsg(this)" title="Read aloud">
                                <span class="material-icons">volume_up</span>
                            </button>
                            <button class="msg-act-btn bookmark-btn" onclick="toggleBookmark(this)" title="Bookmark">
                                <span class="material-icons">bookmark_border</span>
                            </button>
                            <div class="msg-feedback">
                                <button class="fb-btn up <?php echo $fb === 1 ? 'active' : ''; ?>" data-val="1" title="Helpful" onclick="submitFeedback(<?php echo (int)$msg['id']; ?>, 1, this)">
                                    <span class="material-icons">thumb_up</span>
                                </button>
                                <button class="fb-btn down <?php echo $fb === -1 ? 'active' : ''; ?>" data-val="-1" title="Not helpful" onclick="submitFeedback(<?php echo (int)$msg['id']; ?>, -1, this)">
                                    <span class="material-icons">thumb_down</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Input -->
        <div class="input-area">
            <!-- Image preview strip (thumbnails shown here before sending) -->
            <div class="img-preview-strip" id="imgPreviewStrip"></div>

            <div class="input-box-wrap" id="inputDropZone">
                <textarea class="input-textarea" id="msgInput"
                          placeholder="<?php echo __('input_placeholder_ai'); ?>"
                          rows="1"></textarea>
                <div class="input-actions">
                    <!-- Quick prompts -->
                    <div class="quick-panel-wrap">
                        <div class="quick-panel" id="quickPanel"></div>
                        <button class="quick-trigger-btn" onclick="toggleQuickPanel()" title="Quick farming prompts (templates)">
                            <span class="material-icons">bolt</span>
                        </button>
                    </div>
                    <!-- Image attach (badge injected by JS) -->
                    <button class="img-attach-btn" id="imgAttachBtn" onclick="openImagePicker()" title="Attach crop image (Ctrl+I)">
                        <span class="material-icons">image</span>
                    </button>
                    <div class="emoji-wrap">
                        <div class="emoji-panel" id="emojiPanel"></div>
                        <button class="input-btn" id="emojiBtn" onclick="toggleEmojiPicker()" title="Emoji">
                            <span class="material-icons">sentiment_satisfied</span>
                        </button>
                    </div>
                    <button class="input-btn voice" id="voiceBtn" onclick="openMicModal()" title="Voice input">
                        <span class="material-icons">mic</span>
                    </button>
                    <button class="input-btn send" id="sendBtn" title="Send" disabled>
                        <span class="material-icons">send</span>
                    </button>
                </div>
            </div>
            <div style="max-width:800px;margin:4px auto 0;display:flex;justify-content:flex-end;padding:0 4px;">
                <span class="char-counter" id="charCounter" style="display:none;"></span>
            </div>
            <p class="input-hint"><?php echo __('ai_disclaimer'); ?></p>
        </div>
    </div><!-- .chat-main -->

</div><!-- .chat-app -->

<script>
// ── Config ──────────────────────────────────────────────────────────
const BASE_URL       = <?php echo json_encode($base_url); ?>;
const CONV_API       = BASE_URL + 'agent/api/conversations.php';
const SEND_API       = BASE_URL + 'agent/api/send.php';
const USER_LOCATION  = localStorage.getItem('userLocation') || 'Bangladesh';

// UI translations (PHP-injected)
const T = {
    newChat:            <?php echo json_encode(__('new_chat_btn')); ?>,
    welcomeTitle:       <?php echo json_encode(__('ai_welcome_title')); ?>,
    welcomeSub:         <?php echo json_encode(__('ai_welcome_sub_short')); ?>,
    today:              <?php echo json_encode(__('today_label')); ?>,
    yesterday:          <?php echo json_encode(__('yesterday')); ?>,
    previous7Days:      <?php echo json_encode(__('previous_7_days')); ?>,
    noConversations:    <?php echo json_encode(__('no_conversations_yet')); ?>,
    deleteConfirm:      <?php echo json_encode(__('delete_conv_confirm')); ?>,
    loading:            <?php echo json_encode(__('loading_label')); ?>,
};

let currentCid       = <?php echo json_encode($cid ?: null); ?>;
let currentTitle     = <?php echo json_encode($activeConvo ? $activeConvo['title'] : __('new_chat_btn')); ?>;
let _msgOffset       = <?php echo json_encode((int)($initMsgOffset ?? 0)); ?>;
let _hasMoreMsgs     = <?php echo json_encode(!empty($initHasMore)); ?>;
let _loadingMoreMsgs = false;
let isSending        = false;
let isRecording      = false;
let recognition      = null;
let synth            = window.speechSynthesis;
let currentUtterance = null;
let isVoiceConversation = false;
let silenceInterval  = null;

// ── Init ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Pre-fill suggestion grid immediately with seasonal defaults (before async list loads)
    if (document.getElementById('suggestionsGrid')) {
        renderSuggestionCards(getSeasonalSuggestions());
    }
    loadConversationList();
    autoResizeTextarea();
    if (currentCid) showConvoActions();
    scrollToBottom();

    // Enhance any pre-loaded code blocks from server-rendered history
    document.querySelectorAll('.msg-bubble').forEach(b => enhanceCodeBlocks(b));

    // Apply font size preference + enrich pre-rendered AI messages
    applyFontSize();
    document.querySelectorAll('.msg-row.assistant .msg-bubble').forEach(b => {
        addReadTime(b);
        setupCollapse(b);
    });
    syncBookmarkButtons();

    // Init new features
    initEmojiPanel();
    const ps = document.getElementById('personalitySelect');
    if (ps) ps.value = currentPersonality;
    const langBtn = document.getElementById('langToggleBtn');
    if (langBtn) {
        if (forceLang === 'bn') { langBtn.textContent = '🇧🇩 বাংলা'; langBtn.classList.add('active'); }
        else if (forceLang === 'en') { langBtn.textContent = '🇬🇧 English'; langBtn.classList.add('active'); }
    }

    // Geolocation
    if (navigator.geolocation && !localStorage.getItem('userLocation')) {
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude, lon = pos.coords.longitude;
            if (lat >= 20.5 && lat <= 26.5 && lon >= 88 && lon <= 92.7) {
                localStorage.setItem('userLocation', 'Bangladesh');
            }
        }, () => {});
    }

    // Restore always-speak toggle state
    if (localStorage.getItem('alwaysSpeak') === 'true') {
        const btn = document.getElementById('speakToggleBtn');
        if (btn) {
            btn.classList.add('voice-on');
            btn.querySelector('.material-icons').textContent = 'volume_up';
        }
    }

    // Populate voice languages when TTS voices are ready
    if (typeof speechSynthesis !== 'undefined') {
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = populateVoiceLangs;
        }
        populateVoiceLangs();
    }
});

// ── Textarea auto-resize ────────────────────────────────────────────
function autoResizeTextarea() {
    const ta      = document.getElementById('msgInput');
    const btn     = document.getElementById('sendBtn');
    const counter = document.getElementById('charCounter');
    const MAX_CHARS = 2000;

    ta.addEventListener('input', () => {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
        btn.disabled = ta.value.trim().length === 0;

        // Character counter
        const len = ta.value.length;
        if (len > 100) {
            counter.style.display = 'block';
            counter.textContent   = len + ' / ' + MAX_CHARS;
            counter.className     = 'char-counter' + (len > MAX_CHARS ? ' over' : len > MAX_CHARS * 0.8 ? ' warn' : '');
        } else {
            counter.style.display = 'none';
        }
    });

    ta.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!btn.disabled && !isSending) sendMessage();
        }
    });

    document.getElementById('sendBtn').addEventListener('click', () => {
        if (!isSending) sendMessage();
    });
}

// ── Send message ────────────────────────────────────────────────────
async function sendMessage(text) {
    const ta  = document.getElementById('msgInput');
    const msg = text || ta.value.trim();
    if (!msg && !attachedImages.length) return;
    if (isSending) return;

    if (!text) { ta.value = ''; ta.style.height = 'auto'; }
    document.getElementById('sendBtn').disabled = true;
    isSending = true;

    // Build effective message (append image note if images attached)
    const imgs = [...attachedImages];
    let effectiveMsg = msg;
    if (imgs.length) {
        if (!text) {
            if (!msg) effectiveMsg = imgs.length > 1
                ? `I've attached ${imgs.length} crop images. What disease or problem do you see?`
                : "I've attached a crop image. What disease or problem do you see?";
            // Don't append filename noise when user typed a message — keep it clean for the AI
        }
        attachedImages = [];
        renderImagePreviews();
    }

    lastUserMessage = effectiveMsg;

    // Hide welcome screen
    const ws = document.getElementById('welcomeScreen');
    if (ws) ws.remove();

    appendUserMsg(effectiveMsg, imgs);
    showTyping();

    try {
        // Include image data if present
        const payload = {
            conversation_id: currentCid,
            message: effectiveMsg,
            location: USER_LOCATION,
            personality: currentPersonality,
            lang: forceLang || undefined,
        };
        // Send all attached images as an array
        if (imgs.length) {
            payload.images = imgs.map(img => ({
                data: img.dataUrl.replace(/^data:[^;]+;base64,/, ''),
                mime: img.dataUrl.match(/^data:([^;]+);/)?.[1] || 'image/jpeg',
            }));
        }

        const res = await fetch(SEND_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        removeTyping();

        if (data.image_invalid) {
            removeTyping();
            // Remove the user message row just appended
            const rows = document.querySelectorAll('.msg-row.user');
            if (rows.length) rows[rows.length - 1].remove();
            showCustomAlert(data.message || 'Please upload a valid crop or plant image.');
            isSending = false;
            document.getElementById('sendBtn').disabled = false;
            document.getElementById('msgInput').focus();
            return;
        }

        if (data.success) {
            if (!currentCid) {
                currentCid   = data.conversation_id;
                currentTitle = data.title;
                updateTitleDisplay(data.title);
                showConvoActions();
                history.replaceState({}, '', BASE_URL + 'agent/chat/' + currentCid);
            } else if (data.title && data.title !== 'New Chat' && currentTitle === 'New Chat') {
                currentTitle = data.title;
                updateTitleDisplay(data.title);
            }
            // Disease-card responses contain complex HTML — streaming strips it to plain text,
            // so skip streaming and render the full HTML immediately instead.
            const hasDiseaseCard = data.reply && data.reply.includes('sc-disease-card');
            const aiRow = appendAIMsg(data.reply, data.detectedLang, hasDiseaseCard, data.msg_id || null);
            if (data.followUps && data.followUps.length) {
                displayFollowUps(data.followUps, aiRow);
            }
            loadConversationList(currentCid);
            playSound('receive');
            speakAIResponse(data.reply, data.detectedLang);
        } else {
            appendErrorMsg(data.message || 'Failed to get response.');
        }
    } catch (err) {
        removeTyping();
        appendErrorMsg('Network error. Please check your connection.');
    }

    isSending = false;
    document.getElementById('sendBtn').disabled = false;
    document.getElementById('msgInput').focus();
}

function sendSuggestion(text) {
    sendMessage(text);
}

// ── DOM Helpers ─────────────────────────────────────────────────────
const AI_ICON_SVG = `<img src="${BASE_URL}agent/assets/logo.png" alt="AI" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">`;
const USER_ICON_SVG = `<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#2ecc71;"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 4v2h16v-2c0-1.33-2.67-4-8-4z"/></svg>`;

function _buildUserMsgRow(text, images = [], timeStr = null) {
    const row = document.createElement('div');
    row.className = 'msg-row user';
    let contentHtml = '';
    if (images.length) {
        const MAX_SHOW = 3;
        const shown    = images.slice(0, MAX_SHOW);
        const extra    = images.length - MAX_SHOW;
        contentHtml += '<div class="msg-imgs-wrap">';
        shown.forEach(img => {
            const src = img.src || img.dataUrl;
            contentHtml += `<img src="${src}" class="msg-img" alt="${escHtml(img.name || '')}" onclick="openLightbox(this.src)" loading="lazy">`;
        });
        if (extra > 0) {
            const overflowSrc = images[MAX_SHOW].src || images[MAX_SHOW].dataUrl;
            contentHtml += `<div class="msg-imgs-more" onclick="openLightbox('${overflowSrc}')">+${extra}</div>`;
        }
        contentHtml += '</div>';
    }
    if (text) contentHtml += escHtml(text).replace(/\n/g, '<br>');
    const t = timeStr || new Date().toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
    row.innerHTML = `
        <div class="msg-icon">${USER_ICON_SVG}</div>
        <div class="msg-bubble-wrap">
            <div class="msg-bubble">${contentHtml}</div>
            <div class="msg-meta"><span class="msg-time">${t}</span></div>
        </div>`;
    return row;
}

function appendUserMsg(text, images = []) {
    const wrap = document.getElementById('messagesWrap');
    const row  = _buildUserMsgRow(text, images);
    wrap.appendChild(row);
    scrollToBottom();
    playSound('send');
}

function _buildAIMsgRow(html, msgId = null, feedback = null, lang = null, animate = false) {
    const row  = document.createElement('div');
    row.className = 'msg-row assistant';
    if (msgId) row.dataset.msgId = msgId;

    const time = new Date().toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit'});
    const langBadge    = lang ? `<span class="meta-lang">${lang}</span>` : '';
    const fbUpActive   = feedback === 1  ? ' active' : '';
    const fbDownActive = feedback === -1 ? ' active' : '';

    const bubbleEl = document.createElement('div');
    bubbleEl.className = 'msg-bubble';
    const inner = document.createElement('div');
    inner.innerHTML = `<div class="msg-meta">
        ${langBadge}
        <span class="msg-time">${time}</span>
        <div class="msg-actions">
            <button class="msg-act-btn" onclick="copyMsg(this)" title="Copy"><span class="material-icons">content_copy</span></button>
            <button class="msg-act-btn speak-btn" onclick="speakMsg(this)" title="Read aloud"><span class="material-icons">volume_up</span></button>
            <button class="msg-act-btn regen" onclick="regenerateResponse()" title="Regenerate response"><span class="material-icons">refresh</span></button>
            <button class="msg-act-btn bookmark-btn" onclick="toggleBookmark(this)" title="Bookmark"><span class="material-icons">bookmark_border</span></button>
        </div>
        ${msgId ? `<div class="msg-feedback">
            <button class="fb-btn up${fbUpActive}" onclick="submitFeedback(${msgId},1,this)" title="Helpful"><span class="material-icons">thumb_up</span></button>
            <button class="fb-btn down${fbDownActive}" onclick="submitFeedback(${msgId},-1,this)" title="Not helpful"><span class="material-icons">thumb_down</span></button>
        </div>` : ''}
    </div>`;
    inner.insertBefore(bubbleEl, inner.firstChild);
    row.innerHTML = `<div class="msg-icon">${AI_ICON_SVG}</div>`;
    row.appendChild(inner);

    if (animate) bubbleEl.classList.add('msg-new');
    bubbleEl.innerHTML = html;
    enhanceCodeBlocks(bubbleEl);
    addReadTime(bubbleEl);
    setupCollapse(bubbleEl);
    return row;
}

function appendAIMsg(html, lang, skipStream = false, msgId = null, feedback = null) {
    const wrap = document.getElementById('messagesWrap');
    const row  = _buildAIMsgRow(html, msgId, feedback, lang, true);
    wrap.appendChild(row);
    scrollToBottom();
    return row;
}

function appendErrorMsg(msg) {
    const wrap = document.getElementById('messagesWrap');
    const row  = document.createElement('div');
    row.className = 'msg-row assistant';
    row.innerHTML = `
        <div class="msg-icon">${AI_ICON_SVG}</div>
        <div class="msg-bubble" style="border-color:rgba(239,68,68,0.3);color:#fca5a5;">
            ⚠ ${escHtml(msg)}
        </div>`;
    wrap.appendChild(row);
    scrollToBottom();
}

function showTyping() {
    const wrap = document.getElementById('messagesWrap');
    const el   = document.createElement('div');
    el.className = 'typing-row'; el.id = 'typingRow';
    el.innerHTML = `
        <div class="msg-icon">${AI_ICON_SVG}</div>
        <div class="typing-bubble">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <span class="typing-label">Thinking…</span>
        </div>`;
    wrap.appendChild(el);
    scrollToBottom();
}

function removeTyping() {
    const el = document.getElementById('typingRow');
    if (el) el.remove();
}

function scrollToBottom(force = false) {
    const wrap = document.getElementById('messagesWrap');
    const btn  = document.getElementById('scrollToBottomBtn');
    const isNearBottom = wrap.scrollHeight - wrap.scrollTop - wrap.clientHeight < 120;
    if (force || isNearBottom) {
        wrap.scrollTop = wrap.scrollHeight;
        if (btn) btn.classList.remove('visible');
    }
}

// Scroll-to-bottom button visibility
(function() {
    document.addEventListener('DOMContentLoaded', () => {
        const wrap = document.getElementById('messagesWrap');
        const btn  = document.getElementById('scrollToBottomBtn');
        if (!wrap || !btn) return;
        wrap.addEventListener('scroll', () => {
            const distFromBottom = wrap.scrollHeight - wrap.scrollTop - wrap.clientHeight;
            btn.classList.toggle('visible', distFromBottom > 200);
            const bar = document.getElementById('scrollProgressBar');
            if (bar) {
                const max = wrap.scrollHeight - wrap.clientHeight;
                bar.style.width = (max > 0 ? (wrap.scrollTop / max) * 100 : 0) + '%';
            }
        });
    });
})();

// ── Relative time helper ────────────────────────────────────────────
function relTime(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(dateStr).toLocaleDateString('default', { month: 'short', day: 'numeric' });
}

// ── Build a single sidebar conversation item ────────────────────────
function buildConvItem(c, active) {
    const isActive  = c.conversation_id === active;
    const isPinned  = c.is_pinned === 1;   // PDO may return string; compare after int cast from server
    const title     = escHtml(c.title || 'Untitled');
    const time      = relTime(c.updated_at);
    const pinIcon   = isPinned ? 'push_pin' : 'chat_bubble_outline';
    const pinLabel  = isPinned ? 'Unpin' : 'Pin';
    const pinMIcon  = 'push_pin';
    const safeTitle = title.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return `
        <a class="conv-item${isActive ? ' active' : ''}${isPinned ? ' pinned' : ''}" onclick="loadChat('${c.conversation_id}')">
            <div class="conv-icon"><span class="material-icons">${pinIcon}</span></div>
            <div class="conv-body">
                <span class="conv-title">${title}</span>
                <span class="conv-time">${time}</span>
            </div>
            <div class="conv-actions">
                <button class="conv-btn" onclick="event.stopPropagation();pinChatItem('${c.conversation_id}',${isPinned?1:0})" title="${pinLabel}">
                    <span class="material-icons">${pinMIcon}</span>
                </button>
                <button class="conv-btn" onclick="event.stopPropagation();archiveChatItem('${c.conversation_id}')" title="Archive">
                    <span class="material-icons">archive</span>
                </button>
                <button class="conv-btn" onclick="event.stopPropagation();openRenameFor('${c.conversation_id}','${safeTitle}')" title="Rename">
                    <span class="material-icons">edit</span>
                </button>
                <button class="conv-btn del" onclick="event.stopPropagation();deleteChat('${c.conversation_id}',this)" title="Delete">
                    <span class="material-icons">delete_outline</span>
                </button>
            </div>
        </a>`;
}

// ── Conversation list ───────────────────────────────────────────────
async function loadConversationList(activeCid) {
    const active = activeCid || currentCid;
    try {
        const res  = await fetch(CONV_API + '?action=list');
        const data = await res.json();
        if (!data.success) return;

        const list      = data.conversations || [];
        const container = document.getElementById('convList');

        // Update topbar pin/archive button labels for current conversation
        _syncCurrentConvMeta(list);

        if (!list.length) {
            container.innerHTML = `<div style="padding:16px;text-align:center;color:#4b5563;font-size:12px;">${T.noConversations}</div>`;
            _appendArchivedToggle(container);
            updateWelcomeSuggestions([]);
            return;
        }

        // Separate pinned from regular
        const pinned  = list.filter(c => c.is_pinned);
        const regular = list.filter(c => !c.is_pinned);

        // Group regular by date
        const groups  = {};
        const today   = new Date(); today.setHours(0,0,0,0);
        const yest    = new Date(today); yest.setDate(yest.getDate()-1);
        const week    = new Date(today); week.setDate(week.getDate()-7);

        regular.forEach(c => {
            const d = new Date(c.updated_at); d.setHours(0,0,0,0);
            let grp;
            if (d >= today)     grp = T.today;
            else if (d >= yest) grp = T.yesterday;
            else if (d >= week) grp = T.previous7Days;
            else                grp = d.toLocaleString('default',{month:'long',year:'numeric'});
            if (!groups[grp]) groups[grp] = [];
            groups[grp].push(c);
        });

        let html = '';

        // Pinned section
        if (pinned.length) {
            html += `<div class="conv-group-label">📌 Pinned</div>`;
            pinned.forEach(c => { html += buildConvItem(c, active); });
        }

        // Date groups
        for (const [grp, items] of Object.entries(groups)) {
            html += `<div class="conv-group-label">${grp}</div>`;
            items.forEach(c => { html += buildConvItem(c, active); });
        }

        container.innerHTML = html;
        _appendArchivedToggle(container);
        updateWelcomeSuggestions(list);
    } catch {}
}

// Append the "Archived" toggle row at the bottom of conv-list
function _appendArchivedToggle(container) {
    const btn = document.createElement('button');
    btn.className = 'archived-toggle-btn';
    btn.id = 'archivedToggleBtn';
    btn.innerHTML = `<span class="material-icons">archive</span>Archived<span class="archived-count" id="archivedCountBadge"></span>`;
    btn.onclick = toggleArchivedSection;
    container.appendChild(btn);

    const archivedList = document.createElement('div');
    archivedList.id = 'archivedList';
    container.appendChild(archivedList);

    // Restore open state after sidebar re-render
    if (_archivedOpen) {
        _archivedOpen = false; // reset so toggleArchivedSection will open it
        toggleArchivedSection();
    }
}

// Sync current conversation's pin/archive state to topbar buttons
function _syncCurrentConvMeta(list) {
    if (!currentCid) return;
    // list only contains non-archived; archived conversations won't be found here
    // — state is updated separately in loadChat() and archiveCurrentChat()
    const c = list.find(c => c.conversation_id === currentCid);
    if (!c) return;
    currentIsPinned   = c.is_pinned === 1;
    currentIsArchived = c.is_archived === 1;
    _updatePinBtnLabel();
    _updateArchiveBtnLabel();
}

// ── Load a conversation ─────────────────────────────────────────────
async function loadChat(cid) {
    if (cid === currentCid) { closeSidebarMobile(); return; }

    try {
        const res  = await fetch(CONV_API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'load', conversation_id:cid, offset:0})
        });
        const data = await res.json();
        if (!data.success) return;

        currentCid    = cid;
        currentTitle  = data.conversation.title;
        currentIsPinned   = (data.conversation.is_pinned   === 1 || data.conversation.is_pinned   === '1');
        currentIsArchived = (data.conversation.is_archived === 1 || data.conversation.is_archived === '1');
        history.replaceState({}, '', BASE_URL + 'agent/chat/' + cid);
        updateTitleDisplay(currentTitle);
        _updatePinBtnLabel();
        _updateArchiveBtnLabel();
        showConvoActions();

        // Reset pagination state for this conversation
        _msgOffset       = data.messages.length;
        _hasMoreMsgs     = !!data.has_more;
        _loadingMoreMsgs = false;

        const wrap = document.getElementById('messagesWrap');
        wrap.innerHTML = '';

        // Show "load earlier" button if there are older messages
        if (_hasMoreMsgs) _prependLoadMoreBtn(wrap);

        data.messages.forEach(m => {
            if (m.role === 'user') {
                const storedImgs = (m.images || []).map(path => ({ src: BASE_URL + path, name: '' }));
                appendUserMsg(m.content, storedImgs);
            } else {
                appendAIMsg(m.content, null, true, m.id || null, m.feedback || null);
            }
        });

        loadConversationList(cid);
        closeSidebarMobile();
        scrollToBottom();
    } catch {}
}

// ── Load more (older) messages ───────────────────────────────────────
function _prependLoadMoreBtn(wrap) {
    const existing = document.getElementById('loadMoreWrap');
    if (existing) return;
    const div = document.createElement('div');
    div.className = 'load-more-wrap';
    div.id        = 'loadMoreWrap';
    div.innerHTML = `<button class="load-more-btn" id="loadMoreBtn" onclick="loadMoreMessages()">
        <span class="material-icons">expand_less</span> Load earlier messages
    </button>`;
    wrap.prepend(div);
}

async function loadMoreMessages() {
    if (!currentCid || _loadingMoreMsgs || !_hasMoreMsgs) return;
    _loadingMoreMsgs = true;
    const btn = document.getElementById('loadMoreBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Loading…'; }

    try {
        const res  = await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'load', conversation_id:currentCid, offset:_msgOffset})
        });
        const data = await res.json();
        if (!data.success) return;

        const wrap     = document.getElementById('messagesWrap');
        const anchor   = document.getElementById('loadMoreWrap');
        const prevH    = wrap.scrollHeight;

        // Remove load-more button temporarily
        if (anchor) anchor.remove();

        // Prepend older messages (they come back in chronological order)
        const fragment = document.createDocumentFragment();
        data.messages.forEach(m => {
            let row;
            if (m.role === 'user') {
                const storedImgs = (m.images || []).map(path => ({ src: BASE_URL + path, name: '' }));
                row = _buildUserMsgRow(m.content, storedImgs);
            } else {
                row = _buildAIMsgRow(m.content, m.id || null, m.feedback || null);
            }
            fragment.appendChild(row);
        });

        _msgOffset   += data.messages.length;
        _hasMoreMsgs  = !!data.has_more;

        // Re-add load-more button at top if still more
        if (_hasMoreMsgs) {
            const newBtn = document.createElement('div');
            newBtn.className = 'load-more-wrap';
            newBtn.id        = 'loadMoreWrap';
            newBtn.innerHTML = `<button class="load-more-btn" id="loadMoreBtn" onclick="loadMoreMessages()">
                <span class="material-icons">expand_less</span> Load earlier messages
            </button>`;
            wrap.prepend(newBtn);
        }
        wrap.prepend(fragment);

        // Restore scroll position (user stays at same message)
        wrap.scrollTop = wrap.scrollHeight - prevH;
    } catch {}
    _loadingMoreMsgs = false;
}

// ── Dynamic suggestion engine ────────────────────────────────────────
const TOPIC_SUGGESTIONS = {
    rice:      { re: /rice|ধান|paddy|boro|aman|aus|আমন|আউশ|বোরো|dhan/i,      cards: [
        { icon:'grass',      text:'ধানের পোকামাকড় চিহ্নিতকরণ',       prompt:'আমার ধান গাছে কোন রোগ বা পোকার আক্রমণ হয়েছে, সমাধান দিন' },
        { icon:'water_drop', text:'AWD irrigation for rice',            prompt:'How to implement AWD irrigation technique to save water in rice farming?' },
        { icon:'science',    text:'ধানের সার প্রয়োগ তালিকা',          prompt:'বোরো ধানের জন্য সম্পূর্ণ সার প্রয়োগ তালিকা দিন' },
    ]},
    vegetable: { re: /vegetable|সবজি|tomato|টমেটো|potato|আলু|brinjal|বেগুন|cabbage|okra|ঢেঁড়স/i, cards: [
        { icon:'eco',        text:'সবজি চাষের সেরা পদ্ধতি',           prompt:'এই মৌসুমে কোন সবজি চাষ করলে সবচেয়ে বেশি লাভ হবে?' },
        { icon:'bug_report', text:'Vegetable pest & disease control',   prompt:'How to control pests and diseases in vegetable garden organically?' },
        { icon:'water_drop', text:'Drip irrigation for vegetables',     prompt:'How to set up drip irrigation for vegetable farming in Bangladesh?' },
    ]},
    soil:      { re: /soil|মাটি|fertilizer|সার|urea|ইউরিয়া|compost|জৈব|pH|nitrogen/i, cards: [
        { icon:'science',    text:'মাটি পরীক্ষা ও সার সুপারিশ',       prompt:'আমার মাটির স্বাস্থ্য পরীক্ষা করবো কীভাবে এবং কী সার দেবো?' },
        { icon:'grass',      text:'জৈব সার তৈরির পদ্ধতি',             prompt:'বাড়িতে কম্পোস্ট ও ভার্মিকম্পোস্ট সার তৈরি করবো কীভাবে?' },
    ]},
    pest:      { re: /pest|পোকা|disease|রোগ|IPM|blast|blight|wilt|ঝলসা|রোগবালাই/i, cards: [
        { icon:'bug_report', text:'রোগ শনাক্ত করে সমাধান',            prompt:'আমার ফসলে রোগ হয়েছে, লক্ষণ দেখে সমাধান দিন' },
        { icon:'health_and_safety', text:'IPM organic methods',        prompt:'What are the most effective IPM methods for organic farming?' },
    ]},
    market:    { re: /price|দাম|market|বাজার|sell|বিক্রি|profit|লাভ|income/i, cards: [
        { icon:'price_check',text:'আজকের বাজার দর জানুন',             prompt:'বাংলাদেশে কৃষি পণ্যের আজকের বাজার দর ও বিক্রির পরামর্শ দিন' },
        { icon:'trending_up',text:'এই মৌসুমে লাভজনক ফসল',           prompt:'এই মৌসুমে কোন ফসল চাষ করলে সবচেয়ে বেশি লাভ হবে?' },
    ]},
    weather:   { re: /weather|আবহাওয়া|rain|বৃষ্টি|flood|বন্যা|drought|খরা|irrigation|সেচ/i, cards: [
        { icon:'wb_sunny',   text:'আবহাওয়া ও সেচ পরিকল্পনা',        prompt:'আগামী সপ্তাহের আবহাওয়া অনুযায়ী সেচ পরিকল্পনা দিন' },
        { icon:'water_drop', text:'Irrigation scheduling',             prompt:'How to create an efficient irrigation schedule for my crops?' },
    ]},
};

function getSeasonalSuggestions() {
    const month = new Date().getMonth() + 1;
    const seasonal = {
        1:  [{ icon:'grass',       text:'Boro rice fertilizer guide',      prompt:'What fertilizer should I apply for Boro rice in January and February?' },
             { icon:'wb_sunny',    text:'শীতকালীন ফসল পরিচর্যা',         prompt:'শীতকালীন ফসলকে ঠান্ডা থেকে রক্ষা করবো কীভাবে?' },
             { icon:'eco',         text:'Winter vegetables to grow',        prompt:'Which winter vegetables should I plant now for best yield?' },
             { icon:'science',     text:'Wheat disease prevention',         prompt:'How to prevent yellow rust and blast in wheat crop?' }],
        2:  [{ icon:'grass',       text:'Boro panicle initiation care',     prompt:'Boro rice is at panicle initiation stage — what care should I take?' },
             { icon:'eco',         text:'Mustard/potato harvest tips',      prompt:'How to properly harvest and store mustard and potato?' },
             { icon:'bug_report',  text:'Wheat blast monitoring',           prompt:'How to identify and prevent wheat blast disease?' },
             { icon:'science',     text:'Boro fertilizer top-dress',        prompt:'What is the correct urea top-dress schedule for Boro rice?' }],
        3:  [{ icon:'grass',       text:'বোরো ধান পাকার লক্ষণ',          prompt:'বোরো ধান কখন কাটার উপযুক্ত হয়, কীভাবে বুঝবো?' },
             { icon:'bug_report',  text:'BPH & blast monitoring',           prompt:'How to identify and control BPH and blast disease in Boro rice?' },
             { icon:'agriculture', text:'Aus nursery preparation',          prompt:'How to prepare seedbed for Aus rice in March-April?' },
             { icon:'eco',         text:'Summer vegetable planting',        prompt:'Which summer vegetables to plant in March for good yield?' }],
        4:  [{ icon:'grass',       text:'বোরো ধান কাটার গাইড',           prompt:'বোরো ধান কাটার পর সঠিকভাবে মাড়াই ও সংরক্ষণ করবো কীভাবে?' },
             { icon:'agriculture', text:'Aus rice variety selection',       prompt:'Which Aus rice variety is best for my area? BRRI dhan27, 48, or 83?' },
             { icon:'eco',         text:'Summer crop planning',             prompt:'What summer crops should I plan for April-May planting?' },
             { icon:'water_drop',  text:'Monsoon drainage planning',        prompt:'How should I prepare drainage for my field before monsoon?' }],
        5:  [{ icon:'grass',       text:'বোরো কাটার পর জমি তৈরি',        prompt:'বোরো ধান কাটার পর আউশ মৌসুমের জন্য জমি কীভাবে তৈরি করবো?' },
             { icon:'agriculture', text:'আউশ ধানের জাত নির্বাচন',        prompt:'আউশ মৌসুমে কোন ধানের জাত বেশি লাভজনক ও রোগ প্রতিরোধী?' },
             { icon:'water_drop',  text:'বর্ষায় সেচ ও পানি ব্যবস্থাপনা', prompt:'বর্ষা মৌসুমে সেচ ও পানি ব্যবস্থাপনা কীভাবে করবো?' },
             { icon:'bug_report',  text:'Kharif season pest watch',         prompt:'What pests and diseases should I watch out for in Kharif season?' }],
        6:  [{ icon:'agriculture', text:'আমন ধানের নার্সারি বিছানা',     prompt:'আমন ধানের বীজতলা কীভাবে তৈরি করবো এবং কোন জাত ভালো?' },
             { icon:'grass',       text:'Aus rice care & fertilizer',       prompt:'Aus rice tillering stage — what fertilizer and care is needed?' },
             { icon:'water_drop',  text:'বন্যার আগে প্রস্তুতি',          prompt:'বন্যাপ্রবণ এলাকায় ধান রক্ষার জন্য কী করবো?' },
             { icon:'eco',         text:'Monsoon vegetable guide',          prompt:'Which vegetables grow well during monsoon season in Bangladesh?' }],
        7:  [{ icon:'agriculture', text:'আমন রোপণের সঠিক সময়',         prompt:'আমন ধান রোপণের সঠিক সময়, গভীরতা ও দূরত্ব কত হবে?' },
             { icon:'grass',       text:'Aus rice harvest guide',           prompt:'How to harvest and store Aus rice properly?' },
             { icon:'water_drop',  text:'Flood zone crop management',       prompt:'How to manage crops in flood-prone areas during heavy monsoon?' },
             { icon:'bug_report',  text:'Aman blast disease prevention',    prompt:'How to prevent blast disease in Aman rice during monsoon?' }],
        8:  [{ icon:'agriculture', text:'আমন ধানের সার ব্যবস্থাপনা',    prompt:'আমন ধানের কুশি পর্যায়ে কী সার দেবো এবং কতটুকু?' },
             { icon:'bug_report',  text:'BPH & sheath blight in Aman',      prompt:'How to control brown plant hopper and sheath blight in Aman rice?' },
             { icon:'water_drop',  text:'Waterlogging management',          prompt:'How to manage waterlogging and drainage in my rice field?' },
             { icon:'eco',         text:'Post-flood crop recovery',         prompt:'My crops were flooded — how do I recover and replant?' }],
        9:  [{ icon:'agriculture', text:'আমন শীষ বের হওয়ার পরিচর্যা',  prompt:'আমন ধানের শীষ বের হওয়ার সময় কী যত্ন নেবো?' },
             { icon:'eco',         text:'Early Rabi crop planning',         prompt:'Rabi season is coming — which crops to plan for October-November?' },
             { icon:'bug_report',  text:'BPH monitoring in Aman',           prompt:'How to identify and control brown plant hopper in Aman rice?' },
             { icon:'science',     text:'Soil prep for Rabi season',        prompt:'How to prepare soil for Rabi crops after Aman harvest?' }],
        10: [{ icon:'agriculture', text:'আমন ধান কাটার প্রস্তুতি',      prompt:'আমন ধান পাকার লক্ষণ কী এবং কখন কাটবো?' },
             { icon:'grass',       text:'Boro nursery preparation',         prompt:'How to prepare Boro rice nursery bed in October-November?' },
             { icon:'eco',         text:'Potato & mustard planting guide',  prompt:'How and when to plant potato and mustard for best yield?' },
             { icon:'science',     text:'Winter vegetable fertilizer',      prompt:'What fertilizer is needed for winter vegetable farming?' }],
        11: [{ icon:'grass',       text:'বোরো ধান রোপণ গাইড',           prompt:'বোরো ধান রোপণের সঠিক পদ্ধতি, সার ও সেচ পরিকল্পনা দিন' },
             { icon:'eco',         text:'Wheat & mustard sowing tips',      prompt:'Best practices for sowing wheat and mustard in November?' },
             { icon:'science',     text:'Fertilizer for winter crops',      prompt:'Complete fertilizer guide for Boro rice and winter vegetables?' },
             { icon:'water_drop',  text:'Winter irrigation schedule',       prompt:'How to schedule irrigation for winter crops to save water?' }],
        12: [{ icon:'grass',       text:'Boro rice early care guide',       prompt:'Boro rice is at early tillering — what fertilizer and care is needed?' },
             { icon:'eco',         text:'শীতকালীন সবজি পরিচর্যা',        prompt:'শীতকালীন সবজির সঠিক পরিচর্যা ও রোগবালাই দমন পদ্ধতি দিন' },
             { icon:'science',     text:'Wheat at vegetative stage',        prompt:'Wheat is at vegetative stage — what fertilizer and irrigation is needed?' },
             { icon:'bug_report',  text:'Winter pest prevention',           prompt:'How to prevent common pests and diseases in winter crops?' }],
    };
    return seasonal[month] || seasonal[5];
}

function generateDynamicSuggestions(conversations) {
    const recentTitles = conversations.slice(0, 8).map(c => (c.title || '').toLowerCase());
    let matched = [];
    for (const data of Object.values(TOPIC_SUGGESTIONS)) {
        if (recentTitles.some(t => data.re.test(t))) {
            matched = [...matched, ...data.cards];
        }
    }
    const seen = new Set();
    matched = matched.filter(s => !seen.has(s.text) && seen.add(s.text));
    if (matched.length >= 4) return matched.slice(0, 4);
    const seasonal = getSeasonalSuggestions();
    for (const d of seasonal) {
        if (!seen.has(d.text) && matched.length < 4) { matched.push(d); seen.add(d.text); }
    }
    return matched.slice(0, 4);
}

function renderSuggestionCards(cards, gridId = 'suggestionsGrid') {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    grid.innerHTML = cards.map(s => `
        <div class="suggestion-card" data-prompt="${escHtml(s.prompt)}" onclick="sendSuggestion(this.dataset.prompt)">
            <div class="suggestion-icon-wrap">
                <span class="material-icons">${escHtml(s.icon)}</span>
            </div>
            <div class="suggestion-text">${escHtml(s.text)}</div>
        </div>`).join('');
}

function updateWelcomeSuggestions(conversations) {
    const ws = document.getElementById('welcomeScreen');
    if (!ws) return;
    const label = document.getElementById('suggestionsLabel');
    if (conversations && conversations.length > 0) {
        const cards = generateDynamicSuggestions(conversations);
        renderSuggestionCards(cards);
        if (label) {
            label.innerHTML = `<span class="material-icons">history</span>Based on your farming history`;
        }
    } else {
        renderSuggestionCards(getSeasonalSuggestions());
        if (label) {
            label.innerHTML = `<span class="material-icons">tips_and_updates</span>Get started`;
        }
    }
}

// ── New chat ────────────────────────────────────────────────────────
function newChat() {
    currentCid        = null;
    currentIsPinned   = false;
    currentIsArchived = false;
    currentTitle      = T.newChat;
    _msgOffset        = 0;
    _hasMoreMsgs      = false;
    _loadingMoreMsgs  = false;
    history.replaceState({}, '', BASE_URL + 'agent/chat');
    updateTitleDisplay(T.newChat);
    hideConvoActions();

    const wrap = document.getElementById('messagesWrap');
    wrap.innerHTML = `
        <div class="welcome-screen" id="welcomeScreen">
            <img src="${BASE_URL}agent/assets/logo.png" class="welcome-logo" alt="Chashi Bhai">
            <h2 class="welcome-title">${T.welcomeTitle}</h2>
            <p class="welcome-sub">${T.welcomeSub}</p>
            <div class="suggestions-label" id="suggestionsLabel">
                <span class="material-icons">tips_and_updates</span>
                Get started
            </div>
            <div class="suggestions" id="suggestionsGrid"></div>
        </div>`;

    // Render seasonal defaults immediately, then refresh with personalized ones
    renderSuggestionCards(getSeasonalSuggestions(), 'suggestionsGrid');
    loadConversationList(null);
    closeSidebarMobile();
    document.getElementById('msgInput').focus();
}

// ── Custom confirm dialog ────────────────────────────────────────────
let _confirmCallback = null;
function showConfirm(title, msg, icon, iconColor, iconBg, okLabel, okClass, callback) {
    document.getElementById('confirmTitle').textContent    = title;
    document.getElementById('confirmMsg').textContent      = msg;
    document.getElementById('confirmIcon').textContent     = icon;
    document.getElementById('confirmIconWrap').style.color      = iconColor;
    document.getElementById('confirmIconWrap').style.background = iconBg;
    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.textContent  = okLabel;
    okBtn.className    = 'confirm-ok-btn ' + okClass;
    _confirmCallback   = callback;
    document.getElementById('confirmModal').classList.add('show');
}
function _confirmCancel() {
    document.getElementById('confirmModal').classList.remove('show');
    _confirmCallback = null;
}
function _confirmOk() {
    document.getElementById('confirmModal').classList.remove('show');
    if (_confirmCallback) { const cb = _confirmCallback; _confirmCallback = null; cb(); }
}

// ── Delete ──────────────────────────────────────────────────────────
async function deleteChat(cid, btn) {
    showConfirm(
        'Delete Conversation',
        'All messages will be permanently deleted. This cannot be undone.',
        'delete_forever', '#ef4444', 'rgba(239,68,68,0.15)',
        'Delete', 'confirm-danger',
        async () => {
            await fetch(CONV_API, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete', conversation_id:cid})
            });
            if (cid === currentCid) newChat();
            else loadConversationList();
        }
    );
}

async function deleteCurrentChat() {
    if (!currentCid) return;
    await deleteChat(currentCid, null);
}

// ── Rename ──────────────────────────────────────────────────────────
let renameTarget = null;
function openRenameModal() {
    renameTarget = currentCid;
    document.getElementById('renameInput').value = currentTitle;
    document.getElementById('renameModal').classList.add('show');
    setTimeout(() => document.getElementById('renameInput').focus(), 100);
}
function openRenameFor(cid, title) {
    renameTarget = cid;
    document.getElementById('renameInput').value = title;
    document.getElementById('renameModal').classList.add('show');
    setTimeout(() => document.getElementById('renameInput').focus(), 100);
}
function closeRenameModal() {
    document.getElementById('renameModal').classList.remove('show');
    renameTarget = null;
}
async function confirmRename() {
    const title = document.getElementById('renameInput').value.trim();
    if (!title || !renameTarget) return;
    await fetch(CONV_API, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'rename', conversation_id:renameTarget, title})
    });
    if (renameTarget === currentCid) { currentTitle = title; updateTitleDisplay(title); }
    loadConversationList(currentCid);
    closeRenameModal();
}
document.addEventListener('keydown', e => {
    // Any key press stops active speech (except modifier-only keys)
    if (activeSpeakBtn && !['Control','Alt','Shift','Meta','CapsLock'].includes(e.key)) {
        stopVoice();
    }

    if (e.key === 'Escape') {
        closeRenameModal();
        closeMemoryModal();
        closeBookmarksModal();
        closeShareModal();
        closeSearch();
        closeQuickPanel();
        document.getElementById('shortcutsOverlay')?.classList.remove('show');
        document.getElementById('emojiPanel')?.classList.remove('show');
        document.getElementById('exportModal')?.classList.remove('show');
        document.getElementById('imgLightbox')?.classList.remove('show');
    }
    if (e.key === 'Enter' && document.getElementById('renameModal').classList.contains('show')) confirmRename();

    if (e.ctrlKey && !e.shiftKey && !e.altKey) {
        if (e.key === 'k') { e.preventDefault(); newChat(); }
        if (e.key === 'e') { e.preventDefault(); exportConversation(); }
        if (e.key === 'b') { e.preventDefault(); toggleSidebar(); }
        if (e.key === 'm') { e.preventDefault(); openMemoryPanel(); }
        if (e.key === 'f') { e.preventDefault(); openSearch(); }
        if (e.key === 'i') { e.preventDefault(); openImagePicker(); }
        if (e.key === '/') { e.preventDefault(); document.getElementById('shortcutsOverlay')?.classList.toggle('show'); }
    }
});

// Any click anywhere stops active speech (except on the speak button itself, which toggles)
document.addEventListener('click', e => {
    if (!activeSpeakBtn) return;
    if (activeSpeakBtn.contains(e.target)) return; // speak btn handles itself via speakMsg
    stopVoice();
}, true); // capture phase so it fires before button handlers

// ── UI helpers ──────────────────────────────────────────────────────
function updateTitleDisplay(t) {
    document.getElementById('chatTitleText').textContent = t;
    document.title = t + ' — Smart Chashi';
}
function showConvoActions() {
    ['renameBtn','deleteBtn','shareBtn','pinBtn','archiveBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    });
}
function hideConvoActions() {
    ['renameBtn','deleteBtn','shareBtn','pinBtn','archiveBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
}

function toggleSidebar() {
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth < 768;
    if (isMobile) {
        sb.classList.toggle('open');
        ov.classList.toggle('active');
    } else {
        sb.classList.toggle('collapsed');
    }
}
function closeSidebarMobile() {
    if (window.innerWidth < 768) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }
}

// ── Copy & Speak ────────────────────────────────────────────────────
function copyMsg(btn) {
    const bubble = btn.closest('.msg-row')?.querySelector('.msg-bubble');
    const text   = bubble?.innerText || '';
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('.material-icons');
        icon.textContent = 'check';
        btn.style.color = '#4ade80';
        setTimeout(() => { icon.textContent = 'content_copy'; btn.style.color = ''; }, 1500);
    }).catch(() => {
        // Fallback for older browsers / non-https
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        const icon = btn.querySelector('.material-icons');
        icon.textContent = 'check';
        setTimeout(() => { icon.textContent = 'content_copy'; }, 1500);
    });
}

// Track which per-message speak button is currently active
let activeSpeakBtn = null;

function resetSpeakBtn() {
    if (activeSpeakBtn) {
        activeSpeakBtn.classList.remove('speaking');
        activeSpeakBtn.querySelector('.material-icons').textContent = 'volume_up';
        activeSpeakBtn.title = 'Read aloud';
        activeSpeakBtn = null;
    }
}

function speakMsg(btn) {
    if (!('speechSynthesis' in window)) {
        showCustomAlert('Text-to-speech is not supported in this browser.');
        return;
    }

    const bubble = btn.closest('.msg-row')?.querySelector('.msg-bubble');
    const text   = bubble?.innerText?.trim() || '';
    if (!text) return;

    // Toggle: same button while speaking → stop
    if (btn === activeSpeakBtn) {
        stopVoice();
        return;
    }

    stopVoice(); // stop previous + reset its button

    // Strip markdown/code for clean audio
    const cleanText = text
        .replace(/#{1,6}\s/g, '')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/\*([^*]+)\*/g, '$1')
        .replace(/`[^`]+`/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    if (!cleanText) return;

    // Mark button as active immediately
    activeSpeakBtn = btn;
    btn.classList.add('speaking');
    btn.querySelector('.material-icons').textContent = 'volume_off';
    btn.title = 'Stop reading';

    // Small gap so synth.cancel() settles before new speak
    setTimeout(() => {
        const eng = window.speechSynthesis;
        let voices = eng.getVoices();

        function doSpeak() {
            voices = eng.getVoices();

            // Detect if text is mostly Bengali (Unicode block 0980-09FF)
            const bengaliChars = (cleanText.match(/[ঀ-৿]/g) || []).length;
            const isBengali    = bengaliChars / cleanText.length > 0.15;

            let voice = null;
            if (isBengali) {
                // Try Bengali voices first
                voice = voices.find(v => v.lang === 'bn-BD')
                     || voices.find(v => v.lang === 'bn-IN')
                     || voices.find(v => v.lang.startsWith('bn'));
            }
            // Fallback: any English voice (always available)
            if (!voice) voice = voices.find(v => v.lang === 'en-US')
                             || voices.find(v => v.lang.startsWith('en'))
                             || voices[0];

            if (!voice) {
                resetSpeakBtn();
                showCustomAlert('No text-to-speech voice available. Please install a TTS voice in your OS settings.');
                return;
            }

            const utt   = new SpeechSynthesisUtterance(cleanText);
            utt.voice   = voice;
            utt.lang    = voice.lang;
            utt.rate    = voice.lang.startsWith('bn') ? 0.8 : 0.92;
            utt.pitch   = 1.0;
            utt.volume  = 1.0;

            const vi = document.getElementById('voiceIndicator');
            vi.classList.add('active');
            startSilence();

            utt.onend   = () => { vi.classList.remove('active'); stopSilence(); resetSpeakBtn(); };
            utt.onerror = () => { vi.classList.remove('active'); stopSilence(); resetSpeakBtn(); };

            eng.speak(utt);
        }

        // voices may be empty on first call — wait for them
        if (voices.length) {
            doSpeak();
        } else {
            eng.onvoiceschanged = () => { eng.onvoiceschanged = null; doSpeak(); };
            // Hard timeout in case onvoiceschanged never fires
            setTimeout(() => { if (activeSpeakBtn === btn) { resetSpeakBtn(); } }, 3000);
        }
    }, 80);
}

function speakAIResponse(text, detectedLang, alwaysSpeak = false) {
    if (!('speechSynthesis' in window)) return;

    const shouldSpeak = isVoiceConversation || alwaysSpeak || localStorage.getItem('alwaysSpeak') === 'true';
    if (!shouldSpeak) return;

    const synthEngine = window.speechSynthesis;

    // Strip HTML/markdown for clean speech
    let cleanText = text
        .replace(/<[^>]*>/g, ' ')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/\*([^*]+)\*/g, '$1')
        .replace(/#{1,6}\s/g, '')
        .replace(/`[^`]+`/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    if (!cleanText) return;

    synthEngine.cancel();

    const voices     = synthEngine.getVoices();
    const langMap    = { bn: 'bn-BD', en: 'en-US' };
    let targetLang   = detectedLang;
    if (targetLang && langMap[targetLang]) targetLang = langMap[targetLang];
    if (!targetLang) targetLang = document.getElementById('micLang')?.value || 'bn-BD';

    let voice = null, actualLang = targetLang;

    if (targetLang.startsWith('bn')) {
        // Bengali voice detection chain
        const patterns = [
            v => v.lang === 'bn-BD',
            v => v.lang === 'bn-IN',
            v => v.lang === 'bn',
            v => v.lang.startsWith('bn-'),
            v => v.lang.startsWith('bn'),
            v => v.name.toLowerCase().includes('bengali'),
            v => v.name.toLowerCase().includes('bangla'),
            v => v.name.toLowerCase().includes('bangladesh'),
            v => v.name.toLowerCase().includes('bn-bd'),
        ];
        for (const p of patterns) {
            voice = voices.find(p);
            if (voice) { actualLang = voice.lang; break; }
        }
    }

    if (!voice) {
        // Bengali: use Google Translate TTS instead of falling back to English
        if (targetLang.startsWith('bn')) {
            useGoogleTTS(cleanText);
            return;
        }
        voice = voices.find(v => v.lang === targetLang);
        if (!voice) {
            const code = targetLang.split('-')[0];
            voice = voices.find(v => v.lang.startsWith(code));
        }
        if (!voice) voice = voices.find(v => v.lang.startsWith('en')) || voices[0];
        if (voice) actualLang = voice.lang;
    }
    if (!voice) return;

    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.voice  = voice;
    utterance.lang   = actualLang;
    utterance.rate   = actualLang.startsWith('bn') ? 0.8 : 0.9;
    utterance.pitch  = 1.0;
    utterance.volume = 1.0;

    const vi = document.getElementById('voiceIndicator');
    vi.classList.add('active');
    startSilence();

    let startTimeout = setTimeout(() => { synthEngine.cancel(); cleanupVI(); }, 25000);
    let started = false;

    utterance.onstart = () => { started = true; clearTimeout(startTimeout); };
    utterance.onend   = () => { if (!started) clearTimeout(startTimeout); cleanupVI(); };
    utterance.onerror = (e) => {
        if (!started) clearTimeout(startTimeout);
        if (e.error === 'language-not-supported' || e.error === 'voice-unavailable') {
            const fb = voices.find(v => v.lang.startsWith('en') && v !== voice);
            if (fb) {
                const fbu = new SpeechSynthesisUtterance(cleanText);
                fbu.voice = fb; fbu.lang = fb.lang; fbu.rate = 0.9;
                fbu.onend = cleanupVI; fbu.onerror = cleanupVI;
                synthEngine.speak(fbu);
                return;
            }
        }
        cleanupVI();
    };

    synthEngine.speak(utterance);

    function cleanupVI() {
        vi.classList.remove('active');
        stopSilence();
        currentUtterance = null;
        resetSpeakBtn();
    }
}

// Bengali TTS via Google Translate audio fallback
function useGoogleTTS(text) {
    const vi = document.getElementById('voiceIndicator');
    vi.classList.add('active');
    startSilence();

    // Split into chunks of ≤180 chars at word boundaries
    const parts = [];
    const words  = text.split(' ');
    let chunk    = '';
    for (const word of words) {
        const next = chunk ? chunk + ' ' + word : word;
        if (next.length > 180 && chunk) { parts.push(chunk); chunk = word; }
        else { chunk = next; }
    }
    if (chunk) parts.push(chunk);

    let idx = 0;
    let currentAudio = null;

    function cleanup() {
        vi.classList.remove('active');
        stopSilence();
        currentUtterance = null;
        resetSpeakBtn();
    }

    function playNext() {
        if (idx >= parts.length) { cleanup(); return; }
        const url   = 'https://translate.google.com/translate_tts?ie=UTF-8&q='
                    + encodeURIComponent(parts[idx]) + '&tl=bn&client=tw-ob';
        currentAudio = new Audio(url);
        currentAudio.onended = () => { idx++; playNext(); };
        currentAudio.onerror = cleanup;
        currentAudio.play().catch(cleanup);
        idx++;
    }

    // Expose stop for stopVoice()
    window._gttsAudio = () => { if (currentAudio) { currentAudio.pause(); currentAudio = null; } cleanup(); };
    playNext();
}

function stopVoice() {
    synth.cancel();
    if (typeof window._gttsAudio === 'function') { window._gttsAudio(); window._gttsAudio = null; }
    stopSilence();
    document.getElementById('voiceIndicator').classList.remove('active');
    resetSpeakBtn();
}
document.getElementById('stopVoiceBtn').addEventListener('click', stopVoice);

// ── Voice input ─────────────────────────────────────────────────────
function openMicModal() {
    document.getElementById('micModal').classList.add('show');
    document.getElementById('recognizedText').textContent = 'Press the mic button to start speaking…';
    populateVoiceLangs();
}
function closeMicModal() {
    if (isRecording) stopRecognition();
    document.getElementById('micModal').classList.remove('show');
}

function toggleRecording() {
    if (isRecording) stopRecognition();
    else startRecognition();
}

function startRecognition() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        showCustomAlert('Voice recognition is not supported in this browser. Please use Chrome or Edge.');
        return;
    }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SR();
    recognition.lang = document.getElementById('micLang').value;
    recognition.interimResults = true;
    recognition.continuous = false; // mobile-safe

    // Mobile optimization
    if (/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)) {
        recognition.continuous = false;
    }

    const btn = document.getElementById('micRecordBtn');
    btn.classList.add('recording');
    btn.querySelector('.material-icons').textContent = 'stop';
    isRecording = true;
    document.getElementById('recognizedText').textContent = 'Listening…';

    recognition.onresult = e => {
        let transcript = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            transcript += e.results[i][0].transcript;
        }
        document.getElementById('recognizedText').textContent = transcript;
    };

    recognition.onend = () => {
        isRecording = false;
        btn.classList.remove('recording');
        btn.querySelector('.material-icons').textContent = 'mic';
        const txt = document.getElementById('recognizedText').textContent.trim();
        if (txt && txt !== 'Listening…' && txt !== 'Press the mic button to start speaking…') {
            isVoiceConversation = true; // mark as voice conversation for auto-speak
            document.getElementById('msgInput').value = txt;
            document.getElementById('sendBtn').disabled = false;
            closeMicModal();
            // Auto-send after brief delay so modal closes cleanly
            setTimeout(() => sendMessage(), 120);
        }
    };

    recognition.onerror = e => {
        isRecording = false;
        btn.classList.remove('recording');
        btn.querySelector('.material-icons').textContent = 'mic';
        const errorMessages = {
            'no-speech':           'No speech detected. Please speak clearly and try again.',
            'audio-capture':       'Microphone not found. Please check your microphone.',
            'not-allowed':         'Microphone access denied. Please allow microphone permissions.',
            'network':             'Network error during voice recognition.',
            'service-not-allowed': 'Voice recognition service not available. Try again later.',
        };
        const msg = errorMessages[e.error] || 'Voice error: ' + e.error;
        document.getElementById('recognizedText').textContent = msg;
        showCustomAlert(msg);
    };

    recognition.start();
}

function stopRecognition() {
    if (recognition) recognition.stop();
    isRecording = false;
    const btn = document.getElementById('micRecordBtn');
    btn.classList.remove('recording');
    btn.querySelector('.material-icons').textContent = 'mic';
}

// ── Sound effects ───────────────────────────────────────────────────
function playSound(type) {
    try {
        const map = {
            send:    BASE_URL + 'agent/assets/audio/message send.mp3',
            receive: BASE_URL + 'agent/assets/audio/message-notification.mp3',
        };
        if (map[type]) new Audio(map[type]).play();
    } catch {}
}

// ── Utility ─────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Voice keep-alive (prevents mobile TTS from cutting off) ─────────
function startSilence() {
    stopSilence();
    silenceInterval = setInterval(() => {
        if (!window.speechSynthesis.speaking) {
            const u = new SpeechSynthesisUtterance(' ');
            u.volume = 0;
            window.speechSynthesis.speak(u);
        }
    }, 10000);
}
function stopSilence() {
    if (silenceInterval) { clearInterval(silenceInterval); silenceInterval = null; }
}

// ── Custom alert toast ───────────────────────────────────────────────
function showCustomAlert(msg) {
    const el = document.getElementById('customAlert');
    document.getElementById('customAlertMsg').textContent = msg;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(hideCustomAlert, 6000);
}
function hideCustomAlert() {
    document.getElementById('customAlert').classList.remove('show');
}

// ── Always-speak toggle ──────────────────────────────────────────────
function toggleAlwaysSpeak() {
    const current = localStorage.getItem('alwaysSpeak') === 'true';
    localStorage.setItem('alwaysSpeak', (!current).toString());
    const btn  = document.getElementById('speakToggleBtn');
    const icon = btn.querySelector('.material-icons');
    if (!current) {
        btn.classList.add('voice-on');
        icon.textContent = 'volume_up';
    } else {
        btn.classList.remove('voice-on');
        icon.textContent = 'volume_off';
        synth.cancel();
        stopSilence();
        isVoiceConversation = false;
        document.getElementById('voiceIndicator').classList.remove('active');
    }
}

// ── Personality mode & language preference ───────────────────────────
let currentPersonality = localStorage.getItem('personality') || 'general';
let forceLang = localStorage.getItem('forceLang') || '';

function setPersonality(mode) {
    currentPersonality = mode;
    localStorage.setItem('personality', mode);
}

function toggleLang() {
    const btn = document.getElementById('langToggleBtn');
    if (forceLang === '') {
        forceLang = 'bn';
        btn.textContent = '🇧🇩 বাংলা';
        btn.classList.add('active');
    } else if (forceLang === 'bn') {
        forceLang = 'en';
        btn.textContent = '🇬🇧 English';
        btn.classList.add('active');
    } else {
        forceLang = '';
        btn.textContent = '🌐 Auto';
        btn.classList.remove('active');
    }
    localStorage.setItem('forceLang', forceLang);
}

// ── UI language toggle ───────────────────────────────────────────────
function toggleUiLang() {
    const current = document.documentElement.lang || '<?php echo $currentLang; ?>';
    const next    = current === 'bn' ? 'en' : 'bn';
    fetch('<?php echo $base_url; ?>api/set-language.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'language=' + next
    }).then(() => location.reload());
}

// ── Export conversation ──────────────────────────────────────────────
function exportConversation() {
    const rows = document.querySelectorAll('.msg-row');
    if (!rows.length) { showCustomAlert('No messages to export.'); return; }
    document.getElementById('exportModal').classList.add('show');
}

let exportFmt = 'txt';
function selectExportFmt(fmt) {
    exportFmt = fmt;
    ['Txt','Md','Json'].forEach(f => {
        const el = document.getElementById('fmt' + f);
        if (el) el.classList.toggle('active', f.toLowerCase() === fmt);
    });
}

function doExport() {
    const rows = document.querySelectorAll('.msg-row');
    if (!rows.length) { showCustomAlert('No messages to export.'); return; }

    const messages = [];
    rows.forEach(row => {
        const isUser = row.classList.contains('user');
        const bubble = row.querySelector('.msg-bubble');
        if (bubble) messages.push({ role: isUser ? 'user' : 'assistant', content: bubble.innerText.trim() });
    });

    let content = '', ext = exportFmt;
    if (exportFmt === 'txt') {
        content  = 'Chashi Bhai AI — Conversation Export\n';
        content += 'Date: ' + new Date().toLocaleString() + '\n';
        content += 'Title: ' + currentTitle + '\n';
        content += '='.repeat(50) + '\n\n';
        messages.forEach(m => { content += (m.role === 'user' ? 'You:\n' : 'Chashi Bhai:\n') + m.content + '\n\n'; });
    } else if (exportFmt === 'md') {
        content  = `# ${currentTitle}\n\n`;
        content += `**Exported:** ${new Date().toLocaleString()}\n\n---\n\n`;
        messages.forEach(m => {
            if (m.role === 'user') content += `**You:** ${m.content}\n\n`;
            else content += `**Chashi Bhai:**\n\n${m.content}\n\n---\n\n`;
        });
    } else {
        content = JSON.stringify({
            title: currentTitle,
            exported_at: new Date().toISOString(),
            conversation_id: currentCid,
            messages
        }, null, 2);
        ext = 'json';
    }

    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const a    = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(blob),
        download: 'chashi-bhai-' + Date.now() + '.' + ext
    });
    a.click();
    URL.revokeObjectURL(a.href);
    document.getElementById('exportModal').classList.remove('show');
}


// ── Regenerate last AI response ──────────────────────────────────────
let lastUserMessage = null;
function regenerateResponse() {
    if (!lastUserMessage || isSending) return;
    const rows = document.querySelectorAll('.msg-row.assistant');
    if (rows.length) rows[rows.length - 1].remove();
    sendMessage(lastUserMessage);
}

// ── Emoji picker ─────────────────────────────────────────────────────
const FARMING_EMOJIS = [
    '🌾','🌿','🌱','🌻','🌽','🥦','🥕','🌶️','🍅','🍆','🫑','🌴','🌳','🍀',
    '☀️','🌧️','💧','🌈','⛅','🌫️','🌡️','❄️','🌊','🏔️',
    '🐄','🐓','🐑','🐐','🐟','🦟','🐛','🐜','🦗','🐝','🐞',
    '🧪','🔬','📊','💰','🏡','🚜','🌍',
    '✅','⚠️','💡','📅','📝','🔔','👍','🙏','💚','❤️',
];

function initEmojiPanel() {
    const panel = document.getElementById('emojiPanel');
    if (!panel) return;
    panel.innerHTML = FARMING_EMOJIS.map(e =>
        '<button class="emoji-btn-item" onclick="insertEmoji(\'' + e + '\')" title="' + e + '">' + e + '</button>'
    ).join('');
}

function toggleEmojiPicker() {
    document.getElementById('emojiPanel').classList.toggle('show');
}

function insertEmoji(emoji) {
    const ta = document.getElementById('msgInput');
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + emoji + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + emoji.length;
    ta.dispatchEvent(new Event('input'));
    ta.focus();
    document.getElementById('emojiPanel').classList.remove('show');
}

// Close emoji panel on outside click
document.addEventListener('click', e => {
    const panel = document.getElementById('emojiPanel');
    const btn = document.getElementById('emojiBtn');
    if (panel?.classList.contains('show') && !panel.contains(e.target) && e.target !== btn && !btn?.contains(e.target)) {
        panel.classList.remove('show');
    }
});

// ── Follow-up chips ──────────────────────────────────────────────────
function displayFollowUps(questions, afterRow) {
    if (!questions || !questions.length || !afterRow) return;
    const wrap = document.createElement('div');
    wrap.className = 'followup-wrap';
    questions.forEach(q => {
        const btn = document.createElement('button');
        btn.className = 'followup-chip';
        btn.textContent = q;
        btn.onclick = () => {
            wrap.remove();
            sendMessage(q);
        };
        wrap.appendChild(btn);
    });
    afterRow.parentNode.insertBefore(wrap, afterRow.nextSibling);
}

// ── Message feedback (thumbs up / down) ──────────────────────────────
async function submitFeedback(msgId, value, btn) {
    const row    = btn.closest('.msg-row');
    const isUp   = btn.classList.contains('up');
    const isDown = btn.classList.contains('down');

    // Toggle off if already active
    const currentActive = isUp ? btn.classList.contains('active') : btn.classList.contains('active');
    const newValue = currentActive ? 0 : value;

    // Update UI immediately
    row.querySelectorAll('.fb-btn').forEach(b => b.classList.remove('active'));
    if (newValue !== 0) btn.classList.add('active');

    try {
        await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'feedback', message_id: msgId, value: newValue})
        });
    } catch {}
}

// ── Conversation search / filter ──────────────────────────────────────
let _allConvItems = [];

function filterConversations(query) {
    const q = query.trim().toLowerCase();
    const items = document.querySelectorAll('.conv-item');
    const labels = document.querySelectorAll('.conv-group-label');

    if (!q) {
        items.forEach(el => el.style.display = '');
        labels.forEach(el => el.style.display = '');
        return;
    }

    const visibleGroups = new Set();
    items.forEach(el => {
        const title = (el.querySelector('.conv-title')?.textContent || '').toLowerCase();
        const match = title.includes(q);
        el.style.display = match ? '' : 'none';
        if (match) visibleGroups.add(el.previousElementSibling?.classList.contains('conv-group-label') ? el.previousElementSibling : null);
    });

    // Show/hide group labels based on visible items
    labels.forEach(label => {
        let next = label.nextElementSibling;
        let hasVisible = false;
        while (next && !next.classList.contains('conv-group-label')) {
            if (next.classList.contains('conv-item') && next.style.display !== 'none') hasVisible = true;
            next = next.nextElementSibling;
        }
        label.style.display = hasVisible ? '' : 'none';
    });
}

// ── Code block enhancements (copy button + language label) ────────────
function enhanceCodeBlocks(container) {
    container.querySelectorAll('pre.code-block').forEach(pre => {
        if (pre.parentNode.classList.contains('code-block-wrap')) return;
        const wrap = document.createElement('div');
        wrap.className = 'code-block-wrap';

        const code  = pre.querySelector('code');
        const lang  = (code?.className || '').replace('lang-', '') || 'code';
        const header = document.createElement('div');
        header.className = 'code-block-header';
        header.innerHTML = `
            <span class="code-lang-label">${escHtml(lang)}</span>
            <button class="code-copy-btn" onclick="copyCodeBlock(this)">
                <span class="material-icons">content_copy</span> Copy
            </button>`;

        pre.parentNode.insertBefore(wrap, pre);
        wrap.appendChild(header);
        wrap.appendChild(pre);
    });
}

function copyCodeBlock(btn) {
    const pre  = btn.closest('.code-block-wrap').querySelector('pre');
    const text = pre?.innerText || '';
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<span class="material-icons">check</span> Copied!';
        setTimeout(() => btn.innerHTML = '<span class="material-icons">content_copy</span> Copy', 2000);
    });
}

// ── Memory panel ──────────────────────────────────────────────────────
async function openMemoryPanel() {
    document.getElementById('memoryModal').classList.add('show');
    await refreshMemoryList();
}

function closeMemoryModal() {
    document.getElementById('memoryModal').classList.remove('show');
}

async function refreshMemoryList() {
    const listEl = document.getElementById('memoryList');
    listEl.innerHTML = '<div class="memory-empty">Loading…</div>';

    try {
        const res  = await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'memory_list'})
        });
        const data = await res.json();
        const mem  = data.memory || [];

        if (!mem.length) {
            listEl.innerHTML = '<div class="memory-empty">No memories yet. Chat to build your profile!</div>';
            return;
        }

        const iconMap = {
            grows_boro_rice: '🌾', grows_aman_rice: '🌾', grows_aus_rice: '🌾',
            grows_crop: '🌿', farming_type: '🥬', farming_method: '♻️',
            user_district: '📍', farm_size: '📐', preferred_language: '🌐',
            uses_technology: '⚙️',
        };

        listEl.innerHTML = mem.map(m => `
            <div class="memory-item" data-mem-id="${m.id}">
                <span class="memory-item-icon">${iconMap[m.memory_key] || '🧠'}</span>
                <div class="memory-item-body">
                    <div class="memory-item-key">${escHtml(m.memory_key.replace(/_/g,' '))}</div>
                    <div class="memory-item-val">${escHtml(m.memory_value)}</div>
                    <div class="memory-item-src">${m.source === 'manual' ? '✏️ Manual' : '🤖 Auto-detected'} · ${new Date(m.updated_at).toLocaleDateString()}</div>
                </div>
                <button class="memory-del-btn" onclick="deleteMemoryItem(${m.id}, this)" title="Delete">
                    <span class="material-icons">close</span>
                </button>
            </div>`).join('');
    } catch {
        listEl.innerHTML = '<div class="memory-empty">Failed to load memory.</div>';
    }
}

async function deleteMemoryItem(id, btn) {
    btn.closest('.memory-item').style.opacity = '0.4';
    await fetch(CONV_API, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action: 'memory_delete', id})
    });
    await refreshMemoryList();
}

async function clearAllMemory() {
    if (!confirm('Clear all AI memory about you? This cannot be undone.')) return;
    await fetch(CONV_API, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action: 'memory_clear'})
    });
    await refreshMemoryList();
    showCustomAlert('Memory cleared.');
}

async function saveManualMemory() {
    const key = document.getElementById('memKeyInput').value.trim();
    const val = document.getElementById('memValInput').value.trim();
    if (!key || !val) { showCustomAlert('Please fill in both key and value.'); return; }

    await fetch(CONV_API, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action: 'memory_save', key, value: val})
    });
    document.getElementById('memKeyInput').value = '';
    document.getElementById('memValInput').value = '';
    await refreshMemoryList();
}

// ── In-conversation message search ──────────────────────────────────
let searchMatches = [], searchIdx = 0;

function openSearch() {
    const bar = document.getElementById('searchBar');
    // Move bar into chat-main so it sits under topbar
    const main = document.querySelector('.chat-main');
    if (main && !main.contains(bar)) main.prepend(bar);
    bar.classList.add('show');
    document.getElementById('searchInput').focus();
}

function closeSearch() {
    document.getElementById('searchBar').classList.remove('show');
    document.getElementById('searchInput').value = '';
    clearSearchHL();
    searchMatches = [];
    document.getElementById('searchCount').textContent = '';
}

function clearSearchHL() {
    document.querySelectorAll('.search-highlight').forEach(el => {
        const t = document.createTextNode(el.textContent);
        el.parentNode.replaceChild(t, el);
    });
    document.querySelectorAll('.msg-bubble').forEach(b => b.normalize());
}

function performSearch(query) {
    clearSearchHL();
    searchMatches = []; searchIdx = 0;
    const countEl = document.getElementById('searchCount');
    countEl.textContent = '';
    if (!query.trim()) return;
    const q = query.trim().toLowerCase();
    document.querySelectorAll('.msg-bubble').forEach(b => hlNode(b, q));
    searchMatches = Array.from(document.querySelectorAll('.search-highlight'));
    if (!searchMatches.length) { countEl.textContent = '0 results'; return; }
    searchMatches[0].classList.add('current');
    searchMatches[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    countEl.textContent = `1/${searchMatches.length}`;
}

function hlNode(node, q) {
    if (node.nodeType === Node.TEXT_NODE) {
        const idx = node.textContent.toLowerCase().indexOf(q);
        if (idx === -1) return;
        const span = document.createElement('span');
        span.className = 'search-highlight';
        span.textContent = node.textContent.substr(idx, q.length);
        const after = node.splitText(idx);
        after.textContent = after.textContent.substr(q.length);
        node.parentNode.insertBefore(span, after);
    } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'SCRIPT') {
        Array.from(node.childNodes).forEach(c => hlNode(c, q));
    }
}

function navigateSearch(dir) {
    if (!searchMatches.length) return;
    searchMatches[searchIdx].classList.remove('current');
    searchIdx = (searchIdx + dir + searchMatches.length) % searchMatches.length;
    searchMatches[searchIdx].classList.add('current');
    searchMatches[searchIdx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('searchCount').textContent = `${searchIdx + 1}/${searchMatches.length}`;
}

function searchKeyNav(e) {
    if (e.key === 'Enter') { e.preventDefault(); navigateSearch(e.shiftKey ? -1 : 1); }
    if (e.key === 'Escape') { e.preventDefault(); closeSearch(); }
}

// ── Image attachment ─────────────────────────────────────────────────
let attachedImages = [];

function openImagePicker() { document.getElementById('imgFileInput').click(); }

function handleImageFile(files) {
    if (!files || !files.length) return;
    const slots = 3 - attachedImages.length;
    if (slots <= 0) { showCustomAlert('Maximum 3 images per message.'); return; }
    let added = 0;
    Array.from(files).slice(0, slots).forEach(file => {
        if (!file.type.startsWith('image/')) { showCustomAlert('Only image files are supported.'); return; }
        if (file.size > 8 * 1024 * 1024) { showCustomAlert(`"${file.name}" is too large (max 8 MB).`); return; }
        const reader = new FileReader();
        reader.onload = e => {
            attachedImages.push({ dataUrl: e.target.result, name: file.name });
            added++;
            renderImagePreviews();
            // Reset file input so same file can be re-selected
            document.getElementById('imgFileInput').value = '';
        };
        reader.readAsDataURL(file);
    });
}

function renderImagePreviews() {
    const strip = document.getElementById('imgPreviewStrip');
    strip.innerHTML = '';

    if (!attachedImages.length) {
        strip.classList.remove('has-images');
        _updateImgAttachBadge(0);
        return;
    }

    strip.classList.add('has-images');

    attachedImages.forEach((img, i) => {
        const thumb = document.createElement('div');
        thumb.className = 'img-thumb';
        thumb.title     = img.name;
        thumb.onclick   = (e) => { if (!e.target.closest('.img-thumb-remove')) openLightbox(img.dataUrl); };
        thumb.innerHTML = `
            <img src="${img.dataUrl}" alt="">
            <span class="img-thumb-num">${i + 1}</span>
            <button class="img-thumb-remove" onclick="event.stopPropagation();removeImage(${i})" title="Remove">
                <span class="material-icons">close</span>
            </button>`;
        strip.appendChild(thumb);
    });

    // "Add more" button (up to 3 images)
    if (attachedImages.length < 3) {
        const addBtn = document.createElement('button');
        addBtn.className = 'img-add-more-btn';
        addBtn.title     = 'Add more images (max 3)';
        addBtn.onclick   = openImagePicker;
        addBtn.innerHTML = '<span class="material-icons">add_photo_alternate</span><span>Add</span>';
        strip.appendChild(addBtn);
    }

    // Count info
    const info = document.createElement('span');
    info.className   = 'img-preview-info';
    info.textContent = `${attachedImages.length}/3`;
    strip.appendChild(info);

    document.getElementById('sendBtn').disabled = false;
    _updateImgAttachBadge(attachedImages.length);
}

function _updateImgAttachBadge(count) {
    const btn = document.getElementById('imgAttachBtn');
    if (!btn) return;
    let badge = btn.querySelector('.img-badge');
    if (count > 0) {
        btn.classList.add('has-images');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'img-badge';
            btn.appendChild(badge);
        }
        badge.textContent = count;
    } else {
        btn.classList.remove('has-images');
        if (badge) badge.remove();
    }
}

function removeImage(i) {
    attachedImages.splice(i, 1);
    renderImagePreviews();
    if (!attachedImages.length && !document.getElementById('msgInput').value.trim()) {
        document.getElementById('sendBtn').disabled = true;
    }
}

// Paste handler for images
document.getElementById('msgInput').addEventListener('paste', e => {
    const items = e.clipboardData?.items;
    if (!items) return;
    Array.from(items).forEach(item => {
        if (item.type.startsWith('image/')) {
            e.preventDefault();
            handleImageFile([item.getAsFile()]);
        }
    });
});

// ── Full-page image drag-and-drop ────────────────────────────────────
(function() {
    const overlay = document.getElementById('dropOverlay');
    const inputDz = document.getElementById('inputDropZone');
    let dragCount = 0; // counter avoids false dragleave on child elements

    function hasFiles(dt) {
        if (!dt) return false;
        try { return Array.from(dt.types).includes('Files'); } catch { return false; }
    }

    document.addEventListener('dragenter', e => {
        if (!hasFiles(e.dataTransfer)) return;
        dragCount++;
        overlay.classList.add('active');
    });

    document.addEventListener('dragleave', e => {
        if (!hasFiles(e.dataTransfer)) return;
        dragCount--;
        if (dragCount <= 0) { dragCount = 0; overlay.classList.remove('active'); }
    });

    document.addEventListener('dragover', e => {
        if (hasFiles(e.dataTransfer)) e.preventDefault(); // allow drop
    });

    document.addEventListener('drop', e => {
        dragCount = 0;
        overlay.classList.remove('active');
        inputDz?.classList.remove('drag-over');
        if (!e.dataTransfer?.files?.length) return;
        e.preventDefault();
        handleImageFile(e.dataTransfer.files);
    });

    // Local visual feedback on the input box while hovering over it
    if (inputDz) {
        inputDz.addEventListener('dragover',  () => inputDz.classList.add('drag-over'));
        inputDz.addEventListener('dragleave', () => inputDz.classList.remove('drag-over'));
    }
})();

// ── Image lightbox ───────────────────────────────────────────────────
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imgLightbox').classList.add('show');
}

// ── Quick prompts ────────────────────────────────────────────────────
const QUICK_PROMPTS = {
    'Crop Management': [
        'কোন মাসে ধান রোপণ করা ভালো?',
        'আমার জমিতে সার দেওয়ার সঠিক পদ্ধতি কী?',
        'What is the best fertilizer schedule for rice?',
        'How to increase wheat yield per bigha?',
    ],
    'Pest & Disease': [
        'আমার ধান গাছে হলুদ পাতা দেখা যাচ্ছে, কী করব?',
        'How to control blast disease in rice?',
        'শাক-সবজিতে পোকামাকড় দমন করার প্রাকৃতিক উপায়?',
        'What pesticide is safe for vegetable crops?',
    ],
    'Weather & Soil': [
        'আজকের আবহাওয়া আমার ফসলের জন্য কেমন?',
        'How to improve clay soil for vegetable farming?',
        'বন্যার পরে কী ফসল চাষ করা উচিত?',
        'Best crops for dry season in Bangladesh?',
    ],
    'Market & Finance': [
        'আজকে বাজারে ধানের দাম কত?',
        'কৃষি ঋণ পাওয়ার সহজ উপায় কী?',
        'How to get the best price for vegetables?',
        'Which cash crop is most profitable this season?',
    ],
};

function toggleQuickPanel() {
    const panel = document.getElementById('quickPanel');
    panel.classList.toggle('show');
    if (panel.classList.contains('show') && !panel.dataset.built) {
        buildQuickPanel();
        panel.dataset.built = '1';
    }
}

function closeQuickPanel() {
    document.getElementById('quickPanel')?.classList.remove('show');
}

function buildQuickPanel() {
    const panel = document.getElementById('quickPanel');
    panel.innerHTML = '<div class="quick-panel-title">⚡ Quick Farming Prompts</div>';
    Object.entries(QUICK_PROMPTS).forEach(([cat, prompts]) => {
        const label = document.createElement('div');
        label.className = 'quick-cat-label';
        label.textContent = cat;
        panel.appendChild(label);
        prompts.forEach(p => {
            const chip = document.createElement('button');
            chip.className = 'quick-chip';
            chip.textContent = p;
            chip.onclick = () => {
                document.getElementById('msgInput').value = p;
                document.getElementById('msgInput').dispatchEvent(new Event('input'));
                document.getElementById('msgInput').focus();
                closeQuickPanel();
            };
            panel.appendChild(chip);
        });
    });
}

// Close quick panel on outside click
document.addEventListener('click', e => {
    const wrap = document.querySelector('.quick-panel-wrap');
    if (wrap && !wrap.contains(e.target)) closeQuickPanel();
});

// ── Conversation stats in topbar ─────────────────────────────────────
function updateStats() {
    const userMsgs = document.querySelectorAll('.msg-row.user').length;
    const aiMsgs   = document.querySelectorAll('.msg-row.assistant').length;
    let el = document.getElementById('chatStatsEl');
    if (!el) {
        el = document.createElement('span');
        el.id = 'chatStatsEl';
        el.className = 'chat-stats';
        const ta = document.querySelector('.topbar-actions');
        if (ta) ta.prepend(el);
    }
    if (userMsgs + aiMsgs > 0) el.textContent = `${userMsgs + aiMsgs} msgs`;
    else el.textContent = '';
}

// Patch appendAIMsg to call updateStats
const _origAppend = appendAIMsg;
// stats updated via MutationObserver below
const _statObs = new MutationObserver(updateStats);
document.addEventListener('DOMContentLoaded', () => {
    const mw = document.getElementById('messagesWrap');
    if (mw) _statObs.observe(mw, { childList: true });
    updateStats();
});

// ── Populate mic language selector from browser TTS voices ───────────
function populateVoiceLangs() {
    const sel = document.getElementById('micLang');
    if (!sel) return;
    const voices = window.speechSynthesis.getVoices();
    if (!voices.length) return;

    // Build map of best voice per locale (bn/en only)
    const map = {};
    voices.forEach(v => {
        const lc = (v.lang || '').toLowerCase();
        if (!lc.startsWith('bn') && !lc.startsWith('en')) return;
        if (!map[v.lang]) map[v.lang] = v;
    });

    const current = sel.value;

    // Sort: bn-BD first, other bn, en-US, other en
    let langs = Object.keys(map).sort((a, b) => {
        if (a === 'bn-BD') return -1; if (b === 'bn-BD') return 1;
        if (a.startsWith('bn') && !b.startsWith('bn')) return -1;
        if (b.startsWith('bn') && !a.startsWith('bn')) return 1;
        if (a === 'en-US') return -1; if (b === 'en-US') return 1;
        return a.localeCompare(b);
    });

    if (!langs.includes('bn-BD')) langs.unshift('bn-BD');
    if (!langs.some(l => l.startsWith('en'))) langs.push('en-US');

    sel.innerHTML = '';
    langs.forEach(lang => {
        const v = map[lang];
        const flag = lang.startsWith('bn') ? '🇧🇩' : '🇬🇧';
        const label = v ? `${flag} ${lang} — ${v.name}` : (lang.startsWith('bn') ? '🇧🇩 বাংলা' : '🇬🇧 English');
        const opt = new Option(label, lang);
        sel.appendChild(opt);
    });

    // Restore saved preference, else default to stored or bn-BD
    const stored = localStorage.getItem('voiceLang') || current || 'bn-BD';
    if ([...sel.options].some(o => o.value === stored)) sel.value = stored;

    sel.onchange = () => localStorage.setItem('voiceLang', sel.value);
}

// ── Font size control ────────────────────────────────────────────────
const FONT_SIZES = ['md', 'sm', 'lg'];
let currentFontSize = localStorage.getItem('chatFontSize') || 'md';

function toggleFontSize() {
    const idx = FONT_SIZES.indexOf(currentFontSize);
    currentFontSize = FONT_SIZES[(idx + 1) % FONT_SIZES.length];
    localStorage.setItem('chatFontSize', currentFontSize);
    applyFontSize();
}

function applyFontSize() {
    const wrap = document.getElementById('messagesWrap');
    if (wrap) {
        wrap.removeAttribute('data-fs');
        if (currentFontSize !== 'md') wrap.dataset.fs = currentFontSize;
    }
    const btn = document.getElementById('fontSizeBtn');
    if (btn) {
        const labels = { md: 'A', sm: 'A−', lg: 'A+' };
        btn.textContent = labels[currentFontSize] || 'A';
        btn.title = { md: 'Font: Medium (click for Small)', sm: 'Font: Small (click for Large)', lg: 'Font: Large (click for Medium)' }[currentFontSize];
    }
}

// ── Reading time badge ───────────────────────────────────────────────
function addReadTime(bubbleEl) {
    const text = bubbleEl.innerText || '';
    const words = text.trim().split(/\s+/).filter(Boolean).length;
    if (words < 80) return;
    const mins = Math.max(1, Math.round(words / 180));
    const row = bubbleEl.closest('.msg-row');
    if (!row) return;
    const meta = row.querySelector('.msg-meta');
    if (!meta || meta.querySelector('.read-time')) return;
    const badge = document.createElement('span');
    badge.className = 'read-time';
    badge.innerHTML = `<span class="material-icons">schedule</span>${mins} min read`;
    meta.appendChild(badge);
}

// ── Collapsible long messages ────────────────────────────────────────
const COLLAPSE_CHARS = 600;

function setupCollapse(bubbleEl) {
    const text = bubbleEl.innerText || '';
    if (text.length < COLLAPSE_CHARS) return;
    if (bubbleEl.classList.contains('collapsible')) return;
    bubbleEl.classList.add('collapsible', 'collapsed');
    const btn = document.createElement('button');
    btn.className = 'show-more-btn';
    btn.textContent = '▼ Show more';
    btn.onclick = () => {
        const isNowCollapsed = bubbleEl.classList.toggle('collapsed');
        btn.textContent = isNowCollapsed ? '▼ Show more' : '▲ Show less';
    };
    bubbleEl.insertAdjacentElement('afterend', btn);
}

// ── Bookmark / saved messages ────────────────────────────────────────
function toggleBookmark(btn) {
    const row = btn.closest('.msg-row');
    const bubble = row?.querySelector('.msg-bubble');
    if (!bubble) return;

    const msgId = row.dataset.msgId || ('bm_' + Math.random().toString(36).slice(2));
    if (!row.dataset.msgId) row.dataset.msgId = msgId;

    const text = bubble.innerText.trim().slice(0, 400);
    let bookmarks = JSON.parse(localStorage.getItem('chatBookmarks') || '[]');
    const existIdx = bookmarks.findIndex(b => b.id === msgId);

    if (existIdx >= 0) {
        bookmarks.splice(existIdx, 1);
        btn.classList.remove('bookmarked');
        btn.querySelector('.material-icons').textContent = 'bookmark_border';
        btn.title = 'Bookmark';
    } else {
        bookmarks.unshift({
            id: msgId,
            text,
            time: new Date().toLocaleString(),
            convId: currentCid,
            convTitle: currentTitle || 'Chat'
        });
        if (bookmarks.length > 60) bookmarks = bookmarks.slice(0, 60);
        btn.classList.add('bookmarked');
        btn.querySelector('.material-icons').textContent = 'bookmark';
        btn.title = 'Remove bookmark';
        showToast('Message saved to bookmarks', 'success');
    }
    localStorage.setItem('chatBookmarks', JSON.stringify(bookmarks));
}

function syncBookmarkButtons() {
    const bookmarks = JSON.parse(localStorage.getItem('chatBookmarks') || '[]');
    const ids = new Set(bookmarks.map(b => b.id));
    document.querySelectorAll('.msg-row[data-msg-id]').forEach(row => {
        const btn = row.querySelector('.bookmark-btn');
        if (!btn) return;
        if (ids.has(row.dataset.msgId)) {
            btn.classList.add('bookmarked');
            btn.querySelector('.material-icons').textContent = 'bookmark';
            btn.title = 'Remove bookmark';
        }
    });
}

function openBookmarks() {
    document.getElementById('bookmarksModal').classList.add('show');
    renderBookmarksList();
}

function closeBookmarksModal() {
    document.getElementById('bookmarksModal')?.classList.remove('show');
}

function renderBookmarksList() {
    const list = document.getElementById('bookmarksList');
    const bookmarks = JSON.parse(localStorage.getItem('chatBookmarks') || '[]');
    if (!bookmarks.length) {
        list.innerHTML = '<div class="bookmarks-empty">⭐ No saved messages yet.<br>Hover over any AI message and click the bookmark icon to save it here.</div>';
        return;
    }
    list.innerHTML = bookmarks.map((b, i) => `
        <div class="bookmark-item">
            <div class="bookmark-item-text">${escHtml(b.text)}${b.text.length >= 400 ? '…' : ''}</div>
            <div class="bookmark-item-meta">
                <span>${escHtml(b.convTitle || 'Chat')}</span>
                <button class="bookmark-copy-btn" onclick="copyBookmark(${i}, this)">
                    <span class="material-icons">content_copy</span> Copy
                </button>
                <span>${escHtml(b.time)}</span>
            </div>
            <button class="bookmark-remove-btn" onclick="removeBookmark(${i})" title="Remove">
                <span class="material-icons">close</span>
            </button>
        </div>`).join('');
}

function copyBookmark(idx, btn) {
    const bm = JSON.parse(localStorage.getItem('chatBookmarks') || '[]')[idx];
    if (!bm) return;
    navigator.clipboard.writeText(bm.text).then(() => {
        btn.innerHTML = '<span class="material-icons">check</span> Copied!';
        setTimeout(() => btn.innerHTML = '<span class="material-icons">content_copy</span> Copy', 1800);
    });
}

function removeBookmark(idx) {
    let bookmarks = JSON.parse(localStorage.getItem('chatBookmarks') || '[]');
    const removed = bookmarks.splice(idx, 1)[0];
    localStorage.setItem('chatBookmarks', JSON.stringify(bookmarks));
    renderBookmarksList();
    // Un-mark the button in the chat if message is still visible
    if (removed?.id) {
        const row = document.querySelector(`.msg-row[data-msg-id="${removed.id}"]`);
        const btn = row?.querySelector('.bookmark-btn');
        if (btn) {
            btn.classList.remove('bookmarked');
            btn.querySelector('.material-icons').textContent = 'bookmark_border';
            btn.title = 'Bookmark';
        }
    }
}

// ── Pin / Archive / Share ────────────────────────────────────────────
let currentIsPinned   = false;
let currentIsArchived = false;
let _archivedOpen     = false;

function _updatePinBtnLabel() {
    const btn = document.getElementById('pinBtn');
    const lbl = document.getElementById('pinBtnLabel');
    if (!btn || !lbl) return;
    lbl.textContent = currentIsPinned ? 'Unpin' : 'Pin';
    btn.title       = currentIsPinned ? 'Unpin conversation' : 'Pin conversation';
}

function _updateArchiveBtnLabel() {
    const btn = document.getElementById('archiveBtn');
    const lbl = document.getElementById('archiveBtnLabel');
    if (!btn || !lbl) return;
    lbl.textContent = currentIsArchived ? 'Unarchive' : 'Archive';
    btn.title       = currentIsArchived ? 'Unarchive conversation' : 'Archive conversation';
}

// Pin/unpin current conversation (topbar button)
async function pinCurrentChat() {
    if (!currentCid) return;
    try {
        const res  = await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'pin', conversation_id: currentCid})
        });
        const data = await res.json();
        if (data.success) {
            currentIsPinned = data.is_pinned === 1;
            _updatePinBtnLabel();
            loadConversationList(currentCid);
            showToast(data.is_pinned ? 'Conversation pinned' : 'Conversation unpinned', 'success');
        } else {
            showCustomAlert(data.message || 'Pin failed. Run migration_v4.sql first.');
        }
    } catch { showCustomAlert('Network error.'); }
}

// Archive/unarchive current conversation (topbar button)
async function archiveCurrentChat() {
    if (!currentCid) return;
    const doArchive = async () => {
        try {
            const res  = await fetch(CONV_API, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action: 'archive', conversation_id: currentCid})
            });
            const data = await res.json();
            if (data.success) {
                if (data.is_archived === 1) {
                    showToast('Conversation archived', 'success');
                    newChat();
                } else {
                    currentIsArchived = false;
                    currentIsPinned   = data.is_pinned === 1;
                    _updateArchiveBtnLabel();
                    _updatePinBtnLabel();
                    loadConversationList(currentCid);
                    showToast('Conversation unarchived', 'success');
                }
            } else {
                showCustomAlert(data.message || 'Archive failed. Run migration_v4.sql first.');
            }
        } catch { showCustomAlert('Network error.'); }
    };
    if (currentIsArchived) { doArchive(); return; }
    showConfirm(
        'Archive Conversation',
        'This conversation will be hidden from the main list. You can restore it anytime.',
        'archive', '#f59e0b', 'rgba(245,158,11,0.15)',
        'Archive', 'confirm-warning', doArchive
    );
}

// Pin/unpin via sidebar item (conv-actions button)
async function pinChatItem(cid, isPinned) {
    try {
        await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'pin', conversation_id: cid})
        });
        if (cid === currentCid) { currentIsPinned = !isPinned; _updatePinBtnLabel(); }
        loadConversationList(currentCid);
    } catch {}
}

// Archive or unarchive via sidebar item (conv-actions button)
async function archiveChatItem(cid, isCurrentlyArchived = false) {
    const doIt = async () => {
        try {
            const res  = await fetch(CONV_API, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action: 'archive', conversation_id: cid})
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.is_archived ? 'Conversation archived' : 'Conversation unarchived', 'success');
                if (data.is_archived && cid === currentCid) newChat();
                else loadConversationList(currentCid);
            }
        } catch {}
    };
    if (isCurrentlyArchived) { doIt(); return; }
    showConfirm(
        'Archive Conversation',
        'This conversation will be hidden from the main list. You can restore it anytime.',
        'archive', '#f59e0b', 'rgba(245,158,11,0.15)',
        'Archive', 'confirm-warning', doIt
    );
}

// Toggle archived section in sidebar
async function toggleArchivedSection() {
    _archivedOpen = !_archivedOpen;
    const list = document.getElementById('archivedList');
    if (!list) return;
    if (!_archivedOpen) {
        list.style.display = 'none';
        return;
    }
    list.style.display = 'block';
    list.innerHTML = `<div style="padding:10px 14px;color:#4b5563;font-size:12px;">Loading…</div>`;
    try {
        const res  = await fetch(CONV_API + '?action=list&archived=1');
        const data = await res.json();
        const items = data.conversations || [];
        const badge = document.getElementById('archivedCountBadge');
        if (badge) badge.textContent = items.length || '';
        if (!items.length) {
            list.innerHTML = `<div style="padding:10px 14px;color:#4b5563;font-size:12px;">No archived conversations.</div>`;
            return;
        }
        list.innerHTML = `<div class="conv-group-label">🗄️ Archived</div>` +
            items.map(c => {
                const title    = escHtml(c.title || 'Untitled');
                const safeTitle = title.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
                return `
                <a class="conv-item${c.conversation_id === currentCid ? ' active' : ''}" onclick="loadChat('${c.conversation_id}')">
                    <div class="conv-icon"><span class="material-icons" style="color:#6b7280;">archive</span></div>
                    <div class="conv-body">
                        <span class="conv-title">${title}</span>
                        <span class="conv-time">${relTime(c.updated_at)}</span>
                    </div>
                    <div class="conv-actions">
                        <button class="conv-btn" onclick="event.stopPropagation();archiveChatItem('${c.conversation_id}',true)" title="Unarchive">
                            <span class="material-icons">unarchive</span>
                        </button>
                        <button class="conv-btn del" onclick="event.stopPropagation();deleteChat('${c.conversation_id}',this)" title="Delete">
                            <span class="material-icons">delete_outline</span>
                        </button>
                    </div>
                </a>`;
            }).join('');
    } catch {
        list.innerHTML = `<div style="padding:10px 14px;color:#ef4444;font-size:12px;">Failed to load.</div>`;
    }
}

// ── Share modal ──────────────────────────────────────────────────────
function _buildSharePreview() {
    const rows = [...document.querySelectorAll('#messagesWrap .msg-row')].slice(0, 5);
    if (!rows.length) return '';
    return rows.map(row => {
        const isUser = row.classList.contains('user');
        const bubble = row.querySelector('.msg-bubble');
        if (!bubble) return '';
        const text = bubble.innerText.trim();
        const preview = text.length > 110 ? text.slice(0, 110) + '…' : text;
        return `<div class="share-prev-msg ${isUser ? 'user' : 'ai'}">
            <span class="share-prev-role">${isUser ? 'You' : 'Chashi Bhai'}</span>
            <span class="share-prev-text">${escHtml(preview)}</span>
        </div>`;
    }).join('');
}

async function openShareModal() {
    if (!currentCid) return;
    const modal = document.getElementById('shareModal');
    modal.classList.add('show');
    document.getElementById('shareModalBody').innerHTML      = '<div class="share-loading">Generating share link…</div>';
    document.getElementById('shareActionsRow').style.display = 'none';

    // Render conversation preview
    const previewHtml = _buildSharePreview();
    const previewEl   = document.getElementById('sharePreview');
    const previewMsgs = document.getElementById('sharePreviewMsgs');
    if (previewHtml) {
        previewMsgs.innerHTML    = previewHtml;
        previewEl.style.display  = 'block';
    } else {
        previewEl.style.display  = 'none';
    }

    try {
        const res  = await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'share_generate', conversation_id: currentCid})
        });
        const data = await res.json();
        if (data.success && data.token) {
            const shareUrl = BASE_URL + 'agent/share/' + data.token;
            document.getElementById('shareModalBody').innerHTML = `
                <div class="share-link-row">
                    <input type="text" class="share-link-input" id="shareLinkInput"
                           value="${escHtml(shareUrl)}" readonly onclick="this.select()">
                    <button class="share-copy-btn" id="shareCopyBtn" onclick="copyShareLink()">
                        <span class="material-icons">content_copy</span> Copy
                    </button>
                </div>`;
            document.getElementById('shareActionsRow').style.display = 'flex';
        } else {
            document.getElementById('shareModalBody').innerHTML =
                `<div class="share-loading">${escHtml(data.message || 'Failed to generate link.')}</div>`;
        }
    } catch {
        document.getElementById('shareModalBody').innerHTML =
            '<div class="share-loading">Network error. Please try again.</div>';
    }
}

function closeShareModal() {
    document.getElementById('shareModal')?.classList.remove('show');
    document.getElementById('sharePreview').style.display = 'none';
}

function copyShareLink() {
    const input = document.getElementById('shareLinkInput');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('shareCopyBtn');
        if (btn) {
            btn.innerHTML = '<span class="material-icons">check</span> Copied!';
            setTimeout(() => btn.innerHTML = '<span class="material-icons">content_copy</span> Copy', 2000);
        }
        showToast('Link copied to clipboard', 'success');
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        showToast('Link copied', 'success');
    });
}

async function revokeShareLink() {
    if (!confirm('Disable the share link? Anyone with the current link will lose access.')) return;
    try {
        await fetch(CONV_API, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'share_revoke', conversation_id: currentCid})
        });
        closeShareModal();
        showToast('Share link disabled', 'success');
    } catch { showCustomAlert('Network error.'); }
}

// ── Success toast (green variant) ────────────────────────────────────
function showToast(msg, type = '') {
    const el = document.getElementById('customAlert');
    const icon = el.querySelector('.material-icons');
    document.getElementById('customAlertMsg').textContent = msg;
    el.className = 'custom-alert show' + (type === 'success' ? ' success' : '');
    if (icon) icon.textContent = type === 'success' ? 'check_circle' : 'warning';
    clearTimeout(el._t);
    el._t = setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => { el.className = 'custom-alert'; if (icon) icon.textContent = 'warning'; }, 300);
    }, 2500);
}
</script>
</body>
</html>
