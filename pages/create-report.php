<?php
/**
 * SmartChashi - Create Disease Report Page
 * For Farmers to submit disease reports to officers
 */

if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    redirect('home');
}

$currentLang = $_SESSION['lang'] ?? 'en';

// Get farmer's crops for dropdown
$db = new Database();
$crops = $db->resultSet("SELECT crop_id, crop_name FROM crop_data WHERE farmer_id = ? ORDER BY crop_name", [$currentUser['user_id']]) ?: [];

include __DIR__ . '/../layouts/header.php';
?>

<style>
.create-report-page { padding: 1rem 0; max-width: 900px; margin: 0 auto; }

.page-hero {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
    display: flex; align-items: center; gap: 1rem;
}
.page-hero h1 { margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; }
.page-hero p { margin: 0.5rem 0 0; opacity: 0.9; }

.report-form-card {
    background: var(--card-bg); border-radius: 16px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;
}
.form-header {
    padding: 1.5rem; border-bottom: 1px solid var(--border-color);
    display: flex; align-items: center; gap: 0.75rem;
}
.form-header h2 { margin: 0; font-size: 1.25rem; }
.form-header .step-badge {
    background: var(--primary-color); color: white;
    padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;
}

.form-body { padding: 1.5rem; }

.form-section { margin-bottom: 2rem; }
.form-section:last-child { margin-bottom: 0; }
.form-section-title {
    font-size: 1rem; font-weight: 600; color: #374151;
    margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
    padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color);
}
.form-section-title .material-icons { color: var(--primary-color); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-row.full { grid-template-columns: 1fr; }

.form-group { margin-bottom: 1rem; }
.form-group label {
    display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;
}
.form-group label .required { color: #ef4444; }
.form-group .hint { font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem; }

.form-control {
    width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1rem; transition: all 0.2s;
    background: var(--bg-color);
}
.form-control:focus {
    outline: none; border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
textarea.form-control { resize: vertical; min-height: 100px; }

.severity-options { display: flex; gap: 1rem; flex-wrap: wrap; }
.severity-option {
    flex: 1; min-width: 100px; padding: 1rem; border: 2px solid var(--border-color);
    border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.2s;
}
.severity-option:hover { border-color: var(--primary-color); }
.severity-option.selected { border-color: var(--primary-color); background: rgba(22, 163, 74, 0.05); }
.severity-option input { display: none; }
.severity-option .icon { font-size: 2rem; margin-bottom: 0.5rem; }
.severity-option.low .icon { color: #10b981; }
.severity-option.medium .icon { color: #f59e0b; }
.severity-option.high .icon { color: #ef4444; }
.severity-option .label { font-weight: 600; }

.image-upload-area {
    border: 2px dashed var(--border-color); border-radius: 12px;
    padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s;
    background: var(--bg-color);
}
.image-upload-area:hover { border-color: var(--primary-color); background: rgba(22, 163, 74, 0.02); }
.image-upload-area.dragover { border-color: var(--primary-color); background: rgba(22, 163, 74, 0.05); }
.image-upload-area .material-icons { font-size: 48px; color: #9ca3af; margin-bottom: 0.5rem; }
.image-upload-area p { margin: 0; color: #6b7280; }
.image-upload-area input { display: none; }

.image-preview-container { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
.image-preview {
    position: relative; width: 120px; height: 120px; border-radius: 8px; overflow: hidden;
}
.image-preview img { width: 100%; height: 100%; object-fit: cover; }
.image-preview .remove-btn {
    position: absolute; top: 4px; right: 4px; width: 24px; height: 24px;
    background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.image-preview .remove-btn .material-icons { font-size: 16px; }

.form-actions {
    padding: 1.5rem; border-top: 1px solid var(--border-color);
    display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 1rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.5rem; border: none;
}
.btn-primary { background: #16a34a !important; color: #ffffff !important; }
.btn-primary:hover { background: #15803d !important; }
.btn-primary:disabled { background: #9ca3af !important; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6 !important; color: #374151 !important; border: 1px solid #d1d5db !important; }
.btn-secondary:hover { background: #e5e7eb !important; }

#submitBtn {
    background: #16a34a !important;
    color: #ffffff !important;
    padding: 0.85rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
#submitBtn:hover { background: #15803d !important; }
#submitBtn:disabled { background: #9ca3af !important; color: #ffffff !important; cursor: not-allowed; }

.alert {
    padding: 1rem; border-radius: 8px; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.75rem;
}
.alert-success { background: #d1fae5; color: #065f46; }
.alert-error { background: #fee2e2; color: #991b1b; }
.alert .material-icons { font-size: 20px; }

.loading-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.9); z-index: 9999;
    display: none; align-items: center; justify-content: center; flex-direction: column;
}
.loading-overlay.active { display: flex; }
.loading-overlay .spinner { animation: spin 1s linear infinite; font-size: 48px; color: var(--primary-color); }
.loading-overlay p { margin-top: 1rem; font-weight: 500; }
@keyframes spin { 100% { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .severity-options { flex-direction: column; }
    .form-actions { flex-direction: column; }
    .form-actions .btn { width: 100%; justify-content: center; }
}
</style>

<div class="create-report-page">
    <!-- Hero Section -->
    <div class="page-hero">
        <div>
            <h1><span class="material-icons">bug_report</span> <?php echo __('create_report'); ?></h1>
            <p><?php echo __('report_disease_to_officer'); ?></p>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer"></div>

    <!-- Report Form -->
    <div class="report-form-card">
        <div class="form-header">
            <span class="material-icons">edit_note</span>
            <h2><?php echo __('disease_report_form'); ?></h2>
        </div>

        <form id="reportForm" enctype="multipart/form-data">
            <div class="form-body">
                <!-- Crop Information -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="material-icons">agriculture</span>
                        <?php echo __('crop_information'); ?>
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cropId"><?php echo __('select_crop'); ?> <span class="required">*</span></label>
                            <select id="cropId" name="crop_id" class="form-control" required>
                                <option value=""><?php echo __('choose_crop'); ?></option>
                                <?php foreach ($crops as $crop): ?>
                                    <option value="<?php echo $crop['crop_id']; ?>"><?php echo htmlspecialchars($crop['crop_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="hint"><?php echo __('select_affected_crop'); ?></p>
                        </div>
                        <div class="form-group">
                            <label for="diseaseName"><?php echo __('disease_name'); ?></label>
                            <input type="text" id="diseaseName" name="disease_name" class="form-control" placeholder="<?php echo __('enter_disease_name'); ?>">
                            <p class="hint"><?php echo __('disease_name_hint'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Severity Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="material-icons">speed</span>
                        <?php echo __('severity_level'); ?>
                    </h3>
                    <div class="severity-options">
                        <label class="severity-option low">
                            <input type="radio" name="severity" value="low" checked>
                            <div class="icon"><span class="material-icons">sentiment_satisfied</span></div>
                            <div class="label"><?php echo __('low'); ?></div>
                            <div class="desc"><?php echo __('minor_issue'); ?></div>
                        </label>
                        <label class="severity-option medium">
                            <input type="radio" name="severity" value="medium">
                            <div class="icon"><span class="material-icons">sentiment_neutral</span></div>
                            <div class="label"><?php echo __('medium'); ?></div>
                            <div class="desc"><?php echo __('moderate_issue'); ?></div>
                        </label>
                        <label class="severity-option high">
                            <input type="radio" name="severity" value="high">
                            <div class="icon"><span class="material-icons">sentiment_very_dissatisfied</span></div>
                            <div class="label"><?php echo __('high'); ?></div>
                            <div class="desc"><?php echo __('severe_issue'); ?></div>
                        </label>
                    </div>
                </div>

                <!-- Symptoms Description -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="material-icons">description</span>
                        <?php echo __('symptoms_description'); ?>
                    </h3>
                    <div class="form-group">
                        <label for="symptoms"><?php echo __('describe_symptoms'); ?> <span class="required">*</span></label>
                        <textarea id="symptoms" name="symptoms" class="form-control" rows="4" required placeholder="<?php echo __('symptoms_placeholder'); ?>"></textarea>
                        <p class="hint"><?php echo __('symptoms_hint'); ?></p>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="material-icons">photo_camera</span>
                        <?php echo __('upload_images'); ?>
                    </h3>
                    <div class="image-upload-area" id="uploadArea">
                        <span class="material-icons">cloud_upload</span>
                        <p><strong><?php echo __('click_or_drag'); ?></strong></p>
                        <p><?php echo __('max_images'); ?></p>
                        <input type="file" id="imageInput" name="images[]" accept="image/*" multiple>
                    </div>
                    <div class="image-preview-container" id="imagePreview"></div>
                </div>

                <!-- Additional Notes -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="material-icons">note_add</span>
                        <?php echo __('additional_notes'); ?>
                    </h3>
                    <div class="form-group">
                        <label for="notes"><?php echo __('any_other_info'); ?></label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="<?php echo __('notes_placeholder'); ?>"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo $base_url; ?>?page=dashboard" class="btn btn-secondary">
                    <span class="material-icons">arrow_back</span>
                    <?php echo __('cancel'); ?>
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span class="material-icons">send</span>
                    <?php echo __('submit_report'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <span class="material-icons spinner">sync</span>
    <p><?php echo __('submitting_report'); ?></p>
</div>

<script>
var baseUrl = <?php echo json_encode($base_url); ?>;
const jsT = {
    report_submitted: <?php echo json_encode(__('report_submitted_successfully')); ?>,
    error_occurred: <?php echo json_encode(__('error_occurred')); ?>,
    select_crop: <?php echo json_encode(__('please_select_crop')); ?>,
    enter_symptoms: <?php echo json_encode(__('please_enter_symptoms')); ?>,
    max_files: <?php echo json_encode(__('max_3_images')); ?>
};

let selectedImages = [];

document.addEventListener('DOMContentLoaded', function() {
    // Severity selection
    document.querySelectorAll('.severity-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.severity-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
    });
    
    // Set initial selection
    document.querySelector('.severity-option.low').classList.add('selected');

    // Image upload handling
    const uploadArea = document.getElementById('uploadArea');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');

    uploadArea.addEventListener('click', () => imageInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    
    imageInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        const remaining = 3 - selectedImages.length;
        const filesToAdd = Array.from(files).slice(0, remaining);
        
        if (files.length > remaining) {
            showNotification(jsT.max_files, 'error');
        }
        
        filesToAdd.forEach(file => {
            if (file.type.startsWith('image/')) {
                selectedImages.push(file);
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-btn" data-index="${selectedImages.length - 1}">
                            <span class="material-icons">close</span>
                        </button>
                    `;
                    imagePreview.appendChild(preview);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    imagePreview.addEventListener('click', (e) => {
        if (e.target.closest('.remove-btn')) {
            const index = parseInt(e.target.closest('.remove-btn').dataset.index);
            selectedImages.splice(index, 1);
            renderPreviews();
        }
    });

    function renderPreviews() {
        imagePreview.innerHTML = '';
        selectedImages.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-btn" data-index="${index}">
                        <span class="material-icons">close</span>
                    </button>
                `;
                imagePreview.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }

    // Form submission
    document.getElementById('reportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const cropId = document.getElementById('cropId').value;
        const symptoms = document.getElementById('symptoms').value.trim();
        
        if (!cropId) {
            showNotification(jsT.select_crop, 'error');
            return;
        }
        
        if (!symptoms) {
            showNotification(jsT.enter_symptoms, 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('crop_id', cropId);
        formData.append('disease_name', document.getElementById('diseaseName').value);
        formData.append('severity', document.querySelector('input[name="severity"]:checked').value);
        formData.append('symptoms', symptoms);
        formData.append('notes', document.getElementById('notes').value);
        
        selectedImages.forEach((file, index) => {
            formData.append('images[]', file);
        });
        
        document.getElementById('loadingOverlay').classList.add('active');
        document.getElementById('submitBtn').disabled = true;
        
        try {
            const response = await fetch(baseUrl + 'ajax/submit-farmer-report.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(jsT.report_submitted, 'success');
                setTimeout(() => {
                    window.location.href = baseUrl + '?page=my-reports';
                }, 2000);
            } else {
                showNotification(data.message || jsT.error_occurred, 'error');
                document.getElementById('submitBtn').disabled = false;
            }
        } catch (error) {
            console.error('Submit error:', error);
            showNotification(jsT.error_occurred, 'error');
            document.getElementById('submitBtn').disabled = false;
        } finally {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
    });
});

// showNotification is now provided globally via footer.php
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
