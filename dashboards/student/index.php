<?php
require_once '../../config/config.php';
checkRole(['student']);

$db = new Database();
$conn = $db->getConnection();

$student_id = $_SESSION['student_id'];

// Get student details
$query = "SELECT s.*, u.email, u.username, u.profile_picture 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance statistics
$query = "SELECT 
          COUNT(*) as total_days,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
          FROM attendance 
          WHERE student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate attendance percentage
$attendance_percentage = $attendance_stats['total_days'] > 0 
    ? round(($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100, 1) 
    : 0;

// Get recent attendance
$query = "SELECT * FROM attendance WHERE student_id = :student_id ORDER BY date DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get registered events
$query = "SELECT e.*, er.attendance_status, er.check_in_time 
          FROM events e 
          JOIN event_registrations er ON e.event_id = er.event_id 
          WHERE er.student_id = :student_id 
          ORDER BY e.event_date DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$registered_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming exams
$query = "SELECT * FROM exam_entries 
          WHERE student_id = :student_id AND exam_date >= CURDATE() 
          ORDER BY exam_date ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduID</title>
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
                    <a href="events.php" class="nav-item">
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
                    <h1>Welcome, <?php echo htmlspecialchars($student['first_name']); ?>!</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Dashboard</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Student Info Card -->
                <div class="card mb-3" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </h2>
                            <p style="opacity: 0.95; font-size: 1.1rem;">
                                <i class="fas fa-id-card"></i> Student ID: <?php echo htmlspecialchars($student['student_number']); ?>
                            </p>
                            <p style="opacity: 0.95; font-size: 1.1rem;">
                                <i class="fas fa-graduation-cap"></i> Grade: <?php echo htmlspecialchars($student['grade']); ?> - <?php echo htmlspecialchars($student['class_section']); ?>
                            </p>
                        </div>
                        <div style="text-align: center;">
                            <a href="qr-code.php" style="background: white; color: var(--primary-color); padding: 1rem 2rem; border-radius: 50px; font-weight: 600; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                <i class="fas fa-qrcode"></i> View QR Code
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_percentage; ?>%</h3>
                            <p>Attendance Rate</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['present_days']; ?></h3>
                            <p>Days Present</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['late_days']; ?></h3>
                            <p>Times Late</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-calendar-xmark"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['absent_days']; ?></h3>
                            <p>Days Absent</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Attendance & Upcoming Exams -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem;">
                    <!-- Recent Attendance -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Attendance</h3>
                            <a href="attendance.php" style="color: var(--primary-color); font-size: 0.875rem;">View All</a>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_attendance)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: var(--text-tertiary);">No attendance records</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_attendance as $att): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                                                <td><?php echo $att['check_in_time'] ?: '-'; ?></td>
                                                <td>
                                                    <?php 
                                                    $status_colors = [
                                                        'present' => 'success',
                                                        'absent' => 'danger',
                                                        'late' => 'warning',
                                                        'excused' => 'info'
                                                    ];
                                                    $color = $status_colors[$att['status']] ?? 'info';
                                                    ?>
                                                    <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;
                                                                background: rgba(var(--<?php echo $color; ?>-color-rgb, 37, 99, 235), 0.1); 
                                                                color: var(--<?php echo $color; ?>-color);">
                                                        <?php echo ucfirst($att['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Upcoming Exams -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Exams</h3>
                            <a href="exams.php" style="color: var(--primary-color); font-size: 0.875rem;">View All</a>
                        </div>
                        <div>
                            <?php if (empty($upcoming_exams)): ?>
                                <p style="text-align: center; color: var(--text-tertiary); padding: 2rem;">No upcoming exams</p>
                            <?php else: ?>
                                <?php foreach ($upcoming_exams as $exam): ?>
                                    <div style="padding: 1rem; border-bottom: 1px solid var(--border-light);">
                                        <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($exam['exam_name']); ?>
                                        </h4>
                                        <p style="font-size: 0.8rem; color: var(--text-tertiary); margin-bottom: 0.5rem;">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?>
                                        </p>
                                        <p style="font-size: 0.85rem; color: var(--text-secondary);">
                                            <i class="fas fa-location-dot"></i> Hall: <?php echo htmlspecialchars($exam['exam_hall'] ?: 'TBA'); ?> | 
                                            <i class="fas fa-chair"></i> Seat: <?php echo htmlspecialchars($exam['seat_number'] ?: 'TBA'); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Registered Events -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">My Events</h3>
                        <a href="events.php" style="color: var(--primary-color); font-size: 0.875rem;">Browse Events</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Check-in Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registered_events)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-tertiary);">No registered events</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registered_events as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td><?php echo $event['check_in_time'] ? date('H:i', strtotime($event['check_in_time'])) : '-'; ?></td>
                                            <td>
                                                <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;
                                                            background: rgba(37, 99, 235, 0.1); color: var(--primary-color);">
                                                    <?php echo ucfirst($event['attendance_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="mt-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="qr-code.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-qrcode" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">View QR Code</h3>
                    </a>
                    
                    <a href="face-registration.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-face-smile" style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Register Face</h3>
                    </a>
                    
                    <a href="attendance.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">View Attendance</h3>
                    </a>
                    
                    <a href="profile.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-user-edit" style="font-size: 2rem; color: var(--danger-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Edit Profile</h3>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Sidebar scroll position preservation
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar-nav');
            const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos && sidebar) {
                sidebar.scrollTop = parseInt(savedScrollPos);
            }
            
            const activeItem = document.querySelector('.nav-item.active');
            if (activeItem && sidebar) {
                const sidebarRect = sidebar.getBoundingClientRect();
                const itemRect = activeItem.getBoundingClientRect();
                if (itemRect.top < sidebarRect.top || itemRect.bottom > sidebarRect.bottom) {
                    activeItem.scrollIntoView({ block: 'center', behavior: 'auto' });
                }
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
