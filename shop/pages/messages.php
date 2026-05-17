<?php
/**
 * Shop Customer Messages Page — Modern Chat UI
 */

require_once __DIR__ . '/../config/config.php';
requireShopLogin();

$db     = new ShopDatabase();
$userId = $_SESSION['shop_user_id'];

$selectedConversationId = intval($_GET['chat'] ?? 0);
$orderId  = intval($_GET['order']  ?? 0);
$farmerId = intval($_GET['seller'] ?? 0);

if (!$selectedConversationId && $orderId && !$farmerId) {
    $oi = $db->single("SELECT seller_id FROM shop_order_items WHERE order_id = ? LIMIT 1", [$orderId]);
    if ($oi) $farmerId = $oi['seller_id'];
}

$conversations = $db->resultSet(
    "SELECT c.*,
        u.first_name as farmer_name,
        (SELECT message FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM shop_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_msg_time,
        so.order_number
     FROM shop_conversations c
     LEFT JOIN users u ON c.farmer_id = u.user_id
     LEFT JOIN shop_orders so ON c.order_id = so.order_id
     WHERE c.customer_id = ? AND c.customer_type = 'general'
     ORDER BY c.last_message_at DESC",
    [$userId]
);

$selectedConvo = null;
$messages      = [];
if ($selectedConversationId) {
    $selectedConvo = $db->single(
        "SELECT c.*, u.first_name as farmer_name, so.order_number, so.order_status
         FROM shop_conversations c
         LEFT JOIN users u ON c.farmer_id = u.user_id
         LEFT JOIN shop_orders so ON c.order_id = so.order_id
         WHERE c.conversation_id = ? AND c.customer_id = ? AND c.customer_type = 'general'",
        [$selectedConversationId, $userId]
    );
    if ($selectedConvo) {
        $messages = $db->resultSet(
            "SELECT * FROM shop_messages WHERE conversation_id = ? ORDER BY created_at ASC",
            [$selectedConversationId]
        );
        // Reset unread counter
        try {
            $db->query("UPDATE shop_conversations SET customer_unread = 0, buyer_unread = 0 WHERE conversation_id = ?");
            $db->bind(1, $selectedConversationId); $db->execute();
        } catch (Exception $e) {
            $db->query("UPDATE shop_conversations SET customer_unread = 0 WHERE conversation_id = ?");
            $db->bind(1, $selectedConversationId); $db->execute();
        }
        $db->query("UPDATE shop_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'farmer'");
        $db->bind(1, $selectedConversationId); $db->execute();
    }
}

$farmerInfo = null;
if ($farmerId && !$selectedConversationId) {
    $farmerInfo = $db->single("SELECT user_id, first_name, last_name FROM users WHERE user_id = ?", [$farmerId]);
    $existingConvo = $db->single(
        "SELECT conversation_id FROM shop_conversations WHERE farmer_id = ? AND customer_id = ? AND customer_type = 'general'",
        [$farmerId, $userId]
    );
    if ($existingConvo) {
        header('Location: ' . shopUrl('messages/' . $existingConvo['conversation_id']));
        exit;
    }
}

$pageTitle = 'Messages';
include __DIR__ . '/../layouts/header.php';
?>

<style>
/* ── Chat Layout ─────────────────────────────────────────────────── */
.chat-page {
    max-width: 1100px; margin: 0 auto; padding: 16px;
    height: calc(100vh - 130px); display: flex; flex-direction: column;
}
.chat-layout {
    flex: 1; display: flex; gap: 0; overflow: hidden;
    border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    background: #fff;
}

/* ── Sidebar ─────────────────────────────────────────────────────── */
.chat-sidebar {
    width: 320px; flex-shrink: 0;
    display: flex; flex-direction: column;
    border-right: 1px solid #e5e7eb;
    background: #fafafa;
}
.chat-sidebar-header {
    padding: 18px 16px 12px;
    border-bottom: 1px solid #e5e7eb;
}
.chat-sidebar-header h2 {
    font-size: 18px; font-weight: 700; color: #111827;
    display: flex; align-items: center; gap: 8px; margin: 0 0 12px;
}
.chat-sidebar-header h2 .material-icons { color: #16a34a; font-size: 22px; }

.chat-search {
    display: flex; align-items: center;
    background: #f3f4f6; border-radius: 20px;
    padding: 6px 12px; gap: 6px;
}
.chat-search .material-icons { color: #9ca3af; font-size: 18px; }
.chat-search input {
    flex: 1; border: none; background: none; outline: none;
    font-size: 13px; color: #374151;
}
.chat-search input::placeholder { color: #9ca3af; }

.convo-list { flex: 1; overflow-y: auto; }
.convo-list::-webkit-scrollbar { width: 4px; }
.convo-list::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 2px; }

.convo-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; cursor: pointer; transition: background 0.15s;
    text-decoration: none; border-bottom: 1px solid #f3f4f6;
    position: relative;
}
.convo-item:hover { background: #f0fdf4; }
.convo-item.active { background: #f0fdf4; border-left: 3px solid #16a34a; }
.convo-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #22c55e);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.convo-avatar .material-icons { color: white; font-size: 20px; }
.convo-body { flex: 1; min-width: 0; }
.convo-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; }
.convo-name { font-size: 14px; font-weight: 600; color: #111827; }
.convo-time { font-size: 11px; color: #9ca3af; }
.convo-preview { font-size: 12px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.convo-badge {
    width: 20px; height: 20px; border-radius: 50%;
    background: #16a34a; color: white; font-size: 11px; font-weight: 600;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.order-tag-sm {
    display: inline-block; background: #f3f4f6; color: #374151;
    padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 500;
    margin-right: 4px;
}

.empty-convos {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; padding: 40px 20px; text-align: center; color: #9ca3af;
}
.empty-convos .material-icons { font-size: 48px; margin-bottom: 12px; opacity: 0.4; }
.empty-convos p { font-size: 14px; margin: 0 0 4px; color: #6b7280; }
.empty-convos small { font-size: 12px; }

/* ── Main chat area ──────────────────────────────────────────────── */
.chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; background: #fff; }

.chat-topbar {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0; min-height: 64px;
}
.back-btn-mobile {
    display: none; color: #374151; background: none; border: none; cursor: pointer;
    padding: 4px; border-radius: 6px;
}
.back-btn-mobile .material-icons { font-size: 22px; }
.chat-topbar-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #22c55e);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.chat-topbar-avatar .material-icons { color: white; font-size: 18px; }
.chat-topbar-info { flex: 1; }
.chat-topbar-name { font-size: 15px; font-weight: 600; color: #111827; }
.chat-topbar-sub { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 6px; }
.online-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block; }
.status-chip {
    display: inline-flex; align-items: center;
    padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500;
}
.status-chip.pending   { background: #fef3c7; color: #92400e; }
.status-chip.confirmed { background: #d1fae5; color: #065f46; }
.status-chip.shipped   { background: #dbeafe; color: #1e40af; }
.status-chip.delivered { background: #d1fae5; color: #065f46; }
.status-chip.cancelled { background: #fee2e2; color: #991b1b; }

/* ── Messages body ───────────────────────────────────────────────── */
.chat-body {
    flex: 1; overflow-y: auto; padding: 16px 20px;
    background: #f9fafb;
    scrollbar-width: thin; scrollbar-color: #d1d5db transparent;
}
.chat-body::-webkit-scrollbar { width: 5px; }
.chat-body::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }

.msg-date-divider {
    text-align: center; margin: 12px 0;
    font-size: 11px; color: #9ca3af; font-weight: 500;
    position: relative;
}
.msg-date-divider::before, .msg-date-divider::after {
    content: ''; position: absolute; top: 50%; width: calc(50% - 50px);
    height: 1px; background: #e5e7eb;
}
.msg-date-divider::before { left: 0; }
.msg-date-divider::after { right: 0; }

.msg-row { display: flex; margin-bottom: 8px; }
.msg-row.sent  { justify-content: flex-end; }
.msg-row.received { justify-content: flex-start; }

.msg-bubble {
    max-width: 68%; padding: 10px 14px;
    border-radius: 18px; font-size: 14px; line-height: 1.5;
    word-break: break-word; position: relative;
}
.msg-row.sent .msg-bubble {
    background: linear-gradient(135deg, #15803d, #22c55e);
    color: white;
    border-bottom-right-radius: 4px;
}
.msg-row.received .msg-bubble {
    background: #fff; color: #111827;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.msg-time {
    font-size: 10px; display: block; margin-top: 4px; opacity: 0.7;
}
.msg-row.sent .msg-time  { text-align: right; }
.msg-row.received .msg-time { text-align: left; color: #9ca3af; }
.msg-image { max-width: 200px; border-radius: 10px; margin-bottom: 6px; display: block; }

.chat-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; text-align: center; color: #9ca3af;
}
.chat-empty .material-icons { font-size: 56px; opacity: 0.25; margin-bottom: 16px; }
.chat-empty h3 { font-size: 16px; color: #6b7280; margin: 0 0 6px; }
.chat-empty p  { font-size: 13px; }

/* ── Input bar ───────────────────────────────────────────────────── */
.chat-input-bar {
    display: flex; align-items: flex-end; gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #e5e7eb;
    background: #fff; flex-shrink: 0;
}
.chat-input-wrap {
    flex: 1; background: #f3f4f6; border-radius: 24px;
    display: flex; align-items: flex-end; padding: 8px 14px; gap: 8px;
    border: 1px solid transparent; transition: border-color 0.2s;
}
.chat-input-wrap:focus-within { border-color: #22c55e; background: #fff; }
.chat-textarea {
    flex: 1; background: none; border: none; outline: none; resize: none;
    font-size: 14px; color: #111827; font-family: inherit;
    max-height: 120px; min-height: 22px; line-height: 1.5;
}
.chat-textarea::placeholder { color: #9ca3af; }
.chat-send-btn {
    width: 40px; height: 40px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, #15803d, #22c55e);
    color: white; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.chat-send-btn:hover { transform: scale(1.08); }
.chat-send-btn:disabled { opacity: 0.4; transform: none; cursor: not-allowed; }
.chat-send-btn .material-icons { font-size: 20px; }

/* ── No selection placeholder ────────────────────────────────────── */
.chat-no-select {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; color: #9ca3af; background: #fff;
}
.chat-no-select .material-icons { font-size: 64px; opacity: 0.2; margin-bottom: 16px; }
.chat-no-select h3 { font-size: 18px; color: #6b7280; margin: 0 0 8px; }
.chat-no-select p  { font-size: 13px; max-width: 280px; }

/* ── Mobile ──────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .chat-page { padding: 0; height: calc(100vh - 110px); }
    .chat-layout { border-radius: 0; border: none; }
    .chat-sidebar { width: 100%; position: absolute; inset: 0; z-index: 5; background: #fff;
                    display: none; }
    .chat-sidebar.show { display: flex; }
    .chat-main { position: absolute; inset: 0; z-index: 4; }
    .chat-main.has-chat .chat-sidebar { display: none; }
    .back-btn-mobile { display: flex !important; }
    .chat-layout { position: relative; overflow: hidden; }
}
</style>

<div class="chat-page">
<div class="chat-layout">

    <!-- Sidebar -->
    <aside class="chat-sidebar <?php echo !$selectedConvo && !$farmerInfo ? 'show' : ''; ?>" id="chatSidebar">
        <div class="chat-sidebar-header">
            <h2><span class="material-icons">forum</span> Messages</h2>
            <div class="chat-search">
                <span class="material-icons">search</span>
                <input type="text" placeholder="Search conversations…" oninput="filterConvos(this.value)">
            </div>
        </div>
        <div class="convo-list" id="convoList">
            <?php if (empty($conversations) && !$farmerInfo): ?>
            <div class="empty-convos">
                <span class="material-icons">chat_bubble_outline</span>
                <p>No conversations yet</p>
                <small>Click "Message Seller" on any order or product page</small>
            </div>
            <?php else: ?>
            <?php foreach ($conversations as $c):
                $lastT = $c['last_msg_time'] ? date('g:i A', strtotime($c['last_msg_time'])) : '';
                $today = date('Y-m-d');
                if ($c['last_msg_time'] && date('Y-m-d', strtotime($c['last_msg_time'])) !== $today) {
                    $lastT = date('M j', strtotime($c['last_msg_time']));
                }
            ?>
            <a class="convo-item <?php echo $c['conversation_id'] == $selectedConversationId ? 'active' : ''; ?>"
               href="<?php echo shopUrl('messages/' . $c['conversation_id']); ?>"
               data-name="<?php echo strtolower(htmlspecialchars($c['farmer_name'] ?? '')); ?>">
                <div class="convo-avatar"><span class="material-icons">storefront</span></div>
                <div class="convo-body">
                    <div class="convo-top">
                        <span class="convo-name"><?php echo htmlspecialchars($c['farmer_name'] ?? 'Seller'); ?></span>
                        <span class="convo-time"><?php echo $lastT; ?></span>
                    </div>
                    <div class="convo-preview">
                        <?php if ($c['order_number']): ?><span class="order-tag-sm">#<?php echo htmlspecialchars($c['order_number']); ?></span><?php endif; ?>
                        <?php echo htmlspecialchars(substr($c['last_message'] ?? 'No messages yet', 0, 45)); ?>
                    </div>
                </div>
                <?php if (($c['customer_unread'] ?? 0) > 0): ?>
                <span class="convo-badge"><?php echo (int)$c['customer_unread']; ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Chat -->
    <div class="chat-main <?php echo ($selectedConvo || $farmerInfo) ? 'has-chat' : ''; ?>" id="chatMain">
        <?php if ($selectedConvo): ?>
        <!-- Active conversation -->
        <div class="chat-topbar">
            <button class="back-btn-mobile" onclick="showSidebar()">
                <span class="material-icons">arrow_back</span>
            </button>
            <div class="chat-topbar-avatar"><span class="material-icons">storefront</span></div>
            <div class="chat-topbar-info">
                <div class="chat-topbar-name"><?php echo htmlspecialchars($selectedConvo['farmer_name'] ?? 'Seller'); ?></div>
                <div class="chat-topbar-sub">
                    <span class="online-dot"></span>
                    <?php if ($selectedConvo['order_number']): ?>
                    Order #<?php echo htmlspecialchars($selectedConvo['order_number']); ?> &nbsp;
                    <span class="status-chip <?php echo htmlspecialchars($selectedConvo['order_status'] ?? ''); ?>">
                        <?php echo ucfirst($selectedConvo['order_status'] ?? ''); ?>
                    </span>
                    <?php else: ?>
                    Seller
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="chat-body" id="chatBody">
            <?php
            $prevDate = '';
            foreach ($messages as $msg):
                $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                if ($msgDate !== $prevDate):
                    $prevDate = $msgDate;
                    $label = ($msgDate === date('Y-m-d')) ? 'Today' : date('M j, Y', strtotime($msg['created_at']));
            ?>
            <div class="msg-date-divider"><?php echo $label; ?></div>
            <?php endif; ?>
            <div class="msg-row <?php echo $msg['sender_type'] === 'customer' ? 'sent' : 'received'; ?>">
                <div class="msg-bubble">
                    <?php if (!empty($msg['attachment_url'])): ?>
                    <img src="<?php echo htmlspecialchars($msg['attachment_url']); ?>" alt="Attachment" class="msg-image">
                    <?php endif; ?>
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    <span class="msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?>
            <div class="chat-empty">
                <span class="material-icons">waving_hand</span>
                <h3>Start the conversation!</h3>
                <p>Send your first message to the seller below.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="chat-input-bar">
            <div class="chat-input-wrap">
                <textarea class="chat-textarea" id="msgInput" placeholder="Type a message…" rows="1"></textarea>
            </div>
            <button class="chat-send-btn" id="sendBtn" onclick="sendMsg()" disabled>
                <span class="material-icons">send</span>
            </button>
        </div>

        <?php elseif ($farmerInfo): ?>
        <!-- New conversation -->
        <div class="chat-topbar">
            <button class="back-btn-mobile" onclick="showSidebar()"><span class="material-icons">arrow_back</span></button>
            <div class="chat-topbar-avatar"><span class="material-icons">storefront</span></div>
            <div class="chat-topbar-info">
                <div class="chat-topbar-name"><?php echo htmlspecialchars($farmerInfo['first_name'] . ' ' . ($farmerInfo['last_name'] ?? '')); ?></div>
                <div class="chat-topbar-sub">New conversation</div>
            </div>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="chat-empty">
                <span class="material-icons">chat</span>
                <h3>Say hello!</h3>
                <p>Send your first message to this seller.</p>
            </div>
        </div>
        <div class="chat-input-bar">
            <div class="chat-input-wrap">
                <textarea class="chat-textarea" id="msgInput" placeholder="Type your first message…" rows="1"></textarea>
            </div>
            <button class="chat-send-btn" id="sendBtn" onclick="startConvo()" disabled>
                <span class="material-icons">send</span>
            </button>
        </div>

        <?php else: ?>
        <div class="chat-no-select">
            <span class="material-icons">forum</span>
            <h3>Your Messages</h3>
            <p>Select a conversation on the left, or start one from a product or order page.</p>
        </div>
        <?php endif; ?>
    </div><!-- .chat-main -->

</div><!-- .chat-layout -->
</div><!-- .chat-page -->

<script>
const MSG_AJAX      = '<?php echo shopUrl("ajax/messages.php"); ?>';
const CURRENT_CONVO = <?php echo $selectedConversationId ?: 'null'; ?>;
const FARMER_ID     = <?php echo $farmerId ?: 'null'; ?>;
const ORDER_ID      = <?php echo $orderId  ?: 'null'; ?>;
let pollTimer = null;
let lastMsgId = <?php echo !empty($messages) ? (int)end($messages)['message_id'] : 0; ?>;

// Textarea auto-resize + send button enable
const msgInput = document.getElementById('msgInput');
const sendBtn  = document.getElementById('sendBtn');
if (msgInput) {
    msgInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        if (sendBtn) sendBtn.disabled = this.value.trim().length === 0;
    });
    msgInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (sendBtn && !sendBtn.disabled) {
                CURRENT_CONVO ? sendMsg() : startConvo();
            }
        }
    });
}

async function sendMsg() {
    const text = msgInput.value.trim();
    if (!text || !CURRENT_CONVO) return;
    msgInput.value = ''; msgInput.style.height = 'auto';
    if (sendBtn) sendBtn.disabled = true;

    appendBubble(text, 'sent');
    scrollBottom();

    try {
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('conversation_id', CURRENT_CONVO);
        fd.append('message', text);
        fd.append('sender_type', 'customer');
        await fetch(MSG_AJAX, { method:'POST', body:fd });
    } catch(e) {}
}

async function startConvo() {
    const text = msgInput.value.trim();
    if (!text || !FARMER_ID) return;
    if (sendBtn) sendBtn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('action', 'start_conversation');
        fd.append('farmer_id', FARMER_ID);
        fd.append('order_id', ORDER_ID || 0);
        fd.append('message', text);
        const res  = await fetch(MSG_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) window.location = '<?php echo shopUrl("messages/"); ?>' + data.conversation_id;
        else { if (sendBtn) sendBtn.disabled = false; }
    } catch(e) { if (sendBtn) sendBtn.disabled = false; }
}

function appendBubble(text, type, time) {
    const body = document.getElementById('chatBody');
    if (!body) return;
    const empty = body.querySelector('.chat-empty');
    if (empty) empty.remove();
    const t = time || new Date().toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
    const row = document.createElement('div');
    row.className = 'msg-row ' + type;
    row.innerHTML = '<div class="msg-bubble">' + escHtml(text) + '<span class="msg-time">' + t + '</span></div>';
    body.appendChild(row);
}

function scrollBottom() {
    const b = document.getElementById('chatBody');
    if (b) b.scrollTop = b.scrollHeight;
}

async function pollNew() {
    if (!CURRENT_CONVO) return;
    try {
        const res  = await fetch(MSG_AJAX + '?action=poll&conversation_id=' + CURRENT_CONVO + '&last_id=' + lastMsgId);
        const data = await res.json();
        if (data.success && data.messages?.length) {
            data.messages.forEach(m => {
                if (m.sender_type !== 'customer') {
                    appendBubble(m.message, 'received', new Date(m.created_at).toLocaleTimeString([], {hour:'numeric', minute:'2-digit'}));
                }
                lastMsgId = Math.max(lastMsgId, parseInt(m.message_id) || 0);
            });
            scrollBottom();
        }
    } catch(e) {}
}

function filterConvos(q) {
    document.querySelectorAll('.convo-item').forEach(item => {
        item.style.display = item.dataset.name?.includes(q.toLowerCase()) ? '' : 'none';
    });
}

function showSidebar() {
    document.getElementById('chatSidebar').classList.add('show');
    document.getElementById('chatMain').classList.remove('has-chat');
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

document.addEventListener('DOMContentLoaded', function() {
    scrollBottom();
    if (CURRENT_CONVO) pollTimer = setInterval(pollNew, 3000);
});
window.addEventListener('beforeunload', () => { if (pollTimer) clearInterval(pollTimer); });
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
