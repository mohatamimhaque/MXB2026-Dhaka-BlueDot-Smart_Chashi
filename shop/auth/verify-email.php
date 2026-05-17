<?php
/**
 * Email Verification Page
 */

require_once __DIR__ . '/../config/config.php';

$db = new ShopDatabase();
$message = null;
$success = false;

// Handle token verification
if (isset($_GET['token'])) {
    $result = verifyEmailToken($_GET['token']);
    $message = $result['message'];
    $success = $result['success'];
}

// Handle resend request
$showResend = isset($_GET['resend']) || (isShopLoggedIn() && !isEmailVerified());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend']) && isShopLoggedIn()) {
    $result = resendVerificationEmail($_SESSION['shop_user_id']);
    $message = $result['message'];
    $success = $result['success'];
}

$pageTitle = 'Verify Email';
include __DIR__ . '/../layouts/header.php';
?>

<div class="verify-page container">
    <div class="verify-card">
        <?php if ($success): ?>
        <!-- Success State -->
        <div class="verify-success">
            <div class="success-icon">
                <span class="material-icons">verified</span>
            </div>
            <h1>Email Verified!</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo shopUrl('pages/products.php'); ?>" class="btn btn-primary btn-lg">
                <span class="material-icons">storefront</span>
                Start Shopping
            </a>
        </div>
        
        <?php elseif (isset($_GET['token']) && !$success): ?>
        <!-- Failed Verification -->
        <div class="verify-failed">
            <div class="failed-icon">
                <span class="material-icons">error</span>
            </div>
            <h1>Verification Failed</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <?php if (isShopLoggedIn()): ?>
            <form method="POST">
                <button type="submit" name="resend" class="btn btn-primary">
                    <span class="material-icons">email</span>
                    Send New Verification Link
                </button>
            </form>
            <?php else: ?>
            <a href="<?php echo shopUrl('auth/login.php'); ?>" class="btn btn-primary">
                Login to Resend
            </a>
            <?php endif; ?>
        </div>
        
        <?php elseif ($showResend): ?>
        <!-- Resend Verification -->
        <div class="verify-pending">
            <div class="pending-icon">
                <span class="material-icons">mail_outline</span>
            </div>
            <h1>Verify Your Email</h1>
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $success ? 'success' : 'info'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isShopLoggedIn()): 
                $user = getShopUser();
            ?>
            <p>We sent a verification link to:</p>
            <p class="email-display"><?php echo htmlspecialchars($user['email']); ?></p>
            <p class="hint">Didn't receive the email? Check your spam folder or request a new one.</p>
            
            <form method="POST">
                <button type="submit" name="resend" class="btn btn-primary btn-lg">
                    <span class="material-icons">refresh</span>
                    Resend Verification Email
                </button>
            </form>
            
            <div class="verify-actions">
                <a href="<?php echo shopUrl(); ?>">Continue browsing</a>
                <span>|</span>
                <a href="<?php echo shopUrl('auth/logout.php'); ?>">Logout</a>
            </div>
            <?php else: ?>
            <p>Please login to resend verification email.</p>
            <a href="<?php echo shopUrl('auth/login.php'); ?>" class="btn btn-primary">
                Login
            </a>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Default State -->
        <div class="verify-info">
            <div class="info-icon">
                <span class="material-icons">mark_email_read</span>
            </div>
            <h1>Check Your Email</h1>
            <p>We've sent a verification link to your email address. Click the link to verify your account.</p>
            <a href="<?php echo shopUrl('auth/login.php'); ?>" class="btn btn-outline">
                Go to Login
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.verify-page {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-xl);
}

.verify-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: var(--spacing-2xl);
    box-shadow: var(--shadow-lg);
    text-align: center;
    max-width: 450px;
    width: 100%;
}

.success-icon,
.failed-icon,
.pending-icon,
.info-icon {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--spacing-lg);
}

.success-icon {
    background: linear-gradient(135deg, var(--success), #059669);
}

.failed-icon {
    background: linear-gradient(135deg, var(--danger), #dc2626);
}

.pending-icon {
    background: linear-gradient(135deg, var(--warning), #d97706);
}

.info-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.success-icon .material-icons,
.failed-icon .material-icons,
.pending-icon .material-icons,
.info-icon .material-icons {
    font-size: 2.5rem;
    color: var(--white);
}

.verify-card h1 {
    font-size: var(--font-size-xl);
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.verify-card p {
    color: var(--gray-600);
    margin-bottom: var(--spacing-lg);
}

.email-display {
    font-weight: 600;
    color: var(--primary);
    font-size: var(--font-size-lg);
}

.hint {
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.verify-actions {
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--gray-200);
    font-size: var(--font-size-sm);
    color: var(--gray-500);
}

.verify-actions a {
    color: var(--primary);
}

.verify-actions span {
    margin: 0 var(--spacing-sm);
}

.alert {
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
