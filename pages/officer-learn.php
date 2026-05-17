<?php
/**
 * Agricultural Learning Center — Officer Management Panel
 * Officers can create/edit/delete content, playlists, quizzes
 */
if (!isLoggedIn()) { redirect('login'); }

// Inline language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    $_SESSION['language'] = $_GET['lang'];
    setcookie('language', $_GET['lang'], time() + (86400 * 30), '/');
}

$currentUser = getCurrentUser();
if (!in_array($currentUser['role'], ['officer', 'admin'])) { redirect('learn'); }

include __DIR__ . '/../layouts/header.php';

$db   = new Database();
$uid  = $_SESSION['user_id'];

// Stats
$totalContent  = $db->single("SELECT COUNT(*) as c FROM learn_content WHERE created_by = ?", [$uid])['c'] ?? 0;
$published     = $db->single("SELECT COUNT(*) as c FROM learn_content WHERE created_by = ? AND is_published = 1", [$uid])['c'] ?? 0;
$totalViews    = $db->single("SELECT SUM(views) as v FROM learn_content WHERE created_by = ?", [$uid])['v'] ?? 0;
$totalLikes    = $db->single("SELECT COUNT(*) as c FROM learn_likes ll JOIN learn_content lc ON ll.content_id = lc.id WHERE lc.created_by = ?", [$uid])['c'] ?? 0;
$totalCerts    = $db->single("SELECT COUNT(*) as c FROM learn_certificates lce JOIN learn_content lc ON lce.content_id = lc.id WHERE lc.created_by = ?", [$uid])['c'] ?? 0;

