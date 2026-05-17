<?php
/**
 * Admin — AI Management
 * Configure AI provider, test responses, view usage analytics.
 */
$currPage = "AI Management";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../providers/AIProviderFactory.php';
require_once __DIR__ . '/../layouts/admin-header.php';

// Load current AI settings from DB
$db = new Database();
$aiSettingsRaw = $db->resultSet(
    "SELECT setting_key, setting_value FROM admin_settings WHERE setting_group = 'ai' OR setting_key LIKE 'ai_%'"
);
$aiMap = [];
foreach ($aiSettingsRaw as $r) $aiMap[$r['setting_key']] = $r['setting_value'];

$activeProvider = $aiMap['ai_provider'] ?? 'groq';
$activeModel    = $aiMap['ai_model']    ?? '';
$temperature    = $aiMap['ai_temperature'] ?? '0.7';
$maxTokens      = $aiMap['ai_max_tokens']  ?? '1200';
$systemPrompt   = $aiMap['ai_system_prompt'] ?? '';

$providers = AIProviderFactory::providers();
$systemSettings = $db->single("SELECT * FROM system_settings WHERE id = 1") ?: [];
// Merge system_settings into aiMap for convenience
foreach (['disease_detection_api_url', 'agent_api_url'] as $k) {
    if (!isset($aiMap[$k]) && !empty($systemSettings[$k])) {
        $aiMap[$k] = $systemSettings[$k];
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-icons" style="vertical-align:middle;color:var(--primary)">smart_toy</span>
            AI Management
        </h1>
        <p class="page-subtitle">Configure AI provider, API keys, and monitor usage.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="loadStats()">
            <span class="material-icons">refresh</span> Refresh
        </button>
        <a href="<?php echo $base_url; ?>?page=agent" target="_blank" class="btn btn-primary">
            <span class="material-icons">chat</span> Open AI Chat
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" id="aiStatsGrid">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(85,122,70,.15)">
            <span class="material-icons" style="color:#557A46">bolt</span>
        </div>
        <div class="stat-content">
            <span class="stat-value" id="statCallsToday">—</span>
            <span class="stat-label">AI Calls Today</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(63,81,181,.15)">
            <span class="material-icons" style="color:#3f51b5">dns</span>
        </div>
        <div class="stat-content">
            <span class="stat-value" id="statCallsWeek">—</span>
            <span class="stat-label">Calls (7 days)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,152,0,.15)">
            <span class="material-icons" style="color:#ff9800">timer</span>
        </div>
        <div class="stat-content">
            <span class="stat-value" id="statAvgMs">—</span>
            <span class="stat-label">Avg Response (ms)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(244,67,54,.15)">
            <span class="material-icons" style="color:#f44336">error_outline</span>
        </div>
        <div class="stat-content">
            <span class="stat-value" id="statFails">—</span>
            <span class="stat-label">Failures Today</span>
        </div>
    </div>
</div>

<!-- Provider Overview -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">hub</span> Provider Overview</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <?php foreach ($providers as $key => $info): ?>
            <?php
            $hasKey  = !empty($aiMap['ai_api_key_' . $key]) || ($key === 'groq' && defined('GROQ_API_KEY') && GROQ_API_KEY);
            $isActive = ($key === $activeProvider);
            ?>
            <div style="flex:1;min-width:160px;padding:16px;border-radius:12px;border:2px solid <?php echo $isActive ? $info['color'] : '#e5e7eb'; ?>;background:<?php echo $isActive ? "rgba(0,0,0,.03)" : 'transparent'; ?>;position:relative">
                <?php if ($isActive): ?>
                <span style="position:absolute;top:8px;right:8px;font-size:11px;font-weight:700;background:<?php echo $info['color']; ?>;color:#fff;padding:2px 8px;border-radius:12px">ACTIVE</span>
                <?php endif; ?>
                <div style="font-weight:700;font-size:15px;margin-bottom:4px;color:<?php echo $info['color']; ?>"><?php echo $info['name']; ?></div>
                <div style="font-size:12px;color:var(--text-secondary)">
                    <?php if ($hasKey): ?>
                    <span class="material-icons" style="font-size:14px;vertical-align:middle;color:#4caf50">check_circle</span> API key configured
                    <?php else: ?>
                    <span class="material-icons" style="font-size:14px;vertical-align:middle;color:#f44336">cancel</span> No API key
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Config + Test Panel -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px" class="ai-config-grid">

    <!-- Left: Configuration -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="material-icons">tune</span> Configuration</h3>
        </div>
        <div class="card-body">
            <form id="aiConfigForm" onsubmit="saveAiConfig(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update_ai_settings">

                <div class="form-group">
                    <label class="form-label">AI Provider</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
                        <?php foreach ($providers as $key => $info): ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="ai_provider" value="<?php echo $key; ?>"
                                   <?php echo $activeProvider === $key ? 'checked' : ''; ?>
                                   onchange="onProviderChange('<?php echo $key; ?>')">
                            <span style="display:inline-block;padding:4px 14px;border-radius:20px;border:2px solid <?php echo $info['color']; ?>;font-size:13px;font-weight:600;color:<?php echo $info['color']; ?>;user-select:none">
                                <?php echo $info['name']; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Model</label>
                    <select name="ai_model" id="modelSelect" class="form-control">
                        <?php foreach ($providers as $key => $info): ?>
                        <?php foreach ($info['models'] as $m): ?>
                        <option value="<?php echo $m; ?>"
                                data-provider="<?php echo $key; ?>"
                                <?php echo ($m === $activeModel) ? 'selected' : ''; ?>>
                            <?php echo $m; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">API Key — <span id="providerLabel"><?php echo ucfirst($activeProvider); ?></span></label>
                    <div style="display:flex;gap:8px">
                        <input type="password" id="apiKeyInput" class="form-control" placeholder="Paste your API key here (leave blank to keep saved key)">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="loadApiKey()">
                            <span class="material-icons">visibility</span>
                        </button>
                    </div>
                    <small style="color:var(--text-secondary)">Stored in the database. Never shown in full.</small>
                </div>

                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group">
                        <label class="form-label">Temperature — <span id="tempVal"><?php echo $temperature; ?></span></label>
                        <input type="range" name="ai_temperature" min="0" max="2" step="0.1"
                               value="<?php echo htmlspecialchars($temperature); ?>"
                               oninput="document.getElementById('tempVal').textContent=this.value"
                               style="width:100%">
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary)">
                            <span>Precise (0)</span><span>Creative (2)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Tokens</label>
                        <input type="number" name="ai_max_tokens" class="form-control"
                               min="256" max="32000" value="<?php echo htmlspecialchars($maxTokens); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Custom System Prompt (optional override)</label>
                    <textarea name="ai_system_prompt" class="form-control" rows="4"
                              placeholder="Leave blank to use Chashi Bhai default prompt..."><?php echo htmlspecialchars($systemPrompt); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%" id="saveBtn">
                    <span class="material-icons">save</span> Save Configuration
                </button>
                <div id="saveMsg" style="margin-top:10px;display:none"></div>
            </form>
        </div>
    </div>

    <!-- Right: Test Panel -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="material-icons">science</span> Test AI</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;height:100%">
            <div class="form-group">
                <label class="form-label">Provider to Test</label>
                <select id="testProvider" class="form-control" onchange="testProviderChange()">
                    <?php foreach ($providers as $key => $info): ?>
                    <option value="<?php echo $key; ?>" <?php echo $key === $activeProvider ? 'selected' : ''; ?>><?php echo $info['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Model</label>
                <select id="testModel" class="form-control">
                    <?php foreach ($providers[$activeProvider]['models'] as $m): ?>
                    <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label">Test Prompt</label>
                <textarea id="testPrompt" class="form-control" rows="3"
                          placeholder="e.g. What is the best time to plant Boro rice?">Hello! Can you respond in one short sentence?</textarea>
            </div>
            <button class="btn btn-primary" onclick="runAiTest()" style="width:100%;margin-bottom:12px" id="testBtn">
                <span class="material-icons">send</span> Run Test
            </button>
            <div id="testResult" style="background:var(--bg-secondary,#f9fafb);border-radius:8px;padding:12px;min-height:80px;font-size:13px;display:none">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                    <span style="font-weight:600;color:var(--primary)">Response</span>
                    <span id="testMeta" style="color:var(--text-secondary);font-size:11px"></span>
                </div>
                <div id="testReply" style="line-height:1.6;white-space:pre-wrap"></div>
            </div>
        </div>
    </div>
</div>

<!-- Disease Detection API Config -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">biotech</span> Disease Detection API</h3>
        <span style="font-size:12px;color:var(--text-secondary);background:rgba(99,102,241,.1);padding:4px 10px;border-radius:20px">
            <span class="material-icons" style="font-size:13px;vertical-align:middle">lock</span> Backend logic unchanged
        </span>
    </div>
    <div class="card-body">
        <form id="diseaseApiForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_system_settings">
            <div class="form-group">
                <label class="form-label">
                    <span class="material-icons" style="font-size:16px;vertical-align:middle">link</span>
                    Disease Detection API Endpoint
                </label>
                <div style="display:flex;gap:8px">
                    <input type="url" name="disease_detection_api_url" id="diseaseApiUrl" class="form-control"
                        value="<?php echo htmlspecialchars($aiMap['disease_detection_api_url'] ?? ($systemSettings['disease_detection_api_url'] ?? '')); ?>"
                        placeholder="https://api.example.com/disease-detection" style="flex:1">
                    <button type="button" class="btn btn-secondary" onclick="pingDiseaseApi()">
                        <span class="material-icons">wifi_tethering</span> Test
                    </button>
                </div>
                <span class="form-hint" style="display:block;margin-top:6px">
                    External REST endpoint for plant disease analysis. The actual detection logic (camera capture, image upload, result display) remains unchanged.
                </span>
                <div id="diseaseApiStatus" style="display:none;margin-top:8px;padding:8px 12px;border-radius:8px;font-size:13px"></div>
            </div>
            <div class="form-group">
                <label class="form-label">AI Agent API Endpoint</label>
                <input type="url" name="agent_api_url" class="form-control"
                    value="<?php echo htmlspecialchars($aiMap['agent_api_url'] ?? ($systemSettings['agent_api_url'] ?? '')); ?>"
                    placeholder="https://api.example.com/agent">
                <span class="form-hint" style="display:block;margin-top:6px">Chashi Bhai chatbot endpoint (when using external proxy mode).</span>
            </div>
            <button type="submit" class="btn btn-primary">
                <span class="material-icons">save</span> Save API URLs
            </button>
            <span id="diseaseFormMsg" style="margin-left:12px;font-size:13px;display:none"></span>
        </form>
    </div>
</div>

<!-- Usage Chart -->
<div class="chart-card large" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">bar_chart</span> AI Usage Over Time</h3>
        <div class="card-actions">
            <select id="chartDays" class="chart-select" onchange="loadChart()">
                <option value="7">Last 7 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </div>
    </div>
    <div class="card-body" style="height:200px;position:relative">
        <canvas id="aiUsageChart"></canvas>
    </div>
</div>

<!-- Usage Logs Table -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title"><span class="material-icons">history</span> Recent AI Call Logs</h3>
        <div style="display:flex;align-items:center;gap:8px">
            <label style="font-size:13px;color:var(--text-secondary)">Rows:</label>
            <select id="logsPerPage" class="chart-select" onchange="goToPage(1)">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="loadLogs()">
                <span class="material-icons">refresh</span>
            </button>
        </div>
    </div>
    <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="logsTable" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Provider</th>
                    <th>Model</th>
                    <th>Response (ms)</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody id="logsBody">
                <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-secondary)">
                    <span class="material-icons spinning">sync</span> Loading...
                </td></tr>
            </tbody>
        </table>
        <div style="margin-top:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div style="font-size:13px;color:var(--text-secondary)" id="logsMeta"></div>
            <div id="logsPagination" style="display:flex;align-items:center;gap:4px"></div>
        </div>
    </div>
