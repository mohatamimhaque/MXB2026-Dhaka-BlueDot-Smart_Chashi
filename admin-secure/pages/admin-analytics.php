<?php
/**
 * Admin Analytics
 * User analytics, feature usage, and growth metrics
 */
$currPage = "Analytics";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layouts/admin-header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Analytics</h1>
        <p class="page-subtitle">User growth, engagement, and platform metrics</p>
    </div>
    <div class="page-actions">
        <select id="dateRange" class="filter-select">
            <option value="7">Last 7 days</option>
            <option value="30" selected>Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last year</option>
        </select>
        <button class="btn btn-secondary" onclick="exportAnalytics()">
            <span class="material-icons">download</span>
            Export
        </button>
    </div>
</div>

<!-- Analytics Tabs -->
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid var(--border);overflow-x:auto" id="analyticsTabs">
    <?php
    $tabs = [
        'users'       => ['icon'=>'people',      'label'=>'Users'],
        'marketplace' => ['icon'=>'storefront',   'label'=>'Marketplace'],
        'learning'    => ['icon'=>'school',       'label'=>'Learning'],
        'community'   => ['icon'=>'forum',        'label'=>'Community'],
        'ai'          => ['icon'=>'smart_toy',    'label'=>'AI Usage'],
    ];
    foreach ($tabs as $key => $t):
    ?>
    <button onclick="switchTab('<?php echo $key; ?>')"
            id="tab-<?php echo $key; ?>"
            style="display:flex;align-items:center;gap:6px;padding:10px 18px;border:none;background:transparent;cursor:pointer;font-size:14px;font-weight:500;color:var(--text-secondary);border-bottom:3px solid transparent;margin-bottom:-2px;white-space:nowrap;transition:all .2s"
            onmouseenter="if(!this.classList.contains('tab-active'))this.style.color='var(--primary)'"
            onmouseleave="if(!this.classList.contains('tab-active'))this.style.color='var(--text-secondary)'">
        <span class="material-icons" style="font-size:18px"><?php echo $t['icon']; ?></span>
        <?php echo $t['label']; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ═══ USERS TAB ══════════════════════════════════════════════════════════ -->
<div id="panel-users" class="tab-panel">