$categories = $db->resultSet("SELECT * FROM learn_categories ORDER BY sort_order ASC", []);
?>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<style>
/* ── Quill overrides ── */
.ql-container { font-family: inherit; font-size: 13px; border-radius: 0 0 8px 8px !important; }
.ql-toolbar { border-radius: 8px 8px 0 0 !important; border-color: #e5e7eb !important; }
.ql-container.ql-snow { border-color: #e5e7eb !important; }
.ql-editor { min-height: 80px; }
.ql-editor.tall { min-height: 180px; }
.quill-wrap:focus-within .ql-toolbar,
.quill-wrap:focus-within .ql-container.ql-snow { border-color: #557A46 !important; }

/* Thumb upload */
.thumb-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.thumb-row .form-input { flex:1; min-width:160px; }
.thumb-preview { width:70px; height:50px; border-radius:8px; object-fit:cover; border:1px solid #e5e7eb; display:none; }
.btn-upload-thumb { padding:8px 12px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; font-size:12px; white-space:nowrap; display:flex; align-items:center; gap:4px; }
.btn-upload-thumb:hover { background:#e5e7eb; }

/* ── Officer Learn Panel ──────────────────────────────────────────── */
.ol-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }

/* Stats row */
.ol-stats { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.ol-stat {
    flex: 1; min-width: 130px; background: #fff; border-radius: 12px;
    border: 1px solid #e5e7eb; padding: 16px 18px; text-align: center;
}
.ol-stat .val { font-size: 28px; font-weight: 800; color: #111827; }
.ol-stat .lbl { font-size: 12px; color: #6b7280; font-weight: 500; margin-top: 2px; }

/* Toolbar */
.ol-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.ol-toolbar h2 { font-size: 18px; font-weight: 700; color: #111827; flex: 1; }
.btn-create { display: flex; align-items: center; gap: 6px; padding: 10px 18px; background: #557A46; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .2s; }
.btn-create:hover { background: #3d5c32; }
.btn-create .material-icons { font-size: 18px; }

/* Content table */
.ol-table-wrap { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; }
.ol-table { width: 100%; border-collapse: collapse; }
.ol-table th { background: #f9fafb; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; padding: 12px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.ol-table td { padding: 12px 14px; border-bottom: 1px solid #f9fafb; vertical-align: middle; font-size: 13px; color: #374151; }
.ol-table tr:last-child td { border-bottom: none; }
.ol-table tr:hover td { background: #fafafa; }
.content-thumb-cell { display: flex; align-items: center; gap: 10px; }
.content-emoji { font-size: 24px; flex-shrink: 0; }
.content-cell-title { font-weight: 600; color: #111827; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.content-cell-desc { font-size: 11px; color: #9ca3af; }
.type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; color: #fff; }
.type-badge .material-icons { font-size: 12px; }
.status-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all .2s; }
.status-pill.published { background: #d1fae5; color: #065f46; }
.status-pill.draft { background: #f3f4f6; color: #6b7280; }
.status-pill:hover { opacity: .8; }
.tbl-actions { display: flex; align-items: center; gap: 6px; }
.tbl-btn { width: 30px; height: 30px; border: none; background: #f3f4f6; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; }
.tbl-btn .material-icons { font-size: 16px; color: #6b7280; }
.tbl-btn:hover { background: #e5e7eb; }
.tbl-btn.del:hover { background: #fef2f2; }
.tbl-btn.del .material-icons { color: #ef4444; }
.tbl-btn.edit:hover { background: #eff6ff; }
.tbl-btn.edit .material-icons { color: #3b82f6; }
.tbl-btn.view:hover { background: #f0fdf4; }
.tbl-btn.view .material-icons { color: #557A46; }
.tbl-btn.manage:hover { background: #fef3c7; }
.tbl-btn.manage .material-icons { color: #d97706; }

/* Modal base */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000;
    display: flex; align-items: center; justify-content: center; padding: 20px;
    opacity: 0; pointer-events: none; transition: opacity .2s;
}
.modal-overlay.show { opacity: 1; pointer-events: all; }
.modal-box {
    background: #fff; border-radius: 16px; width: 100%; max-width: 760px;
    max-height: 90vh; overflow-y: auto; padding: 28px;
    transform: translateY(20px); transition: transform .2s;
}
.modal-overlay.show .modal-box { transform: translateY(0); }
.modal-title { font-size: 20px; font-weight: 700; color: #111827; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
.modal-close { background: none; border: none; cursor: pointer; color: #9ca3af; }
.modal-close .material-icons { font-size: 22px; }

/* Form styles */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-grid.single { grid-template-columns: 1fr; }
@media(max-width:600px){ .form-grid { grid-template-columns: 1fr; } }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full { grid-column: 1/-1; }
.form-label { font-size: 12px; font-weight: 600; color: #374151; }
.form-input, .form-select, .form-textarea {
    padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px;
    font-size: 13px; color: #111827; transition: border .2s; outline: none; width: 100%;
    font-family: inherit;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #557A46; }
.form-textarea { min-height: 80px; resize: vertical; }
.form-textarea.tall { min-height: 160px; }

/* Type tabs */
.type-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.type-tab {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px;
    border: 1.5px solid #e5e7eb; cursor: pointer; font-size: 12px; font-weight: 600;
    color: #6b7280; transition: all .2s; background: #fff;
}
.type-tab .material-icons { font-size: 16px; }
.type-tab.selected { border-color: #557A46; background: #f0fdf4; color: #065f46; }

/* Toggle switch */
.toggle-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; }
.toggle { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: #d1d5db; border-radius: 11px; cursor: pointer; transition: .2s; }
.toggle-slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .2s; }
.toggle input:checked + .toggle-slider { background: #557A46; }
.toggle input:checked + .toggle-slider::before { transform: translateX(18px); }

/* Playlist items editor */
.pl-items-list { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.pl-item-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
.pl-item-row:last-child { border-bottom: none; }
.pl-item-num { font-size: 12px; color: #9ca3af; width: 20px; text-align: center; flex-shrink: 0; }
.pl-item-title { flex: 1; font-size: 13px; color: #374151; }
.btn-add-item { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: #f0fdf4; border: 1.5px dashed #86efac; border-radius: 8px; color: #16a34a; font-size: 13px; font-weight: 500; cursor: pointer; width: 100%; justify-content: center; margin-top: 8px; }
.btn-add-item:hover { background: #dcfce7; }

/* Quiz editor */
.quiz-q-row { background: #f9fafb; border-radius: 10px; padding: 14px; margin-bottom: 12px; }
.quiz-q-title { font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
.quiz-opt-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.quiz-opt-row input[type=text] { flex: 1; padding: 7px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px; }
.quiz-opt-row input[type=radio] { accent-color: #557A46; }
.quiz-opt-row label { font-size: 11px; color: #6b7280; }
.btn-add-q { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #eff6ff; border: 1.5px dashed #93c5fd; border-radius: 8px; color: #3b82f6; font-size: 13px; font-weight: 500; cursor: pointer; width: 100%; justify-content: center; margin-top: 8px; }
.btn-add-q:hover { background: #dbeafe; }

.btn-submit { width: 100%; padding: 12px; background: #557A46; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 20px; transition: background .2s; }
.btn-submit:hover { background: #3d5c32; }
.btn-submit:disabled { opacity: .5; cursor: not-allowed; }

/* Empty state */
.ol-empty { text-align: center; padding: 60px 20px; color: #9ca3af; }
.ol-empty .material-icons { font-size: 56px; color: #e5e7eb; display: block; margin-bottom: 12px; }

/* Manage panel (playlist/quiz items) */
.manage-panel { margin-top: 24px; }
.manage-section { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 20px; margin-bottom: 16px; }
.manage-section h3 { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.manage-section h3 .material-icons { color: #557A46; }
</style>

<div class="ol-wrap">
    <!-- Hero-lite -->
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <a href="<?php echo $base_url; ?>?page=learn" style="display:flex;align-items:center;gap:4px;color:#557A46;font-size:13px;font-weight:500;text-decoration:none;">
                <span class="material-icons" style="font-size:17px;">arrow_back</span> <?php echo __('learning_center'); ?>
            </a>
            <span style="color:#e5e7eb;">›</span>
            <span style="font-size:13px;color:#6b7280;"><?php echo get_language()==='bn' ? 'কন্টেন্ট পরিচালনা' : 'Manage Content'; ?></span>
        </div>
        <h1 style="font-size:22px;font-weight:800;color:#111827;margin-top:10px;">📚 <?php echo __('officer_learning_panel'); ?></h1>
        <p style="font-size:13px;color:#6b7280;"><?php echo get_language()==='bn' ? 'কৃষকদের জন্য ভিডিও প্লেলিস্ট, ব্লগ, গাইড, ওয়েবিনার ও কুইজ তৈরি ও পরিচালনা করুন।' : 'Create and manage video playlists, blogs, seasonal guides, webinars, and quizzes for farmers.'; ?></p>
        <!-- Language toggle -->
        <div style="display:flex;gap:6px;margin-top:10px;">
            <a href="?page=officer-learn&lang=en" style="padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;border:1.5px solid <?php echo get_language()==='en'?'#557A46':'#e5e7eb'; ?>;background:<?php echo get_language()==='en'?'#557A46':'#fff'; ?>;color:<?php echo get_language()==='en'?'#fff':'#374151'; ?>;text-decoration:none;">EN</a>
            <a href="?page=officer-learn&lang=bn" style="padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;border:1.5px solid <?php echo get_language()==='bn'?'#557A46':'#e5e7eb'; ?>;background:<?php echo get_language()==='bn'?'#557A46':'#fff'; ?>;color:<?php echo get_language()==='bn'?'#fff':'#374151'; ?>;text-decoration:none;">বাং</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="ol-stats">
        <div class="ol-stat"><div class="val"><?php echo $totalContent; ?></div><div class="lbl"><?php echo __('total_content'); ?></div></div>
        <div class="ol-stat"><div class="val"><?php echo $published; ?></div><div class="lbl"><?php echo __('published'); ?></div></div>
        <div class="ol-stat"><div class="val"><?php echo number_format($totalViews); ?></div><div class="lbl"><?php echo __('total_views'); ?></div></div>
        <div class="ol-stat"><div class="val"><?php echo $totalLikes; ?></div><div class="lbl"><?php echo __('total_likes'); ?></div></div>
        <div class="ol-stat"><div class="val"><?php echo $totalCerts; ?></div><div class="lbl"><?php echo __('certificates_issued'); ?></div></div>
    </div>

    <!-- Toolbar -->
    <div class="ol-toolbar">
        <h2><?php echo get_language()==='bn' ? 'সব কন্টেন্ট' : 'All Content'; ?></h2>
        <button class="btn-create" onclick="openCreateModal()">
            <span class="material-icons">add</span> <?php echo __('create_content'); ?>
        </button>
    </div>

    <!-- Content table -->
    <div class="ol-table-wrap" id="contentTableWrap">
        <div style="padding:40px;text-align:center;color:#9ca3af;">
            <span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;">hourglass_empty</span>Loading…
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- CREATE/EDIT MODAL                                                      -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="createModal">
<div class="modal-box">
    <div class="modal-title">
        <span id="modalHeading">Create New Content</span>
        <button class="modal-close" onclick="closeModal('createModal')"><span class="material-icons">close</span></button>
    </div>

    <!-- Type selector -->
    <div class="type-tabs" id="typeTabs">
        <button class="type-tab selected" data-type="playlist" onclick="selectType('playlist',this)"><span class="material-icons">playlist_play</span>Video Playlist</button>
        <button class="type-tab" data-type="blog" onclick="selectType('blog',this)"><span class="material-icons">article</span>Blog</button>
        <button class="type-tab" data-type="guide" onclick="selectType('guide',this)"><span class="material-icons">calendar_month</span>Seasonal Guide</button>
        <button class="type-tab" data-type="article" onclick="selectType('article',this)"><span class="material-icons">auto_stories</span>Expert Article</button>
        <button class="type-tab" data-type="webinar" onclick="selectType('webinar',this)"><span class="material-icons">live_tv</span>Webinar</button>
        <button class="type-tab" data-type="quiz" onclick="selectType('quiz',this)"><span class="material-icons">quiz</span>Quiz</button>
    </div>

    <!-- Common fields -->
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Title (English) *</label>
            <input type="text" class="form-input" id="fTitle" placeholder="e.g. Boro Rice Cultivation Guide">
        </div>
        <div class="form-group">
            <label class="form-label">Title (Bangla)</label>
            <input type="text" class="form-input" id="fTitleBn" placeholder="বাংলা শিরোনাম">
        </div>
        <div class="form-group full">
            <label class="form-label">Short Description</label>
            <div class="quill-wrap" id="quillDescWrap">
                <div id="quillDesc"></div>
            </div>
            <textarea class="form-textarea" id="fDesc" style="display:none"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="fCat">
                <option value="">— Select —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Season</label>
            <select class="form-select" id="fSeason">
                <option value="all">All Seasons</option>
                <option value="boro">Boro (Winter Rice)</option>
                <option value="aman">Aman (Monsoon Rice)</option>
                <option value="aus">Aus (Summer Rice)</option>
                <option value="rabi">Rabi (Winter Crops)</option>
                <option value="kharif">Kharif (Summer Crops)</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Difficulty</label>
            <select class="form-select" id="fDiff">
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Crop Tags (comma-separated)</label>
            <input type="text" class="form-input" id="fCropTags" placeholder="rice, wheat, vegetable">
        </div>
        <div class="form-group">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" class="form-input" id="fDuration" placeholder="e.g. 20" min="1">
        </div>
        <div class="form-group">
            <label class="form-label">Thumbnail</label>
            <div class="thumb-row">
                <input type="text" class="form-input" id="fThumb" placeholder="Paste URL or upload image…">
                <label class="btn-upload-thumb">
                    <span class="material-icons" style="font-size:15px;">upload</span> Upload
                    <input type="file" id="fThumbFile" accept="image/*" style="display:none" onchange="uploadThumb(this)">
                </label>
                <img id="fThumbPreview" class="thumb-preview" alt="preview">
            </div>
        </div>
    </div>

    <!-- Type-specific fields -->
    <!-- VIDEO/PLAYLIST -->
    <div id="typeFields-playlist" class="type-fields">
        <div style="background:#fef3c7;border-radius:8px;padding:12px;font-size:12px;color:#92400e;margin:16px 0 12px;display:flex;align-items:center;gap:6px;">
            <span class="material-icons" style="font-size:16px;">info</span>
            After creating, you can add individual videos to this playlist from the manage panel.
        </div>
        <div class="form-group">
            <label class="form-label">First Video URL (YouTube)</label>
            <input type="url" class="form-input" id="fYtUrl" placeholder="https://youtube.com/watch?v=...">
        </div>
    </div>

    <!-- BLOG / GUIDE / ARTICLE -->
    <div id="typeFields-blog" class="type-fields" style="display:none">
        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Article Body</label>
            <div class="quill-wrap" id="quillBlogWrap">
                <div id="quillBlog" style="min-height:180px;"></div>
            </div>
            <textarea id="fBodyBlog" style="display:none"></textarea>
        </div>
    </div>
    <div id="typeFields-guide" class="type-fields" style="display:none">
        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Guide Content</label>
            <div class="quill-wrap" id="quillGuideWrap">
                <div id="quillGuide" style="min-height:180px;"></div>
            </div>
            <textarea id="fBodyGuide" style="display:none"></textarea>
        </div>
    </div>
    <div id="typeFields-article" class="type-fields" style="display:none">
        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Article Body</label>
            <div class="quill-wrap" id="quillArticleWrap">
                <div id="quillArticle" style="min-height:180px;"></div>
            </div>
            <textarea id="fBodyArticle" style="display:none"></textarea>
        </div>
    </div>

    <!-- WEBINAR -->
    <div id="typeFields-webinar" class="type-fields" style="display:none">
        <div class="form-grid" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Webinar/Meeting URL</label>
                <input type="url" class="form-input" id="fWebUrl" placeholder="https://meet.google.com/...">
            </div>
            <div class="form-group">
                <label class="form-label">Scheduled Date & Time</label>
                <input type="datetime-local" class="form-input" id="fWebDate">
            </div>
        </div>
    </div>

    <!-- QUIZ -->
    <div id="typeFields-quiz" class="type-fields" style="display:none">
        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Passing Score (%)</label>
            <input type="number" class="form-input" id="fPassScore" value="70" min="1" max="100" style="max-width:120px;">
        </div>
        <div style="margin-top:16px;font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">Quiz Questions</div>
        <div id="quizQList"></div>
        <button class="btn-add-q" onclick="addQuestion()">
            <span class="material-icons">add</span> Add Question
        </button>
    </div>

    <!-- Publish toggles -->
    <div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">
        <label class="toggle-row">
            <div class="toggle"><input type="checkbox" id="fPublished" checked><div class="toggle-slider"></div></div>
            Publish immediately
        </label>
        <label class="toggle-row">
            <div class="toggle"><input type="checkbox" id="fFeatured"><div class="toggle-slider"></div></div>
            Feature this content
        </label>
    </div>

    <button class="btn-submit" id="modalSubmitBtn" onclick="submitContent()">
        <span class="material-icons" style="font-size:16px;vertical-align:middle;">save</span> Create Content
    </button>
</div>
</div>

<!-- MANAGE MODAL (playlist items / quiz questions) -->
<div class="modal-overlay" id="manageModal">
<div class="modal-box">
    <div class="modal-title">
        <span id="manageTitle">Manage Content</span>
        <button class="modal-close" onclick="closeModal('manageModal')"><span class="material-icons">close</span></button>
    </div>
    <div id="managePanelBody"></div>
</div>
</div>

<script>
const LEARN_API  = BASE_URL + 'ajax/learn.php';

// ── Quill instances ─────────────────────────────────────────────────
const toolbarFull = [
    [{ 'header': [2, 3, false] }],
    ['bold', 'italic', 'underline'],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    ['blockquote', 'code-block', 'link'],
    ['clean']
];
const toolbarShort = [['bold', 'italic'], [{ 'list': 'bullet' }], ['link'], ['clean']];

let qDesc, qBlog, qGuide, qArticle;

document.addEventListener('DOMContentLoaded', () => {
    qDesc    = new Quill('#quillDesc',    { modules: { toolbar: toolbarShort }, theme: 'snow', placeholder: 'Brief description…' });
    qBlog    = new Quill('#quillBlog',    { modules: { toolbar: toolbarFull  }, theme: 'snow', placeholder: 'Blog content…' });
    qGuide   = new Quill('#quillGuide',   { modules: { toolbar: toolbarFull  }, theme: 'snow', placeholder: 'Guide content…' });
    qArticle = new Quill('#quillArticle', { modules: { toolbar: toolbarFull  }, theme: 'snow', placeholder: 'Article body…' });

    // Sync thumbnail preview when URL is typed
    document.getElementById('fThumb').addEventListener('input', function() {
        const prev = document.getElementById('fThumbPreview');
        if (this.value) { prev.src = this.value; prev.style.display = 'block'; }
        else { prev.style.display = 'none'; }
    });
});

// ── Thumbnail file upload ───────────────────────────────────────────
async function uploadThumb(input) {
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('thumbnail', input.files[0]);
    try {
        const res  = await fetch(LEARN_API + '?action=upload_thumbnail', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById('fThumb').value = data.url;
            const prev = document.getElementById('fThumbPreview');
            prev.src = BASE_URL + data.url;
            prev.style.display = 'block';
        } else {
            alert(data.message || 'Upload failed');
        }
    } catch { alert('Upload error'); }
}

const TYPE_META  = {
    video:    { icon: 'play_circle',   color: '#e53935', emoji: '🎬' },
    playlist: { icon: 'playlist_play', color: '#e53935', emoji: '📺' },
    blog:     { icon: 'article',       color: '#1976d2', emoji: '📝' },
    guide:    { icon: 'calendar_month',color: '#0097a7', emoji: '📖' },
    article:  { icon: 'auto_stories',  color: '#7b1fa2', emoji: '📚' },
    webinar:  { icon: 'live_tv',       color: '#f57c00', emoji: '🎙️' },
    quiz:     { icon: 'quiz',          color: '#388e3c', emoji: '📋' },
};

let selectedType = 'playlist';
let editId       = null;
let qCounter     = 0;

// ── Load content table ──────────────────────────────────────────────
async function loadMyContent() {
    const wrap = document.getElementById('contentTableWrap');
    try {
        const res  = await fetch(LEARN_API + '?action=my_content');
        const data = await res.json();
        if (!data.success) { wrap.innerHTML = '<div class="ol-empty"><span class="material-icons">error</span><p>' + data.message + '</p></div>'; return; }

        if (!data.items.length) {
            wrap.innerHTML = `<div class="ol-empty">
                <span class="material-icons">school</span>
                <p>No content yet. Click <strong>Create New Content</strong> to get started.</p>
            </div>`;
            return;
        }

        wrap.innerHTML = `<table class="ol-table">
            <thead><tr>
                <th>Content</th><th>Type</th><th>Season</th>
                <th>Views</th><th>Likes</th><th>Completions</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>` + data.items.map(it => `
            <tr>
                <td>
                    <div class="content-thumb-cell">
                        <div class="content-emoji">${TYPE_META[it.type]?.emoji||'📄'}</div>
                        <div>
                            <div class="content-cell-title" title="${escHtml(it.title)}">${escHtml(it.title)}</div>
                            <div class="content-cell-desc">${escHtml(it.cat_name||'')} · ${it.difficulty}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="type-badge" style="background:${TYPE_META[it.type]?.color||'#557A46'}">
                        <span class="material-icons">${TYPE_META[it.type]?.icon||'article'}</span>
                        ${it.type}
                    </span>
                </td>
                <td>${it.season === 'all' ? 'All' : ucFirst(it.season)}</td>
                <td>${it.views||0}</td>
                <td>${it.like_count||0}</td>
                <td>${it.completions||0}</td>
                <td>
                    <span class="status-pill ${it.is_published==1?'published':'draft'}"
                          onclick="togglePublish(${it.id},this)">
                        ${it.is_published==1 ? '✅ Published' : '📝 Draft'}
                    </span>
                </td>
                <td>
                    <div class="tbl-actions">
                        <button class="tbl-btn view" onclick="window.open(BASE_URL+'?page=learn-view&id=${it.id}','_blank')" title="Preview">
                            <span class="material-icons">open_in_new</span>
                        </button>
                        ${['playlist','quiz'].includes(it.type) ? `<button class="tbl-btn manage" onclick="openManage(${it.id},'${it.type}','${escHtml(it.title)}')" title="Manage items">
                            <span class="material-icons">list</span>
                        </button>` : ''}
                        <button class="tbl-btn edit" onclick="openEdit(${it.id})" title="Edit">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="tbl-btn del" onclick="deleteContent(${it.id})" title="Delete">
                            <span class="material-icons">delete_outline</span>
                        </button>
                    </div>
                </td>
            </tr>`).join('') + `</tbody></table>`;
    } catch (e) {
        wrap.innerHTML = '<div class="ol-empty"><span class="material-icons">wifi_off</span><p>Failed to load content.</p></div>';
    }
}

// ── Type selector ───────────────────────────────────────────────────
function selectType(type, btn) {
    selectedType = type;
    document.querySelectorAll('.type-tab').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.querySelectorAll('.type-fields').forEach(el => el.style.display = 'none');
    const tf = document.getElementById('typeFields-' + type);
    if (tf) tf.style.display = 'block';
    // body field maps
    ['blog','guide','article'].forEach(t => {
        const el = document.getElementById('typeFields-' + t);
        if (el) el.style.display = (type === t) ? 'block' : 'none';
    });
}

// ── Modal open/close ────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function openCreateModal() {
    editId = null;
    resetForm();
    document.getElementById('modalHeading').textContent = 'Create New Content';
    document.getElementById('modalSubmitBtn').textContent = '💾 Create Content';
    openModal('createModal');
}

function resetForm() {
    ['fTitle','fTitleBn','fCropTags','fDuration','fThumb','fYtUrl','fWebUrl'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('fCat').value = '';
    document.getElementById('fSeason').value = 'all';
    document.getElementById('fDiff').value = 'beginner';
    document.getElementById('fPassScore').value = '70';
    document.getElementById('fPublished').checked = true;
    document.getElementById('fFeatured').checked = false;
    document.getElementById('quizQList').innerHTML = '';
    document.getElementById('fThumbPreview').style.display = 'none';
    qCounter = 0;
    // Clear Quill editors
    if (qDesc) qDesc.setContents([]);
    if (qBlog) qBlog.setContents([]);
    if (qGuide) qGuide.setContents([]);
    if (qArticle) qArticle.setContents([]);
    selectType('playlist', document.querySelector('[data-type="playlist"]'));
}

async function openEdit(id) {
    editId = id;
    try {
        const res  = await fetch(LEARN_API + '?action=get&id=' + id);
        const data = await res.json();
        if (!data.success) return alert('Failed to load content');
        const it   = data.item;

        resetForm();
        document.getElementById('modalHeading').textContent = 'Edit Content';
        document.getElementById('modalSubmitBtn').textContent = '💾 Save Changes';

        document.getElementById('fTitle').value       = it.title || '';
        document.getElementById('fTitleBn').value     = it.title_bn || '';
        document.getElementById('fDesc').value        = it.description || '';
        document.getElementById('fCropTags').value    = it.crop_tags || '';
        document.getElementById('fDuration').value    = it.duration_min || '';
        document.getElementById('fThumb').value       = it.thumbnail_url || '';
        document.getElementById('fCat').value         = it.category_id || '';
        document.getElementById('fSeason').value      = it.season || 'all';
        document.getElementById('fDiff').value        = it.difficulty || 'beginner';
        document.getElementById('fPublished').checked = it.is_published == 1;
        document.getElementById('fFeatured').checked  = it.is_featured == 1;

        // Type-specific
        const tabBtn = document.querySelector(`[data-type="${it.type}"]`);
        if (tabBtn) selectType(it.type, tabBtn);

        if (it.youtube_url && document.getElementById('fYtUrl')) document.getElementById('fYtUrl').value = it.youtube_url;
        if (it.webinar_url && document.getElementById('fWebUrl')) document.getElementById('fWebUrl').value = it.webinar_url;
        if (it.webinar_scheduled_at && document.getElementById('fWebDate')) document.getElementById('fWebDate').value = it.webinar_scheduled_at.slice(0,16);
        if (it.pass_score && document.getElementById('fPassScore')) document.getElementById('fPassScore').value = it.pass_score;

        // Load into Quill editors
        const quillMap = { blog: qBlog, guide: qGuide, article: qArticle };
        if (quillMap[it.type] && it.content_body) {
            quillMap[it.type].clipboard.dangerouslyPasteHTML(it.content_body);
        }
        if (it.description) qDesc.clipboard.dangerouslyPasteHTML(it.description);

        // Thumbnail preview
        if (it.thumbnail_url) {
            const prev = document.getElementById('fThumbPreview');
            prev.src = it.thumbnail_url;
            prev.style.display = 'block';
        }

        openModal('createModal');
    } catch {}
}

// ── Submit form ─────────────────────────────────────────────────────
async function submitContent() {
    const btn   = document.getElementById('modalSubmitBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Saving…';

    // Sync Quill editors to hidden textareas
    if (qDesc) document.getElementById('fDesc').value = qDesc.root.innerHTML === '<p><br></p>' ? '' : qDesc.root.innerHTML;
    if (qBlog) document.getElementById('fBodyBlog').value = qBlog.root.innerHTML === '<p><br></p>' ? '' : qBlog.root.innerHTML;
    if (qGuide) document.getElementById('fBodyGuide').value = qGuide.root.innerHTML === '<p><br></p>' ? '' : qGuide.root.innerHTML;
    if (qArticle) document.getElementById('fBodyArticle').value = qArticle.root.innerHTML === '<p><br></p>' ? '' : qArticle.root.innerHTML;

    const bodyMap = { blog:'fBodyBlog', guide:'fBodyGuide', article:'fBodyArticle', playlist:'', webinar:'', quiz:'', video:'' };
    const bodyKey = bodyMap[selectedType];
    const bodyVal = bodyKey ? (document.getElementById(bodyKey)?.value || '') : '';

    // Collect quiz questions if applicable
    let questions = [];
    if (selectedType === 'quiz') {
        document.querySelectorAll('.quiz-q-block').forEach(block => {
            const qText = block.querySelector('.qq-text')?.value?.trim();
            const expl  = block.querySelector('.qq-expl')?.value?.trim();
            if (!qText) return;
            const opts  = [];
            block.querySelectorAll('.quiz-opt-row').forEach((row, ri) => {
                const text  = row.querySelector('input[type=text]')?.value?.trim();
                const radio = row.querySelector('input[type=radio]');
                if (text) opts.push({ text, correct: radio && radio.checked ? 1 : 0 });
            });
            questions.push({ question: qText, explanation: expl, options: opts });
        });
    }

    const payload = {
        type:        selectedType,
        title:       document.getElementById('fTitle').value.trim(),
        title_bn:    document.getElementById('fTitleBn').value.trim(),
        description: document.getElementById('fDesc').value.trim(),
        category_id: document.getElementById('fCat').value || 0,
        season:      document.getElementById('fSeason').value,
        difficulty:  document.getElementById('fDiff').value,
        crop_tags:   document.getElementById('fCropTags').value.trim(),
        duration_min:document.getElementById('fDuration').value || 0,
        thumbnail_url:document.getElementById('fThumb').value.trim(),
        youtube_url:  document.getElementById('fYtUrl')?.value?.trim() || '',
        webinar_url:  document.getElementById('fWebUrl')?.value?.trim() || '',
        webinar_scheduled_at: document.getElementById('fWebDate')?.value || '',
        pass_score:   document.getElementById('fPassScore')?.value || 70,
        content_body: bodyVal,
        is_published: document.getElementById('fPublished').checked ? 1 : 0,
        is_featured:  document.getElementById('fFeatured').checked ? 1 : 0,
        questions,
    };

    if (!payload.title) { alert('Title is required.'); btn.disabled = false; btn.textContent = '💾 Create Content'; return; }

    const action = editId ? 'update' : 'create';
    if (editId) payload.id = editId;

    try {
        const res  = await fetch(LEARN_API + '?action=' + action, {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeModal('createModal');
            loadMyContent();
        } else {
            alert(data.message || 'Error saving content');
        }
    } catch {
        alert('Network error');
    }
    btn.disabled = false; btn.textContent = editId ? '💾 Save Changes' : '💾 Create Content';
}

// ── Delete ──────────────────────────────────────────────────────────
async function deleteContent(id) {
    if (!confirm('Delete this content permanently?')) return;
    const res  = await fetch(LEARN_API + '?action=delete', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) loadMyContent();
    else alert(data.message);
}

// ── Toggle publish ──────────────────────────────────────────────────
async function togglePublish(id, el) {
    const res  = await fetch(LEARN_API + '?action=toggle_publish', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
        el.textContent = data.is_published ? '✅ Published' : '📝 Draft';
        el.className = 'status-pill ' + (data.is_published ? 'published' : 'draft');
    }
}

// ── Manage playlist/quiz items ──────────────────────────────────────
async function openManage(id, type, title) {
    document.getElementById('manageTitle').textContent = 'Manage: ' + title;
    const body = document.getElementById('managePanelBody');
    body.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;">Loading…</div>';
    openModal('manageModal');

    const res  = await fetch(LEARN_API + '?action=get&id=' + id);
    const data = await res.json();
    if (!data.success) { body.innerHTML = '<p>Error loading content.</p>'; return; }

    if (type === 'playlist') {
        renderManagePlaylist(id, data.item.playlist_items || []);
    } else if (type === 'quiz') {
        renderManageQuiz(id, data.item.questions || []);
    }
}

function renderManagePlaylist(cid, items) {
    const body = document.getElementById('managePanelBody');
    const rows = items.map((vi,i) => `
        <div class="pl-item-row" id="plrow-${vi.id}">
            <div class="pl-item-num">${i+1}</div>
            <div class="pl-item-title">${escHtml(vi.title)} ${vi.duration_min ? '('+vi.duration_min+'m)' : ''}</div>
            <button class="tbl-btn del" onclick="deletePlaylistItem(${vi.id})" title="Remove">
                <span class="material-icons">delete_outline</span>
            </button>
        </div>`).join('') || '<div style="padding:14px;font-size:13px;color:#9ca3af;">No videos yet.</div>';

    body.innerHTML = `<div class="manage-section">
        <h3><span class="material-icons">playlist_play</span> Playlist Videos</h3>
        <div class="pl-items-list">${rows}</div>
        <div style="margin-top:16px;">
            <div class="form-grid" style="margin-bottom:10px;">
                <div class="form-group">
                    <label class="form-label">Video Title</label>
                    <input type="text" class="form-input" id="newViTitle" placeholder="e.g. Introduction to Boro Rice">
                </div>
                <div class="form-group">
                    <label class="form-label">YouTube URL</label>
                    <input type="url" class="form-input" id="newViUrl" placeholder="https://youtube.com/watch?v=...">
                </div>
                <div class="form-group">
                    <label class="form-label">Duration (min)</label>
                    <input type="number" class="form-input" id="newViDur" placeholder="10" min="1" style="max-width:120px;">
                </div>
            </div>
            <button class="btn-add-item" onclick="addPlaylistItem(${cid})">
                <span class="material-icons">add</span> Add Video
            </button>
        </div>
    </div>`;
}

async function addPlaylistItem(cid) {
    const title = document.getElementById('newViTitle').value.trim();
    const url   = document.getElementById('newViUrl').value.trim();
    const dur   = document.getElementById('newViDur').value;
    if (!title || !url) { alert('Title and URL required'); return; }
    const res  = await fetch(LEARN_API + '?action=add_playlist_item', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ content_id: cid, title, youtube_url: url, duration_min: dur || 0 })
    });
    const data = await res.json();
    if (data.success) openManage(cid, 'playlist', '');
}

async function deletePlaylistItem(id) {
    if (!confirm('Remove this video?')) return;
    const res  = await fetch(LEARN_API + '?action=delete_playlist_item', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id })
    });
    const row = document.getElementById('plrow-' + id);
    if (row) row.remove();
}

function renderManageQuiz(cid, questions) {
    const body = document.getElementById('managePanelBody');
    const qRows = questions.map(q => `
        <div class="quiz-q-row">
            <div class="quiz-q-title">
                📝 ${escHtml(q.question)}
                <button class="tbl-btn del" onclick="deleteQuestion(${q.id})" title="Delete question">
                    <span class="material-icons">delete_outline</span>
                </button>
            </div>
            <div style="font-size:12px;color:#6b7280;">
                ${(q.options||[]).map(o => `<div style="padding:3px 0;${o.is_correct?'color:#059669;font-weight:600;':''}">${o.is_correct?'✅':'○'} ${escHtml(o.option_text)}</div>`).join('')}
            </div>
        </div>`).join('') || '<div style="padding:14px;font-size:13px;color:#9ca3af;">No questions yet.</div>';

    body.innerHTML = `<div class="manage-section">
        <h3><span class="material-icons">quiz</span> Quiz Questions</h3>
        ${qRows}
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">
        <h3><span class="material-icons">add_circle_outline</span> Add Question</h3>
        <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">Question Text</label>
            <input type="text" class="form-input" id="mqText" placeholder="e.g. What is the best fertilizer for rice?">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">Explanation (shown after answering)</label>
            <input type="text" class="form-input" id="mqExpl" placeholder="Optional explanation">
        </div>
        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px;">Options (select the correct one)</div>
        <div id="mqOpts">
            ${[1,2,3,4].map(i => `
            <div class="quiz-opt-row">
                <input type="radio" name="mqCorrect" value="${i}">
                <label style="font-size:11px;color:#6b7280;white-space:nowrap;">Correct</label>
                <input type="text" placeholder="Option ${i}">
            </div>`).join('')}
        </div>
        <button class="btn-submit" style="max-width:200px;" onclick="addQuizQuestion(${cid})">+ Add Question</button>
    </div>`;
}

async function addQuizQuestion(cid) {
    const qText = document.getElementById('mqText').value.trim();
    const expl  = document.getElementById('mqExpl').value.trim();
    if (!qText) { alert('Question text required'); return; }

    const optRows = document.querySelectorAll('#mqOpts .quiz-opt-row');
    const opts    = [];
    const correct = document.querySelector('#mqOpts input[type=radio]:checked')?.value;
    optRows.forEach((row, i) => {
        const text = row.querySelector('input[type=text]')?.value?.trim();
        if (text) opts.push({ text, correct: correct == (i+1) ? 1 : 0 });
    });

    if (opts.length < 2) { alert('Add at least 2 options'); return; }
    if (!opts.some(o => o.correct)) { alert('Mark at least one option as correct'); return; }

    const res  = await fetch(LEARN_API + '?action=add_question', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ content_id: cid, question: qText, explanation: expl, options: opts })
    });
    const data = await res.json();
    if (data.success) openManage(cid, 'quiz', '');
    else alert(data.message);
}

async function deleteQuestion(id) {
    if (!confirm('Delete this question?')) return;
    const res  = await fetch(LEARN_API + '?action=delete_question', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id })
    });
    location.reload();
}

// ── Quiz builder in create modal ────────────────────────────────────
function addQuestion() {
    const qi = ++qCounter;
    const el = document.createElement('div');
    el.className = 'quiz-q-block quiz-q-row';
    el.id = 'qqb-' + qi;
    el.innerHTML = `
        <div class="quiz-q-title">
            Question ${qi}
            <button type="button" style="border:none;background:none;cursor:pointer;color:#ef4444;" onclick="document.getElementById('qqb-${qi}').remove()">✕</button>
        </div>
        <div class="form-group" style="margin-bottom:8px;">
            <input type="text" class="form-input qq-text" placeholder="Question text…">
        </div>
        <div class="form-group" style="margin-bottom:8px;">
            <input type="text" class="form-input qq-expl" placeholder="Explanation (optional)…">
        </div>
        <div style="font-size:11px;color:#6b7280;margin-bottom:6px;">Options — select correct answer:</div>
        ${[1,2,3,4].map(i => `
        <div class="quiz-opt-row">
            <input type="radio" name="correct-${qi}" value="${i}">
            <label style="font-size:11px;color:#6b7280;">Correct</label>
            <input type="text" placeholder="Option ${i}">
        </div>`).join('')}`;
    document.getElementById('quizQList').appendChild(el);
}

// ── Utility ──────────────────────────────────────────────────────────
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

// Close modal on overlay click
document.getElementById('createModal').addEventListener('click', e => { if (e.target.id === 'createModal') closeModal('createModal'); });
document.getElementById('manageModal').addEventListener('click', e => { if (e.target.id === 'manageModal') closeModal('manageModal'); });

// ── Init ──────────────────────────────────────────────────────────────
loadMyContent();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
