<?php

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();

// Get active tab
$activeTab = $_GET['tab'] ?? 'posts';

// Get search query
$searchQuery = trim($_GET['search'] ?? '');

// Get posts
$posts = $db->resultSet("SELECT cp.*, u.first_name, u.last_name FROM community_posts cp LEFT JOIN users u ON cp.user_id = u.user_id ORDER BY cp.created_at DESC LIMIT 50");

// Get nearby farmers
$nearbyFarmers = [];
if ($activeTab === 'farmers' && !empty($user['latitude']) && !empty($user['longitude'])) {
    $nearbyFarmers = $db->resultSet("
        SELECT u.user_id, u.first_name, u.last_name, u.location, u.latitude, u.longitude,
               ROUND(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))), 2) as distance
        FROM users u 
        WHERE u.role = 'farmer' AND u.user_id != ? AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL
        HAVING distance <= 50
        ORDER BY distance ASC
        LIMIT 100
    ", [$user['latitude'], $user['longitude'], $user['latitude'], $_SESSION['user_id']]);
    
    // Filter by search query (name or location)
    if (!empty($searchQuery)) {
        $nearbyFarmers = array_filter($nearbyFarmers, function($farmer) use ($searchQuery) {
            $name = strtolower($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? ''));
            $location = strtolower($farmer['location'] ?? '');
            $search = strtolower($searchQuery);
            return strpos($name, $search) !== false || strpos($location, $search) !== false;
        });
    }
}

