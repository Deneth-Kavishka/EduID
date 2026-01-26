<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Get success/error messages from redirects
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

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
                
                <?php include 'includes/header_profile.php'; ?>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1rem; padding: 1rem; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; color: #22c55e; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; color: #ef4444; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
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
                    <div class="card" style="text-align: center; padding: 2rem; cursor: pointer;" onclick="openAddStudentModal()">
                        <i class="fas fa-user-plus" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Add New Student</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Register a new student</p>
                    </div>
                    
                    <div class="card" style="text-align: center; padding: 2rem; cursor: pointer;" onclick="openAddTeacherModal()">
                        <i class="fas fa-chalkboard-user" style="font-size: 2.5rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Add New Teacher</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Register a new teacher</p>
                    </div>
                    
                    <div class="card" style="text-align: center; padding: 2rem; cursor: pointer;" onclick="openCreateEventModal()">
                        <i class="fas fa-calendar-plus" style="font-size: 2.5rem; color: var(--warning-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Create Event</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">Schedule a new event</p>
                    </div>
                    
                    <a href="reports.php" class="card" style="text-align: center; padding: 2rem; cursor: pointer;">
                        <i class="fas fa-chart-bar" style="font-size: 2.5rem; color: var(--danger-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.1rem; color: var(--text-primary);">Generate Report</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">View analytics & reports</p>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color: var(--primary-color);"></i> Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form id="addStudentForm" method="POST" action="students.php">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>First Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="first_name" required placeholder="Enter first name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="last_name" required placeholder="Enter last name">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Email <span style="color: #ef4444;">*</span></label>
                            <input type="email" name="email" required placeholder="student@email.com">
                        </div>
                        <div class="form-group">
                            <label>Student ID <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="student_id" required placeholder="e.g., STU2024001">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Grade/Class</label>
                            <input type="text" name="grade" placeholder="e.g., Grade 10">
                        </div>
                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" name="section" placeholder="e.g., A">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="+94 XX XXX XXXX">
                    </div>
                    <div class="form-group">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="password" required placeholder="Create a password" minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Student</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Teacher Modal -->
    <div id="addTeacherModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-chalkboard-user" style="color: var(--success-color);"></i> Add New Teacher</h3>
                <button class="modal-close" onclick="closeModal('addTeacherModal')">&times;</button>
            </div>
            <form id="addTeacherForm" method="POST" action="teachers.php">
                <input type="hidden" name="action" value="add_teacher">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>First Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="first_name" required placeholder="Enter first name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="last_name" required placeholder="Enter last name">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Email <span style="color: #ef4444;">*</span></label>
                            <input type="email" name="email" required placeholder="teacher@email.com">
                        </div>
                        <div class="form-group">
                            <label>Employee ID <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="employee_id" required placeholder="e.g., EMP2024001">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" placeholder="e.g., Mathematics">
                        </div>
                        <div class="form-group">
                            <label>Subject Specialization</label>
                            <input type="text" name="subject" placeholder="e.g., Algebra, Calculus">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" placeholder="+94 XX XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" placeholder="e.g., M.Sc., B.Ed.">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="password" required placeholder="Create a password" minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTeacherModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Event Modal -->
    <div id="createEventModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus" style="color: var(--warning-color);"></i> Create New Event</h3>
                <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
            </div>
            <form id="createEventForm" method="POST" action="events.php">
                <input type="hidden" name="action" value="create_event">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label>Event Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="event_name" required placeholder="Enter event name">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Event Type</label>
                            <select name="event_type">
                                <option value="">Select Type</option>
                                <option value="academic">Academic</option>
                                <option value="sports">Sports</option>
                                <option value="cultural">Cultural</option>
                                <option value="exam">Examination</option>
                                <option value="meeting">Meeting</option>
                                <option value="holiday">Holiday</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Event Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" name="venue" placeholder="e.g., Main Auditorium">
                        </div>
                        <div class="form-group">
                            <label>Max Participants</label>
                            <input type="number" name="max_participants" min="1" placeholder="e.g., 100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Enter event description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Create Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-container {
            background: var(--bg-primary);
            border-radius: 16px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function openAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function openAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function openCreateEventModal() {
            document.getElementById('createEventModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
