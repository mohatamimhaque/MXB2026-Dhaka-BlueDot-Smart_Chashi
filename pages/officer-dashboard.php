<?php
include __DIR__ . '/../layouts/header.php';

if (!isLoggedIn() || getCurrentUser()['role'] !== 'officer') {
    redirect('dashboard');
}

$user = getCurrentUser();
$db = new Database();

// Get statistics
$totalFarmers = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
$activeCrops = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE status = 'growing'", [])['count'] ?? 0;
$diseaseReports30 = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;
$alertsIssued7 = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [])['count'] ?? 0;

// Get recent disease detections
$recentDetections = $db->resultSet("SELECT dd.*, u.first_name, u.last_name, c.crop_name 
    FROM disease_reports dd 
    JOIN users u ON dd.user_id = u.user_id 
    LEFT JOIN crop_data c ON dd.crop_id = c.crop_id 
    ORDER BY dd.created_at DESC LIMIT 10", []);

// Get farmers needing attention
$farmersNeedingAttention = $db->resultSet("SELECT u.*, COUNT(dd.detection_id) as detection_count 
    FROM users u 
    LEFT JOIN disease_reports dd ON u.user_id = dd.user_id 
    WHERE u.role = 'farmer' AND dd.severity = 'high' AND dd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY u.user_id 
    LIMIT 5", []);

// Get crops by region
$cropsByRegion = $db->resultSet("SELECT fp.region, COUNT(cd.crop_id) as crop_count 
    FROM farmer_profiles fp 
    LEFT JOIN crop_data cd ON fp.user_id = cd.farmer_id 
    WHERE fp.region IS NOT NULL 
    GROUP BY fp.region", []);
?>

<section class="hero">
    <h1><span class="material-icons">badge</span> Agriculture Officer Dashboard</h1>
    <p>Monitor and Support Farmers</p>
</section>

<!-- Officer Statistics -->
<h2 class="mt-3"><span class="material-icons">analytics</span> Your Oversight Statistics</h2>
<div class="officer-stats-grid">
    <div class="officer-stat-box stat-success">
        <span class="material-icons stat-icon">people</span>
        <div class="stat-number"><?php echo $totalFarmers; ?></div>
        <div class="stat-label">Total Farmers</div>
    </div>
    <div class="officer-stat-box stat-primary">
        <span class="material-icons stat-icon">agriculture</span>
        <div class="stat-number"><?php echo $activeCrops; ?></div>
        <div class="stat-label">Active Crops</div>
    </div>
    <div class="officer-stat-box stat-warning">
        <span class="material-icons stat-icon">report</span>
        <div class="stat-number"><?php echo $diseaseReports30; ?></div>
        <div class="stat-label">Reports (30d)</div>
    </div>
    <div class="officer-stat-box stat-danger">
        <span class="material-icons stat-icon">notifications_active</span>
        <div class="stat-number"><?php echo $alertsIssued7; ?></div>
        <div class="stat-label">Alerts (7d)</div>
    </div>
</div>

<!-- Quick Actions -->
<h2 class="mt-3"><span class="material-icons">bolt</span> Officer Actions</h2>
<div class="officer-actions-grid">
    <a href="<?php echo $base_url; ?>farmer-reports" class="officer-action-card">
        <span class="material-icons officer-action-icon">description</span>
        <h3>Farmer Reports</h3>
        <p>View detailed farmer reports and crop data</p>
        <button class="btn btn-small mt-2">View Reports</button>
    </a>
    
    <a href="<?php echo $base_url; ?>issue-alert" class="officer-action-card">
        <span class="material-icons officer-action-icon">warning</span>
        <h3>Issue Alert</h3>
        <p>Send alerts to farmers in your region</p>
        <button class="btn btn-small btn-warning mt-2">Issue Alert</button>
    </a>
    
    <a href="<?php echo $base_url; ?>advisory" class="officer-action-card">
        <span class="material-icons officer-action-icon">campaign</span>
        <h3>Advisory</h3>
        <p>Create and publish farming advisories</p>
        <button class="btn btn-small btn-info mt-2">Create Advisory</button>
    </a>
    
    <a href="<?php echo $base_url; ?>field-visits" class="officer-action-card">
        <span class="material-icons officer-action-icon">agriculture</span>
        <h3>Field Visits</h3>
        <p>Schedule and manage field visits</p>
        <button class="btn btn-small btn-secondary mt-2">Manage Visits</button>
    </a>
</div>

<!-- Farmers Needing Attention -->
<?php if ($farmersNeedingAttention): ?>
<h2 class="mt-3"><span class="material-icons">priority_high</span> Farmers Needing Attention</h2>
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Farmer</th>
                    <th>Contact</th>
                    <th>High Severity Issues</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($farmersNeedingAttention as $farmer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
                    <td><span class="badge badge-danger"><?php echo $farmer['detection_count']; ?> issues</span></td>
                    <td class="table-actions">
                        <a href="tel:<?php echo $farmer['phone']; ?>" class="btn btn-small">
                            <span class="material-icons">phone</span> Call
                        </a>
                        <a href="<?php echo $base_url; ?>farmer-profile-view?id=<?php echo $farmer['user_id']; ?>" class="btn btn-small btn-info">
                            <span class="material-icons">visibility</span> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Disease Detections -->
<h2 class="mt-3"><span class="material-icons">search</span> Recent Disease Detections</h2>
<div class="officer-detections-grid">
    <?php if ($recentDetections): ?>
        <?php foreach (array_slice($recentDetections, 0, 6) as $detection): ?>
        <div class="card detection-card">
            <div class="card-header">
                <div class="detection-crop-info">
                    <span class="material-icons">eco</span>
                    <h4 class="card-title"><?php echo htmlspecialchars($detection['crop_name'] ?? 'Unknown Crop'); ?></h4>
                </div>
                <span class="badge badge-<?php echo $detection['severity'] === 'high' ? 'danger' : ($detection['severity'] === 'medium' ? 'warning' : 'success'); ?>">
                    <?php echo ucfirst($detection['severity'] ?? 'N/A'); ?>
                </span>
            </div>
            <div class="card-content">
                <p><span class="material-icons detection-icon">person</span> <strong>Farmer:</strong> <?php echo htmlspecialchars($detection['first_name'] . ' ' . ($detection['last_name'] ?? '')); ?></p>
                <p><span class="material-icons detection-icon">coronavirus</span> <strong>Disease:</strong> <?php echo htmlspecialchars($detection['disease_name'] ?? 'Unknown'); ?></p>
                <p><span class="material-icons detection-icon">schedule</span> <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($detection['created_at'])); ?></p>
            </div>
            <div class="card-footer">
                <a href="<?php echo $base_url; ?>detection-detail?id=<?php echo $detection['detection_id']; ?>" class="btn btn-small">
                    <span class="material-icons">visibility</span> View Details
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card text-center empty-state-card">
            <span class="material-icons empty-state-icon">check_circle</span>
            <p class="text-muted">No recent detections</p>
        </div>
    <?php endif; ?>
</div>

<!-- Crops by Region -->
<?php if ($cropsByRegion): ?>
<h2 class="mt-3"><span class="material-icons">map</span> Crops by Region</h2>
<div class="card">
    <div class="region-stats-list">
        <?php foreach ($cropsByRegion as $region): ?>
        <div class="region-stat-row">
            <span class="material-icons region-icon">location_on</span>
            <span class="region-name"><?php echo htmlspecialchars($region['region']); ?></span>
            <strong class="badge badge-success"><?php echo $region['crop_count']; ?> crops</strong>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Information Cards -->
<h2 class="mt-3"><span class="material-icons">info</span> Officer Resources</h2>
<div class="officer-resources-grid">
    <div class="card resource-card">
        <span class="material-icons resource-icon">menu_book</span>
        <h4>Knowledge Base</h4>
        <p>Access crop management guides, disease identification, and best practices.</p>
        <a href="<?php echo $base_url; ?>knowledge-base" class="btn btn-small mt-2">
            <span class="material-icons">arrow_forward</span> Browse Resources
        </a>
    </div>
    
    <div class="card resource-card">
        <span class="material-icons resource-icon">contacts</span>
        <h4>Emergency Contacts</h4>
        <p>Contact other officers, agricultural experts, and emergency services.</p>
        <a href="<?php echo $base_url; ?>contacts" class="btn btn-small btn-secondary mt-2">
            <span class="material-icons">arrow_forward</span> View Contacts
        </a>
    </div>
    
    <div class="card resource-card">
        <span class="material-icons resource-icon">assessment</span>
        <h4>Generate Report</h4>
        <p>Generate monthly or quarterly reports for your region.</p>
        <a href="<?php echo $base_url; ?>generate-report" class="btn btn-small btn-info mt-2">
            <span class="material-icons">arrow_forward</span> Generate
        </a>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