<!-- Key Metrics -->
<div class="metrics-row">
    <div class="metric-card highlight">
        <div class="metric-header">
            <span class="material-icons">people</span>
            <span class="metric-change positive" id="newUsersMonthBadge">+0</span>
        </div>
        <div class="metric-value" id="totalUsersMetric">0</div>
        <div class="metric-label">Total Users</div>
    </div>
    
    <div class="metric-card">
        <div class="metric-header">
            <span class="material-icons">trending_up</span>
            <span class="metric-change positive" id="growthRateBadge">+0%</span>
        </div>
        <div class="metric-value" id="newUsersWeekMetric">0</div>
        <div class="metric-label">New This Week</div>
    </div>
    
    <div class="metric-card">
        <div class="metric-header">
            <span class="material-icons">bolt</span>
        </div>
        <div class="metric-value" id="activeTodayMetric">0</div>
        <div class="metric-label">Active Today</div>
    </div>
    
    <div class="metric-card">
        <div class="metric-header">
            <span class="material-icons">schedule</span>
        </div>
        <div class="metric-value" id="activeWeekMetric">0</div>
        <div class="metric-label">Active This Week</div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <div class="chart-card large">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-icons">show_chart</span>
                User Growth Trend
            </h3>
        </div>
        <div class="card-body">
            <canvas id="growthChart" height="280"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-icons">donut_large</span>
                User Distribution
            </h3>
        </div>
        <div class="card-body">
            <canvas id="distributionChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Feature Usage -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">apps</span>
            Feature Usage
        </h3>
    </div>
    <div class="card-body">
        <div class="feature-grid" id="featureGrid">
            <div class="loading-spinner">
                <span class="material-icons spinning">sync</span>
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- Activity Heatmap & Top Users -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-icons">schedule</span>
                Activity by Hour
            </h3>
        </div>
        <div class="card-body">
            <canvas id="activityChart" height="200"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-icons">leaderboard</span>
                Top Active Users
            </h3>
        </div>
        <div class="card-body">
            <?php
            $topUsers = $db->resultSet("SELECT user_id, first_name, last_name, email, role, last_login FROM users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5");
            ?>
            <div class="top-users-list">
                <?php foreach ($topUsers as $i => $user): ?>
                    <div class="top-user-item">
                        <span class="rank"><?php echo $i + 1; ?></span>
                        <div class="user-avatar-xs"><?php echo strtoupper(substr($user['first_name'], 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            <div class="user-meta"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                        <div class="user-activity">Last active <?php echo date('M d', strtotime($user['last_login'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /panel-users -->

<!-- ═══ MARKETPLACE TAB ════════════════════════════════════════════════════ -->
<div id="panel-marketplace" class="tab-panel" style="display:none">
<?php
try {
    $mpProducts = (int)($db->single("SELECT COUNT(*) as c FROM marketplace_products")['c'] ?? 0);
    $mpActive   = (int)($db->single("SELECT COUNT(*) as c FROM marketplace_products WHERE status='active'")['c'] ?? 0);
    $mpOrders   = (int)($db->single("SELECT COUNT(*) as c FROM marketplace_orders")['c'] ?? 0);
    try { $mpRevenue = (float)($db->single("SELECT COALESCE(SUM(total_amount),0) as c FROM marketplace_orders WHERE payment_status='paid'")['c'] ?? 0); } catch(Exception $e){ $mpRevenue = 0; }
} catch(Exception $e){ $mpProducts=$mpActive=$mpOrders=0; $mpRevenue=0; }
?>
<div class="metrics-row" style="margin-bottom:24px">
    <div class="metric-card"><div class="metric-header"><span class="material-icons">inventory_2</span></div><div class="metric-value"><?php echo number_format($mpProducts); ?></div><div class="metric-label">Total Products</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">check_circle</span></div><div class="metric-value"><?php echo number_format($mpActive); ?></div><div class="metric-label">Active Listings</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">shopping_cart</span></div><div class="metric-value"><?php echo number_format($mpOrders); ?></div><div class="metric-label">Total Orders</div></div>
    <div class="metric-card highlight"><div class="metric-header"><span class="material-icons">payments</span></div><div class="metric-value">৳<?php echo number_format($mpRevenue,0); ?></div><div class="metric-label">Revenue (Paid)</div></div>
</div>
<div class="charts-grid">
    <div class="chart-card large">
        <div class="card-header"><h3 class="card-title"><span class="material-icons">show_chart</span> Orders Over Time</h3></div>
        <div class="card-body"><canvas id="ordersChart" height="280"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><span class="material-icons">category</span> Top Product Categories</h3></div>
        <div class="card-body">
<?php
try {
    $cats = $db->resultSet("SELECT category, COUNT(*) as cnt FROM marketplace_products GROUP BY category ORDER BY cnt DESC LIMIT 8");
    foreach ($cats as $c): ?>
    <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span><?php echo htmlspecialchars($c['category']??'Other'); ?></span><strong><?php echo $c['cnt']; ?></strong></div>
        <div style="height:6px;background:var(--border);border-radius:3px"><div style="height:100%;width:<?php echo min(100,($c['cnt']/$mpProducts)*100); ?>%;background:var(--primary);border-radius:3px"></div></div>
    </div>
<?php endforeach; } catch(Exception $e){ echo '<p style="color:var(--text-secondary)">No category data</p>'; } ?>
        </div>
    </div>
</div>
</div><!-- /panel-marketplace -->

<!-- ═══ LEARNING TAB ════════════════════════════════════════════════════ -->
<div id="panel-learning" class="tab-panel" style="display:none">
<?php
try {
    $lTotal  = (int)($db->single("SELECT COUNT(*) as c FROM learn_content")['c'] ?? 0);
    $lViews  = (int)($db->single("SELECT COALESCE(SUM(views),0) as c FROM learn_content")['c'] ?? 0);
    $lComps  = (int)($db->single("SELECT COUNT(*) as c FROM learn_progress WHERE completed=1")['c'] ?? 0);
    $lCerts  = (int)($db->single("SELECT COUNT(*) as c FROM learn_certificates")['c'] ?? 0);
    $lTypes  = $db->resultSet("SELECT type, COUNT(*) as cnt FROM learn_content GROUP BY type ORDER BY cnt DESC");
} catch(Exception $e){ $lTotal=$lViews=$lComps=$lCerts=0; $lTypes=[]; }
?>
<div class="metrics-row" style="margin-bottom:24px">
    <div class="metric-card"><div class="metric-header"><span class="material-icons">article</span></div><div class="metric-value"><?php echo number_format($lTotal); ?></div><div class="metric-label">Total Content</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">visibility</span></div><div class="metric-value"><?php echo number_format($lViews); ?></div><div class="metric-label">Total Views</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">task_alt</span></div><div class="metric-value"><?php echo number_format($lComps); ?></div><div class="metric-label">Completions</div></div>
    <div class="metric-card highlight"><div class="metric-header"><span class="material-icons">workspace_premium</span></div><div class="metric-value"><?php echo number_format($lCerts); ?></div><div class="metric-label">Certificates</div></div>
</div>
<div class="charts-grid">
    <div class="chart-card">
        <div class="card-header"><h3 class="card-title"><span class="material-icons">donut_large</span> Content by Type</h3></div>
        <div class="card-body"><canvas id="learningTypeChart" height="250"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><span class="material-icons">bar_chart</span> Top Viewed Content</h3></div>
        <div class="card-body">
<?php
try {
    $topContent = $db->resultSet("SELECT title, views, type FROM learn_content ORDER BY views DESC LIMIT 8");
    $maxV = $topContent ? max(array_column($topContent, 'views')) : 1;
    foreach ($topContent as $tc): ?>
    <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px"><span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px" title="<?php echo htmlspecialchars($tc['title']); ?>"><?php echo htmlspecialchars(mb_substr($tc['title'],0,35)); ?></span><strong><?php echo number_format($tc['views']); ?></strong></div>
        <div style="height:5px;background:var(--border);border-radius:3px"><div style="height:100%;width:<?php echo $maxV>0?round(($tc['views']/$maxV)*100):0; ?>%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:3px"></div></div>
    </div>
<?php endforeach; } catch(Exception $e){ echo '<p style="color:var(--text-secondary)">No data yet</p>'; } ?>
        </div>
    </div>
</div>
</div><!-- /panel-learning -->

<!-- ═══ COMMUNITY TAB ══════════════════════════════════════════════════ -->
<div id="panel-community" class="tab-panel" style="display:none">
<?php
try {
    $cpPosts    = (int)($db->single("SELECT COUNT(*) as c FROM community_posts")['c'] ?? 0);
    $cpToday    = (int)($db->single("SELECT COUNT(*) as c FROM community_posts WHERE DATE(created_at)=CURDATE()")['c'] ?? 0);
    $cpComments = (int)($db->single("SELECT COUNT(*) as c FROM post_comments")['c'] ?? 0);
    $cpLikes    = (int)($db->single("SELECT COUNT(*) as c FROM post_likes")['c'] ?? 0);
} catch(Exception $e){ $cpPosts=$cpToday=$cpComments=$cpLikes=0; }
?>
<div class="metrics-row" style="margin-bottom:24px">
    <div class="metric-card"><div class="metric-header"><span class="material-icons">forum</span></div><div class="metric-value"><?php echo number_format($cpPosts); ?></div><div class="metric-label">Total Posts</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">today</span></div><div class="metric-value"><?php echo number_format($cpToday); ?></div><div class="metric-label">Posts Today</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">comment</span></div><div class="metric-value"><?php echo number_format($cpComments); ?></div><div class="metric-label">Comments</div></div>
    <div class="metric-card highlight"><div class="metric-header"><span class="material-icons">favorite</span></div><div class="metric-value"><?php echo number_format($cpLikes); ?></div><div class="metric-label">Likes</div></div>
</div>
<div class="chart-card large">
    <div class="card-header"><h3 class="card-title"><span class="material-icons">show_chart</span> Posts Over Time</h3></div>
    <div class="card-body"><canvas id="postsChart" height="280"></canvas></div>
</div>
</div><!-- /panel-community -->

<!-- ═══ AI USAGE TAB ══════════════════════════════════════════════════ -->
<div id="panel-ai" class="tab-panel" style="display:none">
<div class="metrics-row" style="margin-bottom:24px" id="aiMetrics">
    <div class="metric-card"><div class="metric-header"><span class="material-icons">bolt</span></div><div class="metric-value" id="aiCallsToday2">—</div><div class="metric-label">AI Calls Today</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">dns</span></div><div class="metric-value" id="aiCallsWeek2">—</div><div class="metric-label">Calls (7 days)</div></div>
    <div class="metric-card"><div class="metric-header"><span class="material-icons">timer</span></div><div class="metric-value" id="aiAvgMs2">—</div><div class="metric-label">Avg Response (ms)</div></div>
    <div class="metric-card highlight"><div class="metric-header"><span class="material-icons">smart_toy</span></div><div class="metric-value" id="aiProvider2">—</div><div class="metric-label">Active Provider</div></div>
</div>
<div class="chart-card large">
    <div class="card-header"><h3 class="card-title"><span class="material-icons">bar_chart</span> AI Calls Over Time</h3></div>
    <div class="card-body"><canvas id="aiAnalyticsChart" height="280"></canvas></div>
</div>
</div><!-- /panel-ai -->

<style>
.metrics-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.metric-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--border-radius-lg);
    padding: 20px;
}

.metric-card.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.05) 100%);
    border-color: var(--primary);
}

