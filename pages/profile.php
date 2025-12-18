<?php

if (!isLoggedIn()) {
    redirect('login');
}

include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
$db = new Database();
$userRole = $user['role'];

// Get role-specific profile
$profile = null;
if ($userRole === 'farmer') {
    $profile = $db->single("SELECT * FROM farmer_profiles WHERE user_id = ?", [$_SESSION['user_id']]);
}

// Get statistics based on role
$stats = [];
if ($userRole === 'farmer') {
    $stats['crops'] = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ?", [$_SESSION['user_id']])['count'] ?? 0;
    $stats['messages'] = $db->single("SELECT COUNT(*) as count FROM chat_messages WHERE sender_id = ?", [$_SESSION['user_id']])['count'] ?? 0;
    $stats['posts'] = $db->single("SELECT COUNT(*) as count FROM community_posts WHERE user_id = ?", [$_SESSION['user_id']])['count'] ?? 0;
} elseif ($userRole === 'officer') {
    $stats['farmers'] = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
    $stats['reports'] = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;
    $stats['alerts'] = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [])['count'] ?? 0;
} elseif ($userRole === 'admin') {
    $stats['total_users'] = $db->single("SELECT COUNT(*) as count FROM users", [])['count'] ?? 0;
    $stats['farmers'] = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
    $stats['officers'] = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'officer'", [])['count'] ?? 0;
    $stats['active_crops'] = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE status = 'growing'", [])['count'] ?? 0;
}
?>

<section class="hero">
    <h1><span class="material-icons" style="vertical-align: middle; font-size: 2rem;">account_circle</span> <?php echo __('your_profile'); ?> - <?php echo ucfirst($userRole); ?></h1>
    <p><?php echo __('manage_account'); ?></p>
</section>

