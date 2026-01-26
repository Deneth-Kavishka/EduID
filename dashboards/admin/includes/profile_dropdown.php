<?php
/**
 * Profile Dropdown Only Component (without wrapper div)
 * Use this inside existing header-right divs that have custom elements like search boxes
 */

// Get current user data if not already fetched
if (!isset($current_user)) {
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
    
    $query_header = "SELECT * FROM users WHERE user_id = :user_id";
    $stmt_header = $conn_header->prepare($query_header);
    $stmt_header->bindParam(':user_id', $_SESSION['user_id']);
    $stmt_header->execute();
    $current_user = $stmt_header->fetch(PDO::FETCH_ASSOC);
}

// Get unread notification count
if (!isset($conn_header)) {
    $db_header = new Database();
    $conn_header = $db_header->getConnection();
}
$query_notif = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
$stmt_notif = $conn_header->prepare($query_notif);
$stmt_notif->bindParam(':user_id', $_SESSION['user_id']);
$stmt_notif->execute();
$unread_count = $stmt_notif->fetchColumn();

// Get profile picture URL
$header_profile_pic = !empty($current_user['profile_picture']) ? '../../' . $current_user['profile_picture'] : null;
$header_default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%23cbd5e1'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%23cbd5e1'/%3E%3C/svg%3E";
?>

<button class="theme-toggle" id="themeToggleTop" title="Toggle Theme">
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
            <h4>Notifications</h4>
            <div class="notification-actions">
                <button onclick="markAllNotificationsRead()" title="Mark all as read" class="notif-action-btn">
                    <i class="fas fa-check-double"></i>
                </button>
                <button onclick="openNotificationSettings()" title="Notification Settings" class="notif-action-btn">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
        
        <div class="notification-dropdown-body" id="notificationList">
            <div class="notification-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading notifications...</span>
            </div>
        </div>
        
        <div class="notification-dropdown-footer">
            <a onclick="openNotificationModal()" style="cursor: pointer;">View All Notifications</a>
        </div>
    </div>
</div>

<!-- Google-style Profile Dropdown -->
<div class="profile-dropdown-container">
    <button class="profile-trigger" onclick="toggleProfileDropdown()">
        <img src="<?php echo htmlspecialchars($header_profile_pic ?? $header_default_avatar); ?>" alt="Profile" class="user-avatar" onerror="this.src='<?php echo htmlspecialchars($header_default_avatar); ?>'">
    </button>
    
    <div class="profile-dropdown" id="profileDropdown">
        <div class="profile-dropdown-header">
            <div class="profile-dropdown-avatar">
                <img src="<?php echo htmlspecialchars($header_profile_pic ?? $header_default_avatar); ?>" alt="Profile" onerror="this.src='<?php echo htmlspecialchars($header_default_avatar); ?>'">
            </div>
            <div class="profile-dropdown-info">
                <div class="profile-dropdown-name"><?php echo htmlspecialchars($current_user['username']); ?></div>
                <div class="profile-dropdown-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
            </div>
        </div>
        
        <div class="profile-dropdown-manage">
            <a href="profile.php" class="manage-account-btn">
                Manage your Account
            </a>
        </div>
        
        <div class="profile-dropdown-divider"></div>
        
        <div class="profile-dropdown-menu">
            <a href="profile.php" class="profile-dropdown-item">
                <i class="fas fa-user-circle"></i>
                <span>Profile Settings</span>
            </a>
            <a href="settings.php" class="profile-dropdown-item">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
            <a href="#" class="profile-dropdown-item" onclick="toggleThemeDropdown(); return false;">
                <i class="fas fa-moon" id="dropdownThemeIcon"></i>
                <span>Dark Mode</span>
                <span class="theme-indicator" id="themeIndicator">Off</span>
            </a>
        </div>
        
        <div class="profile-dropdown-divider"></div>
        
        <div class="profile-dropdown-menu">
            <a href="../../auth/logout.php" class="profile-dropdown-item logout-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sign out</span>
            </a>
        </div>
        
        <div class="profile-dropdown-footer">
            <a href="#">Privacy Policy</a>
            <span>•</span>
            <a href="#">Terms of Service</a>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/profile_dropdown_styles.php'; ?>
<?php include_once __DIR__ . '/notification_styles.php'; ?>