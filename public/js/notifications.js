/**
 * Notifications JavaScript
 * Handles notification dropdown and real-time updates
 */

class NotificationManager {
    constructor() {
        this.dropdown = null;
        this.badge = null;
        this.list = null;
        this.isOpen = false;
        this.pollInterval = null;
        this.notifications = [];

        this.init();
    }

    init() {
        this.dropdown = document.querySelector('.notification-dropdown');
        this.badge = document.querySelector('.notification-badge');
        this.list = document.querySelector('.notification-list');

        if (!this.dropdown) return;

        // Toggle dropdown
        document.querySelector('.notification-toggle')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-bell')) {
                this.close();
            }
        });

        // Clear all button
        document.querySelector('.clear-all-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.clearAll();
        });

        // Load initial notifications
        this.load();

        // Start polling for updates
        this.startPolling();
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.dropdown.classList.add('show');
        document.querySelector('.notification-bell')?.classList.add('active');
        this.load();
    }

    close() {
        this.isOpen = false;
        this.dropdown.classList.remove('show');
        document.querySelector('.notification-bell')?.classList.remove('active');
    }

    async load() {
        if (!this.list) return;

        this.list.innerHTML = '<div class="notification-loading"><div class="spinner"></div></div>';

        try {
            const response = await fetch(`${window.BASE_URL}ajax/notifications.php?action=get&limit=10`);
            const data = await response.json();

            if (data.success) {
                this.notifications = data.notifications;
                this.updateBadge(data.unread_count);
                this.render();
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
            this.list.innerHTML = '<div class="notification-empty"><span class="material-icons">error</span><p>Failed to load</p></div>';
        }
    }

    render() {
        if (!this.list) return;

        if (this.notifications.length === 0) {
            this.list.innerHTML = `
                <div class="notification-empty">
                    <span class="material-icons">notifications_none</span>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        this.list.innerHTML = this.notifications.map(n => this.renderItem(n)).join('');

        // Add click handlers
        this.list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.dataset.id;
                const link = item.dataset.link;
                this.markRead(id);
                if (link) window.location.href = link;
            });
        });
    }

    renderItem(notification) {
        const iconClass = notification.type || 'system';
        const icon = this.getIcon(notification.type, notification.icon);
        const isUnread = !notification.is_read ? 'unread' : '';
        const timeAgo = this.timeAgo(notification.created_at);

        return `
            <div class="notification-item ${isUnread}" data-id="${notification.notification_id}" data-link="${notification.link || ''}">
                <div class="notification-icon ${iconClass}">
                    <span class="material-icons">${icon}</span>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            </div>
        `;
    }

    getIcon(type, fallback) {
        const icons = {
            'order': 'shopping_bag',
            'message': 'chat',
            'alert': 'warning',
            'advisory': 'assignment',
            'report': 'bug_report',
            'system': 'notifications'
        };
        return icons[type] || fallback || 'notifications';
    }

    updateBadge(count) {
        if (!this.badge) return;
        this.badge.textContent = count > 99 ? '99+' : count;
        this.badge.dataset.count = count;
        this.badge.style.display = count > 0 ? 'flex' : 'none';
    }

    async markRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);

            await fetch(`${window.BASE_URL}ajax/notifications.php`, {
                method: 'POST',
                body: formData
            });

            // Update local state
            const notification = this.notifications.find(n => n.notification_id == notificationId);
            if (notification) notification.is_read = 1;

            // Update badge
            const unread = this.notifications.filter(n => !n.is_read).length;
            this.updateBadge(unread);
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    }

    async clearAll() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            await fetch(`${window.BASE_URL}ajax/notifications.php`, {
                method: 'POST',
                body: formData
            });

            // Update all as read
            this.notifications.forEach(n => n.is_read = 1);
            this.updateBadge(0);
            this.render();

            this.showToast('All notifications marked as read');
        } catch (error) {
            console.error('Failed to clear notifications:', error);
        }
    }

    startPolling() {
        // Poll every 30 seconds for new notifications
        this.pollInterval = setInterval(() => {
            this.checkNewNotifications();
        }, 30000);
    }

    async checkNewNotifications() {
        try {
            const response = await fetch(`${window.BASE_URL}ajax/notifications.php?action=get&limit=1`);
            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.unread_count);

                // Check for new notification
                if (data.notifications.length > 0 && this.notifications.length > 0) {
                    const latest = data.notifications[0];
                    if (latest.notification_id > this.notifications[0]?.notification_id) {
                        this.showNewNotificationToast(latest);
                    }
                }
            }
        } catch (error) {
            console.error('Polling failed:', error);
        }
    }

    showNewNotificationToast(notification) {
        this.showToast(notification.title, notification.type);
        // Play sound if available
        const sound = document.getElementById('notificationSound');
        if (sound) sound.play().catch(() => { });
    }

    showToast(message, type = 'info') {
        // Use existing toast function if available
        if (typeof showToast === 'function') {
            showToast(message, type);
        }
    }

    timeAgo(datetime) {
        const now = new Date();
        const time = new Date(datetime);
        const diff = Math.floor((now - time) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return time.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});
