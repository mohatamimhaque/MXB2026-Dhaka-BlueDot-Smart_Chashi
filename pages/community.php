<?php
include __DIR__ . '/../layouts/header.php';

if (!isLoggedIn()) {
    redirect('login');
}

$user = getCurrentUser();
$userRole = $user['role'];
$db = new Database();

// Get active tab
$activeTab = $_GET['tab'] ?? 'posts';
?>

<!-- Modern Hero Section -->
<section class="hero-modern">
    <div class="hero-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">groups</span>
            <span><?php echo __('community'); ?></span>
        </div>
        <h1>
            <span class="material-icons" style="font-size: 2rem;">forum</span>
            <?php echo __('farmer_community'); ?>
        </h1>
        <p class="hero-subtitle"><?php echo $userRole === 'officer' ? __('monitor_engage_community') : __('share_experiences'); ?></p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">forum</span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">people</span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">thumb_up</span>
        </div>
    </div>
</section>

<!-- Community Tabs Navigation -->
<div class="community-tabs">
    <button class="tab-btn <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" data-tab="posts">
        <span class="material-icons">forum</span>
        <span><?php echo __('community_posts_tab'); ?></span>
    </button>
    <?php if ($userRole === 'farmer'): ?>
    <button class="tab-btn <?php echo $activeTab === 'farmers' ? 'active' : ''; ?>" data-tab="farmers">
        <span class="material-icons">agriculture</span>
        <span><?php echo __('nearby_farmers_tab'); ?></span>
    </button>
    <button class="tab-btn <?php echo $activeTab === 'officers' ? 'active' : ''; ?>" data-tab="officers">
        <span class="material-icons">supervised_user_circle</span>
        <span><?php echo __('officer_network_tab'); ?></span>
    </button>
    
    <?php elseif ($userRole === 'officer'): ?>
    <button class="tab-btn <?php echo $activeTab === 'farmers' ? 'active' : ''; ?>" data-tab="farmers">
        <span class="material-icons">agriculture</span>
        <span><?php echo __('farmers_in_my_region_tab'); ?></span>
    </button>
  
    <?php endif; ?>
    <button class="tab-btn <?php echo $activeTab === 'bookmarks' ? 'active' : ''; ?>" data-tab="bookmarks">
        <span class="material-icons">bookmarks</span>
        <span><?php echo __('my_bookmarks_tab'); ?></span>
    </button>
</div>

