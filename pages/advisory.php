<?php
/**
 * Advisory Management - For Agricultural Officers
 * Create and manage farming advisories with advanced features
 * Supports English and Bengali
 */

// Authentication and role check
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    redirect('home');
}

include __DIR__ . '/../layouts/header.php';

$db = new Database();
$officerId = $_SESSION['user_id'];

// Get filter parameters
$filterCategory = $_GET['category'] ?? 'all';
$filterCrop = $_GET['crop'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';

// Build query for advisories
$whereClause = "WHERE a.created_by = ?";
$params = [$officerId];

if ($filterCategory !== 'all') {
    $whereClause .= " AND a.advisory_type = ?";
    $params[] = $filterCategory;
}

if ($filterCrop !== 'all') {
    $whereClause .= " AND a.target_crops LIKE ?";
    $params[] = "%$filterCrop%";
}

if ($filterStatus !== 'all') {
    $whereClause .= " AND a.is_active = ?";
    $params[] = $filterStatus === 'active' ? 1 : 0;
}

// Get advisories with filters
$advisories = $db->resultSet("SELECT a.*, 
    DATE_FORMAT(a.created_at, '%M %d, %Y') as formatted_date,
    DATE_FORMAT(a.created_at, '%h:%i %p') as formatted_time
    FROM advisories a
    $whereClause
    ORDER BY a.created_at DESC LIMIT 50", $params);

// Get statistics
$totalAdvisories = $db->single("SELECT COUNT(*) as count FROM advisories WHERE created_by = ?", [$officerId])['count'] ?? 0;
$activeAdvisories = $db->single("SELECT COUNT(*) as count FROM advisories WHERE created_by = ? AND is_active = 1", [$officerId])['count'] ?? 0;
$advisoriesThisMonth = $db->single("SELECT COUNT(*) as count FROM advisories WHERE created_by = ? AND MONTH(created_at) = MONTH(CURRENT_DATE())", [$officerId])['count'] ?? 0;

// Get category distribution
$categoryStats = $db->resultSet("SELECT advisory_type, COUNT(*) as count FROM advisories WHERE created_by = ? GROUP BY advisory_type ORDER BY count DESC LIMIT 5", [$officerId]);

// Crop options
$crops = ['Rice', 'Wheat', 'Corn', 'Potato', 'Tomato', 'Onion', 'Garlic', 'Jute', 'Sugarcane', 'Tea', 'Mango', 'Banana', 'Chili', 'Brinjal'];

// Category options with icons
$categories = [
    'planting' => ['icon' => 'eco', 'color' => '#28a745'],
    'irrigation' => ['icon' => 'water_drop', 'color' => '#17a2b8'],
    'fertilizer' => ['icon' => 'science', 'color' => '#6f42c1'],
    'pest' => ['icon' => 'bug_report', 'color' => '#fd7e14'],
    'disease' => ['icon' => 'coronavirus', 'color' => '#dc3545'],
    'harvest' => ['icon' => 'agriculture', 'color' => '#ffc107'],
    'post_harvest' => ['icon' => 'inventory', 'color' => '#6c757d'],
    'weather' => ['icon' => 'wb_cloudy', 'color' => '#4facfe'],
    'market' => ['icon' => 'trending_up', 'color' => '#20c997'],
    'general' => ['icon' => 'info', 'color' => '#557a46']
];

$regions = ['Dhaka', 'Chittagong', 'Khulna', 'Rangpur', 'Sylhet', 'Barisal', 'Rajshahi', 'Mymensingh'];
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
            <span class="material-icons">assignment</span>
            <span><?php echo __('officer'); ?></span>
        </div>
        <h1>
            <span class="material-icons" style="font-size: 2rem;">assignment</span>
            <?php echo __('advisory_management'); ?>
        </h1>
        <p class="hero-subtitle"><?php echo __('create_publish_advisories_desc'); ?></p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">library_books</span>
            <span><?php echo $totalAdvisories; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">check_circle</span>
            <span><?php echo $activeAdvisories; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">eco</span>
        </div>
    </div>
</section>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-icon">
            <span class="material-icons">library_books</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalAdvisories; ?></h3>
            <p><?php echo __('total_advisories'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-active">
        <div class="stat-icon">
            <span class="material-icons">check_circle</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $activeAdvisories; ?></h3>
            <p><?php echo __('active_advisories'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-month">
        <div class="stat-icon">
            <span class="material-icons">calendar_month</span>
        </div>
        <div class="stat-info">
            <h3><?php echo $advisoriesThisMonth; ?></h3>
            <p><?php echo __('this_month'); ?></p>
        </div>
    </div>

    <div class="stat-card stat-views">
        <div class="stat-icon">
            <span class="material-icons">visibility</span>
        </div>
        <div class="stat-info">
            <h3><?php echo count($categoryStats); ?></h3>
            <p><?php echo __('categories_covered'); ?></p>
        </div>
    </div>
</div>

<!-- Quick Category Buttons -->
<div class="category-buttons mb-4">
    <?php foreach (array_slice($categories, 0, 6) as $key => $cat): ?>
    <button class="category-btn" style="--cat-color: <?php echo $cat['color']; ?>;" onclick="selectCategory('<?php echo $key; ?>')">
        <span class="material-icons"><?php echo $cat['icon']; ?></span>
        <?php echo __(str_replace('_', ' ', $key)); ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- Create Advisory Form -->
<div class="card create-advisory-card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">note_add</span>
            <?php echo __('create_new_advisory'); ?>
        </h3>
    </div>
    <form id="advisoryForm" class="card-body">
        <div class="form-row">
            <div class="form-group flex-2">
                <label for="advisoryTitle"><?php echo __('advisory_title'); ?> *</label>
                <input type="text" id="advisoryTitle" name="title" placeholder="<?php echo __('eg_best_practices'); ?>" required>
            </div>
            <div class="form-group flex-1">
                <label for="advisoryType"><?php echo __('category'); ?> *</label>
                <select id="advisoryType" name="advisoryType" required>
                    <option value=""><?php echo __('select_category'); ?></option>
                    <?php foreach ($categories as $key => $cat): ?>
                    <option value="<?php echo $key; ?>"><?php echo __(str_replace('_', ' ', $key)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="targetCrops"><?php echo __('target_crops'); ?></label>
                <input type="text" id="targetCrops" name="targetCrops" placeholder="<?php echo __('eg_rice_wheat'); ?>">
            </div>
            <div class="form-group flex-1">
                <label for="targetRegion"><?php echo __('target_region'); ?></label>
                <select id="targetRegion" name="targetRegion">
                    <option value=""><?php echo __('all_regions'); ?></option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-1">
                <label for="priority"><?php echo __('priority'); ?></label>
                <select id="priority" name="priority">
                    <option value="low"><?php echo __('low'); ?></option>
                    <option value="medium" selected><?php echo __('medium'); ?></option>
                    <option value="high"><?php echo __('high'); ?></option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="content"><?php echo __('advisory_content'); ?> *</label>
            <textarea id="content" name="content" rows="6" placeholder="<?php echo __('detailed_advisory_info'); ?>" required></textarea>
            <small class="char-count"><span id="contentCharCount">0</span>/2000 <?php echo __('characters'); ?></small>
        </div>

        <div class="form-row">
            <div class="form-group flex-1">
                <label for="validFrom"><?php echo __('valid_from'); ?></label>
                <input type="date" id="validFrom" name="validFrom">
            </div>
            <div class="form-group flex-1">
                <label for="validTo"><?php echo __('valid_to'); ?></label>
                <input type="date" id="validTo" name="validTo">
            </div>
        </div>

        <div class="form-options">
            <label class="checkbox-option">
                <input type="checkbox" name="is_active" id="isActive" checked>
                <span class="material-icons">public</span>
                <?php echo __('publish_immediately'); ?>
            </label>
            <label class="checkbox-option">
                <input type="checkbox" name="notify_farmers" id="notifyFarmers">
                <span class="material-icons">notifications</span>
                <?php echo __('notify_farmers'); ?>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span class="material-icons">publish</span>
                <?php echo __('publish_advisory'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="previewAdvisory()">
                <span class="material-icons">visibility</span>
                <?php echo __('preview'); ?>
            </button>
            <button type="reset" class="btn btn-outline">
                <span class="material-icons">clear</span>
                <?php echo __('clear'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter Bar -->
<div class="card filter-card mb-4">
    <div class="filter-bar">
        <div class="filters">
            <div class="filter-group">
                <label><?php echo __('category'); ?></label>
                <select id="filterCategory" onchange="applyFilters()">
                    <option value="all"><?php echo __('all'); ?></option>
                    <?php foreach ($categories as $key => $cat): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filterCategory === $key ? 'selected' : ''; ?>>
                        <?php echo __(str_replace('_', ' ', $key)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><?php echo __('crop'); ?></label>
                <select id="filterCrop" onchange="applyFilters()">
                    <option value="all"><?php echo __('all_crops'); ?></option>
                    <?php foreach ($crops as $crop): ?>
                    <option value="<?php echo $crop; ?>" <?php echo $filterCrop === $crop ? 'selected' : ''; ?>><?php echo $crop; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><?php echo __('status'); ?></label>
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="all"><?php echo __('all'); ?></option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button class="btn btn-outline btn-sm" onclick="clearFilters()">
                <span class="material-icons">clear_all</span>
                <?php echo __('clear_filters'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Advisories List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-icons">library_books</span>
            <?php echo __('my_published_advisories'); ?>
            <span class="badge"><?php echo count($advisories); ?></span>
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($advisories)): ?>
            <div class="empty-state">
                <span class="material-icons">description</span>
                <h3><?php echo __('no_advisories_published'); ?></h3>
                <p><?php echo __('create_first_advisory'); ?></p>
            </div>
        <?php else: ?>
            <div class="advisories-list">
                <?php foreach ($advisories as $advisory): ?>
                    <?php 
                    $catKey = $advisory['advisory_type'] ?? 'general';
                    $catInfo = $categories[$catKey] ?? $categories['general'];
                    ?>
                    <div class="advisory-item" data-id="<?php echo $advisory['advisory_id']; ?>" style="--cat-color: <?php echo $catInfo['color']; ?>;">
                        <div class="advisory-header">
                            <div class="advisory-title-section">
                                <span class="material-icons advisory-icon"><?php echo $catInfo['icon']; ?></span>
                                <div class="advisory-title-info">
                                    <h4><?php echo htmlspecialchars($advisory['title']); ?></h4>
                                    <div class="advisory-meta-inline">
                                        <span class="category-badge" style="background: <?php echo $catInfo['color']; ?>;">
                                            <?php echo ucfirst(str_replace('_', ' ', $catKey)); ?>
                                        </span>
                                        <?php if (!empty($advisory['target_crops'])): ?>
                                        <span class="crop-tag">
                                            <span class="material-icons">eco</span>
                                            <?php echo htmlspecialchars($advisory['target_crops']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="advisory-badges">
                                <?php if ($advisory['is_active']): ?>
                                <span class="badge badge-success"><?php echo __('active'); ?></span>
                                <?php else: ?>
                                <span class="badge badge-secondary"><?php echo __('inactive'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="advisory-content-preview"><?php echo htmlspecialchars(substr($advisory['content'], 0, 180)) . (strlen($advisory['content']) > 180 ? '...' : ''); ?></p>
                        
                        <div class="advisory-footer">
                            <div class="advisory-meta">
                                <span class="meta-item">
                                    <span class="material-icons">schedule</span>
                                    <?php echo $advisory['formatted_date']; ?>
                                </span>
                                <?php if (!empty($advisory['target_region'])): ?>
                                <span class="meta-item">
                                    <span class="material-icons">location_on</span>
                                    <?php echo htmlspecialchars($advisory['target_region']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="advisory-actions">
                                <button class="btn-icon" onclick="viewAdvisory(<?php echo $advisory['advisory_id']; ?>)" title="<?php echo __('view'); ?>">
                                    <span class="material-icons">visibility</span>
                                </button>
                                <button class="btn-icon" onclick="editAdvisory(<?php echo $advisory['advisory_id']; ?>)" title="<?php echo __('edit'); ?>">
                                    <span class="material-icons">edit</span>
                                </button>
                                <button class="btn-icon" onclick="toggleAdvisory(<?php echo $advisory['advisory_id']; ?>, <?php echo $advisory['is_active'] ? 0 : 1; ?>)" title="<?php echo $advisory['is_active'] ? __('deactivate') : __('activate'); ?>">
                                    <span class="material-icons"><?php echo $advisory['is_active'] ? 'visibility_off' : 'visibility'; ?></span>
                                </button>
                                <button class="btn-icon btn-danger" onclick="deleteAdvisory(<?php echo $advisory['advisory_id']; ?>)" title="<?php echo __('delete'); ?>">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div id="advisoriesPaginationContainer" class="pagination-wrapper" style="display: none;">
                <div class="pagination">
                    <button id="advisoriesFirstPageBtn" class="pagination-btn" title="<?php echo __('first_page'); ?>">
                        <span class="material-icons">first_page</span>
                    </button>
                    <button id="advisoriesPrevPageBtn" class="pagination-btn" title="<?php echo __('previous_page'); ?>">
                        <span class="material-icons">chevron_left</span>
                    </button>
                    <div id="advisoriesPageNumbers" class="page-numbers"></div>
                    <button id="advisoriesNextPageBtn" class="pagination-btn" title="<?php echo __('next_page'); ?>">
                        <span class="material-icons">chevron_right</span>
                    </button>
                    <button id="advisoriesLastPageBtn" class="pagination-btn" title="<?php echo __('last_page'); ?>">
                        <span class="material-icons">last_page</span>
                    </button>
                </div>
                <div class="pagination-info">
                    <span id="advisoriesPageInfo"><?php echo __('page'); ?> 1 <?php echo __('of'); ?> 1</span>
                    <span class="pagination-separator">•</span>
                    <span id="advisoriesResultsInfo"><?php echo __('showing'); ?> 0 <?php echo __('advisories'); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Modal -->
<div class="modal" id="viewModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">assignment</span> <?php echo __('advisory_details'); ?></h3>
            <button class="close-modal" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewContent">
            <!-- Content loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewModal')"><?php echo __('close'); ?></button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">edit</span> <?php echo __('edit_advisory'); ?></h3>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editAdvisoryForm">
            <input type="hidden" id="editAdvisoryId" name="advisoryId">
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo __('advisory_title'); ?></label>
                    <input type="text" id="editTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label><?php echo __('advisory_content'); ?></label>
                    <textarea id="editContent" name="content" rows="6" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label><?php echo __('category'); ?></label>
                        <select id="editType" name="advisoryType">
                            <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?php echo $key; ?>"><?php echo __(str_replace('_', ' ', $key)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group flex-1">
                        <label><?php echo __('priority'); ?></label>
                        <select id="editPriority" name="priority">
                            <option value="low"><?php echo __('low'); ?></option>
                            <option value="medium"><?php echo __('medium'); ?></option>
                            <option value="high"><?php echo __('high'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><span class="material-icons">visibility</span> <?php echo __('preview'); ?></h3>
            <button class="close-modal" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <!-- Preview content -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('previewModal')"><?php echo __('close'); ?></button>
            <button class="btn btn-primary" onclick="submitFromPreview()"><?php echo __('publish_advisory'); ?></button>
        </div>
    </div>
</div>

<style>
/* Advisory Page Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-icon .material-icons { font-size: 28px; }

.stat-total .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-active .stat-icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-month .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-views .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.stat-info h3 { font-size: 1.75rem; font-weight: 700; margin: 0; color: var(--text-primary); }
.stat-info p { font-size: 0.875rem; color: var(--text-secondary); margin: 0; }

/* Category Buttons */
.category-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.category-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: 2px solid var(--border-color);
    border-radius: 25px;
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.category-btn:hover {
    border-color: var(--cat-color);
    background: rgba(85, 122, 70, 0.1);
    color: var(--cat-color);
    transform: translateY(-2px);
}

/* Form Styles */
.create-advisory-card { border-top: 4px solid var(--primary-color); }

.form-row {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.form-group { margin-bottom: 1rem; }
.form-group.flex-1 { flex: 1; min-width: 180px; }
.form-group.flex-2 { flex: 2; min-width: 280px; }

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #ccc;
    border-radius: 8px;
    background: #fff;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.15);
}

.form-options {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    padding: 1.25rem 1.5rem;
    background: var(--bg-hover);
    border-radius: 10px;
    margin-bottom: 1.25rem;
    border: 1px solid var(--border-color);
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    font-size: 0.95rem;
}

.checkbox-option input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary-color);
}

.checkbox-option .material-icons {
    font-size: 1.35rem;
    color: var(--primary-color);
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.char-count {
    display: block;
    text-align: right;
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-top: 0.35rem;
}

/* Filter Bar */
.filter-card { padding: 1rem 1.5rem; }

.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.filters {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.filter-group select {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid #ccc;
    background: #fff;
    min-width: 140px;
}

/* Advisory Items */
.advisories-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.advisory-item {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--cat-color);
    border-radius: 10px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.advisory-item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.advisory-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.advisory-title-section {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.advisory-icon {
    font-size: 1.75rem;
    color: var(--cat-color);
}

.advisory-title-info h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.advisory-meta-inline {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    margin-top: 0.35rem;
}

.category-badge {
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.crop-tag {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.crop-tag .material-icons { font-size: 0.9rem; }

.advisory-content-preview {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0.5rem 0;
}

.advisory-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    margin-top: 0.75rem;
}

.advisory-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.meta-item .material-icons { font-size: 1rem; }

.advisory-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    background: var(--bg-hover);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-icon:hover { background: var(--primary-color); color: white; }
.btn-icon.btn-danger:hover { background: #dc3545; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-state .material-icons {
    font-size: 5rem;
    color: var(--text-muted);
    opacity: 0.4;
}

.empty-state h3 { margin: 1rem 0 0.5rem; color: var(--text-primary); }
.empty-state p { color: var(--text-secondary); }

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show { display: flex; animation: fadeIn 0.3s ease; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
    background: var(--bg-card, #fff);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-content.modal-lg { max-width: 700px; }

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-body { padding: 1.5rem; }

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* Responsive */
@media (max-width: 768px) {
    .form-row { flex-direction: column; }
    .form-group.flex-1, .form-group.flex-2 { min-width: 100%; }
    .category-buttons { justify-content: center; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filters { flex-direction: column; }
    .advisory-footer { flex-direction: column; gap: 0.75rem; }
}
</style>

<script>
var baseUrl = (typeof baseUrl !== 'undefined') ? baseUrl : '<?php echo $base_url; ?>';

// showNotification is now provided globally via footer.php

// Character counter
document.getElementById('content').addEventListener('input', function() {
    document.getElementById('contentCharCount').textContent = this.value.length;
});

// Select category from buttons
function selectCategory(cat) {
    document.getElementById('advisoryType').value = cat;
}

// Filter functions
function applyFilters() {
    const category = document.getElementById('filterCategory').value;
    const crop = document.getElementById('filterCrop').value;
    const status = document.getElementById('filterStatus').value;
    
    let url = baseUrl + 'advisory?';
    if (category !== 'all') url += 'category=' + category + '&';
    if (crop !== 'all') url += 'crop=' + crop + '&';
    if (status !== 'all') url += 'status=' + status + '&';
    
    window.location.href = url.replace(/&$/, '');
}

function clearFilters() {
    window.location.href = baseUrl + 'advisory';
}

// Form submission
document.getElementById('advisoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> <?php echo __('publishing'); ?>...';
    
    const formData = new FormData(this);
    formData.append('action', 'create_advisory');
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('advisory_published_success'); ?>', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="material-icons">publish</span> <?php echo __('publish_advisory'); ?>';
        }
    })
    .catch(error => {
        showNotification('<?php echo __('failed_publish_advisory'); ?>', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons">publish</span> <?php echo __('publish_advisory'); ?>';
    });
});

// Preview
function previewAdvisory() {
    const form = document.getElementById('advisoryForm');
    const title = form.title.value || '<?php echo __('no_title'); ?>';
    const type = form.advisoryType.value || 'general';
    const content = form.content.value || '<?php echo __('no_content'); ?>';
    
    document.getElementById('previewContent').innerHTML = `
        <div style="border-left: 4px solid var(--primary-color); padding: 1rem; background: var(--bg-hover); border-radius: 8px;">
            <h4 style="margin: 0 0 0.5rem;">${title}</h4>
            <span style="background: var(--primary-color); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem;">${type}</span>
            <p style="margin: 1rem 0; line-height: 1.6;">${content}</p>
        </div>
    `;
    openModal('previewModal');
}

function submitFromPreview() {
    closeModal('previewModal');
    document.getElementById('advisoryForm').dispatchEvent(new Event('submit'));
}

// View advisory
function viewAdvisory(id) {
    document.getElementById('viewContent').innerHTML = '<div style="text-align:center;padding:2rem;"><span class="material-icons" style="font-size:3rem;animation:spin 1s linear infinite;">sync</span></div>';
    openModal('viewModal');
    
    fetch(baseUrl + 'ajax/officer.php?action=get_advisory&advisoryId=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const a = data.advisory;
                document.getElementById('viewContent').innerHTML = `
                    <div>
                        <h3 style="margin: 0 0 1rem;">${a.title}</h3>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                            <span class="badge badge-primary">${a.advisory_type || 'general'}</span>
                            ${a.target_crops ? `<span><strong>Crops:</strong> ${a.target_crops}</span>` : ''}
                            ${a.target_region ? `<span><strong>Region:</strong> ${a.target_region}</span>` : ''}
                        </div>
                        <div style="background: var(--bg-hover); padding: 1rem; border-radius: 8px; line-height: 1.8;">${a.content.replace(/\n/g, '<br>')}</div>
                        <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                            Created: ${a.created_at}
                        </p>
                    </div>
                `;
            } else {
                document.getElementById('viewContent').innerHTML = '<p class="text-center text-danger">Failed to load advisory</p>';
            }
        })
        .catch(() => {
            document.getElementById('viewContent').innerHTML = '<p class="text-center text-danger">Error loading advisory</p>';
        });
}

// Edit advisory
function editAdvisory(id) {
    fetch(baseUrl + 'ajax/officer.php?action=get_advisory&advisoryId=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const a = data.advisory;
                document.getElementById('editAdvisoryId').value = a.advisory_id;
                document.getElementById('editTitle').value = a.title || '';
                document.getElementById('editContent').value = a.content || '';
                document.getElementById('editType').value = a.advisory_type || 'general';
                document.getElementById('editPriority').value = a.priority || 'medium';
                openModal('editModal');
            }
        })
        .catch(() => showNotification('Error loading advisory', 'error'));
}

// Edit form submission
document.getElementById('editAdvisoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_advisory');
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('<?php echo __('advisory_updated'); ?>', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || '<?php echo __('error_occurred'); ?>', 'error');
        }
    })
    .catch(() => showNotification('<?php echo __('error_occurred'); ?>', 'error'));
});

// Toggle advisory status
function toggleAdvisory(id, newStatus) {
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_advisory&advisoryId=' + id + '&is_active=' + newStatus
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Error', 'error');
        }
    });
}

// Delete advisory
function deleteAdvisory(id) {
    if (!confirm('<?php echo __('confirm_delete_advisory'); ?>')) return;
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_advisory&advisoryId=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-id="${id}"]`).remove();
            showNotification('<?php echo __('advisory_deleted'); ?>', 'success');
        } else {
            showNotification(data.message || 'Error', 'error');
        }
    });
}

// Modal functions
function openModal(modalId) { document.getElementById(modalId).classList.add('show'); }
function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});

// ===== Pagination for Advisories =====
const advisoriesPagination = {
    currentPage: 1,
    itemsPerPage: 10,
    totalItems: 0,
    totalPages: 0
};

document.addEventListener('DOMContentLoaded', function() {
    initAdvisoriesPagination();
});

function initAdvisoriesPagination() {
    const list = document.querySelector('.advisories-list');
    if (!list) return;
    
    const allItems = list.querySelectorAll('.advisory-item');
    advisoriesPagination.totalItems = allItems.length;
    advisoriesPagination.totalPages = Math.ceil(advisoriesPagination.totalItems / advisoriesPagination.itemsPerPage);
    
    const paginationContainer = document.getElementById('advisoriesPaginationContainer');
    if (paginationContainer && advisoriesPagination.totalPages > 1) {
        paginationContainer.style.display = 'flex';
        setupAdvisoriesPaginationListeners();
        showAdvisoriesPage(1);
    } else if (advisoriesPagination.totalItems > 0) {
        allItems.forEach(item => item.style.display = '');
    }
}

function setupAdvisoriesPaginationListeners() {
    document.getElementById('advisoriesFirstPageBtn')?.addEventListener('click', () => showAdvisoriesPage(1));
    document.getElementById('advisoriesPrevPageBtn')?.addEventListener('click', () => showAdvisoriesPage(advisoriesPagination.currentPage - 1));
    document.getElementById('advisoriesNextPageBtn')?.addEventListener('click', () => showAdvisoriesPage(advisoriesPagination.currentPage + 1));
    document.getElementById('advisoriesLastPageBtn')?.addEventListener('click', () => showAdvisoriesPage(advisoriesPagination.totalPages));
}

function showAdvisoriesPage(page) {
    page = Math.max(1, Math.min(page, advisoriesPagination.totalPages));
    advisoriesPagination.currentPage = page;
    
    const list = document.querySelector('.advisories-list');
    const allItems = list.querySelectorAll('.advisory-item');
    
    const startIndex = (page - 1) * advisoriesPagination.itemsPerPage;
    const endIndex = startIndex + advisoriesPagination.itemsPerPage;
    
    allItems.forEach((item, index) => {
        item.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    updateAdvisoriesPaginationControls();
}

function updateAdvisoriesPaginationControls() {
    const { currentPage, totalPages, totalItems, itemsPerPage } = advisoriesPagination;
    
    document.getElementById('advisoriesFirstPageBtn').disabled = currentPage === 1;
    document.getElementById('advisoriesPrevPageBtn').disabled = currentPage === 1;
    document.getElementById('advisoriesNextPageBtn').disabled = currentPage === totalPages;
    document.getElementById('advisoriesLastPageBtn').disabled = currentPage === totalPages;
    
    document.getElementById('advisoriesPageInfo').textContent = `<?php echo __('page'); ?> ${currentPage} <?php echo __('of'); ?> ${totalPages}`;
    
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('advisoriesResultsInfo').textContent = `<?php echo __('showing'); ?> ${startItem}-${endItem} <?php echo __('of'); ?> ${totalItems}`;
    
    generateAdvisoriesPageNumbers();
}

function generateAdvisoriesPageNumbers() {
    const container = document.getElementById('advisoriesPageNumbers');
    const { currentPage, totalPages } = advisoriesPagination;
    container.innerHTML = '';
    
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        container.appendChild(createAdvisoriesPageButton(1));
        if (startPage > 2) container.appendChild(createAdvisoriesEllipsis());
    }
    
    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createAdvisoriesPageButton(i));
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) container.appendChild(createAdvisoriesEllipsis());
        container.appendChild(createAdvisoriesPageButton(totalPages));
    }
}

function createAdvisoriesPageButton(pageNum) {
    const btn = document.createElement('button');
    btn.className = 'page-number' + (pageNum === advisoriesPagination.currentPage ? ' active' : '');
    btn.textContent = pageNum;
    btn.addEventListener('click', () => showAdvisoriesPage(pageNum));
    return btn;
}

function createAdvisoriesEllipsis() {
    const span = document.createElement('span');
    span.className = 'page-ellipsis';
    span.textContent = '...';
    return span;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
