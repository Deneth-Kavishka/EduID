<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total students
$query = "SELECT COUNT(*) as total FROM students WHERE user_id IN (SELECT user_id FROM users WHERE status = 'active')";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total teachers
$query = "SELECT COUNT(*) as total FROM teachers WHERE user_id IN (SELECT user_id FROM users WHERE status = 'active')";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_teachers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total parents
$query = "SELECT COUNT(*) as total FROM parents";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_parents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's attendance
$query = "SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'present'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['today_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent activities
$query = "SELECT al.*, u.username, u.user_role 
          FROM access_logs al 
          JOIN users u ON al.user_id = u.user_id 
          ORDER BY al.access_time DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Upcoming events
$query = "SELECT * FROM events WHERE event_date >= CURDATE() AND status = 'upcoming' ORDER BY event_date ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance summary for chart
$query = "SELECT date, 
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
          FROM attendance 
          WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          GROUP BY date
          ORDER BY date ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EduID</title>
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
                    <a href="index.php" class="nav-item active">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                    <a href="students.php" class="nav-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Students</span>
                    </a>
                    <a href="teachers.php" class="nav-item">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Teachers</span>
                    </a>
                    <a href="parents.php" class="nav-item">
                        <i class="fas fa-users-between-lines"></i>
                        <span>Parents</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Attendance</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Class Attendance</span>
                    </a>
                    <a href="face_attendance.php" class="nav-item">
                        <i class="fas fa-camera"></i>
                        <span>Face Recognition</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Examinations</div>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Management</span>
                    </a>
                    <a href="exam_halls.php" class="nav-item">
                        <i class="fas fa-door-open"></i>
                        <span>Exam Halls</span>
                    </a>
                    <a href="exam_attendance.php" class="nav-item">
                        <i class="fas fa-user-check"></i>
                        <span>Exam Attendance</span>
                    </a>
                    <a href="exam_eligibility.php" class="nav-item">
                        <i class="fas fa-check-double"></i>
                        <span>Eligibility Check</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Reports & Settings</div>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="logs.php" class="nav-item">
                        <i class="fas fa-list"></i>
                        <span>Access Logs</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
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
                    <h1>Dashboard Overview</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Dashboard</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggleTop" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    
                    <div class="user-menu">
                        <img src="../../assets/images/default-avatar.png" alt="Admin" class="user-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><circle cx=%2212%22 cy=%228%22 r=%224%22 fill=%22%23cbd5e1%22/><path d=%22M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z%22 fill=%22%23cbd5e1%22/></svg>'">
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_teachers']; ?></h3>
                            <p>Total Teachers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_parents']; ?></h3>
                            <p>Total Parents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['today_present']; ?></h3>
                            <p>Today's Attendance</p>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Tables Row -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 2rem;">
                    <!-- Attendance Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Weekly Attendance Overview</h3>
                            <button class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_data as $data): ?>
                                        <?php 
                                            $total = $data['present'] + $data['absent'] + $data['late'];
                                            $rate = $total > 0 ? round(($data['present'] / $total) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($data['date'])); ?></td>
                                            <td><span style="color: var(--success-color); font-weight: 600;"><?php echo $data['present']; ?></span></td>
                                            <td><span style="color: var(--danger-color); font-weight: 600;"><?php echo $data['absent']; ?></span></td>
                                            <td><span style="color: var(--warning-color); font-weight: 600;"><?php echo $data['late']; ?></span></td>
                                            <td><strong><?php echo $rate; ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Upcoming Events -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Events</h3>
                            <a href="events.php" style="color: var(--primary-color); font-size: 0.875rem;">View All</a>
                        </div>
                        <div>
                            <?php if (empty($upcoming_events)): ?>
                                <p style="text-align: center; color: var(--text-tertiary); padding: 2rem;">No upcoming events</p>
                            <?php else: ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div style="padding: 1rem; border-bottom: 1px solid var(--border-light); cursor: pointer;" 
                                         onmouseover="this.style.background='var(--bg-hover)'" 
                                         onmouseout="this.style.background='transparent'">
                                        <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($event['event_name']); ?>
                                        </h4>
                                        <p style="font-size: 0.8rem; color: var(--text-tertiary); margin-bottom: 0.5rem;">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </p>
                                        <p style="font-size: 0.85rem; color: var(--text-secondary);">
                                            <i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activities</h3>
                        <a href="logs.php" style="color: var(--primary-color); font-size: 0.875rem;">View All Logs</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Status</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><?php echo date('M d, H:i', strtotime($activity['access_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td>
                                            <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;
                                                        background: rgba(37, 99, 235, 0.1); color: var(--primary-color);">
                                                <?php echo ucfirst($activity['user_role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $activity['access_type'])); ?></td>
                                        <td>
                                            <?php if ($activity['status'] === 'success'): ?>
                                                <span style="color: var(--success-color);"><i class="fas fa-check-circle"></i> Success</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color);"><i class="fas fa-times-circle"></i> Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="mt-3" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <a href="users.php?action=add_student" class="card" style="text-align: center; padding: 2rem; cursor: pointer;">
                        <i class="fas fa-user-plus" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Add New Student</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Register a new student</p>
                    </a>
                    
                    <a href="users.php?action=add_teacher" class="card" style="text-align: center; padding: 2rem; cursor: pointer;">
                        <i class="fas fa-chalkboard-user" style="font-size: 2.5rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Add New Teacher</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Register a new teacher</p>
                    </a>
                    
                    <a href="events.php?action=create" class="card" style="text-align: center; padding: 2rem; cursor: pointer;">
                        <i class="fas fa-calendar-plus" style="font-size: 2.5rem; color: var(--warning-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Create Event</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Schedule a new event</p>
                    </a>
                    
                    <a href="reports.php" class="card" style="text-align: center; padding: 2rem; cursor: pointer;">
                        <i class="fas fa-chart-bar" style="font-size: 2.5rem; color: var(--danger-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Generate Report</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">View analytics & reports</p>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
