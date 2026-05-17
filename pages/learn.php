<?php
/**
 * Agricultural Learning Center — Farmer Browse Page
 */
if (!isLoggedIn()) { redirect('login'); }

// Handle inline language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    $_SESSION['language'] = $_GET['lang'];
    setcookie('language', $_GET['lang'], time() + (86400 * 30), '/');
}

$currentUser = getCurrentUser();
include __DIR__ . '/../layouts/header.php';
?>

<style>
/* ── Learning Center ─────────────────────────────────────────────────── */
.learn-hero { background: linear-gradient(135deg,#1b4332 0%,#2d6a4f 50%,#40916c 100%); }

/* Filter bar */
.learn-filters {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    position: sticky; top: 60px; z-index: 50;
    padding: 0 20px;
}
.learn-filters-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center; gap: 12px;
    overflow-x: auto; padding: 12px 0;
    scrollbar-width: none;
}
.learn-filters-inner::-webkit-scrollbar { display: none; }
.filter-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; border: 1.5px solid #e5e7eb;
    font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;
    background: #fff; color: #374151; transition: all .2s;
}
.filter-pill .material-icons { font-size: 16px; }
.filter-pill.active, .filter-pill:hover { border-color: #557A46; background: #557A46; color: #fff; }
.filter-divider { width: 1px; height: 28px; background: #e5e7eb; flex-shrink: 0; }

/* Search */
.learn-search-wrap { max-width: 1200px; margin: 0 auto 20px; padding: 0 20px; }
.learn-search-box {
    display: flex; align-items: center; gap: 10px;
    background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px;
    padding: 10px 16px;
}
.learn-search-box .material-icons { color: #9ca3af; }
.learn-search-box input {
    flex: 1; border: none; outline: none; font-size: 14px; background: transparent;
}

/* Content grid */
.learn-grid {
    max-width: 1200px; margin: 0 auto; padding: 0 20px 40px;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 20px;
}

/* Content card */
.learn-card {
    background: #fff; border-radius: 14px; overflow: hidden;
    border: 1px solid #e5e7eb; transition: all .2s; cursor: pointer;
    display: flex; flex-direction: column;
}
.learn-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); border-color: #557A46; }

.learn-card-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden; background: #f3f4f6;
    flex-shrink: 0;
}
.learn-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
.learn-card-thumb .thumb-placeholder {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    font-size: 48px; background: linear-gradient(135deg,#f0fdf4,#dcfce7);
}
.thumb-type-badge {
    position: absolute; top: 8px; left: 8px;
    display: flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600; color: #fff;
}
.thumb-type-badge .material-icons { font-size: 13px; }
.play-overlay {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,.3); opacity: 0; transition: opacity .2s;
}
.learn-card:hover .play-overlay { opacity: 1; }
.play-overlay .material-icons { font-size: 48px; color: #fff; }

.learn-card-body { padding: 14px; flex: 1; display: flex; flex-direction: column; }
.learn-card-meta {
    display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;
}
.meta-tag {
    font-size: 11px; padding: 2px 8px; border-radius: 4px;
    font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
}
.meta-diff { color: #92400e; background: #fef3c7; }
.meta-diff.advanced { color: #7c3aed; background: #ede9fe; }
.meta-diff.intermediate { color: #1d4ed8; background: #dbeafe; }
.meta-season { color: #065f46; background: #d1fae5; }
.learn-card-title {
    font-size: 15px; font-weight: 600; color: #111827; line-height: 1.4;
    margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}
.learn-card-desc {
    font-size: 12px; color: #6b7280; line-height: 1.5; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.learn-card-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 12px; padding-top: 10px; border-top: 1px solid #f3f4f6;
}
.card-author { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; }
.card-author-avatar {
    width: 22px; height: 22px; border-radius: 50%; background: #557A46;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: #fff;
}
.card-stats { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #9ca3af; }
.card-stats span { display: flex; align-items: center; gap: 2px; }
.card-stats .material-icons { font-size: 14px; }
.completed-badge {
    display: flex; align-items: center; gap: 3px;
    color: #059669; font-size: 11px; font-weight: 600;
}
.completed-badge .material-icons { font-size: 14px; }

/* Featured strip */
.featured-strip {
    max-width: 1200px; margin: 0 auto 28px; padding: 0 20px;
}
.featured-strip h3 { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.featured-strip h3 .material-icons { color: #f59e0b; }
.featured-scroll {
    display: flex; gap: 16px; overflow-x: auto; padding-bottom: 8px;
    scrollbar-width: thin; scrollbar-color: #e5e7eb transparent;
}
.featured-scroll::-webkit-scrollbar { height: 4px; }
.featured-item {
    flex-shrink: 0; width: 220px; border-radius: 12px; overflow: hidden;
    background: #fff; border: 1.5px solid #fbbf24; cursor: pointer; transition: all .2s;
}
.featured-item:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,158,11,.2); }
.featured-thumb { aspect-ratio: 16/9; background: linear-gradient(135deg,#fef3c7,#fde68a); display: flex; align-items: center; justify-content: center; font-size: 36px; }
.featured-info { padding: 10px 12px; }
.featured-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.3; margin-bottom: 4px; }
.featured-type { font-size: 11px; color: #f59e0b; font-weight: 600; text-transform: uppercase; }

/* My Learning sidebar panel */
.learn-layout { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; display: flex; gap: 24px; }
.learn-main { flex: 1; min-width: 0; }
.learn-sidebar { width: 280px; flex-shrink: 0; }
@media (max-width: 900px) { .learn-sidebar { display: none; } }

.sidebar-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; margin-bottom: 16px; }
.sidebar-card-head { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; color: #111827; }
.sidebar-card-head .material-icons { font-size: 18px; color: #557A46; }
.sidebar-card-body { padding: 12px 16px; }

.progress-item {
    display: flex; align-items: center; gap: 10px; padding: 8px 0;
    border-bottom: 1px solid #f9fafb; cursor: pointer; transition: background .15s;
}
.progress-item:last-child { border-bottom: none; }
.progress-item:hover { background: #f9fafb; margin: 0 -16px; padding-left: 16px; padding-right: 16px; }
.progress-thumb { width: 44px; height: 44px; border-radius: 8px; background: #f3f4f6; object-fit: cover; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.progress-thumb img { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; }
.progress-info { flex: 1; min-width: 0; }
.progress-title { font-size: 12px; font-weight: 500; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.progress-type { font-size: 11px; color: #9ca3af; }

.cert-item { padding: 10px 0; border-bottom: 1px solid #f9fafb; }
.cert-item:last-child { border-bottom: none; }
.cert-title { font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 2px; }
.cert-code { font-size: 11px; color: #9ca3af; font-family: monospace; }
.cert-date { font-size: 10px; color: #9ca3af; }

/* AI tips card */
.ai-tips-card { background: linear-gradient(135deg,#f0fdf4,#dcfce7); border: 1.5px solid #bbf7d0; border-radius: 14px; overflow: hidden; margin-bottom: 16px; }
.ai-tips-head { padding: 14px 16px; display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; color: #065f46; }
.ai-tips-head .material-icons { color: #059669; }
.ai-tips-body { padding: 0 16px 14px; font-size: 13px; color: #065f46; line-height: 1.7; }
.ai-tips-body .tip-row { display: flex; gap: 8px; margin-bottom: 8px; }
.ai-tips-body .tip-num { font-weight: 700; color: #059669; flex-shrink: 0; }

/* Empty state */
.empty-learn { text-align: center; padding: 60px 20px; color: #6b7280; }
.empty-learn .material-icons { font-size: 64px; color: #d1fae5; display: block; margin-bottom: 12px; }
.empty-learn p { font-size: 15px; }

/* Pagination */
.learn-pager { display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap; }
.pager-btn { padding: 7px 14px; border-radius: 8px; border: 1.5px solid #e5e7eb; background: #fff; font-size: 13px; cursor: pointer; transition: all .2s; }
.pager-btn.active, .pager-btn:hover { background: #557A46; color: #fff; border-color: #557A46; }
</style>

<!-- Hero -->
<section class="hero-modern learn-hero">
    <div class="hero-particles">
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">school</span>
            <span><?php echo __('learning_center'); ?></span>
        </div>
        <h1><span class="material-icons" style="font-size:2rem">menu_book</span> <?php echo __('learning_center'); ?></h1>
        <p class="hero-subtitle"><?php echo __('learn_hero_subtitle'); ?></p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1"><span class="material-icons">play_circle</span></div>
        <div class="floating-card fc-2"><span class="material-icons">school</span></div>
        <div class="floating-card fc-3"><span class="material-icons">emoji_events</span></div>
    </div>
</section>

<!-- Filter bar -->
<div class="learn-filters">
    <div class="learn-filters-inner" id="typeFilters">
        <button class="filter-pill active" data-type="all"><span class="material-icons">apps</span> <?php echo __('all_content'); ?></button>
        <button class="filter-pill" data-type="playlist"><span class="material-icons">play_circle</span> <?php echo __('videos'); ?></button>
        <button class="filter-pill" data-type="blog"><span class="material-icons">article</span> Blogs</button>
        <button class="filter-pill" data-type="guide"><span class="material-icons">calendar_month</span> <?php echo __('guides'); ?></button>
        <button class="filter-pill" data-type="article"><span class="material-icons">auto_stories</span> <?php echo __('articles'); ?></button>
        <button class="filter-pill" data-type="webinar"><span class="material-icons">live_tv</span> Webinars</button>
        <button class="filter-pill" data-type="quiz"><span class="material-icons">quiz</span> Quizzes</button>
        <div class="filter-divider"></div>
        <button class="filter-pill" data-season="boro">🌾 Boro</button>
        <button class="filter-pill" data-season="aman">🌿 Aman</button>
        <button class="filter-pill" data-season="aus">☀️ Aus</button>
        <button class="filter-pill" data-season="rabi">❄️ Rabi</button>
        <div class="filter-divider"></div>
        <button class="filter-pill" data-diff="beginner">🟢 <?php echo __('beginner_level'); ?></button>
        <button class="filter-pill" data-diff="intermediate">🔵 <?php echo __('intermediate_level'); ?></button>
        <button class="filter-pill" data-diff="advanced">🟣 <?php echo __('advanced_level'); ?></button>
    </div>
</div>

<?php $curLang = get_language(); ?>
<div style="max-width:1200px;margin:24px auto 0;padding:0 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <!-- Search -->
    <div class="learn-search-box" style="flex:1;min-width:200px;margin:0;">
        <span class="material-icons">search</span>
        <input type="text" id="searchInput" placeholder="<?php echo __('search_learning'); ?>">
    </div>
    <select id="sortSelect" style="padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;background:#fff;cursor:pointer;">
        <option value="latest"><?php echo $curLang === 'bn' ? 'সর্বশেষ' : 'Latest'; ?></option>
        <option value="popular"><?php echo $curLang === 'bn' ? 'সর্বাধিক দেখা' : 'Most Viewed'; ?></option>
        <option value="liked"><?php echo $curLang === 'bn' ? 'সর্বাধিক লাইক' : 'Most Liked'; ?></option>
    </select>
    <!-- Language switcher shortcut -->
    <div style="display:flex;gap:6px;">
        <a href="?page=learn&lang=en" style="padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;border:1.5px solid <?php echo $curLang==='en'?'#557A46':'#e5e7eb'; ?>;background:<?php echo $curLang==='en'?'#557A46':'#fff'; ?>;color:<?php echo $curLang==='en'?'#fff':'#374151'; ?>;text-decoration:none;">EN</a>
        <a href="?page=learn&lang=bn" style="padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;border:1.5px solid <?php echo $curLang==='bn'?'#557A46':'#e5e7eb'; ?>;background:<?php echo $curLang==='bn'?'#557A46':'#fff'; ?>;color:<?php echo $curLang==='bn'?'#fff':'#374151'; ?>;text-decoration:none;">বাং</a>
    </div>
</div>

<!-- Layout with sidebar -->
<div class="learn-layout" style="margin-top:24px;">
    <!-- Main content area -->
    <div class="learn-main">
        <!-- Featured strip -->
        <div class="featured-strip" id="featuredStrip" style="padding:0;"></div>

        <!-- Grid -->
        <div class="learn-grid" id="contentGrid" style="padding:0;"></div>
        <div class="learn-pager" id="pager"></div>
    </div>

    <!-- Sidebar -->
    <aside class="learn-sidebar">
        <!-- AI Tips -->
        <div class="ai-tips-card">
            <div class="ai-tips-head">
                <span class="material-icons">psychology</span> <?php echo $curLang === 'bn' ? 'এআই কৃষি পরামর্শ' : 'AI Farming Tips'; ?>
            </div>
            <div class="ai-tips-body" id="aiTipsBody">
                <div style="color:#9ca3af;font-size:12px;"><?php echo __('loading'); ?></div>
            </div>
            <div style="padding:0 16px 14px;">
                <button onclick="refreshAITips()" style="font-size:12px;color:#059669;background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <span class="material-icons" style="font-size:14px;">refresh</span> <?php echo $curLang === 'bn' ? 'রিফ্রেশ' : 'Refresh tips'; ?>
                </button>
            </div>
        </div>

        <!-- My Learning -->
        <div class="sidebar-card">
            <div class="sidebar-card-head">
                <span class="material-icons">track_changes</span> <?php echo __('my_content'); ?>
            </div>
            <div class="sidebar-card-body" id="myProgressList">
                <div style="color:#9ca3af;font-size:12px;"><?php echo __('loading'); ?></div>
            </div>
        </div>

        <!-- Certificates -->
        <div class="sidebar-card">
            <div class="sidebar-card-head">
                <span class="material-icons">emoji_events</span> <?php echo __('certificates_issued'); ?>
            </div>
            <div class="sidebar-card-body" id="myCertsList">
                <div style="color:#9ca3af;font-size:12px;"><?php echo __('loading'); ?></div>
            </div>
        </div>
    </aside>
</div>

<script>
const LEARN_API = BASE_URL + 'ajax/learn.php';
const BASE      = BASE_URL;

let filters  = { type: 'all', season: '', diff: '', q: '', page: 1 };
let debTimer = null;

// ── Type icons/colors ──────────────────────────────────────────────────
const TYPE_META = {
    video:    { icon: 'play_circle',   color: '#e53935', emoji: '🎬' },
    playlist: { icon: 'playlist_play', color: '#e53935', emoji: '📺' },
    blog:     { icon: 'article',       color: '#1976d2', emoji: '📝' },
    guide:    { icon: 'calendar_month',color: '#0097a7', emoji: '📖' },
    article:  { icon: 'auto_stories',  color: '#7b1fa2', emoji: '📚' },
    webinar:  { icon: 'live_tv',       color: '#f57c00', emoji: '🎙️' },
    quiz:     { icon: 'quiz',          color: '#388e3c', emoji: '📋' },
};

const DIFF_CLASS = { beginner: '', intermediate: 'intermediate', advanced: 'advanced' };

// ── Filter pills ───────────────────────────────────────────────────────
document.querySelectorAll('#typeFilters .filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.dataset.type !== undefined) {
            document.querySelectorAll('[data-type]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filters.type = btn.dataset.type;
        } else if (btn.dataset.season !== undefined) {
            const wasActive = btn.classList.contains('active');
            document.querySelectorAll('[data-season]').forEach(b => b.classList.remove('active'));
            btn.classList.toggle('active', !wasActive);
            filters.season = wasActive ? '' : btn.dataset.season;
        } else if (btn.dataset.diff !== undefined) {
            const wasActive = btn.classList.contains('active');
            document.querySelectorAll('[data-diff]').forEach(b => b.classList.remove('active'));
            btn.classList.toggle('active', !wasActive);
            filters.diff = wasActive ? '' : btn.dataset.diff;
        }
        filters.page = 1;
        loadContent();
    });
});

document.getElementById('searchInput').addEventListener('input', e => {
    clearTimeout(debTimer);
    debTimer = setTimeout(() => { filters.q = e.target.value.trim(); filters.page = 1; loadContent(); }, 400);
});

document.getElementById('sortSelect').addEventListener('change', () => loadContent());

// ── Load content ──────────────────────────────────────────────────────
async function loadContent() {
    const grid = document.getElementById('contentGrid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:#9ca3af;"><span class="material-icons" style="font-size:40px;display:block;margin-bottom:8px;">hourglass_empty</span>Loading…</div>';

    const params = new URLSearchParams({
        action: 'list',
        type: filters.type, season: filters.season, diff: filters.diff,
        q: filters.q, p: filters.page,
    });
    const sort = document.getElementById('sortSelect').value;
    if (sort) params.append('sort', sort);

    try {
        const res  = await fetch(LEARN_API + '?' + params);
        const data = await res.json();
        if (!data.success) { grid.innerHTML = '<div class="empty-learn"><span class="material-icons">error_outline</span><p>' + (data.message||'Error loading content') + '</p></div>'; return; }

        const items = Array.isArray(data.items) ? data.items : [];
        renderFeatured(items.filter(i => i.is_featured == 1).slice(0,6));
        renderGrid(items);
        renderPager(data.pages || 1, data.page || 1);
    } catch (e) {
        grid.innerHTML = '<div class="empty-learn"><span class="material-icons">wifi_off</span><p>Failed to load content.</p></div>';
    }
}

function renderFeatured(items) {
    const strip = document.getElementById('featuredStrip');
    if (!items.length) { strip.innerHTML = ''; return; }
    const tm = TYPE_META;
    strip.innerHTML = `<h3><span class="material-icons">star</span> Featured</h3>
    <div class="featured-scroll">` + items.map(it => `
        <div class="featured-item" onclick="viewContent(${it.id})">
            <div class="featured-thumb">${tm[it.type]?.emoji||'📄'}</div>
            <div class="featured-info">
                <div class="featured-title">${escHtml(it.title)}</div>
                <div class="featured-type">${it.type.toUpperCase()}</div>
            </div>
        </div>`).join('') + `</div>`;
}

function renderGrid(items) {
    const grid = document.getElementById('contentGrid');
    if (!items.length) {
        grid.innerHTML = '<div class="empty-learn" style="grid-column:1/-1"><span class="material-icons">search_off</span><p>No content found. Try different filters.</p></div>';
        return;
    }
    grid.innerHTML = items.map(it => cardHtml(it)).join('');
}

function cardHtml(it) {
    const tm   = TYPE_META[it.type] || { icon:'article', color:'#557A46', emoji:'📄' };
    const diff = DIFF_CLASS[it.difficulty] || '';
    const diffLabel = (it.difficulty||'').charAt(0).toUpperCase() + (it.difficulty||'').slice(1);
    const author = escHtml((it.first_name||'') + ' ' + (it.last_name||''));
    const initials = ((it.first_name||'?')[0] + (it.last_name||'?')[0]).toUpperCase();
    const thumb = it.thumbnail_url
        ? `<img src="${escHtml(it.thumbnail_url)}" loading="lazy">`
        : `<div class="thumb-placeholder">${tm.emoji}</div>`;
    const playOv = ['video','playlist','webinar'].includes(it.type) ? `<div class="play-overlay"><span class="material-icons">play_circle</span></div>` : '';
    const completed = it.user_completed == 1 ? `<span class="completed-badge"><span class="material-icons">check_circle</span> Done</span>` : '';
    const seasonLabel = it.season !== 'all' ? `<span class="meta-tag meta-season">${it.season}</span>` : '';
    const dur = it.duration_min ? `<span>${it.duration_min}m</span>` : '';

    return `<div class="learn-card" onclick="viewContent(${it.id})">
        <div class="learn-card-thumb">
            ${thumb}
            <div class="thumb-type-badge" style="background:${tm.color}">
                <span class="material-icons">${tm.icon}</span>${it.type}
            </div>
            ${playOv}
        </div>
        <div class="learn-card-body">
            <div class="learn-card-meta">
                ${diffLabel ? `<span class="meta-tag meta-diff ${diff}">${diffLabel}</span>` : ''}
                ${seasonLabel}
                ${completed}
            </div>
            <div class="learn-card-title">${escHtml(it.title)}</div>
            <div class="learn-card-desc">${escHtml(it.description||'')}</div>
            <div class="learn-card-footer">
                <div class="card-author">
                    <div class="card-author-avatar">${initials}</div>
                    ${author}
                </div>
                <div class="card-stats">
                    <span><span class="material-icons">visibility</span> ${it.views||0}</span>
                    <span><span class="material-icons">favorite</span> ${it.like_count||0}</span>
                    ${dur}
                </div>
            </div>
        </div>
    </div>`;
}

function renderPager(total, current) {
    const pager = document.getElementById('pager');
    if (total <= 1) { pager.innerHTML = ''; return; }
    let html = '';
    if (current > 1) html += `<button class="pager-btn" onclick="goPage(${current-1})">‹ Prev</button>`;
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || Math.abs(i - current) <= 2) {
            html += `<button class="pager-btn${i===current?' active':''}" onclick="goPage(${i})">${i}</button>`;
        } else if (Math.abs(i - current) === 3) {
            html += `<span style="padding:7px 4px;color:#9ca3af;">…</span>`;
        }
    }
    if (current < total) html += `<button class="pager-btn" onclick="goPage(${current+1})">Next ›</button>`;
    pager.innerHTML = html;
}

function goPage(p) { filters.page = p; loadContent(); window.scrollTo({top:200,behavior:'smooth'}); }

// ── View content ──────────────────────────────────────────────────────
function viewContent(id) {
    window.location.href = BASE + '?page=learn-view&id=' + id;
}

// ── My Progress sidebar ───────────────────────────────────────────────
async function loadSidebar() {
    try {
        const res  = await fetch(LEARN_API + '?action=my_progress');
        const data = await res.json();
        if (!data.success) return;

        // Progress
        const pList = document.getElementById('myProgressList');
        if (!data.progress.length) {
            pList.innerHTML = '<div style="font-size:12px;color:#9ca3af;">No activity yet. Start learning!</div>';
        } else {
            const tm = TYPE_META;
            pList.innerHTML = data.progress.slice(0,6).map(p => `
                <div class="progress-item" onclick="viewContent(${p.content_id})">
                    <div class="progress-thumb">${tm[p.type]?.emoji||'📄'}</div>
                    <div class="progress-info">
                        <div class="progress-title">${escHtml(p.title)}</div>
                        <div class="progress-type">${p.completed==1?'✅ Completed':'In progress'}</div>
                    </div>
                </div>`).join('');
        }

        // Certs
        const cList = document.getElementById('myCertsList');
        if (!data.certificates.length) {
            cList.innerHTML = '<div style="font-size:12px;color:#9ca3af;">No certificates yet. Complete a quiz to earn one!</div>';
        } else {
            cList.innerHTML = data.certificates.map(c => `
                <div class="cert-item">
                    <div class="cert-title">🏆 ${escHtml(c.title)}</div>
                    <div class="cert-code">${c.certificate_code}</div>
                    <div class="cert-date">${new Date(c.issued_at).toLocaleDateString()}</div>
                </div>`).join('');
        }
    } catch {}
}

// ── AI Tips ───────────────────────────────────────────────────────────
async function refreshAITips() {
    const box = document.getElementById('aiTipsBody');
    box.innerHTML = '<div style="color:#9ca3af;font-size:12px;">✨ Generating personalised tips…</div>';
    try {
        const res  = await fetch(LEARN_API + '?action=ai_tips&crop=rice,vegetables&season=current');
        const data = await res.json();
        if (!data.success) throw new Error();
        // Render tips nicely
        const raw = data.tips || '';
        const lines = raw.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').split('\n').filter(l=>l.trim());
        box.innerHTML = lines.map(l => `<div class="tip-row"><div>${l}</div></div>`).join('');
    } catch {
        box.innerHTML = '<div style="color:#9ca3af;font-size:12px;">Could not load tips right now.</div>';
    }
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────
loadContent();
loadSidebar();
refreshAITips();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
