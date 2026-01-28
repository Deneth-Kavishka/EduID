<?php
require_once '../../config/config.php';
checkRole(['student']);

$db = new Database();
$conn = $db->getConnection();

$student_id = $_SESSION['student_id'];

// Get student details
$query = "SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = $_POST['event_id'];
    
    // Check if already registered
    $check_query = "SELECT * FROM event_registrations WHERE event_id = :event_id AND student_id = :student_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':event_id', $event_id);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $insert_query = "INSERT INTO event_registrations (event_id, student_id) VALUES (:event_id, :student_id)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':event_id', $event_id);
        $insert_stmt->bindParam(':student_id', $student_id);
        $insert_stmt->execute();
        $success_message = "Successfully registered for the event!";
    } else {
        $error_message = "You are already registered for this event.";
    }
}

// Handle event unregistration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister_event'])) {
    $event_id = $_POST['event_id'];
    
    $delete_query = "DELETE FROM event_registrations WHERE event_id = :event_id AND student_id = :student_id AND attendance_status = 'registered'";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bindParam(':event_id', $event_id);
    $delete_stmt->bindParam(':student_id', $student_id);
    $delete_stmt->execute();
    
    if ($delete_stmt->rowCount() > 0) {
        $success_message = "Successfully unregistered from the event.";
    } else {
        $error_message = "Unable to unregister. You may have already attended this event.";
    }
}

// Get upcoming events (all available events)
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registered_count,
          (SELECT registration_id FROM event_registrations WHERE event_id = e.event_id AND student_id = :student_id) as is_registered
          FROM events e 
          WHERE e.event_date >= CURDATE() AND e.status IN ('upcoming', 'ongoing')
          ORDER BY e.event_date ASC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get my registered events
$query = "SELECT e.*, er.attendance_status, er.check_in_time, er.registration_date
          FROM events e 
          JOIN event_registrations er ON e.event_id = er.event_id 
          WHERE er.student_id = :student_id 
          ORDER BY e.event_date DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$my_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event statistics