</div>

<style>
.ai-config-grid { grid-template-columns: 1fr 1fr; }
@media (max-width: 900px) { .ai-config-grid { grid-template-columns: 1fr; } }
.data-table { border-collapse: collapse; }
.data-table th, .data-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); font-size: 13px; }
.data-table th { background: var(--bg-secondary, #f9fafb); font-weight: 600; }
.badge-success { background: rgba(76,175,80,.15); color: #388e3c; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-danger  { background: rgba(244,67,54,.15); color: #d32f2f; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.pg-btn { min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);background:var(--bg-tertiary);color:var(--text-primary);border-radius:6px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background .15s,color .15s,border-color .15s;line-height:1; }
.pg-btn:hover:not(:disabled):not(.active) { background:var(--primary,#557A46);color:#fff;border-color:var(--primary,#557A46); }
.pg-btn.active { background:var(--primary,#557A46);color:#fff;border-color:var(--primary,#557A46);font-weight:700;cursor:default; }
.pg-btn:disabled { opacity:.35;cursor:not-allowed; }
.pg-btn .material-icons { font-size:18px;line-height:1; }
</style>

<script>
const PROVIDERS = <?php echo json_encode($providers); ?>;
const CSRF = document.getElementById('csrfToken')?.value || '';
const BASE = document.getElementById('baseUrl')?.value || '';
let usageChart = null;

// ── Stats ──────────────────────────────────────────────────────────
function loadStats() {
    fetch(BASE + 'admin-secure/ajax/settings.php?action=get_ai_stats')
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            document.getElementById('statCallsToday').textContent = d.calls_today.toLocaleString();
            document.getElementById('statCallsWeek').textContent  = d.calls_week.toLocaleString();
            document.getElementById('statAvgMs').textContent       = d.avg_response_ms + ' ms';
            document.getElementById('statFails').textContent       = d.fails_today;
        }).catch(() => {});
}

// ── Chart ──────────────────────────────────────────────────────────
function loadChart() {
    const days = document.getElementById('chartDays').value;
    fetch(BASE + `admin-secure/ajax/settings.php?action=get_ai_chart&days=${days}`)
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            const ctx = document.getElementById('aiUsageChart').getContext('2d');
            if (usageChart) usageChart.destroy();
            usageChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            label: 'Successful Calls',
                            data: d.calls.map((v, i) => v - d.failures[i]),
                            backgroundColor: 'rgba(85,122,70,.7)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Failed Calls',
                            data: d.failures,
                            backgroundColor: 'rgba(244,67,54,.6)',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { position: 'top' } }
                }
            });
        }).catch(() => {});
}

