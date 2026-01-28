<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

$parent_id = $_SESSION['parent_id'];

// Get parent details
$query = "SELECT p.*, u.email, u.profile_picture FROM parents p JOIN users u ON p.user_id = u.user_id WHERE p.parent_id = :parent_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Get children for event registrations
$query = "SELECT s.student_id, s.first_name, s.last_name
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.parent_id = :parent_id AND u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
$child_ids = array_column($children, 'student_id');

// Filter
$filter = $_GET['filter'] ?? 'upcoming';

// Get events
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.event_id) as total_registrations";

if (!empty($child_ids)) {
    $query .= ", (SELECT GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') 
                 FROM event_registrations er 
                 JOIN students s ON er.student_id = s.student_id 
                 WHERE er.event_id = e.event_id AND er.student_id IN (" . implode(',', $child_ids) . ")) as registered_children";
}

$query .= " FROM events e WHERE 1=1";

if ($filter === 'upcoming') {
    $query .= " AND e.status = 'upcoming' AND e.event_date >= CURDATE()";
} elseif ($filter === 'ongoing') {
    $query .= " AND e.status = 'ongoing'";
} elseif ($filter === 'completed') {
    $query .= " AND e.status = 'completed'";
} elseif ($filter === 'registered' && !empty($child_ids)) {
    $query .= " AND e.event_id IN (SELECT event_id FROM event_registrations WHERE student_id IN (" . implode(',', $child_ids) . "))";
}

$query .= " ORDER BY e.event_date " . ($filter === 'completed' ? 'DESC' : 'ASC');

$stmt = $conn->prepare($query);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$upcoming_events = 0;
$registered_events = 0;
$today_events = 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()");
$stmt->execute();
$upcoming_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE event_date = CURDATE()");
$stmt->execute();
$today_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT event_id) as count FROM event_registrations WHERE student_id IN ($placeholders)");
    $stmt->execute($child_ids);
    $registered_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Parent - EduID</title>
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
                    <a href="events.php" class="nav-item active">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
                    </a>
                    <a href="notifications.php" class="nav-item">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
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
                    <h1>Events</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Monitoring</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Events</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-alt" style="color: #3b82f6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Upcoming</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $upcoming_events; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-clock" style="color: #f59e0b; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Today</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $today_events; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-check" style="color: #10b981; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Registered</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $registered_events; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Tabs -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 0.5rem;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="?filter=upcoming" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'upcoming' ? 'background: #3b82f6; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-calendar-alt"></i> Upcoming
                            </a>
                            <a href="?filter=ongoing" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'ongoing' ? 'background: #f59e0b; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-play-circle"></i> Ongoing
                            </a>
                            <a href="?filter=completed" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'completed' ? 'background: #10b981; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-check-circle"></i> Completed
                            </a>
                            <a href="?filter=registered" style="padding: 0.6rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; <?php echo $filter === 'registered' ? 'background: #8b5cf6; color: white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'; ?>">
                                <i class="fas fa-user-check"></i> My Registered
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Events Grid -->
                <?php if (empty($events)): ?>
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-calendar-xmark" style="font-size: 4rem; color: var(--text-tertiary); margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Events Found</h3>
                        <p style="color: var(--text-secondary);">There are no <?php echo $filter; ?> events at the moment.</p>
                    </div>
                </div>
                <?php else: ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($events as $event): ?>
                    <?php
                    $event_date = strtotime($event['event_date']);
                    $is_today = date('Y-m-d', $event_date) === date('Y-m-d');
                    $is_registered = !empty($event['registered_children']);
                    
                    $type_colors = [
                        'academic' => '#3b82f6',
                        'sports' => '#10b981',
                        'cultural' => '#8b5cf6',
                        'workshop' => '#f59e0b',
                        'ceremony' => '#ec4899'
                    ];
                    $event_color = $type_colors[strtolower($event['event_type'] ?? '')] ?? '#6b7280';
                    ?>
                    <div class="card" style="overflow: hidden; <?php echo $is_registered ? 'border: 2px solid #10b981;' : ''; ?>">
                        <div style="height: 8px; background: <?php echo $event_color; ?>;"></div>
                        <div class="card-body" style="padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="margin: 0 0 0.5rem; color: var(--text-primary); font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($event['event_name']); ?>
                                    </h3>
                                    <span style="padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600; background: <?php echo $event_color; ?>20; color: <?php echo $event_color; ?>;">
                                        <?php echo htmlspecialchars($event['event_type'] ?? 'General'); ?>
                                    </span>
                                </div>
                                <?php if ($is_today): ?>
                                <span style="padding: 0.3rem 0.6rem; border-radius: 8px; font-size: 0.7rem; font-weight: 600; background: rgba(245, 158, 11, 0.1); color: #f59e0b; animation: pulse 2s infinite;">
                                    TODAY
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: grid; gap: 0.5rem; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-calendar" style="width: 16px; color: <?php echo $event_color; ?>;"></i>
                                    <?php echo date('l, F d, Y', $event_date); ?>
                                </div>
                                <?php if ($event['start_time']): ?>
                                <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-clock" style="width: 16px; color: <?php echo $event_color; ?>;"></i>
                                    <?php echo date('h:i A', strtotime($event['start_time'])); ?>
                                    <?php if ($event['end_time']): ?>
                                    - <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['venue']): ?>
                                <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-map-marker-alt" style="width: 16px; color: <?php echo $event_color; ?>;"></i>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['description']): ?>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.5;">
                                <?php echo htmlspecialchars(substr($event['description'], 0, 120)); ?>
                                <?php echo strlen($event['description']) > 120 ? '...' : ''; ?>
                            </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                <div style="font-size: 0.8rem; color: var(--text-tertiary);">
                                    <i class="fas fa-users"></i> <?php echo $event['total_registrations']; ?><?php echo $event['max_participants'] ? '/' . $event['max_participants'] : ''; ?> registered
                                </div>
                                <?php if ($is_registered): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <i class="fas fa-check"></i> <?php echo htmlspecialchars($event['registered_children']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</body>
</html>
