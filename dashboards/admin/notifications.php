<?php
/**
 * Notification Management Page - EduID Admin Portal
 * Full notification center with settings
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get current user
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle clear all notifications
if (isset($_POST['clear_all'])) {
    $query = "DELETE FROM notifications WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    header('Location: notifications.php?success=cleared');
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filter settings
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build query
$where_conditions = ["user_id = :user_id"];
$params = [':user_id' => $_SESSION['user_id']];

if ($filter === 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where_conditions[] = "is_read = 1";
}

if ($type_filter && in_array($type_filter, ['info', 'success', 'warning', 'error'])) {
    $where_conditions[] = "type = :type";
    $params[':type'] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM notifications WHERE $where_clause";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $per_page);

// Get notifications
$query = "SELECT * FROM notifications WHERE $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
$stmt = $conn->prepare($unread_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$unread_count = $stmt->fetchColumn();

// Get counts by type
$type_counts_query = "SELECT type, COUNT(*) as count FROM notifications WHERE user_id = :user_id GROUP BY type";
$stmt = $conn->prepare($type_counts_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$type_counts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$type_counts = ['info' => 0, 'success' => 0, 'warning' => 0, 'error' => 0];
foreach ($type_counts_raw as $tc) {
    $type_counts[$tc['type']] = $tc['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - EduID Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .notifications-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .notifications-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notifications-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .notifications-title .badge {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .notifications-actions {
            display: flex;
            gap: 12px;
        }

        .notifications-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card.active {
            border-color: var(--primary);
            background: var(--primary-alpha);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.25rem;
        }

        .stat-card .stat-icon.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-card .stat-icon.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .stat-card .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-card .stat-icon.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-card .stat-icon.all { background: var(--primary-alpha); color: var(--primary); }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .notifications-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .notifications-list {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .notification-item {
            padding: 20px 24px;
            display: flex;
            gap: 16px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--bg-hover);
        }

        .notification-item.unread {
            background: var(--primary-alpha);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
        }

        .notification-checkbox {
            display: flex;
            align-items: flex-start;
            padding-top: 4px;
        }

        .notification-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .notification-icon-lg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
        }

        .notification-icon-lg.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .notification-icon-lg.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .notification-icon-lg.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .notification-icon-lg.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .notification-body {
            flex: 1;
            min-width: 0;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            gap: 16px;
        }

        .notification-title-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .notification-time {
            font-size: 0.85rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .notification-type-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-type-badge.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .notification-type-badge.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .notification-type-badge.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .notification-type-badge.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .notification-message-text {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .notification-actions-row {
            display: flex;
            gap: 12px;
        }

        .notification-action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-action-btn.mark-read {
            background: var(--primary-alpha);
            color: var(--primary);
        }

        .notification-action-btn.mark-read:hover {
            background: var(--primary);
            color: white;
        }

        .notification-action-btn.mark-unread {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .notification-action-btn.mark-unread:hover {
            background: #f59e0b;
            color: white;
        }

        .notification-action-btn.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .notification-action-btn.delete:hover {
            background: #ef4444;
            color: white;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .bulk-actions {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            background: var(--primary-alpha);
            border-bottom: 1px solid var(--border-color);
        }

        .bulk-actions.visible {
            display: flex;
        }

        .bulk-actions span {
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #22c55e;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-item {
                flex-direction: column;
            }

            .notification-header {
                flex-direction: column;
            }

            .notification-meta {
                order: -1;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-id-card"></i>
                    <span>EduID</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="index.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="students.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>Students</span>
                        </a>
                    </li>
                    <li>
                        <a href="teachers.php">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Teachers</span>
                        </a>
                    </li>
                    <li>
                        <a href="parents.php">
                            <i class="fas fa-users"></i>
                            <span>Parents</span>
                        </a>
                    </li>
                    <li>
                        <a href="classes.php">
                            <i class="fas fa-school"></i>
                            <span>Classes</span>
                        </a>
                    </li>
                    <li>
                        <a href="attendance.php">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li>
                        <a href="events.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                            <span class="nav-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="logs.php">
                            <i class="fas fa-history"></i>
                            <span>Access Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search notifications..." id="searchNotifications">
                    </div>
                </div>
                
                <?php include 'includes/header_profile.php'; ?>
            </header>

            <!-- Page Content -->
            <div class="content-wrapper">
                <div class="notifications-page">
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'cleared'): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        All notifications have been cleared successfully.
                    </div>
                    <?php endif; ?>

                    <div class="notifications-header">
                        <div class="notifications-title">
                            <h1>Notifications</h1>
                            <?php if ($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?> unread</span>
                            <?php endif; ?>
                        </div>
                        <div class="notifications-actions">
                            <button class="btn btn-secondary" onclick="markAllRead()">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                                <button type="submit" name="clear_all" class="btn btn-outline-danger">
                                    <i class="fas fa-trash"></i> Clear All
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="notifications-stats">
                        <a href="?filter=all" class="stat-card <?php echo $filter === 'all' && !$type_filter ? 'active' : ''; ?>">
                            <div class="stat-icon all">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_notifications; ?></div>
                            <div class="stat-label">Total</div>
                        </a>
                        <a href="?type=info" class="stat-card <?php echo $type_filter === 'info' ? 'active' : ''; ?>">
                            <div class="stat-icon info">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $type_counts['info']; ?></div>
                            <div class="stat-label">Info</div>
                        </a>
                        <a href="?type=success" class="stat-card <?php echo $type_filter === 'success' ? 'active' : ''; ?>">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $type_counts['success']; ?></div>
                            <div class="stat-label">Success</div>
                        </a>
                        <a href="?type=warning" class="stat-card <?php echo $type_filter === 'warning' ? 'active' : ''; ?>">
                            <div class="stat-icon warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-value"><?php echo $type_counts['warning']; ?></div>
                            <div class="stat-label">Warning</div>
                        </a>
                        <a href="?type=error" class="stat-card <?php echo $type_filter === 'error' ? 'active' : ''; ?>">
                            <div class="stat-icon error">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $type_counts['error']; ?></div>
                            <div class="stat-label">Error</div>
                        </a>
                    </div>

                    <!-- Filters -->
                    <div class="notifications-filters">
                        <a href="?filter=all<?php echo $type_filter ? '&type='.$type_filter : ''; ?>" 
                           class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> All
                        </a>
                        <a href="?filter=unread<?php echo $type_filter ? '&type='.$type_filter : ''; ?>" 
                           class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope"></i> Unread
                        </a>
                        <a href="?filter=read<?php echo $type_filter ? '&type='.$type_filter : ''; ?>" 
                           class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope-open"></i> Read
                        </a>
                    </div>

                    <!-- Notifications List -->
                    <div class="notifications-list">
                        <div class="bulk-actions" id="bulkActions">
                            <span id="selectedCount">0 selected</span>
                            <button class="btn btn-sm btn-primary" onclick="bulkMarkRead()">
                                <i class="fas fa-check"></i> Mark Read
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>

                        <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>No notifications</h3>
                            <p>You're all caught up! Check back later for new notifications.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                                <div class="notification-checkbox">
                                    <input type="checkbox" class="notif-checkbox" value="<?php echo $notif['notification_id']; ?>">
                                </div>
                                <div class="notification-icon-lg <?php echo htmlspecialchars($notif['type']); ?>">
                                    <i class="fas <?php 
                                        $icons = [
                                            'info' => 'fa-info-circle',
                                            'success' => 'fa-check-circle',
                                            'warning' => 'fa-exclamation-triangle',
                                            'error' => 'fa-times-circle'
                                        ];
                                        echo $icons[$notif['type']] ?? 'fa-bell';
                                    ?>"></i>
                                </div>
                                <div class="notification-body">
                                    <div class="notification-header">
                                        <h4 class="notification-title-text"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                        <div class="notification-meta">
                                            <span class="notification-type-badge <?php echo htmlspecialchars($notif['type']); ?>">
                                                <?php echo ucfirst($notif['type']); ?>
                                            </span>
                                            <span class="notification-time">
                                                <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="notification-message-text"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <div class="notification-actions-row">
                                        <?php if (!$notif['is_read']): ?>
                                        <button class="notification-action-btn mark-read" onclick="markRead(<?php echo $notif['notification_id']; ?>)">
                                            <i class="fas fa-envelope-open"></i> Mark as Read
                                        </button>
                                        <?php else: ?>
                                        <button class="notification-action-btn mark-unread" onclick="markUnread(<?php echo $notif['notification_id']; ?>)">
                                            <i class="fas fa-envelope"></i> Mark as Unread
                                        </button>
                                        <?php endif; ?>
                                        <button class="notification-action-btn delete" onclick="deleteNotif(<?php echo $notif['notification_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?><?php echo $type_filter ? '&type='.$type_filter : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                        <a href="?page=1&filter=<?php echo $filter; ?><?php echo $type_filter ? '&type='.$type_filter : ''; ?>">1</a>
                        <?php if ($start > 2): ?><span>...</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?><?php echo $type_filter ? '&type='.$type_filter : ''; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span>...</span><?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?>&filter=<?php echo $filter; ?><?php echo $type_filter ? '&type='.$type_filter : ''; ?>">
                            <?php echo $total_pages; ?>
                        </a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?><?php echo $type_filter ? '&type='.$type_filter : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/theme.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });

        // Mark single notification as read
        function markRead(id) {
            fetch('includes/notification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_read&notification_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        // Mark single notification as unread
        function markUnread(id) {
            fetch('includes/notification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_unread&notification_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        // Mark all as read
        function markAllRead() {
            fetch('includes/notification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const markReadBtn = item.querySelector('.mark-read');
                        if (markReadBtn) markReadBtn.remove();
                    });
                    updateBadge();
                }
            });
        }

        // Delete notification
        function deleteNotif(id) {
            if (!confirm('Are you sure you want to delete this notification?')) return;
            
            fetch('includes/notification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&notification_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (item) {
                        item.remove();
                    }
                    updateBadge();
                    // Check if list is empty
                    if (!document.querySelector('.notification-item')) {
                        location.reload();
                    }
                }
            });
        }

        // Update badge count
        function updateBadge() {
            fetch('includes/notification_handler.php?action=get_unread_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.data.unread_count;
                        const badge = document.querySelector('.notifications-title .badge');
                        const navBadge = document.querySelector('.nav-badge');
                        const headerBadge = document.getElementById('notificationBadge');
                        
                        if (count > 0) {
                            if (badge) badge.textContent = count + ' unread';
                            if (navBadge) navBadge.textContent = count;
                            if (headerBadge) {
                                headerBadge.textContent = count > 9 ? '9+' : count;
                                headerBadge.style.display = 'flex';
                            }
                        } else {
                            if (badge) badge.style.display = 'none';
                            if (navBadge) navBadge.style.display = 'none';
                            if (headerBadge) headerBadge.style.display = 'none';
                        }
                    }
                });
        }

        // Bulk selection handling
        const checkboxes = document.querySelectorAll('.notif-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkSelection);
        });

        function updateBulkSelection() {
            const selected = document.querySelectorAll('.notif-checkbox:checked');
            if (selected.length > 0) {
                bulkActions.classList.add('visible');
                selectedCount.textContent = selected.length + ' selected';
            } else {
                bulkActions.classList.remove('visible');
            }
        }

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.notif-checkbox:checked')).map(cb => cb.value);
        }

        function bulkMarkRead() {
            const ids = getSelectedIds();
            ids.forEach(id => markRead(id));
            document.querySelectorAll('.notif-checkbox:checked').forEach(cb => cb.checked = false);
            updateBulkSelection();
        }

        function bulkDelete() {
            if (!confirm('Are you sure you want to delete ' + getSelectedIds().length + ' notifications?')) return;
            const ids = getSelectedIds();
            ids.forEach(id => {
                fetch('includes/notification_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&notification_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                        if (item) item.remove();
                    }
                });
            });
            setTimeout(() => {
                updateBulkSelection();
                updateBadge();
                if (!document.querySelector('.notification-item')) {
                    location.reload();
                }
            }, 500);
        }

        // Search functionality
        document.getElementById('searchNotifications')?.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.notification-item').forEach(item => {
                const title = item.querySelector('.notification-title-text')?.textContent.toLowerCase() || '';
                const message = item.querySelector('.notification-message-text')?.textContent.toLowerCase() || '';
                if (title.includes(query) || message.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
