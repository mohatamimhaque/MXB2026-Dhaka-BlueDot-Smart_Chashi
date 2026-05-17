<?php
/**
 * Farmer Dashboard
 * Dedicated dashboard for farmers with crop management, weather, and farming tools
 */

// Authentication and role check
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    // Redirect to appropriate dashboard based on role
    if ($currentUser['role'] === 'admin') {
        header('Location: ' . $base_url . 'admin-secure/pages/admin-dashboard.php');
        exit;
    } elseif ($currentUser['role'] === 'officer') {
        redirect('officer-dashboard');
    } else {
        redirect('home');
    }
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$userId = $_SESSION['user_id'];

// Get farmer statistics
$stats = [];
try {
    $stats['total_crops'] = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ?", [$userId])['count'] ?? 0;
    $stats['active_crops'] = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ? AND status = 'growing'", [$userId])['count'] ?? 0;
    $stats['total_yield'] = $db->single("SELECT SUM(actual_yield) as total FROM crop_data WHERE farmer_id = ? AND actual_yield IS NOT NULL", [$userId])['total'] ?? 0;
    $stats['disease_reports'] = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ?", [$userId])['count'] ?? 0;
    $stats['marketplace_products'] = $db->single("SELECT COUNT(*) as count FROM marketplace_products WHERE seller_id = ?", [$userId])['count'] ?? 0;
    $stats['community_posts'] = $db->single("SELECT COUNT(*) as count FROM community_posts WHERE user_id = ?", [$userId])['count'] ?? 0;
} catch (Exception $e) {
    // Handle errors silently
}

// Get recent crops
$recentCrops = [];
try {
    $recentCrops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY planted_date DESC LIMIT 6", [$userId]);
} catch (Exception $e) {
    // Handle errors
}