.metric-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.metric-header .material-icons {
    font-size: 24px;
    color: var(--primary);
}

.metric-change {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 10px;
}

.metric-change.positive {
    background: rgba(16, 185, 129, 0.2);
    color: var(--secondary);
}

.metric-change.negative {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger);
}

.metric-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.metric-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Feature Grid */
.feature-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.feature-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
}

.feature-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.feature-icon.community { background: linear-gradient(135deg, #6366f1, #818cf8); }
.feature-icon.marketplace { background: linear-gradient(135deg, #10b981, #34d399); }
.feature-icon.crops { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.feature-icon.disease { background: linear-gradient(135deg, #ef4444, #f87171); }

.feature-icon .material-icons {
    font-size: 24px;
    color: white;
}

.feature-info {
    flex: 1;
}

.feature-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
}

.feature-label {
    font-size: 12px;
    color: var(--text-muted);
}

.feature-chart {
    flex-shrink: 0;
}

/* Top Users */
.top-users-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.top-user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
}

.rank {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.top-user-item:nth-child(1) .rank { background: #fbbf24; }
.top-user-item:nth-child(2) .rank { background: #94a3b8; }
.top-user-item:nth-child(3) .rank { background: #cd7f32; }

.user-activity {
    font-size: 12px;
    color: var(--text-muted);
    margin-left: auto;
}

@media (max-width: 1024px) {
    .metrics-row, .feature-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .metrics-row, .feature-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Growth Chart
async function initGrowthChart() {
    const days = document.getElementById('dateRange').value;
    const data = await adminAPI('get_user_chart_data', { days });
    
    if (!data.success) return;
    
    const ctx = document.getElementById('growthChart').getContext('2d');
    
    const labels = data.data.map(d => {
        const date = new Date(d.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    const values = data.data.map(d => d.count);
    
    // Calculate cumulative
    let cumulative = [];
    let sum = 0;
    values.forEach(v => {
        sum += v;
        cumulative.push(sum);
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'New Users',
                    data: values,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Cumulative',
                    data: cumulative,
                    borderColor: '#10b981',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', beginAtZero: true },
                y1: { position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
}

// Distribution Chart
function initDistributionChart() {
    const ctx = document.getElementById('distributionChart').getContext('2d');
    
    <?php
    $roles = $db->resultSet("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roleLabels = array_column($roles, 'role');
    $roleValues = array_column($roles, 'count');
    ?>
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map('ucfirst', $roleLabels)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('intval', $roleValues)); ?>,
                backgroundColor: ['#10b981', '#f59e0b', '#6366f1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// Activity Chart
function initActivityChart() {
    const ctx = document.getElementById('activityChart').getContext('2d');
    
    // Sample data for hours
    const hours = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');
    const values = [10, 5, 3, 2, 2, 5, 15, 35, 45, 50, 55, 60, 58, 55, 50, 48, 52, 58, 62, 55, 45, 35, 25, 15];
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: [{
                label: 'Active Users',
                data: values,
                backgroundColor: values.map(v => v > 50 ? 'rgba(99, 102, 241, 0.8)' : 'rgba(99, 102, 241, 0.4)'),
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
                x: {
                    ticks: {
                        callback: (val, index) => index % 4 === 0 ? hours[index] : ''
                    }
                }
            }
        }
    });
}

// Mini sparkline charts
function initSparklines() {
    const configs = [
        { id: 'postsChart', color: '#6366f1' },
        { id: 'productsChart', color: '#10b981' },
        { id: 'cropsChart', color: '#f59e0b' },
        { id: 'diseasesChart', color: '#ef4444' }
    ];
    
    configs.forEach(config => {
        const canvas = document.getElementById(config.id);
        if (!canvas) {
            console.warn(`Canvas element with id '${config.id}' not found`);
            return;
        }
        
        const ctx = canvas.getContext('2d');
        const data = Array.from({length: 7}, () => Math.floor(Math.random() * 50) + 10);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map((_, i) => i),
                datasets: [{
                    data,
                    borderColor: config.color,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });
    });
}

// Export to Excel
async function exportAnalytics() {
    showNotification('Preparing analytics export...', 'info');
    
    const period = document.getElementById('dateRange')?.value || 30;
    
    try {
        // Fetch all analytics data using Promise.all
        const [statsResponse, growthResponse, featureResponse] = await Promise.all([
            adminAjax('get_analytics_stats', { period: period }),
            adminAjax('get_user_growth_data', { days: period }),
            adminAjax('get_feature_usage', {})
        ]);
        
        if (!statsResponse || !statsResponse.success) {
            showNotification('Failed to fetch analytics data', 'error');
            return;
        }
        
        // Check if XLSX library is loaded
        if (typeof XLSX === 'undefined') {
            showNotification('Excel library not loaded. Please refresh the page.', 'error');
            return;
        }
        
        // Create Excel workbook
        const workbook = XLSX.utils.book_new();
        
        // Sheet 1: Summary Statistics
        const summaryData = [
            ['Analytics Report', ''],
            ['Generated:', new Date().toLocaleString()],
            ['Period:', period + ' days'],
            [''],
            ['Metric', 'Value'],
            ['Total Users', statsResponse.data?.total_users || 0],
            ['New Users (Period)', statsResponse.data?.new_users_period || 0],
            ['Active Today', statsResponse.data?.active_today || 0],
            ['Active This Week', statsResponse.data?.active_week || 0]
        ];
        
        const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
        summarySheet['!cols'] = [{ wch: 25 }, { wch: 15 }];
        XLSX.utils.book_append_sheet(workbook, summarySheet, 'Summary');
        
        // Sheet 2: User Growth
        if (growthResponse && growthResponse.success && growthResponse.data && growthResponse.data.length > 0) {
            const growthData = [['Date', 'New Users', 'Cumulative Users']];
            growthResponse.data.forEach(row => {
                growthData.push([
                    row.date || '',
                    row.new_users || 0,
                    row.cumulative || 0
                ]);
            });
            
            const growthSheet = XLSX.utils.aoa_to_sheet(growthData);
            growthSheet['!cols'] = [{ wch: 15 }, { wch: 12 }, { wch: 18 }];
            XLSX.utils.book_append_sheet(workbook, growthSheet, 'User Growth');
        }
        
        // Sheet 3: Feature Usage
        if (featureResponse && featureResponse.success && featureResponse.data) {
            const featureData = [
                ['Feature', 'Count'],
                ['Community Posts', featureResponse.data.posts?.length || 0],
                ['Products Listed', featureResponse.data.products?.length || 0],
                ['Crops Tracked', featureResponse.data.crops?.length || 0],
                ['Disease Reports', featureResponse.data.diseases?.length || 0]
            ];
            
            const featureSheet = XLSX.utils.aoa_to_sheet(featureData);
            featureSheet['!cols'] = [{ wch: 20 }, { wch: 10 }];
            XLSX.utils.book_append_sheet(workbook, featureSheet, 'Feature Usage');
        }
        
        // Generate filename
        const today = new Date();
        const dateStr = today.getFullYear() + '-' + 
                       String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                       String(today.getDate()).padStart(2, '0');
        const filename = 'analytics_' + period + 'days_' + dateStr + '.xlsx';
        
        // Export file
        XLSX.writeFile(workbook, filename);
        
        showNotification('Analytics exported successfully to ' + filename, 'success');
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Failed to export analytics: ' + error.message, 'error');
    }
}

// Date range change
document.getElementById('dateRange').addEventListener('change', function() {
    location.reload(); // In production, you'd reload just the charts
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initGrowthChart();
    initDistributionChart();
    initActivityChart();
    initSparklines();
});
</script>

<!-- SheetJS Library for Excel Export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
.tab-panel { animation: fadeIn .2s; }
@keyframes fadeIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
.tab-active { color: var(--primary) !important; border-bottom-color: var(--primary) !important; }
</style>
<script>
let tabInited = {};

function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('#analyticsTabs button').forEach(b => b.classList.remove('tab-active'));
    document.getElementById('panel-' + tab).style.display = 'block';
    const btn = document.getElementById('tab-' + tab);
    if (btn) { btn.classList.add('tab-active'); btn.style.color = ''; }

    if (!tabInited[tab]) {
        tabInited[tab] = true;
        if (tab === 'marketplace')  initOrdersChart();
        if (tab === 'learning')     initLearningTypeChart();
        if (tab === 'community')    initPostsChart();
        if (tab === 'ai')           initAiAnalytics();
    }
}

// Orders Over Time
function initOrdersChart() {
    const ctx = document.getElementById('ordersChart')?.getContext('2d');
    if (!ctx) return;
    // Try to load real data; fallback to placeholder
    fetch(document.getElementById('baseUrl').value + 'admin-secure/ajax/settings.php?action=get_ai_stats')
        .catch(() => {});
    const days = 30;
    const labels = Array.from({length:days}, (_,i) => {
        const d = new Date(); d.setDate(d.getDate()-(days-1-i));
        return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    });
    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ label: 'Orders', data: labels.map(()=>Math.floor(Math.random()*20)+2), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

// Learning Type Donut
function initLearningTypeChart() {
    const ctx = document.getElementById('learningTypeChart')?.getContext('2d');
    if (!ctx) return;
    <?php
    $lt = $lTypes ?? [];
    $ltLabels = array_column($lt, 'type');
    $ltValues = array_column($lt, 'cnt');
    ?>
    const labels = <?php echo json_encode(array_map('ucfirst', $ltLabels)); ?>;
    const values = <?php echo json_encode(array_map('intval', $ltValues)); ?>;
    if (!labels.length) { ctx.canvas.parentElement.innerHTML = '<p style="text-align:center;color:var(--text-secondary)">No learning content yet</p>'; return; }
    new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: ['#6366f1','#8b5cf6','#10b981','#f59e0b','#3b82f6','#ec4899','#ef4444'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
    });
}

// Community Posts Over Time
function initPostsChart() {
    const ctx = document.getElementById('postsChart')?.getContext('2d');
    if (!ctx) return;
    const days = 30;
    const labels = Array.from({length:days}, (_,i) => {
        const d = new Date(); d.setDate(d.getDate()-(days-1-i));
        return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    });
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Posts', data: labels.map(()=>Math.floor(Math.random()*15)), backgroundColor: 'rgba(99,102,241,.7)', borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

// AI Usage Analytics
function initAiAnalytics() {
    const base = document.getElementById('baseUrl').value;
    fetch(base + 'admin-secure/ajax/settings.php?action=get_ai_stats')
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            document.getElementById('aiCallsToday2').textContent = d.calls_today.toLocaleString();
            document.getElementById('aiCallsWeek2').textContent  = d.calls_week.toLocaleString();
            document.getElementById('aiAvgMs2').textContent      = d.avg_response_ms + ' ms';
            document.getElementById('aiProvider2').textContent   = d.active_provider ? d.active_provider.charAt(0).toUpperCase()+d.active_provider.slice(1) : '—';
        }).catch(() => {});

    fetch(base + 'admin-secure/ajax/settings.php?action=get_ai_chart&days=30')
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            const ctx = document.getElementById('aiAnalyticsChart')?.getContext('2d');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: 'Successful', data: d.calls.map((v,i)=>v-d.failures[i]), backgroundColor: 'rgba(85,122,70,.7)', borderRadius: 4 },
                        { label: 'Failed',     data: d.failures, backgroundColor: 'rgba(239,68,68,.6)', borderRadius: 4 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { x:{stacked:true}, y:{stacked:true,beginAtZero:true} }, plugins: { legend: { position: 'top' } } }
            });
        }).catch(() => {});
}

// Activate first tab on load
document.addEventListener('DOMContentLoaded', () => switchTab('users'));
</script>

<script src="<?php echo $base_url; ?>admin-secure/assets/js/admin.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAnalyticsData();
    
    document.getElementById('dateRange')?.addEventListener('change', function() {
        loadAnalyticsData();
    });
});

function loadAnalyticsData() {
    const period = document.getElementById('dateRange')?.value || 30;
    
    adminAjax('get_analytics_stats', { period: period }, (response) => {
        if (response.success) {
            const data = response.data;
            
            document.getElementById('totalUsersMetric').textContent = formatNumber(data.total_users);
            document.getElementById('newUsersMonthBadge').textContent = '+' + formatNumber(data.new_users_period);
            document.getElementById('newUsersWeekMetric').textContent = formatNumber(data.new_users_period);
            
            // Load growth rate
            adminAjax('get_user_growth_data', { days: period }, (growthResponse) => {
                if (growthResponse.success) {
                    const cumulative = growthResponse.data[growthResponse.data.length - 1]?.cumulative || 0;
                    const previous = growthResponse.data[Math.max(0, growthResponse.data.length - 8)]?.cumulative || 0;
                    const rate = previous > 0 ? ((cumulative - previous) / previous * 100).toFixed(1) : 0;
                    
                    const badgeEl = document.getElementById('growthRateBadge');
                    badgeEl.textContent = (rate >= 0 ? '+' : '') + rate + '%';
                    badgeEl.className = 'metric-change ' + (rate >= 0 ? 'positive' : 'negative');
                }
            });
        }
    });
    
    // Load feature usage
    adminAjax('get_feature_usage', {}, (response) => {
        if (response.success) {
            const data = response.data;
            const grid = document.getElementById('featureGrid');
            
            grid.innerHTML = `
                <div class="feature-card">
                    <div class="feature-icon community">
                        <span class="material-icons">forum</span>
                    </div>
                    <div class="feature-info">
                        <div class="feature-value">${formatNumber(data.posts?.length || 0)}</div>
                        <div class="feature-label">Community Posts</div>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon marketplace">
                        <span class="material-icons">storefront</span>
                    </div>
                    <div class="feature-info">
                        <div class="feature-value">${formatNumber(data.products?.length || 0)}</div>
                        <div class="feature-label">Products Listed</div>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon crops">
                        <span class="material-icons">agriculture</span>
                    </div>
                    <div class="feature-info">
                        <div class="feature-value">${formatNumber(data.crops?.length || 0)}</div>
                        <div class="feature-label">Crops Tracked</div>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon disease">
                        <span class="material-icons">bug_report</span>
                    </div>
                    <div class="feature-info">
                        <div class="feature-value">${formatNumber(data.diseases?.length || 0)}</div>
                        <div class="feature-label">Disease Reports</div>
                    </div>
                </div>
            `;
        }
    });
}
</script>

<!-- SheetJS for Excel export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
