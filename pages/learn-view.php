<?php
/**
 * Learning Center — Content Viewer
 */
if (!isLoggedIn()) { redirect('login'); }

// Inline language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    $_SESSION['language'] = $_GET['lang'];
    setcookie('language', $_GET['lang'], time() + (86400 * 30), '/');
}

$currentUser = getCurrentUser();
$contentId   = (int)($_GET['id'] ?? 0);
if (!$contentId) { redirect('learn'); }

$db  = new Database();
$uid = $_SESSION['user_id'];

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
    [$uid, $uid, $uid, $contentId, $uid]
);
if (!$item) { redirect('learn'); }

$db->query("UPDATE learn_content SET views = views + 1 WHERE id = ?")->bind(1,$contentId)->execute();
$db->query("INSERT INTO learn_progress (user_id,content_id) VALUES (?,?) ON DUPLICATE KEY UPDATE last_accessed=NOW()")
   ->bind(1,$uid)->bind(2,$contentId)->execute();

$playlistItems = [];
if (in_array($item['type'], ['video','playlist'])) {
    $playlistItems = $db->resultSet(
        "SELECT * FROM learn_playlist_items WHERE content_id = ? ORDER BY sort_order ASC", [$contentId]
    );
}

$quizQuestions = [];
$bestAttempt   = null;
if ($item['type'] === 'quiz') {
    $qs = $db->resultSet("SELECT * FROM learn_quiz_questions WHERE content_id = ? ORDER BY sort_order ASC", [$contentId]);
    foreach ($qs as &$q) {
        $q['options'] = $db->resultSet("SELECT id, option_text FROM learn_quiz_options WHERE question_id = ? ORDER BY id ASC", [$q['id']]);
    }
    unset($q);
    $quizQuestions = $qs;
    $bestAttempt   = $db->single(
        "SELECT score, total, passed FROM learn_quiz_attempts WHERE user_id = ? AND content_id = ? ORDER BY score DESC LIMIT 1",
        [$uid, $contentId]
    );
}

$related = $db->resultSet(
    "SELECT id, title, type, thumbnail_url, duration_min FROM learn_content
     WHERE is_published = 1 AND id != ? AND (category_id = ? OR season = ?)
     ORDER BY is_featured DESC, views DESC LIMIT 5",
    [$contentId, $item['category_id'] ?? 0, $item['season']]
);

function extractYouTubeId(string $url): string {
    preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $url, $m);
    return $m[1] ?? '';
}

$TYPE_META = [
    'video'   => ['icon'=>'play_circle',   'color'=>'#e53935','label'=>'Video',   'emoji'=>'🎬'],
    'playlist'=> ['icon'=>'playlist_play', 'color'=>'#e53935','label'=>'Playlist','emoji'=>'📺'],
    'blog'    => ['icon'=>'article',       'color'=>'#1976d2','label'=>'Blog',    'emoji'=>'📝'],
    'guide'   => ['icon'=>'calendar_month','color'=>'#0097a7','label'=>'Guide',   'emoji'=>'📖'],
    'article' => ['icon'=>'auto_stories',  'color'=>'#7b1fa2','label'=>'Article', 'emoji'=>'📚'],
    'webinar' => ['icon'=>'live_tv',       'color'=>'#f57c00','label'=>'Webinar', 'emoji'=>'🎙️'],
    'quiz'    => ['icon'=>'quiz',          'color'=>'#388e3c','label'=>'Quiz',    'emoji'=>'📋'],
];
$tm       = $TYPE_META[$item['type']] ?? ['icon'=>'article','color'=>'#557A46','label'=>'Content','emoji'=>'📄'];
$initials = strtoupper(substr($item['first_name']??'?',0,1).substr($item['last_name']??'?',0,1));
$diffMap  = ['beginner'=>['Beginner','#92400e','#fef3c7'],'intermediate'=>['Intermediate','#1d4ed8','#dbeafe'],'advanced'=>['Advanced','#6d28d9','#ede9fe']];
$dl       = $diffMap[$item['difficulty']] ?? null;
$isReadable = in_array($item['type'], ['blog','guide','article']);

include __DIR__ . '/../layouts/header.php';
?>

<style>
/* ── Reading Progress Bar ─────────────────────────────────────────────── */
#readProgress {
    position: fixed; top: 0; left: 0; height: 3px; width: 0;
    background: linear-gradient(90deg, #557A46, #86efac);
    z-index: 9999; transition: width .1s linear;
    display: none;
}

/* ── Page Layout ──────────────────────────────────────────────────────── */
.lv-wrap {
    max-width: 1200px; margin: 0 auto;
    padding: 0 20px 80px; display: flex; gap: 32px;
    align-items: flex-start;
}
.lv-main  { flex: 1; min-width: 0; }
.lv-side  { width: 310px; flex-shrink: 0; position: sticky; top: 80px; }
@media(max-width:1024px){ .lv-side { width: 270px; } }
@media(max-width:860px) { .lv-side { display: none; } }

/* ── Hero Banner ──────────────────────────────────────────────────────── */
.lv-hero {
    position: relative; width: 100%; border-radius: 20px; overflow: hidden;
    background: #111827; margin-bottom: 28px;
    box-shadow: 0 8px 40px rgba(0,0,0,.15);
}
.lv-hero-bg {
    position: absolute; inset: 0; background-size: cover; background-position: center;
    filter: blur(2px) brightness(.45); transform: scale(1.05);
}
.lv-hero-solid {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(85,122,70,.85) 0%, rgba(17,24,39,.9) 100%);
}
.lv-hero-content {
    position: relative; padding: 36px 36px 32px;
}
@media(max-width:640px){ .lv-hero-content { padding: 24px 20px; } }