<!-- Community Posts Tab -->
<div class="tab-content" id="posts-tab" style="display: <?php echo $activeTab === 'posts' ? 'block' : 'none'; ?>;">
    <div class="community-grid">
        <div class="community-form-section">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="material-icons" style="vertical-align: middle;">
                            <?php echo $userRole === 'officer' ? 'campaign' : 'post_add'; ?>
                        </span>
                        <?php echo $userRole === 'officer' ? __('post_official_advisory') : __('create_new_post'); ?>
                    </h3>
                </div>

                <form id="postForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_post">
                    <div class="form-group">
                        <label for="title"><?php echo __('title'); ?> *</label>
                        <input type="text" id="title" name="title" placeholder="<?php echo __('whats_on_mind'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category"><?php echo __('category'); ?></label>
                        <select id="category" name="category">
                            <option value="General Discussion"><?php echo __('general_discussion'); ?></option>
                            <option value="Crop Problems"><?php echo __('crop_problems'); ?></option>
                            <option value="Best Practices"><?php echo __('best_practices'); ?></option>
                            <option value="Market Updates"><?php echo __('market_updates'); ?></option>
                            <option value="Weather Discussion"><?php echo __('weather_discussion'); ?></option>
                            <option value="Pest Control"><?php echo __('pest_control'); ?></option>
                            <option value="Fertilizer Tips"><?php echo __('fertilizer_tips'); ?></option>
                            <?php if ($userRole === 'officer'): ?>
                            <option value="Official Advisory"><?php echo __('official_advisory'); ?></option>
                            <option value="Government Updates"><?php echo __('government_updates'); ?></option>
                            <option value="Training Programs"><?php echo __('training_programs'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="content"><?php echo __('content'); ?> *</label>
                        <textarea id="content" name="content" placeholder="<?php echo __('share_experience'); ?>" required rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="postPhoto"><?php echo __('add_photo_optional'); ?></label>
                        <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.5rem;"><?php echo __('jpg_png_max_5mb'); ?></p>
                        <input type="file" id="postPhoto" name="postPhoto" accept="image/*" style="cursor: pointer;">
                        <small class="form-text"><?php echo __('photo_engagement_tip'); ?></small>
                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                            <img id="previewImg" src="" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                            <button type="button" onclick="clearImagePreview()" class="btn btn-small btn-secondary" style="margin-top: 5px;"><?php echo __('remove_image'); ?></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-block" id="submitPostBtn">
                            <span class="material-icons">send</span> <?php echo __('post'); ?>
                        </button>
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

                <div class="stats-list" id="communityStats">
                    <p><span class="material-icons">people</span><strong><?php echo __('total_farmers'); ?>:</strong> <span id="totalFarmers" class="stat-value"><?php echo __('loading'); ?></span></p>
                    <p><span class="material-icons">article</span><strong><?php echo __('posts_today'); ?>:</strong> <span id="postsToday" class="stat-value"><?php echo __('loading'); ?></span></p>
                    <p><span class="material-icons">forum</span><strong><?php echo __('active_discussions'); ?>:</strong> <span id="activeDiscussions" class="stat-value"><?php echo __('loading'); ?></span></p>
                </div>
                <p class="popular-topics-title"><span class="material-icons">trending_up</span><strong><?php echo __('popular_topics'); ?>:</strong></p>
                <ul class="popular-topics-list" id="trendingTopics">
                    <li><span class="material-icons">hourglass_empty</span>Loading topics...</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Filter and Sort Controls -->
    <div class="posts-controls" style="margin: 2rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
        <div class="filter-group" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 250px;">
                <span class="material-icons">search</span>
                <input type="text" id="searchInput" class="form-control" placeholder="<?php echo __('search_posts'); ?>" style="flex: 1;">
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="categoryFilter"><span class="material-icons">filter_list</span> <?php echo __('category'); ?>:</label>
                <select id="categoryFilter" class="filter-select">
                    <option value="all"><?php echo __('all_categories'); ?></option>
                    <option value="General Discussion"><?php echo __('general_discussion'); ?></option>
                    <option value="Crop Problems"><?php echo __('crop_problems'); ?></option>
                    <option value="Best Practices"><?php echo __('best_practices'); ?></option>
                    <option value="Market Updates"><?php echo __('market_updates'); ?></option>
                    <option value="Weather Discussion"><?php echo __('weather_discussion'); ?></option>
                    <option value="Pest Control"><?php echo __('pest_control'); ?></option>
                    <option value="Fertilizer Tips"><?php echo __('fertilizer_tips'); ?></option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="sortBy"><span class="material-icons">sort</span> <?php echo __('sort_by'); ?>:</label>
                <select id="sortBy" class="filter-select">
                    <option value="newest"><?php echo __('newest_first'); ?></option>
                    <option value="oldest"><?php echo __('oldest_first'); ?></option>
                    <option value="popular"><?php echo __('most_popular'); ?></option>
                    <option value="most_discussed"><?php echo __('most_discussed'); ?></option>
                </select>
            </div>

            <button id="refreshPosts" class="btn btn-small" style="margin-left: auto;">
                <span class="material-icons">refresh</span> <?php echo __('refresh'); ?>
            </button>
        </div>
    </div>

    <h2 class="section-title">
        <span class="material-icons" style="vertical-align: middle;">forum</span>
        <?php echo __('recent_posts'); ?>
    </h2>

    <!-- Loading indicator -->
    <div id="postsLoader" class="loader" style="display: none; text-align: center; padding: 20px;">
        <span class="material-icons rotating" style="animation: spin 1s linear infinite;">refresh</span> <?php echo __('loading_posts'); ?>
    </div>

    <!-- Posts container -->
    <div class="posts-container" id="postsContainer">
        <!-- Posts will be loaded dynamically -->
    </div>

    <!-- Load More Button -->
    <div style="text-align: center; margin-top: 2rem;">
        <div id="noMorePosts" style="display: none; padding: 20px; color: #666;">
            <span class="material-icons">check_circle</span> <?php echo __('youve_reached_end'); ?>
        </div>
    </div>
    
    <!-- Pagination Controls -->
    <div id="paginationContainer" class="pagination-wrapper">
        <div class="pagination">
            <button id="firstPageBtn" class="pagination-btn" title="First Page">
                <span class="material-icons">first_page</span>
            </button>
            <button id="prevPageBtn" class="pagination-btn" title="Previous Page">
                <span class="material-icons">chevron_left</span>
            </button>
            <div id="pageNumbers" class="page-numbers">
                <!-- Page numbers will be generated here -->
            </div>
            <button id="nextPageBtn" class="pagination-btn" title="Next Page">
                <span class="material-icons">chevron_right</span>
            </button>
            <button id="lastPageBtn" class="pagination-btn" title="Last Page">
                <span class="material-icons">last_page</span>
            </button>
        </div>
        <div class="pagination-info">
            <span id="pageInfo"><?php echo __('page'); ?> 1 <?php echo __('of'); ?> 1</span>
            <span class="pagination-separator">•</span>
            <span id="resultsInfo"><?php echo __('showing'); ?> 0 <?php echo __('posts'); ?></span>
        </div>
    </div>
</div>

<!-- Nearby Farmers Tab -->
<div class="tab-content" id="farmers-tab" style="display: <?php echo $activeTab === 'farmers' ? 'block' : 'none'; ?>;">
    <div class="farmers-tab-header">
        <h2 class="section-title">
            <span class="material-icons" style="vertical-align: middle;">agriculture</span>
            <?php echo __('nearby_farmers'); ?>
            <span id="farmersCount" class="count-badge">0</span>
        </h2>
    </div>

    <!-- Search & Filters Section -->
    <div class="farmers-search-section">
        <div class="search-filters-grid">
            <!-- Search Input -->
            <div class="search-input-wrapper">
                <span class="material-icons search-icon">search</span>
                <input type="text" 
                       id="farmersSearch" 
                       class="search-input-modern" 
                       placeholder="<?php echo __('search_by_name_location'); ?>"
                       autocomplete="off">
             
            </div>
            
            <!-- Distance Filter -->
            <div class="distance-filter-wrapper" id="distanceFilterSection">
                <label for="distanceFilter" class="filter-label">
                    <span class="material-icons">place</span>
                    <?php echo __('distance'); ?>: <span id="distanceValue">50</span> <?php echo __('km'); ?>
                </label>
                <input type="range" 
                       id="distanceFilter" 
                       class="distance-slider" 
                       min="5" 
                       max="100" 
                       value="50" 
                       step="5">
            </div>
            
            <!-- Crop Filter -->
            <div class="crop-filter-wrapper">
                <select id="cropFilter" class="filter-select">
                    <option value=""><?php echo __('all_crops'); ?></option>
                    <option value="rice"><?php echo __('rice'); ?></option>
                    <option value="wheat"><?php echo __('wheat'); ?></option>
                    <option value="vegetables"><?php echo __('vegetables'); ?></option>
                    <option value="fruits"><?php echo __('fruits'); ?></option>
                </select>
            </div>
            
            <!-- Experience Filter -->
            <div class="experience-filter-wrapper">
                <select id="experienceFilter" class="filter-select">
                    <option value=""><?php echo __('all_experience'); ?></option>
                    <option value="beginner"><?php echo __('beginner'); ?></option>
                    <option value="intermediate"><?php echo __('intermediate'); ?></option>
                    <option value="expert"><?php echo __('expert'); ?></option>
                </select>
            </div>
        </div>
        
        <!-- Active Filters Display -->
        <div id="activeFilters" class="active-filters" style="display: none;">
            <span class="active-filters-label"><?php echo __('active_filters'); ?>:</span>
            <div id="filterTags" class="filter-tags"></div>
            <button type="button" id="clearAllFilters" class="clear-all-btn">
                <span class="material-icons">clear_all</span>
                <?php echo __('clear_all'); ?>
            </button>
        </div>
    </div>

    <!-- Results Info -->
    <div class="farmers-results-info" id="farmersResultsInfo" style="display: none;">
        <span class="results-count">
            <span class="material-icons">people</span>
            <?php echo __('showing'); ?> <strong id="farmersShowing">0</strong> <?php echo __('farmers'); ?>
        </span>
        <span id="locationInfo" class="location-info"></span>
    </div>

    <!-- Loading indicator -->
    <div id="farmersLoader" class="farmers-loader" style="display: none;">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <p><?php echo __('loading_farmers'); ?></p>
        </div>
    </div>

    <!-- Farmers Grid Container -->
    <div class="farmers-grid" id="farmersContainer">
        <!-- Farmers will be loaded dynamically via AJAX -->
    </div>

    <!-- No Results Message -->
    <div id="noFarmersFound" class="no-results-card" style="display: none;">
        <div class="no-results-icon">
            <span class="material-icons">person_search</span>
        </div>
        <h3><?php echo __('no_farmers_found'); ?></h3>
        <p><?php echo __('no_nearby_farmers'); ?></p>
        <div class="no-results-suggestions">
            <p><?php echo __('try_these'); ?>:</p>
            <ul>
                <li><span class="material-icons">expand_more</span> <?php echo __('increase_distance'); ?></li>
                <li><span class="material-icons">search_off</span> <?php echo __('clear_search_filters'); ?></li>
                <li><span class="material-icons">location_on</span> <?php echo __('update_your_location'); ?></li>
            </ul>
        </div>
    </div>

    <!-- Load More Button -->
    <div id="loadMoreFarmers" class="load-more-section" style="display: none;">
        <button type="button" id="loadMoreFarmersBtn" class="btn btn-outline load-more-btn">
            <span class="material-icons">expand_more</span>
            <?php echo __('load_more'); ?>
        </button>
    </div>
</div>

<!-- Officer Network Tab -->
<div class="tab-content" id="officers-tab" style="display: <?php echo $activeTab === 'officers' ? 'block' : 'none'; ?>;">
    <h2 class="section-title">
        <span class="material-icons" style="vertical-align: middle;">supervised_user_circle</span>
        <?php echo __('officer_network'); ?>
        <span id="officersCount" class="count-badge" style="background: var(--info); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; margin-left: 0.5rem;">0</span>
    </h2>

    <!-- Search Form -->
    <div class="search-section" style="margin: 2rem 0;">
        <form id="officersSearchForm" class="search-form">
            <div class="search-input-group" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <span class="material-icons search-icon">search</span>
                <input type="text" id="officersSearch" name="search" placeholder="<?php echo __('search_by_name_location'); ?>" class="search-input" style="flex: 1; min-width: 150px;">
                <select id="officerRegionFilter" class="form-control" style="max-width: 150px;">
                    <option value=""><?php echo __('all_regions'); ?></option>
                </select>
                <button type="submit" class="btn btn-small">
                    <span class="material-icons">search</span>
                </button>
                <button type="button" id="clearOfficersSearch" class="btn btn-small btn-secondary" style="display: none;"><?php echo __('clear'); ?></button>
            </div>
        </form>
    </div>

    <!-- Loading indicator -->
    <div id="officersLoader" class="loader" style="display: none; text-align: center; padding: 20px;">
        <span class="material-icons rotating" style="animation: spin 1s linear infinite;">refresh</span> <?php echo __('loading_officers'); ?>
    </div>

    <!-- Officers container -->
    <div class="officers-grid" id="officersContainer">
        <!-- Officers will be loaded dynamically -->
    </div>

    <div id="noOfficersFound" style="display: none;" class="notice notice-info">
        <p><?php echo __('no_officers_found'); ?></p>
    </div>
</div>

<!-- Bookmarks Tab -->
<div class="tab-content" id="bookmarks-tab" style="display: <?php echo $activeTab === 'bookmarks' ? 'block' : 'none'; ?>;">
    <h2 class="section-title">
        <span class="material-icons" style="vertical-align: middle;">bookmarks</span>
        <?php echo __('my_bookmarked_posts'); ?>
    </h2>

    <!-- Loading indicator -->
    <div id="bookmarksLoader" class="loader" style="display: none; text-align: center; padding: 20px;">
        <span class="material-icons rotating" style="animation: spin 1s linear infinite;">refresh</span> <?php echo __('loading_bookmarks'); ?>
    </div>

    <!-- Bookmarks container -->
    <div class="posts-container" id="bookmarksContainer">
        <!-- Bookmarks will be loaded dynamically -->
    </div>

    <div id="noBookmarks" style="display: none;" class="notice notice-info">
        <p><?php echo __('no_bookmarks_yet'); ?></p>
    </div>
</div>

<!-- Post Detail Modal -->
<div id="postModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <span class="close-modal" onclick="closePostModal()">&times;</span>
        <div id="postModalContent">
            <!-- Post detail will be loaded here -->
        </div>
    </div>
</div>

<!-- Edit Post Modal -->
<div id="editPostModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeEditPostModal()">&times;</span>
        <h2><span class="material-icons">edit</span> <?php echo __('edit_post'); ?></h2>
        <form id="editPostForm">
            <input type="hidden" id="editPostId" name="postId">
            <div class="form-group">
                <label for="editTitle"><?php echo __('title'); ?> *</label>
                <input type="text" id="editTitle" name="title" required>
            </div>
            <div class="form-group">
                <label for="editCategory"><?php echo __('category'); ?></label>
                <select id="editCategory" name="category">
                    <option value="General Discussion"><?php echo __('general_discussion'); ?></option>
                    <option value="Crop Problems"><?php echo __('crop_problems'); ?></option>
                    <option value="Best Practices"><?php echo __('best_practices'); ?></option>
                    <option value="Market Updates"><?php echo __('market_updates'); ?></option>
                    <option value="Weather Discussion"><?php echo __('weather_discussion'); ?></option>
                    <option value="Pest Control"><?php echo __('pest_control'); ?></option>
                    <option value="Fertilizer Tips"><?php echo __('fertilizer_tips'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="editContent"><?php echo __('content'); ?> *</label>
                <textarea id="editContent" name="content" required rows="6"></textarea>
            </div>
            <div class="form-group" style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn"><?php echo __('save_changes'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeEditPostModal()"><?php echo __('cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="closeShareModal()">&times;</span>
        <h2><span class="material-icons">share</span> <?php echo __('share_post'); ?></h2>
        <div id="shareOptions" style="padding: 1rem 0;">
            <p><?php echo __('share_this_post'); ?></p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                <button class="btn btn-small" onclick="shareToWhatsApp()">
                    <span class="material-icons">whatsapp</span> <?php echo __('whatsapp'); ?>
                </button>
                <button class="btn btn-small" onclick="shareToFacebook()">
                    <span class="material-icons">facebook</span> <?php echo __('facebook'); ?>
                </button>
                <button class="btn btn-small" onclick="copyShareLink()">
                    <span class="material-icons">link</span> <?php echo __('copy_link'); ?>
                </button>
            </div>
            <div style="margin-top: 1rem;">
                <label><?php echo __('share_url'); ?>:</label>
                <input type="text" id="shareUrl" readonly style="width: 100%; padding: 0.5rem; border-radius: 4px; border: 1px solid #ddd;">
            </div>
        </div>
    </div>
</div>

<style>
.rotating {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.tab-content {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.filter-select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
}

.stat-value {
    color: #4caf50;
    font-weight: bold;
}

.post-card {
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 1rem;
    position: relative;
}

.post-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.modal {
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: #fff;
    margin: auto;
    padding: 0;
    border: none;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlideUp 0.3s ease;
    overflow: hidden;
}

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-content h2,
.modal-content h3 {
    margin: 0;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, var(--primary, #557A46) 0%, #3d5a32 100%);
    color: white;
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-content h2 .material-icons,
.modal-content h3 .material-icons {
    font-size: 1.5rem;
}

.modal-content form {
    padding: 1.5rem;
}

.modal-content .form-group {
    margin-bottom: 1.25rem;
}

.modal-content .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.modal-content .form-group input,
.modal-content .form-group textarea,
.modal-content .form-group select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.modal-content .form-group input:focus,
.modal-content .form-group textarea:focus,
.modal-content .form-group select:focus {
    outline: none;
    border-color: var(--primary, #557A46);
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.1);
}

.modal-content .btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.modal-content .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.close-modal {
    color: rgba(255,255,255,0.7);
    position: absolute;
    right: 1rem;
    top: 0.85rem;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
    z-index: 1;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
}

.close-modal:hover,
.close-modal:focus {
    color: #fff;
    background: rgba(255,255,255,0.2);
}

.like-btn {
    transition: all 0.3s;
}

.like-btn.liked .material-icons {
    color: #e91e63;
}

.bookmark-btn.bookmarked .material-icons {
    color: #ffc107;
}

.helpful-btn, .unhelpful-btn {
    transition: all 0.3s;
}

.helpful-btn.voted .material-icons {
    color: #4caf50;
}

.unhelpful-btn.voted .material-icons {
    color: #f44336;
}

.post-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 8px;
}

.post-actions button {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.post-content {
    padding: 0 1.5rem;
}

.post-meta {
    padding: 0 1.5rem;
}

.comment-section {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 12px;
}

.comment-section h4 {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.comment-section h4 .material-icons {
    color: var(--primary, #557A46);
}

.comment-form {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.comment-form textarea {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    resize: none;
    font-size: 0.95rem;
    min-height: 80px;
    transition: border-color 0.2s;
}

.comment-form textarea:focus {
    outline: none;
    border-color: var(--primary, #557A46);
}

.comment-item {
    padding: 1rem;
    background: white;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.comment-item:hover {
    border-color: var(--primary, #557A46);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #333;
}

.comment-author-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary, #557A46) 0%, #8FBC46 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    font-weight: 600;
}

.comment-date {
    font-size: 0.8rem;
    color: #888;
}

.comment-content {
    margin: 0.5rem 0;
    color: #555;
    line-height: 1.6;
    padding-left: 2.5rem;
}

.comment-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-left: 2.5rem;
}

.btn-danger {
    background-color: #f44336;
    color: white;
}

.btn-danger:hover {
    background-color: #d32f2f;
}

.pagination-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    margin: 2rem 0;
    padding: 1.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem;
    border: 2px solid #e0e0e0;
    background: #fff;
    color: #333;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.pagination-btn:hover:not(:disabled) {
    background: #557A46;
    color: white;
    border-color: #557A46;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(85, 122, 70, 0.3);
}

.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #f5f5f5;
    border-color: #e0e0e0;
}

.page-numbers {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.page-number {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem 0.75rem;
    border: 2px solid #e0e0e0;
    background: #fff;
    color: #333;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 0.95rem;
}

.page-number:hover {
    background: #f8f9fa;
    border-color: #557A46;
    transform: translateY(-1px);
}

.page-number.active {
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    color: white;
    border-color: #557A46;
    box-shadow: 0 4px 12px rgba(85, 122, 70, 0.4);
    font-weight: 600;
}

.page-ellipsis {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    color: #999;
    font-weight: bold;
}

.pagination-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #666;
    font-size: 0.9rem;
}

.pagination-separator {
    color: #ccc;
}

#pageInfo {
    font-weight: 600;
    color: #333;
}

#resultsInfo {
    color: #666;
}

@media (max-width: 768px) {
    .pagination {
        gap: 0.25rem;
    }
    
    .pagination-btn,
    .page-number {
        min-width: 36px;
        height: 36px;
        font-size: 0.85rem;
    }
    
    .pagination-btn span {
        font-size: 1.2rem;
    }
}

.reply-form {
    margin-top: 1rem;
    padding: 1rem;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.nested-comment {
    margin-left: 2rem;
    border-left: 2px solid #e0e0e0;
    padding-left: 1rem;
}

.badge-pinned {
    background: #ff9800;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.post-owner-badge {
    background: #2196f3;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

/* ===== Farmers Tab Modern Styles ===== */
.farmers-tab-header {
    margin-bottom: 1.5rem;
}

.farmers-tab-header .section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-badge {
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

/* Search & Filters Section */
.farmers-search-section {
    background: linear-gradient(135deg, #f8fdf5 0%, #fff 100%);
    border: 1px solid rgba(85, 122, 70, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(85, 122, 70, 0.08);
}

.search-filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 1rem;
    align-items: end;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input-wrapper .search-icon {
    position: absolute;
    right: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: #557A46;
    font-size: 1.3rem;
    z-index: 1;
    pointer-events: none;
}

.search-input-modern {
    width: 100%;
    padding: 0.875rem 3rem 0.875rem 3.2rem;
    border: 2px solid #e0e8d8;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.search-input-modern:focus {
    outline: none;
    border-color: #557A46;
    box-shadow: 0 0 0 4px rgba(85, 122, 70, 0.1);
}

.clear-search-btn {
    position: absolute;
    right: 0.75rem;
    background: #f0f0f0;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-search-btn:hover {
    background: #e0e0e0;
}

.clear-search-btn .material-icons {
    font-size: 18px;
    color: #666;
}

/* Distance Filter */
.distance-filter-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    color: #557A46;
    font-weight: 600;
}

.filter-label .material-icons {
    font-size: 1rem;
}

.distance-slider {
    -webkit-appearance: none;
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(to right, #8FBC46, #557A46);
    outline: none;
    cursor: pointer;
}

.distance-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 3px solid #557A46;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
}

.distance-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

/* Filter Select Dropdowns */
.crop-filter-wrapper,
.experience-filter-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid #e0e8d8;
    border-radius: 10px;
    background: white;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    color: #333;
}

.filter-select:focus {
    outline: none;
    border-color: #557A46;
}

/* Active Filters */
.active-filters {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(85, 122, 70, 0.15);
    flex-wrap: wrap;
}

.active-filters-label {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.filter-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.75rem;
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
}

.filter-tag .remove-tag {
    cursor: pointer;
    opacity: 0.8;
    font-size: 16px;
}

.filter-tag .remove-tag:hover {
    opacity: 1;
}

.clear-all-btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.75rem;
    background: transparent;
    border: 1px solid #dc3545;
    color: #dc3545;
    border-radius: 20px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-all-btn:hover {
    background: #dc3545;
    color: white;
}

.clear-all-btn .material-icons {
    font-size: 16px;
}

/* Results Info */
.farmers-results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.results-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #333;
}

.results-count .material-icons {
    color: #557A46;
}

.location-info {
    font-size: 0.85rem;
    color: #666;
}

/* Loading State */
.farmers-loader {
    display: flex;
    justify-content: center;
    padding: 3rem;
}

.loader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.loader-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #e0e8d8;
    border-top: 4px solid #557A46;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loader-content p {
    color: #666;
    font-size: 0.95rem;
}

/* Farmers Grid */
.farmers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

/* Modern Farmer Card */
.farmer-card-modern {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.farmer-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(85, 122, 70, 0.15);
}

.farmer-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, #f8fdf5 0%, #fff 100%);
    border-bottom: 1px solid rgba(85, 122, 70, 0.1);
}

.farmer-avatar-modern {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #557A46;
    box-shadow: 0 4px 10px rgba(85, 122, 70, 0.2);
}

.farmer-avatar-placeholder-modern {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 4px 10px rgba(85, 122, 70, 0.2);
}

.farmer-info-modern {
    flex: 1;
    min-width: 0;
}

.farmer-name-modern {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.experience-badge-modern {
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: capitalize;
}

.experience-badge-modern.beginner {
    background: #e3f2fd;
    color: #1976d2;
}

.experience-badge-modern.intermediate {
    background: #fff3e0;
    color: #e65100;
}

.experience-badge-modern.expert {
    background: #e8f5e9;
    color: #2e7d32;
}

.farmer-location-modern {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

.farmer-location-modern .material-icons {
    font-size: 16px;
    color: #557A46;
}

.distance-badge-modern {
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    color: white;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.farmer-card-body {
    padding: 1rem 1.25rem;
}

.farmer-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.farmer-detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #555;
    font-size: 0.9rem;
}

.farmer-detail-item .material-icons {
    font-size: 18px;
    color: #8FBC46;
}

.farmer-crops-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.5rem;
}

.crop-tag {
    background: #f0f7eb;
    color: #557A46;
    padding: 0.25rem 0.6rem;
    border-radius: 15px;
    font-size: 0.75rem;
}

.farmer-card-footer {
    display: flex;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.farmer-card-footer .btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
}

.farmer-card-footer .btn .material-icons {
    font-size: 18px;
}

.btn-profile {
    background: linear-gradient(135deg, #557A46 0%, #8FBC46 100%);
    color: white;
    border: none;
}

.btn-profile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(85, 122, 70, 0.3);
}

.btn-message {
    background: white;
    color: #557A46;
    border: 2px solid #557A46;
}

.btn-message:hover {
    background: #557A46;
    color: white;
}

.btn-call {
    background: #4caf50;
    color: white;
    border: none;
    padding: 0.6rem !important;
    flex: 0 !important;
    min-width: 42px;
}

.btn-call:hover {
    background: #388e3c;
}

/* No Results Card */
.no-results-card {
    background: linear-gradient(135deg, #fff 0%, #f8fdf5 100%);
    border: 2px dashed rgba(85, 122, 70, 0.3);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
}

.no-results-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #f0f7eb 0%, #e8f5e9 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.no-results-icon .material-icons {
    font-size: 40px;
    color: #557A46;
}

.no-results-card h3 {
    color: #333;
    margin-bottom: 0.5rem;
}

.no-results-card > p {
    color: #666;
    margin-bottom: 1.5rem;
}

.no-results-suggestions {
    text-align: left;
    max-width: 300px;
    margin: 0 auto;
}

.no-results-suggestions p {
    color: #555;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.no-results-suggestions ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.no-results-suggestions li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
    padding: 0.5rem 0;
    font-size: 0.9rem;
}

.no-results-suggestions li .material-icons {
    font-size: 18px;
    color: #8FBC46;
}

/* Load More Section */
.load-more-section {
    text-align: center;
    margin-top: 2rem;
}

.load-more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 2rem;
    border: 2px solid #557A46;
    background: white;
    color: #557A46;
    border-radius: 30px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.load-more-btn:hover {
    background: #557A46;
    color: white;
    transform: translateY(-2px);
}

/* Responsive Styles */
@media (max-width: 992px) {
    .search-filters-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .search-input-wrapper {
        grid-column: span 2;
    }
}

@media (max-width: 576px) {
    .search-filters-grid {
        grid-template-columns: 1fr;
    }
    
    .search-input-wrapper {
        grid-column: span 1;
    }
    
    .farmers-grid {
        grid-template-columns: 1fr;
    }
    
    .farmer-card-footer {
        flex-wrap: wrap;
    }
    
    .farmer-card-footer .btn {
        flex: 1 1 calc(50% - 0.375rem);
    }
    
    .farmers-results-info {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
}
</style>

<script>
// Pass base URL and user ID to JavaScript
window.communityBaseUrl = '<?php echo $base_url; ?>';
window.currentUserId = <?php echo $user['user_id']; ?>;

// Pass translations to JavaScript
window.translations = {
    post_created_success: '<?php echo __('post_created_success'); ?>',
    post_updated_success: '<?php echo __('post_updated_success'); ?>',
    failed_create_post: '<?php echo __('failed_create_post'); ?>',
    failed_update_post: '<?php echo __('failed_update_post'); ?>',
    failed_load_posts: '<?php echo __('failed_load_posts'); ?>',
    empty_response_login: '<?php echo __('empty_response_login'); ?>',
    no_posts_filter: '<?php echo __('no_posts_filter'); ?>',
    failed_load_post_details: '<?php echo __('failed_load_post_details'); ?>',
    comments: '<?php echo __('comments'); ?>',
    post_comment: '<?php echo __('post_comment'); ?>',
    comment_added: '<?php echo __('comment_added'); ?>',
    write_your_reply: '<?php echo __('write_your_reply'); ?>',
    post_reply: '<?php echo __('post_reply'); ?>',
    cancel: '<?php echo __('cancel'); ?>',
    please_write_reply: '<?php echo __('please_write_reply'); ?>',
    reply_posted_success: '<?php echo __('reply_posted_success'); ?>',
    comment_deleted_success: '<?php echo __('comment_deleted_success'); ?>',
    failed_delete_comment: '<?php echo __('failed_delete_comment'); ?>',
    confirm_delete_comment: '<?php echo __('confirm_delete_comment'); ?>',
    marked_helpful: '<?php echo __('marked_helpful'); ?>',
    marked_not_helpful: '<?php echo __('marked_not_helpful'); ?>',
    bookmarked: '<?php echo __('bookmarked'); ?>',
    bookmark_removed: '<?php echo __('bookmark_removed'); ?>',
    just_now: '<?php echo __('just_now'); ?>',
    minute: '<?php echo __('minute'); ?>',
    minutes: '<?php echo __('minutes'); ?>',
    hour: '<?php echo __('hour'); ?>',
    hours: '<?php echo __('hours'); ?>',
    yesterday: '<?php echo __('yesterday'); ?>',
    day: '<?php echo __('day'); ?>',
    days: '<?php echo __('days'); ?>',
    ago: '<?php echo __('ago'); ?>',
    message: '<?php echo __('message'); ?>',
    view_profile: '<?php echo __('view_profile'); ?>',
    contact: '<?php echo __('contact'); ?>',
    messaging_coming_soon: '<?php echo __('messaging_coming_soon'); ?>',
    contact_coming_soon: '<?php echo __('contact_coming_soon'); ?>',
    location_not_set: '<?php echo __('location_not_set'); ?>',
    location_not_specified: '<?php echo __('location_not_specified'); ?>',
    agricultural_officer: '<?php echo __('agricultural_officer'); ?>',
    pinned: '<?php echo __('pinned'); ?>',
    by: '<?php echo __('by'); ?>',
    in: '<?php echo __('in'); ?>',
    km: '<?php echo __('km'); ?>',
    no_trending_topics: '<?php echo __('no_trending_topics'); ?>',
    unknown_error: '<?php echo __('unknown_error'); ?>',
    showing: '<?php echo __('showing'); ?>',
    of: '<?php echo __('of'); ?>',
    page: '<?php echo __('page'); ?>',
    likes: '<?php echo __('likes'); ?>',
    reply: '<?php echo __('reply'); ?>',
    edit: '<?php echo __('edit'); ?>',
    delete: '<?php echo __('delete'); ?>',
    post: '<?php echo __('post'); ?>',
    error: '<?php echo __('error'); ?>',
    please_enter_comment: '<?php echo __('please_enter_comment'); ?>',
    failed_add_comment: '<?php echo __('failed_add_comment'); ?>',
    post_id_not_found: '<?php echo __('post_id_not_found'); ?>',
    server_error: '<?php echo __('server_error'); ?>',
    network_error: '<?php echo __('network_error'); ?>',
    failed_delete_comment_short: '<?php echo __('failed_delete_comment_short'); ?>',
    invalid_response: '<?php echo __('invalid_response'); ?>',
    // Farmers tab translations
    location_based_results: '<?php echo __('location_based_results'); ?>',
    region: '<?php echo __('region'); ?>',
    acres: '<?php echo __('acres'); ?>',
    load_more: '<?php echo __('load_more'); ?>'
};
</script>
<script src="<?php echo $base_url; ?>public/js/community.js"></script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
