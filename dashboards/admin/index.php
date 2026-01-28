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

// Get admin user info
$query = "SELECT username, email, profile_picture FROM users WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <!-- Use vladmandic face-api fork for better compatibility -->
    <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js"></script>
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
                    <h1>Dashboard</h1>
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
                <!-- Welcome Message -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 600; color: var(--text-primary); margin: 0;">
                        <i class="fas fa-hand-wave" style="color: #f59e0b; margin-right: 0.5rem;"></i>
                        Welcome, <?php echo htmlspecialchars($admin['username']); ?>!
                    </h2>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Here's your administration dashboard overview</p>
                </div>
                
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
        <div class="modal-container" style="max-width: 650px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus" style="color: var(--primary-color);"></i> Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form id="addStudentForm" onsubmit="return handleAddStudentSubmit(event)">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-body" style="padding: 1.5rem;">
                    <!-- Step indicator / Tabs -->
                    <div id="addStudentSteps" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
                        <div class="step-item active" data-step="1" onclick="switchToStep(1)" style="flex: 1; text-align: center; padding: 0.5rem; border-radius: 8px; background: rgba(59, 130, 246, 0.1); border: 2px solid var(--primary-color); cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-user"></i> Student Info
                        </div>
                        <div class="step-item" data-step="2" onclick="switchToStep(2)" style="flex: 1; text-align: center; padding: 0.5rem; border-radius: 8px; background: var(--bg-secondary); border: 2px solid var(--border-color); cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-camera"></i> Face Registration
                        </div>
                    </div>
                    
                    <!-- Step 1: Student Info -->
                    <div id="studentInfoStep">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>First Name <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="first_name" id="add_first_name" required placeholder="Enter first name">
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="last_name" id="add_last_name" required placeholder="Enter last name">
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
                    
                    <!-- Step 2: Face Registration (hidden initially) -->
                    <div id="faceRegistrationStep" style="display: none;">
                        <div id="newStudentInfo" style="margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #22c55e, #16a34a); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;" id="newStudentAvatar">ST</div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-primary);" id="newStudentName">Student Name</div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Student created successfully!</div>
                            </div>
                            <i class="fas fa-check-circle" style="color: #22c55e; margin-left: auto; font-size: 1.25rem;"></i>
                        </div>
                        
                        <!-- Face API Status -->
                        <div id="addFaceApiStatus" style="margin-bottom: 1rem; padding: 0.75rem; border-radius: 8px; text-align: center; display: none;">
                            <i class="fas fa-spinner fa-spin"></i> <span id="addFaceApiStatusText">Loading face detection models...</span>
                        </div>
                        
                        <div style="position: relative; width: 100%; max-width: 350px; margin: 0 auto;">
                            <video id="addFaceVideo" style="width: 100%; border-radius: 10px; background: #000;" autoplay playsinline></video>
                            <canvas id="addFaceCanvas" style="display: none;"></canvas>
                            <canvas id="addFaceDetectionCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
                            <div id="addFaceOverlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.7); border-radius: 10px;">
                                <div style="text-align: center; color: white;">
                                    <i class="fas fa-video" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                    <p>Click "Start Camera" to register face</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Face Detection Feedback -->
                        <div id="addFaceDetectionFeedback" style="margin-top: 0.75rem; padding: 0.5rem; border-radius: 8px; text-align: center; font-size: 0.85rem; display: none;">
                        </div>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                                <button type="button" id="addStartCameraBtn" class="btn btn-primary" onclick="startAddFaceCamera()" style="padding: 0.5rem 1rem;">
                                    <i class="fas fa-video"></i> Start Camera
                                </button>
                                <button type="button" id="addCaptureFaceBtn" class="btn btn-primary" onclick="captureAddFace()" style="padding: 0.5rem 1rem; display: none;" disabled>
                                    <i class="fas fa-camera"></i> Capture Face
                                </button>
                                <button type="button" id="addSaveFaceBtn" class="btn" onclick="saveAddFaceData()" style="padding: 0.5rem 1rem; display: none; background: #22c55e; color: white;">
                                    <i class="fas fa-save"></i> Save Face Data
                                </button>
                                <button type="button" id="addRetakeFaceBtn" class="btn btn-secondary" onclick="retakeAddFace()" style="padding: 0.5rem 1rem; display: none;">
                                    <i class="fas fa-redo"></i> Retake
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="button" id="skipFaceBtn" class="btn btn-secondary" onclick="skipFaceRegistration()" style="display: none;">
                        <i class="fas fa-forward"></i> Skip Face Registration
                    </button>
                    <button type="submit" id="addStudentSubmitBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add Student</button>
                    <button type="button" id="finishAddStudentBtn" class="btn btn-primary" onclick="finishAddStudent()" style="display: none;"><i class="fas fa-check"></i> Finish</button>
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
        // Variables for face registration
        let addFaceStream = null;
        let addCapturedImageData = null;
        let addCapturedFaceDescriptor = null;
        let addFaceDetectionInterval = null;
        let addFaceApiModelsLoaded = false;
        let newStudentUserId = null;
        let currentStep = 1;
        
        // Switch between tabs
        function switchToStep(step) {
            currentStep = step;
            
            // Update tab appearance
            document.querySelectorAll('#addStudentSteps .step-item').forEach((tabEl, i) => {
                if (i + 1 === step) {
                    tabEl.style.background = 'rgba(59, 130, 246, 0.1)';
                    tabEl.style.borderColor = 'var(--primary-color)';
                    tabEl.style.opacity = '1';
                } else {
                    tabEl.style.background = 'var(--bg-secondary)';
                    tabEl.style.borderColor = 'var(--border-color)';
                    tabEl.style.opacity = '0.7';
                }
            });
            
            // Show/hide step content
            if (step === 1) {
                document.getElementById('studentInfoStep').style.display = 'block';
                document.getElementById('faceRegistrationStep').style.display = 'none';
                document.getElementById('addStudentSubmitBtn').style.display = 'inline-flex';
                
                // Reset skip button for next use
                const skipBtn = document.getElementById('skipFaceBtn');
                skipBtn.style.display = 'none';
                skipBtn.innerHTML = '<i class="fas fa-forward"></i> Skip Face Registration';
                skipBtn.onclick = skipFaceRegistration;
                
                document.getElementById('finishAddStudentBtn').style.display = 'none';
            } else if (step === 2) {
                document.getElementById('studentInfoStep').style.display = 'none';
                document.getElementById('faceRegistrationStep').style.display = 'block';
                
                const skipBtn = document.getElementById('skipFaceBtn');
                
                if (newStudentUserId) {
                    // Student already created, show skip/finish buttons
                    document.getElementById('addStudentSubmitBtn').style.display = 'none';
                    skipBtn.style.display = 'inline-flex';
                    skipBtn.innerHTML = '<i class="fas fa-forward"></i> Skip Face Registration';
                    skipBtn.onclick = skipFaceRegistration;
                } else {
                    // Preview mode - show message that student needs to be created first
                    document.getElementById('newStudentName').textContent = 'Create student first';
                    document.getElementById('newStudentAvatar').textContent = '?';
                    const checkIcon = document.getElementById('newStudentInfo').querySelector('.fa-check-circle');
                    if (checkIcon) checkIcon.remove();
                    
                    document.getElementById('addStudentSubmitBtn').style.display = 'none';
                    skipBtn.style.display = 'inline-flex';
                    skipBtn.innerHTML = '<i class="fas fa-arrow-left"></i> Back to Student Info';
                    skipBtn.onclick = function() { switchToStep(1); };
                }
                
                // Reset face registration UI
                resetFaceRegistrationUI();
            }
        }
        
        function resetFaceRegistrationUI() {
            stopAddFaceCamera();
            document.getElementById('addFaceOverlay').style.display = 'flex';
            document.getElementById('addStartCameraBtn').style.display = 'inline-flex';
            document.getElementById('addCaptureFaceBtn').style.display = 'none';
            document.getElementById('addSaveFaceBtn').style.display = 'none';
            document.getElementById('addRetakeFaceBtn').style.display = 'none';
            document.getElementById('addFaceApiStatus').style.display = 'none';
            document.getElementById('addFaceDetectionFeedback').style.display = 'none';
            const video = document.getElementById('addFaceVideo');
            const canvas = document.getElementById('addFaceCanvas');
            if (video) video.style.display = 'block';
            if (canvas) canvas.style.display = 'none';
            addCapturedImageData = null;
            addCapturedFaceDescriptor = null;
        }
        
        function openAddStudentModal() {
            // Reset to step 1
            currentStep = 1;
            document.getElementById('studentInfoStep').style.display = 'block';
            document.getElementById('faceRegistrationStep').style.display = 'none';
            document.getElementById('addStudentSubmitBtn').style.display = 'inline-flex';
            document.getElementById('skipFaceBtn').style.display = 'none';
            document.getElementById('finishAddStudentBtn').style.display = 'none';
            document.getElementById('addStudentForm').reset();
            
            // Reset step indicators
            document.querySelectorAll('#addStudentSteps .step-item').forEach((step, i) => {
                if (i === 0) {
                    step.style.background = 'rgba(59, 130, 246, 0.1)';
                    step.style.borderColor = 'var(--primary-color)';
                    step.style.opacity = '1';
                } else {
                    step.style.background = 'var(--bg-secondary)';
                    step.style.borderColor = 'var(--border-color)';
                    step.style.opacity = '0.7';
                }
            });
            
            // Reset skip button
            document.getElementById('skipFaceBtn').textContent = '';
            document.getElementById('skipFaceBtn').innerHTML = '<i class="fas fa-forward"></i> Skip Face Registration';
            document.getElementById('skipFaceBtn').onclick = skipFaceRegistration;
            
            newStudentUserId = null;
            
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
            if (modalId === 'addStudentModal') {
                stopAddFaceCamera();
            }
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Handle add student form submission
        async function handleAddStudentSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = document.getElementById('addStudentSubmitBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'add_student');
                
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    newStudentUserId = result.user_id;
                    
                    // Move to step 2 - Face Registration
                    goToFaceRegistrationStep(formData.get('first_name'), formData.get('last_name'));
                } else {
                    alert(result.message || 'Error adding student');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding student. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function goToFaceRegistrationStep(firstName, lastName) {
            currentStep = 2;
            
            // Update step indicators
            document.querySelectorAll('#addStudentSteps .step-item').forEach((step, i) => {
                if (i === 0) {
                    step.style.background = 'rgba(34, 197, 94, 0.1)';
                    step.style.borderColor = '#22c55e';
                    step.innerHTML = '<i class="fas fa-check"></i> Student Info';
                } else {
                    step.style.background = 'rgba(59, 130, 246, 0.1)';
                    step.style.borderColor = 'var(--primary-color)';
                    step.style.opacity = '1';
                }
            });
            
            // Show student info
            document.getElementById('newStudentName').textContent = `${firstName} ${lastName}`;
            document.getElementById('newStudentAvatar').textContent = (firstName[0] + lastName[0]).toUpperCase();
            
            // Ensure check icon is present
            const infoDiv = document.getElementById('newStudentInfo');
            if (!infoDiv.querySelector('.fa-check-circle')) {
                const checkIcon = document.createElement('i');
                checkIcon.className = 'fas fa-check-circle';
                checkIcon.style.cssText = 'color: #22c55e; margin-left: auto; font-size: 1.25rem;';
                infoDiv.appendChild(checkIcon);
            }
            
            // Switch to face registration step
            document.getElementById('studentInfoStep').style.display = 'none';
            document.getElementById('faceRegistrationStep').style.display = 'block';
            
            // Update buttons - reset skip button properly
            document.getElementById('addStudentSubmitBtn').style.display = 'none';
            const skipBtn = document.getElementById('skipFaceBtn');
            skipBtn.style.display = 'inline-flex';
            skipBtn.innerHTML = '<i class="fas fa-forward"></i> Skip Face Registration';
            skipBtn.onclick = skipFaceRegistration;
            document.getElementById('finishAddStudentBtn').style.display = 'none';
            
            // Reset face registration UI
            resetFaceRegistrationUI();
        }
        
        function skipFaceRegistration() {
            stopAddFaceCamera();
            closeModal('addStudentModal');
            alert('Student added successfully! Face registration skipped.');
            location.reload();
        }
        
        function finishAddStudent() {
            closeModal('addStudentModal');
            location.reload();
        }
        
        // Load face-api.js models
        async function loadAddFaceApiModels() {
            if (addFaceApiModelsLoaded) return true;
            
            const statusDiv = document.getElementById('addFaceApiStatus');
            const statusText = document.getElementById('addFaceApiStatusText');
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(59, 130, 246, 0.1)';
            statusDiv.style.color = '#3b82f6';
            statusText.textContent = 'Loading face detection models...';
            
            try {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model';
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                
                addFaceApiModelsLoaded = true;
                statusDiv.style.background = 'rgba(34, 197, 94, 0.1)';
                statusDiv.style.color = '#22c55e';
                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> <span>Face detection models loaded!</span>';
                
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 2000);
                
                return true;
            } catch (error) {
                console.error('Error loading face-api models:', error);
                statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                statusDiv.style.color = '#ef4444';
                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span>Error loading models. Please refresh.</span>';
                return false;
            }
        }
        
        async function startAddFaceCamera() {
            try {
                // Load face-api models first
                const modelsLoaded = await loadAddFaceApiModels();
                if (!modelsLoaded) {
                    alert('Failed to load face detection models. Please refresh the page.');
                    return;
                }
                
                addFaceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } });
                const video = document.getElementById('addFaceVideo');
                video.srcObject = addFaceStream;
                
                // Wait for video to be ready
                await new Promise(resolve => {
                    video.onloadedmetadata = resolve;
                });
                
                document.getElementById('addFaceOverlay').style.display = 'none';
                document.getElementById('addStartCameraBtn').style.display = 'none';
                document.getElementById('addCaptureFaceBtn').style.display = 'inline-flex';
                
                // Start real-time face detection
                startAddRealTimeFaceDetection();
                
            } catch (err) {
                alert('Unable to access camera. Please ensure camera permissions are granted.');
                console.error('Camera error:', err);
            }
        }
        
        function stopAddFaceCamera() {
            if (addFaceStream) {
                addFaceStream.getTracks().forEach(track => track.stop());
                addFaceStream = null;
            }
            if (addFaceDetectionInterval) {
                clearInterval(addFaceDetectionInterval);
                addFaceDetectionInterval = null;
            }
        }
        
        function startAddRealTimeFaceDetection() {
            const video = document.getElementById('addFaceVideo');
            const canvas = document.getElementById('addFaceDetectionCanvas');
            const feedback = document.getElementById('addFaceDetectionFeedback');
            const captureBtn = document.getElementById('addCaptureFaceBtn');
            
            feedback.style.display = 'block';
            
            addFaceDetectionInterval = setInterval(async () => {
                if (!addFaceStream) return;
                
                try {
                    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks();
                    
                    const ctx = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    if (detection) {
                        // Draw face box
                        const box = detection.detection.box;
                        ctx.strokeStyle = '#22c55e';
                        ctx.lineWidth = 3;
                        ctx.strokeRect(box.x, box.y, box.width, box.height);
                        
                        feedback.style.background = 'rgba(34, 197, 94, 0.1)';
                        feedback.style.color = '#22c55e';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> Face detected! Click Capture when ready.';
                        captureBtn.disabled = false;
                    } else {
                        feedback.style.background = 'rgba(245, 158, 11, 0.1)';
                        feedback.style.color = '#f59e0b';
                        feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No face detected. Please position your face in view.';
                        captureBtn.disabled = true;
                    }
                } catch (error) {
                    console.error('Face detection error:', error);
                }
            }, 200);
        }
        
        async function captureAddFace() {
            const video = document.getElementById('addFaceVideo');
            const canvas = document.getElementById('addFaceCanvas');
            const feedback = document.getElementById('addFaceDetectionFeedback');
            
            // Stop real-time detection
            if (addFaceDetectionInterval) {
                clearInterval(addFaceDetectionInterval);
                addFaceDetectionInterval = null;
            }
            
            feedback.style.background = 'rgba(59, 130, 246, 0.1)';
            feedback.style.color = '#3b82f6';
            feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting face data...';
            
            try {
                // Detect face with full descriptor
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                
                if (!detection) {
                    feedback.style.background = 'rgba(239, 68, 68, 0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.innerHTML = '<i class="fas fa-times-circle"></i> No face detected. Please try again.';
                    startAddRealTimeFaceDetection();
                    return;
                }
                
                // Store face descriptor (128-dimension Float32Array)
                addCapturedFaceDescriptor = Array.from(detection.descriptor);
                
                // Capture image
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                addCapturedImageData = canvas.toDataURL('image/jpeg', 0.9);
                
                // Show captured image
                video.style.display = 'none';
                canvas.style.display = 'block';
                canvas.style.width = '100%';
                canvas.style.borderRadius = '10px';
                document.getElementById('addFaceDetectionCanvas').style.display = 'none';
                
                stopAddFaceCamera();
                
                feedback.style.background = 'rgba(34, 197, 94, 0.1)';
                feedback.style.color = '#22c55e';
                feedback.innerHTML = '<i class="fas fa-check-circle"></i> Face captured successfully! (128-point descriptor extracted)';
                
                document.getElementById('addCaptureFaceBtn').style.display = 'none';
                document.getElementById('addSaveFaceBtn').style.display = 'inline-flex';
                document.getElementById('addRetakeFaceBtn').style.display = 'inline-flex';
                
            } catch (error) {
                console.error('Error capturing face:', error);
                feedback.style.background = 'rgba(239, 68, 68, 0.1)';
                feedback.style.color = '#ef4444';
                feedback.innerHTML = '<i class="fas fa-times-circle"></i> Error capturing face. Please try again.';
                startAddRealTimeFaceDetection();
            }
        }
        
        function retakeAddFace() {
            const video = document.getElementById('addFaceVideo');
            const canvas = document.getElementById('addFaceCanvas');
            
            video.style.display = 'block';
            canvas.style.display = 'none';
            document.getElementById('addFaceDetectionCanvas').style.display = 'block';
            addCapturedImageData = null;
            addCapturedFaceDescriptor = null;
            
            document.getElementById('addSaveFaceBtn').style.display = 'none';
            document.getElementById('addRetakeFaceBtn').style.display = 'none';
            document.getElementById('addCaptureFaceBtn').style.display = 'inline-flex';
            document.getElementById('addCaptureFaceBtn').disabled = true;
            
            startAddFaceCamera();
        }
        
        async function saveAddFaceData() {
            if (!addCapturedImageData || !addCapturedFaceDescriptor || !newStudentUserId) {
                alert('No face data captured. Please capture face first.');
                return;
            }
            
            const btn = document.getElementById('addSaveFaceBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            try {
                const formData = new FormData();
                formData.append('user_id', newStudentUserId);
                formData.append('face_image', addCapturedImageData);
                formData.append('face_descriptor', JSON.stringify(addCapturedFaceDescriptor));
                
                const response = await fetch('save_face_admin.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('addFaceDetectionFeedback').style.background = 'rgba(34, 197, 94, 0.1)';
                    document.getElementById('addFaceDetectionFeedback').style.color = '#22c55e';
                    document.getElementById('addFaceDetectionFeedback').innerHTML = '<i class="fas fa-check-circle"></i> Face data saved successfully!';
                    
                    // Hide skip and save buttons, show finish
                    document.getElementById('skipFaceBtn').style.display = 'none';
                    document.getElementById('addSaveFaceBtn').style.display = 'none';
                    document.getElementById('addRetakeFaceBtn').style.display = 'none';
                    document.getElementById('finishAddStudentBtn').style.display = 'inline-flex';
                    
                    // Update step indicator
                    document.querySelectorAll('#addStudentSteps .step-item')[1].style.background = 'rgba(34, 197, 94, 0.1)';
                    document.querySelectorAll('#addStudentSteps .step-item')[1].style.borderColor = '#22c55e';
                    document.querySelectorAll('#addStudentSteps .step-item')[1].innerHTML = '<i class="fas fa-check"></i> Face Registered';
                } else {
                    alert(result.message || 'Error saving face data');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Face Data';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving face data');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Face Data';
            }
        }
        
        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'addStudentModal') {
                        stopAddFaceCamera();
                    }
                    this.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                stopAddFaceCamera();
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = '';
            }
        });
        
        // Update time display
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('navbarTime');
            const dateElement = document.getElementById('navbarDate');
            
            if (timeElement && dateElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', { hour12: false });
                dateElement.textContent = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
            }
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>
