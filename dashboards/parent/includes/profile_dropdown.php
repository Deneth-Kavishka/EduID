<?php
/**
 * Parent Profile Dropdown Component
 * Google-style profile dropdown with notifications
 */

// Get current parent data if not already fetched
if (!isset($parent)) {
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    
    $query_header = "SELECT p.*, u.email, u.username, u.profile_picture, u.status 
                     FROM parents p 
                     JOIN users u ON p.user_id = u.user_id 
                     WHERE p.parent_id = :parent_id";
    $stmt_header = $conn_header->prepare($query_header);
    $stmt_header->bindParam(':parent_id', $_SESSION['parent_id']);
    $stmt_header->execute();
    $parent = $stmt_header->fetch(PDO::FETCH_ASSOC);
}

// Get user data for profile dropdown
if (!isset($conn_header)) {
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
}

$query_user = "SELECT * FROM users WHERE user_id = :user_id";
$stmt_user = $conn_header->prepare($query_user);
$stmt_user->bindParam(':user_id', $_SESSION['user_id']);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Get unread notification count
$query_notif = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
$stmt_notif = $conn_header->prepare($query_notif);
$stmt_notif->bindParam(':user_id', $_SESSION['user_id']);
$stmt_notif->execute();
$unread_count = $stmt_notif->fetchColumn();

// Get profile picture URL
$header_profile_pic = !empty($current_user['profile_picture']) ? '../../' . $current_user['profile_picture'] : null;
$header_default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%2310b981'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%2310b981'/%3E%3C/svg%3E";
?>

<!-- Current Time Display - Windows Taskbar Style -->
<div class="header-time-display" style="display: flex; flex-direction: column; align-items: flex-end; margin-right: 1rem; line-height: 1.2;">
    <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-primary);" id="navbarTime"><?php echo date('H:i:s'); ?></span>
    <span style="font-size: 0.7rem; color: var(--text-secondary);" id="navbarDate"><?php echo date('m/d/Y'); ?></span>
</div>

<button class="theme-toggle" id="themeToggleTop" title="Toggle Theme" type="button">
    <i class="fas fa-moon"></i>
</button>

<!-- Notification Bell with Dropdown -->
<div class="notification-dropdown-container">
    <button class="notification-trigger" onclick="toggleNotificationDropdown()" title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="notification-badge" id="notificationBadge" style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
    </button>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <span style="font-weight: 600;">Notifications</span>
            <button onclick="markAllRead()" style="background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 0.8rem;">Mark all read</button>
        </div>
        <div class="notification-dropdown-content" id="notificationContent">
            <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                <p>No new notifications</p>
            </div>
        </div>
    </div>
</div>

<!-- Profile Dropdown -->
<div class="profile-dropdown-container">
    <button class="profile-trigger" onclick="toggleProfileDropdown()">
        <img src="<?php echo htmlspecialchars($header_profile_pic ?? $header_default_avatar); ?>" alt="Profile" class="profile-avatar" onerror="this.src='<?php echo htmlspecialchars($header_default_avatar); ?>'">
    </button>
    
    <div class="profile-dropdown" id="profileDropdown">
        <!-- Profile Header -->
        <div class="profile-dropdown-header">
            <img src="<?php echo htmlspecialchars($header_profile_pic ?? $header_default_avatar); ?>" alt="Profile" class="profile-dropdown-avatar" onerror="this.src='<?php echo htmlspecialchars($header_default_avatar); ?>'">
            <div class="profile-dropdown-info">
                <span class="profile-dropdown-name"><?php echo htmlspecialchars(($parent['first_name'] ?? '') . ' ' . ($parent['last_name'] ?? '')); ?></span>
                <span class="profile-dropdown-email"><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></span>
            </div>
        </div>
        
        <!-- Account Section -->
        <div class="profile-dropdown-section">
            <a href="profile.php" class="profile-dropdown-item">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="children.php" class="profile-dropdown-item">
                <i class="fas fa-children"></i>
                <span>My Children</span>
            </a>
        </div>
        
        <!-- Quick Links Section -->
        <div class="profile-dropdown-section">
            <a href="attendance.php" class="profile-dropdown-item">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance History</span>
            </a>
            <a href="notifications.php" class="profile-dropdown-item">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
        </div>
        
        <!-- Logout Section -->
        <div class="profile-dropdown-footer">
            <a href="../../auth/logout.php" class="profile-dropdown-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sign out</span>
            </a>
        </div>
    </div>
