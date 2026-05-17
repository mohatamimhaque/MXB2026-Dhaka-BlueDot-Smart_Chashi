<?php
/**
 * Admin Reports — Pro Edition
 * Fixed report generation + advanced dashboard
 */
$currPage = "Reports";
require_once __DIR__ . '/../../config/config.php';

$db = new Database();

// Generated reports
try {
    $reports = $db->resultSet("
        SELECT r.*, CONCAT(u.first_name, ' ', COALESCE(u.last_name,'')) as generated_by_name
        FROM generated_reports r
        LEFT JOIN users u ON r.generated_by = u.user_id
        ORDER BY r.created_at DESC LIMIT 50
    ") ?? [];
} catch (Exception $e) { $reports = []; }

// Scheduled reports
try {
    $scheduledReports = $db->resultSet("
        SELECT sr.*, CONCAT(u.first_name,' ',COALESCE(u.last_name,'')) as creator_name
        FROM scheduled_reports sr
        LEFT JOIN users u ON sr.created_by = u.user_id
        ORDER BY sr.created_at DESC
    ") ?? [];
} catch (Exception $e) { $scheduledReports = []; }

// Stats
$totalReports    = count($reports);
$thisMonthCount  = count(array_filter($reports, fn($r) => date('Y-m', strtotime($r['created_at'])) === date('Y-m')));
$totalBytes      = array_sum(array_column($reports, 'file_size'));
$scheduledActive = count(array_filter($scheduledReports, fn($r) => $r['is_enabled']));

function fmtBytes($b, $p = 1) {
    if ($b <= 0) return '0 B';
    $u = ['B','KB','MB','GB'];
    $pow = min(floor(log($b)/log(1024)), 3);
    return round($b/pow(1024,$pow),$p).' '.$u[$pow];
}

require_once __DIR__ . '/../layouts/admin-header.php';
?>

<!-- ── Stats Row ─────────────────────────────────────────────────────────── -->
<div class="rpt-stats-row" id="statsRow">
    <div class="rpt-stat">
        <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8)">
            <span class="material-icons">description</span>
        </div>
        <div>
            <div class="rpt-stat-val" id="statTotal"><?php echo $totalReports; ?></div>
            <div class="rpt-stat-lbl">Total Reports</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399)">
            <span class="material-icons">calendar_month</span>
        </div>
        <div>
            <div class="rpt-stat-val" id="statMonth"><?php echo $thisMonthCount; ?></div>
            <div class="rpt-stat-lbl">This Month</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
            <span class="material-icons">storage</span>
        </div>
        <div>
            <div class="rpt-stat-val"><?php echo fmtBytes($totalBytes); ?></div>
            <div class="rpt-stat-lbl">Storage Used</div>
        </div>
    </div>
    <div class="rpt-stat">
        <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#3b82f6,#60a5fa)">
            <span class="material-icons">schedule</span>
        </div>
        <div>
            <div class="rpt-stat-val"><?php echo $scheduledActive; ?></div>
            <div class="rpt-stat-lbl">Active Schedules</div>
        </div>
    </div>
</div>

<!-- ── Page Header ───────────────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Generate, preview, and schedule system reports</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="refreshReportList()">
            <span class="material-icons">refresh</span> Refresh
        </button>
        <button class="btn btn-primary" onclick="showGenerateModal()">
            <span class="material-icons">add</span> Generate Report
        </button>
    </div>
</div>

<!-- ── Quick Report Templates ────────────────────────────────────────────── -->
<div class="rpt-section">
    <div class="rpt-section-header">
        <h3 class="section-title">Quick Reports</h3>
        <span class="section-hint">Click a card to configure and generate instantly</span>
    </div>
    <div class="templates-grid">
        <?php
        $templates = [
            ['type'=>'user_summary',     'icon'=>'people',        'label'=>'User Summary',      'desc'=>'Registrations, roles, and activity',         'grad'=>'#6366f1,#818cf8'],
            ['type'=>'security_audit',   'icon'=>'security',      'label'=>'Security Audit',    'desc'=>'Login attempts, blocked IPs, events',         'grad'=>'#ef4444,#f87171'],
            ['type'=>'activity_log',     'icon'=>'history',       'label'=>'Activity Log',      'desc'=>'All admin actions and system changes',         'grad'=>'#10b981,#34d399'],
            ['type'=>'content_analytics','icon'=>'analytics',     'label'=>'Content Analytics', 'desc'=>'Posts, products, and engagement metrics',      'grad'=>'#3b82f6,#60a5fa'],
            ['type'=>'system_health',    'icon'=>'monitor_heart', 'label'=>'System Health',     'desc'=>'Server metrics, errors, disk usage',           'grad'=>'#f59e0b,#fbbf24'],
            ['type'=>'financial',        'icon'=>'payments',      'label'=>'Financial',         'desc'=>'Marketplace orders and revenue data',           'grad'=>'#8b5cf6,#a78bfa'],
            ['type'=>'ai_usage',         'icon'=>'psychology',    'label'=>'AI Usage',          'desc'=>'Chashi Bhai API calls and response metrics',   'grad'=>'#ec4899,#f472b6'],
        ];
        foreach ($templates as $t): ?>
        <div class="template-card" onclick="quickGenerate('<?php echo $t['type']; ?>')" title="Generate <?php echo $t['label']; ?>">
            <div class="template-icon" style="background:linear-gradient(135deg,<?php echo $t['grad']; ?>)">
                <span class="material-icons"><?php echo $t['icon']; ?></span>
            </div>
            <div class="template-info">
                <h4><?php echo $t['label']; ?></h4>
                <p><?php echo $t['desc']; ?></p>
            </div>
            <span class="material-icons template-arrow">arrow_forward</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Generated Reports Table ───────────────────────────────────────────── -->
