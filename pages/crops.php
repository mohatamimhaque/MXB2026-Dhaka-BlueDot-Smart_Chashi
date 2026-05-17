<?php
if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();

// Get crop statistics
$stats = $db->single("SELECT 
    COUNT(*) as total_crops,
    SUM(area_hectares) as total_area,
    COUNT(CASE WHEN status = 'growing' THEN 1 END) as growing_count,
    COUNT(CASE WHEN status = 'planning' THEN 1 END) as planning_count,
    COUNT(CASE WHEN status = 'harvested' THEN 1 END) as harvested_count
    FROM crop_data WHERE farmer_id = ?", [$_SESSION['user_id']]);

$crops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY created_at DESC", [$_SESSION['user_id']]);
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
            <span class="material-icons">agriculture</span>
            <span><?php echo __('crop_management'); ?></span>
        </div>
        <h1>
            <span class="material-icons" style="font-size: 2rem;">agriculture</span>
            <?php echo __('my_crops'); ?>
        </h1>
        <p class="hero-subtitle"><?php echo __('track_manage_crops'); ?></p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">spa</span>
            <span><?php echo $stats['total_crops'] ?? 0; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">eco</span>
            <span><?php echo $stats['growing_count'] ?? 0; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">landscape</span>
        </div>
    </div>
</section>

<!-- Modern Statistics Cards -->
<div class="stats-grid-modern">
    <div class="stat-card-modern stat-gradient-purple">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">spa</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $stats['total_crops'] ?? 0; ?></div>
                <div class="stat-label-modern"><?php echo __('total_crops'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-red">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">landscape</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo format_number($stats['total_area'] ?? 0, 2); ?></div>
                <div class="stat-label-modern"><?php echo __('total_hectares'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-cyan">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">eco</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $stats['growing_count'] ?? 0; ?></div>
                <div class="stat-label-modern"><?php echo __('growing'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-green">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">calendar_today</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $stats['planning_count'] ?? 0; ?></div>
                <div class="stat-label-modern"><?php echo __('planning'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Add Crop Form (Collapsible) -->
<div class="card mt-3">
    <div class="card-header-action" onclick="toggleAddForm()">
        <h2><span class="material-icons" style="vertical-align: middle;">add_circle</span> <?php echo __('add_crop'); ?></h2>
        <span class="material-icons toggle-icon">expand_more</span>
    </div>
    <form id="cropForm" method="POST" class="crop-form" style="display: none;">
        <div class="form-row">
            <div class="form-group">
                <label for="cropName"><?php echo __('crop_name'); ?> *</label>
                <input type="text" id="cropName" name="cropName" placeholder="<?php echo __('rice_wheat_etc'); ?>" required>
            </div>

            <div class="form-group">
                <label for="variety"><?php echo __('variety'); ?></label>
                <input type="text" id="variety" name="variety" placeholder="<?php echo __('variety_eg'); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="area"><?php echo __('area_hectares'); ?> *</label>
                <input type="number" id="area" name="area" placeholder="0.00" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="plantedDate"><?php echo __('planted_date'); ?> *</label>
                <input type="date" id="plantedDate" name="plantedDate" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="status"><?php echo __('status'); ?> *</label>
                <select id="status" name="status" required>
                    <option value="planning"><?php echo __('planning'); ?></option>
                    <option value="growing" selected><?php echo __('growing'); ?></option>
                    <option value="harvesting"><?php echo __('harvesting'); ?></option>
                    <option value="harvested"><?php echo __('harvested'); ?></option>
                </select>
            </div>

            <div class="form-group">
                <label for="expectedHarvest"><?php echo __('expected_harvest'); ?></label>
                <input type="date" id="expectedHarvest" name="expectedHarvest">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons">add</span>
                <?php echo __('add_crop'); ?>
            </button>
            <button type="reset" class="btn btn-secondary">
                <span class="material-icons">refresh</span>
                <?php echo __('reset'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter and Search -->
<div class="card mt-3">
    <div class="filter-bar">
        <div class="search-box">
            <span class="material-icons">search</span>
            <input type="text" id="searchInput" placeholder="<?php echo __('search_crops'); ?>..." onkeyup="filterCrops()">
        </div>
        
        <div class="filter-group">
            <select id="statusFilter" onchange="filterCrops()">
                <option value=""><?php echo __('all_status'); ?></option>
                <option value="planning"><?php echo __('planning'); ?></option>
                <option value="growing"><?php echo __('growing'); ?></option>
                <option value="harvesting"><?php echo __('harvesting'); ?></option>
                <option value="harvested"><?php echo __('harvested'); ?></option>
            </select>
            
            <select id="sortBy" onchange="sortCrops()">
                <option value="date-desc"><?php echo __('newest_first'); ?></option>
                <option value="date-asc"><?php echo __('oldest_first'); ?></option>
                <option value="name-asc"><?php echo __('name_a_z'); ?></option>
                <option value="name-desc"><?php echo __('name_z_a'); ?></option>
                <option value="area-desc"><?php echo __('largest_area'); ?></option>
                <option value="area-asc"><?php echo __('smallest_area'); ?></option>
            </select>
        </div>

        <div class="view-toggle">
            <button class="btn-icon active" onclick="setView('grid')" data-view="grid">
                <span class="material-icons">grid_view</span>
            </button>
            <button class="btn-icon" onclick="setView('list')" data-view="list">
                <span class="material-icons">view_list</span>
            </button>
        </div>
    </div>
</div>

<?php if ($crops): ?>
    <div id="cropsContainer" class="crops-list-grid mt-3">
    <?php foreach ($crops as $crop): ?>
    <div class="card crop-card" 
         data-crop-id="<?php echo $crop['crop_id']; ?>"
         data-crop-name="<?php echo strtolower(htmlspecialchars($crop['crop_name'])); ?>"
         data-crop-status="<?php echo $crop['status']; ?>"
         data-crop-area="<?php echo $crop['area_hectares']; ?>"
         data-crop-date="<?php echo strtotime($crop['created_at']); ?>">
        
        <div class="card-header">
            <div class="crop-title-group">
                <h4 class="card-title"><?php echo htmlspecialchars($crop['crop_name']); ?></h4>
                <?php if ($crop['variety']): ?>
                    <span class="crop-variety"><?php echo htmlspecialchars($crop['variety']); ?></span>
                <?php endif; ?>
            </div>
            <span class="badge badge-<?php 
                echo $crop['status'] === 'growing' ? 'success' : 
                    ($crop['status'] === 'planning' ? 'info' : 
                    ($crop['status'] === 'harvesting' ? 'warning' : 'secondary')); 
            ?>">
                <?php echo ucfirst($crop['status']); ?>
            </span>
        </div>

        <div class="card-content">
            <div class="crop-detail-row">
                <span class="material-icons">landscape</span>
                <div>
                    <strong><?php echo __('area_hectares'); ?>:</strong>
                    <span><?php echo format_number($crop['area_hectares'], 2); ?> ha</span>
                </div>
            </div>
            
            <div class="crop-detail-row">
                <span class="material-icons">event</span>
                <div>
                    <strong><?php echo __('planted'); ?>:</strong>
                    <span><?php echo date('M d, Y', strtotime($crop['planted_date'])); ?></span>
                </div>
            </div>
            
            <?php if (!empty($crop['expected_harvest'])): ?>
            <div class="crop-detail-row">
                <span class="material-icons">calendar_today</span>
                <div>
                    <strong><?php echo __('expected_harvest'); ?>:</strong>
                    <span><?php echo date('M d, Y', strtotime($crop['expected_harvest'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="crop-detail-row">
                <span class="material-icons">schedule</span>
                <div>
                    <strong><?php echo __('days_planted'); ?>:</strong>
                    <span><?php 
                        $days = floor((time() - strtotime($crop['planted_date'])) / 86400);
                        echo $days . ' ' . __('days');
                    ?></span>
                </div>
            </div>
        </div>

        <div class="card-footer crop-actions">
            <button onclick="editCrop(<?php echo $crop['crop_id']; ?>)" class="btn btn-small btn-primary" title="<?php echo __('edit'); ?>">
                <span class="material-icons">edit</span>
            </button>
            
            <a href="<?php echo $base_url; ?>?page=disease" class="btn btn-small btn-info" title="<?php echo __('disease_check'); ?>">
                <span class="material-icons">bug_report</span>
            </a>
            
            <button onclick="updateStatus(<?php echo $crop['crop_id']; ?>)" class="btn btn-small btn-success" title="<?php echo __('update_status'); ?>">
                <span class="material-icons">update</span>
            </button>
            
            <button onclick="deleteCrop(<?php echo $crop['crop_id']; ?>, '<?php echo htmlspecialchars($crop['crop_name'], ENT_QUOTES); ?>')" class="btn btn-small btn-danger" title="<?php echo __('delete'); ?>">
                <span class="material-icons">delete</span>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    
    <!-- Pagination Controls -->
    <div id="cropsPaginationContainer" class="pagination-wrapper" style="display: none;">
        <div class="pagination">
            <button id="cropsFirstPageBtn" class="pagination-btn" title="<?php echo __('first_page'); ?>">
                <span class="material-icons">first_page</span>
            </button>
            <button id="cropsPrevPageBtn" class="pagination-btn" title="<?php echo __('previous_page'); ?>">
                <span class="material-icons">chevron_left</span>
            </button>
            <div id="cropsPageNumbers" class="page-numbers"></div>
            <button id="cropsNextPageBtn" class="pagination-btn" title="<?php echo __('next_page'); ?>">
                <span class="material-icons">chevron_right</span>
            </button>
            <button id="cropsLastPageBtn" class="pagination-btn" title="<?php echo __('last_page'); ?>">
                <span class="material-icons">last_page</span>
            </button>
        </div>
        <div class="pagination-info">
            <span id="cropsPageInfo"><?php echo __('page'); ?> 1 <?php echo __('of'); ?> 1</span>
            <span class="pagination-separator">•</span>
            <span id="cropsResultsInfo"><?php echo __('showing'); ?> 0 <?php echo __('crops'); ?></span>
        </div>
    </div>
    
    <div id="noResults" style="display: none;">
        <div class="card text-center mt-4">
            <span class="material-icons" style="font-size: 64px; color: #ccc;">search_off</span>
            <h3><?php echo __('no_crops_found'); ?></h3>
            <p><?php echo __('try_different_search'); ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card text-center mt-4">
        <span class="material-icons" style="font-size: 64px; color: #ccc;">agriculture</span>
        <h3><?php echo __('no_crops_added'); ?></h3>
        <p><?php echo __('add_first_crop_form'); ?></p>
    </div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><span class="material-icons">edit</span> <?php echo __('edit_crop'); ?></h2>
            <span class="material-icons close-modal" onclick="closeEditModal()">close</span>
        </div>
        <form id="editForm">
            <input type="hidden" id="editCropId">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editCropName"><?php echo __('crop_name'); ?> *</label>
                    <input type="text" id="editCropName" required>
                </div>
                <div class="form-group">
                    <label for="editVariety"><?php echo __('variety'); ?></label>
                    <input type="text" id="editVariety">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editArea"><?php echo __('area_hectares'); ?> *</label>
                    <input type="number" id="editArea" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="editPlantedDate"><?php echo __('planted_date'); ?> *</label>
                    <input type="date" id="editPlantedDate" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editStatus"><?php echo __('status'); ?> *</label>
                    <select id="editStatus" required>
                        <option value="planning"><?php echo __('planning'); ?></option>
                        <option value="growing"><?php echo __('growing'); ?></option>
                        <option value="harvesting"><?php echo __('harvesting'); ?></option>
                        <option value="harvested"><?php echo __('harvested'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editExpectedHarvest"><?php echo __('expected_harvest'); ?></label>
                    <input type="date" id="editExpectedHarvest">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">save</span>
                    <?php echo __('save_changes'); ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    <span class="material-icons">close</span>
                    <?php echo __('cancel'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h2><span class="material-icons">update</span> <?php echo __('update_status'); ?></h2>
            <span class="material-icons close-modal" onclick="closeStatusModal()">close</span>
        </div>
        <form id="statusForm">
            <input type="hidden" id="statusCropId">
            
            <div class="form-group">
                <label for="newStatus"><?php echo __('new_status'); ?> *</label>
                <select id="newStatus" required>
                    <option value="planning"><?php echo __('planning'); ?></option>
                    <option value="growing"><?php echo __('growing'); ?></option>
                    <option value="harvesting"><?php echo __('harvesting'); ?></option>
                    <option value="harvested"><?php echo __('harvested'); ?></option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">check</span>
                    <?php echo __('update'); ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">
                    <span class="material-icons">close</span>
                    <?php echo __('cancel'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentView = 'grid';

// Toggle add form
function toggleAddForm() {
    const form = document.getElementById('cropForm');
    const icon = document.querySelector('.toggle-icon');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        icon.textContent = 'expand_less';
    } else {
        form.style.display = 'none';
        icon.textContent = 'expand_more';
    }
}

// Add crop
document.getElementById('cropForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> <?php echo __('adding'); ?>...';
    
    const formData = {
        cropName: document.getElementById('cropName').value.trim(),
        variety: document.getElementById('variety').value.trim(),
        area: parseFloat(document.getElementById('area').value),
        plantedDate: document.getElementById('plantedDate').value,
        status: document.getElementById('status').value,
        expectedHarvest: document.getElementById('expectedHarvest').value || null
    };
    
    if (!formData.cropName || !formData.area || !formData.plantedDate) {
        showNotification('<?php echo __('fill_required_fields'); ?>', 'warning');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
        return;
    }
    
    try {
        const response = await fetch('<?php echo $base_url; ?>api/crop/add-crop.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(formData)
        });
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Invalid response format');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            this.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || '<?php echo __('failed_add_crop'); ?>', 'error');
        }
    } catch (error) {
        console.error('Add crop error:', error);
        showNotification('<?php echo __('error_occurred'); ?>', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});

// Edit crop
async function editCrop(cropId) {
    try {
        const response = await fetch('<?php echo $base_url; ?>api/crop/get-crop.php?id=' + cropId, {
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('editCropId').value = data.crop.crop_id;
            document.getElementById('editCropName').value = data.crop.crop_name;
            document.getElementById('editVariety').value = data.crop.variety || '';
            document.getElementById('editArea').value = data.crop.area_hectares;
            document.getElementById('editPlantedDate').value = data.crop.planted_date;
            document.getElementById('editStatus').value = data.crop.status;
            document.getElementById('editExpectedHarvest').value = data.crop.expected_harvest || '';
            
            document.getElementById('editModal').style.display = 'flex';
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Edit error:', error);
        showNotification('<?php echo __('error_occurred'); ?>', 'error');
    }
}

// Save edit
document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> <?php echo __('saving'); ?>...';
    
    const formData = {
        cropId: parseInt(document.getElementById('editCropId').value),
        cropName: document.getElementById('editCropName').value.trim(),
        variety: document.getElementById('editVariety').value.trim(),
        area: parseFloat(document.getElementById('editArea').value),
        plantedDate: document.getElementById('editPlantedDate').value,
        status: document.getElementById('editStatus').value,
        expectedHarvest: document.getElementById('editExpectedHarvest').value || null
    };
    
    try {
        const response = await fetch('<?php echo $base_url; ?>api/crop/update-crop.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeEditModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Update error:', error);
        showNotification('<?php echo __('error_occurred'); ?>', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});

// Update status
function updateStatus(cropId) {
    document.getElementById('statusCropId').value = cropId;
    document.getElementById('statusModal').style.display = 'flex';
}

document.getElementById('statusForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons spinning">sync</span> <?php echo __('updating'); ?>...';
    
    const formData = {
        cropId: parseInt(document.getElementById('statusCropId').value),
        status: document.getElementById('newStatus').value
    };
    
    try {
        const response = await fetch('<?php echo $base_url; ?>api/crop/update-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeStatusModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Status update error:', error);
        showNotification('<?php echo __('error_occurred'); ?>', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});

// Delete crop
async function deleteCrop(cropId, cropName) {
    if (!confirm('<?php echo __('confirm_delete'); ?> "' + cropName + '"? <?php echo __('cannot_be_undone'); ?>')) {
        return;
    }
    
    try {
        const response = await fetch('<?php echo $base_url; ?>api/crop/delete-crop.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ cropId: parseInt(cropId) })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            document.querySelector(`[data-crop-id="${cropId}"]`).remove();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('<?php echo __('error_occurred'); ?>', 'error');
    }
}

// Filter crops
function filterCrops() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const cards = document.querySelectorAll('.crop-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const name = card.dataset.cropName;
        const status = card.dataset.cropStatus;
        
        const matchesSearch = name.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
    document.getElementById('cropsContainer').style.display = visibleCount === 0 ? 'none' : '';
}

// Sort crops
function sortCrops() {
    const sortBy = document.getElementById('sortBy').value;
    const container = document.getElementById('cropsContainer');
    const cards = Array.from(document.querySelectorAll('.crop-card'));
    
    cards.sort((a, b) => {
        switch(sortBy) {
            case 'date-desc':
                return parseInt(b.dataset.cropDate) - parseInt(a.dataset.cropDate);
            case 'date-asc':
                return parseInt(a.dataset.cropDate) - parseInt(b.dataset.cropDate);
            case 'name-asc':
                return a.dataset.cropName.localeCompare(b.dataset.cropName);
            case 'name-desc':
                return b.dataset.cropName.localeCompare(a.dataset.cropName);
            case 'area-desc':
                return parseFloat(b.dataset.cropArea) - parseFloat(a.dataset.cropArea);
            case 'area-asc':
                return parseFloat(a.dataset.cropArea) - parseFloat(b.dataset.cropArea);
            default:
                return 0;
        }
    });
    
    cards.forEach(card => container.appendChild(card));
}

// View toggle
function setView(view) {
    const container = document.getElementById('cropsContainer');
    const buttons = document.querySelectorAll('.view-toggle .btn-icon');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    if (view === 'list') {
        container.classList.remove('crops-list-grid');
        container.classList.add('crops-list-view');
    } else {
        container.classList.remove('crops-list-view');
        container.classList.add('crops-list-grid');
    }
    
    currentView = view;
}

// Modal functions
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const statusModal = document.getElementById('statusModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === statusModal) {
        closeStatusModal();
    }
}

// showNotification is now provided globally via footer.php

// ===== Pagination for Crops =====
const cropsPagination = {
    currentPage: 1,
    itemsPerPage: 12,
    totalItems: 0,
    totalPages: 0,
    visibleItems: []
};

document.addEventListener('DOMContentLoaded', function() {
    initCropsPagination();
});

function initCropsPagination() {
    const container = document.getElementById('cropsContainer');
    if (!container) return;
    
    updateCropsPaginationState();
}

function updateCropsPaginationState() {
    const container = document.getElementById('cropsContainer');
    if (!container) return;
    
    // Get only visible items (after filtering)
    const allCrops = container.querySelectorAll('.crop-card');
    cropsPagination.visibleItems = Array.from(allCrops).filter(item => item.style.display !== 'none');
    cropsPagination.totalItems = cropsPagination.visibleItems.length;
    cropsPagination.totalPages = Math.ceil(cropsPagination.totalItems / cropsPagination.itemsPerPage);
    
    const paginationContainer = document.getElementById('cropsPaginationContainer');
    if (paginationContainer && cropsPagination.totalPages > 1) {
        paginationContainer.style.display = 'flex';
        setupCropsPaginationListeners();
        showCropsPage(1);
    } else if (paginationContainer) {
        paginationContainer.style.display = 'none';
        // Show all items if only one page
        allCrops.forEach(item => {
            if (item.style.display !== 'none') item.style.display = '';
        });
    }
}

function setupCropsPaginationListeners() {
    const firstBtn = document.getElementById('cropsFirstPageBtn');
    const prevBtn = document.getElementById('cropsPrevPageBtn');
    const nextBtn = document.getElementById('cropsNextPageBtn');
    const lastBtn = document.getElementById('cropsLastPageBtn');
    
    // Remove old listeners by cloning
    if (firstBtn) {
        const newFirst = firstBtn.cloneNode(true);
        firstBtn.parentNode.replaceChild(newFirst, firstBtn);
        newFirst.addEventListener('click', () => showCropsPage(1));
    }
    if (prevBtn) {
        const newPrev = prevBtn.cloneNode(true);
        prevBtn.parentNode.replaceChild(newPrev, prevBtn);
        newPrev.addEventListener('click', () => showCropsPage(cropsPagination.currentPage - 1));
    }
    if (nextBtn) {
        const newNext = nextBtn.cloneNode(true);
        nextBtn.parentNode.replaceChild(newNext, nextBtn);
        newNext.addEventListener('click', () => showCropsPage(cropsPagination.currentPage + 1));
    }
    if (lastBtn) {
        const newLast = lastBtn.cloneNode(true);
        lastBtn.parentNode.replaceChild(newLast, lastBtn);
        newLast.addEventListener('click', () => showCropsPage(cropsPagination.totalPages));
    }
}

function showCropsPage(page) {
    page = Math.max(1, Math.min(page, cropsPagination.totalPages));
    cropsPagination.currentPage = page;
    
    const container = document.getElementById('cropsContainer');
    const allCrops = container.querySelectorAll('.crop-card');
    
    // First hide all
    allCrops.forEach(item => item.style.display = 'none');
    
    // Show only current page items
    const startIndex = (page - 1) * cropsPagination.itemsPerPage;
    const endIndex = startIndex + cropsPagination.itemsPerPage;
    
    cropsPagination.visibleItems.forEach((item, index) => {
        item.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    updateCropsPaginationControls();
}

function updateCropsPaginationControls() {
    const { currentPage, totalPages, totalItems, itemsPerPage } = cropsPagination;
    
    document.getElementById('cropsFirstPageBtn').disabled = currentPage === 1;
    document.getElementById('cropsPrevPageBtn').disabled = currentPage === 1;
    document.getElementById('cropsNextPageBtn').disabled = currentPage === totalPages;
    document.getElementById('cropsLastPageBtn').disabled = currentPage === totalPages;
    
    document.getElementById('cropsPageInfo').textContent = `<?php echo __('page'); ?> ${currentPage} <?php echo __('of'); ?> ${totalPages}`;
    
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('cropsResultsInfo').textContent = `<?php echo __('showing'); ?> ${startItem}-${endItem} <?php echo __('of'); ?> ${totalItems}`;
    
    generateCropsPageNumbers();
}

function generateCropsPageNumbers() {
    const container = document.getElementById('cropsPageNumbers');
    const { currentPage, totalPages } = cropsPagination;
    container.innerHTML = '';
    
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        container.appendChild(createCropsPageButton(1));
        if (startPage > 2) container.appendChild(createCropsEllipsis());
    }
    
    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createCropsPageButton(i));
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) container.appendChild(createCropsEllipsis());
        container.appendChild(createCropsPageButton(totalPages));
    }
}

function createCropsPageButton(pageNum) {
    const btn = document.createElement('button');
    btn.className = 'page-number' + (pageNum === cropsPagination.currentPage ? ' active' : '');
    btn.textContent = pageNum;
    btn.addEventListener('click', () => showCropsPage(pageNum));
    return btn;
}

function createCropsEllipsis() {
    const span = document.createElement('span');
    span.className = 'page-ellipsis';
    span.textContent = '...';
    return span;
}

// Override filterCrops to update pagination after filtering
const originalFilterCrops = filterCrops;
filterCrops = function() {
    originalFilterCrops();
    setTimeout(updateCropsPaginationState, 100);
};

// Override sortCrops to update pagination after sorting
const originalSortCrops = sortCrops;
sortCrops = function() {
    originalSortCrops();
    setTimeout(updateCropsPaginationState, 100);
};
</script>

<style>
/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
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
    color: #2c3e50;
}

.stat-info p {
    margin: 5px 0 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Card Header Action */
.card-header-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.card-header-action:hover {
    background: #f8f9fa;
}

.toggle-icon {
    transition: transform 0.3s ease;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
}

.form-group input,
.form-group select {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
    display: flex;
    align-items: center;
}

.search-box .material-icons {
    position: absolute;
    left: 12px;
    color: #7f8c8d;
}

.search-box input {
    width: 100%;
    padding: 10px 10px 10px 45px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.filter-group {
    display: flex;
    gap: 10px;
}

.filter-group select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
}

.view-toggle {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 40px;
    height: 40px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-icon:hover,
.btn-icon.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.btn-icon .material-icons {
    font-size: 20px;
}

/* Crop Card Enhanced */
.crop-card {
    transition: all 0.3s ease;
}

.crop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.crop-title-group {
    flex: 1;
}

.crop-variety {
    display: block;
    font-size: 0.85rem;
    color: #7f8c8d;
    font-weight: normal;
    margin-top: 3px;
}

.crop-detail-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.crop-detail-row:last-child {
    border-bottom: none;
}

.crop-detail-row .material-icons {
    font-size: 20px;
    color: #3498db;
    margin-top: 2px;
}

.crop-detail-row div {
    flex: 1;
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.crop-detail-row strong {
    color: #2c3e50;
}

.crop-detail-row span {
    color: #7f8c8d;
}

/* List View */
.crops-list-view {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.crops-list-view .crop-card {
    width: 100%;
}

.crops-list-view .card-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

/* Badges */
.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-secondary {
    background: #e2e3e5;
    color: #383d41;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.btn-small {
    padding: 8px 12px;
    font-size: 0.9rem;
}

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    background: #229954;
}

.btn-info {
    background: #3498db;
    color: white;
}

.btn-info:hover {
    background: #2980b9;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 0; /* No padding - header and body handle their own */
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
}

.modal-small {
    max-width: 400px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, var(--primary, #557A46) 0%, #2d5a27 100%);
    color: white;
    border-radius: 16px 16px 0 0;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -60%;
    right: -20%;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    pointer-events: none;
}

.modal-header h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    font-size: 1.2rem;
    position: relative;
    z-index: 1;
}

.modal-header h2 .material-icons {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.4rem;
    border-radius: 8px;
}

.close-modal {
    cursor: pointer;
    color: white;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    padding: 0.3rem;
    position: relative;
    z-index: 1;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.35);
    transform: rotate(90deg);
}

/* Modal body */
.modal-content form {
    padding: 1.5rem;
}

/* Toast Notification */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 400px;
}

.toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    transform: translateX(400px);
    transition: transform 0.3s ease;
    border-left: 4px solid;
}

.toast.show {
    transform: translateX(0);
}

.toast-success {
    border-left-color: #22c55e;
}

.toast-success .material-icons {
    color: #22c55e;
}

.toast-danger {
    border-left-color: #ef4444;
}

.toast-danger .material-icons {
    color: #ef4444;
}

.toast-warning {
    border-left-color: #f59e0b;
}

.toast-warning .material-icons {
    color: #f59e0b;
}

.toast-info {
    border-left-color: #3b82f6;
}

.toast-info .material-icons {
    color: #3b82f6;
}

.toast .material-icons {
    font-size: 24px;
}

.toast span:last-child {
    flex: 1;
    font-size: 14px;
    color: #374151;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.spinning {
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        width: 100%;
    }
    
    .filter-group {
        flex-direction: column;
    }
    
    .filter-group select {
        width: 100%;
    }
    
    .crops-list-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .crop-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
