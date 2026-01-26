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

// Get profile picture URL
$header_profile_pic = !empty($current_user['profile_picture']) ? '../../' . $current_user['profile_picture'] : null;
$header_default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%23cbd5e1'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%23cbd5e1'/%3E%3C/svg%3E";
?>

<button class="theme-toggle" id="themeToggleTop" title="Toggle Theme">
    <i class="fas fa-moon"></i>
</button>

<div class="notification-icon">
    <i class="fas fa-bell"></i>
    <span class="badge">3</span>
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