// ── Logs with pagination ──────────────────────────────────────────
let logsCurrentPage = 1;

function goToPage(page) {
    logsCurrentPage = page;
    loadLogs();
}

function loadLogs() {
    const tbody  = document.getElementById('logsBody');
    const limit  = parseInt(document.getElementById('logsPerPage').value, 10);
    const offset = (logsCurrentPage - 1) * limit;

    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-secondary)"><span class="material-icons spinning">sync</span> Loading...</td></tr>';
    document.getElementById('logsPagination').innerHTML = '';

    fetch(BASE + `admin-secure/ajax/settings.php?action=get_ai_logs&limit=${limit}&offset=${offset}`)
        .then(r => r.json()).then(d => {
            if (!d.success) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red">${d.message}</td></tr>`;
                return;
            }
            if (!d.logs.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-secondary)">No logs yet. AI usage will appear here.</td></tr>';
                document.getElementById('logsMeta').textContent = '';
                return;
            }

            tbody.innerHTML = d.logs.map((l, i) => `
                <tr>
                    <td style="color:var(--text-secondary)">${offset + i + 1}</td>
                    <td>${l.user_name?.trim() || '<em style="color:var(--text-secondary)">Guest</em>'}</td>
                    <td><strong style="color:${providerColor(l.provider)}">${ucFirst(l.provider)}</strong></td>
                    <td style="font-size:11px;color:var(--text-secondary)">${l.model || '—'}</td>
                    <td>${l.response_time_ms} ms</td>
                    <td>${l.success == 1 ? '<span class="badge-success">Success</span>' : '<span class="badge-danger">Failed</span>'}</td>
                    <td style="font-size:11px;color:var(--text-secondary)">${formatDate(l.created_at)}</td>
                </tr>
            `).join('');

            const total     = parseInt(d.total, 10);
            const totalPages = Math.ceil(total / limit);
            const from      = offset + 1;
            const to        = Math.min(offset + d.logs.length, total);
            document.getElementById('logsMeta').textContent =
                `Showing ${from.toLocaleString()}–${to.toLocaleString()} of ${total.toLocaleString()} records`;

            renderPagination(totalPages);
        }).catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red">Failed to load logs</td></tr>';
        });
}

