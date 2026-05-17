<?php
/**
 * Farmer Messages Page — Customer ↔ Farmer shop conversations
 */

require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn() || getCurrentUser()['role'] !== 'farmer') {
    header('Location: ' . $base_url . '?page=login');
    exit;
}

$db       = new Database();
$farmerId = (int)$_SESSION['user_id'];

$selectedConvoId = intval($_GET['chat'] ?? 0);

// Get all conversations for this farmer
$conversations = $db->resultSet(
    "SELECT c.*,
        COALESCE(
            (SELECT first_name FROM general_users WHERE user_id = c.customer_id AND c.customer_type = 'general'),
            (SELECT first_name FROM users WHERE user_id = c.customer_id AND c.customer_type = 'farmer'),
            'Customer'
        ) as customer_name,
        (SELECT message    FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_msg_time,
        so.order_number
     FROM shop_conversations c
     LEFT JOIN shop_orders so ON c.order_id = so.order_id
     WHERE c.farmer_id = ?
     ORDER BY c.last_message_at DESC",
    [$farmerId]
);

// Get selected conversation + messages
$selectedConvo = null;
$messages      = [];
if ($selectedConvoId) {
    $selectedConvo = $db->single(
        "SELECT c.*,
            COALESCE(
                (SELECT first_name FROM general_users WHERE user_id = c.customer_id AND c.customer_type = 'general'),
                (SELECT first_name FROM users WHERE user_id = c.customer_id AND c.customer_type = 'farmer'),
                'Customer'
            ) as customer_name,
            so.order_number, so.order_status
         FROM shop_conversations c
         LEFT JOIN shop_orders so ON c.order_id = so.order_id
         WHERE c.conversation_id = ? AND c.farmer_id = ?",
        [$selectedConvoId, $farmerId]
    );
    if ($selectedConvo) {
        $messages = $db->resultSet(
            "SELECT * FROM shop_messages WHERE conversation_id = ? ORDER BY created_at ASC",
            [$selectedConvoId]
        );
        // Reset farmer_unread
        try {
            $db->query("UPDATE shop_conversations SET farmer_unread = 0, seller_unread = 0 WHERE conversation_id = ?")
               ->bind(1, $selectedConvoId)->execute();
        } catch (Exception $e) {
            $db->query("UPDATE shop_conversations SET farmer_unread = 0 WHERE conversation_id = ?")
               ->bind(1, $selectedConvoId)->execute();
        }
        $db->query("UPDATE shop_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'customer'")
           ->bind(1, $selectedConvoId)->execute();
    }
}

$pageTitle = 'Messages';
include __DIR__ . '/../layouts/header.php';
?>

<style>
/* ── Page wrapper ───────────────────────────────────────────────────── */
.fm-page {
    padding: 20px 16px;
    height: calc(100vh - 130px);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}
.fm-layout {
    display: flex;
    height: 100%;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
}

/* ── Sidebar ─────────────────────────────────────────────────────────── */
.fm-sidebar {
    width: 320px;
    min-width: 280px;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    background: #fff;
    flex-shrink: 0;
}
.fm-sidebar-hd {
    padding: 16px 18px 12px;
    border-bottom: 1px solid #f0f0f0;
    flex-shrink: 0;
}
.fm-sidebar-hd h2 {
    margin: 0 0 10px;
    font-size: 17px;
    font-weight: 700;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 8px;
}
.fm-sidebar-hd h2 .material-icons { font-size: 20px; color: #557A46; }
.fm-search {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f3f4f6;
    border-radius: 10px;
    padding: 8px 12px;
}
.fm-search .material-icons { font-size: 18px; color: #9ca3af; }
.fm-search input {
    border: none; background: none; outline: none;
    font-size: 13px; color: #374151; width: 100%;
}
.fm-convo-list {
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
.fm-convo-list::-webkit-scrollbar { width: 4px; }
.fm-convo-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }

.fm-convo-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 16px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f9fafb;
    transition: background 0.15s;
    position: relative;
}
.fm-convo-item:hover  { background: #f9fafb; }
.fm-convo-item.active { background: #f0fdf4; }
.fm-convo-item.unread { background: #f0fdf4; }

.fm-convo-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #557A46, #8FBC46);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fm-convo-avatar .material-icons { font-size: 22px; color: #fff; }
.fm-convo-body { flex: 1; min-width: 0; }
.fm-convo-top {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 3px;
}
.fm-convo-name {
    font-size: 14px; font-weight: 600; color: #1f2937;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 160px;
}
.fm-convo-time { font-size: 11px; color: #9ca3af; flex-shrink: 0; }
.fm-convo-preview {
    font-size: 12px; color: #6b7280;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fm-order-tag {
    font-size: 10px; font-weight: 600; color: #557A46;
    background: #dcfce7; border-radius: 4px; padding: 1px 5px;
    margin-right: 4px;
}
.fm-badge {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%);
    background: #ef4444; color: #fff;
    font-size: 10px; font-weight: 700;
    border-radius: 10px; padding: 2px 6px;
    min-width: 18px; text-align: center;
}
.fm-empty-convos {
    display: flex; flex-direction: column; align-items: center;
    padding: 48px 20px; color: #9ca3af; text-align: center;
}
.fm-empty-convos .material-icons { font-size: 48px; opacity: 0.25; margin-bottom: 12px; }
.fm-empty-convos p { font-size: 14px; }

/* ── Main chat area ──────────────────────────────────────────────────── */
.fm-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #fff;
}
.fm-topbar {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
    background: #fff;
}
.fm-back-btn {
    display: none;
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: #f3f4f6; cursor: pointer;
    align-items: center; justify-content: center;
}
.fm-topbar-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #557A46, #8FBC46);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fm-topbar-avatar .material-icons { font-size: 20px; color: #fff; }
.fm-topbar-info { flex: 1; }
.fm-topbar-name { font-size: 15px; font-weight: 700; color: #1f2937; }
.fm-topbar-sub  { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 6px; }
.fm-online-dot  { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; }
.fm-status-chip {
    font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 8px;
    text-transform: capitalize;
}
.fm-status-chip.pending   { background: #fef3c7; color: #92400e; }
.fm-status-chip.confirmed { background: #dbeafe; color: #1e40af; }
.fm-status-chip.delivered { background: #dcfce7; color: #166534; }
.fm-status-chip.cancelled { background: #fee2e2; color: #991b1b; }

/* ── Messages body ───────────────────────────────────────────────────── */
.fm-body {
    flex: 1; overflow-y: auto; padding: 16px 20px;
    background: #f9fafb;
    scrollbar-width: thin; scrollbar-color: #d1d5db transparent;
}
.fm-body::-webkit-scrollbar { width: 5px; }
.fm-body::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }

.fm-date-div {
    text-align: center; margin: 12px 0;
    font-size: 11px; color: #9ca3af; font-weight: 500;
    position: relative;
}
.fm-date-div::before, .fm-date-div::after {
    content: ''; position: absolute; top: 50%;
    width: calc(50% - 54px); height: 1px; background: #e5e7eb;
}
.fm-date-div::before { left: 0; }
.fm-date-div::after  { right: 0; }

.fm-msg-row         { display: flex; margin-bottom: 8px; }
.fm-msg-row.sent    { justify-content: flex-end; }
.fm-msg-row.received{ justify-content: flex-start; }

.fm-bubble {
    max-width: 68%; padding: 10px 14px;
    border-radius: 18px; font-size: 14px; line-height: 1.5;
    word-break: break-word;
}
.fm-msg-row.sent .fm-bubble {
    background: linear-gradient(135deg, #3d6b30, #557A46);
    color: white; border-bottom-right-radius: 4px;
}
.fm-msg-row.received .fm-bubble {
    background: #fff; color: #111827;
    border: 1px solid #e5e7eb; border-bottom-left-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.fm-msg-time {
    font-size: 10px; display: block; margin-top: 4px; opacity: 0.7;
}
.fm-msg-row.sent .fm-msg-time     { text-align: right; }
.fm-msg-row.received .fm-msg-time { text-align: left; color: #9ca3af; }
.fm-msg-img { max-width: 200px; border-radius: 10px; margin-bottom: 6px; display: block; }

.fm-chat-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; text-align: center; color: #9ca3af;
}
.fm-chat-empty .material-icons { font-size: 56px; opacity: 0.25; margin-bottom: 16px; }
.fm-chat-empty h3 { font-size: 16px; color: #6b7280; margin: 0 0 6px; }
.fm-chat-empty p  { font-size: 13px; }

/* ── Input bar ───────────────────────────────────────────────────────── */
.fm-input-bar {
    display: flex; align-items: flex-end; gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #e5e7eb;
    background: #fff; flex-shrink: 0;
}
.fm-input-wrap {
    flex: 1; background: #f3f4f6; border-radius: 24px;
    display: flex; align-items: flex-end; padding: 8px 14px;
    border: 1px solid transparent; transition: border-color 0.2s;
}
.fm-input-wrap:focus-within { border-color: #557A46; background: #fff; }
.fm-textarea {
    flex: 1; background: none; border: none; outline: none; resize: none;
    font-size: 14px; color: #111827; font-family: inherit;
    max-height: 120px; min-height: 22px; line-height: 1.5;
}
.fm-textarea::placeholder { color: #9ca3af; }
.fm-send-btn {
    width: 40px; height: 40px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, #3d6b30, #557A46);
    color: white; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.fm-send-btn:hover    { transform: scale(1.08); }
.fm-send-btn:disabled { opacity: 0.4; transform: none; cursor: not-allowed; }
.fm-send-btn .material-icons { font-size: 20px; }

/* ── No conversation selected ────────────────────────────────────────── */
.fm-no-select {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    text-align: center; color: #9ca3af; background: #fff;
}
.fm-no-select .material-icons { font-size: 64px; opacity: 0.2; margin-bottom: 16px; }
.fm-no-select h3 { font-size: 18px; color: #6b7280; margin: 0 0 8px; }
.fm-no-select p  { font-size: 13px; max-width: 280px; }

/* ── Mobile ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .fm-page   { padding: 0; height: calc(100vh - 110px); }
    .fm-layout { border-radius: 0; border: none; position: relative; overflow: hidden; }
    .fm-sidebar {
        width: 100%; position: absolute; inset: 0; z-index: 5;
        background: #fff; display: none;
    }
    .fm-sidebar.show { display: flex; }
    .fm-main   { position: absolute; inset: 0; z-index: 4; }
    .fm-back-btn { display: flex !important; }
}
</style>

<div class="fm-page">
<div class="fm-layout">

    <!-- Sidebar -->
    <aside class="fm-sidebar <?php echo !$selectedConvo ? 'show' : ''; ?>" id="fmSidebar">
        <div class="fm-sidebar-hd">
            <h2><span class="material-icons">forum</span> Customer Messages</h2>
            <div class="fm-search">
                <span class="material-icons">search</span>
                <input type="text" placeholder="Search conversations…" oninput="fmFilter(this.value)">
            </div>
        </div>
        <div class="fm-convo-list" id="fmConvoList">
            <?php if (empty($conversations)): ?>
            <div class="fm-empty-convos">
                <span class="material-icons">chat_bubble_outline</span>
                <p>No customer messages yet</p>
            </div>
            <?php else: ?>
            <?php foreach ($conversations as $c):
                $lastT = '';
                if ($c['last_msg_time']) {
                    $today = date('Y-m-d');
                    $msgDay = date('Y-m-d', strtotime($c['last_msg_time']));
                    $lastT  = ($msgDay === $today) ? date('g:i A', strtotime($c['last_msg_time'])) : date('M j', strtotime($c['last_msg_time']));
                }
                $unread = (int)($c['farmer_unread'] ?? 0);
            ?>
            <a class="fm-convo-item <?php echo $c['conversation_id'] == $selectedConvoId ? 'active' : ($unread > 0 ? 'unread' : ''); ?>"
               href="<?php echo $base_url; ?>?page=farmer-messages&chat=<?php echo $c['conversation_id']; ?>"
               data-name="<?php echo strtolower(htmlspecialchars($c['customer_name'] ?? '')); ?>">
                <div class="fm-convo-avatar"><span class="material-icons">person</span></div>
                <div class="fm-convo-body">
                    <div class="fm-convo-top">
                        <span class="fm-convo-name"><?php echo htmlspecialchars($c['customer_name'] ?? 'Customer'); ?></span>
                        <span class="fm-convo-time"><?php echo $lastT; ?></span>
                    </div>
                    <div class="fm-convo-preview">
                        <?php if ($c['order_number']): ?>
                        <span class="fm-order-tag">#<?php echo htmlspecialchars($c['order_number']); ?></span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars(substr($c['last_message'] ?? 'No messages yet', 0, 45)); ?>
                    </div>
                </div>
                <?php if ($unread > 0 && $c['conversation_id'] != $selectedConvoId): ?>
                <span class="fm-badge"><?php echo $unread > 99 ? '99+' : $unread; ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Chat -->
    <div class="fm-main" id="fmMain">
        <?php if ($selectedConvo): ?>
        <div class="fm-topbar">
            <button class="fm-back-btn" onclick="fmShowSidebar()">
                <span class="material-icons">arrow_back</span>
            </button>
            <div class="fm-topbar-avatar"><span class="material-icons">person</span></div>
            <div class="fm-topbar-info">
                <div class="fm-topbar-name"><?php echo htmlspecialchars($selectedConvo['customer_name'] ?? 'Customer'); ?></div>
                <div class="fm-topbar-sub">
                    <span class="fm-online-dot"></span>
                    <?php if ($selectedConvo['order_number']): ?>
                    Order #<?php echo htmlspecialchars($selectedConvo['order_number']); ?>&nbsp;
                    <span class="fm-status-chip <?php echo htmlspecialchars($selectedConvo['order_status'] ?? ''); ?>">
                        <?php echo ucfirst($selectedConvo['order_status'] ?? ''); ?>
                    </span>
                    <?php else: ?>
                    Customer
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="fm-body" id="fmBody">
            <?php
            $prevDate = '';
            foreach ($messages as $msg):
                $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                if ($msgDate !== $prevDate):
                    $prevDate = $msgDate;
                    $label = ($msgDate === date('Y-m-d')) ? 'Today' : date('M j, Y', strtotime($msg['created_at']));
            ?>
            <div class="fm-date-div"><?php echo $label; ?></div>
            <?php endif; ?>
            <div class="fm-msg-row <?php echo $msg['sender_type'] === 'farmer' ? 'sent' : 'received'; ?>">
                <div class="fm-bubble">
                    <?php if (!empty($msg['attachment_url'])): ?>
                    <img src="<?php echo htmlspecialchars($msg['attachment_url']); ?>" alt="Attachment" class="fm-msg-img">
                    <?php endif; ?>
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    <span class="fm-msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?>
            <div class="fm-chat-empty">
                <span class="material-icons">waving_hand</span>
                <h3>Start the conversation!</h3>
                <p>Reply to this customer below.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="fm-input-bar">
            <div class="fm-input-wrap">
                <textarea class="fm-textarea" id="fmInput" placeholder="Type a message…" rows="1"></textarea>
            </div>
            <button class="fm-send-btn" id="fmSendBtn" onclick="fmSend()" disabled>
                <span class="material-icons">send</span>
            </button>
        </div>

        <?php else: ?>
        <div class="fm-no-select">
            <span class="material-icons">forum</span>
            <h3>Customer Messages</h3>
            <p>Select a conversation to read and reply to customer messages.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<script>
const FM_AJAX   = '<?php echo $base_url; ?>ajax/messages.php';
const FM_CONVO  = <?php echo $selectedConvoId ?: 'null'; ?>;
let fmPollTimer = null;
let fmLastId    = <?php echo !empty($messages) ? (int)end($messages)['message_id'] : 0; ?>;

const fmInput   = document.getElementById('fmInput');
const fmSendBtn = document.getElementById('fmSendBtn');

if (fmInput) {
    fmInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        if (fmSendBtn) fmSendBtn.disabled = this.value.trim().length === 0;
    });
    fmInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (fmSendBtn && !fmSendBtn.disabled) fmSend();
        }
    });
}

async function fmSend() {
    const text = fmInput.value.trim();
    if (!text || !FM_CONVO) return;
    fmInput.value = ''; fmInput.style.height = 'auto';
    if (fmSendBtn) fmSendBtn.disabled = true;

    fmAppend(text, 'sent');
    fmScroll();

    try {
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('conversation_id', FM_CONVO);
        fd.append('message', text);
        fd.append('sender_type', 'farmer');
        const res  = await fetch(FM_AJAX, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.message_id) {
            fmLastId = Math.max(fmLastId, data.message_id);
        }
    } catch(e) {}
}

function fmAppend(text, type, time, msgId) {
    const body = document.getElementById('fmBody');
    if (!body) return;
    const empty = body.querySelector('.fm-chat-empty');
    if (empty) empty.remove();
    const t = time || new Date().toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
    const row = document.createElement('div');
    row.className = 'fm-msg-row ' + type;
    row.innerHTML = '<div class="fm-bubble">' + fmEsc(text) + '<span class="fm-msg-time">' + t + '</span></div>';
    body.appendChild(row);
    if (msgId) fmLastId = Math.max(fmLastId, parseInt(msgId) || 0);
}

function fmScroll() {
    const b = document.getElementById('fmBody');
    if (b) b.scrollTop = b.scrollHeight;
}

async function fmPoll() {
    if (!FM_CONVO) return;
    try {
        const res  = await fetch(FM_AJAX + '?action=poll&conversation_id=' + FM_CONVO + '&last_id=' + fmLastId);
        const data = await res.json();
        if (data.success && data.messages?.length) {
            data.messages.forEach(m => {
                if (m.sender_type !== 'farmer') {
                    fmAppend(m.message, 'received',
                        new Date(m.created_at).toLocaleTimeString([], {hour:'numeric', minute:'2-digit'}),
                        m.message_id
                    );
                }
                fmLastId = Math.max(fmLastId, parseInt(m.message_id) || 0);
            });
            fmScroll();
        }
    } catch(e) {}
}

function fmFilter(q) {
    document.querySelectorAll('.fm-convo-item').forEach(item => {
        item.style.display = item.dataset.name?.includes(q.toLowerCase()) ? '' : 'none';
    });
}

function fmShowSidebar() {
    document.getElementById('fmSidebar').classList.add('show');
}

function fmEsc(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\n/g,'<br>');
}

document.addEventListener('DOMContentLoaded', function() {
    fmScroll();
    if (FM_CONVO) fmPollTimer = setInterval(fmPoll, 3000);
});
window.addEventListener('beforeunload', () => { if (fmPollTimer) clearInterval(fmPollTimer); });
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
