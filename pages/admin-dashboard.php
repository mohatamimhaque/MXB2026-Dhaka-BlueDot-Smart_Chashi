<?php

if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    redirect('dashboard');
}

include __DIR__ . '/../layouts/header.php';
$user = getCurrentUser();
$db = new Database();

// Get comprehensive statistics
$totalUsers = $db->single("SELECT COUNT(*) as count FROM users", [])['count'] ?? 0;
$totalFarmers = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
$totalOfficers = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'officer'", [])['count'] ?? 0;
$totalAdmins = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'admin'", [])['count'] ?? 0;
$activeCrops = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE status = 'growing'", [])['count'] ?? 0;
$totalDiseaseReports = $db->single("SELECT COUNT(*) as count FROM disease_reports", [])['count'] ?? 0;
$totalCommunityPosts = $db->single("SELECT COUNT(*) as count FROM community_posts", [])['count'] ?? 0;
$totalAlerts = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;

// Get recent users
$recentUsers = $db->resultSet("SELECT * FROM users ORDER BY created_at DESC LIMIT 10", []);

// Get system activity
$recentActivity = $db->resultSet("SELECT 'crop' as type, created_at, farmer_id as user_id FROM crop_data 
    UNION ALL SELECT 'post' as type, created_at, user_id FROM community_posts 
    UNION ALL SELECT 'detection' as type, created_at, user_id FROM disease_reports 
    ORDER BY created_at DESC LIMIT 15", []);
?>

<section class="hero">
    <h1>🎛️ Admin Dashboard</h1>
    <p>System Overview and Management</p>
</section>

<!-- System Statistics -->
<h2 class="mt-3">📊 System Statistics</h2>
<div class="stats-grid">
    <div class="stat-box" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
        <div class="stat-number" style="color: white;"><?php echo $totalUsers; ?></div>
        <div class="stat-label" style="color: white;">Total Users</div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, var(--success), #27AE60);">
        <div class="stat-number" style="color: white;"><?php echo $totalFarmers; ?></div>
        <div class="stat-label" style="color: white;">Farmers</div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, var(--info), #2980b9);">
        <div class="stat-number" style="color: white;"><?php echo $totalOfficers; ?></div>
        <div class="stat-label" style="color: white;">Officers</div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, var(--accent), #e67e22);">
        <div class="stat-number" style="color: white;"><?php echo $totalAdmins; ?></div>
        <div class="stat-label" style="color: white;">Admins</div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="grid mt-2">
    <div class="card text-center">
        <h3><?php echo $activeCrops; ?></h3>
        <p>🌱 Active Crops</p>
    </div>
    <div class="card text-center">
        <h3><?php echo $totalDiseaseReports; ?></h3>
        <p>🔍 Disease Reports</p>
    </div>
    <div class="card text-center">
        <h3><?php echo $totalCommunityPosts; ?></h3>
        <p>💬 Community Posts</p>
    </div>
    <div class="card text-center">
        <h3><?php echo $totalAlerts; ?></h3>
        <p>⚠️ Recent Alerts</p>
    </div>
</div>

<!-- Admin Actions -->
<h2 class="mt-3">⚡ Quick Actions</h2>
<div class="grid">
    <a href="<?php echo $base_url; ?>user-management" class="card" style="text-decoration: none;">
        <h3>👥 User Management</h3>
        <p>Manage users, roles, and permissions</p>
        <button class="btn btn-small mt-2">Manage Users</button>
    </a>
    
    <a href="<?php echo $base_url; ?>system-settings" class="card" style="text-decoration: none;">
        <h3>⚙️ System Settings</h3>
        <p>Configure application settings</p>
        <button class="btn btn-small btn-secondary mt-2">Settings</button>
    </a>
    
    <a href="<?php echo $base_url; ?>analytics" class="card" style="text-decoration: none;">
        <h3>📈 Analytics</h3>
        <p>View detailed analytics and reports</p>
        <button class="btn btn-small btn-info mt-2">View Analytics</button>
    </a>
    
    <a href="<?php echo $base_url; ?>content-moderation" class="card" style="text-decoration: none;">
        <h3>🛡️ Moderation</h3>
        <p>Moderate community content</p>
        <button class="btn btn-small btn-warning mt-2">Moderate</button>
    </a>
</div>

<!-- Recent Users -->
<h2 class="mt-3">👤 Recent Users</h2>
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentUsers): ?>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td>#<?php echo $u['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($u['first_name'] . ' ' . ($u['last_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="badge badge-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'officer' ? 'info' : 'success'); ?>"><?php echo ucfirst($u['role']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo $base_url; ?>edit-user?id=<?php echo $u['user_id']; ?>" class="btn btn-small">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No users found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- System Activity -->
<h2 class="mt-3">📋 Recent Activity</h2>
<div class="card">
    <?php if ($recentActivity): ?>
        <?php foreach ($recentActivity as $activity): ?>
        <div class="activity-item" style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
            <?php
            $icon = $activity['type'] === 'crop' ? '🌱' : ($activity['type'] === 'post' ? '💬' : '🔍');
            $text = $activity['type'] === 'crop' ? 'New crop added' : ($activity['type'] === 'post' ? 'Community post created' : 'Disease detection performed');
            ?>
            <span><?php echo $icon; ?> <?php echo $text; ?> by User #<?php echo $activity['user_id']; ?></span>
            <span class="text-muted" style="float: right; font-size: 0.85rem;"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></span>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center text-muted">No recent activity</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
