<?php
/**
 * Shop Profile Settings Page
 */

require_once __DIR__ . '/../config/config.php';

requireShopLogin();

$db = new ShopDatabase();
$user = getShopUser();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF
    if (!verifyShopCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token.');
        shopRedirect('profile/settings.php');
    }
    
    if ($action === 'update_profile') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $postalCode = sanitize($_POST['postal_code'] ?? '');
        
        if (empty($firstName)) {
            $errors[] = 'First name is required';
        }
        
        if (empty($errors)) {
            $db->update('general_users', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'district' => $district,
                'postal_code' => $postalCode
            ], 'user_id = ?', [$user['user_id']]);
            
            $_SESSION['shop_user_name'] = $firstName;
            setFlashMessage('success', 'Profile updated successfully!');
            shopRedirect('profile/settings.php');
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if (strlen($newPassword) < SHOP_PASSWORD_MIN_LENGTH) {
            $errors[] = 'New password must be at least ' . SHOP_PASSWORD_MIN_LENGTH . ' characters';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }
        
        if (empty($errors)) {
            updateShopUserPassword($user['user_id'], $newPassword);
            setFlashMessage('success', 'Password changed successfully!');
            shopRedirect('profile/settings.php');
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode(', ', $errors));
    }
}

// Refresh user data
$user = getShopUser();

$pageTitle = 'Account Settings';
include __DIR__ . '/../layouts/header.php';
?>

<div class="settings-page container">
    <div class="page-header">
        <a href="<?php echo shopUrl('profile/'); ?>" class="back-link">
            <span class="material-icons">arrow_back</span>
            Back to Profile
        </a>
        <h1><span class="material-icons">settings</span> Account Settings</h1>
    </div>

    <div class="settings-layout">
        <!-- Profile Form -->
        <div class="settings-section">
            <h2>Profile Information</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateShopCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <div class="form-hint">Email cannot be changed</div>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city" class="form-label">City</label>
                        <input type="text" id="city" name="city" class="form-control"
                               value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="district" class="form-label">District</label>
                        <select id="district" name="district" class="form-control">
                            <option value="">Select District</option>
                            <?php
                            $districts = ['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet', 'Barisal', 'Rangpur', 'Mymensingh', 'Comilla', 'Gazipur', 'Narayanganj'];
                            foreach ($districts as $d):
                            ?>
                            <option value="<?php echo $d; ?>" <?php echo ($user['district'] ?? '') === $d ? 'selected' : ''; ?>>
                                <?php echo $d; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control"
                               value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">save</span>
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Password Form -->
        <div class="settings-section">
            <h2>Change Password</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateShopCSRFToken(); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           minlength="<?php echo SHOP_PASSWORD_MIN_LENGTH; ?>" required>
                    <div class="form-hint">Minimum <?php echo SHOP_PASSWORD_MIN_LENGTH; ?> characters</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">lock</span>
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.settings-page {
    padding: var(--spacing-xl) var(--spacing-md);
}

.page-header {
    margin-bottom: var(--spacing-xl);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--gray-500);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-md);
}

.back-link:hover {
    color: var(--primary);
}

.page-header h1 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--font-size-2xl);
    color: var(--gray-800);
}

.settings-layout {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xl);
    max-width: 700px;
}

.settings-section {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.settings-section h2 {
    font-size: var(--font-size-lg);
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--gray-200);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
}

@media (min-width: 640px) {
    .form-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row:has(.form-group:nth-child(3)) {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