<div class="profile-page-grid">
        <!-- Personal Information -->
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">person</span> <?php echo __('personal_information'); ?></h2>
            <form method="POST" class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName"><?php echo __('first_name'); ?></label>
                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="lastName"><?php echo __('last_name'); ?></label>
                        <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email"><?php echo __('email'); ?></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone"><?php echo __('phone'); ?></label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="role"><?php echo __('account_type'); ?></label>
                    <input type="text" id="role" name="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                </div>

                <button type="submit" class="btn"><?php echo __('update_profile'); ?></button>
            </form>
        </div>

        <!-- Role-Specific Profile -->
        <?php if ($userRole === 'farmer'): ?>
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">agriculture</span> <?php echo __('farming_profile'); ?></h2>
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="landSize"><?php echo __('land_size'); ?></label>
                    <input type="number" id="landSize" name="landSize" value="<?php echo $profile['land_size_hectares'] ?? ''; ?>" step="0.01">
                </div>

                <div class="form-group">
                    <label for="experience"><?php echo __('experience_level'); ?></label>
                    <select id="experience" name="experience">
                        <option value=""><?php echo __('select'); ?></option>
                        <option value="beginner" <?php echo ($profile['experience_level'] ?? '') === 'beginner' ? 'selected' : ''; ?>><?php echo __('beginner'); ?></option>
                        <option value="intermediate" <?php echo ($profile['experience_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>><?php echo __('intermediate'); ?></option>
                        <option value="advanced" <?php echo ($profile['experience_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>><?php echo __('advanced'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="primaryCrop"><?php echo __('primary_crop'); ?></label>
                    <input type="text" id="primaryCrop" name="primaryCrop" placeholder="e.g., Rice, Wheat" value="<?php echo htmlspecialchars($profile['primary_crop'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="region"><?php echo __('region_district'); ?></label>
                    <select id="region" name="region">
                        <option value=""><?php echo __('select'); ?></option>
                        <option value="Dhaka" <?php echo ($profile['region'] ?? '') === 'Dhaka' ? 'selected' : ''; ?>>Dhaka</option>
                        <option value="Chittagong" <?php echo ($profile['region'] ?? '') === 'Chittagong' ? 'selected' : ''; ?>>Chittagong</option>
                        <option value="Khulna" <?php echo ($profile['region'] ?? '') === 'Khulna' ? 'selected' : ''; ?>>Khulna</option>
                        <option value="Rangpur" <?php echo ($profile['region'] ?? '') === 'Rangpur' ? 'selected' : ''; ?>>Rangpur</option>
                        <option value="Sylhet" <?php echo ($profile['region'] ?? '') === 'Sylhet' ? 'selected' : ''; ?>>Sylhet</option>
                        <option value="Barisal" <?php echo ($profile['region'] ?? '') === 'Barisal' ? 'selected' : ''; ?>>Barisal</option>
                    </select>
                </div>

                <button type="submit" class="btn"><?php echo __('update_farming_info'); ?></button>
            </form>
        </div>
        <?php elseif ($userRole === 'officer'): ?>
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">badge</span> Officer Profile</h2>
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="officerRegion">Assigned Region</label>
                    <select id="officerRegion" name="officerRegion">
                        <option value="">Select Region</option>
                        <option value="Dhaka">Dhaka</option>
                        <option value="Chittagong">Chittagong</option>
                        <option value="Khulna">Khulna</option>
                        <option value="Rangpur">Rangpur</option>
                        <option value="Sylhet">Sylhet</option>
                        <option value="Barisal">Barisal</option>
                        <option value="Rajshahi">Rajshahi</option>
                        <option value="Mymensingh">Mymensingh</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="officerId">Officer ID</label>
                    <input type="text" id="officerId" name="officerId" value="AGR-<?php echo str_pad($user['user_id'], 6, '0', STR_PAD_LEFT); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <select id="specialization" name="specialization">
                        <option value="">Select Specialization</option>
                        <option value="crop_management">Crop Management</option>
                        <option value="pest_control">Pest Control</option>
                        <option value="soil_health">Soil Health</option>
                        <option value="irrigation">Irrigation Systems</option>
                        <option value="organic_farming">Organic Farming</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" placeholder="e.g., Department of Agriculture Extension">
                </div>

                <button type="submit" class="btn">Update Officer Info</button>
            </form>
        </div>
        <?php elseif ($userRole === 'admin'): ?>
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">admin_panel_settings</span> Administrator Profile</h2>
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="adminId">Admin ID</label>
                    <input type="text" id="adminId" name="adminId" value="ADM-<?php echo str_pad($user['user_id'], 6, '0', STR_PAD_LEFT); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="accessLevel">Access Level</label>
                    <select id="accessLevel" name="accessLevel">
                        <option value="super_admin">Super Admin (Full Access)</option>
                        <option value="admin">Admin (Standard Access)</option>
                        <option value="moderator">Moderator (Limited Access)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="responsibilities">Responsibilities</label>
                    <textarea id="responsibilities" name="responsibilities" rows="4" placeholder="Enter your key responsibilities..."></textarea>
                </div>

                <button type="submit" class="btn">Update Admin Info</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Security -->
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">lock</span> <?php echo __('security'); ?></h2>
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="currentPassword"><?php echo __('current_password'); ?></label>
                    <input type="password" id="currentPassword" name="currentPassword" placeholder="<?php echo __('current_password'); ?>">
                </div>

                <div class="form-group">
                    <label for="newPassword"><?php echo __('new_password'); ?></label>
                    <input type="password" id="newPassword" name="newPassword" placeholder="<?php echo __('new_password'); ?>">
                </div>

                <div class="form-group">
                    <label for="confirmPassword"><?php echo __('confirm_password'); ?></label>
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="<?php echo __('confirm_password'); ?>">
                </div>

                <button type="submit" class="btn"><?php echo __('change_password'); ?></button>
            </form>
        </div>

        <!-- Account Statistics -->
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">bar_chart</span> <?php echo __('account_statistics'); ?></h2>
            <div class="profile-stats-list">
                <div class="stat-row">
                    <span><?php echo __('account_created'); ?>:</span>
                    <strong><?php echo date('M d, Y', strtotime($user['created_at'])); ?></strong>
                </div>

                <div class="stat-row">
                    <span><?php echo __('last_login'); ?>:</span>
                    <strong><?php echo date('M d, Y', strtotime($user['last_login'] ?? $user['created_at'])); ?></strong>
                </div>

                <?php if ($userRole === 'farmer'): ?>
                <div class="stat-row">
                    <span><?php echo __('total_crops'); ?>:</span>
                    <strong class="badge badge-success"><?php echo $stats['crops']; ?></strong>
                </div>

                <div class="stat-row">
                    <span><?php echo __('chat_messages'); ?>:</span>
                    <strong class="badge badge-info"><?php echo $stats['messages']; ?></strong>
                </div>

                <div class="stat-row">
                    <span><?php echo __('community_posts'); ?>:</span>
                    <strong class="badge badge-info"><?php echo $stats['posts']; ?></strong>
                </div>
                <?php elseif ($userRole === 'officer'): ?>
                <div class="stat-row">
                    <span>Farmers Supervised:</span>
                    <strong class="badge badge-success"><?php echo $stats['farmers']; ?></strong>
                </div>

                <div class="stat-row">
                    <span>Reports (30 days):</span>
                    <strong class="badge badge-info"><?php echo $stats['reports']; ?></strong>
                </div>

                <div class="stat-row">
                    <span>Alerts Issued (7 days):</span>
                    <strong class="badge badge-warning"><?php echo $stats['alerts']; ?></strong>
                </div>
                <?php elseif ($userRole === 'admin'): ?>
                <div class="stat-row">
                    <span>Total Users:</span>
                    <strong class="badge" style="background: var(--primary);"><?php echo $stats['total_users']; ?></strong>
                </div>

                <div class="stat-row">
                    <span>Farmers:</span>
                    <strong class="badge badge-success"><?php echo $stats['farmers']; ?></strong>
                </div>

                <div class="stat-row">
                    <span>Officers:</span>
                    <strong class="badge badge-info"><?php echo $stats['officers']; ?></strong>
                </div>

                <div class="stat-row">
                    <span>Active Crops:</span>
                    <strong class="badge badge-success"><?php echo $stats['active_crops']; ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role-Specific Quick Actions -->
        <?php if ($userRole === 'officer'): ?>
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">bolt</span> Quick Actions</h2>
            <div class="profile-actions-grid">
                <a href="<?php echo $base_url; ?>officer-dashboard" class="btn btn-small"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">dashboard</span> Officer Dashboard</a>
                <a href="<?php echo $base_url; ?>farmer-reports" class="btn btn-small btn-secondary"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">description</span> View Reports</a>
                <a href="<?php echo $base_url; ?>issue-alert" class="btn btn-small btn-warning"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">warning</span> Issue Alert</a>
            </div>
        </div>
        <?php elseif ($userRole === 'admin'): ?>
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">build</span> Admin Tools</h2>
            <div class="profile-actions-grid">
                <a href="<?php echo $base_url; ?>admin-dashboard" class="btn btn-small"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">dashboard</span> Admin Dashboard</a>
                <a href="<?php echo $base_url; ?>user-management" class="btn btn-small btn-secondary"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">people</span> Manage Users</a>
                <a href="<?php echo $base_url; ?>system-settings" class="btn btn-small btn-info"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">settings</span> Settings</a>
                <a href="<?php echo $base_url; ?>analytics" class="btn btn-small btn-success"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">analytics</span> Analytics</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Preferences -->
        <div class="card profile-card">
            <h2><span class="material-icons" style="vertical-align: middle;">tune</span> <?php echo __('preferences'); ?></h2>
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="theme"><?php echo __('theme'); ?></label>
                    <select id="theme" name="theme">
                        <option><?php echo __('light'); ?></option>
                        <option><?php echo __('dark'); ?></option>
                    </select>
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" checked>
                        <span><?php echo __('marketing_emails'); ?></span>
                    </label>
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" checked>
                        <span><?php echo __('product_updates'); ?></span>
                    </label>
                </div>

                <button type="submit" class="btn"><?php echo __('save_preferences'); ?></button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="card profile-card danger-zone-card">
            <h2 class="danger-heading"><span class="material-icons" style="vertical-align: middle;">warning</span> <?php echo __('danger_zone'); ?></h2>
            <p><?php echo __('delete_account_warning'); ?></p>
            <button class="btn btn-danger btn-small"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">delete_forever</span> <?php echo __('delete_account'); ?></button>
        </div>
    </div>



<?php include __DIR__ . '/../layouts/footer.php'; ?>
