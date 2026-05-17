<?php
/**
 * SmartChashi - Farmer Reports Page (AJAX Version)
 * For Agricultural Officers to view and analyze farmer disease reports
 */

if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer') {
    redirect('home');
}

include __DIR__ . '/../layouts/header.php';

$regions = ['Dhaka', 'Chittagong', 'Khulna', 'Rangpur', 'Sylhet', 'Barisal', 'Rajshahi', 'Mymensingh'];
$currentLang = $_SESSION['lang'] ?? 'en';
?>

<style>
.reports-page { padding: 1rem 0; }
.reports-hero {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
}
.reports-hero h1 { display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.75rem; }
.reports-hero p { margin: 0.5rem 0 0; opacity: 0.9; }
.hero-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.hero-actions .btn { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
.hero-actions .btn:hover { background: rgba(255,255,255,0.3); }

.stats-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-box { background: #ffffff; padding: 1.25rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 1rem; transition: transform 0.2s; cursor: pointer; }
.stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
.stat-box .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0; }
.stat-box .stat-info { display: flex; flex-direction: column; }
.stat-box .stat-info .stat-value { font-size: 1.5rem; font-weight: 700; color: #1f2937; line-height: 1.2; }
.stat-box .stat-info .stat-label { font-size: 0.85rem; color: #6b7280; }
.stat-total .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-pending .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-resolved .stat-icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-critical .stat-icon { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.stat-today .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-week .stat-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.chart-card { background: #ffffff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.chart-card h3 { margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; color: #1f2937; }
.chart-container { height: 250px; position: relative; }
.chart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #6b7280; }
.chart-empty .material-icons { font-size: 48px; opacity: 0.3; margin-bottom: 0.5rem; }

.filters-card { background: #ffffff; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.filters-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; cursor: pointer; }
.filters-header h3 { margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; color: #1f2937; }
.filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: end; }
.filters-grid .form-group { margin: 0; }
.filters-grid .form-group label { display: block; margin-bottom: 0.25rem; font-size: 0.85rem; color: #374151; font-weight: 500; }
.filter-actions { display: flex; gap: 0.5rem; align-items: end; }

.reports-card { background: var(--card-bg); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; }
.reports-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.reports-header h3 { margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.reports-header .count-badge { background: var(--primary-color); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; }
.reports-table { width: 100%; border-collapse: collapse; }
.reports-table th { background: var(--bg-color); padding: 1rem; text-align: left; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.reports-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.reports-table tr:hover { background: var(--bg-color); }
.farmer-cell { display: flex; align-items: center; gap: 0.75rem; }
.farmer-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
.farmer-info h4 { margin: 0; font-size: 0.95rem; }
.farmer-info small { color: var(--text-muted); }
.severity-badge { padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
.severity-high { background: #fee2e2; color: #dc2626; }
.severity-medium { background: #fef3c7; color: #d97706; }
.severity-low { background: #d1fae5; color: #059669; }
.status-badge { padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
.status-pending { background: #fef3c7; color: #d97706; }
.status-reviewed { background: #dbeafe; color: #2563eb; }
.status-resolved { background: #d1fae5; color: #059669; }
.status-detected { background: #fef3c7; color: #d97706; }
.status-treating { background: #dbeafe; color: #2563eb; }
.status-cured { background: #d1fae5; color: #059669; }
.status-failed { background: #fee2e2; color: #dc2626; }
.action-btns { display: flex; gap: 0.5rem; }
.action-btn { width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.action-btn .material-icons { font-size: 18px; }
.action-btn-view { background: #dbeafe; color: #2563eb; }
.action-btn-view:hover { background: #bfdbfe; }
.action-btn-respond { background: #d1fae5; color: #059669; }
.action-btn-respond:hover { background: #a7f3d0; }
.action-btn-call { background: #f3e8ff; color: #7c3aed; }
.action-btn-call:hover { background: #e9d5ff; }
.pagination-wrapper { padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem; }
.pagination-info { color: var(--text-muted); font-size: 0.9rem; }
.pagination { display: flex; gap: 0.25rem; }
.pagination a, .pagination span { padding: 0.5rem 0.75rem; border-radius: 8px; text-decoration: none; font-size: 0.9rem; cursor: pointer; }
.pagination a { background: var(--bg-color); color: var(--text-color); }
.pagination a:hover { background: var(--primary-color); color: white; }
.pagination .current { background: var(--primary-color); color: white; }
.empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.empty-state .material-icons { font-size: 64px; opacity: 0.3; margin-bottom: 1rem; }
.loading-overlay { display: flex; align-items: center; justify-content: center; padding: 3rem; }
.loading-overlay .material-icons { animation: spin 1s linear infinite; font-size: 48px; color: var(--primary-color); }
@keyframes spin { 100% { transform: rotate(360deg); } }

.report-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; opacity: 0; transition: opacity 0.3s ease; }
.report-modal.active { display: flex; opacity: 1; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.report-modal.active .report-modal-content { animation: slideUp 0.3s ease; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.report-modal-content { background: #ffffff; border-radius: 16px; width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.report-modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.report-modal-header h3 { margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.report-modal-body { padding: 1.5rem; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.info-item { padding: 0.75rem; background: var(--bg-color); border-radius: 8px; }
.info-item label { font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem; }
.info-item span { font-weight: 600; }

@media (max-width: 768px) {
    .charts-grid { grid-template-columns: 1fr; }
    .filters-grid { grid-template-columns: 1fr 1fr; }
    .info-grid { grid-template-columns: 1fr; }
}
</style>

<div class="reports-page">
    <!-- Hero Section -->
    <div class="reports-hero">
        <div>
            <h1><span class="material-icons">assessment</span> <?php echo __('farmer_reports'); ?></h1>
            <p><?php echo __('monitor_respond_reports'); ?></p>
        </div>
        <div class="hero-actions">
            <button class="btn" onclick="loadData()">
                <span class="material-icons">refresh</span> <?php echo __('refresh'); ?>
            </button>
        </div>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard" id="statsContainer">
        <div class="stat-box stat-total" onclick="filterByStatus('all')">
            <div class="stat-icon"><span class="material-icons">description</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statTotal">--</div>
                <div class="stat-label"><?php echo __('total_reports'); ?></div>
            </div>
        </div>
        <div class="stat-box stat-pending" onclick="filterByStatus('pending')">
            <div class="stat-icon"><span class="material-icons">pending</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statPending">--</div>
                <div class="stat-label"><?php echo __('pending_review'); ?></div>
            </div>
        </div>
        <div class="stat-box stat-resolved" onclick="filterByStatus('resolved')">
            <div class="stat-icon"><span class="material-icons">check_circle</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statResolved">--</div>
                <div class="stat-label"><?php echo __('resolved'); ?></div>
            </div>
        </div>
        <div class="stat-box stat-critical" onclick="filterBySeverity('high')">
            <div class="stat-icon"><span class="material-icons">warning</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statCritical">--</div>
                <div class="stat-label"><?php echo __('critical_issues'); ?></div>
            </div>
        </div>
        <div class="stat-box stat-today">
            <div class="stat-icon"><span class="material-icons">today</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statToday">--</div>
                <div class="stat-label"><?php echo __('today'); ?></div>
            </div>
        </div>
        <div class="stat-box stat-week">
            <div class="stat-icon"><span class="material-icons">date_range</span></div>
            <div class="stat-info">
                <div class="stat-value" id="statWeek">--</div>
                <div class="stat-label"><?php echo __('this_week'); ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3><span class="material-icons">pie_chart</span> <?php echo __('disease_distribution'); ?></h3>
            <div class="chart-container" id="diseaseChartContainer">
                <canvas id="diseaseChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3><span class="material-icons">show_chart</span> <?php echo __('reports_trend'); ?></h3>
            <div class="chart-container" id="trendChartContainer">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3><span class="material-icons">map</span> <?php echo __('regional_distribution'); ?></h3>
            <div class="chart-container" id="regionalChartContainer">
                <canvas id="regionalChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3><span class="material-icons">speed</span> <?php echo __('severity_breakdown'); ?></h3>
            <div class="chart-container" id="severityChartContainer">
                <canvas id="severityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-card">
        <div class="filters-header" onclick="toggleFilters()">
            <h3><span class="material-icons">filter_list</span> <?php echo __('advanced_filters'); ?></h3>
            <span class="material-icons" id="filterToggleIcon">expand_more</span>
        </div>
        <div class="filters-grid" id="filtersContent">
            <div class="form-group">
                <label><?php echo __('search'); ?></label>
                <input type="text" id="filterSearch" class="form-control" placeholder="<?php echo __('search_reports'); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('region'); ?></label>
                <select id="filterRegion" class="form-control">
                    <option value="all"><?php echo __('all_regions'); ?></option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo __('severity'); ?></label>
                <select id="filterSeverity" class="form-control">
                    <option value="all"><?php echo __('all_severities'); ?></option>
                    <option value="low"><?php echo __('low'); ?></option>
                    <option value="medium"><?php echo __('medium'); ?></option>
                    <option value="high"><?php echo __('high'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo __('status'); ?></label>
                <select id="filterStatus" class="form-control">
                    <option value="all"><?php echo __('all_status'); ?></option>
                    <option value="pending"><?php echo __('pending'); ?></option>
                    <option value="reviewed"><?php echo __('reviewed'); ?></option>
                    <option value="resolved"><?php echo __('resolved'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo __('date_from'); ?></label>
                <input type="date" id="filterDateFrom" class="form-control">
            </div>
            <div class="form-group">
                <label><?php echo __('date_to'); ?></label>
                <input type="date" id="filterDateTo" class="form-control">
            </div>
            <div class="filter-actions">
                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                    <span class="material-icons">search</span> <?php echo __('apply_filters'); ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                    <span class="material-icons">clear</span> <?php echo __('clear'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="reports-card">
        <div class="reports-header">
            <h3>
                <span class="material-icons">list_alt</span> <?php echo __('disease_reports'); ?>
                <span class="count-badge" id="reportCount">0</span>
            </h3>
        </div>
        <div id="reportsTableContainer">
            <div class="loading-overlay">
                <span class="material-icons">sync</span>
            </div>
        </div>
        <div class="pagination-wrapper" id="paginationContainer" style="display: none;"></div>
    </div>
</div>

<!-- Report Detail Modal -->
<div class="report-modal" id="reportModal">
    <div class="report-modal-content">
        <div class="report-modal-header">
            <h3><span class="material-icons">description</span> <?php echo __('report_details'); ?></h3>
            <button class="modal-close" onclick="closeModal('reportModal')">&times;</button>
        </div>
        <div class="report-modal-body" id="reportModalBody">
            <div class="loading-overlay"><span class="material-icons">sync</span></div>
        </div>
    </div>
</div>

<!-- Response Modal -->
<div class="report-modal" id="responseModal">
    <div class="report-modal-content">
        <div class="report-modal-header">
            <h3><span class="material-icons">reply</span> <?php echo __('respond_to_report'); ?></h3>
            <button class="modal-close" onclick="closeModal('responseModal')">&times;</button>
        </div>
        <div class="report-modal-body">
            <form id="responseForm" onsubmit="submitResponse(event)">
                <input type="hidden" id="responseReportId" name="report_id">
                <div class="form-group">
                    <label><?php echo __('update_status'); ?></label>
                    <select id="responseStatus" name="status" class="form-control" required>
                        <option value="reviewed"><?php echo __('reviewed'); ?></option>
                        <option value="resolved"><?php echo __('resolved'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo __('your_response'); ?> *</label>
                    <textarea id="responseMessage" name="message" class="form-control" rows="4" required placeholder="<?php echo __('enter_response'); ?>"></textarea>
                </div>
                <div class="form-group">
                    <label><?php echo __('recommended_action'); ?></label>
                    <textarea id="responseAction" name="action" class="form-control" rows="2" placeholder="<?php echo __('recommended_action_placeholder'); ?>"></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('responseModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="responseSubmitBtn">
                        <span class="material-icons">send</span> <?php echo __('send_response'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var baseUrl = <?php echo json_encode($base_url); ?>;
const jsT = {
    loading: <?php echo json_encode(__('loading')); ?>,
    no_reports_found: <?php echo json_encode(__('no_reports_found')); ?>,
    try_different_filters: <?php echo json_encode(__('try_different_filters')); ?>,
    farmer: <?php echo json_encode(__('farmer')); ?>,
    disease: <?php echo json_encode(__('disease')); ?>,
    crop: <?php echo json_encode(__('crop')); ?>,
    region: <?php echo json_encode(__('region')); ?>,
    severity: <?php echo json_encode(__('severity')); ?>,
    status: <?php echo json_encode(__('status')); ?>,
    date: <?php echo json_encode(__('date')); ?>,
    actions: <?php echo json_encode(__('actions')); ?>,
    view_details: <?php echo json_encode(__('view_details')); ?>,
    respond: <?php echo json_encode(__('respond')); ?>,
    call_farmer: <?php echo json_encode(__('call_farmer')); ?>,
    showing: <?php echo json_encode(__('showing')); ?>,
    of: <?php echo json_encode(__('of')); ?>,
    reports: <?php echo json_encode(__('reports')); ?>,
    response_sent: <?php echo json_encode(__('response_sent')); ?>,
    failed_send_response: <?php echo json_encode(__('failed_send_response')); ?>,
    no_data: <?php echo json_encode(__('no_data_available')); ?>,
    phone: <?php echo json_encode(__('phone')); ?>,
    symptoms: <?php echo json_encode(__('symptoms')); ?>,
    treatment: <?php echo json_encode(__('treatment')); ?>,
    detected_on: <?php echo json_encode(__('detected_on')); ?>,
    unknown: <?php echo json_encode(__('unknown')); ?>
};

let currentPage = 1;
let diseaseChart, trendChart, regionalChart, severityChart;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadData();
});

// Load all data via AJAX
function loadData() {
    loadStats();
    loadCharts();
    loadReports();
}

// Load Statistics
function loadStats() {
    fetch(baseUrl + 'ajax/farmer-reports-data.php?action=stats')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('statTotal').textContent = data.stats.total || 0;
            document.getElementById('statPending').textContent = data.stats.pending || 0;
            document.getElementById('statResolved').textContent = data.stats.resolved || 0;
            document.getElementById('statCritical').textContent = data.stats.critical || 0;
            document.getElementById('statToday').textContent = data.stats.today || 0;
            document.getElementById('statWeek').textContent = data.stats.this_week || 0;
        }
    })
    .catch(err => console.error('Stats error:', err));
}

// Load Charts
function loadCharts() {
    fetch(baseUrl + 'ajax/farmer-reports-data.php?action=charts')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            renderDiseaseChart(data.disease || []);
            renderTrendChart(data.trend || []);
            renderRegionalChart(data.regional || []);
            renderSeverityChart(data.severity || []);
        }
    })
    .catch(err => console.error('Charts error:', err));
}

function renderDiseaseChart(data) {
    const ctx = document.getElementById('diseaseChart');
    if (diseaseChart) diseaseChart.destroy();
    
    if (!data || data.length === 0) {
        ctx.parentElement.innerHTML = '<div class="chart-empty"><span class="material-icons">pie_chart</span><p>' + jsT.no_data + '</p></div>';
        return;
    }
    
    diseaseChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.disease_name || jsT.unknown),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#11998e', '#38ef7d', '#eb3349', '#fa709a']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });
}

function renderTrendChart(data) {
    const ctx = document.getElementById('trendChart');
    if (trendChart) trendChart.destroy();
    
    if (!data || data.length === 0) {
        ctx.parentElement.innerHTML = '<div class="chart-empty"><span class="material-icons">show_chart</span><p>' + jsT.no_data + '</p></div>';
        return;
    }
    
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => new Date(d.date).toLocaleDateString('<?php echo $currentLang === 'bn' ? 'bn-BD' : 'en-US'; ?>', {month: 'short', day: 'numeric'})),
            datasets: [{
                label: jsT.reports,
                data: data.map(d => d.count),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderRegionalChart(data) {
    const ctx = document.getElementById('regionalChart');
    if (regionalChart) regionalChart.destroy();
    
    if (!data || data.length === 0) {
        ctx.parentElement.innerHTML = '<div class="chart-empty"><span class="material-icons">map</span><p>' + jsT.no_data + '</p></div>';
        return;
    }
    
    regionalChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.region),
            datasets: [{
                label: jsT.reports,
                data: data.map(d => d.count),
                backgroundColor: '#4facfe'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderSeverityChart(data) {
    const ctx = document.getElementById('severityChart');
    if (severityChart) severityChart.destroy();
    
    if (!data || data.length === 0) {
        ctx.parentElement.innerHTML = '<div class="chart-empty"><span class="material-icons">speed</span><p>' + jsT.no_data + '</p></div>';
        return;
    }
    
    const colors = { high: '#ef4444', medium: '#f59e0b', low: '#10b981' };
    severityChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(d => (d.severity || 'low').charAt(0).toUpperCase() + (d.severity || 'low').slice(1)),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: data.map(d => colors[d.severity] || '#10b981')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

// Load Reports Table
function loadReports(page = 1) {
    currentPage = page;
    const container = document.getElementById('reportsTableContainer');
    container.innerHTML = '<div class="loading-overlay"><span class="material-icons">sync</span></div>';
    
    const params = new URLSearchParams({
        action: 'reports',
        page: page,
        search: document.getElementById('filterSearch').value,
        region: document.getElementById('filterRegion').value,
        severity: document.getElementById('filterSeverity').value,
        status: document.getElementById('filterStatus').value,
        date_from: document.getElementById('filterDateFrom').value,
        date_to: document.getElementById('filterDateTo').value
    });
    
    fetch(baseUrl + 'ajax/farmer-reports-data.php?' + params)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('reportCount').textContent = data.total;
            renderReportsTable(data.reports, data.total, data.page, data.totalPages);
        } else {
            container.innerHTML = '<div class="empty-state"><span class="material-icons">inbox</span><h3>' + jsT.no_reports_found + '</h3><p>' + jsT.try_different_filters + '</p></div>';
        }
    })
    .catch(err => {
        console.error('Reports error:', err);
        container.innerHTML = '<div class="empty-state"><span class="material-icons">error</span><p>Error loading reports</p></div>';
    });
}

function renderReportsTable(reports, total, page, totalPages) {
    const container = document.getElementById('reportsTableContainer');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = '<div class="empty-state"><span class="material-icons">inbox</span><h3>' + jsT.no_reports_found + '</h3><p>' + jsT.try_different_filters + '</p></div>';
        document.getElementById('paginationContainer').style.display = 'none';
        return;
    }
    
    let html = `<div class="table-responsive"><table class="reports-table">
        <thead><tr>
            <th>${jsT.farmer}</th>
            <th>${jsT.disease}</th>
            <th>${jsT.crop}</th>
            <th>${jsT.region}</th>
            <th>${jsT.severity}</th>
            <th>${jsT.status}</th>
            <th>${jsT.date}</th>
            <th>${jsT.actions}</th>
        </tr></thead><tbody>`;
    
    reports.forEach(r => {
        const initial = (r.first_name || 'U').charAt(0).toUpperCase();
        const severity = r.severity || 'low';
        const status = r.status || 'pending';
        
        html += `<tr>
            <td>
                <div class="farmer-cell">
                    <div class="farmer-avatar">${initial}</div>
                    <div class="farmer-info">
                        <h4>${escapeHtml(r.first_name || '')} ${escapeHtml(r.last_name || '')}</h4>
                        <small><span class="material-icons" style="font-size: 14px;">phone</span> ${escapeHtml(r.phone || 'N/A')}</small>
                    </div>
                </div>
            </td>
            <td><strong>${escapeHtml(r.disease_name || jsT.unknown)}</strong></td>
            <td>${escapeHtml(r.crop_name || 'N/A')}</td>
            <td><span class="material-icons" style="font-size: 14px;">location_on</span> ${escapeHtml(r.region || 'N/A')}</td>
            <td><span class="severity-badge severity-${severity}">${severity}</span></td>
            <td><span class="status-badge status-${status}">${status}</span></td>
            <td>${escapeHtml(r.formatted_date || '')}</td>
            <td>
                <div class="action-btns">
                    <button class="action-btn action-btn-view" onclick="viewReport(${r.detection_id})" title="${jsT.view_details}">
                        <span class="material-icons">visibility</span>
                    </button>
                    <button class="action-btn action-btn-respond" onclick="respondToReport(${r.detection_id})" title="${jsT.respond}">
                        <span class="material-icons">reply</span>
                    </button>
                    ${r.phone ? `<a href="tel:${r.phone}" class="action-btn action-btn-call" title="${jsT.call_farmer}"><span class="material-icons">phone</span></a>` : ''}
                </div>
            </td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    
    // Render pagination
    renderPagination(total, page, totalPages);
}

function renderPagination(total, page, totalPages) {
    const container = document.getElementById('paginationContainer');
    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    const perPage = 15;
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    
    let html = `<div class="pagination-info">${jsT.showing} ${start}-${end} ${jsT.of} ${total} ${jsT.reports}</div><div class="pagination">`;
    
    if (page > 1) html += `<a onclick="loadReports(${page - 1})"><span class="material-icons">chevron_left</span></a>`;
    
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
        if (i === page) {
            html += `<span class="current">${i}</span>`;
        } else {
            html += `<a onclick="loadReports(${i})">${i}</a>`;
        }
    }
    
    if (page < totalPages) html += `<a onclick="loadReports(${page + 1})"><span class="material-icons">chevron_right</span></a>`;
    
    html += '</div>';
    container.innerHTML = html;
}

// Filter Functions
function toggleFilters() {
    const content = document.getElementById('filtersContent');
    const icon = document.getElementById('filterToggleIcon');
    content.style.display = content.style.display === 'none' ? 'grid' : 'none';
    icon.textContent = content.style.display === 'none' ? 'expand_more' : 'expand_less';
}

function applyFilters() {
    loadReports(1);
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterRegion').value = 'all';
    document.getElementById('filterSeverity').value = 'all';
    document.getElementById('filterStatus').value = 'all';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    loadReports(1);
}

function filterByStatus(status) {
    document.getElementById('filterStatus').value = status;
    loadReports(1);
}

function filterBySeverity(severity) {
    document.getElementById('filterSeverity').value = severity;
    loadReports(1);
}

// View Report Details
function viewReport(id) {
    document.getElementById('reportModal').classList.add('active');
    document.getElementById('reportModalBody').innerHTML = '<div class="loading-overlay"><span class="material-icons">sync</span></div>';
    
    fetch(baseUrl + 'ajax/farmer-reports-data.php?action=detail&id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const r = data.report;
            document.getElementById('reportModalBody').innerHTML = `
                <div class="info-grid">
                    <div class="info-item"><label>${jsT.farmer}</label><span>${escapeHtml(r.first_name || '')} ${escapeHtml(r.last_name || '')}</span></div>
                    <div class="info-item"><label>${jsT.phone}</label><span>${escapeHtml(r.phone || 'N/A')}</span></div>
                    <div class="info-item"><label>${jsT.disease}</label><span>${escapeHtml(r.disease_name || jsT.unknown)}</span></div>
                    <div class="info-item"><label>${jsT.crop}</label><span>${escapeHtml(r.crop_name || 'N/A')}</span></div>
                    <div class="info-item"><label>${jsT.region}</label><span>${escapeHtml(r.region || 'N/A')}</span></div>
                    <div class="info-item"><label>${jsT.severity}</label><span class="severity-badge severity-${r.severity || 'low'}">${r.severity || 'low'}</span></div>
                    <div class="info-item"><label>${jsT.status}</label><span class="status-badge status-${r.status || 'pending'}">${r.status || 'pending'}</span></div>
                    <div class="info-item"><label>${jsT.detected_on}</label><span>${escapeHtml(r.formatted_date || '')}</span></div>
                </div>
                ${r.symptoms ? `<div class="form-group"><label>${jsT.symptoms}</label><p style="background: var(--bg-color); padding: 1rem; border-radius: 8px;">${escapeHtml(r.symptoms)}</p></div>` : ''}
                ${r.treatment ? `<div class="form-group"><label>${jsT.treatment}</label><p style="background: #d1fae5; padding: 1rem; border-radius: 8px; color: #059669;">${escapeHtml(r.treatment)}</p></div>` : ''}
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <button class="btn btn-primary" onclick="closeModal('reportModal'); respondToReport(${r.detection_id});">
                        <span class="material-icons">reply</span> ${jsT.respond}
                    </button>
                    ${r.phone ? `<a href="tel:${r.phone}" class="btn btn-secondary"><span class="material-icons">phone</span> ${jsT.call_farmer}</a>` : ''}
                </div>
            `;
        } else {
            document.getElementById('reportModalBody').innerHTML = '<p class="text-danger">Error loading report</p>';
        }
    })
    .catch(err => {
        document.getElementById('reportModalBody').innerHTML = '<p class="text-danger">Error loading report</p>';
    });
}

// Respond to Report
function respondToReport(id) {
    document.getElementById('responseReportId').value = id;
    document.getElementById('responseModal').classList.add('active');
}

function submitResponse(e) {
    e.preventDefault();
    
    const btn = document.getElementById('responseSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">sync</span> ' + jsT.loading;
    
    const formData = new FormData(document.getElementById('responseForm'));
    
    fetch(baseUrl + 'ajax/farmer-reports-data.php?action=respond', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(jsT.response_sent, 'success');
            closeModal('responseModal');
            document.getElementById('responseForm').reset();
            loadReports(currentPage);
            loadStats();
        } else {
            showNotification(data.message || jsT.failed_send_response, 'error');
        }
    })
    .catch(err => {
        showNotification(jsT.failed_send_response, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons">send</span> <?php echo __('send_response'); ?>';
    });
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function exportReports(format) {
    const params = new URLSearchParams({
        export: format,
        search: document.getElementById('filterSearch').value,
        region: document.getElementById('filterRegion').value,
        severity: document.getElementById('filterSeverity').value,
        status: document.getElementById('filterStatus').value,
        date_from: document.getElementById('filterDateFrom').value,
        date_to: document.getElementById('filterDateTo').value
    });
    window.open(baseUrl + 'ajax/export-reports.php?' + params, '_blank');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on outside click
document.querySelectorAll('.report-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.report-modal.active').forEach(m => m.classList.remove('active'));
    }
});

</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
