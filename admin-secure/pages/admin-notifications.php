<?php
/**
 * Admin — Notification Management
 * Create, view, and manage system notifications.
 */
$currPage = "Notifications";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layouts/admin-header.php';

// Handle form POST (create notification)
$createMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_notification') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $createMsg = ['error' => 'Invalid token'];
    } else {
        $title    = trim($_POST['title'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $type     = $_POST['ntype'] ?? 'info';
        $target   = $_POST['target'] ?? 'all';
        $targetId = ($target === 'user') ? (int)($_POST['target_user_id'] ?? 0) : null;

        if ($title && $message) {
            try {
                if ($target === 'all') {
                    // Insert one row for all (user_id = NULL)
                    $db->query("INSERT INTO admin_notifications (user_id, title, message, type, created_by) VALUES (NULL, ?, ?, ?, ?)")
                       ->bind(1,$title)->bind(2,$message)->bind(3,$type)->bind(4,$_SESSION['user_id'])->execute();
                    $createMsg = ['success' => 'Notification sent to all users.'];
                } elseif ($target === 'role') {
                    $role = $_POST['target_role'] ?? 'farmer';
                    $users = $db->resultSet("SELECT user_id FROM users WHERE role = ?", [$role]);
                    foreach ($users as $u) {
                        $db->query("INSERT INTO admin_notifications (user_id, title, message, type, created_by) VALUES (?,?,?,?,?)")
                           ->bind(1,$u['user_id'])->bind(2,$title)->bind(3,$message)->bind(4,$type)->bind(5,$_SESSION['user_id'])->execute();
                    }
                    $createMsg = ['success' => 'Notification sent to all ' . ucfirst($role) . 's.'];
                } elseif ($target === 'user' && $targetId) {
                    $db->query("INSERT INTO admin_notifications (user_id, title, message, type, created_by) VALUES (?,?,?,?,?)")
                       ->bind(1,$targetId)->bind(2,$title)->bind(3,$message)->bind(4,$type)->bind(5,$_SESSION['user_id'])->execute();
                    $createMsg = ['success' => 'Notification sent to user #' . $targetId . '.'];
                }
            } catch (Exception $e) {
                $createMsg = ['error' => $e->getMessage()];
            }
        } else {
            $createMsg = ['error' => 'Title and message are required.'];
        }
    }
}

// Load recent notifications
try {
    $notifications = $db->resultSet(
        "SELECT n.*, u.first_name, u.last_name
         FROM admin_notifications n
         LEFT JOIN users u ON n.user_id = u.user_id
         ORDER BY n.created_at DESC LIMIT 100"
    );
    $nStats = [
        'total'  => (int)($db->single("SELECT COUNT(*) as c FROM admin_notifications")['c'] ?? 0),
        'unread' => (int)($db->single("SELECT COUNT(*) as c FROM admin_notifications WHERE is_read=0")['c'] ?? 0),
        'today'  => (int)($db->single("SELECT COUNT(*) as c FROM admin_notifications WHERE DATE(created_at)=CURDATE()")['c'] ?? 0),
    ];
} catch (Exception $e) {
    $notifications = [];
    $nStats = ['total'=>0,'unread'=>0,'today'=>0];
}

$userList = $db->resultSet("SELECT user_id, first_name, last_name, email, role FROM users ORDER BY first_name LIMIT 200");
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-icons" style="vertical-align:middle;color:var(--primary)">notifications</span>
            Notification Management
        </h1>
        <p class="page-subtitle">Create and manage system notifications for users.</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(99,102,241,.15)"><span class="material-icons" style="color:#6366f1">notifications</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($nStats['total']); ?></span><span class="stat-label">Total Sent</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.15)"><span class="material-icons" style="color:#f59e0b">mark_email_unread</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($nStats['unread']); ?></span><span class="stat-label">Unread</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.15)"><span class="material-icons" style="color:#10b981">today</span></div>
        <div class="stat-content"><span class="stat-value"><?php echo number_format($nStats['today']); ?></span><span class="stat-label">Sent Today</span></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:24px" class="notif-grid">

    <!-- Create Notification -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="material-icons">add_alert</span> Send Notification</h3>
        </div>
        <div class="card-body">
            <?php if ($createMsg): ?>
            <div style="padding:12px;border-radius:8px;margin-bottom:16px;<?php echo isset($createMsg['success']) ? 'background:rgba(16,185,129,.12);color:#059669' : 'background:rgba(239,68,68,.12);color:#dc2626'; ?>">
                <span class="material-icons" style="vertical-align:middle;font-size:16px"><?php echo isset($createMsg['success']) ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($createMsg['success'] ?? $createMsg['error']); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="create_notification">

                <div class="form-group">
                    <label class="form-label">Notification Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. System Maintenance Notice">
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Write your notification message..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="ntype" class="form-control">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Alert</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Send To</label>
                    <select name="target" class="form-control" id="targetSelect" onchange="onTargetChange()">
                        <option value="all">All Users</option>
                        <option value="role">By Role</option>
                        <option value="user">Specific User</option>
                    </select>
                </div>

                <div id="roleTarget" style="display:none" class="form-group">
                    <label class="form-label">Role</label>
                    <select name="target_role" class="form-control">
                        <option value="farmer">Farmers</option>
                        <option value="officer">Officers</option>
                        <option value="admin">Admins</option>
                    </select>
                </div>

                <div id="userTarget" style="display:none" class="form-group">
                    <label class="form-label">User</label>
                    <select name="target_user_id" class="form-control">
                        <option value="">-- Select User --</option>
                        <?php foreach ($userList as $u): ?>
                        <option value="<?php echo $u['user_id']; ?>">
                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <span class="material-icons">send</span> Send Notification
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="material-icons">history</span> Recent Notifications</h3>
        </div>
        <div class="card-body" style="padding:0;max-height:580px;overflow-y:auto">
            <?php if (empty($notifications)): ?>
            <div style="text-align:center;padding:48px;color:var(--text-secondary)">
                <span class="material-icons" style="font-size:48px;display:block;margin-bottom:12px">notifications_none</span>
                No notifications sent yet.
            </div>
            <?php else: ?>
            <?php
            $typeColors = ['info'=>'#3b82f6','success'=>'#10b981','warning'=>'#f59e0b','error'=>'#ef4444'];
            $typeIcons  = ['info'=>'info','success'=>'check_circle','warning'=>'warning','error'=>'error'];
            foreach ($notifications as $n):
                $color = $typeColors[$n['type']] ?? '#6366f1';
                $icon  = $typeIcons[$n['type']] ?? 'notifications';
                $recipient = $n['user_id'] ? htmlspecialchars($n['first_name'].' '.$n['last_name']) : '<em>All Users</em>';
            ?>
            <div style="padding:14px 16px;border-bottom:1px solid var(--border);<?php echo $n['is_read'] ? '' : 'background:rgba(99,102,241,.04)'; ?>">
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span class="material-icons" style="font-size:20px;color:<?php echo $color; ?>;flex-shrink:0;margin-top:2px"><?php echo $icon; ?></span>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">
                            <strong style="font-size:13px"><?php echo htmlspecialchars($n['title'] ?? '—'); ?></strong>
                            <span style="font-size:11px;color:var(--text-secondary);white-space:nowrap;margin-left:8px">
                                <?php echo date('M d, H:i', strtotime($n['created_at'])); ?>
                            </span>
                        </div>
                        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px"><?php echo htmlspecialchars(mb_substr($n['message'] ?? '', 0, 100)); ?></div>
                        <div style="font-size:11px;display:flex;gap:8px">
                            <span style="color:<?php echo $color; ?>">
                                <?php echo ucfirst($n['type'] ?? 'info'); ?>
                            </span>
                            <span style="color:var(--text-muted)">→ <?php echo $recipient; ?></span>
                            <?php if (!$n['is_read']): ?>
                            <span style="background:rgba(99,102,241,.15);color:#6366f1;padding:1px 8px;border-radius:10px;font-weight:700">Unread</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notif-grid { grid-template-columns: 1fr 1.6fr; }
@media (max-width: 900px) { .notif-grid { grid-template-columns: 1fr; } }
</style>
<script>
function onTargetChange() {
    const v = document.getElementById('targetSelect').value;
    document.getElementById('roleTarget').style.display = v === 'role' ? 'block' : 'none';
    document.getElementById('userTarget').style.display = v === 'user' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../layouts/admin-footer.php'; ?>