$query = "SELECT 
          COUNT(*) as total_registered,
          SUM(CASE WHEN er.attendance_status = 'attended' THEN 1 ELSE 0 END) as attended,
          SUM(CASE WHEN e.event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming
          FROM event_registrations er
          JOIN events e ON er.event_id = e.event_id
          WHERE er.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$event_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .event-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.total { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-icon.upcoming { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-icon.attended { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: var(--primary-color);
        }
        
        .tab.active {
            color: var(--primary-color);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .event-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .event-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .event-banner {
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .event-banner i {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.3);
        }
        
        .event-date-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .event-date-badge .day {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }
        
        .event-date-badge .month {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .event-status-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .event-status-badge.upcoming { background: rgba(245, 158, 11, 0.9); color: white; }
        .event-status-badge.ongoing { background: rgba(16, 185, 129, 0.9); color: white; }
        .event-status-badge.completed { background: rgba(107, 114, 128, 0.9); color: white; }
        .event-status-badge.registered { background: rgba(59, 130, 246, 0.9); color: white; }
        
        .event-content {
            padding: 1.5rem;
        }
        
        .event-type {
            font-size: 0.75rem;
            color: var(--primary-color);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .event-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }
        
        .event-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .event-meta-item i {
            color: var(--primary-color);
        }
        
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .participants-info {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .participants-info strong {
            color: var(--text-primary);
        }
        
        .btn-register {
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-register.register {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-register.register:hover {
            background: var(--primary-dark);
        }
        
        .btn-register.registered {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .btn-register.unregister {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .btn-register.unregister:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .attendance-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .attendance-badge.registered {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .attendance-badge.attended {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .attendance-badge.absent {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>
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
                    <a href="qr-code.php" class="nav-item">
                        <i class="fas fa-qrcode"></i>
                        <span>My QR Code</span>
                    </a>
                    <a href="face-registration.php" class="nav-item">
                        <i class="fas fa-face-smile"></i>
                        <span>Face Registration</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Academic</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Attendance</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exams</span>
                    </a>
                    <a href="events.php" class="nav-item active">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
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
                        <span>Events</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($success_message)): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Event Stats -->
                <div class="event-stats">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-ticket"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $event_stats['total_registered'] ?? 0; ?></h3>
                            <p>Total Registrations</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon upcoming">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $event_stats['upcoming'] ?? 0; ?></h3>
                            <p>Upcoming Events</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon attended">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $event_stats['attended'] ?? 0; ?></h3>
                            <p>Events Attended</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('browse')">
                        <i class="fas fa-search"></i> Browse Events
                    </button>
                    <button class="tab" onclick="switchTab('my')">
                        <i class="fas fa-bookmark"></i> My Events
                    </button>
                </div>
                
                <!-- Browse Events Tab -->
                <div id="browseTab" class="tab-content active">
                    <?php if (count($upcoming_events) > 0): ?>
                        <div class="events-grid">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-card">
                                    <div class="event-banner">
                                        <?php
                                        $icons = [
                                            'Sports' => 'fa-futbol',
                                            'Cultural' => 'fa-masks-theater',
                                            'Academic' => 'fa-graduation-cap',
                                            'Workshop' => 'fa-tools',
                                            'Seminar' => 'fa-chalkboard-teacher'
                                        ];
                                        $icon = $icons[$event['event_type']] ?? 'fa-calendar-star';
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        
                                        <div class="event-date-badge">
                                            <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                            <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                        </div>
                                        
                                        <?php if ($event['is_registered']): ?>
                                            <span class="event-status-badge registered">Registered</span>
                                        <?php else: ?>
                                            <span class="event-status-badge <?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-content">
                                        <div class="event-type"><?php echo htmlspecialchars($event['event_type'] ?? 'Event'); ?></div>
                                        <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                        <div class="event-description">
                                            <?php echo htmlspecialchars($event['description'] ?? 'No description available.'); ?>
                                        </div>
                                        
                                        <div class="event-meta">
                                            <div class="event-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span>
                                                    <?php 
                                                    echo $event['start_time'] ? date('h:i A', strtotime($event['start_time'])) : 'TBA';
                                                    if ($event['end_time']) echo ' - ' . date('h:i A', strtotime($event['end_time']));
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($event['venue'] ?? 'TBA'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-footer">
                                            <div class="participants-info">
                                                <strong><?php echo $event['registered_count']; ?></strong>
                                                <?php if ($event['max_participants']): ?>
                                                    / <?php echo $event['max_participants']; ?>
                                                <?php endif; ?>
                                                registered
                                            </div>
                                            
                                            <?php if ($event['is_registered']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" name="unregister_event" class="btn-register unregister">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php elseif (!$event['max_participants'] || $event['registered_count'] < $event['max_participants']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" name="register_event" class="btn-register register">
                                                        <i class="fas fa-plus"></i> Register
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn-register" style="background: var(--bg-secondary); color: var(--text-tertiary);">Full</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-xmark"></i>
                            <h3>No Upcoming Events</h3>
                            <p>There are no upcoming events at the moment. Check back later!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- My Events Tab -->
                <div id="myTab" class="tab-content">
                    <?php if (count($my_events) > 0): ?>
                        <div class="events-grid">
                            <?php foreach ($my_events as $event): ?>
                                <div class="event-card">
                                    <div class="event-banner" style="background: linear-gradient(135deg, <?php echo $event['event_date'] >= date('Y-m-d') ? '#3b82f6, #8b5cf6' : '#6b7280, #374151'; ?>);">
                                        <?php
                                        $icons = [
                                            'Sports' => 'fa-futbol',
                                            'Cultural' => 'fa-masks-theater',
                                            'Academic' => 'fa-graduation-cap',
                                            'Workshop' => 'fa-tools',
                                            'Seminar' => 'fa-chalkboard-teacher'
                                        ];
                                        $icon = $icons[$event['event_type']] ?? 'fa-calendar-star';
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        
                                        <div class="event-date-badge">
                                            <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                            <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                        </div>
                                        
                                        <span class="attendance-badge <?php echo $event['attendance_status']; ?>" style="position: absolute; top: 1rem; left: 1rem;">
                                            <?php echo ucfirst($event['attendance_status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="event-content">
                                        <div class="event-type"><?php echo htmlspecialchars($event['event_type'] ?? 'Event'); ?></div>
                                        <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                        <div class="event-description">
                                            <?php echo htmlspecialchars($event['description'] ?? 'No description available.'); ?>
                                        </div>
                                        
                                        <div class="event-meta">
                                            <div class="event-meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($event['venue'] ?? 'TBA'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-footer">
                                            <div class="participants-info">
                                                Registered: <?php echo date('M d, Y', strtotime($event['registration_date'])); ?>
                                            </div>
                                            
                                            <?php if ($event['attendance_status'] === 'attended'): ?>
                                                <span class="attendance-badge attended">
                                                    <i class="fas fa-check"></i> Attended
                                                </span>
                                            <?php elseif ($event['event_date'] >= date('Y-m-d')): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    <button type="submit" name="unregister_event" class="btn-register unregister">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="attendance-badge absent">
                                                    <i class="fas fa-times"></i> Missed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bookmark"></i>
                            <h3>No Registered Events</h3>
                            <p>You haven't registered for any events yet. Browse available events to get started!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'browse') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('browseTab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('myTab').classList.add('active');
            }
        }
        
        // Sidebar scroll position preservation
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar-nav');
            const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos && sidebar) {
                sidebar.scrollTop = parseInt(savedScrollPos);
            }
            
            if (sidebar) {
                sidebar.addEventListener('scroll', function() {
                    sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
                });
            }
        });
    </script>
</body>
</html>
