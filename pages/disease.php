<?php

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();
$crops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY created_at DESC", [$_SESSION['user_id']]);
?>

<style>
:root {
    --disease-primary: var(--primary);
    --disease-secondary: var(--secondary);
    --disease-danger: #ef4444;
    --disease-warning: #f59e0b;
    --disease-success: #22c55e;
}

.disease-detection-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0;
}

.disease-hero-section {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 3rem 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    text-align: center;
    color: white;
    box-shadow: 0 10px 40px rgba(85, 122, 70, 0.3);
}

.disease-hero-section h1 {
    font-size: 2.5rem;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.disease-hero-section p {
    font-size: 1.1rem;
    opacity: 0.95;
    margin: 0;
}

.disease-main-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.disease-upload-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.crop-selector {
    margin-bottom: 2rem;
}

.crop-selector label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.75rem;
    font-size: 1.05rem;
}

.crop-selector select {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    background: white;
    transition: all 0.3s ease;
}

.crop-selector select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(85, 122, 70, 0.1);
}

.crop-selector select optgroup {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--primary);
    background: #f3f4f6;
    padding: 0.5rem;
}

.crop-selector select option {
    padding: 0.5rem;
    font-weight: 500;
}

.upload-zone {
    border: 3px dashed #d1d5db;
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.upload-zone:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    transform: translateY(-2px);
}

.upload-zone.drag-over {
    border-color: var(--primary);
    background: #d1fae5;
    border-style: solid;
}

.upload-icon {
    font-size: 5rem !important;
    color: #9ca3af;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.upload-zone:hover .upload-icon {
    color: var(--primary);
    transform: scale(1.1);
}

.upload-zone h3 {
    color: #374151;
    margin: 0 0 0.5rem 0;
    font-size: 1.3rem;
}

.upload-zone p {
    color: #6b7280;
    margin: 0;
}

.image-preview-section {
    display: none;
    position: relative;
    margin-bottom: 2rem;
}

.image-preview-section.active {
    display: block;
}

.preview-image-container {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.preview-image-container img {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: contain;
    background: #000;
}

.remove-preview-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.95);
    color: white;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.remove-preview-btn:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.capture-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 2rem 0;
}

.capture-option-btn {
    padding: 1.5rem;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.capture-option-btn:hover {
    border-color: var(--primary);
    background: #f0fdf4;
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(85, 122, 70, 0.2);
}

.capture-option-btn .material-icons {
    font-size: 3.5rem;
    color: var(--primary);
}

.capture-option-btn span:last-child {
    font-weight: 600;
    color: #374151;
    font-size: 1.05rem;
}

.analyze-btn {
    width: 100%;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 16px;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
}

.analyze-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(85, 122, 70, 0.4);
}

.analyze-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.analyze-btn .material-icons {
    font-size: 1.5rem;
}

.info-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.info-card h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #374151 !important;
    margin: 0 0 1.25rem 0;
    font-size: 1.2rem;
}

.instruction-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: step;
}

.instruction-steps li {
    counter-increment: step;
    padding: 1rem 1rem 1rem 3.5rem;
    margin-bottom: 0.75rem;
    background: #f9fafb;
    border-radius: 12px;
    position: relative;
    font-size: 0.95rem;
    color: var(--text) !important;
}