function renderPagination(totalPages) {
    const pg  = document.getElementById('logsPagination');
    if (totalPages <= 1) { pg.innerHTML = ''; return; }

    const cur = logsCurrentPage;
    let html  = '';

    // Prev button
    html += `<button class="pg-btn" onclick="goToPage(${cur - 1})" ${cur === 1 ? 'disabled' : ''}>
                 <span class="material-icons" style="font-size:16px">chevron_left</span>
             </button>`;

    // Page number buttons (show up to 7 pages with ellipsis)
    const pages = getPaginationRange(cur, totalPages);
    pages.forEach(p => {
        if (p === '…') {
            html += `<span style="padding:0 4px;color:var(--text-secondary)">…</span>`;
        } else {
            html += `<button class="pg-btn ${p === cur ? 'active' : ''}" onclick="goToPage(${p})">${p}</button>`;
        }
    });

    // Next button
    html += `<button class="pg-btn" onclick="goToPage(${cur + 1})" ${cur === totalPages ? 'disabled' : ''}>
                 <span class="material-icons" style="font-size:16px">chevron_right</span>
             </button>`;

    pg.innerHTML = html;
}

function getPaginationRange(cur, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (cur <= 4)  return [1, 2, 3, 4, 5, '…', total];
    if (cur >= total - 3) return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
    return [1, '…', cur - 1, cur, cur + 1, '…', total];
}

