<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

$parent_id = $_SESSION['parent_id'];
$user_id = $_SESSION['user_id'];

// Get parent details
$query = "SELECT p.*, u.email, u.profile_picture FROM parents p JOIN users u ON p.user_id = u.user_id WHERE p.parent_id = :parent_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    header('Location: notifications.php');
    exit;
}

// Handle delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    header('Location: notifications.php');
    exit;
}

// Handle clear all notifications
if (isset($_GET['clear_all'])) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    header('Location: notifications.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';

// Get notifications
$query = "SELECT * FROM notifications WHERE user_id = :user_id";

if ($filter === 'unread') {
    $query .= " AND is_read = FALSE";
} elseif ($filter === 'read') {
    $query .= " AND is_read = TRUE";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_notifications = 0;
$unread_count = 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread FROM notifications WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_notifications = $stats['total'];
$unread_count = $stats['unread'];

// Group notifications by date
$grouped_notifications = [];
foreach ($notifications as $notification) {
    $date = date('Y-m-d', strtotime($notification['created_at']));
    if (!isset($grouped_notifications[$date])) {
        $grouped_notifications[$date] = [];
    }
    $grouped_notifications[$date][] = $notification;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Parent - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../../assets/images/logo.svg" alt="EduID">
                    <span>EduID</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="index.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="children.php" class="nav-item">
                        <i class="fas fa-children"></i>
                        <span>My Children</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Monitoring</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance History</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Schedule</span>
                    </a>
                    <a href="events.php" class="nav-item">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
                    </a>
                    <a href="notifications.php" class="nav-item active">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                        <?php if ($unread_count > 0): ?>
                        <span style="margin-left: auto; padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; background: #ef4444; color: white;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="../../auth/logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1>Notifications</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Monitoring</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Notifications</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats & Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; gap: 1rem;">
                        <div class="card" style="padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-bell" style="color: #3b82f6;"></i>
                            <div>
                                <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase;">Total</div>
                                <div style="font-weight: 700; color: var(--text-primary);"><?php echo $total_notifications; ?></div>
                            </div>
                        </div>
                        <div class="card" style="padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-envelope" style="color: #ef4444;"></i>
                            <div>
                                <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase;">Unread</div>
                                <div style="font-weight: 700; color: #ef4444;"><?php echo $unread_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($total_notifications > 0): ?>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" style="padding: 0.5rem 1rem; background: #10b981; color: white; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </a>
                        <?php endif; ?>
                        <a href="?clear_all=1" onclick="return confirm('Are you sure you want to delete all notifications?');" style="padding: 0.5rem 1rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                            <i class="fas fa-trash"></i> Clear All
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filter Tabs -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 0.5rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?filter=all" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'all' ? 'background: #3b82f6; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-list"></i> All
                            </a>
                            <a href="?filter=unread" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'unread' ? 'background: #ef4444; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-envelope"></i> Unread
                            </a>
                            <a href="?filter=read" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'read' ? 'background: #10b981; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-envelope-open"></i> Read
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications List -->
                <?php if (empty($grouped_notifications)): ?>
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-bell-slash" style="font-size: 4rem; color: var(--text-tertiary); margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Notifications</h3>
                        <p style="color: var(--text-secondary);">
                            <?php echo $filter === 'unread' ? 'You have no unread notifications.' : ($filter === 'read' ? 'You have no read notifications.' : 'You don\'t have any notifications yet.'); ?>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                
                <?php foreach ($grouped_notifications as $date => $day_notifications): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-tertiary); text-transform: uppercase; margin-bottom: 0.75rem; padding-left: 0.5rem;">
                        <?php
                        $notification_date = strtotime($date);
                        $today = strtotime(date('Y-m-d'));
                        $yesterday = strtotime('-1 day');
                        
                        if ($notification_date === $today) {
                            echo 'Today';
                        } elseif ($notification_date === $yesterday) {
                            echo 'Yesterday';
                        } else {
                            echo date('l, F d, Y', $notification_date);
                        }
                        ?>
                    </div>
                    
                    <div class="card" style="overflow: hidden;">
                        <?php foreach ($day_notifications as $index => $notification): ?>
                        <?php
                        $type_styles = [
                            'info' => ['icon' => 'fa-info-circle', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.1)'],
                            'success' => ['icon' => 'fa-check-circle', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
                            'warning' => ['icon' => 'fa-exclamation-triangle', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
                            'error' => ['icon' => 'fa-times-circle', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)']
                        ];
                        $style = $type_styles[$notification['type']] ?? $type_styles['info'];
                        ?>
                        <div style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem 1.25rem; <?php echo $index < count($day_notifications) - 1 ? 'border-bottom: 1px solid var(--border-color);' : ''; ?> <?php echo !$notification['is_read'] ? 'background: ' . $style['bg'] . ';' : ''; ?>">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: <?php echo $style['bg']; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas <?php echo $style['icon']; ?>" style="color: <?php echo $style['color']; ?>;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 0.25rem;">
                                    <h4 style="margin: 0; font-size: 0.95rem; color: var(--text-primary); font-weight: <?php echo $notification['is_read'] ? '500' : '600'; ?>;">
                                        <?php if (!$notification['is_read']): ?>
                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo $style['color']; ?>; margin-right: 0.5rem;"></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h4>
                                    <span style="font-size: 0.75rem; color: var(--text-tertiary); white-space: nowrap;">
                                        <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                </div>
                                <p style="margin: 0 0 0.75rem; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <div style="display: flex; gap: 0.75rem;">
                                    <?php if (!$notification['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notification['notification_id']; ?>" style="font-size: 0.75rem; color: #10b981; text-decoration: none;">
                                        <i class="fas fa-check"></i> Mark as read
                                    </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notification['notification_id']; ?>" onclick="return confirm('Delete this notification?');" style="font-size: 0.75rem; color: #ef4444; text-decoration: none;">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
