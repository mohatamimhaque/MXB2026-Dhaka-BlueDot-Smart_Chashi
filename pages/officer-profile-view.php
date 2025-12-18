<?php
include __DIR__ . '/../layouts/header.php';

$db = new Database();
$officerId = $_GET['id'] ?? null;

if (!$officerId) {
    redirect('dashboard');
}

// Get officer information
$officer = $db->single("SELECT * FROM users WHERE user_id = ? AND role = 'officer'", [$officerId]);

if (!$officer) {
    redirect('dashboard');
}

// Get officer's public statistics
$farmersSupported = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
$alertsIssued = $db->single("SELECT COUNT(*) as count FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;
$reportsHandled = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [])['count'] ?? 0;
?>

<section class="hero">
    <h1><span class="material-icons">badge</span> Officer Profile</h1>
    <p>Agricultural Officer Information</p>
</section>

<div class="profile-view-grid">
    <!-- Officer Information Card -->
    <div class="card profile-view-card">
        <div class="profile-view-header">
            <div class="profile-view-avatar officer-avatar">
                <span class="material-icons">admin_panel_settings</span>
            </div>
            <div class="profile-view-info">
                <h2><?php echo htmlspecialchars($officer['first_name'] . ' ' . ($officer['last_name'] ?? '')); ?></h2>
                <p class="profile-view-role">
                    <span class="material-icons">badge</span>
                    Agriculture Officer
                </p>
            </div>
        </div>

        <div class="profile-view-details">
            <div class="profile-detail-item">
                <span class="material-icons">email</span>
                <span><strong>Email:</strong> <?php echo htmlspecialchars($officer['email']); ?></span>
            </div>

            <div class="profile-detail-item">
                <span class="material-icons">phone</span>
                <span><strong>Phone:</strong> <?php echo htmlspecialchars($officer['phone']); ?></span>
            </div>

            <div class="profile-detail-item">
                <span class="material-icons">event</span>
                <span><strong>Joined:</strong> <?php echo date('M Y', strtotime($officer['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="card profile-view-card">
        <h3><span class="material-icons">analytics</span> Monthly Overview</h3>
        <div class="profile-stats-list">
            <div class="stat-item">
                <span class="material-icons stat-item-icon">people</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $farmersSupported; ?></div>
                    <div class="stat-item-label">Farmers in Region</div>
                </div>
            </div>

            <div class="stat-item">
                <span class="material-icons stat-item-icon">notifications_active</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $alertsIssued; ?></div>
                    <div class="stat-item-label">Alerts Issued (30d)</div>
                </div>
            </div>

            <div class="stat-item">
                <span class="material-icons stat-item-icon">description</span>
                <div class="stat-item-info">
                    <div class="stat-item-value"><?php echo $reportsHandled; ?></div>
                    <div class="stat-item-label">Reports Handled (30d)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Officer Responsibilities -->
<div class="card mt-4">
    <h3><span class="material-icons">work</span> Responsibilities</h3>
    <div class="officer-responsibilities">
        <div class="responsibility-item">
            <span class="material-icons">check_circle</span>
            <span>Monitor crop health and disease reports</span>
        </div>
        <div class="responsibility-item">
            <span class="material-icons">check_circle</span>
            <span>Issue weather and agricultural alerts</span>
        </div>
        <div class="responsibility-item">
            <span class="material-icons">check_circle</span>
            <span>Provide farming guidance and support</span>
        </div>
        <div class="responsibility-item">
            <span class="material-icons">check_circle</span>
            <span>Coordinate field visits and inspections</span>
        </div>
        <div class="responsibility-item">
            <span class="material-icons">check_circle</span>
            <span>Create and distribute advisories</span>
        </div>
    </div>
</div>

<div class="card mt-4 text-center">
    <?php if (isLoggedIn() && getCurrentUser()['role'] === 'farmer'): ?>
    <h3><span class="material-icons">contact_support</span> Need Assistance?</h3>
    <p>Contact this officer for agricultural guidance and support.</p>
    <div class="contact-actions">
        <a href="tel:<?php echo htmlspecialchars($officer['phone']); ?>" class="btn mt-2">
            <span class="material-icons">phone</span> Call Officer
        </a>
        <a href="mailto:<?php echo htmlspecialchars($officer['email']); ?>" class="btn btn-secondary mt-2">
            <span class="material-icons">email</span> Send Email
        </a>
    </div>
    <?php else: ?>
    <h3><span class="material-icons">info</span> Agricultural Support</h3>
    <p>Our officers are here to support farmers with expert guidance.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