// Get recent activities
$recentActivities = [];
try {
    $recentActivities = $db->resultSet("
        SELECT 'crop' as type, crop_name as title, planted_date as date, status 
        FROM crop_data WHERE farmer_id = ? 
        UNION ALL 
        SELECT 'post' as type, title, created_at as date, 'published' as status 
        FROM community_posts WHERE user_id = ? 
        ORDER BY date DESC LIMIT 5
    ", [$userId, $userId]);
} catch (Exception $e) {
    // Handle errors
}

// Get upcoming tasks
$upcomingTasks = [];
try {
    $upcomingTasks = $db->resultSet("SELECT * FROM tasks WHERE user_id = ? AND status != 'completed' ORDER BY due_date ASC LIMIT 5", [$userId]);
} catch (Exception $e) {
    // Handle errors
}
?>

<!-- Modern Hero Section -->
<section class="hero-modern">
    <div class="hero-particles" id="heroParticles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">verified_user</span>
            <span><?php echo __('farmer'); ?></span>
        </div>
        <h1>
            <span class="wave-emoji">👋</span> 
            <?php echo __('welcome_back'); ?>, <?php echo htmlspecialchars($currentUser['first_name']); ?>!
        </h1>
        <p class="hero-subtitle"><?php echo __('track_manage_crops'); ?></p>
        <div class="hero-quick-stats">
            <div class="quick-stat">
                <span class="material-icons">schedule</span>
                <span><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div class="quick-stat">
                <span class="material-icons">location_on</span>
                <span>Bangladesh</span>
            </div>
        </div>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">agriculture</span>
            <span><?php echo $stats['active_crops']; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">shopping_cart</span>
            <span><?php echo $stats['marketplace_products']; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">eco</span>
        </div>
    </div>
</section>

<!-- Live Status Bar -->
<div class="live-status-bar">
    <div class="status-item status-online">
        <span class="status-dot pulse"></span>
        <span><?php echo __('system_online'); ?></span>
    </div>
    <div class="status-item">
        <span class="material-icons">update</span>
        <span id="last-updated"><?php echo __('last_updated'); ?>: <span id="update-time"><?php echo date('h:i A'); ?></span></span>
    </div>
    <div class="status-item">
        <span class="material-icons">cloud_done</span>
        <span><?php echo __('data_synced'); ?></span>
    </div>
</div>

<!-- Modern Statistics Cards -->
<div class="stats-grid-modern">
    <div class="stat-card-modern stat-gradient-green">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">agriculture</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern" id="total-crops-count"><?php echo $stats['total_crops']; ?></div>
                <div class="stat-label-modern"><?php echo __('total_crops'); ?></div>
            </div>
            <div class="stat-trend trend-up">
                <span class="material-icons">trending_up</span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-blue">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">eco</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern" id="active-crops-count"><?php echo $stats['active_crops']; ?></div>
                <div class="stat-label-modern"><?php echo __('active_crops'); ?></div>
            </div>
            <div class="stat-trend trend-up">
                <span class="material-icons">trending_up</span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-orange">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">shopping_cart</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern" id="products-count"><?php echo $stats['marketplace_products']; ?></div>
                <div class="stat-label-modern"><?php echo __('products_listed'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-red">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">bug_report</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern" id="disease-reports-count"><?php echo $stats['disease_reports']; ?></div>
                <div class="stat-label-modern"><?php echo __('disease_reports'); ?></div>
            </div>
        </div>
    </div>
</div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Crops -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo __('recent_crops'); ?></h3>
                <a href="<?php echo $base_url; ?>?page=crops" class="btn btn-small"><?php echo __('view_all'); ?></a>
            </div>
            <div class="card-body">
                <div id="recent-crops-container">
                    <?php if (empty($recentCrops)): ?>
                        <p class="text-center text-muted"><?php echo __('no_crops_yet'); ?>. <a href="<?php echo $base_url; ?>?page=crops"><?php echo __('add_first_crop'); ?></a></p>
                    <?php else: ?>
                        <div class="crop-list">
                            <?php foreach ($recentCrops as $crop): ?>
                                <div class="crop-item">
                                    <div class="crop-icon">
                                        <span class="material-icons">grass</span>
                                    </div>
                                    <div class="crop-details">
                                        <strong><?php echo htmlspecialchars($crop['crop_name']); ?></strong>
                                        <small>
                                            <?php 
                                            $status = !empty($crop['status']) ? htmlspecialchars($crop['status']) : __('growing');
                                            echo $status;
                                            ?> | 
                                            <?php 
                                            if (!empty($crop['planted_date']) && $crop['planted_date'] != '0000-00-00') {
                                                echo date('M d, Y', strtotime($crop['planted_date']));
                                            } else {
                                                echo __('no_date');
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo __('quick_actions'); ?></h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="<?php echo $base_url; ?>?page=crops" class="action-btn">
                        <span class="material-icons">add_circle</span>
                        <span><?php echo __('add_crop'); ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=farmer-orders" class="action-btn">
                        <span class="material-icons">receipt_long</span>
                        <span><?php echo __('my_orders') ?: 'My Orders'; ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=farmer-messages" class="action-btn">
                        <span class="material-icons">chat</span>
                        <span><?php echo __('messages') ?: 'Messages'; ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=disease" class="action-btn">
                        <span class="material-icons">camera_alt</span>
                        <span><?php echo __('detect_disease'); ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=weather" class="action-btn">
                        <span class="material-icons">wb_sunny</span>
                        <span><?php echo __('check_weather'); ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=marketplace" class="action-btn">
                        <span class="material-icons">storefront</span>
                        <span><?php echo __('sell_products'); ?></span>
                    </a>
                    <a href="<?php echo $base_url; ?>?page=community" class="action-btn">
                        <span class="material-icons">forum</span>
                        <span><?php echo __('community'); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo __('recent_activities'); ?></h3>
            </div>
            <div class="card-body">
                <div id="recent-activities-container">
                    <?php if (empty($recentActivities)): ?>
                        <p class="text-center text-muted"><?php echo __('no_recent_activities'); ?></p>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <span class="material-icons"><?php echo $activity['type'] === 'crop' ? 'agriculture' : 'forum'; ?></span>
                                    <div class="activity-details">
                                        <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                        <small><?php echo date('M d, Y', strtotime($activity['date'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.refresh-btn {
    background: #557A46;
    color: white;
    border: none;
    padding: 0.75rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.refresh-btn:hover {
    background: #3d5a32;
    transform: rotate(180deg);
}

.refresh-btn:active {
    transform: scale(0.95) rotate(180deg);
}

.refresh-btn .material-icons {
    font-size: 24px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-icon .material-icons {
    font-size: 32px;
}

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    color: #333;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
}

.action-btn {
    display: flex !important;
    width: auto !important;
    height: auto !important;
    flex-direction: column !important;
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 1rem !important;
    background: #f5f5f5 !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    color: #333;
    transition: all 0.3s;
}

.action-btn:hover {
    background: #557A46;
   transform: translateY(-2px);
}

.crop-list, .activity-list, .task-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.crop-item, .activity-item, .task-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.crop-icon, .activity-item .material-icons {
    color: #557A46;
}

.crop-details, .activity-details, .task-details {
    flex: 1;
}

.crop-details strong, .activity-details strong, .task-details strong {
    display: block;
    margin-bottom: 0.25rem;
}

.crop-details small, .activity-details small, .task-details small {
    color: #666;
    font-size: 0.85rem;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: #999;
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.crop-item, .activity-item, .task-item {
    animation: fadeIn 0.3s ease-out;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 12px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #557A46;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
</style>

<script>
// Real-time data refresh configuration
const REFRESH_INTERVAL = 30000; // 30 seconds
let refreshTimer = null;

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/get-dashboard-stats.php');
        if (!response.ok) throw new Error('Failed to fetch stats');
        
        const data = await response.json();
        if (data.success) {
            // Update statistics cards
            if (document.getElementById('total-crops-count')) {
                document.getElementById('total-crops-count').textContent = data.stats.total_crops || 0;
            }
            if (document.getElementById('active-crops-count')) {
                document.getElementById('active-crops-count').textContent = data.stats.active_crops || 0;
            }
            if (document.getElementById('products-count')) {
                document.getElementById('products-count').textContent = data.stats.marketplace_products || 0;
            }
            if (document.getElementById('disease-reports-count')) {
                document.getElementById('disease-reports-count').textContent = data.stats.disease_reports || 0;
            }
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Load recent crops
async function loadRecentCrops() {
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/get-recent-crops.php');
        if (!response.ok) throw new Error('Failed to fetch crops');
        
        const data = await response.json();
        const container = document.getElementById('recent-crops-container');
        
        if (data.success && data.crops && data.crops.length > 0) {
            let html = '<div class="crop-list">';
            data.crops.forEach(crop => {
                let formattedDate = '<?php echo __('no_date'); ?>';
                
                if (crop.planted_date && crop.planted_date !== '0000-00-00' && crop.planted_date !== null) {
                    const plantingDate = new Date(crop.planted_date);
                    // Check if date is valid
                    if (!isNaN(plantingDate.getTime())) {
                        formattedDate = plantingDate.toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                    }
                }
                
                const status = crop.status || '<?php echo __('growing'); ?>';
                
                html += `
                    <div class="crop-item">
                        <div class="crop-icon">
                            <span class="material-icons">grass</span>
                        </div>
                        <div class="crop-details">
                            <strong>${escapeHtml(crop.crop_name)}</strong>
                            <small>${escapeHtml(status)} | ${formattedDate}</small>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center text-muted"><?php echo __('no_crops_yet'); ?>. <a href="<?php echo $base_url; ?>?page=crops"><?php echo __('add_first_crop'); ?></a></p>';
        }
    } catch (error) {
        console.error('Error loading recent crops:', error);
    }
}

// Load recent activities
async function loadRecentActivities() {
    try {
        const response = await fetch('<?php echo $base_url; ?>ajax/get-recent-activities.php');
        if (!response.ok) throw new Error('Failed to fetch activities');
        
        const data = await response.json();
        const container = document.getElementById('recent-activities-container');
        
        if (data.success && data.activities && data.activities.length > 0) {
            let html = '<div class="activity-list">';
            data.activities.forEach(activity => {
                let formattedDate = '<?php echo __('no_date'); ?>';
                
                if (activity.date && activity.date !== '0000-00-00' && activity.date !== null) {
                    const activityDate = new Date(activity.date);
                    if (!isNaN(activityDate.getTime())) {
                        formattedDate = activityDate.toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                    }
                }
                
                const icon = activity.type === 'crop' ? 'agriculture' : 'forum';
                
                html += `
                    <div class="activity-item">
                        <span class="material-icons">${icon}</span>
                        <div class="activity-details">
                            <strong>${escapeHtml(activity.title)}</strong>
                            <small>${formattedDate}</small>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center text-muted"><?php echo __('no_recent_activities'); ?></p>';
        }
    } catch (error) {
        console.error('Error loading recent activities:', error);
    }
}



// Update last updated time
function updateLastUpdatedTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    const timeElement = document.getElementById('update-time');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}



// Stop auto-refresh
function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Initial load
    
    // Update initial time
    updateLastUpdatedTime();
    
    
    
    
    
    // Stop refresh when page is unloaded
    window.addEventListener('beforeunload', stopAutoRefresh);
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
