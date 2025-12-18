<?php

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();
$crops = $db->resultSet("SELECT * FROM crop_data WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 5", [$_SESSION['user_id']]);
?>

<section class="hero">
    <h1><span class="material-icons" style="vertical-align: middle; font-size: 2rem;">bug_report</span> <?php echo __('disease_detection'); ?></h1>
    <p><?php echo __('detect_crop_diseases'); ?></p>
</section>

<?php if ($crops): ?>
    <div class="disease-detection-grid">
        <div class="card disease-form-card">
            <h2><span class="material-icons" style="vertical-align: middle;">photo_camera</span> <?php echo __('analyze_photo'); ?></h2>
            <form id="diseaseForm" method="POST" enctype="multipart/form-data" class="disease-form">
                <div class="form-group">
                    <label for="cropId"><?php echo __('select_crop'); ?> *</label>
                    <select id="cropId" name="cropId" required>
                        <option value=""><?php echo __('choose_crop'); ?></option>
                        <?php foreach ($crops as $crop): ?>
                            <option value="<?php echo $crop['crop_id']; ?>">
                                <?php echo htmlspecialchars($crop['crop_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="diseaseImage"><?php echo __('upload_image'); ?> *</label>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.5rem;"><?php echo __('jpg_png_max'); ?></p>
                    <input type="file" id="diseaseImage" name="diseaseImage" accept="image/*" required>
                </div>

                <button type="submit" class="btn"><?php echo __('analyze'); ?></button>
            </form>

            <div id="diseaseResults" style="display: none; margin-top: 1.5rem;"></div>
        </div>

        <div class="card disease-instructions-card">
            <h3><span class="material-icons" style="vertical-align: middle;">checklist</span> <?php echo __('how_to_use'); ?></h3>
            <ol class="instruction-list">
                <li><span class="material-icons">check_circle</span><?php echo __('select_crop'); ?></li>
                <li><span class="material-icons">check_circle</span><?php echo __('take_clear_photo'); ?></li>
                <li><span class="material-icons">check_circle</span><?php echo __('good_lighting'); ?></li>
                <li><span class="material-icons">check_circle</span><?php echo __('upload_get_results'); ?></li>
            </ol>
        </div>
    </div>
    <?php else: ?>
        <div class="notice notice-info mt-3">
            <p><?php echo __('add_crop_first'); ?></p>
            <a href="<?php echo $base_url; ?>crops" class="btn btn-small mt-2"><?php echo __('add_crop'); ?></a>
        </div>
    <?php endif; ?>
        </div>

    <h2 class="mt-4" style="margin-left:28px"><span class="material-icons" style="vertical-align: middle;">library_books</span> Common Diseases Reference</h2>
    <div class="disease-reference-grid">
        <div class="card disease-ref-card">
            <h4><span class="material-icons" style="color: #dc3545;">emergency</span> Tomato Diseases</h4>
            <ul class="disease-list">
                <li><span class="material-icons">arrow_right</span>Early Blight</li>
                <li><span class="material-icons">arrow_right</span>Late Blight</li>
                <li><span class="material-icons">arrow_right</span>Fusarium Wilt</li>
                <li><span class="material-icons">arrow_right</span>Bacterial Wilt</li>
            </ul>
        </div>

        <div class="card disease-ref-card">
            <h4><span class="material-icons" style="color: #28a745;">eco</span> Vegetable Diseases</h4>
            <ul class="disease-list">
                <li><span class="material-icons">arrow_right</span>Powdery Mildew</li>
                <li><span class="material-icons">arrow_right</span>Downy Mildew</li>
                <li><span class="material-icons">arrow_right</span>Damping Off</li>
                <li><span class="material-icons">arrow_right</span>Root Rot</li>
            </ul>
        </div>
    </div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