</div>

<!-- Styles for Profile Dropdown -->
<style>
.notification-dropdown-container,
.profile-dropdown-container {
    position: relative;
}

.notification-trigger,
.profile-trigger {
    background: none;
    border: none;
    cursor: pointer;
    position: relative;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background 0.2s;
}

.notification-trigger:hover,
.profile-trigger:hover {
    background: var(--bg-tertiary);
}

.notification-trigger i {
    font-size: 1.25rem;
    color: var(--text-secondary);
}

.notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #ef4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.15rem 0.35rem;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
}

.profile-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
}

.notification-dropdown,
.profile-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: var(--bg-primary);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    border: 1px solid var(--border-color);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1000;
}

.notification-dropdown.active,
.profile-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-dropdown {
    width: 320px;
}

.profile-dropdown {
    width: 280px;
}

.notification-dropdown-header,
.profile-dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-dropdown-header {
    gap: 1rem;
}

.profile-dropdown-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.profile-dropdown-info {
    flex: 1;
    overflow: hidden;
}

.profile-dropdown-name {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-dropdown-email {
    display: block;
    font-size: 0.8rem;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-dropdown-content {
    max-height: 300px;
    overflow-y: auto;
}

.profile-dropdown-section {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.profile-dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    transition: background 0.15s;
}

.profile-dropdown-item:hover {
    background: var(--bg-secondary);
}

.profile-dropdown-item i {
    width: 20px;
    text-align: center;
    color: var(--text-secondary);
}

.profile-dropdown-footer {
    padding: 0.5rem;
}

.profile-dropdown-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem;
    color: #ef4444;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.15s;
    font-weight: 500;
}

.profile-dropdown-logout:hover {
    background: rgba(239, 68, 68, 0.1);
}
</style>

<!-- Scripts for Dropdowns -->
<script>
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    const notifDropdown = document.getElementById('notificationDropdown');
    notifDropdown?.classList.remove('active');
    dropdown.classList.toggle('active');
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    profileDropdown?.classList.remove('active');
    dropdown.classList.toggle('active');
}

function markAllRead() {
    fetch('includes/notification_handler.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notificationBadge').style.display = 'none';
                document.getElementById('notificationContent').innerHTML = `
                    <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                        <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                        <p>No new notifications</p>
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error:', err));
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const profileContainer = document.querySelector('.profile-dropdown-container');
    const notifContainer = document.querySelector('.notification-dropdown-container');
    
    if (profileContainer && !profileContainer.contains(e.target)) {
        document.getElementById('profileDropdown')?.classList.remove('active');
    }
    if (notifContainer && !notifContainer.contains(e.target)) {
        document.getElementById('notificationDropdown')?.classList.remove('active');
    }
});

// Theme Toggle - Self-contained implementation
(function() {
    // Get saved theme or default to light
    function getSavedTheme() {
        return localStorage.getItem('theme') || 'light';
    }
    
    // Apply theme to document
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        // Update all theme toggle icons
        document.querySelectorAll('.theme-toggle i, #themeToggleTop i').forEach(function(icon) {
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });
    }
    
    // Toggle theme
    function handleThemeToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        const currentTheme = getSavedTheme();
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
    }
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Apply saved theme immediately
        applyTheme(getSavedTheme());
        
        // Attach click handler to theme toggle button
        const themeBtn = document.getElementById('themeToggleTop');
        if (themeBtn) {
            themeBtn.addEventListener('click', handleThemeToggle);
        }
    });
    
    // Also apply theme immediately (before DOMContentLoaded)
    applyTheme(getSavedTheme());
})();

// Update navbar time every second
function updateNavbarTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
    
    const navbarTime = document.getElementById('navbarTime');
    const navbarDate = document.getElementById('navbarDate');
    if (navbarTime) navbarTime.textContent = timeStr;
    if (navbarDate) navbarDate.textContent = dateStr;
}
setInterval(updateNavbarTime, 1000);
</script>