function providerColor(p) {
    return { groq: '#f55036', openai: '#10a37f', gemini: '#4285f4', claude: '#b07040', deepseek: '#2c6fad', openrouter: '#6c5ce7' }[p] || '#555';
}
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function formatDate(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ── Provider selector change ───────────────────────────────────────
function onProviderChange(key) {
    const sel = document.getElementById('modelSelect');
    // Show only matching models
    Array.from(sel.options).forEach(o => {
        o.style.display = o.dataset.provider === key ? '' : 'none';
    });
    // Select first visible
    const first = Array.from(sel.options).find(o => o.dataset.provider === key);
    if (first) sel.value = first.value;
    document.getElementById('providerLabel').textContent = ucFirst(key);
}

function testProviderChange() {
    const key = document.getElementById('testProvider').value;
    const sel = document.getElementById('testModel');
    sel.innerHTML = (PROVIDERS[key]?.models || []).map(m => `<option value="${m}">${m}</option>`).join('');
}

// ── Load API key ───────────────────────────────────────────────────
function loadApiKey() {
    const provider = document.querySelector('input[name="ai_provider"]:checked')?.value || 'groq';
    fetch(BASE + 'admin-secure/ajax/settings.php?action=get_ai_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=${encodeURIComponent(CSRF)}&provider=${provider}`
    }).then(r => r.json()).then(d => {
        if (d.success && d.key) {
            const field = document.getElementById('apiKeyInput');
            field.type = 'text';
            field.value = d.key.substring(0, 6) + '••••••••' + d.key.slice(-4);
            setTimeout(() => { field.type = 'password'; field.value = ''; }, 5000);
        } else {
            alert('No API key saved for this provider.');
        }
    });
}

// ── Save config ────────────────────────────────────────────────────
function saveAiConfig(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = document.getElementById('saveBtn');
    const msg  = document.getElementById('saveMsg');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons spinning">sync</span> Saving...';

    const data = new FormData(form);
    // Inject API key from separate field
    const key = document.getElementById('apiKeyInput').value.trim();
    if (key && !key.includes('••')) data.set('ai_api_key', key);

    fetch(BASE + 'admin-secure/ajax/settings.php', { method: 'POST', body: data })
        .then(r => r.json()).then(d => {
            msg.style.display = 'block';
            msg.style.padding = '10px';
            msg.style.borderRadius = '8px';
            if (d.success) {
                msg.style.background = 'rgba(76,175,80,.12)';
                msg.style.color = '#388e3c';
                msg.innerHTML = '<span class="material-icons" style="vertical-align:middle;font-size:18px">check_circle</span> ' + d.message;
                loadStats();
            } else {
                msg.style.background = 'rgba(244,67,54,.12)';
                msg.style.color = '#d32f2f';
                msg.innerHTML = '<span class="material-icons" style="vertical-align:middle;font-size:18px">error</span> ' + d.message;
            }
        }).finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons">save</span> Save Configuration';
            setTimeout(() => msg.style.display = 'none', 5000);
        });
}