<div class="card" id="reportsCard">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">description</span>
            Generated Reports
        </h3>
        <div class="card-header-right">
            <div class="search-box">
                <span class="material-icons">search</span>
                <input type="text" id="reportSearch" placeholder="Search reports…" oninput="filterReports(this.value)">
            </div>
            <select id="typeFilter" class="filter-select" onchange="filterReports()">
                <option value="">All Types</option>
                <option value="user_summary">User Summary</option>
                <option value="security_audit">Security Audit</option>
                <option value="activity_log">Activity Log</option>
                <option value="content_analytics">Content Analytics</option>
                <option value="system_health">System Health</option>
                <option value="financial">Financial</option>
                <option value="ai_usage">AI Usage</option>
            </select>
        </div>
    </div>
    <div class="card-body no-padding">
        <table class="data-table" id="reportsTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                    <th>Report</th>
                    <th>Type</th>
                    <th>Format</th>
                    <th>Size</th>
                    <th>Generated By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reportsTableBody">
                <?php if (empty($reports)): ?>
                <tr><td colspan="8">
                    <div class="empty-state small">
                        <span class="material-icons">description</span>
                        <p>No reports generated yet</p>
                        <button class="btn btn-primary btn-sm" onclick="showGenerateModal()">Generate First Report</button>
                    </div>
                </td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                    <?php
                        $fmt = $r['format'] ?? 'csv';
                        $fmtIcon = match($fmt) { 'pdf' => 'picture_as_pdf', 'csv' => 'grid_on', 'xlsx','excel' => 'table_chart', default => 'description' };
                        $typeLabel = ucwords(str_replace('_',' ', $r['report_type']));
                    ?>
                    <tr data-report-id="<?php echo $r['report_id']; ?>" data-type="<?php echo $r['report_type']; ?>" data-name="<?php echo htmlspecialchars(strtolower($r['report_name'] ?? '')); ?>">
                        <td><input type="checkbox" class="row-check" value="<?php echo $r['report_id']; ?>"></td>
                        <td>
                            <div class="report-name">
                                <span class="material-icons rpt-fmt-icon"><?php echo $fmtIcon; ?></span>
                                <div>
                                    <div class="rpt-name-text"><?php echo htmlspecialchars($r['report_name'] ?? 'Report'); ?></div>
                                    <div class="rpt-date-range"><?php
                                        if (!empty($r['date_from']) && !empty($r['date_to']))
                                            echo date('M d', strtotime($r['date_from'])) . ' – ' . date('M d, Y', strtotime($r['date_to']));
                                    ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="type-badge"><?php echo $typeLabel; ?></span></td>
                        <td><span class="format-badge <?php echo $fmt; ?>"><?php echo strtoupper($fmt); ?></span></td>
                        <td class="size-cell"><?php echo fmtBytes($r['file_size'] ?? 0); ?></td>
                        <td class="meta-cell"><?php echo htmlspecialchars($r['generated_by_name'] ?? 'System'); ?></td>
                        <td class="date-cell"><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn" onclick="previewReport(<?php echo $r['report_id']; ?>)" title="Preview">
                                    <span class="material-icons">visibility</span>
                                </button>
                                <button class="action-btn" onclick="downloadReport(<?php echo $r['report_id']; ?>)" title="Download">
                                    <span class="material-icons">download</span>
                                </button>
                                <button class="action-btn danger" onclick="deleteReport(<?php echo $r['report_id']; ?>, this)" title="Delete">
                                    <span class="material-icons">delete_outline</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- Bulk actions bar -->
        <div class="bulk-bar" id="bulkBar" style="display:none">
            <span id="bulkCount">0 selected</span>
            <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
                <span class="material-icons">delete</span> Delete Selected
            </button>
            <button class="btn btn-secondary btn-sm" onclick="clearSelection()">Cancel</button>
        </div>
    </div>
</div>

<!-- ── Scheduled Reports ─────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">schedule</span> Scheduled Reports</h3>
        <button class="btn btn-sm btn-secondary" onclick="showScheduleModal()">
            <span class="material-icons">add</span> Schedule
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($scheduledReports)): ?>
        <div class="empty-state">
            <span class="material-icons">event</span>
            <p>No scheduled reports configured</p>
            <button class="btn btn-primary" onclick="showScheduleModal()">Schedule Your First Report</button>
        </div>
        <?php else: ?>
        <div class="scheduled-list">
            <?php foreach ($scheduledReports as $s): ?>
            <div class="scheduled-item">
                <div class="sched-icon-wrap">
                    <span class="material-icons">event_repeat</span>
                </div>
                <div class="scheduled-info">
                    <h4><?php echo htmlspecialchars($s['report_name'] ?? 'Unnamed'); ?></h4>
                    <span class="scheduled-meta">
                        <?php echo ucwords(str_replace('_', ' ', $s['report_type'])); ?> &bull;
                        <?php echo strtoupper($s['format'] ?? 'csv'); ?> &bull;
                        <?php echo htmlspecialchars($s['schedule_human'] ?? 'Custom'); ?>
                    </span>
                </div>
                <div class="scheduled-next">
                    <span class="material-icons">schedule</span>
                    <?php echo $s['next_send'] ? date('M d, H:i', strtotime($s['next_send'])) : 'Not scheduled'; ?>
                </div>
                <div class="scheduled-actions">
                    <label class="task-toggle">
                        <input type="checkbox" <?php echo $s['is_enabled'] ? 'checked' : ''; ?>
                               onchange="toggleSchedule(<?php echo $s['schedule_id']; ?>, this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                    <button class="action-btn" onclick="runScheduleNow(<?php echo $s['schedule_id']; ?>, '<?php echo $s['report_type']; ?>')" title="Run Now">
                        <span class="material-icons">play_arrow</span>
                    </button>
                    <button class="action-btn danger" onclick="deleteSchedule(<?php echo $s['schedule_id']; ?>, this)" title="Delete">
                        <span class="material-icons">delete_outline</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════ MODALS ══════════════════════════════════════ -->

