<?php
require_once '../../config/config.php';
checkRole(['teacher']);

$db = new Database();
$conn = $db->getConnection();

$teacher_id = $_SESSION['teacher_id'];

// Get teacher details
$query = "SELECT t.*, u.email FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE t.teacher_id = :teacher_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's attendance statistics
$query = "SELECT COUNT(DISTINCT a.student_id) as total_marked 
          FROM attendance a 
          WHERE a.date = CURDATE() AND a.verified_by = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total students
$query = "SELECT COUNT(*) as total FROM students WHERE user_id IN (SELECT user_id FROM users WHERE status = 'active')";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get upcoming events
$query = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent verification activities
$query = "SELECT a.*, s.first_name, s.last_name, s.student_number 
          FROM attendance a 
          JOIN students s ON a.student_id = s.student_id 
          WHERE a.verified_by = :user_id 
          ORDER BY a.created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recent_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - EduID</title>
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
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Verification</div>
                    <a href="qr-scanner.php" class="nav-item">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Scanner</span>
                    </a>
                    <a href="face-verification.php" class="nav-item">
                        <i class="fas fa-face-smile"></i>
                        <span>Face Verification</span>
                    </a>
                    <a href="mark-attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Mark Attendance</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="students.php" class="nav-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>My Students</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Verification</span>
                    </a>
                    <a href="events.php" class="nav-item">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
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
                    <h1>Dashboard</h1>
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
                <!-- Welcome Message -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 600; color: var(--text-primary); margin: 0;">
                        <i class="fas fa-hand-wave" style="color: #f59e0b; margin-right: 0.5rem;"></i>
                        Welcome, <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>!
                    </h2>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Here's your teaching dashboard overview</p>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $total_students; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $today_stats['total_marked']; ?></h3>
                            <p>Verified Today</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-days"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo count($upcoming_events); ?></h3>
                            <p>Upcoming Events</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo count($recent_verifications); ?></h3>
                            <p>Recent Activity</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="mt-3" style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <a href="qr-scanner.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                        <i class="fas fa-qrcode" style="font-size: 1rem; color: var(--primary-color);"></i>
                        <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">Scan QR Code</span>
                    </a>
                    
                    <a href="face-verification.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                        <i class="fas fa-face-smile" style="font-size: 1rem; color: var(--success-color);"></i>
                        <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">Face Verification</span>
                    </a>
                    
                    <a href="mark-attendance.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                        <i class="fas fa-calendar-check" style="font-size: 1rem; color: var(--warning-color);"></i>
                        <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">Mark Attendance</span>
                    </a>
                    
                    <a href="reports.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                        <i class="fas fa-chart-bar" style="font-size: 1rem; color: var(--danger-color);"></i>
                        <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">View Reports</span>
                    </a>
                </div>
                
                <!-- Recent Verifications & Upcoming Events -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 2rem;">
                    <!-- Recent Verifications -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Verifications</h3>
                            <a href="students.php" style="color: var(--primary-color); font-size: 0.875rem;">View All</a>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_verifications)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-tertiary);">No verifications yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_verifications as $verification): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($verification['student_number']); ?></td>
                                                <td><?php echo date('M d', strtotime($verification['date'])); ?></td>
                                                <td><?php echo $verification['check_in_time']; ?></td>
                                                <td>
                                                    <?php 
                                                    $method_icons = [
                                                        'qr_code' => 'fa-qrcode',
                                                        'face_recognition' => 'fa-face-smile',
                                                        'manual' => 'fa-hand'
                                                    ];
                                                    $icon = $method_icons[$verification['verification_method']] ?? 'fa-check';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </td>
                                                <td>
                                                    <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;
                                                                background: rgba(16, 185, 129, 0.1); color: var(--success-color);">
                                                        <?php echo ucfirst($verification['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                    <div style="padding: 1rem; border-bottom: 1px solid var(--border-light);">
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
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
<script>
// Preserve sidebar scroll position and ensure active item is visible
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar-nav');
    const activeItem = document.querySelector('.nav-item.active');
    
    if (sidebar && activeItem) {
        setTimeout(() => {
            activeItem.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 100);
    }
    
    // Universal time update function for navbar and stats cards
    function updateAllTimeDisplays() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit', year: 'numeric' });
        
        // Update navbar time
        const navbarTime = document.getElementById('navbarTime');
        const navbarDate = document.getElementById('navbarDate');
        if (navbarTime && navbarDate) {
            navbarTime.textContent = timeStr;
            navbarDate.textContent = dateStr;
        }
        
        // Update stats card time if exists
        const currentTime = document.getElementById('currentTime');
        const currentDate = document.getElementById('currentDate');
        if (currentTime && currentDate) {
            currentTime.textContent = timeStr;
            currentDate.textContent = dateStr;
        }
    }
    
    // Update time every second
    setInterval(updateAllTimeDisplays, 1000);
    updateAllTimeDisplays(); // Initial update
});
</script>
</html>