.instruction-steps li::before {
    content: counter(step);
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.tips-card {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
}

.tips-card h3 {
    color: white;
}

.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tips-list li {
    padding: 0.875rem 1rem;
    margin-bottom: 0.75rem;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    border-left: 4px solid rgba(255, 255, 255, 0.5);
    font-size: 0.95rem;
    backdrop-filter: blur(10px);
    color: var(--text) !important ;
}

.loading-overlay {
    display: none;
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.loading-overlay.active {
    display: block;
}

.loading-spinner {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border: 6px solid #e5e7eb;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spinner-rotate 1s linear infinite;
}

@keyframes spinner-rotate {
    to { transform: rotate(360deg); }
}

.loading-overlay h3 {
    color: #374151;
    margin: 1.5rem 0 0.5rem 0;
}

.loading-overlay p {
    color: #6b7280;
    margin: 0;
}

.results-section {
    display: none;
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-top: 2rem;
}

.results-section.active {
    display: block;
    animation: slideUp 0.4s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.result-header {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #f3f4f6;
}

.result-icon-container {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.result-icon-container .material-icons {
    font-size: 3rem;
}

.severity-high .result-icon-container {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: var(--disease-danger);
}

.severity-medium .result-icon-container {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: var(--disease-warning);
}

.severity-low .result-icon-container {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: var(--disease-success);
}

.result-info h2 {
    margin: 0 0 0.5rem 0;
    color: #111827;
    font-size: 1.8rem;
}

.severity-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.severity-high .severity-badge {
    background: var(--disease-danger);
    color: white;
}

.severity-medium .severity-badge {
    background: var(--disease-warning);
    color: white;
}

.severity-low .severity-badge {
    background: var(--disease-success);
    color: white;
}

.confidence-section {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 12px;
}

.confidence-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    color: #374151;
    font-weight: 600;
}

.confidence-percentage {
    font-size: 1.5rem;
    color: var(--primary);
}

.confidence-bar {
    height: 12px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.confidence-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 10px;
    transition: width 1s ease-out;
    box-shadow: 0 2px 8px rgba(85, 122, 70, 0.4);
}

.treatment-section {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    padding: 2rem;
    border-radius: 16px;
    border: 2px solid #bbf7d0;
}

.treatment-section h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #065f46;
    margin: 0 0 1.25rem 0;
    font-size: 1.3rem;
}

.treatment-section h3 .material-icons {
    font-size: 1.8rem;
}

.treatment-text {
    color: #065f46;
    line-height: 1.8;
    white-space: pre-line;
    font-size: 1rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.action-btn {
    flex: 1;
    padding: 1rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.action-btn-primary {
    background: var(--primary);
    color: white;
}

.action-btn-primary:hover {
    background: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(85, 122, 70, 0.3);
}

.action-btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.action-btn-secondary:hover {
    background: #e5e7eb;
}

.camera-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.camera-modal.active {
    display: flex;
}

.camera-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.camera-content h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 1.5rem 0;
    color: #111827;
    font-size: 1.5rem;
}

.camera-video-container {
    position: relative;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.camera-video {
    width: 100%;
    height: auto;
    display: block;
}

.camera-canvas {
    display: none;
}

.camera-controls {
    display: flex;
    gap: 1rem;
}

.camera-btn {
    flex: 1;
    padding: 1.25rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.05rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.camera-btn-capture {
    background: var(--primary);
    color: white;
}

.camera-btn-capture:hover {
    background: var(--secondary);
    transform: translateY(-2px);
}

.camera-btn-cancel {
    background: #f3f4f6;
    color: #374151;
}

.camera-btn-cancel:hover {
    background: #e5e7eb;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.empty-state .material-icons {
    font-size: 6rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #374151;
    margin: 0 0 1rem 0;
}

.empty-state p {
    color: #6b7280;
    margin: 0 0 2rem 0;
}

@media (max-width: 1024px) {
    .disease-main-container {
        grid-template-columns: 1fr;
    }
    
    .info-sidebar {
        order: -1;
    }
}

@media (max-width: 640px) {
    .disease-hero-section h1 {
        font-size: 1.8rem;
    }
    
    .capture-options {
        grid-template-columns: 1fr;
    }
    
    .result-header {
        flex-direction: column;
        text-align: center;
    }
}

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    min-width: 300px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideInRight 0.3s ease-out;
    background: white;
    border-left: 4px solid #2196f3;
}

.toast.success {
    border-left-color: #4caf50;
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
}

.toast.danger,
.toast.error {
    border-left-color: #f44336;
    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
}

.toast.warning {
    border-left-color: #ff9800;
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
}

.toast.info {
    border-left-color: #2196f3;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
}

.toast .material-icons {
    font-size: 1.5rem;
}

.toast.success .material-icons {
    color: #4caf50;
}

.toast.danger .material-icons,
.toast.error .material-icons {
    color: #f44336;
}

.toast.warning .material-icons {
    color: #ff9800;
}

.toast.info .material-icons {
    color: #2196f3;
}

.toast-message {
    flex: 1;
    color: #333;
    font-weight: 500;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    color: #666;
    transition: color 0.2s;
}

.toast-close:hover {
    color: #333;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast.hiding {
    animation: slideOutRight 0.3s ease-in forwards;
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

@media (max-width: 640px) {
    .toast-container {
        top: 10px;
        right: 10px;
        left: 10px;
    }
    
    .toast {
        min-width: auto;
        width: 100%;
    }
}
</style>

<div class="disease-detection-page">
    <div class="disease-hero-section">
        <h1>
            <span class="material-icons" style="font-size: 3rem;">bug_report</span>
            <?php echo __('disease_detection'); ?>
        </h1>
        <p><?php echo __('ai_powered_plant_disease_detection'); ?></p>
    </div>

    <div class="disease-main-container">
        <div class="disease-upload-card">
            <form id="diseaseDetectionForm">
                <!-- Enhanced Crop Selector -->
                <div class="crop-selector">
                    <h4 style="display: flex; align-items: center; gap: 0.5rem; margin: 0 0 0.5rem 0;">
                        <span class="material-icons">eco</span>
                        <?php echo __('select_crop'); ?>
                    </h4>
                    <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('select_crop_desc'); ?>
                    </p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo __('crop_type'); ?>
                            </label>
                            <select id="cropSelect" name="cropType" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem;">
                                <option value=""><?php echo __('select_a_crop'); ?></option>
                                <optgroup label="<?php echo __('bangladesh_crops'); ?>">
                                    <?php 
                                    $bangladeshCrops = [
                                        ['Rice', 'crop_rice'],
                                        ['Mango', 'crop_mango'],
                                        ['Sugarcane', 'crop_sugarcane'],
                                        ['Cotton', 'crop_cotton'],
                                        ['Jackfruit', 'crop_jackfruit'],
                                        ['Cauliflower', 'crop_cauliflower'],
                                        ['Pumpkin', 'crop_pumpkin'],
                                    ];
                                    foreach ($bangladeshCrops as $crop):
                                        $value = $crop[0];
                                        $translationKey = $crop[1];
                                    ?>
                                    <option value="<?php echo $value; ?>"><?php echo __($translationKey); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="<?php echo __('common_crops'); ?>">
                                    <?php 
                                    $commonCrops = [
                                        ['Apple', 'crop_apple'],
                                        ['Grape', 'crop_grape'],
                                        ['Tomato', 'crop_tomato'],
                                        ['Potato', 'crop_potato'],
                                        ['Corn_(maize)', 'crop_corn'],
                                        ['Pepper,_bell', 'crop_pepper'],
                                        ['Strawberry', 'crop_strawberry'],
                                        ['Cherry_(including_sour)', 'crop_cherry'],
                                        ['Peach', 'crop_peach'],
                                        ['Orange', 'crop_orange'],
                                        ['Soybean', 'crop_soybean'],
                                    ];
                                    foreach ($commonCrops as $crop):
                                        $value = $crop[0];
                                        $translationKey = $crop[1];
                                    ?>
                                    <option value="<?php echo $value; ?>"><?php echo __($translationKey); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <option value="other"><?php echo __('other_type'); ?></option>
                            </select>
                        </div>
                        <div id="customCropInput" style="flex: 1; min-width: 200px; display: none;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo __('enter_crop_name'); ?>
                            </label>
                            <input type="text" id="customCrop" name="customCrop" placeholder="<?php echo ($_SESSION['lang'] ?? 'en') === 'bn' ? 'যেমন: Wheat, Banana...' : 'e.g., Wheat, Banana...'; ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem;">
                        </div>
                    </div>
                </div>
                
                <?php if ($crops && count($crops) > 0): ?>
                <div class="crop-selector" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-icons">agriculture</span>
                        <?php echo __('my_crops'); ?> (<?php echo __('optional'); ?>)
                    </label>
                    <select id="farmerCropSelect" name="cropId" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; margin-top: 0.5rem;">
                        <option value=""><?php echo __('no_crop_selected'); ?></option>
                        <?php foreach ($crops as $crop): ?>
                            <option value="<?php echo $crop['crop_id']; ?>">
                                <?php echo htmlspecialchars($crop['crop_name']); ?> 
                                - <?php echo __('planted'); ?>: <?php echo date('M d, Y', strtotime($crop['planted_date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div id="uploadZone" class="upload-zone">
                    <span class="material-icons upload-icon">cloud_upload</span>
                    <h3><?php echo __('drag_drop_or_click'); ?></h3>
                    <p><?php echo __('supports_jpg_png_max_5mb'); ?></p>
                </div>

                <div id="imagePreview" class="image-preview-section">
                    <div class="preview-image-container">
                        <img id="previewImg" src="" alt="Preview">
                        <button type="button" class="remove-preview-btn" onclick="removeImage()">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                </div>

                <div class="capture-options" id="captureOptions">
                    <button type="button" class="capture-option-btn" onclick="openCamera()">
                        <span class="material-icons">photo_camera</span>
                        <span><?php echo __('use_camera'); ?></span>
                    </button>
                    <button type="button" class="capture-option-btn" onclick="selectFile()">
                        <span class="material-icons">photo_library</span>
                        <span><?php echo __('choose_from_gallery'); ?></span>
                    </button>
                </div>

                <input type="file" id="fileInput" accept="image/*" style="display: none;">

                <button type="submit" class="analyze-btn" id="analyzeButton" disabled>
                    <span class="material-icons">analytics</span>
                    <?php echo __('analyze_now'); ?>
                </button>
            </form>

            <div id="loadingOverlay" class="loading-overlay">
                <div class="loading-spinner"></div>
                <h3><?php echo __('analyzing_image'); ?></h3>
                <p><?php echo __('ai_is_detecting_diseases'); ?></p>
            </div>

            <div id="resultsSection" class="results-section">
                <!-- Results will be dynamically inserted here -->
            </div>
        </div>

        <div class="info-sidebar">
            <div class="info-card">
                <h3>
                    <span class="material-icons">help_outline</span>
                    <?php echo __('how_it_works'); ?>
                </h3>
                <ol class="instruction-steps">
                    <li><?php echo __('select_your_crop_from_list'); ?></li>
                    <li><?php echo __('take_clear_photo_affected_area'); ?></li>
                    <li><?php echo __('ai_analyzes_and_identifies'); ?></li>
                    <li><?php echo __('get_treatment_recommendations'); ?></li>
                </ol>
            </div>

            <div class="info-card tips-card">
                <h3>
                    <span class="material-icons">lightbulb</span>
                    <?php echo __('pro_tips'); ?>
                </h3>
                <ul class="tips-list">
                    <li><?php echo __('use_natural_daylight'); ?></li>
                    <li><?php echo __('focus_on_damaged_leaves'); ?></li>
                    <li><?php echo __('avoid_blurry_images'); ?></li>
                    <li><?php echo __('capture_multiple_symptoms'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Camera Modal -->
    <div id="cameraModal" class="camera-modal">
        <div class="camera-content">
            <h3>
                <span class="material-icons">photo_camera</span>
                <?php echo __('capture_plant_image'); ?>
            </h3>
            <div class="camera-video-container">
                <video id="cameraVideo" class="camera-video" autoplay playsinline></video>
                <canvas id="cameraCanvas" class="camera-canvas"></canvas>
            </div>
            <div class="camera-controls">
                <button type="button" class="camera-btn camera-btn-capture" onclick="captureImage()">
                    <span class="material-icons">camera</span>
                    <?php echo __('capture'); ?>
                </button>
                <button type="button" class="camera-btn camera-btn-cancel" onclick="closeCamera()">
                    <span class="material-icons">close</span>
                    <?php echo __('cancel'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let selectedFile = null;
    let cameraStream = null;
    const baseUrl = '<?php echo $base_url; ?>';
    const currentLang = '<?php echo $_SESSION['lang'] ?? 'en'; ?>';
    
    // Translations object
    const translations = {
        crop: '<?php echo __('crop_type'); ?>',
        symptoms: '<?php echo __('symptoms'); ?>',
        treatment: '<?php echo __('treatment'); ?>',
        organic_treatment: '<?php echo __('organic_treatment'); ?>',
        prevention: '<?php echo __('prevention'); ?>',
        healthy_plant: '<?php echo __('healthy_plant'); ?>',
        not_crop_title: '<?php echo __('not_crop_detected'); ?>',
        not_crop_desc: '<?php echo __('please_upload_crop_image'); ?>'
    };
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // showNotification is now provided globally via footer.php
    
    // Element references
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const analyzeBtn = document.getElementById('analyzeButton');
    const analyzingOverlay = document.getElementById('loadingOverlay');
    const resultCard = document.getElementById('resultsSection');
    const cropSelect = document.getElementById('cropSelect');
    const customCropInput = document.getElementById('customCropInput');
    const customCrop = document.getElementById('customCrop');
    
    // Crop selector
    cropSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            customCropInput.style.display = 'block';
            customCrop.focus();
        } else {
            customCropInput.style.display = 'none';
            customCrop.value = '';
        }
    });
    
    function getSelectedCrop() {
        return cropSelect.value === 'other' ? customCrop.value.trim() : cropSelect.value;
    }
    
    // Click to browse
    uploadZone.addEventListener('click', () => fileInput.click());
    
    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('drag-over');
    });
    
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleFile(e.target.files[0]);
    });
    
    function handleFile(file) {
        if (!file.type.startsWith('image/')) {
            showNotification('<?php echo __('please_select_valid_image'); ?>', 'error');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showNotification('<?php echo __('file_too_large_max_5mb'); ?>', 'error');
            return;
        }
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            imagePreview.style.display = 'block';
            imagePreview.classList.add('active');
            uploadZone.style.display = 'none';
            document.getElementById('captureOptions').style.display = 'none';
            analyzeBtn.disabled = false;
        };
        reader.readAsDataURL(file);
    }
    
    // Remove/cancel image
    window.removeImage = function() {
        selectedFile = null;
        fileInput.value = '';
        imagePreview.style.display = 'none';
        imagePreview.classList.remove('active');
        uploadZone.style.display = 'block';
        document.getElementById('captureOptions').style.display = 'grid';
        analyzeBtn.disabled = true;
        resultCard.classList.remove('active');
    };
    
    // Camera functions
    window.openCamera = async function() {
        const modal = document.getElementById('cameraModal');
        const video = document.getElementById('cameraVideo');
        
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            });
            
            video.srcObject = cameraStream;
            modal.classList.add('active');
        } catch (error) {
            console.error('Camera error:', error);
            showNotification('<?php echo __('camera_access_denied'); ?>', 'error');
        }
    };
    
    window.closeCamera = function() {
        const modal = document.getElementById('cameraModal');
        const video = document.getElementById('cameraVideo');
        
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        
        video.srcObject = null;
        modal.classList.remove('active');
    };
    
    window.captureImage = function() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('cameraCanvas');
        const context = canvas.getContext('2d');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0);
        
        canvas.toBlob(function(blob) {
            selectedFile = new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' });
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                imagePreview.classList.add('active');
                uploadZone.style.display = 'none';
                document.getElementById('captureOptions').style.display = 'none';
                analyzeBtn.disabled = false;
            };
            reader.readAsDataURL(selectedFile);
            
            window.closeCamera();
        }, 'image/jpeg', 0.95);
    };
    
    window.selectFile = function() {
        fileInput.click();
    };
    
    // Form submission
    document.getElementById('diseaseDetectionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!selectedFile) {
            showNotification('<?php echo __('please_upload_or_capture_image'); ?>', 'error');
            return;
        }
        const crop = getSelectedCrop();
        if (!crop) {
            showNotification(currentLang === 'bn' ? 'অনুগ্রহ করে প্রথমে ফসল নির্বাচন করুন' : 'Please select a crop first', 'warning');
            cropSelect.focus();
            return;
        }
        analyzeImage();
    });
    

    const API_BASE = <?php echo json_encode($SYSTEM_SETTINGS['disease_detection_api_url'] ?? ''); ?>;


    function analyzeImage() {
        // Check if API URL is configured
        if (!API_BASE || API_BASE.trim() === '') {
            analyzingOverlay.classList.remove('active');
            showNotification('<?php echo __('Disease Detection API is not configured. Please contact administrator.'); ?>', 'error');
            analyzeBtn.disabled = false;
            return;
        }

        const formData = new FormData();
        formData.append('image', selectedFile);
        formData.append('crop', getSelectedCrop());
        
        analyzingOverlay.classList.add('active');
        resultCard.classList.remove('active');
        analyzeBtn.disabled = true;
        
        fetch(API_BASE, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`API returned status ${response.status}`);
            }
            return response.json();
        })
        .then(response => {
            analyzingOverlay.classList.remove('active');
            if (response.success) {
                displayResults(response.data);
                // Show single toast based on result priority
                if (response.data.is_healthy) {
                    showNotification('Great news! Your plant appears healthy.', 'success');
                } else if (response.data.is_uncertain) {
                    showNotification('Detection uncertain. Please try a clearer image or consult an agricultural expert.', 'warning');
                } else {
                    showNotification('Disease detected. See recommendations below.', 'info');
                }
            } else {
                if (response.error_code === 'NOT_CROP') {
                    showNotCropError(response);
                } else {
                    showNotification(response.message || 'Analysis failed', 'error');
                }
            }
            analyzeBtn.disabled = false;
        })
        .catch(error => {
            analyzingOverlay.classList.remove('active');
            console.error('Analysis error:', error);
            
            // Show more specific error message
            let errorMessage = '<?php echo __('Error analyzing image. Please try again.'); ?>';
            
            if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                errorMessage = '<?php echo __('Cannot connect to Disease Detection API. Please ensure the API server is running.'); ?>';
            } else if (error.message.includes('status')) {
                errorMessage = '<?php echo __('Disease Detection API error. Please contact administrator.'); ?>';
            }
            
            showNotification(errorMessage, 'error');
            analyzeBtn.disabled = false;
        });
    }
    
    function showNotCropError(response) {
        const title = translations.not_crop_title;
        const desc = translations.not_crop_desc;
        
        resultCard.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <span class="material-icons" style="font-size: 4rem; color: #dc3545;">report_problem</span>
                <h2 style="color: #dc3545; margin: 1rem 0;">${escapeHtml(title)}</h2>
                <p style="color: #666; font-size: 1.1rem;">${escapeHtml(desc)}</p>
            </div>
        `;
        resultCard.classList.add('active');
    }
    
    function displayResults(data) {
        const confidence = parseFloat(data.confidence);
        let confidenceClass = confidence >= 80 ? 'high' : (confidence >= 60 ? 'medium' : 'low');
        const isHealthy = data.is_healthy || false;
        const isUncertain = data.is_uncertain || confidence < 50;
        
        let statusIcon = isHealthy ? 'check_circle' : (isUncertain ? 'help_outline' : 'bug_report');
        let statusColor = isHealthy ? '#4caf50' : (isUncertain ? '#ff9800' : '#f44336');
        
        const html = `
            ${data.warning ? `
            <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-icons" style="color: #856404;">warning</span>
                <span style="color: #856404;">${escapeHtml(data.warning)}</span>
            </div>
            ` : ''}
            
            ${isHealthy ? `
            <div style="background: linear-gradient(135deg, #4caf50, #8bc34a); color: white; padding: 2rem; border-radius: 12px; text-align: center; margin-bottom: 1.5rem;">
                <span class="material-icons" style="font-size: 3rem;">eco</span>
                <h2 style="margin: 0.5rem 0;">${translations.healthy_plant}</h2>
            </div>
            ` : ''}
            
            <div class="disease-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid #f3f4f6;">
                <div>
                    <p style="color: #666; margin: 0 0 0.5rem 0;">${translations.crop}: <strong>${escapeHtml(data.crop || 'Unknown')}</strong></p>
                    <h2 class="disease-name" style="color: ${statusColor}; margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-icons" style="vertical-align: middle;">${statusIcon}</span>
                        ${currentLang === 'bn' && data.disease_name_bn ? escapeHtml(data.disease_name_bn) : escapeHtml(data.disease_name || 'Unknown')}
                    </h2>
                </div>
                <div style="text-align: right;">
                    <div class="confidence-badge confidence-${confidenceClass}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: #f9fafb; border-radius: 12px; margin-bottom: 0.5rem;">
                        <span class="material-icons" style="font-size: 1.2rem; color: var(--primary);">${isUncertain ? 'help_outline' : 'verified'}</span>
                        <span style="font-weight: 700; font-size: 1.2rem; color: var(--primary);">${confidence.toFixed(1)}%</span>
                    </div>
                    <div>
                        <span class="severity-badge severity-${data.severity || 'low'}" style="display: inline-block; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem; background: ${data.severity === 'high' ? '#ef4444' : data.severity === 'medium' ? '#f59e0b' : '#22c55e'}; color: white;">${(data.severity || 'low').toUpperCase()}</span>
                    </div>
                </div>
            </div>
            
            ${data.symptoms ? `
            <div class="info-section" style="margin: 1.5rem 0; padding: 1.5rem; background: #fff3e0; border-radius: 12px; border-left: 4px solid #ff9800;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: #e65100; margin: 0 0 0.75rem 0;">
                    <span class="material-icons">warning</span> ${translations.symptoms}
                </h3>
                <p style="color: #e65100; margin: 0; line-height: 1.6;">${escapeHtml(currentLang === 'bn' && data.symptoms_bn ? data.symptoms_bn : data.symptoms)}</p>
            </div>
            ` : ''}
            
            ${data.treatment ? `
            <div class="info-section" style="margin: 1.5rem 0; padding: 1.5rem; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 12px; border-left: 4px solid #4caf50;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: #065f46; margin: 0 0 0.75rem 0;">
                    <span class="material-icons">medical_services</span> ${translations.treatment}
                </h3>
                <p style="color: #065f46; margin: 0; line-height: 1.6;">${escapeHtml(currentLang === 'bn' && data.treatment_bn ? data.treatment_bn : data.treatment)}</p>
            </div>
            ` : ''}
            
            ${data.organic_treatment ? `
            <div class="info-section" style="margin: 1.5rem 0; padding: 1.5rem; background: #e8f5e9; border-radius: 12px; border-left: 4px solid #4caf50;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: #1b5e20; margin: 0 0 0.75rem 0;">
                    <span class="material-icons">eco</span> ${translations.organic_treatment}
                </h3>
                <p style="color: #1b5e20; margin: 0; line-height: 1.6;">${escapeHtml(currentLang === 'bn' && data.organic_treatment_bn ? data.organic_treatment_bn : data.organic_treatment)}</p>
            </div>
            ` : ''}
            
            ${data.prevention ? `
            <div class="info-section" style="margin: 1.5rem 0; padding: 1.5rem; background: #e3f2fd; border-radius: 12px; border-left: 4px solid #2196f3;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: #0d47a1; margin: 0 0 0.75rem 0;">
                    <span class="material-icons">shield</span> ${translations.prevention}
                </h3>
                <p style="color: #0d47a1; margin: 0; line-height: 1.6;">${escapeHtml(currentLang === 'bn' && data.prevention_bn ? data.prevention_bn : data.prevention)}</p>
            </div>
            ` : ''}
            
            <div class="action-buttons">
                <button type="button" class="action-btn action-btn-primary" onclick="analyzeAnother()">
                    <span class="material-icons">refresh</span>
                    <?php echo __('analyze_another'); ?>
                </button>
                <button type="button" class="action-btn action-btn-secondary" onclick="window.print()">
                    <span class="material-icons">print</span>
                    <?php echo __('print_results'); ?>
                </button>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center; color: #888; font-size: 0.9rem;">
                <span class="material-icons" style="vertical-align: middle; font-size: 1rem;">schedule</span>
                Detected on ${new Date(data.detected_at || Date.now()).toLocaleString()}
            </div>
        `;
        
        resultCard.innerHTML = html;
        resultCard.classList.add('active');
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    window.analyzeAnother = function() {
        removeImage();
        resultCard.classList.remove('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
})();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
