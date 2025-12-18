<?php
include __DIR__ . '/../layouts/header.php';

$db = new Database();
$farmerId = $_GET['id'] ?? null;

if (!$farmerId) {
    redirect('dashboard');
}

// Get farmer information
$farmer = $db->single("SELECT u.*, fp.* FROM users u 
    LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id 
    WHERE u.user_id = ? AND u.role = 'farmer'", [$farmerId]);

if (!$farmer) {
    redirect('dashboard');
}

// Get farmer's public statistics
$crops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 6", [$farmerId]);
$cropCount = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ?", [$farmerId])['count'] ?? 0;
$activeCrops = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ? AND status = 'growing'", [$farmerId])['count'] ?? 0;
$communityPosts = $db->single("SELECT COUNT(*) as count FROM community_posts WHERE user_id = ?", [$farmerId])['count'] ?? 0;
?>

<section class="hero">
    <h1><span class="material-icons">person</span> Farmer Profile</h1>
    <p>Public profile information</p>
</section>

<div class="profile-view-grid">
    <!-- Farmer Information Card -->
    <div class="card profile-view-card">
        <div class="profile-view-header">
            <div class="profile-view-avatar">
                <span class="material-icons">account_circle</span>
            </div>
            <div class="profile-view-info">
                <h2><?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?></h2>
                <p class="profile-view-role">
                    <span class="material-icons">agriculture</span>
                    Farmer
                </p>
            </div>
        </div>

        <div class="profile-view-details">
            <?php if (!empty($farmer['region'])): ?>
            <div class="profile-detail-item">
                <span class="material-icons">location_on</span>
                <span><strong>Region:</strong> <?php echo htmlspecialchars($farmer['region']); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($farmer['farm_size'])): ?>
            <div class="profile-detail-item">
                <span class="material-icons">landscape</span>
                <span><strong>Farm Size:</strong> <?php echo htmlspecialchars($farmer['farm_size']); ?> acres</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($farmer['primary_crops'])): ?>
            <div class="profile-detail-item">
                <span class="material-icons">eco</span>
                <span><strong>Primary Crops:</strong> <?php echo htmlspecialchars($farmer['primary_crops']); ?></span>
            </div>
            <?php endif; ?>

            <div class="profile-detail-item">
                <span class="material-icons">event</span>
                <span><strong>Member Since:</strong> <?php echo date('M Y', strtotime($farmer['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="card profile-view-card">
        <h3><span class="material-icons">analytics</span> Statistics</h3>
        <div class="profile-stats-list">
            <div class="stat-item">
                <span class="material-icons stat-item-icon">agriculture</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $cropCount; ?></div>
                    <div class="stat-item-label">Total Crops</div>
                </div>
            </div>

            <div class="stat-item">
                <span class="material-icons stat-item-icon">eco</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $activeCrops; ?></div>
                    <div class="stat-item-label">Active Crops</div>
                </div>
            </div>

            <div class="stat-item">
                <span class="material-icons stat-item-icon">forum</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $communityPosts; ?></div>
                    <div class="stat-item-label">Community Posts</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Crops -->
<?php if ($crops): ?>
<h2 class="mt-3"><span class="material-icons">agriculture</span> Recent Crops</h2>
<div class="crops-list-grid">
    <?php foreach ($crops as $crop): ?>
    <div class="crop-card">
        <div class="crop-card-header">
            <span class="material-icons">eco</span>
            <h4><?php echo htmlspecialchars($crop['crop_name']); ?></h4>
        </div>
        <div class="crop-details">
            <div class="crop-detail">
                <span class="material-icons crop-detail-icon">category</span>
                <span><?php echo htmlspecialchars($crop['crop_type']); ?></span>
            </div>
            <div class="crop-detail">
                <span class="material-icons crop-detail-icon">landscape</span>
                <span><?php echo htmlspecialchars($crop['area']); ?> acres</span>
            </div>
            <div class="crop-detail">
                <span class="material-icons crop-detail-icon">event</span>
                <span><?php echo date('M d, Y', strtotime($crop['planting_date'])); ?></span>
            </div>
            <div class="crop-detail">
                <span class="material-icons crop-detail-icon">
                    <?php echo $crop['status'] === 'growing' ? 'eco' : ($crop['status'] === 'harvested' ? 'check_circle' : 'schedule'); ?>
                </span>
                <span><?php echo ucfirst($crop['status']); ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card mt-4 text-center">
    <?php if (isLoggedIn() && getCurrentUser()['role'] === 'officer'): ?>
    <h3><span class="material-icons">contact_phone</span> Contact Farmer</h3>
    <p>As an officer, you can contact this farmer directly.</p>
    <a href="tel:<?php echo htmlspecialchars($farmer['phone']); ?>" class="btn mt-2">
        <span class="material-icons">phone</span> Call Farmer
    </a>
    <?php else: ?>
    <h3><span class="material-icons">info</span> Connect with Farmers</h3>
    <p>Join our community to connect with farmers and share knowledge.</p>
    <a href="<?php echo $base_url; ?>community" class="btn mt-2">
        <span class="material-icons">people</span> Visit Community
    </a>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