// ── Test AI ────────────────────────────────────────────────────────
function runAiTest() {
    const provider = document.getElementById('testProvider').value;
    const model    = document.getElementById('testModel').value;
    const prompt   = document.getElementById('testPrompt').value.trim();
    const btn      = document.getElementById('testBtn');
    const result   = document.getElementById('testResult');
    const reply    = document.getElementById('testReply');
    const meta     = document.getElementById('testMeta');

    if (!prompt) { alert('Enter a test prompt.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons spinning">sync</span> Testing...';
    result.style.display = 'none';

    const t0 = performance.now();
    fetch(BASE + 'admin-secure/ajax/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=${encodeURIComponent(CSRF)}&action=test_ai&provider=${encodeURIComponent(provider)}&model=${encodeURIComponent(model)}&prompt=${encodeURIComponent(prompt)}`
    }).then(r => r.json()).then(d => {
        const elapsed = Math.round(performance.now() - t0);
        result.style.display = 'block';
        if (d.success) {
            reply.textContent = d.reply;
            meta.textContent  = `${ucFirst(provider)} / ${model} — ${elapsed} ms`;
            result.style.borderLeft = '3px solid #4caf50';
        } else {
            reply.textContent = 'Error: ' + d.message;
            result.style.borderLeft = '3px solid #f44336';
            meta.textContent = `Failed — ${elapsed} ms`;
        }
    }).catch(err => {
        result.style.display = 'block';
        reply.textContent = 'Network error: ' + err.message;
        result.style.borderLeft = '3px solid #f44336';
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons">send</span> Run Test';
    });
}

// ── Disease API form ──────────────────────────────────────────────
document.getElementById('diseaseApiForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const msg = document.getElementById('diseaseFormMsg');
    try {
        const r = await fetch(BASE + 'admin-secure/ajax/settings.php', { method: 'POST', body: fd });
        const d = await r.json();
        msg.style.display = 'inline';
        msg.style.color = d.success ? '#388e3c' : '#d32f2f';
        msg.textContent = d.message || (d.success ? 'Saved!' : 'Failed');
        setTimeout(() => msg.style.display = 'none', 4000);
    } catch { msg.textContent = 'Network error'; msg.style.display = 'inline'; }
});

async function pingDiseaseApi() {
    const url  = document.getElementById('diseaseApiUrl').value.trim();
    const stat = document.getElementById('diseaseApiStatus');
    if (!url) { alert('Enter an API URL first.'); return; }
    stat.style.display = 'block';
    stat.style.background = 'rgba(99,102,241,.08)';
    stat.style.color = 'var(--text-primary)';
    stat.textContent = 'Testing connection…';
    try {
        const t0 = performance.now();
        const r = await fetch(BASE + 'admin-secure/ajax/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${encodeURIComponent(CSRF)}&action=ping_url&url=${encodeURIComponent(url)}`
        });
        const d = await r.json();
        const ms = Math.round(performance.now() - t0);
        if (d.success) {
            stat.style.background = 'rgba(76,175,80,.12)';
            stat.style.color = '#388e3c';
            stat.textContent = `✓ Reachable — HTTP ${d.http_code || '200'} (${ms} ms)`;
        } else {
            stat.style.background = 'rgba(244,67,54,.1)';
            stat.style.color = '#d32f2f';
            stat.textContent = `✗ ${d.message || 'Unreachable'}`;
        }
    } catch(err) {
        stat.style.background = 'rgba(244,67,54,.1)'; stat.style.color = '#d32f2f';
        stat.textContent = '✗ Network error: ' + err.message;
    }
}

// ── Init ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    onProviderChange('<?php echo htmlspecialchars($activeProvider); ?>');
    loadStats();
    loadChart();
    loadLogs();
});
</script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