<!-- Generate Modal -->
<div class="modal-overlay" id="generateModal" onclick="if(event.target===this)hideGenerateModal()">
<div class="modal-box generate-modal">
    <div class="modal-header">
        <h3><span class="material-icons">add_chart</span> Generate Report</h3>
        <button class="modal-close" onclick="hideGenerateModal()"><span class="material-icons">close</span></button>
    </div>
    <div class="modal-body">
        <form id="generateForm">
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <select id="reportType" name="report_type" class="form-input" required>
                    <option value="user_summary">👥 User Summary</option>
                    <option value="security_audit">🔒 Security Audit</option>
                    <option value="activity_log">📋 Activity Log</option>
                    <option value="content_analytics">📊 Content Analytics</option>
                    <option value="system_health">🖥️ System Health</option>
                    <option value="financial">💰 Financial</option>
                    <option value="ai_usage">🤖 AI Usage</option>
                </select>
            </div>

            <!-- Date presets -->
            <div class="form-group">
                <label class="form-label">Date Range</label>
                <div class="date-presets">
                    <button type="button" class="preset-btn" onclick="setPreset(7)">7d</button>
                    <button type="button" class="preset-btn active" onclick="setPreset(30)">30d</button>
                    <button type="button" class="preset-btn" onclick="setPreset(90)">90d</button>
                    <button type="button" class="preset-btn" onclick="setPreset(365)">1 year</button>
                    <button type="button" class="preset-btn" onclick="setPresetThisMonth()">This month</button>
                </div>
                <div class="form-row" style="margin-top:10px">
                    <div class="form-group" style="margin-bottom:0">
                        <input type="date" id="dateFrom" name="date_from" class="form-input"
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <input type="date" id="dateTo" name="date_to" class="form-input"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Format</label>
                <div class="format-options">
                    <label class="format-option">
                        <input type="radio" name="format" value="csv" checked>
                        <div class="format-box">
                            <span class="material-icons">grid_on</span><span>CSV</span>
                            <small>Excel compatible</small>
                        </div>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="format" value="xlsx">
                        <div class="format-box">
                            <span class="material-icons">table_chart</span><span>XLSX</span>
                            <small>Spreadsheet</small>
                        </div>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="format" value="pdf">
                        <div class="format-box">
                            <span class="material-icons">data_object</span><span>JSON</span>
                            <small>Raw data</small>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Report Name <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
                <input type="text" id="reportName" name="report_name" class="form-input"
                       placeholder="Auto-generated if empty" maxlength="100">
            </div>
        </form>

        <!-- Progress -->
        <div class="gen-progress" id="genProgress" style="display:none">
            <div class="gen-progress-bar"><div class="gen-progress-fill" id="genProgressFill"></div></div>
            <div class="gen-progress-steps">
                <span class="gen-step active" id="stepCollect">📊 Collecting data…</span>
                <span class="gen-step" id="stepWrite">💾 Writing file…</span>
                <span class="gen-step" id="stepSave">✅ Saving record…</span>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideGenerateModal()">Cancel</button>
        <button type="button" class="btn btn-primary" id="generateBtn" onclick="submitGenerate()">
            <span class="material-icons">description</span> Generate
        </button>
    </div>
</div>
</div>

<!-- Schedule Modal -->
<div class="modal-overlay" id="scheduleModal" onclick="if(event.target===this)hideScheduleModal()">
<div class="modal-box generate-modal">
    <div class="modal-header">
        <h3><span class="material-icons">event_repeat</span> Schedule Report</h3>
        <button class="modal-close" onclick="hideScheduleModal()"><span class="material-icons">close</span></button>
    </div>
    <div class="modal-body">
        <form id="scheduleForm">
            <div class="form-group">
                <label class="form-label">Report Name</label>
                <input type="text" name="report_name" class="form-input" placeholder="e.g. Weekly User Summary" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-input" required>
                        <option value="user_summary">User Summary</option>
                        <option value="security_audit">Security Audit</option>
                        <option value="activity_log">Activity Log</option>
                        <option value="content_analytics">Content Analytics</option>
                        <option value="system_health">System Health</option>
                        <option value="financial">Financial</option>
                        <option value="ai_usage">AI Usage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Format</label>
                    <select name="format" class="form-input">
                        <option value="csv">CSV</option>
                        <option value="xlsx">XLSX</option>
                        <option value="pdf">JSON</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Frequency</label>
                <select name="schedule_cron" class="form-input" id="schedCron" required>
                    <option value="0 8 * * *"    data-human="Daily at 8:00 AM">Daily</option>
                    <option value="0 8 * * 1"    data-human="Every Monday 8:00 AM">Weekly (Mon)</option>
                    <option value="0 8 1 * *"    data-human="1st of each month">Monthly</option>
                    <option value="0 8 1 1,4,7,10 *" data-human="Quarterly (1st Jan/Apr/Jul/Oct)">Quarterly</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Email Recipients <span style="color:var(--text-muted);font-weight:400">(comma separated)</span></label>
                <input type="text" name="recipients" class="form-input" placeholder="admin@example.com">
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="hideScheduleModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitSchedule()">
            <span class="material-icons">schedule</span> Schedule
        </button>
    </div>
</div>
</div>