// Get officers
$officers = [];
if ($activeTab === 'officers') {
    $officers = $db->resultSet("
        SELECT u.user_id, u.first_name, u.last_name, u.location, u.latitude, u.longitude, u.phone
        FROM users u 
        WHERE u.role = 'officer'
        ORDER BY u.first_name ASC
        LIMIT 100
    ");
    
    // Filter by search query (name or location)
    if (!empty($searchQuery)) {
        $officers = array_filter($officers, function($officer) use ($searchQuery) {
            $name = strtolower($officer['first_name'] . ' ' . ($officer['last_name'] ?? ''));
            $location = strtolower($officer['location'] ?? '');
            $search = strtolower($searchQuery);
            return strpos($name, $search) !== false || strpos($location, $search) !== false;
        });
    }
}
?>

<section class="hero">
    <h1><?php echo __('farmer_community'); ?></h1>
    <p><?php echo __('share_experiences'); ?></p>
</section>

<!-- Community Tabs Navigation -->
<div class="community-tabs">
    <a href="?tab=posts" class="tab-btn <?php echo $activeTab === 'posts' ? 'active' : ''; ?>">
        <span class="material-icons">forum</span>
        <span>Community Posts</span>
    </a>
    <a href="?tab=farmers" class="tab-btn <?php echo $activeTab === 'farmers' ? 'active' : ''; ?>">
        <span class="material-icons">agriculture</span>
        <span>Nearby Farmers</span>
    </a>
    <a href="?tab=officers" class="tab-btn <?php echo $activeTab === 'officers' ? 'active' : ''; ?>">
        <span class="material-icons">supervised_user_circle</span>
        <span>Officer Network</span>
    </a>
</div>

<!-- Community Posts Tab -->
<?php if ($activeTab === 'posts'): ?>
<div class="community-grid">
    <div class="community-form-section">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">post_add</span>
                    <?php echo __('create_new_post'); ?>
                </h3>
            </div>

            <form id="postForm" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title"><?php echo __('title'); ?> *</label>
                    <input type="text" id="title" name="title" placeholder="<?php echo __('whats_on_mind'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category"><?php echo __('category'); ?></label>
                    <select id="category" name="category">
                        <option>General Discussion</option>
                        <option>Crop Problems</option>
                        <option>Best Practices</option>
                        <option>Market Updates</option>
                        <option>Weather Discussion</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="content"><?php echo __('content'); ?> *</label>
                    <textarea id="content" name="content" placeholder="<?php echo __('share_experience'); ?>" required></textarea>
                </div>

                <div class="form-group">
                    <label for="postPhoto">Add Photo (Optional)</label>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.5rem;">JPG, PNG - Max 5MB</p>
                    <input type="file" id="postPhoto" name="postPhoto" accept="image/*" style="cursor: pointer;">
                    <small class="form-text">Adding a photo will help attract more engagement to your post</small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-block"><?php echo __('post'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="community-stats-section">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">analytics</span>
                    <?php echo __('community_stats'); ?>
                </h3>
            </div>

            <div class="stats-list">
                <p><span class="material-icons">people</span><strong><?php echo __('total_farmers'); ?>:</strong> 12,450</p>
                <p><span class="material-icons">article</span><strong><?php echo __('posts_today'); ?>:</strong> 345</p>
                <p><span class="material-icons">forum</span><strong><?php echo __('active_discussions'); ?>:</strong> 89</p>
            </div>
            <p class="popular-topics-title"><span class="material-icons">trending_up</span><strong><?php echo __('popular_topics'); ?>:</strong></p>
            <ul class="popular-topics-list">
                <li><span class="material-icons">chevron_right</span>Rice Diseases</li>
                <li><span class="material-icons">chevron_right</span>Vegetable Pricing</li>
                <li><span class="material-icons">chevron_right</span>Weather Impacts</li>
                <li><span class="material-icons">chevron_right</span>Organic Farming</li>
            </ul>
        </div>
    </div>
</div>

<h2 class="section-title">
    <span class="material-icons" style="vertical-align: middle;">forum</span>
    <?php echo __('recent_posts'); ?>
</h2>
<?php if ($posts): ?>
    <div class="posts-container">
        <?php foreach ($posts as $post): ?>
        <div class="card post-card">
            <div class="card-header post-header">
                <div class="post-info">
                    <h4 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h4>
                    <small class="post-meta">
                        <span class="material-icons">person</span>
                        by <?php echo htmlspecialchars($post['first_name'] . ' ' . ($post['last_name'] ?? '')); ?> 
                        in <strong><?php echo htmlspecialchars($post['category']); ?></strong>
                    </small>
                </div>
                <span class="badge"><?php echo date('M d', strtotime($post['created_at'])); ?></span>
            </div>

            <?php if (!empty($post['post_image'])): ?>
                <div class="post-image-container">
                    <img src="<?php echo htmlspecialchars($post['post_image']); ?>" alt="Post image" class="post-image">
                </div>
            <?php endif; ?>

            <div class="card-content">
                <p><?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?>...</p>
            </div>

            <div class="card-footer post-actions">
                <button class="btn-like btn btn-small" data-post-id="<?php echo $post['post_id']; ?>">
                    <span class="material-icons">favorite</span> <?php echo $post['likes']; ?> Likes
                </button>
                <a href="#" class="btn btn-small"><span class="material-icons">reply</span> Reply</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="notice notice-info">
        <p>No posts yet. Be the first to share your farming experience!</p>
    </div>
<?php endif; ?>

<?php elseif ($activeTab === 'farmers'): ?>
<!-- Nearby Farmers Tab -->
<h2 class="section-title">
    <span class="material-icons" style="vertical-align: middle;">agriculture</span>
    Nearby Farmers
</h2>

<!-- Search Form -->
<div class="search-section">
    <form method="GET" class="search-form">
        <input type="hidden" name="tab" value="farmers">
        <div class="search-input-group">
            <span class="material-icons search-icon">search</span>
            <input type="text" name="search" placeholder="Search by name or location..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-input">
            <button type="submit" class="btn btn-small" style="margin-left: 0.5rem;">
                <span class="material-icons">search</span>
            </button>
            <?php if (!empty($searchQuery)): ?>
                <a href="?tab=farmers" class="btn btn-small btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($user['latitude']) && !empty($user['longitude'])): ?>
    <?php if (!empty($nearbyFarmers)): ?>
        <div class="farmers-grid">
            <?php foreach ($nearbyFarmers as $farmer): ?>
            <div class="card farmer-card">
                <div class="farmer-header">
                    <div class="farmer-info">
                        <h4 class="farmer-name"><?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?></h4>
                        <p class="farmer-location">
                            <span class="material-icons">location_on</span>
                            <?php echo htmlspecialchars($farmer['location'] ?? 'Location not set'); ?>
                        </p>
                    </div>
                    <span class="distance-badge"><?php echo $farmer['distance']; ?> km</span>
                </div>
                <div class="card-footer farmer-actions">
                    <button class="btn btn-small">
                        <span class="material-icons">message</span> Message
                    </button>
                    <button class="btn btn-small btn-secondary">
                        <span class="material-icons">info</span> View Profile
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="notice notice-info">
            <p>No nearby farmers found within 50 km. Expand your search or check back later!</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="notice notice-warning">
        <p><span class="material-icons">location_off</span> Please set your location in your profile to find nearby farmers.</p>
        <a href="<?php echo $base_url; ?>profile" class="btn btn-small mt-2">
            <span class="material-icons">edit</span> Update Profile
        </a>
    </div>
<?php endif; ?>

<?php elseif ($activeTab === 'officers'): ?>
<!-- Officer Network Tab -->
<h2 class="section-title">
    <span class="material-icons" style="vertical-align: middle;">supervised_user_circle</span>
    Officer Network
</h2>

<!-- Search Form -->
<div class="search-section">
    <form method="GET" class="search-form">
        <input type="hidden" name="tab" value="officers">
        <div class="search-input-group">
            <span class="material-icons search-icon">search</span>
            <input type="text" name="search" placeholder="Search by name or location..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-input">
            <button type="submit" class="btn btn-small" style="margin-left: 0.5rem;">
                <span class="material-icons">search</span>
            </button>
            <?php if (!empty($searchQuery)): ?>
                <a href="?tab=officers" class="btn btn-small btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($officers)): ?>
    <div class="officers-grid">
        <?php foreach ($officers as $officer): ?>
        <div class="card officer-card">
            <div class="officer-header">
                <div class="officer-info">
                    <h4 class="officer-name"><?php echo htmlspecialchars($officer['first_name'] . ' ' . ($officer['last_name'] ?? '')); ?></h4>
                    <p class="officer-title">
                        <span class="material-icons">work</span>
                        Agricultural Officer
                    </p>
                    <p class="officer-location">
                        <span class="material-icons">location_on</span>
                        <?php echo htmlspecialchars($officer['location'] ?? 'Location not specified'); ?>
                    </p>
                </div>
            </div>
            <div class="officer-contact">
                <?php if (!empty($officer['phone'])): ?>
                    <p><span class="material-icons">phone</span> <?php echo htmlspecialchars($officer['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer officer-actions">
                <button class="btn btn-small">
                    <span class="material-icons">mail</span> Contact
                </button>
                <button class="btn btn-small btn-secondary">
                    <span class="material-icons">info</span> View Profile
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="notice notice-info">
        <p>No officers found in the system.</p>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
