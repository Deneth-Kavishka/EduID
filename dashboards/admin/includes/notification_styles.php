<style>
/* Notification Dropdown Styles */
.notification-dropdown-container {
    position: relative;
}

.notification-trigger {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: var(--bg-card, #ffffff);
    color: var(--text-secondary, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 1.1rem;
}

.notification-trigger:hover {
    background: var(--bg-hover, #f1f5f9);
    color: var(--primary, #4f46e5);
}

.notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notification-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 380px;
    max-height: 500px;
    background: var(--bg-card, #ffffff);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.2s ease;
    z-index: 1001;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.notification-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.notification-dropdown-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-card, #ffffff);
}

.notification-dropdown-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
}

.notification-actions {
    display: flex;
    gap: 8px;
}

.notif-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--text-secondary, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    text-decoration: none;
}

.notif-action-btn:hover {
    background: var(--bg-hover, #f1f5f9);
    color: var(--primary, #4f46e5);
}

.notification-dropdown-body {
    flex: 1;
    overflow-y: auto;
    max-height: 350px;
}

.notification-loading {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-secondary, #64748b);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.notification-loading i {
    font-size: 1.5rem;
    color: var(--primary, #4f46e5);
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-secondary, #64748b);
}

.notification-empty i {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
}

.notification-empty p {
    margin: 0;
    font-size: 0.95rem;
}

.notification-item {
    padding: 14px 20px;
    display: flex;
    gap: 12px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    cursor: pointer;
    transition: background 0.2s ease;
    position: relative;
}

.notification-item:hover {
    background: var(--bg-hover, #f8fafc);
}

.notification-item.unread {
    background: var(--primary-alpha, rgba(79, 70, 229, 0.05));
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary, #4f46e5);
}

.notification-icon-wrapper {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-icon-wrapper.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.notification-icon-wrapper.success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.notification-icon-wrapper.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.notification-icon-wrapper.error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    margin-bottom: 4px;
    line-height: 1.3;
}

.notification-message {
    font-size: 0.85rem;
    color: var(--text-secondary, #64748b);
    line-height: 1.4;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--text-muted, #94a3b8);
}

.notification-delete {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.notification-item:hover .notification-delete {
    display: flex;
}

.notification-delete:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.notification-item-actions {
    display: flex;
    flex-direction: column;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.notification-item:hover .notification-item-actions {
    opacity: 1;
}

.notif-item-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.notif-item-btn:hover {
    background: var(--primary-alpha, rgba(79, 70, 229, 0.1));
    color: var(--primary, #4f46e5);
}

.notif-item-btn.delete:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.notification-dropdown-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--border-color, #e2e8f0);
    text-align: center;
    background: var(--bg-card, #ffffff);
}

.notification-dropdown-footer a {
    font-size: 0.9rem;
    color: var(--primary, #4f46e5);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
    cursor: pointer;
}

.notification-dropdown-footer a:hover {
    text-decoration: underline;
}

/* Notification Modal Styles */
.notification-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}

.notification-modal-overlay.active {
    display: flex;
}

.notification-modal {
    background: var(--bg-card, #ffffff);
    border-radius: 16px;
    width: 100%;
    max-width: 700px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.notification-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notification-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-modal-header h3 i {
    color: var(--primary, #4f46e5);
}

.notification-modal-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-action-btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.modal-action-btn.primary {
    background: var(--primary, #4f46e5);
    color: white;
}

.modal-action-btn.primary:hover {
    background: var(--primary-dark, #4338ca);
}

.modal-action-btn.secondary {
    background: var(--bg-hover, #f1f5f9);
    color: var(--text-secondary, #64748b);
}

.modal-action-btn.secondary:hover {
    background: var(--border-color, #e2e8f0);
}

.modal-action-btn.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.modal-action-btn.danger:hover {
    background: #ef4444;
    color: white;
}

.modal-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--text-secondary, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.2s ease;
}

.modal-close-btn:hover {
    background: var(--bg-hover, #f1f5f9);
    color: var(--text-primary, #1e293b);
}

.notification-modal-tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 0 24px;
}

.modal-tab {
    padding: 12px 20px;
    border: none;
    background: none;
    color: var(--text-secondary, #64748b);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    position: relative;
    transition: color 0.2s ease;
}

.modal-tab:hover {
    color: var(--text-primary, #1e293b);
}

.modal-tab.active {
    color: var(--primary, #4f46e5);
}

.modal-tab.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary, #4f46e5);
}

.notification-modal-filters {
    padding: 16px 24px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.filter-chip {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-card, #ffffff);
    color: var(--text-secondary, #64748b);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-chip:hover {
    border-color: var(--primary, #4f46e5);
    color: var(--primary, #4f46e5);
}

.filter-chip.active {
    background: var(--primary, #4f46e5);
    color: white;
    border-color: var(--primary, #4f46e5);
}

.notification-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.modal-notification-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.modal-notification-item {
    padding: 16px 24px;
    display: flex;
    gap: 14px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    transition: background 0.2s ease;
    position: relative;
}

.modal-notification-item:hover {
    background: var(--bg-hover, #f8fafc);
}

.modal-notification-item.unread {
    background: var(--primary-alpha, rgba(79, 70, 229, 0.05));
}

.modal-notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary, #4f46e5);
}

.modal-notif-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
}

.modal-notif-icon.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.modal-notif-icon.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.modal-notif-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.modal-notif-icon.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.modal-notif-content {
    flex: 1;
    min-width: 0;
}

.modal-notif-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
}

.modal-notif-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    margin: 0;
}

.modal-notif-time {
    font-size: 0.8rem;
    color: var(--text-muted, #94a3b8);
    white-space: nowrap;
}

.modal-notif-message {
    font-size: 0.9rem;
    color: var(--text-secondary, #64748b);
    line-height: 1.5;
    margin: 0;
}

.modal-notif-actions {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    padding-top: 4px;
}

.modal-notif-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    opacity: 0;
}

.modal-notification-item:hover .modal-notif-btn {
    opacity: 1;
}

.modal-notif-btn:hover {
    background: var(--primary-alpha, rgba(79, 70, 229, 0.1));
    color: var(--primary, #4f46e5);
}

.modal-notif-btn.delete:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.modal-empty-state {
    padding: 60px 24px;
    text-align: center;
}

.modal-empty-state i {
    font-size: 3.5rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 16px;
}

.modal-empty-state h4 {
    font-size: 1.1rem;
    color: var(--text-primary, #1e293b);
    margin: 0 0 8px;
}

.modal-empty-state p {
    color: var(--text-secondary, #64748b);
    margin: 0;
}

/* Settings Tab Styles */
.notification-settings {
    padding: 24px;
}

.settings-section {
    margin-bottom: 28px;
}

.settings-section:last-child {
    margin-bottom: 0;
}

.settings-section h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-section h4 i {
    color: var(--primary, #4f46e5);
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-info {
    flex: 1;
}

.setting-info label {
    display: block;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-primary, #1e293b);
    margin-bottom: 4px;
}

.setting-info span {
    font-size: 0.85rem;
    color: var(--text-secondary, #64748b);
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    width: 48px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--border-color, #e2e8f0);
    border-radius: 26px;
    transition: 0.3s;
}

.toggle-slider::before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.toggle-switch input:checked + .toggle-slider {
    background: var(--primary, #4f46e5);
}

.toggle-switch input:checked + .toggle-slider::before {
    transform: translateX(22px);
}

.notification-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Dark Mode Adjustments */
[data-theme="dark"] .notification-dropdown {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .notification-item.unread {
    background: rgba(79, 70, 229, 0.1);
}

/* Responsive */
@media (max-width: 480px) {
    .notification-dropdown {
        width: calc(100vw - 30px);
        right: -100px;
    }
}
</style>

<script>
// Notification Dropdown Functions
let notificationDropdownOpen = false;

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    notificationDropdownOpen = !notificationDropdownOpen;
    
    if (notificationDropdownOpen) {
        dropdown.classList.add('active');
        loadNotifications();
        // Close profile dropdown if open
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown && profileDropdown.classList.contains('active')) {
            profileDropdown.classList.remove('active');
        }
    } else {
        dropdown.classList.remove('active');
    }
}

function loadNotifications() {
    const listContainer = document.getElementById('notificationList');
    
    listContainer.innerHTML = `
        <div class="notification-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading notifications...</span>
        </div>
    `;
    
    fetch('includes/notification_handler.php?action=get_notifications&limit=10')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderNotifications(data.data.notifications, data.data.unread_count);
            } else {
                listContainer.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Error loading notifications</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            listContainer.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error loading notifications</p>
                </div>
            `;
        });
}

function renderNotifications(notifications, unreadCount) {
    const listContainer = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    
    // Update badge
    if (unreadCount > 0) {
        badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
    
    if (!notifications || notifications.length === 0) {
        listContainer.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications yet</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        const icon = getNotificationIcon(notif.type);
        const isUnread = notif.is_read === '0' || notif.is_read === 0;
        
        html += `
            <div class="notification-item ${isUnread ? 'unread' : ''}" 
                 data-id="${notif.notification_id}">
                <div class="notification-icon-wrapper ${notif.type}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content" onclick="toggleNotificationReadStatus(${notif.notification_id}, ${isUnread})">
                    <div class="notification-title">${escapeHtml(notif.title)}</div>
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-time">${formatTimeAgo(notif.created_at)}</div>
                </div>
                <div class="notification-item-actions">
                    <button class="notif-item-btn" onclick="toggleNotificationReadStatus(${notif.notification_id}, ${isUnread})" title="${isUnread ? 'Mark as read' : 'Mark as unread'}">
                        <i class="fas ${isUnread ? 'fa-envelope-open' : 'fa-envelope'}"></i>
                    </button>
                    <button class="notif-item-btn delete" onclick="deleteNotification(event, ${notif.notification_id})" title="Delete">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    listContainer.innerHTML = html;
}

function getNotificationIcon(type) {
    const icons = {
        'info': 'fa-info-circle',
        'success': 'fa-check-circle',
        'warning': 'fa-exclamation-triangle',
        'error': 'fa-times-circle'
    };
    return icons[type] || 'fa-bell';
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function markNotificationRead(notificationId) {
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
            }
            // Update badge
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error:', error));
}

function toggleNotificationReadStatus(notificationId, isCurrentlyUnread) {
    const action = isCurrentlyUnread ? 'mark_read' : 'mark_unread';
    
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notifications to update UI
            loadNotifications();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsRead() {
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI - remove unread class from all items
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            // Hide badge
            const badge = document.getElementById('notificationBadge');
            badge.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

function deleteNotification(event, notificationId) {
    event.stopPropagation();
    
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove item from DOM
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.remove();
            }
            // Check if list is empty
            const list = document.getElementById('notificationList');
            if (!list.querySelector('.notification-item')) {
                list.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet</p>
                    </div>
                `;
            }
            // Update badge
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNotificationBadge() {
    fetch('includes/notification_handler.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('notificationBadge');
                const count = data.data.unread_count;
                if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error:', error));
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(event) {
    const container = document.querySelector('.notification-dropdown-container');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (container && dropdown && !container.contains(event.target)) {
        dropdown.classList.remove('active');
        notificationDropdownOpen = false;
    }
});

// Update badge periodically (every 60 seconds)
setInterval(updateNotificationBadge, 60000);

// =============================================
// NOTIFICATION MODAL FUNCTIONS
// =============================================

let currentModalFilter = 'all';
let currentModalTab = 'notifications';
let allModalNotifications = [];

function openNotificationModal() {
    // Close dropdown first
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.remove('active');
        notificationDropdownOpen = false;
    }
    
    // Create modal if it doesn't exist
    if (!document.getElementById('notificationModalOverlay')) {
        createNotificationModal();
    }
    
    const modal = document.getElementById('notificationModalOverlay');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Load all notifications
    loadModalNotifications();
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModalOverlay');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function createNotificationModal() {
    const modalHTML = `
        <div class="notification-modal-overlay" id="notificationModalOverlay" onclick="if(event.target === this) closeNotificationModal()">
            <div class="notification-modal">
                <div class="notification-modal-header">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                    <div class="notification-modal-actions">
                        <button class="modal-action-btn secondary" onclick="markAllModalNotificationsRead()">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                        <button class="modal-action-btn danger" onclick="clearAllModalNotifications()">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                        <button class="modal-close-btn" onclick="closeNotificationModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="notification-modal-tabs">
                    <button class="modal-tab active" data-tab="notifications" onclick="switchModalTab('notifications')">
                        <i class="fas fa-bell"></i> All Notifications
                    </button>
                    <button class="modal-tab" data-tab="settings" onclick="switchModalTab('settings')">
                        <i class="fas fa-cog"></i> Settings
                    </button>
                </div>
                
                <div class="notification-modal-filters" id="modalFilters">
                    <button class="filter-chip active" data-filter="all" onclick="filterModalNotifications('all')">All</button>
                    <button class="filter-chip" data-filter="unread" onclick="filterModalNotifications('unread')">Unread</button>
                    <button class="filter-chip" data-filter="info" onclick="filterModalNotifications('info')">
                        <i class="fas fa-info-circle"></i> Info
                    </button>
                    <button class="filter-chip" data-filter="success" onclick="filterModalNotifications('success')">
                        <i class="fas fa-check-circle"></i> Success
                    </button>
                    <button class="filter-chip" data-filter="warning" onclick="filterModalNotifications('warning')">
                        <i class="fas fa-exclamation-triangle"></i> Warning
                    </button>
                    <button class="filter-chip" data-filter="error" onclick="filterModalNotifications('error')">
                        <i class="fas fa-times-circle"></i> Error
                    </button>
                </div>
                
                <div class="notification-modal-body" id="modalBody">
                    <div class="modal-notification-list" id="modalNotificationList">
                        <div class="modal-empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <h4>Loading notifications...</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function switchModalTab(tab) {
    currentModalTab = tab;
    
    // Update tab buttons
    document.querySelectorAll('.modal-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === tab);
    });
    
    const filtersEl = document.getElementById('modalFilters');
    const bodyEl = document.getElementById('modalBody');
    
    if (tab === 'notifications') {
        filtersEl.style.display = 'flex';
        loadModalNotifications();
    } else {
        filtersEl.style.display = 'none';
        renderSettingsTab();
    }
}

function renderSettingsTab() {
    const bodyEl = document.getElementById('modalBody');
    bodyEl.innerHTML = `
        <div class="notification-settings">
            <div class="settings-section">
                <h4><i class="fas fa-bell"></i> Notification Preferences</h4>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Email Notifications</label>
                        <span>Receive notifications via email</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="emailNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Push Notifications</label>
                        <span>Receive browser push notifications</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="pushNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Sound Alerts</label>
                        <span>Play sound for new notifications</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="soundNotif">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="settings-section">
                <h4><i class="fas fa-filter"></i> Notification Types</h4>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>System Alerts</label>
                        <span>Important system updates and alerts</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="systemNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Student Updates</label>
                        <span>New registrations and student activities</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="studentNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Attendance Alerts</label>
                        <span>Low attendance and absence notifications</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="attendanceNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Event Reminders</label>
                        <span>Upcoming events and deadlines</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="eventNotif" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="settings-section">
                <h4><i class="fas fa-clock"></i> Quiet Hours</h4>
                <div class="setting-item">
                    <div class="setting-info">
                        <label>Enable Quiet Hours</label>
                        <span>Don't show notifications during quiet hours</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="quietHours">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    `;
}

function loadModalNotifications() {
    const listEl = document.getElementById('modalNotificationList');
    if (!listEl) return;
    
    listEl.innerHTML = `
        <div class="modal-empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <h4>Loading notifications...</h4>
        </div>
    `;
    
    fetch('includes/notification_handler.php?action=get_notifications&limit=50')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allModalNotifications = data.data.notifications || [];
                renderModalNotifications();
            } else {
                listEl.innerHTML = `
                    <div class="modal-empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Error loading notifications</h4>
                        <p>Please try again later</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            listEl.innerHTML = `
                <div class="modal-empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <h4>Error loading notifications</h4>
                    <p>Please try again later</p>
                </div>
            `;
        });
}

function filterModalNotifications(filter) {
    currentModalFilter = filter;
    
    // Update filter chips
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.toggle('active', chip.dataset.filter === filter);
    });
    
    renderModalNotifications();
}

function renderModalNotifications() {
    const listEl = document.getElementById('modalNotificationList');
    if (!listEl) return;
    
    let filtered = allModalNotifications;
    
    if (currentModalFilter === 'unread') {
        filtered = allModalNotifications.filter(n => n.is_read === '0' || n.is_read === 0);
    } else if (['info', 'success', 'warning', 'error'].includes(currentModalFilter)) {
        filtered = allModalNotifications.filter(n => n.type === currentModalFilter);
    }
    
    if (filtered.length === 0) {
        listEl.innerHTML = `
            <div class="modal-empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No notifications</h4>
                <p>${currentModalFilter === 'all' ? 'You\'re all caught up!' : 'No ' + currentModalFilter + ' notifications'}</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    filtered.forEach(notif => {
        const icon = getNotificationIcon(notif.type);
        const isUnread = notif.is_read === '0' || notif.is_read === 0;
        
        html += `
            <div class="modal-notification-item ${isUnread ? 'unread' : ''}" data-id="${notif.notification_id}">
                <div class="modal-notif-icon ${notif.type}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="modal-notif-content">
                    <div class="modal-notif-header">
                        <h5 class="modal-notif-title">${escapeHtml(notif.title)}</h5>
                        <span class="modal-notif-time">${formatTimeAgo(notif.created_at)}</span>
                    </div>
                    <p class="modal-notif-message">${escapeHtml(notif.message)}</p>
                </div>
                <div class="modal-notif-actions">
                    <button class="modal-notif-btn" onclick="toggleModalNotificationRead(${notif.notification_id}, ${isUnread})" title="${isUnread ? 'Mark as read' : 'Mark as unread'}">
                        <i class="fas ${isUnread ? 'fa-envelope-open' : 'fa-envelope'}"></i>
                    </button>
                    <button class="modal-notif-btn delete" onclick="deleteModalNotification(${notif.notification_id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    listEl.innerHTML = html;
}

function toggleModalNotificationRead(id, isCurrentlyUnread) {
    const action = isCurrentlyUnread ? 'mark_read' : 'mark_unread';
    
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&notification_id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update local data
            const notif = allModalNotifications.find(n => n.notification_id == id);
            if (notif) {
                notif.is_read = isCurrentlyUnread ? 1 : 0;
            }
            renderModalNotifications();
            updateNotificationBadge();
            loadNotifications(); // Refresh dropdown too
        }
    });
}

function deleteModalNotification(id) {
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&notification_id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allModalNotifications = allModalNotifications.filter(n => n.notification_id != id);
            renderModalNotifications();
            updateNotificationBadge();
            loadNotifications();
        }
    });
}

function markAllModalNotificationsRead() {
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allModalNotifications.forEach(n => n.is_read = 1);
            renderModalNotifications();
            updateNotificationBadge();
            loadNotifications();
        }
    });
}

function clearAllModalNotifications() {
    if (!confirm('Are you sure you want to clear all notifications?')) return;
    
    fetch('includes/notification_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clear_all'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allModalNotifications = [];
            renderModalNotifications();
            updateNotificationBadge();
            loadNotifications();
        }
    });
}

function openNotificationSettings() {
    openNotificationModal();
    setTimeout(() => switchModalTab('settings'), 100);
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNotificationModal();
    }
});
</script>