<!-- Preview Modal -->
<div class="modal-overlay" id="previewModal" onclick="if(event.target===this)hidePreviewModal()">
<div class="modal-box preview-modal">
    <div class="modal-header">
        <h3 id="previewTitle"><span class="material-icons">visibility</span> Report Preview</h3>
        <div style="display:flex;gap:8px;align-items:center">
            <button class="btn btn-sm btn-secondary" id="previewDownloadBtn" onclick="">
                <span class="material-icons">download</span> Download
            </button>
            <button class="modal-close" onclick="hidePreviewModal()"><span class="material-icons">close</span></button>
        </div>
    </div>
    <div class="modal-body preview-body" id="previewBody">
        <div class="preview-loading"><span class="material-icons spin">refresh</span> Loading preview…</div>
    </div>
</div>
</div>

<style>
/* ── Base Overrides ──────────────────────────────────────────────────────── */
.page-header { display:flex; align-items:center; justify-content:space-between; gap:20px; margin-bottom:24px; }
.page-header-content { flex:1; }
.page-title { font-size:26px; font-weight:700; color:var(--text-primary); margin:0 0 4px; }
.page-subtitle { font-size:13px; color:var(--text-muted); margin:0; }
.page-actions { display:flex; gap:10px; }

/* ── Stats Row ──────────────────────────────────────────────────────────── */
.rpt-stats-row {
    display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;
}
.rpt-stat {
    display:flex; align-items:center; gap:14px;
    background:var(--bg-secondary); border:1px solid var(--border);
    border-radius:12px; padding:18px 20px;
}
.rpt-stat-icon {
    width:46px; height:46px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
}
.rpt-stat-icon .material-icons { font-size:22px; color:white; }
.rpt-stat-val { font-size:24px; font-weight:700; color:var(--text-primary); line-height:1; }
.rpt-stat-lbl { font-size:12px; color:var(--text-muted); margin-top:3px; }

/* ── Section Header ─────────────────────────────────────────────────────── */
.rpt-section { margin-bottom:24px; }
.rpt-section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.section-title { font-size:15px; font-weight:600; color:var(--text-primary); margin:0; }
.section-hint { font-size:12px; color:var(--text-muted); }

/* ── Templates Grid ─────────────────────────────────────────────────────── */
.templates-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.template-card {
    display:flex; align-items:center; gap:12px; padding:16px;
    background:var(--bg-secondary); border:1px solid var(--border);
    border-radius:10px; cursor:pointer; transition:all 0.2s;
}
.template-card:hover {
    border-color:var(--primary); transform:translateY(-2px);
    box-shadow:0 4px 16px rgba(99,102,241,0.18);
}
.template-icon {
    width:40px; height:40px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
}
.template-icon .material-icons { font-size:20px; color:white; }
.template-info { flex:1; min-width:0; }
.template-info h4 { font-size:13px; font-weight:600; color:var(--text-primary); margin:0 0 2px; white-space:nowrap; }
.template-info p  { font-size:11px; color:var(--text-muted); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.template-arrow { color:var(--text-muted); transition:transform 0.2s; font-size:18px !important; flex-shrink:0; }
.template-card:hover .template-arrow { transform:translateX(3px); color:var(--primary); }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px; }
.card-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; background:var(--bg-tertiary); border-bottom:1px solid var(--border); }
.card-title { display:flex; align-items:center; gap:8px; font-size:15px; font-weight:600; color:var(--text-primary); margin:0; }
.card-title .material-icons { font-size:18px; color:var(--primary); }
.card-header-right { display:flex; align-items:center; gap:10px; }
.card-body { padding:20px; }
.card-body.no-padding { padding:0; }

/* ── Search & Filter ────────────────────────────────────────────────────── */
.search-box {
    display:flex; align-items:center; gap:6px;
    background:var(--bg-secondary); border:1px solid var(--border);
    border-radius:7px; padding:5px 10px;
}
.search-box .material-icons { font-size:16px; color:var(--text-muted); }
.search-box input { background:none; border:none; outline:none; color:var(--text-primary); font-size:13px; width:180px; font-family:inherit; }
.filter-select {
    background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-secondary);
    font-size:12px; font-family:inherit; padding:6px 10px; border-radius:7px; cursor:pointer; outline:none;
}

/* ── Table ──────────────────────────────────────────────────────────────── */
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { padding:10px 14px; text-align:left; font-size:11px; font-weight:600; color:var(--text-muted); background:var(--bg-tertiary); border-bottom:1px solid var(--border); text-transform:uppercase; letter-spacing:0.04em; }
.data-table tbody tr { border-bottom:1px solid var(--border); transition:background 0.15s; }
.data-table tbody tr:hover { background:var(--bg-tertiary); }
.data-table tbody tr.hidden { display:none; }
.data-table tbody td { padding:13px 14px; font-size:13px; color:var(--text-secondary); }
.date-cell { font-size:11px; color:var(--text-muted); }
.size-cell { font-size:12px; color:var(--text-muted); }
.meta-cell { font-size:12px; }

/* ── Report Name ────────────────────────────────────────────────────────── */
.report-name { display:flex; align-items:center; gap:10px; }
.rpt-fmt-icon { font-size:20px !important; color:var(--primary); }
.rpt-name-text { font-size:13px; font-weight:500; color:var(--text-primary); }
.rpt-date-range { font-size:11px; color:var(--text-muted); margin-top:1px; }

