<!-- Teacher Notification Styles and Handler -->
<style>
    /* Notification Dropdown Container */
    .notification-dropdown-container {
        position: relative;
    }
    
    .notification-trigger {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        color: var(--text-secondary);
        position: relative;
        transition: all 0.2s;
    }
    
    .notification-trigger:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        background: #ef4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 600;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 360px;
        max-height: 480px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
    }
    
    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .notification-dropdown-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .notification-dropdown-header h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .notification-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .notif-action-btn {
        background: none;
        border: none;
        padding: 0.4rem;
        border-radius: 6px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.2s;
    }
    
    .notif-action-btn:hover {
        background: var(--bg-secondary);
        color: #10b981;
    }
    
    .notification-dropdown-body {
        flex: 1;
        overflow-y: auto;
        max-height: 350px;
    }
    
    .notification-item {
        display: flex;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .notification-item:hover {
        background: var(--bg-secondary);
    }
    
    .notification-item.unread {
        background: rgba(16, 185, 129, 0.05);
    }
    
    .notification-icon-wrapper {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .notification-content {
        flex: 1;
        min-width: 0;
    }
    
    .notification-title {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    
    .notification-message {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .notification-time {
        font-size: 0.7rem;
        color: var(--text-tertiary);
        margin-top: 0.25rem;
    }
    
    .notification-dropdown-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid var(--border-color);
        text-align: center;
    }
    
    .notification-dropdown-footer a {
        color: #10b981;
        font-size: 0.85rem;
        text-decoration: none;
        font-weight: 500;
    }
    
    .notification-dropdown-footer a:hover {
        text-decoration: underline;
    }
    
    .notification-loading, .notification-empty {
        padding: 2rem;
        text-align: center;
        color: var(--text-secondary);
    }
    
    .notification-loading i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #10b981;
    }
    
    /* Notification Modal */
    .notification-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s;
    }
    
    .notification-modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .notification-modal {
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        background: var(--bg-primary);
        border-radius: 16px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        transform: scale(0.9);
        transition: transform 0.3s;
    }
    
    .notification-modal-overlay.show .notification-modal {
        transform: scale(1);
    }
    
    .notification-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .notification-modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-primary);
    }
    
    .notification-modal-close {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .notification-modal-close:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    .notification-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    
    .notification-list-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 0.5rem;
        transition: background 0.2s;
    }
    
    .notification-list-item:hover {
        background: var(--bg-secondary);
    }
    
    .notification-list-item.unread {
        background: rgba(16, 185, 129, 0.05);
        border-left: 3px solid #10b981;
    }
</style>

<script>
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        
        // Close profile dropdown if open
        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }
        
        dropdown.classList.toggle('show');
        
        if (dropdown.classList.contains('show')) {
            loadNotifications();
        }
    }
    
    function loadNotifications() {
        const list = document.getElementById('notificationList');
        list.innerHTML = `
            <div class="notification-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading notifications...</span>
            </div>
        `;
        
        fetch('includes/notification_handler.php?action=get_notifications&limit=5')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    list.innerHTML = data.notifications.map(notif => `
                        <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markNotificationRead(${notif.notification_id})">
                            <div class="notification-icon-wrapper" style="background: ${getNotificationColor(notif.type)};">
                                <i class="${getNotificationIcon(notif.type)}" style="color: white; font-size: 0.8rem;"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notif.title}</div>
                                <div class="notification-message">${notif.message}</div>
                                <div class="notification-time">${formatTime(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                            <p>No notifications</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                list.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 0.5rem; color: #ef4444;"></i>
                        <p>Failed to load notifications</p>
                    </div>
                `;
            });
    }
    
    function getNotificationIcon(type) {
        const icons = {
            'info': 'fas fa-info',
            'success': 'fas fa-check',
            'warning': 'fas fa-exclamation',
            'alert': 'fas fa-bell',
            'system': 'fas fa-cog'
        };
        return icons[type] || 'fas fa-bell';
    }
    
    function getNotificationColor(type) {
        const colors = {
            'info': '#3b82f6',
            'success': '#10b981',
            'warning': '#f59e0b',
            'alert': '#ef4444',
            'system': '#8b5cf6'
        };
        return colors[type] || '#6b7280';
    }
    
    function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    }
    
    function markNotificationRead(id) {
        fetch('includes/notification_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', notification_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge();
                loadNotifications();
            }
        });
    }
    
    function markAllNotificationsRead() {
        fetch('includes/notification_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge();
                loadNotifications();
            }
        });
    }
    
    function updateNotificationBadge() {
        fetch('includes/notification_handler.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('notificationBadge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 9 ? '9+' : data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
    }
    
    function openNotificationModal() {
        // Close dropdown
        document.getElementById('notificationDropdown').classList.remove('show');
        
        // Create modal if not exists
        let modal = document.getElementById('notificationModalOverlay');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'notificationModalOverlay';
            modal.className = 'notification-modal-overlay';
            modal.innerHTML = `
                <div class="notification-modal">
                    <div class="notification-modal-header">
                        <h3><i class="fas fa-bell"></i> All Notifications</h3>
                        <button class="notification-modal-close" onclick="closeNotificationModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="notification-modal-body" id="notificationModalList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading...</span>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        setTimeout(() => modal.classList.add('show'), 10);
        loadModalNotifications();
    }
    
    function closeNotificationModal() {
        const modal = document.getElementById('notificationModalOverlay');
        if (modal) {
            modal.classList.remove('show');
        }
    }
    
    function loadModalNotifications() {
        const list = document.getElementById('notificationModalList');
        
        fetch('includes/notification_handler.php?action=get_notifications&limit=50')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    list.innerHTML = data.notifications.map(notif => `
                        <div class="notification-list-item ${notif.is_read == 0 ? 'unread' : ''}">
                            <div class="notification-icon-wrapper" style="background: ${getNotificationColor(notif.type)};">
                                <i class="${getNotificationIcon(notif.type)}" style="color: white;"></i>
                            </div>
                            <div class="notification-content" style="flex: 1;">
                                <div class="notification-title">${notif.title}</div>
                                <div class="notification-message" style="-webkit-line-clamp: unset;">${notif.message}</div>
                                <div class="notification-time">${formatTime(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `
                        <div class="notification-empty" style="padding: 3rem;">
                            <i class="fas fa-bell-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>No notifications yet</p>
                        </div>
                    `;
                }
            });
    }
    
    // Close modal on backdrop click
    document.addEventListener('click', function(e) {
        if (e.target.id === 'notificationModalOverlay') {
            closeNotificationModal();
        }
    });
</script>
