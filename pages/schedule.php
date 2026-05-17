<?php
/**
 * Officer Schedule Management Page
 * Manage field visits to farmers
 */

include __DIR__ . '/../layouts/header.php';

// Authentication and role check
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    redirect('dashboard');
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get schedule statistics
$scheduledCount = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND status = 'scheduled'", [$userId])['count'] ?? 0;
$completedCount = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND status = 'completed'", [$userId])['count'] ?? 0;
$cancelledCount = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND status = 'cancelled'", [$userId])['count'] ?? 0;
$followUpCount = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND follow_up_required = 1 AND status = 'completed'", [$userId])['count'] ?? 0;
$todayVisits = $db->single("SELECT COUNT(*) as count FROM field_visits WHERE officer_id = ? AND visit_date = CURDATE() AND status = 'scheduled'", [$userId])['count'] ?? 0;

// Get all visits for calendar
$allVisits = $db->resultSet("
    SELECT fv.*, u.first_name, u.last_name, u.phone, fp.region 
    FROM field_visits fv 
    JOIN users u ON fv.farmer_id = u.user_id 
    LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id 
    WHERE fv.officer_id = ? 
    ORDER BY fv.visit_date DESC, fv.visit_time ASC
", [$userId]);

// Get farmers for dropdown
$farmers = $db->resultSet("
    SELECT u.user_id, u.first_name, u.last_name, u.phone, fp.region 
    FROM users u 
    LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id 
    WHERE u.role = 'farmer' 
    ORDER BY u.first_name ASC
", []);

// Current month for calendar
$currentMonth = date('Y-m');
?>

<!-- Modern Hero Section -->
<section class="page-hero">
    <div class="hero-particles"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">event_note</span>
            <span><?php echo __('officer'); ?></span>
        </div>
        <h1>
            <span class="material-icons">calendar_month</span>
            <?php echo __('schedule_management') ?: 'Schedule Management'; ?>
        </h1>
        <p class="hero-subtitle"><?php echo __('manage_field_visits') ?: 'Plan and manage your field visits to farmers'; ?></p>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">event_available</span>
            <span><?php echo $scheduledCount; ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">check_circle</span>
            <span><?php echo $completedCount; ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">today</span>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<div class="stats-grid-modern mb-4">
    <div class="stat-card-modern stat-gradient-blue">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">event</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $scheduledCount; ?></div>
                <div class="stat-label-modern"><?php echo __('scheduled_visits') ?: 'Scheduled Visits'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-green">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">task_alt</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $completedCount; ?></div>
                <div class="stat-label-modern"><?php echo __('completed_visits') ?: 'Completed Visits'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-orange">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">follow_the_signs</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $followUpCount; ?></div>
                <div class="stat-label-modern"><?php echo __('follow_up_required') ?: 'Follow-up Required'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card-modern stat-gradient-purple">
        <div class="stat-card-bg"></div>
        <div class="stat-card-content">
            <div class="stat-icon-wrap">
                <span class="material-icons">today</span>
            </div>
            <div class="stat-info">
                <div class="stat-number-modern"><?php echo $todayVisits; ?></div>
                <div class="stat-label-modern"><?php echo __('todays_visits') ?: "Today's Visits"; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header-modern">
        <h3><span class="material-icons">bolt</span> <?php echo __('quick_actions'); ?></h3>
    </div>
    <div class="card-body-modern">
        <div class="quick-actions-grid">
            <button class="btn-modern btn-modern-primary" onclick="openScheduleModal()">
                <span class="material-icons">add_circle</span>
                <?php echo __('schedule_new_visit') ?: 'Schedule New Visit'; ?>
            </button>
            <button class="btn-modern btn-modern-secondary" onclick="filterVisits('today')">
                <span class="material-icons">today</span>
                <?php echo __('todays_visits') ?: "Today's Visits"; ?>
                <?php if ($todayVisits > 0): ?>
                <span class="badge-modern badge-primary"><?php echo $todayVisits; ?></span>
                <?php endif; ?>
            </button>
            <button class="btn-modern btn-modern-secondary" onclick="filterVisits('scheduled')">
                <span class="material-icons">pending_actions</span>
                <?php echo __('pending_visits') ?: 'Pending Visits'; ?>
            </button>
            <button class="btn-modern btn-modern-secondary" onclick="filterVisits('follow_up')">
                <span class="material-icons">replay</span>
                <?php echo __('follow_ups') ?: 'Follow-ups'; ?>
            </button>
        </div>
    </div>
</div>

<!-- Calendar and Visits Grid -->
<div class="schedule-grid">
    <!-- Calendar View -->
    <div class="card">
        <div class="card-header-modern">
            <h3><span class="material-icons">calendar_month</span> <?php echo __('calendar_view') ?: 'Calendar View'; ?></h3>
            <div class="calendar-nav">
                <button class="btn-icon-modern" onclick="changeMonth(-1)"><span class="material-icons">chevron_left</span></button>
                <span id="currentMonthLabel"><?php echo date('F Y'); ?></span>
                <button class="btn-icon-modern" onclick="changeMonth(1)"><span class="material-icons">chevron_right</span></button>
            </div>
        </div>
        <div class="card-body-modern">
            <div id="calendarContainer" class="calendar-container">
                <!-- Calendar will be rendered here -->
            </div>
        </div>
    </div>
    
    <!-- Visits List -->
    <div class="card">
        <div class="card-header-modern">
            <h3><span class="material-icons">list_alt</span> <?php echo __('visits_list') ?: 'Visits List'; ?></h3>
            <div class="list-filters">
                <select id="statusFilter" class="form-control-modern" onchange="filterByStatus()" style="width: auto;">
                    <option value="all"><?php echo __('all_status'); ?></option>
                    <option value="scheduled"><?php echo __('scheduled'); ?></option>
                    <option value="completed"><?php echo __('completed'); ?></option>
                    <option value="cancelled"><?php echo __('cancelled'); ?></option>
                </select>
            </div>
        </div>
        <div class="card-body-modern" style="max-height: 500px; overflow-y: auto;">
            <div id="visitsList" class="visits-list">
                <?php if (empty($allVisits)): ?>
                <div class="empty-state-modern">
                    <span class="material-icons">event_busy</span>
                    <h3><?php echo __('no_visits_scheduled') ?: 'No visits scheduled'; ?></h3>
                    <p><?php echo __('schedule_first_visit') ?: 'Schedule your first field visit to get started'; ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($allVisits as $visit): ?>
                <div class="visit-item" data-status="<?php echo $visit['status']; ?>" data-date="<?php echo $visit['visit_date']; ?>">
                    <div class="visit-date-badge">
                        <span class="day"><?php echo date('d', strtotime($visit['visit_date'])); ?></span>
                        <span class="month"><?php echo date('M', strtotime($visit['visit_date'])); ?></span>
                    </div>
                    <div class="visit-details">
                        <h4><?php echo htmlspecialchars($visit['first_name'] . ' ' . ($visit['last_name'] ?? '')); ?></h4>
                        <p class="visit-meta">
                            <span><span class="material-icons">schedule</span> <?php echo $visit['visit_time'] ? date('h:i A', strtotime($visit['visit_time'])) : 'TBD'; ?></span>
                            <span><span class="material-icons">place</span> <?php echo htmlspecialchars($visit['region'] ?? 'N/A'); ?></span>
                        </p>
                        <?php if ($visit['purpose']): ?>
                        <p class="visit-purpose"><?php echo htmlspecialchars(substr($visit['purpose'], 0, 80)); ?><?php echo strlen($visit['purpose']) > 80 ? '...' : ''; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="visit-status">
                        <span class="badge-modern badge-<?php echo $visit['status'] === 'completed' ? 'success' : ($visit['status'] === 'cancelled' ? 'danger' : 'info'); ?>">
                            <?php echo ucfirst($visit['status']); ?>
                        </span>
                        <?php if ($visit['follow_up_required']): ?>
                        <span class="badge-modern badge-warning">Follow-up</span>
                        <?php endif; ?>
                    </div>
                    <div class="visit-actions">
                        <?php if ($visit['status'] === 'scheduled'): ?>
                        <button class="btn-icon-modern" onclick="completeVisit(<?php echo $visit['visit_id']; ?>)" title="Complete">
                            <span class="material-icons">check</span>
                        </button>
                        <button class="btn-icon-modern" onclick="editVisit(<?php echo $visit['visit_id']; ?>)" title="Edit">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="btn-icon-modern" onclick="cancelVisit(<?php echo $visit['visit_id']; ?>)" title="Cancel" style="color: #dc3545;">
                            <span class="material-icons">close</span>
                        </button>
                        <?php else: ?>
                        <button class="btn-icon-modern" onclick="viewVisit(<?php echo $visit['visit_id']; ?>)" title="View Details">
                            <span class="material-icons">visibility</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Visit Modal -->
<div id="scheduleModal" class="modal-modern">
    <div class="modal-content-modern">
        <div class="modal-header-modern">
            <h3><span class="material-icons">event</span> <?php echo __('schedule_new_visit') ?: 'Schedule New Visit'; ?></h3>
            <button class="modal-close-modern" onclick="closeScheduleModal()">&times;</button>
        </div>
        <form id="scheduleForm" class="modal-body-modern">
            <div class="form-group-modern">
                <label for="farmerId"><?php echo __('select_farmer'); ?> *</label>
                <select id="farmerId" name="farmerId" class="form-control-modern" required>
                    <option value=""><?php echo __('select_farmer'); ?></option>
                    <?php foreach ($farmers as $farmer): ?>
                    <option value="<?php echo $farmer['user_id']; ?>">
                        <?php echo htmlspecialchars($farmer['first_name'] . ' ' . ($farmer['last_name'] ?? '')); ?>
                        - <?php echo htmlspecialchars($farmer['region'] ?? 'N/A'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-modern">
                    <label for="visitDate"><?php echo __('visit_date'); ?> *</label>
                    <input type="date" id="visitDate" name="visitDate" class="form-control-modern" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group-modern">
                    <label for="visitTime"><?php echo __('visit_time'); ?></label>
                    <input type="time" id="visitTime" name="visitTime" class="form-control-modern">
                </div>
            </div>
            <div class="form-group-modern">
                <label for="purpose"><?php echo __('purpose'); ?></label>
                <textarea id="purpose" name="purpose" class="form-control-modern" rows="3" placeholder="<?php echo __('visit_purpose_placeholder') ?: 'Describe the purpose of this visit...'; ?>"></textarea>
            </div>
        </form>
        <div class="modal-footer-modern">
            <button class="btn-modern btn-modern-secondary" onclick="closeScheduleModal()"><?php echo __('cancel'); ?></button>
            <button class="btn-modern btn-modern-primary" onclick="submitSchedule()">
                <span class="material-icons">check</span> <?php echo __('schedule_visit'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Complete Visit Modal -->
<div id="completeModal" class="modal-modern">
    <div class="modal-content-modern">
        <div class="modal-header-modern">
            <h3><span class="material-icons">task_alt</span> <?php echo __('complete_visit') ?: 'Complete Visit'; ?></h3>
            <button class="modal-close-modern" onclick="closeCompleteModal()">&times;</button>
        </div>
        <form id="completeForm" class="modal-body-modern">
            <input type="hidden" id="completeVisitId" name="visitId">
            <div class="form-group-modern">
                <label for="observations"><?php echo __('observations'); ?> *</label>
                <textarea id="observations" name="observations" class="form-control-modern" rows="4" required placeholder="<?php echo __('observations_placeholder') ?: 'Record your observations from the visit...'; ?>"></textarea>
            </div>
            <div class="form-group-modern">
                <label for="recommendations"><?php echo __('recommendations'); ?></label>
                <textarea id="recommendations" name="recommendations" class="form-control-modern" rows="3" placeholder="<?php echo __('recommendations_placeholder') ?: 'Recommendations for the farmer...'; ?>"></textarea>
            </div>
            <div class="form-group-modern" style="display: flex; align-items: center; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" id="followUpRequired" name="followUpRequired" style="width: 20px; height: 20px;">
                    <span><?php echo __('follow_up_required') ?: 'Follow-up Required'; ?></span>
                </label>
            </div>
            <div class="form-group-modern" id="followUpDateGroup" style="display: none;">
                <label for="followUpDate"><?php echo __('follow_up_date'); ?></label>
                <input type="date" id="followUpDate" name="followUpDate" class="form-control-modern" min="<?php echo date('Y-m-d'); ?>">
            </div>
        </form>
        <div class="modal-footer-modern">
            <button class="btn-modern btn-modern-secondary" onclick="closeCompleteModal()"><?php echo __('cancel'); ?></button>
            <button class="btn-modern btn-modern-primary" onclick="submitComplete()">
                <span class="material-icons">check_circle</span> <?php echo __('mark_complete'); ?>
            </button>
        </div>
    </div>
</div>

<!-- View Visit Modal -->
<div id="viewModal" class="modal-modern">
    <div class="modal-content-modern" style="max-width: 500px;">
        <div class="modal-header-modern">
            <h3><span class="material-icons">info</span> <?php echo __('visit_details') ?: 'Visit Details'; ?></h3>
            <button class="modal-close-modern" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="viewModalContent" class="modal-body-modern">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-footer-modern">
            <button class="btn-modern btn-modern-secondary" onclick="closeViewModal()"><?php echo __('close'); ?></button>
        </div>
    </div>
</div>

<style>
.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .schedule-grid {
        grid-template-columns: 1fr;
    }
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.calendar-nav span {
    font-weight: 600;
    min-width: 140px;
    text-align: center;
}

.calendar-container {
    width: 100%;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-bottom: 0.5rem;
}

.calendar-header span {
    text-align: center;
    font-weight: 600;
    font-size: 0.85rem;
    color: #666;
    padding: 0.5rem 0;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    font-size: 0.9rem;
}

.calendar-day:hover {
    background: #e5e7eb;
}

.calendar-day.other-month {
    color: #ccc;
    background: transparent;
}

.calendar-day.today {
    background: var(--gradient-primary);
    color: white;
    font-weight: 600;
}

.calendar-day.has-visit::after {
    content: '';
    position: absolute;
    bottom: 4px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #28a745;
}

.calendar-day.has-visit.today::after {
    background: white;
}

.visit-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border-left: 4px solid var(--primary, #557A46);
    transition: all 0.2s;
}

.visit-item:hover {
    background: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.visit-date-badge {
    min-width: 50px;
    text-align: center;
    padding: 0.5rem;
    background: var(--primary, #557A46);
    color: white;
    border-radius: 8px;
}

.visit-date-badge .day {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
}

.visit-date-badge .month {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.visit-details {
    flex: 1;
}

.visit-details h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
}

.visit-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #666;
    margin: 0;
}

.visit-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.visit-meta .material-icons {
    font-size: 14px;
}

.visit-purpose {
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
    color: #888;
    font-style: italic;
}

.visit-status {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.visit-actions {
    display: flex;
    gap: 0.25rem;
}

.mb-4 {
    margin-bottom: 1.5rem;
}

.list-filters select {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}
</style>

<script>
var baseUrl = '<?php echo $base_url; ?>';
var currentDate = new Date();
var visitsData = <?php echo json_encode($allVisits); ?>;

// Render calendar
function renderCalendar() {
    const container = document.getElementById('calendarContainer');
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    document.getElementById('currentMonthLabel').textContent = 
        new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    
    let html = '<div class="calendar-header">';
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        html += `<span>${day}</span>`;
    });
    html += '</div><div class="calendar-days">';
    
    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        html += '<div class="calendar-day other-month"></div>';
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
        const hasVisit = visitsData.some(v => v.visit_date === dateStr && v.status === 'scheduled');
        
        let classes = 'calendar-day';
        if (isToday) classes += ' today';
        if (hasVisit) classes += ' has-visit';
        
        html += `<div class="${classes}" onclick="showDayVisits('${dateStr}')">${day}</div>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

function showDayVisits(date) {
    const visits = visitsData.filter(v => v.visit_date === date);
    if (visits.length > 0) {
        filterVisits('date', date);
    }
}

// Modal functions
function openScheduleModal() {
    document.getElementById('scheduleModal').classList.add('active');
    document.getElementById('visitDate').value = new Date().toISOString().split('T')[0];
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
    document.getElementById('scheduleForm').reset();
}

function submitSchedule() {
    const form = document.getElementById('scheduleForm');
    if (!form.reportValidity()) return;
    
    const formData = new FormData(form);
    formData.append('action', 'schedule_visit');
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Visit scheduled successfully', 'success');
            closeScheduleModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to schedule visit', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function completeVisit(visitId) {
    document.getElementById('completeVisitId').value = visitId;
    document.getElementById('completeModal').classList.add('active');
}

function closeCompleteModal() {
    document.getElementById('completeModal').classList.remove('active');
    document.getElementById('completeForm').reset();
    document.getElementById('followUpDateGroup').style.display = 'none';
}

document.getElementById('followUpRequired').addEventListener('change', function() {
    document.getElementById('followUpDateGroup').style.display = this.checked ? 'block' : 'none';
});

function submitComplete() {
    const form = document.getElementById('completeForm');
    if (!form.reportValidity()) return;
    
    const formData = new FormData(form);
    formData.append('action', 'complete_visit');
    formData.append('visitId', document.getElementById('completeVisitId').value);
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Visit completed', 'success');
            closeCompleteModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to complete visit', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function cancelVisit(visitId) {
    if (!confirm('<?php echo __('confirm_cancel_visit') ?: 'Are you sure you want to cancel this visit?'; ?>')) return;
    
    const formData = new FormData();
    formData.append('action', 'cancel_visit');
    formData.append('visitId', visitId);
    
    fetch(baseUrl + 'ajax/officer.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Visit cancelled', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to cancel', 'error');
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function editVisit(visitId) {
    // For simplicity, reuse schedule modal
    const visit = visitsData.find(v => v.visit_id == visitId);
    if (visit) {
        document.getElementById('farmerId').value = visit.farmer_id;
        document.getElementById('visitDate').value = visit.visit_date;
        document.getElementById('visitTime').value = visit.visit_time || '';
        document.getElementById('purpose').value = visit.purpose || '';
        openScheduleModal();
    }
}

function viewVisit(visitId) {
    const visit = visitsData.find(v => v.visit_id == visitId);
    if (!visit) return;
    
    document.getElementById('viewModalContent').innerHTML = `
        <div style="padding: 0.5rem 0;">
            <p><strong>Farmer:</strong> ${visit.first_name} ${visit.last_name || ''}</p>
            <p><strong>Date:</strong> ${new Date(visit.visit_date).toLocaleDateString()}</p>
            <p><strong>Time:</strong> ${visit.visit_time || 'N/A'}</p>
            <p><strong>Status:</strong> <span class="badge-modern badge-${visit.status === 'completed' ? 'success' : 'danger'}">${visit.status}</span></p>
            ${visit.purpose ? `<p><strong>Purpose:</strong> ${visit.purpose}</p>` : ''}
            ${visit.observations ? `<p><strong>Observations:</strong> ${visit.observations}</p>` : ''}
            ${visit.recommendations ? `<p><strong>Recommendations:</strong> ${visit.recommendations}</p>` : ''}
            ${visit.follow_up_required ? `<p><strong>Follow-up Date:</strong> ${visit.follow_up_date || 'TBD'}</p>` : ''}
        </div>
    `;
    document.getElementById('viewModal').classList.add('active');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

function filterVisits(type, value) {
    const items = document.querySelectorAll('.visit-item');
    const today = new Date().toISOString().split('T')[0];
    
    items.forEach(item => {
        const status = item.dataset.status;
        const date = item.dataset.date;
        let show = true;
        
        if (type === 'today') {
            show = date === today && status === 'scheduled';
        } else if (type === 'scheduled') {
            show = status === 'scheduled';
        } else if (type === 'follow_up') {
            show = item.querySelector('.badge-warning') !== null;
        } else if (type === 'date') {
            show = date === value;
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
}

function filterByStatus() {
    const status = document.getElementById('statusFilter').value;
    const items = document.querySelectorAll('.visit-item');
    
    items.forEach(item => {
        if (status === 'all' || item.dataset.status === status) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});

// Close modals on outside click
document.querySelectorAll('.modal-modern').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// showNotification is now provided globally via footer.php
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