/* ── Badges ─────────────────────────────────────────────────────────────── */
.type-badge { display:inline-block; padding:3px 8px; background:rgba(99,102,241,0.15); color:var(--primary); border-radius:5px; font-size:11px; font-weight:600; }
.format-badge { display:inline-block; padding:3px 7px; border-radius:4px; font-size:10px; font-weight:700; }
.format-badge.csv  { background:rgba(16,185,129,0.15); color:#10b981; }
.format-badge.xlsx { background:rgba(99,102,241,0.15); color:#6366f1; }
.format-badge.pdf,.format-badge.json { background:rgba(245,158,11,0.15); color:#f59e0b; }

/* ── Action Buttons ─────────────────────────────────────────────────────── */
.action-buttons { display:flex; gap:6px; }
.action-btn { width:30px; height:30px; border-radius:7px; background:var(--bg-tertiary); border:1px solid var(--border); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s; }
.action-btn:hover { background:var(--primary); color:white; border-color:var(--primary); }
.action-btn.danger:hover { background:#ef4444; border-color:#ef4444; }
.action-btn .material-icons { font-size:15px; }

/* ── Bulk Bar ───────────────────────────────────────────────────────────── */
.bulk-bar { display:flex; align-items:center; gap:12px; padding:12px 16px; background:rgba(99,102,241,0.08); border-top:1px solid var(--border); font-size:13px; }
.bulk-bar span { color:var(--text-primary); font-weight:500; }
.btn-danger { background:#ef4444; color:white; }
.btn-danger:hover { background:#dc2626; }

/* ── Empty State ────────────────────────────────────────────────────────── */
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:50px 20px; text-align:center; color:var(--text-muted); }
.empty-state .material-icons { font-size:44px; margin-bottom:12px; opacity:0.4; }
.empty-state p { font-size:14px; margin:0 0 14px; }
.empty-state.small { padding:24px; }
.empty-state.small .material-icons { font-size:30px; }

/* ── Scheduled List ─────────────────────────────────────────────────────── */
.scheduled-list { display:flex; flex-direction:column; gap:10px; }
.scheduled-item { display:flex; align-items:center; gap:16px; padding:14px 16px; background:var(--bg-tertiary); border-radius:9px; transition:all 0.15s; }
.scheduled-item:hover { border-left:3px solid var(--primary); padding-left:13px; }
.sched-icon-wrap { width:36px; height:36px; border-radius:8px; background:rgba(99,102,241,0.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sched-icon-wrap .material-icons { font-size:18px; color:var(--primary); }
.scheduled-info { flex:1; }
.scheduled-info h4 { font-size:13px; font-weight:600; color:var(--text-primary); margin:0 0 2px; }
.scheduled-meta { font-size:11px; color:var(--text-muted); }
.scheduled-next { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-secondary); white-space:nowrap; }
.scheduled-next .material-icons { font-size:15px; color:var(--primary); }
.scheduled-actions { display:flex; align-items:center; gap:10px; flex-shrink:0; }

/* ── Toggle ─────────────────────────────────────────────────────────────── */
.task-toggle { position:relative; width:38px; height:20px; display:inline-block; }
.task-toggle input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:var(--bg-secondary); border:1px solid var(--border); border-radius:20px; transition:0.25s; }
.toggle-slider::before { position:absolute; content:""; height:14px; width:14px; left:2px; bottom:2px; background:var(--text-muted); border-radius:50%; transition:0.25s; }
.task-toggle input:checked + .toggle-slider { background:var(--secondary,#10b981); border-color:var(--secondary,#10b981); }
.task-toggle input:checked + .toggle-slider::before { transform:translateX(18px); background:white; }

/* ── Modals ─────────────────────────────────────────────────────────────── */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; visibility:hidden; transition:all 0.25s; }
.modal-overlay.active { opacity:1; visibility:visible; }
.modal-box { background:var(--bg-secondary); border-radius:14px; box-shadow:0 24px 48px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; transform:translateY(-16px); transition:transform 0.25s; width:500px; max-width:92vw; }
.modal-overlay.active .modal-box { transform:translateY(0); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:18px 22px; background:var(--bg-tertiary); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1; }
.modal-header h3 { font-size:16px; font-weight:600; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:8px; }
.modal-header h3 .material-icons { font-size:20px; color:var(--primary); }
.modal-close { background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px; border-radius:4px; transition:all 0.15s; display:flex; align-items:center; }
.modal-close:hover { background:var(--bg-secondary); color:var(--text-primary); }
.modal-body { padding:22px; }
.modal-footer { display:flex; justify-content:flex-end; gap:10px; padding:14px 22px; background:var(--bg-tertiary); border-top:1px solid var(--border); }
.preview-modal { width:720px; max-width:92vw; }
.preview-body { padding:0; min-height:200px; }
.preview-loading { display:flex; align-items:center; justify-content:center; gap:8px; padding:48px; color:var(--text-muted); }

/* ── Form Elements ──────────────────────────────────────────────────────── */
.form-group { margin-bottom:18px; }
.form-label { display:block; font-size:12px; font-weight:600; color:var(--text-primary); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.04em; }
.form-input { width:100%; padding:9px 12px; background:var(--bg-tertiary); border:1px solid var(--border); border-radius:7px; font-size:13px; color:var(--text-primary); font-family:inherit; transition:all 0.15s; }
.form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.1); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

/* Date presets */
.date-presets { display:flex; gap:6px; flex-wrap:wrap; }
.preset-btn { padding:4px 10px; border-radius:5px; border:1px solid var(--border); background:var(--bg-tertiary); color:var(--text-secondary); font-size:11px; font-weight:600; cursor:pointer; transition:all 0.15s; font-family:inherit; }
.preset-btn.active, .preset-btn:hover { border-color:var(--primary); color:var(--primary); background:rgba(99,102,241,0.08); }

/* Format options */
.format-options { display:flex; gap:10px; }
.format-option { flex:1; cursor:pointer; }
.format-option input { display:none; }
.format-box { display:flex; flex-direction:column; align-items:center; gap:5px; padding:12px 8px; background:var(--bg-tertiary); border:2px solid var(--border); border-radius:8px; transition:all 0.15s; }
.format-option input:checked + .format-box { border-color:var(--primary); background:rgba(99,102,241,0.08); }
.format-box .material-icons { font-size:22px; color:var(--text-muted); }
.format-option input:checked + .format-box .material-icons { color:var(--primary); }
.format-box span:nth-child(2) { font-size:12px; font-weight:600; color:var(--text-secondary); }
.format-box small { font-size:10px; color:var(--text-muted); }
.format-option input:checked + .format-box span { color:var(--primary); }

/* Generate progress */
.gen-progress { margin-top:18px; }
.gen-progress-bar { height:4px; background:var(--border); border-radius:2px; overflow:hidden; margin-bottom:12px; }
.gen-progress-fill { height:100%; background:linear-gradient(90deg,var(--primary),#60a5fa); width:0%; transition:width 0.5s; border-radius:2px; }
.gen-progress-steps { display:flex; flex-direction:column; gap:6px; }
.gen-step { font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
.gen-step.active { color:var(--primary); font-weight:600; }
.gen-step.done { color:#10b981; }

/* Preview body */
.preview-section { padding:20px 22px; border-bottom:1px solid var(--border); }
.preview-section:last-child { border-bottom:none; }
.preview-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); margin-bottom:12px; }
.preview-kv-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.preview-kv { background:var(--bg-tertiary); border-radius:7px; padding:10px 14px; }
.preview-kv-label { font-size:11px; color:var(--text-muted); margin-bottom:2px; }
.preview-kv-val { font-size:18px; font-weight:700; color:var(--text-primary); }
.preview-table { width:100%; border-collapse:collapse; font-size:12px; }
.preview-table th { padding:7px 10px; background:var(--bg-tertiary); border-bottom:1px solid var(--border); text-align:left; font-weight:600; color:var(--text-muted); font-size:10px; text-transform:uppercase; }
.preview-table td { padding:7px 10px; border-bottom:1px solid var(--border); color:var(--text-secondary); }
.preview-table tr:last-child td { border-bottom:none; }
.preview-table-wrap { max-height:280px; overflow-y:auto; border:1px solid var(--border); border-radius:7px; }
.preview-more-note { font-size:11px; color:var(--text-muted); margin-top:6px; text-align:center; }

/* Buttons */
.btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 16px; border:none; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s; white-space:nowrap; font-family:inherit; }
.btn .material-icons { font-size:16px; }
.btn-sm { padding:6px 12px; font-size:12px; }
.btn-primary { background:var(--primary); color:white; }
.btn-primary:hover { filter:brightness(1.1); transform:translateY(-1px); box-shadow:0 4px 12px rgba(99,102,241,0.35); }
.btn-secondary { background:var(--bg-tertiary); color:var(--text-primary); border:1px solid var(--border); }
.btn-secondary:hover { border-color:var(--primary); color:var(--primary); }
.btn-primary:disabled { opacity:0.6; pointer-events:none; }

/* Spinner */
@keyframes spin { to { transform:rotate(360deg); } }
.spin { animation:spin 1s linear infinite; }

/* Responsive */
@media(max-width:1200px) { .templates-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:900px) {
    .rpt-stats-row { grid-template-columns:repeat(2,1fr); }
    .templates-grid { grid-template-columns:repeat(2,1fr); }
    .page-header { flex-direction:column; align-items:flex-start; }
}
@media(max-width:600px) {
    .rpt-stats-row { grid-template-columns:1fr; }
    .templates-grid { grid-template-columns:1fr; }
    .card-header { flex-direction:column; align-items:flex-start; gap:10px; }
    .card-header-right { width:100%; }
    .search-box input { width:100%; }
    .form-row { grid-template-columns:1fr; }
    .format-options { flex-wrap:wrap; }
}
</style>

<script>
const API = (typeof BASE_URL !== 'undefined' ? BASE_URL : '<?php echo $base_url; ?>') + 'admin-secure/ajax/reports.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function notify(msg, type='info') {
    if (typeof showToast === 'function') showToast(msg, type);
    else alert(msg);
}

function csrfToken() { return typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''; }

function postJSON(data) {
    const fd = new FormData();
    for (const [k,v] of Object.entries(data)) fd.append(k,v);
    fd.append('csrf_token', csrfToken());
    return fetch(API, {method:'POST', body:fd}).then(r=>r.json());
}

// ── Date Presets ─────────────────────────────────────────────────────────────
function setPreset(days) {
    document.querySelectorAll('.preset-btn').forEach(b=>b.classList.remove('active'));
    event.target.classList.add('active');
    const to   = new Date();
    const from = new Date(); from.setDate(from.getDate() - days);
    document.getElementById('dateTo').value   = to.toISOString().split('T')[0];
    document.getElementById('dateFrom').value = from.toISOString().split('T')[0];
}
function setPresetThisMonth() {
    document.querySelectorAll('.preset-btn').forEach(b=>b.classList.remove('active'));
    event.target.classList.add('active');
    const now   = new Date();
    const from  = new Date(now.getFullYear(), now.getMonth(), 1);
    document.getElementById('dateTo').value   = now.toISOString().split('T')[0];
    document.getElementById('dateFrom').value = from.toISOString().split('T')[0];
}

// ── Quick Generate from Template Card ────────────────────────────────────────
function quickGenerate(type) {
    document.getElementById('reportType').value = type;
    showGenerateModal();
}

// ── Generate Modal ───────────────────────────────────────────────────────────
function showGenerateModal() { document.getElementById('generateModal').classList.add('active'); }
function hideGenerateModal() {
    document.getElementById('generateModal').classList.remove('active');
    document.getElementById('genProgress').style.display='none';
    document.getElementById('generateBtn').disabled=false;
    document.getElementById('generateBtn').innerHTML='<span class="material-icons">description</span> Generate';
}

async function submitGenerate() {
    const form = document.getElementById('generateForm');
    const type = document.getElementById('reportType').value;
    if (!type) { notify('Select a report type','error'); return; }

    const btn = document.getElementById('generateBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons spin">refresh</span> Generating…';

    // Show progress
    const prog  = document.getElementById('genProgress');
    const fill  = document.getElementById('genProgressFill');
    const stepC = document.getElementById('stepCollect');
    const stepW = document.getElementById('stepWrite');
    const stepS = document.getElementById('stepSave');
    prog.style.display='block';
    stepC.className='gen-step active'; stepW.className='gen-step'; stepS.className='gen-step';
    fill.style.width='10%';

    // Animate steps
    const t1 = setTimeout(() => { fill.style.width='40%'; stepC.className='gen-step done'; stepW.className='gen-step active'; }, 600);
    const t2 = setTimeout(() => { fill.style.width='75%'; stepW.className='gen-step done'; stepS.className='gen-step active'; }, 1400);

    const fd = new FormData(form);
    fd.append('action','generate_report'); fd.append('csrf_token',csrfToken());

    try {
        const res  = await fetch(API, {method:'POST', body:fd});
        const data = await res.json();
        clearTimeout(t1); clearTimeout(t2);
        fill.style.width='100%';
        stepS.className='gen-step done';

        if (data.success) {
            notify(`Report generated: ${data.file_name} (${fmtBytes(data.file_size||0)})`, 'success');
            setTimeout(() => { hideGenerateModal(); location.reload(); }, 1200);
        } else {
            notify(data.message || 'Generation failed','error');
            btn.disabled=false;
            btn.innerHTML='<span class="material-icons">description</span> Generate';
        }
    } catch(e) {
        clearTimeout(t1); clearTimeout(t2);
        notify('Network error — please try again','error');
        btn.disabled=false;
        btn.innerHTML='<span class="material-icons">description</span> Generate';
    }
}

function fmtBytes(b) {
    if(b<=0)return'0 B'; const u=['B','KB','MB','GB']; const p=Math.min(Math.floor(Math.log(b)/Math.log(1024)),3);
    return (b/Math.pow(1024,p)).toFixed(1)+' '+u[p];
}

// ── Preview ───────────────────────────────────────────────────────────────────
async function previewReport(id) {
    document.getElementById('previewModal').classList.add('active');
    document.getElementById('previewBody').innerHTML='<div class="preview-loading"><span class="material-icons spin">refresh</span> Loading…</div>';
    document.getElementById('previewTitle').innerHTML='<span class="material-icons">visibility</span> Loading Report…';
    document.getElementById('previewDownloadBtn').onclick = () => downloadReport(id);

    try {
        const data = await postJSON({action:'preview_report', report_id:id});
        if (!data.success) { document.getElementById('previewBody').innerHTML=`<div class="preview-loading">Failed: ${data.message||'Unknown error'}</div>`; return; }

        const r = data.report || {};
        document.getElementById('previewTitle').innerHTML=`<span class="material-icons">visibility</span> ${r.report_name||'Report Preview'}`;

        let html = '';

        // Summary
        html += `<div class="preview-section">
            <div class="preview-section-title">Report Info</div>
            <div class="preview-kv-grid">
                <div class="preview-kv"><div class="preview-kv-label">Type</div><div style="font-size:14px;font-weight:600;color:var(--text-primary)">${(r.report_type||'').replace(/_/g,' ')}</div></div>
                <div class="preview-kv"><div class="preview-kv-label">Format</div><div style="font-size:14px;font-weight:600;color:var(--text-primary)">${(r.format||'').toUpperCase()}</div></div>
                <div class="preview-kv"><div class="preview-kv-label">Period</div><div style="font-size:13px;font-weight:600;color:var(--text-primary)">${r.date_from||'—'} → ${r.date_to||'—'}</div></div>
                <div class="preview-kv"><div class="preview-kv-label">Size</div><div style="font-size:14px;font-weight:600;color:var(--text-primary)">${fmtBytes(r.file_size||0)}</div></div>
            </div>
        </div>`;

        // Data section
        if (data.csv_preview) {
            const rows = data.csv_preview;
            html += buildCsvPreview(rows);
        } else if (data.data) {
            html += buildDataPreview(data.data, r.report_type);
        }

        document.getElementById('previewBody').innerHTML = html;
    } catch(e) {
        document.getElementById('previewBody').innerHTML='<div class="preview-loading">Failed to load preview</div>';
    }
}

function buildDataPreview(d, type) {
    let html = '<div class="preview-section"><div class="preview-section-title">Key Metrics</div><div class="preview-kv-grid">';
    const nums = {};
    for (const [k,v] of Object.entries(d)) {
        if (typeof v === 'number' && !k.startsWith('_')) nums[k] = v;
    }
    for (const [k,v] of Object.entries(nums)) {
        html += `<div class="preview-kv"><div class="preview-kv-label">${k.replace(/_/g,' ')}</div><div class="preview-kv-val">${typeof v==='number'&&v>999?v.toLocaleString():v}</div></div>`;
    }
    html += '</div></div>';

    // Table data
    const arrKeys = ['recent_users','activities','failed_attempts','blocked_ips','products','posts','by_provider','top_products','orders_by_status','error_log','users_by_role','activity_counts'];
    for (const k of arrKeys) {
        if (!d[k] || !d[k].length) continue;
        const rows = d[k].slice(0,15);
        const cols = Object.keys(rows[0]);
        html += `<div class="preview-section"><div class="preview-section-title">${k.replace(/_/g,' ')} (${d[k].length} records)</div>
        <div class="preview-table-wrap"><table class="preview-table">
        <thead><tr>${cols.map(c=>`<th>${c.replace(/_/g,' ')}</th>`).join('')}</tr></thead>
        <tbody>${rows.map(r=>`<tr>${cols.map(c=>`<td>${r[c]??''}</td>`).join('')}</tr>`).join('')}</tbody>
        </table></div>`;
        if (d[k].length>15) html+=`<div class="preview-more-note">Showing 15 of ${d[k].length} records. Download for full data.</div>`;
        html += '</div>';
        break; // show only the first array for conciseness
    }
    return html;
}

function buildCsvPreview(rows) {
    if (!rows.length) return '';
    const head = rows[0]; const body = rows.slice(1, 20);
    return `<div class="preview-section"><div class="preview-section-title">CSV Preview</div>
    <div class="preview-table-wrap"><table class="preview-table">
    <thead><tr>${head.map(c=>`<th>${c}</th>`).join('')}</tr></thead>
    <tbody>${body.map(r=>`<tr>${r.map(c=>`<td>${c}</td>`).join('')}</tr>`).join('')}</tbody>
    </table></div>
    <div class="preview-more-note">Showing first ${body.length} rows. Download CSV for full data.</div>
    </div>`;
}

function hidePreviewModal() { document.getElementById('previewModal').classList.remove('active'); }

// ── Download ─────────────────────────────────────────────────────────────────
function downloadReport(id) {
    const url = `${API}?action=download_report&report_id=${id}&csrf_token=${csrfToken()}`;
    const a = document.createElement('a'); a.href=url; a.download=''; document.body.appendChild(a); a.click(); a.remove();
    notify('Download started…','info');
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function deleteReport(id, btn) {
    if (!confirm('Delete this report? This cannot be undone.')) return;
    btn.disabled=true;
    const data = await postJSON({action:'delete_report', report_id:id});
    if (data.success) {
        const row = document.querySelector(`tr[data-report-id="${id}"]`);
        if (row) row.remove();
        notify('Report deleted','success');
    } else {
        notify(data.message||'Delete failed','error');
        btn.disabled=false;
    }
}

// ── Filter / Search ───────────────────────────────────────────────────────────
function filterReports(val) {
    const q    = (val || document.getElementById('reportSearch').value || '').toLowerCase();
    const type = document.getElementById('typeFilter').value;
    document.querySelectorAll('#reportsTableBody tr').forEach(tr => {
        if (!tr.dataset.reportId) return;
        const name = tr.dataset.name || '';
        const t    = tr.dataset.type || '';
        const show = (!q || name.includes(q)) && (!type || t === type);
        tr.classList.toggle('hidden', !show);
    });
}

// ── Bulk actions ──────────────────────────────────────────────────────────────
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    const bar = document.getElementById('bulkBar');
    bar.style.display = checked > 0 ? 'flex' : 'none';
    document.getElementById('bulkCount').textContent = `${checked} selected`;
}
function toggleSelectAll(cb) {
    document.querySelectorAll('.row-check').forEach(c=>c.checked=cb.checked);
    updateBulkBar();
}
document.addEventListener('change', e => { if(e.target.classList.contains('row-check')) updateBulkBar(); });

function clearSelection() {
    document.querySelectorAll('.row-check').forEach(c=>c.checked=false);
    document.getElementById('selectAll').checked=false;
    updateBulkBar();
}

async function bulkDelete() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(c=>c.value);
    if (!ids.length || !confirm(`Delete ${ids.length} report(s)?`)) return;
    for (const id of ids) {
        await postJSON({action:'delete_report', report_id:id});
        const row = document.querySelector(`tr[data-report-id="${id}"]`);
        if (row) row.remove();
    }
    notify(`${ids.length} report(s) deleted`,'success');
    clearSelection();
}

// ── Schedule Modal ────────────────────────────────────────────────────────────
function showScheduleModal() { document.getElementById('scheduleModal').classList.add('active'); }
function hideScheduleModal() { document.getElementById('scheduleModal').classList.remove('active'); }

async function submitSchedule() {
    const form = document.getElementById('scheduleForm');
    const sel  = form.querySelector('[name="schedule_cron"]');
    const human = sel.options[sel.selectedIndex].dataset.human || sel.value;
    const fd   = new FormData(form);
    fd.append('action','create_scheduled_report'); fd.append('csrf_token',csrfToken());
    fd.append('schedule_human', human);
    try {
        const res  = await fetch(API,{method:'POST',body:fd});
        const data = await res.json();
        if (data.success) { notify('Report scheduled!','success'); hideScheduleModal(); location.reload(); }
        else notify(data.message||'Failed','error');
    } catch(e) { notify('Network error','error'); }
}

async function toggleSchedule(id, enabled) {
    const data = await postJSON({action:'toggle_scheduled_report', schedule_id:id, is_enabled:enabled?1:0});
    if (!data.success) { notify(data.message||'Toggle failed','error'); location.reload(); }
    else notify(`Schedule ${enabled?'enabled':'disabled'}`,'success');
}

async function deleteSchedule(id, btn) {
    if (!confirm('Delete this scheduled report?')) return;
    btn.disabled=true;
    const data = await postJSON({action:'delete_scheduled_report', schedule_id:id});
    if (data.success) { btn.closest('.scheduled-item').remove(); notify('Deleted','success'); }
    else { notify(data.message||'Failed','error'); btn.disabled=false; }
}

function runScheduleNow(id, type) {
    document.getElementById('reportType').value = type;
    showGenerateModal();
}

// ── Refresh Report List via AJAX ──────────────────────────────────────────────
function refreshReportList() { location.reload(); }

// ── Close on ESC ────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key==='Escape') {
        hideGenerateModal(); hideScheduleModal(); hidePreviewModal();
    }
});
</script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
