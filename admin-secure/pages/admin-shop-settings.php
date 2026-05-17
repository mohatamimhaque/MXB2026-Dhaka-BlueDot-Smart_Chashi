<?php
/**
 * Admin Shop Settings
 * Controls footer, delivery, contact info, and social links for the buyer shop.
 */
$currPage = "Shop Settings";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../shop/config/config.php';
require_once __DIR__ . '/../layouts/admin-header.php';

$shopDb = new ShopDatabase();

// Load all current shop settings
$keys = [
    'footer_about', 'footer_email', 'footer_phone', 'footer_address',
    'footer_facebook', 'footer_instagram', 'footer_youtube', 'footer_twitter',
    'footer_copyright', 'delivery_note', 'delivery_charge', 'shop_email',
    'shop_phone', 'shop_name_override'
];
$settings = $shopDb->getSettings($keys);
$csrf = generateCSRFToken();
?>

<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1 class="page-title">Shop Settings</h1>
            <p class="page-subtitle">Control buyer-facing shop content: footer, delivery, and contact info</p>
        </div>
    </div>
</div>

<div class="page-content">
    <div id="settingsAlert" style="display:none;" class="alert mb-3"></div>

    <form id="shopSettingsForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <div class="settings-grid">

            <!-- Delivery Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">local_shipping</span> Delivery</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Delivery Note <small>(shown in top bar)</small></label>
                        <input type="text" name="delivery_note" class="form-control"
                               value="<?php echo htmlspecialchars($settings['delivery_note'] ?? 'Free delivery on orders over ৳500'); ?>"
                               placeholder="e.g. Free delivery on orders over ৳500">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Delivery Charge (৳)</label>
                        <input type="number" name="delivery_charge" class="form-control" min="0" step="0.01"
                               value="<?php echo htmlspecialchars($settings['delivery_charge'] ?? '60'); ?>"
                               placeholder="0 for free">
                    </div>
                </div>
            </div>

            <!-- Footer About -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">info</span> Footer About</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Shop Name Override <small>(leave blank to use SHOP_NAME constant)</small></label>
                        <input type="text" name="shop_name_override" class="form-control"
                               value="<?php echo htmlspecialchars($settings['shop_name_override'] ?? ''); ?>"
                               placeholder="SmartChashi Shop">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Footer Copyright Text</label>
                        <input type="text" name="footer_copyright" class="form-control"
                               value="<?php echo htmlspecialchars($settings['footer_copyright'] ?? ''); ?>"
                               placeholder="SmartChashi">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Footer About Text</label>
                        <textarea name="footer_about" class="form-control" rows="3"
                                  placeholder="Short description shown in the footer..."><?php echo htmlspecialchars($settings['footer_about'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">contact_phone</span> Contact Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Footer Email</label>
                        <input type="email" name="footer_email" class="form-control"
                               value="<?php echo htmlspecialchars($settings['footer_email'] ?? ''); ?>"
                               placeholder="info@smartchashi.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Footer Phone</label>
                        <input type="text" name="footer_phone" class="form-control"
                               value="<?php echo htmlspecialchars($settings['footer_phone'] ?? ''); ?>"
                               placeholder="+880 1700-000000">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Footer Address</label>
                        <input type="text" name="footer_address" class="form-control"
                               value="<?php echo htmlspecialchars($settings['footer_address'] ?? ''); ?>"
                               placeholder="Dhaka, Bangladesh">
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-icons">share</span> Social Media Links</h3>
                </div>
                <div class="card-body">
                    <?php
                    $socials = [
                        'footer_facebook'  => ['Facebook',  'https://facebook.com/'],
                        'footer_instagram' => ['Instagram', 'https://instagram.com/'],
                        'footer_youtube'   => ['YouTube',   'https://youtube.com/'],
                        'footer_twitter'   => ['X / Twitter', 'https://x.com/'],
                    ];
                    foreach ($socials as $key => [$label, $placeholder]):
                    ?>
                    <div class="form-group">
                        <label class="form-label"><?php echo $label; ?> <small>(full URL, leave blank to hide)</small></label>
                        <input type="url" name="<?php echo $key; ?>" class="form-control"
                               value="<?php echo htmlspecialchars($settings[$key] ?? ''); ?>"
                               placeholder="<?php echo $placeholder; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- .settings-grid -->

        <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1rem;">
            <button type="button" class="btn btn-secondary" onclick="location.reload()">
                <span class="material-icons">refresh</span> Reset
            </button>
            <button type="submit" class="btn btn-primary" id="saveBtn">
                <span class="material-icons">save</span> Save Changes
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('shopSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveBtn');
    const origHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Saving...';

    const data = Object.fromEntries(new FormData(this).entries());

    try {
        const res = await fetch('<?php echo $base_url; ?>admin-secure/ajax/shop-settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        showAlert(json.success ? 'success' : 'error', json.message);
    } catch (err) {
        showAlert('error', 'Network error. Please try again.');
    }

    saveBtn.disabled = false;
    saveBtn.innerHTML = origHtml;
});

function showAlert(type, message) {
    const el = document.getElementById('settingsAlert');
    el.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' mb-3';
    el.textContent = message;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => { el.style.display = 'none'; }, 6000);
}
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
