<?php
/**
 * Admin — Learning Center Control
 * Full admin control over all learning content across all officers.
 */
$currPage = "Learning Center";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layouts/admin-header.php';

// Live stats (graceful — tables may not exist yet)
try {
    $lStats = [
        'total'       => (int)($db->single("SELECT COUNT(*) as c FROM learn_content")['c']                         ?? 0),
        'published'   => (int)($db->single("SELECT COUNT(*) as c FROM learn_content WHERE is_published=1")['c']    ?? 0),
        'featured'    => (int)($db->single("SELECT COUNT(*) as c FROM learn_content WHERE is_featured=1")['c']     ?? 0),
        'views'       => (int)($db->single("SELECT COALESCE(SUM(views),0) as c FROM learn_content")['c']          ?? 0),
        'completions' => (int)($db->single("SELECT COUNT(*) as c FROM learn_progress WHERE completed=1")['c']     ?? 0),
        'certs'       => (int)($db->single("SELECT COUNT(*) as c FROM learn_certificates")['c']                   ?? 0),
    ];
} catch (Exception $e) {
    $lStats = ['total'=>0,'published'=>0,'featured'=>0,'views'=>0,'completions'=>0,'certs'=>0];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-icons" style="vertical-align:middle;color:var(--primary)">school</span>
            Learning Center Control
        </h1>
        <p class="page-subtitle">Manage all educational content across all officers.</p>
    </div>
    <div class="page-actions">
        <a href="<?php echo $base_url; ?>?page=officer-learn" target="_blank" class="btn btn-secondary">
            <span class="material-icons">open_in_new</span> Officer Panel
        </a>
        <a href="<?php echo $base_url; ?>?page=learn" target="_blank" class="btn btn-primary">
            <span class="material-icons">visibility</span> View Public
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(6,1fr)">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(99,102,241,.15)"><span class="material-icons" style="color:#6366f1">article</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['total']); ?></span><span class="stat-label">Total Content</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.15)"><span class="material-icons" style="color:#10b981">public</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['published']); ?></span><span class="stat-label">Published</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.15)"><span class="material-icons" style="color:#f59e0b">star</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['featured']); ?></span><span class="stat-label">Featured</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,.15)"><span class="material-icons" style="color:#3b82f6">visibility</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['views']); ?></span><span class="stat-label">Total Views</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139,92,246,.15)"><span class="material-icons" style="color:#8b5cf6">check_circle</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['completions']); ?></span><span class="stat-label">Completions</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(236,72,153,.15)"><span class="material-icons" style="color:#ec4899">workspace_premium</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($lStats['certs']); ?></span><span class="stat-label">Certificates</span></div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by title..." oninput="debounceLoad()">
            </div>
            <select id="filterType" class="form-control" style="width:160px" onchange="loadContent()">
                <option value="all">All Types</option>
                <option value="video">Video</option>
                <option value="playlist">Playlist</option>
                <option value="blog">Blog</option>
                <option value="guide">Guide</option>
                <option value="article">Article</option>
                <option value="webinar">Webinar</option>
                <option value="quiz">Quiz</option>
            </select>
            <select id="filterStatus" class="form-control" style="width:140px" onchange="loadContent()">
                <option value="all">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
            <button class="btn btn-secondary" onclick="loadContent()">
                <span class="material-icons">refresh</span>
            </button>
        </div>
    </div>
</div>

<!-- Content Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">list</span> All Learning Content</h3>
        <span id="contentTotal" style="font-size:13px;color:var(--text-secondary)"></span>
    </div>
    <div class="card-body" style="overflow-x:auto;padding:0">
        <table style="width:100%;border-collapse:collapse" id="contentTable">
            <thead>
                <tr style="background:var(--bg-tertiary)">
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Title</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Type</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Author</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Views</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Completions</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Published</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Featured</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Actions</th>
                </tr>
            </thead>
            <tbody id="contentBody">
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary)">
                    <span class="material-icons spinning" style="font-size:32px">sync</span>
                </td></tr>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div style="padding:16px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border)">
        <div id="pageInfo" style="font-size:13px;color:var(--text-secondary)"></div>
        <div id="pagination" style="display:flex;gap:4px"></div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:none;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center">
        <span class="material-icons" style="font-size:48px;color:#ef4444;margin-bottom:16px">warning</span>
        <h3 style="margin-bottom:8px">Delete Content?</h3>
        <p style="color:var(--text-secondary);margin-bottom:24px" id="deleteModalTitle">This will permanently delete this content and all associated data.</p>
        <div style="display:flex;gap:12px;justify-content:center">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">
                <span class="material-icons">delete</span> Delete
            </button>
        </div>
    </div>