/* Back row */
.lv-back {
    display: flex; align-items: center; gap: 6px; margin-bottom: 20px;
    font-size: 13px; font-weight: 500; color: rgba(255,255,255,.75);
    text-decoration: none; width: fit-content;
    transition: color .2s;
}
.lv-back:hover { color: #fff; }
.lv-back .material-icons { font-size: 18px; }

/* Chips row */
.lv-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.chip-type {
    display: flex; align-items: center; gap: 5px;
    padding: 5px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
    color: #fff; letter-spacing: .03em;
    background: rgba(255,255,255,.15); backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,.25);
}
.chip-type .material-icons { font-size: 14px; }
.chip-diff {
    padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 700;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,.2);
}
.chip-season {
    padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 700;
    background: rgba(134,239,172,.2); color: #86efac; border: 1px solid rgba(134,239,172,.3);
}
.chip-cat {
    padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 600;
    background: rgba(255,255,255,.12); color: rgba(255,255,255,.8);
    border: 1px solid rgba(255,255,255,.15);
}

/* Title */
.lv-title {
    font-size: clamp(20px, 4vw, 30px); font-weight: 800; color: #fff;
    line-height: 1.25; margin-bottom: 8px;
}
.lv-title-bn { font-size: 17px; color: rgba(255,255,255,.7); font-weight: 500; margin-bottom: 12px; }
.lv-desc-hero { font-size: 14px; color: rgba(255,255,255,.65); line-height: 1.7; margin-bottom: 20px; max-width: 680px; }

/* Stats row */
.lv-stats {
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    font-size: 12px; color: rgba(255,255,255,.65); margin-bottom: 20px;
}
.lv-stat { display: flex; align-items: center; gap: 5px; }
.lv-stat .material-icons { font-size: 15px; }

