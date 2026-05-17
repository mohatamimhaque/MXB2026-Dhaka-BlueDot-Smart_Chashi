<?php
/**
 * SmartChashi - My Reports Page
 * For Farmers to view their submitted disease reports
 */

if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    redirect('home');
}

$currentLang = $_SESSION['lang'] ?? 'en';

include __DIR__ . '/../layouts/header.php';
?>

<style>
.my-reports-page { padding: 1rem 0; }

.page-hero {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
}
.page-hero h1 { margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; }
.page-hero p { margin: 0.5rem 0 0; opacity: 0.9; }
.page-hero .btn { background: white; color: var(--primary-color); }
.page-hero .btn:hover { background: #f0f0f0; }

.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-card {
    background: var(--card-bg); border-radius: 12px; padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;
}
.stat-card .icon { font-size: 2rem; margin-bottom: 0.5rem; }
.stat-card .value { font-size: 1.75rem; font-weight: 700; }
.stat-card .label { font-size: 0.85rem; color: #6b7280; }
.stat-card.pending .icon { color: #f59e0b; }
.stat-card.treating .icon { color: #3b82f6; }
.stat-card.cured .icon { color: #10b981; }
.stat-card.total .icon { color: #6366f1; }

.reports-container { background: var(--card-bg); border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.reports-header {
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
}
.reports-header h2 { margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem; }
.filter-tabs { display: flex; gap: 0.5rem; }
.filter-tab {
    padding: 0.5rem 1rem; border-radius: 20px; background: var(--bg-color);
    border: none; cursor: pointer; font-size: 0.9rem; transition: all 0.2s;
}
.filter-tab:hover { background: #e5e7eb; }
.filter-tab.active { background: var(--primary-color); color: white; }

.reports-list { padding: 0; }
.report-item {
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);
    display: grid; grid-template-columns: auto 1fr auto; gap: 1rem; align-items: center;
    cursor: pointer; transition: background 0.2s;
}
.report-item:last-child { border-bottom: none; }
.report-item:hover { background: var(--bg-color); }

.report-icon {
    width: 50px; height: 50px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
}
.report-icon.low { background: #d1fae5; color: #059669; }
.report-icon.medium { background: #fef3c7; color: #d97706; }
.report-icon.high { background: #fee2e2; color: #dc2626; }
.report-icon .material-icons { font-size: 24px; }

.report-info h3 { margin: 0 0 0.25rem; font-size: 1rem; }
.report-info p { margin: 0; font-size: 0.9rem; color: #6b7280; display: flex; flex-wrap: wrap; gap: 1rem; }
.report-info .meta-item { display: flex; align-items: center; gap: 0.25rem; }
.report-info .meta-item .material-icons { font-size: 16px; }

.report-status { text-align: right; }
.status-badge {
    padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500;
    display: inline-block;
}
.status-detected { background: #fef3c7; color: #d97706; }
.status-treating { background: #dbeafe; color: #2563eb; }
.status-cured { background: #d1fae5; color: #059669; }
.status-failed { background: #fee2e2; color: #dc2626; }
.report-date { font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem; }

.empty-state {
    text-align: center; padding: 3rem; color: #6b7280;
}
.empty-state .material-icons { font-size: 64px; opacity: 0.3; margin-bottom: 1rem; }
.empty-state h3 { margin: 0 0 0.5rem; }
.empty-state p { margin: 0 0 1.5rem; }

.loading { text-align: center; padding: 3rem; }
.loading .material-icons { font-size: 48px; color: var(--primary-color); animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.btn {
    padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 1rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.5rem; border: none; text-decoration: none;
}
.btn-primary { background: var(--primary-color); color: white; }
.btn-primary:hover { background: #15803d; }

/* Report Detail Modal */
.modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
.modal.active { display: flex; }
.modal-content { background: var(--card-bg); border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
.modal-body { padding: 1.5rem; }
.detail-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
.detail-row:last-child { border-bottom: none; }
.detail-label { color: #6b7280; font-size: 0.9rem; }
.detail-value { font-weight: 500; text-align: right; }
.detail-image { margin-top: 1rem; border-radius: 12px; overflow: hidden; }
.detail-image img { width: 100%; height: auto; display: block; }
.response-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color); }
.response-section h4 { margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary-color); }
.response-box { background: var(--bg-color); padding: 1rem; border-radius: 8px; }
.response-box .officer { font-weight: 600; margin-bottom: 0.5rem; }
.response-box .message { color: #374151; }
.response-box .date { font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem; }

@media (max-width: 768px) {
    .report-item { grid-template-columns: 1fr; }
    .report-status { text-align: left; display: flex; gap: 1rem; align-items: center; }
    .filter-tabs { flex-wrap: wrap; }
}
</style>

<div class="my-reports-page">
    <!-- Hero Section -->
    <div class="page-hero">
        <div>
            <h1><span class="material-icons">assignment</span> <?php echo __('my_reports'); ?></h1>
            <p><?php echo __('view_your_disease_reports'); ?></p>
        </div>
        <a href="<?php echo $base_url; ?>?page=create-report" class="btn" style="color:#15803d">
            <span class="material-icons">add</span>
            <?php echo __('create_report'); ?>
        </a>
    </div>

    <!-- Stats Row -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card total">
            <div class="icon"><span class="material-icons">description</span></div>
            <div class="value" id="statTotal">--</div>
            <div class="label"><?php echo __('total_reports'); ?></div>
        </div>
        <div class="stat-card pending">
            <div class="icon"><span class="material-icons">pending</span></div>
            <div class="value" id="statPending">--</div>
            <div class="label"><?php echo __('pending'); ?></div>
        </div>
        <div class="stat-card treating">
            <div class="icon"><span class="material-icons">medical_services</span></div>
            <div class="value" id="statTreating">--</div>
            <div class="label"><?php echo __('treating'); ?></div>
        </div>
        <div class="stat-card cured">
            <div class="icon"><span class="material-icons">check_circle</span></div>
            <div class="value" id="statCured">--</div>
            <div class="label"><?php echo __('resolved'); ?></div>
        </div>
    </div>

    <!-- Reports Container -->
    <div class="reports-container">
        <div class="reports-header">
            <h2><span class="material-icons">list_alt</span> <?php echo __('your_reports'); ?></h2>
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all"><?php echo __('all'); ?></button>
                <button class="filter-tab" data-filter="detected"><?php echo __('pending'); ?></button>
                <button class="filter-tab" data-filter="treating"><?php echo __('treating'); ?></button>
                <button class="filter-tab" data-filter="cured"><?php echo __('resolved'); ?></button>
            </div>
        </div>
        <div class="reports-list" id="reportsList">
            <div class="loading">
                <span class="material-icons">sync</span>
            </div>
        </div>
        
       
    </div>
</div>

<!-- Report Detail Modal -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><span class="material-icons">info</span> <?php echo __('report_details'); ?></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
var baseUrl = <?php echo json_encode($base_url); ?>;
const jsT = {
    no_reports: <?php echo json_encode(__('no_reports_found')); ?>,
    create_first: <?php echo json_encode(__('create_your_first_report')); ?>,
    create_report: <?php echo json_encode(__('create_report')); ?>,
    loading: <?php echo json_encode(__('loading')); ?>,
    crop: <?php echo json_encode(__('crop')); ?>,
    severity: <?php echo json_encode(__('severity')); ?>,
    status: <?php echo json_encode(__('status')); ?>,
    symptoms: <?php echo json_encode(__('symptoms')); ?>,
    submitted_on: <?php echo json_encode(__('submitted_on')); ?>,
    officer_response: <?php echo json_encode(__('officer_response')); ?>,
    no_response_yet: <?php echo json_encode(__('no_response_yet')); ?>,
    error_loading: <?php echo json_encode(__('error_loading')); ?>
};

let currentFilter = 'all';
let allReports = [];

document.addEventListener('DOMContentLoaded', function() {
    loadReports();
    
    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            renderReports();
        });
    });
    
    // Close modal on overlay click
    document.getElementById('detailModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
});

function loadReports() {
    fetch(baseUrl + 'ajax/get-my-reports.php')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            allReports = data.reports;
            updateStats(data.stats);
            renderReports();
        } else {
            showEmpty();
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showEmpty();
    });
}

function updateStats(stats) {
    document.getElementById('statTotal').textContent = parseInt(stats.total) || 0;
    document.getElementById('statPending').textContent = parseInt(stats.detected) || 0;
    document.getElementById('statTreating').textContent = parseInt(stats.treating) || 0;
    document.getElementById('statCured').textContent = (parseInt(stats.cured) || 0) + (parseInt(stats.failed) || 0);
}

function renderReports() {
    const container = document.getElementById('reportsList');
    let reports = allReports;
    
    if (currentFilter !== 'all') {
        if (currentFilter === 'cured') {
            reports = allReports.filter(r => r.status === 'cured' || r.status === 'failed');
        } else {
            reports = allReports.filter(r => r.status === currentFilter);
        }
    }
    
    if (reports.length === 0) {
        showEmpty();
        return;
    }
    
    let html = '';
    reports.forEach(r => {
        const severity = r.severity || 'low';
        const status = r.status || 'detected';
        html += `
            <div class="report-item" onclick="viewReport(${r.detection_id})">
                <div class="report-icon ${severity}">
                    <span class="material-icons">bug_report</span>
                </div>
                <div class="report-info">
                    <h3>${escapeHtml(r.disease_name || 'Disease Report')}</h3>
                    <p>
                        <span class="meta-item"><span class="material-icons">agriculture</span> ${escapeHtml(r.crop_name || 'N/A')}</span>
                        <span class="meta-item"><span class="material-icons">speed</span> ${severity}</span>
                    </p>
                </div>
                <div class="report-status">
                    <span class="status-badge status-${status}">${status}</span>
                    <div class="report-date">${escapeHtml(r.formatted_date || '')}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function showEmpty() {
    document.getElementById('reportsList').innerHTML = `
        <div class="empty-state">
            <span class="material-icons">inbox</span>
            <h3>${jsT.no_reports}</h3>
            <p>${jsT.create_first}</p>
            <a href="${baseUrl}?page=create-report" class="btn btn-primary" style="color:#15803d">
                <span class="material-icons">add</span> ${jsT.create_report}
            </a>
        </div>
    `;
}

function viewReport(id) {
    const modal = document.getElementById('detailModal');
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div class="loading"><span class="material-icons">sync</span></div>';
    modal.classList.add('active');
    
    fetch(baseUrl + 'ajax/get-my-report-detail.php?id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            renderDetail(data.report, data.response);
        } else {
            modalBody.innerHTML = '<p>' + jsT.error_loading + '</p>';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        modalBody.innerHTML = '<p>' + jsT.error_loading + '</p>';
    });
}

function renderDetail(report, response) {
    const severity = report.severity || 'low';
    const status = report.status || 'detected';
    
    let html = `
        <div class="detail-row">
            <span class="detail-label">${jsT.crop}</span>
            <span class="detail-value">${escapeHtml(report.crop_name || 'N/A')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">${jsT.severity}</span>
            <span class="detail-value"><span class="status-badge severity-${severity}">${severity}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">${jsT.status}</span>
            <span class="detail-value"><span class="status-badge status-${status}">${status}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">${jsT.submitted_on}</span>
            <span class="detail-value">${escapeHtml(report.formatted_date || '')}</span>
        </div>
        <div class="detail-row" style="flex-direction: column; gap: 0.5rem;">
            <span class="detail-label">${jsT.symptoms}</span>
            <p style="margin: 0; color: #374151;">${escapeHtml(report.symptoms || 'N/A')}</p>
        </div>
    `;
    
    if (report.image_url) {
        html += `
            <div class="detail-image">
                <img src="${baseUrl}public/${report.image_url}" alt="Disease Image">
            </div>
        `;
    }
    
    html += `
        <div class="response-section">
            <h4><span class="material-icons">reply</span> ${jsT.officer_response}</h4>
    `;
    
    if (response) {
        html += `
            <div class="response-box">
                <div class="officer">${escapeHtml(response.officer_name || 'Officer')}</div>
                <div class="message">${escapeHtml(response.message || '')}</div>
                <div class="date">${escapeHtml(response.formatted_date || '')}</div>
            </div>
        `;
    } else {
        html += `<p style="color: #9ca3af; font-style: italic;">${jsT.no_response_yet}</p>`;
    }
    
    html += '</div>';
    
    document.getElementById('modalBody').innerHTML = html;
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>