</div>

<style>
.btn-danger { background: #ef4444; color: #fff; border: none; }
.btn-danger:hover { background: #dc2626; }
.toggle-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer;
    border: none; transition: all .2s;
}
.toggle-pill.on  { background: rgba(16,185,129,.15); color: #059669; }
.toggle-pill.off { background: rgba(100,116,139,.12); color: var(--text-secondary); }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; border: none; cursor: pointer;
    background: transparent; color: var(--text-secondary); transition: all .15s;
}
.action-btn:hover { background: var(--bg-hover); color: var(--primary); }
.action-btn.danger:hover { background: rgba(239,68,68,.1); color: #ef4444; }
</style>

<script>
const BASE     = document.getElementById('baseUrl')?.value || '';
const LEARN_AJAX = BASE + 'ajax/learn.php';
const TYPE_COLORS = {video:'#6366f1',playlist:'#8b5cf6',blog:'#10b981',guide:'#059669',article:'#3b82f6',webinar:'#f59e0b',quiz:'#ec4899'};
let currentPage = 1, deleteTargetId = null, debounceTimer;

function debounceLoad() { clearTimeout(debounceTimer); debounceTimer = setTimeout(loadContent, 400); }

function loadContent(p = 1) {
    currentPage = p;
    const type   = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const q      = document.getElementById('searchInput').value.trim();
    const tbody  = document.getElementById('contentBody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px"><span class="material-icons spinning">sync</span></td></tr>';

    fetch(`${LEARN_AJAX}?action=admin_list&type=${encodeURIComponent(type)}&status=${encodeURIComponent(status)}&q=${encodeURIComponent(q)}&p=${p}`)
        .then(r => r.json()).then(d => {
            if (!d.success) { tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:24px">${d.message}</td></tr>`; return; }
            document.getElementById('contentTotal').textContent = `${d.total.toLocaleString()} items`;
            document.getElementById('pageInfo').textContent = `Page ${d.page} of ${d.pages} — ${d.total.toLocaleString()} total`;
            renderPagination(d.pages, d.page);

            if (!d.items.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary)">No content found</td></tr>';
                return;
            }
            tbody.innerHTML = d.items.map(item => `
                <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseenter="this.style.background='var(--bg-hover)'" onmouseleave="this.style.background=''">
                    <td style="padding:12px 16px">
                        <div style="font-weight:600;font-size:14px;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(item.title)}">${esc(item.title)}</div>
                        <div style="font-size:11px;color:var(--text-secondary)">${item.certs || 0} certs issued</div>
                    </td>
                    <td style="padding:12px 16px">
                        <span style="background:${TYPE_COLORS[item.type]||'#6366f1'}22;color:${TYPE_COLORS[item.type]||'#6366f1'};padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase">${item.type}</span>
                    </td>
                    <td style="padding:12px 16px">
                        <div style="font-size:13px">${esc((item.first_name||'') + ' ' + (item.last_name||''))}</div>
                        <div style="font-size:11px;color:var(--text-secondary)">${ucFirst(item.author_role||'')}</div>
                    </td>
                    <td style="padding:12px 16px;text-align:center;font-size:13px">${(item.views||0).toLocaleString()}</td>
                    <td style="padding:12px 16px;text-align:center;font-size:13px">${(item.completions||0).toLocaleString()}</td>
                    <td style="padding:12px 16px;text-align:center">
                        <button class="toggle-pill ${item.is_published ? 'on' : 'off'}" id="pub-${item.id}" onclick="togglePublish(${item.id})">
                            <span class="material-icons" style="font-size:14px">${item.is_published ? 'check_circle' : 'radio_button_unchecked'}</span>
                            ${item.is_published ? 'Live' : 'Draft'}
                        </button>
                    </td>
                    <td style="padding:12px 16px;text-align:center">
                        <button class="toggle-pill ${item.is_featured ? 'on' : 'off'}" id="feat-${item.id}" onclick="toggleFeatured(${item.id})">
                            <span class="material-icons" style="font-size:14px">${item.is_featured ? 'star' : 'star_border'}</span>
                            ${item.is_featured ? 'Yes' : 'No'}
                        </button>
                    </td>
                    <td style="padding:12px 16px;text-align:center">
                        <div style="display:flex;gap:4px;justify-content:center">
                            <a href="${BASE}?page=learn-view&id=${item.id}" target="_blank" class="action-btn" title="Preview">
                                <span class="material-icons" style="font-size:18px">visibility</span>
                            </a>
                            <button class="action-btn danger" onclick="openDeleteModal(${item.id}, '${esc(item.title)}')" title="Delete">
                                <span class="material-icons" style="font-size:18px">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }).catch(() => {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:24px">Failed to load content</td></tr>';
        });
}

function renderPagination(pages, current) {
    const el = document.getElementById('pagination');
    let html = '';
    for (let i = 1; i <= Math.min(pages, 10); i++) {
        html += `<button onclick="loadContent(${i})" style="padding:6px 12px;border-radius:8px;border:1px solid ${i===current?'var(--primary)':'var(--border)'};background:${i===current?'var(--primary)':'transparent'};color:${i===current?'#fff':'var(--text-primary)'};cursor:pointer;font-size:13px">${i}</button>`;
    }
    el.innerHTML = html;
}

function togglePublish(id) {
    fetch(LEARN_AJAX, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'toggle_publish', id})
    }).then(r => r.json()).then(d => {
        if (!d.success) { alert(d.message); return; }
        const btn = document.getElementById(`pub-${id}`);
        if (btn) {
            const on = d.is_published == 1;
            btn.className = `toggle-pill ${on ? 'on' : 'off'}`;
            btn.innerHTML = `<span class="material-icons" style="font-size:14px">${on ? 'check_circle' : 'radio_button_unchecked'}</span> ${on ? 'Live' : 'Draft'}`;
        }
    }).catch(() => alert('Error toggling publish status'));
}

function toggleFeatured(id) {
    fetch(LEARN_AJAX, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'toggle_featured', id})
    }).then(r => r.json()).then(d => {
        if (!d.success) { alert(d.message); return; }
        const btn = document.getElementById(`feat-${id}`);
        if (btn) {
            const on = d.is_featured == 1;
            btn.className = `toggle-pill ${on ? 'on' : 'off'}`;
            btn.innerHTML = `<span class="material-icons" style="font-size:14px">${on ? 'star' : 'star_border'}</span> ${on ? 'Yes' : 'No'}`;
        }
    }).catch(() => alert('Error toggling featured status'));
}

function openDeleteModal(id, title) {
    deleteTargetId = id;
    document.getElementById('deleteModalTitle').textContent = `Delete "${title}"? This cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; deleteTargetId = null; }

function confirmDelete() {
    if (!deleteTargetId) return;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting...';
    fetch(LEARN_AJAX, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: deleteTargetId})
    }).then(r => r.json()).then(d => {
        if (d.success) { closeDeleteModal(); loadContent(currentPage); }
        else { alert(d.message); }
    }).catch(() => alert('Delete failed')).finally(() => {
        btn.disabled = false; btn.innerHTML = '<span class="material-icons">delete</span> Delete';
    });
}

function esc(str) { const d = document.createElement('div'); d.appendChild(document.createTextNode(str||'')); return d.innerHTML; }
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

document.addEventListener('DOMContentLoaded', () => loadContent(1));
</script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