/* Author in hero */
.lv-author-hero {
    display: flex; align-items: center; gap: 10px;
}
.lv-author-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff;
    flex-shrink: 0; overflow: hidden;
}
.lv-author-avatar img { width: 100%; height: 100%; object-fit: cover; }
.lv-author-name { font-size: 13px; font-weight: 600; color: #fff; }
.lv-author-role { font-size: 11px; color: rgba(255,255,255,.55); margin-top: 1px; }

/* ── Actions Bar ──────────────────────────────────────────────────────── */
.lv-actions {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 28px;
}
.lv-btn {
    display: flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .2s; border: 1.5px solid #e5e7eb;
    background: #fff; color: #374151; text-decoration: none;
    white-space: nowrap;
}
.lv-btn:hover { border-color: #557A46; color: #557A46; background: #f0fdf4; }
.lv-btn .material-icons { font-size: 17px; }
.lv-btn.liked { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
.lv-btn.success { border-color: #059669; color: #059669; background: #f0fdf4; cursor: default; }
.lv-btn.primary { background: #557A46; color: #fff; border-color: #557A46; }
.lv-btn.primary:hover { background: #3d5c32; border-color: #3d5c32; }
.lv-btn.gold { border-color: #f59e0b; color: #92400e; background: #fffbeb; cursor: default; }

/* ── Video ────────────────────────────────────────────────────────────── */
.lv-video-frame {
    width: 100%; aspect-ratio: 16/9; border-radius: 16px; overflow: hidden;
    background: #000; margin-bottom: 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,.18);
}
.lv-video-frame iframe { width: 100%; height: 100%; border: none; display: block; }

/* ── Playlist ──────────────────────────────────────────────────────────── */
.playlist-wrap { display: flex; gap: 16px; margin-bottom: 24px; }
.playlist-main { flex: 1; min-width: 0; }
.playlist-rail {
    width: 270px; flex-shrink: 0; background: #fff;
    border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden;
    max-height: 440px; display: flex; flex-direction: column;
}
@media(max-width:700px){ .playlist-wrap{flex-direction:column;} .playlist-rail{width:100%;max-height:230px;} }
.playlist-rail-head {
    padding: 12px 16px; font-weight: 700; font-size: 13px; color: #111827;
    border-bottom: 1px solid #f3f4f6; background: #f9fafb;
    display: flex; align-items: center; gap: 6px; flex-shrink: 0;
}
.playlist-rail-body { overflow-y: auto; flex: 1; }
.playlist-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
    transition: background .15s; position: relative;
}
.playlist-item:hover { background: #f0fdf4; }
.playlist-item.active { background: #ecfdf5; }
.playlist-item.active::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:#557A46; border-radius:0 2px 2px 0; }
.playlist-num { font-size: 11px; color: #9ca3af; width: 18px; text-align: center; flex-shrink: 0; }
.playlist-title { font-size: 12px; font-weight: 500; color: #374151; line-height: 1.4; flex: 1; }
.playlist-dur { font-size: 11px; color: #9ca3af; flex-shrink: 0; }

/* ── Article Body ─────────────────────────────────────────────────────── */
.article-wrap { position: relative; }
.article-body {
    font-size: 16px; line-height: 2; color: #374151;
    background: #fff; border-radius: 16px; padding: 32px 36px;
    border: 1px solid #e5e7eb; margin-bottom: 28px;
    box-shadow: 0 2px 12px rgba(0,0,0,.04);
}
@media(max-width:640px){ .article-body { padding: 20px 18px; } }
.article-body h2 { font-size: 22px; font-weight: 800; color: #111827; margin: 32px 0 12px; padding-bottom: 8px; border-bottom: 2px solid #f0fdf4; }
.article-body h3 { font-size: 18px; font-weight: 700; color: #1f2937; margin: 24px 0 10px; }
.article-body p  { margin-bottom: 16px; }
.article-body ul, .article-body ol { padding-left: 28px; margin-bottom: 16px; }
.article-body li { margin-bottom: 8px; }
.article-body strong { color: #111827; font-weight: 700; }
.article-body a { color: #557A46; text-decoration: underline; }
.article-body blockquote {
    border-left: 4px solid #557A46; padding: 14px 20px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 0 12px 12px 0;
    margin: 20px 0; color: #065f46; font-style: italic; font-size: 15px;
}
.article-body img { max-width: 100%; border-radius: 12px; margin: 16px 0; box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.article-body code { background: #f3f4f6; padding: 3px 7px; border-radius: 5px; font-family: monospace; font-size: 14px; color: #7c3aed; }
.article-body pre { background: #1e293b; color: #e2e8f0; padding: 20px; border-radius: 12px; overflow-x: auto; margin: 16px 0; }
.article-body pre code { background: none; color: inherit; padding: 0; }
.article-body table { width: 100%; border-collapse: collapse; margin: 16px 0; }
.article-body th { background: #f0fdf4; padding: 10px 14px; text-align: left; font-weight: 700; color: #065f46; border: 1px solid #bbf7d0; }
.article-body td { padding: 10px 14px; border: 1px solid #e5e7eb; }
.article-body tr:hover td { background: #f9fafb; }

/* Table of Contents */
.toc-box {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #bbf7d0; border-radius: 14px;
    padding: 20px 24px; margin-bottom: 28px;
}
.toc-box h4 { font-size: 13px; font-weight: 700; color: #065f46; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.toc-box h4 .material-icons { font-size: 17px; }
.toc-list { list-style: none; padding: 0; margin: 0; }
.toc-list li { padding: 4px 0; }
.toc-list a { font-size: 13px; color: #047857; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: color .15s; }
.toc-list a::before { content: '•'; color: #86efac; font-size: 16px; }
.toc-list a:hover { color: #065f46; }

/* ── Webinar ──────────────────────────────────────────────────────────── */
.webinar-card {
    background: linear-gradient(135deg, #fff7ed, #fef3c7);
    border: 1.5px solid #fed7aa; border-radius: 16px; padding: 28px;
    margin-bottom: 28px; position: relative; overflow: hidden;
}
.webinar-card::before {
    content: '🎙️'; position: absolute; right: 24px; top: 20px;
    font-size: 64px; opacity: .12;
}
.webinar-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 100px; background: #f57c00; color: #fff; font-size: 12px; font-weight: 700; margin-bottom: 16px; }
.webinar-date-txt { font-size: 22px; font-weight: 800; color: #c2410c; margin-bottom: 8px; }
.webinar-join-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; background: #f57c00; color: #fff; border-radius: 10px;
    text-decoration: none; font-weight: 700; margin-top: 16px; transition: background .2s;
}
.webinar-join-btn:hover { background: #e65100; }

/* ── Quiz ──────────────────────────────────────────────────────────────── */
.quiz-wrap {
    background: #fff; border-radius: 16px; border: 1px solid #e5e7eb;
    overflow: hidden; margin-bottom: 28px;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
}
.quiz-header {
    padding: 24px 28px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-bottom: 1px solid #bbf7d0;
}
.quiz-header h3 { font-size: 18px; font-weight: 800; color: #065f46; margin-bottom: 6px; }
.quiz-header p { font-size: 13px; color: #047857; }
.quiz-best { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 100px; background: rgba(5,150,105,.12); color: #065f46; font-size: 12px; font-weight: 700; margin-top: 10px; }
.quiz-prog-track { background: rgba(0,0,0,.1); border-radius: 4px; height: 5px; margin-top: 16px; }
.quiz-prog-fill  { height: 5px; border-radius: 4px; background: #557A46; transition: width .35s ease; }

.quiz-body { padding: 28px; }
.q-num { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
.q-text { font-size: 17px; font-weight: 700; color: #111827; margin-bottom: 20px; line-height: 1.5; }
.q-opts { display: flex; flex-direction: column; gap: 10px; }
.q-opt {
    display: flex; align-items: center; gap: 14px; padding: 14px 18px;
    border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer;
    transition: all .2s; position: relative;
}
.q-opt:hover { border-color: #86efac; background: #f0fdf4; }
.q-opt.selected { border-color: #557A46; background: #f0fdf4; }
.q-opt.correct  { border-color: #059669; background: #ecfdf5; }
.q-opt.wrong    { border-color: #ef4444; background: #fef2f2; }
.q-opt input[type=radio] { accent-color: #557A46; width: 18px; height: 18px; flex-shrink: 0; }
.q-opt label { flex: 1; cursor: pointer; font-size: 14px; color: #374151; font-weight: 500; }
.q-expl { margin-top: 14px; padding: 12px 16px; background: #fefce8; border-radius: 10px; font-size: 13px; color: #713f12; border-left: 3px solid #f59e0b; }

.quiz-nav { display: flex; justify-content: space-between; align-items: center; padding: 18px 28px; border-top: 1px solid #f3f4f6; background: #fafafa; }
.quiz-nav-btn { padding: 10px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; border: 2px solid; transition: all .2s; }
.quiz-nav-btn.prev { border-color: #e5e7eb; background: #fff; color: #374151; }
.quiz-nav-btn.prev:hover:not(:disabled) { border-color: #9ca3af; }
.quiz-nav-btn.next { border-color: #557A46; background: #557A46; color: #fff; }
.quiz-nav-btn.next:hover:not(:disabled) { background: #3d5c32; }
.quiz-nav-btn:disabled { opacity: .4; cursor: not-allowed; }
.quiz-q-counter { font-size: 12px; color: #9ca3af; font-weight: 600; }

/* Quiz result */
.quiz-result { padding: 40px 28px; text-align: center; }
.result-ring {
    width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 42px; font-weight: 900; border: 6px solid;
}
.result-ring.pass { border-color: #059669; color: #059669; background: #ecfdf5; }
.result-ring.fail { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
.result-label { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 6px; }
.result-sub { font-size: 14px; color: #6b7280; margin-bottom: 28px; }
.cert-award {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1.5px solid #fbbf24; border-radius: 16px; padding: 24px; margin-bottom: 24px; text-align: center;
}
.cert-award h4 { color: #92400e; font-size: 17px; font-weight: 800; margin-bottom: 6px; }
.cert-award .cert-code { font-family: monospace; font-size: 20px; font-weight: 800; color: #78350f; letter-spacing: .12em; }
.cert-award p { font-size: 12px; color: #92400e; margin-top: 6px; }

/* ── Related ──────────────────────────────────────────────────────────── */
.related-section { margin-top: 36px; }
.section-hd {
    font-size: 17px; font-weight: 800; color: #111827; margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
    padding-bottom: 12px; border-bottom: 2px solid #f0fdf4;
}
.section-hd .material-icons { color: #557A46; }
.related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
.rel-card {
    border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden;
    cursor: pointer; background: #fff; transition: all .22s;
    text-decoration: none; display: block;
}
.rel-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); border-color: #d1fae5; }
.rel-card-thumb {
    aspect-ratio: 16/9; background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 36px; overflow: hidden; position: relative;
}
.rel-card-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.rel-card:hover .rel-card-thumb img { transform: scale(1.05); }
.rel-card-type-badge {
    position: absolute; bottom: 6px; left: 6px;
    padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;
    color: #fff; backdrop-filter: blur(4px);
}
.rel-card-body { padding: 12px 14px; }
.rel-card-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.4; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rel-card-meta  { font-size: 11px; color: #9ca3af; display: flex; align-items: center; gap: 4px; }
.rel-card-meta .material-icons { font-size: 13px; }

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.lv-side-card {
    background: #fff; border-radius: 16px; border: 1px solid #e5e7eb;
    overflow: hidden; margin-bottom: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.lv-side-head {
    padding: 14px 18px; border-bottom: 1px solid #f3f4f6;
    font-weight: 700; font-size: 13px; color: #111827;
    display: flex; align-items: center; gap: 8px;
    background: #fafafa;
}
.lv-side-head .material-icons { font-size: 18px; color: #557A46; }
.lv-side-body { padding: 16px 18px; }
.lv-meta-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid #f9fafb; font-size: 13px;
}
.lv-meta-row:last-child { border-bottom: none; }
.lv-meta-label { color: #6b7280; }
.lv-meta-val   { font-weight: 700; color: #111827; }
.crop-tag {
    display: inline-block; padding: 4px 12px; background: #f0fdf4;
    color: #065f46; border-radius: 100px; font-size: 11px; font-weight: 700; margin: 3px;
    border: 1px solid #bbf7d0;
}
.cert-side-code { font-family: monospace; font-size: 16px; font-weight: 800; color: #78350f; letter-spacing: .1em; margin: 8px 0; }

/* ── Complete badge ───────────────────────────────────────────────────── */
.complete-banner {
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 1.5px solid #6ee7b7; border-radius: 14px;
    padding: 14px 18px; margin-bottom: 20px;
    font-size: 14px; font-weight: 600; color: #065f46;
}
.complete-banner .material-icons { font-size: 22px; color: #059669; }
</style>

<div id="readProgress"></div>

<?php
$TYPE_META = [
    'video'   => ['icon'=>'play_circle',   'color'=>'#e53935','label'=>'Video',   'emoji'=>'🎬'],
    'playlist'=> ['icon'=>'playlist_play', 'color'=>'#e53935','label'=>'Playlist','emoji'=>'📺'],
    'blog'    => ['icon'=>'article',       'color'=>'#1976d2','label'=>'Blog',    'emoji'=>'📝'],
    'guide'   => ['icon'=>'calendar_month','color'=>'#0097a7','label'=>'Guide',   'emoji'=>'📖'],
    'article' => ['icon'=>'auto_stories',  'color'=>'#7b1fa2','label'=>'Article', 'emoji'=>'📚'],
    'webinar' => ['icon'=>'live_tv',       'color'=>'#f57c00','label'=>'Webinar', 'emoji'=>'🎙️'],
    'quiz'    => ['icon'=>'quiz',          'color'=>'#388e3c','label'=>'Quiz',    'emoji'=>'📋'],
];
$tm = $TYPE_META[$item['type']] ?? ['icon'=>'article','color'=>'#557A46','label'=>'Content','emoji'=>'📄'];
$relEmojis = ['video'=>'🎬','playlist'=>'📺','blog'=>'📝','guide'=>'📖','article'=>'📚','webinar'=>'🎙️','quiz'=>'📋'];
?>

<div class="lv-wrap">
<div class="lv-main">

    <!-- ══ HERO ════════════════════════════════════════════════════════ -->
    <div class="lv-hero">
        <?php if ($item['thumbnail_url']): ?>
        <div class="lv-hero-bg" style="background-image:url('<?php echo htmlspecialchars($item['thumbnail_url']); ?>')"></div>
        <?php endif; ?>
        <div class="lv-hero-solid"></div>
        <div class="lv-hero-content">
            <a href="<?php echo $base_url; ?>?page=learn" class="lv-back">
                <span class="material-icons">arrow_back_ios</span> Learning Center
            </a>

            <div class="lv-chips">
                <span class="chip-type" style="background:<?php echo $tm['color']; ?>20;border-color:<?php echo $tm['color']; ?>60;">
                    <span class="material-icons" style="color:<?php echo $tm['color']; ?>"><?php echo $tm['icon']; ?></span>
                    <span style="color:#fff;"><?php echo $tm['label']; ?></span>
                </span>
                <?php if ($dl): ?>
                <span class="chip-diff" style="color:<?php echo $dl[1]; ?>;background:<?php echo $dl[2]; ?>30;"><?php echo $dl[0]; ?></span>
                <?php endif; ?>
                <?php if ($item['season'] && $item['season'] !== 'all'): ?>
                <span class="chip-season"><?php echo ucfirst($item['season']); ?> Season</span>
                <?php endif; ?>
                <?php if ($item['cat_name']): ?>
                <span class="chip-cat"><?php echo htmlspecialchars($item['cat_name']); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="lv-title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <?php if ($item['title_bn']): ?>
            <div class="lv-title-bn"><?php echo htmlspecialchars($item['title_bn']); ?></div>
            <?php endif; ?>
            <?php if ($item['description']): ?>
            <p class="lv-desc-hero"><?php echo htmlspecialchars($item['description']); ?></p>
            <?php endif; ?>

            <div class="lv-stats">
                <span class="lv-stat"><span class="material-icons">visibility</span><?php echo number_format($item['views']); ?> views</span>
                <span class="lv-stat"><span class="material-icons">favorite</span><span id="heroLikeCount"><?php echo $item['like_count']; ?></span> likes</span>
                <?php if ($item['duration_min']): ?>
                <span class="lv-stat"><span class="material-icons">schedule</span><?php echo $item['duration_min']; ?> min</span>
                <?php endif; ?>
                <?php if ($item['type'] === 'quiz'): ?>
                <span class="lv-stat"><span class="material-icons">quiz</span><?php echo count($quizQuestions); ?> questions</span>
                <?php endif; ?>
            </div>

            <div class="lv-author-hero">
                <div class="lv-author-avatar">
                    <?php if (!empty($item['profile_img_url'])): ?>
                    <img src="<?php echo htmlspecialchars($item['profile_img_url']); ?>" alt="">
                    <?php else: echo $initials; endif; ?>
                </div>
                <div>
                    <div class="lv-author-name"><?php echo htmlspecialchars(trim(($item['first_name']??'').' '.($item['last_name']??''))); ?></div>
                    <div class="lv-author-role"><?php echo __('agriculture_officer'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ ACTIONS ══════════════════════════════════════════════════════ -->
    <?php if ($item['user_completed']): ?>
    <div class="complete-banner">
        <span class="material-icons">check_circle</span>
        <?php echo get_language() === 'bn' ? 'আপনি এই কন্টেন্ট সম্পন্ন করেছেন — অভিনন্দন!' : 'You completed this content — great job!'; ?>
    </div>
    <?php endif; ?>

    <div class="lv-actions">
        <button class="lv-btn <?php echo $item['user_liked'] ? 'liked' : ''; ?>" id="likeBtn" onclick="toggleLike()">
            <span class="material-icons"><?php echo $item['user_liked'] ? 'favorite' : 'favorite_border'; ?></span>
            <span id="likeCount"><?php echo $item['like_count']; ?></span> <?php echo __('likes'); ?>
        </button>

        <?php if (!$item['user_completed'] && $item['type'] !== 'quiz'): ?>
        <button class="lv-btn primary" onclick="markComplete()">
            <span class="material-icons">task_alt</span> <?php echo get_language() === 'bn' ? 'সম্পন্ন হিসেবে চিহ্নিত করুন' : 'Mark Complete'; ?>
        </button>
        <?php endif; ?>

        <?php if ($item['cert_code']): ?>
        <span class="lv-btn gold">
            <span class="material-icons">emoji_events</span> <?php echo get_language() === 'bn' ? 'সার্টিফিকেট অর্জিত' : 'Certificate Earned'; ?>
        </span>
        <?php endif; ?>

        <button class="lv-btn" onclick="shareContent()" title="<?php echo __('share'); ?>">
            <span class="material-icons">share</span> <?php echo __('share'); ?>
        </button>
    </div>

    <!-- ══ VIDEO ══════════════════════════════════════════════════════ -->
    <?php if ($item['type'] === 'video' && $item['youtube_url']): ?>
        <?php $ytId = extractYouTubeId($item['youtube_url']); ?>
        <?php if ($ytId): ?>
        <div class="lv-video-frame">
            <iframe src="https://www.youtube.com/embed/<?php echo $ytId; ?>?rel=0" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <div style="padding:20px;background:#fef2f2;border-radius:12px;color:#dc2626;margin-bottom:20px;">Invalid YouTube URL.</div>
        <?php endif; ?>
        <?php if ($item['content_body']): ?>
        <div class="article-body"><?php echo $item['content_body']; ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ══ PLAYLIST ═══════════════════════════════════════════════════ -->
    <?php if ($item['type'] === 'playlist' && !empty($playlistItems)): ?>
    <div class="playlist-wrap">
        <div class="playlist-main">
            <div class="lv-video-frame">
                <iframe id="playlistFrame"
                    src="https://www.youtube.com/embed/<?php echo extractYouTubeId($playlistItems[0]['youtube_url']??''); ?>?rel=0"
                    allowfullscreen loading="lazy"></iframe>
            </div>
            <div style="font-size:14px;font-weight:700;color:#111827;margin-top:-8px;margin-bottom:8px;" id="playingTitle"><?php echo htmlspecialchars($playlistItems[0]['title']??''); ?></div>
        </div>
        <div class="playlist-rail">
            <div class="playlist-rail-head">
                <span class="material-icons" style="font-size:17px;color:#557A46;">playlist_play</span>
                Playlist · <?php echo count($playlistItems); ?> videos
            </div>
            <div class="playlist-rail-body">
            <?php foreach ($playlistItems as $i => $vi): ?>
            <?php $ytId = extractYouTubeId($vi['youtube_url']); ?>
            <div class="playlist-item <?php echo $i===0?'active':''; ?>" id="pi-<?php echo $i; ?>"
                 onclick="playVideo(<?php echo $i; ?>,'<?php echo $ytId; ?>','<?php echo htmlspecialchars(addslashes($vi['title'])); ?>')">
                <div class="playlist-num"><?php echo $i+1; ?></div>
                <div class="playlist-title"><?php echo htmlspecialchars($vi['title']); ?></div>
                <?php if ($vi['duration_min']): ?>
                <div class="playlist-dur"><?php echo $vi['duration_min']; ?>m</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
    function playVideo(i, ytId, title) {
        document.getElementById('playlistFrame').src = 'https://www.youtube.com/embed/' + ytId + '?rel=0&autoplay=1';
        document.getElementById('playingTitle').textContent = title;
        document.querySelectorAll('.playlist-item').forEach(el => el.classList.remove('active'));
        document.getElementById('pi-' + i)?.classList.add('active');
    }
    </script>
    <?php if ($item['content_body']): ?>
    <div class="article-body"><?php echo $item['content_body']; ?></div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ══ BLOG / GUIDE / ARTICLE ════════════════════════════════════ -->
    <?php if ($isReadable && $item['content_body']): ?>
    <div id="tocContainer"></div>
    <div class="article-body" id="articleBody"><?php echo $item['content_body']; ?></div>
    <?php endif; ?>

    <!-- ══ WEBINAR ════════════════════════════════════════════════════ -->
    <?php if ($item['type'] === 'webinar'): ?>
    <div class="webinar-card">
        <div class="webinar-badge"><span class="material-icons" style="font-size:14px;">live_tv</span> LIVE WEBINAR</div>
        <?php if ($item['webinar_scheduled_at']): ?>
        <div class="webinar-date-txt">📅 <?php echo date('F j, Y — g:i A', strtotime($item['webinar_scheduled_at'])); ?></div>
        <?php $isPast = strtotime($item['webinar_scheduled_at']) < time(); ?>
        <div style="font-size:14px;color:<?php echo $isPast?'#9ca3af':'#c2410c'; ?>;font-weight:600;">
            <?php echo $isPast ? 'This webinar has ended.' : '⏰ Upcoming — register to attend'; ?>
        </div>
        <?php endif; ?>
        <?php if ($item['description']): ?>
        <p style="margin-top:14px;font-size:14px;color:#374151;line-height:1.8;"><?php echo htmlspecialchars($item['description']); ?></p>
        <?php endif; ?>
        <?php if ($item['webinar_url']): ?>
        <a href="<?php echo htmlspecialchars($item['webinar_url']); ?>" target="_blank" class="webinar-join-btn">
            <span class="material-icons">live_tv</span>
            <?php echo (isset($isPast) && $isPast) ? 'Watch Recording' : 'Join Webinar'; ?>
        </a>
        <?php endif; ?>
        <?php if ($item['content_body']): ?>
        <div style="margin-top:20px;font-size:14px;color:#374151;line-height:1.8;"><?php echo $item['content_body']; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ QUIZ ═══════════════════════════════════════════════════════ -->
    <?php if ($item['type'] === 'quiz'): ?>
    <div class="quiz-wrap" id="quizWrap">
        <div class="quiz-header">
            <h3>📋 <?php echo htmlspecialchars($item['title']); ?></h3>
            <p>Pass score: <?php echo $item['pass_score']; ?>% · <?php echo count($quizQuestions); ?> questions</p>
            <?php if (!empty($bestAttempt)): ?>
            <div class="quiz-best">
                <span class="material-icons" style="font-size:15px;">emoji_events</span>
                Best: <?php echo $bestAttempt['score']; ?>%
                <?php echo $bestAttempt['passed'] ? ' · Passed ✅' : ''; ?>
            </div>
            <?php endif; ?>
            <div class="quiz-prog-track"><div class="quiz-prog-fill" id="qProgress" style="width:0%"></div></div>
        </div>

        <div id="quizQuestions">
        <?php foreach ($quizQuestions as $qi => $q): ?>
        <div class="quiz-body" id="qs-<?php echo $qi; ?>" style="display:<?php echo $qi===0?'block':'none'; ?>">
            <div class="q-num">Question <?php echo $qi+1; ?> of <?php echo count($quizQuestions); ?></div>
            <div class="q-text"><?php echo htmlspecialchars($q['question']); ?></div>
            <div class="q-opts">
            <?php foreach ($q['options'] as $opt): ?>
            <label class="q-opt" id="opt-<?php echo $opt['id']; ?>">
                <input type="radio" name="q-<?php echo $q['id']; ?>" value="<?php echo $opt['id']; ?>"
                       onchange="selectOption(<?php echo $q['id']; ?>,<?php echo $opt['id']; ?>)">
                <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
            </label>
            <?php endforeach; ?>
            </div>
            <?php if ($q['explanation']): ?>
            <div class="q-expl" id="expl-<?php echo $qi; ?>" style="display:none">
                💡 <?php echo htmlspecialchars($q['explanation']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="quiz-nav">
            <button class="quiz-nav-btn prev" id="prevBtn" onclick="prevQ()" disabled>← Previous</button>
            <span class="quiz-q-counter" id="qCounter">1 / <?php echo count($quizQuestions); ?></span>
            <button class="quiz-nav-btn next" id="nextBtn" onclick="nextQ()">Next →</button>
        </div>

        <div id="quizResult" style="display:none"></div>
    </div>

    <script>
    const QUIZ_QUESTIONS = <?php echo json_encode(array_map(fn($q) => ['id'=>$q['id']], $quizQuestions)); ?>;
    const QUIZ_TOTAL = QUIZ_QUESTIONS.length;
    const CONTENT_ID = <?php echo $contentId; ?>;
    let curQ = 0, answers = {};

    function updateProgress() {
        const pct = QUIZ_TOTAL > 0 ? Math.round((curQ+1)/QUIZ_TOTAL*100) : 0;
        document.getElementById('qProgress').style.width = pct + '%';
        document.getElementById('qCounter').textContent = (curQ+1) + ' / ' + QUIZ_TOTAL;
        document.getElementById('prevBtn').disabled = curQ === 0;
        document.getElementById('nextBtn').textContent = curQ === QUIZ_TOTAL-1 ? 'Submit Quiz 🚀' : 'Next →';
    }

    function selectOption(qid, optId) {
        answers[qid] = optId;
        document.querySelectorAll(`[name="q-${qid}"]`).forEach(r => r.closest('.q-opt').classList.remove('selected'));
        const sel = document.querySelector(`[name="q-${qid}"][value="${optId}"]`);
        if (sel) sel.closest('.q-opt').classList.add('selected');
    }

    function nextQ() {
        if (curQ === QUIZ_TOTAL - 1) { submitQuiz(); return; }
        document.getElementById('qs-' + curQ).style.display = 'none';
        curQ++;
        document.getElementById('qs-' + curQ).style.display = 'block';
        updateProgress();
        window.scrollTo({ top: document.getElementById('quizWrap').offsetTop - 80, behavior: 'smooth' });
    }

    function prevQ() {
        document.getElementById('qs-' + curQ).style.display = 'none';
        curQ--;
        document.getElementById('qs-' + curQ).style.display = 'block';
        updateProgress();
    }

    async function submitQuiz() {
        const answered = Object.keys(answers).length;
        if (answered < QUIZ_TOTAL && !confirm(`You answered ${answered} of ${QUIZ_TOTAL} questions. Submit anyway?`)) return;
        const btn = document.getElementById('nextBtn');
        btn.disabled = true; btn.textContent = 'Submitting…';
        try {
            const res  = await fetch(BASE_URL + 'ajax/learn.php?action=submit_quiz', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ content_id: CONTENT_ID, answers })
            });
            const data = await res.json();
            showResult(data);
        } catch { alert('Submission failed. Please try again.'); btn.disabled = false; }
    }

    function showResult(data) {
        document.getElementById('quizQuestions').style.display = 'none';
        document.querySelector('.quiz-nav').style.display = 'none';
        document.getElementById('quizResult').style.display = 'block';
        const passed = data.passed;
        let certHtml = '';
        if (data.certificate_code) {
            certHtml = `<div class="cert-award">
                <h4>🏆 Certificate Earned!</h4>
                <div class="cert-code">${data.certificate_code}</div>
                <p>Save this code as proof of completion.</p>
            </div>`;
        }
        document.getElementById('quizResult').innerHTML = `
            <div class="quiz-result">
                <div class="result-ring ${passed?'pass':'fail'}">${data.score}%</div>
                <div class="result-label">${passed ? '🎉 You Passed!' : '📚 Keep Practicing!'}</div>
                <div class="result-sub">${data.correct} correct of ${data.total} · Pass mark: ${data.pass_score}%</div>
                ${certHtml}
                <button class="lv-btn primary" onclick="location.reload()" style="margin:auto;">
                    <span class="material-icons">refresh</span> Try Again
                </button>
            </div>`;
    }

    updateProgress();
    </script>
    <?php endif; ?>

    <!-- ══ RELATED ════════════════════════════════════════════════════ -->
    <?php if (!empty($related)): ?>
    <div class="related-section">
        <div class="section-hd"><span class="material-icons">recommend</span> <?php echo __('related_content'); ?></div>
        <div class="related-grid">
        <?php foreach ($related as $r):
            $rtm = $TYPE_META[$r['type']] ?? $TYPE_META['article'];
        ?>
        <a class="rel-card" href="<?php echo $base_url; ?>?page=learn-view&id=<?php echo $r['id']; ?>">
            <div class="rel-card-thumb" style="background:<?php echo $rtm['color']; ?>18;">
                <?php if ($r['thumbnail_url']): ?>
                <img src="<?php echo htmlspecialchars($r['thumbnail_url']); ?>" alt="">
                <?php else: echo '<span style="font-size:36px;">' . $relEmojis[$r['type']] . '</span>'; endif; ?>
                <span class="rel-card-type-badge" style="background:<?php echo $rtm['color']; ?>dd;"><?php echo strtoupper($r['type']); ?></span>
            </div>
            <div class="rel-card-body">
                <div class="rel-card-title"><?php echo htmlspecialchars($r['title']); ?></div>
                <div class="rel-card-meta">
                    <?php if ($r['duration_min']): ?>
                    <span class="material-icons">schedule</span><?php echo $r['duration_min']; ?> min
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /lv-main -->

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════ -->
<aside class="lv-side">

    <!-- Progress / completion -->
    <div class="lv-side-card">
        <div class="lv-side-head"><span class="material-icons">info</span> Content Info</div>
        <div class="lv-side-body">
            <div class="lv-meta-row"><span class="lv-meta-label">Type</span><span class="lv-meta-val"><?php echo $tm['label']; ?></span></div>
            <div class="lv-meta-row"><span class="lv-meta-label">Views</span><span class="lv-meta-val"><?php echo number_format($item['views']); ?></span></div>
            <div class="lv-meta-row"><span class="lv-meta-label">Likes</span><span class="lv-meta-val" id="sidebarLikeCount"><?php echo $item['like_count']; ?></span></div>
            <?php if ($item['duration_min']): ?>
            <div class="lv-meta-row"><span class="lv-meta-label">Duration</span><span class="lv-meta-val"><?php echo $item['duration_min']; ?> min</span></div>
            <?php endif; ?>
            <div class="lv-meta-row"><span class="lv-meta-label">Level</span><span class="lv-meta-val"><?php echo ucfirst($item['difficulty']??'–'); ?></span></div>
            <div class="lv-meta-row"><span class="lv-meta-label">Season</span><span class="lv-meta-val"><?php echo $item['season']==='all'?'All':ucfirst($item['season']); ?></span></div>
            <?php if ($item['created_at']): ?>
            <div class="lv-meta-row"><span class="lv-meta-label">Published</span><span class="lv-meta-val"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span></div>
            <?php endif; ?>
            <div class="lv-meta-row"><span class="lv-meta-label">Status</span>
                <span style="color:<?php echo $item['user_completed'] ? '#059669' : '#f59e0b'; ?>;font-weight:700;">
                    <?php echo $item['user_completed'] ? '✅ Completed' : '📖 In Progress'; ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($item['crop_tags']): ?>
    <div class="lv-side-card">
        <div class="lv-side-head"><span class="material-icons">agriculture</span> Crop Tags</div>
        <div class="lv-side-body">
            <?php foreach (explode(',', $item['crop_tags']) as $tag): ?>
            <span class="crop-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($item['cert_code']): ?>
    <div class="lv-side-card" style="border-color:#fbbf24;">
        <div class="lv-side-head" style="color:#92400e;background:#fffbeb;">
            <span class="material-icons" style="color:#f59e0b;">emoji_events</span> Certificate
        </div>
        <div class="lv-side-body">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;"><?php echo htmlspecialchars($item['title']); ?></div>
            <div class="cert-side-code"><?php echo $item['cert_code']; ?></div>
            <div style="font-size:11px;color:#9ca3af;">Keep this code as proof of completion.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick actions -->
    <div class="lv-side-card">
        <div class="lv-side-head"><span class="material-icons">bolt</span> Quick Actions</div>
        <div class="lv-side-body" style="display:flex;flex-direction:column;gap:8px;">
            <button class="lv-btn <?php echo $item['user_liked']?'liked':''; ?>" id="sidebarLikeBtn" onclick="toggleLike()" style="width:100%;justify-content:center;">
                <span class="material-icons"><?php echo $item['user_liked']?'favorite':'favorite_border'; ?></span>
                <?php echo $item['user_liked']?'Unlike':'Like this'; ?>
            </button>
            <?php if (!$item['user_completed'] && $item['type'] !== 'quiz'): ?>
            <button class="lv-btn primary" onclick="markComplete()" style="width:100%;justify-content:center;">
                <span class="material-icons">task_alt</span> Mark Complete
            </button>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>?page=learn" class="lv-btn" style="width:100%;justify-content:center;">
                <span class="material-icons">library_books</span> Browse All
            </a>
        </div>
    </div>

</aside>
</div><!-- /lv-wrap -->

<script>
const CONTENT_ID_GLOBAL = <?php echo $contentId; ?>;
const IS_READABLE = <?php echo $isReadable ? 'true' : 'false'; ?>;

/* ── Like ─────────────────────────────────────────────────────────────── */
async function toggleLike() {
    try {
        const res  = await fetch(BASE_URL + 'ajax/learn.php?action=toggle_like', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ id: CONTENT_ID_GLOBAL })
        });
        const data = await res.json();
        if (!data.success) return;
        ['likeBtn','sidebarLikeBtn'].forEach(id => {
            const btn = document.getElementById(id);
            if (!btn) return;
            btn.classList.toggle('liked', data.liked);
            const ic = btn.querySelector('.material-icons');
            if (ic) ic.textContent = data.liked ? 'favorite' : 'favorite_border';
            const span = btn.querySelector('span:last-child');
            if (id === 'sidebarLikeBtn' && span) span.textContent = data.liked ? 'Unlike' : 'Like this';
        });
        ['likeCount','sidebarLikeCount','heroLikeCount'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = data.count;
        });
    } catch {}
}

/* ── Complete ─────────────────────────────────────────────────────────── */
async function markComplete() {
    try {
        await fetch(BASE_URL + 'ajax/learn.php?action=mark_complete', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ id: CONTENT_ID_GLOBAL })
        });
        location.reload();
    } catch {}
}

/* ── Share ────────────────────────────────────────────────────────────── */
function shareContent() {
    if (navigator.share) {
        navigator.share({ title: document.title, url: location.href }).catch(() => {});
    } else {
        navigator.clipboard?.writeText(location.href).then(() => alert('Link copied!'));
    }
}

/* ── Reading Progress ─────────────────────────────────────────────────── */
if (IS_READABLE) {
    const bar = document.getElementById('readProgress');
    bar.style.display = 'block';
    window.addEventListener('scroll', () => {
        const scrolled = document.documentElement.scrollTop;
        const total    = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        bar.style.width = total > 0 ? Math.min(100, scrolled / total * 100) + '%' : '0%';
    });
}

/* ── Table of Contents ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('articleBody');
    const toc  = document.getElementById('tocContainer');
    if (!body || !toc) return;

    const headings = body.querySelectorAll('h2, h3');
    if (headings.length < 3) return;

    let listHtml = '';
    headings.forEach((h, i) => {
        const id = 'heading-' + i;
        h.id = id;
        const indent = h.tagName === 'H3' ? 'padding-left:16px;' : '';
        listHtml += `<li style="${indent}"><a href="#${id}">${h.textContent}</a></li>`;
    });

    toc.innerHTML = `<div class="toc-box">
        <h4><span class="material-icons">format_list_bulleted</span>Table of Contents</h4>
        <ul class="toc-list">${listHtml}</ul>
    </div>`;
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
