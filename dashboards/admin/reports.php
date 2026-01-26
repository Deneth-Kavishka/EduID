<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

// Get report type from query
$report_type = $_GET['type'] ?? 'overview';
$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? $today;

// Get unique grades (with proper numeric ordering)
$query = "SELECT DISTINCT s.grade FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE u.status = 'active' 
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Overview Statistics
$stats = [];

// Total Students
$query = "SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_students'] = $stmt->fetchColumn();

// Total Teachers
$query = "SELECT COUNT(*) FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_teachers'] = $stmt->fetchColumn();

// Total Parents
$query = "SELECT COUNT(*) FROM parents p JOIN users u ON p.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_parents'] = $stmt->fetchColumn();

// Today's Attendance Rate
$query = "SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
          FROM attendance WHERE date = :today";
$stmt = $conn->prepare($query);
$stmt->bindParam(':today', $today);
$stmt->execute();
$today_att = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['today_attendance_rate'] = $today_att['total'] > 0 
    ? round(($today_att['present'] / $today_att['total']) * 100, 1) 
    : 0;

// This Month's Average Attendance
$query = "SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
          FROM attendance WHERE date LIKE :month";
$stmt = $conn->prepare($query);
$month_pattern = $current_month . '%';
$stmt->bindParam(':month', $month_pattern);
$stmt->execute();
$month_att = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['month_attendance_rate'] = $month_att['total'] > 0 
    ? round(($month_att['present'] / $month_att['total']) * 100, 1) 
    : 0;

// Total Exams This Month
$query = "SELECT COUNT(*) FROM exams WHERE exam_date LIKE :month";
$stmt = $conn->prepare($query);
$stmt->bindParam(':month', $month_pattern);
$stmt->execute();
$stats['month_exams'] = $stmt->fetchColumn();

// Students with Low Attendance (< 75%)
$query = "SELECT COUNT(DISTINCT s.student_id) 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id
          JOIN attendance a ON s.student_id = a.student_id 
          WHERE u.status = 'active' 
          AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          GROUP BY s.student_id
          HAVING (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) < 75";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['low_attendance_students'] = $stmt->rowCount();

// Attendance Trend (Last 7 Days)
$query = "SELECT 
          date,
          COUNT(*) as total,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
          FROM attendance 
          WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          GROUP BY date
          ORDER BY date";
$stmt = $conn->prepare($query);
$stmt->execute();
$attendance_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grade-wise Student Distribution
$query = "SELECT s.grade, COUNT(*) as count 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE u.status = 'active' 
          GROUP BY s.grade 
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grade_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grade-wise Attendance Summary
$query = "SELECT 
          s.grade,
          COUNT(DISTINCT a.attendance_id) as total_records,
          SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
          SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
          SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
          FROM students s 
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date BETWEEN :from AND :to
          WHERE u.status = 'active'
          GROUP BY s.grade 
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade";
$stmt = $conn->prepare($query);
$stmt->bindParam(':from', $date_from);
$stmt->bindParam(':to', $date_to);
$stmt->execute();
$grade_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verification Method Distribution
$query = "SELECT verification_method, COUNT(*) as count 
          FROM attendance 
          WHERE date BETWEEN :from AND :to 
          GROUP BY verification_method";
$stmt = $conn->prepare($query);
$stmt->bindParam(':from', $date_from);
$stmt->bindParam(':to', $date_to);
$stmt->execute();
$verification_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Attendance Records
$query = "SELECT a.*, s.student_number, s.first_name, s.last_name, s.grade, s.class_section
          FROM attendance a
          JOIN students s ON a.student_id = s.student_id
          ORDER BY a.date DESC, a.created_at DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Attendance Students
$query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
          COUNT(a.attendance_id) as total_days,
          SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
          ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.attendance_id)), 1) as rate
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          JOIN attendance a ON s.student_id = a.student_id
          WHERE u.status = 'active' AND a.date BETWEEN :from AND :to
          GROUP BY s.student_id
          HAVING COUNT(a.attendance_id) >= 5
          ORDER BY rate DESC, present_days DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':from', $date_from);
$stmt->bindParam(':to', $date_to);
$stmt->execute();
$top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Students with Low Attendance Details
$query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
          COUNT(a.attendance_id) as total_days,
          SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
          SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
          ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.attendance_id)), 1) as rate
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          JOIN attendance a ON s.student_id = a.student_id
          WHERE u.status = 'active' AND a.date BETWEEN :from AND :to
          GROUP BY s.student_id
          HAVING rate < 75 AND COUNT(a.attendance_id) >= 5
          ORDER BY rate ASC
          LIMIT 15";
$stmt = $conn->prepare($query);
$stmt->bindParam(':from', $date_from);
$stmt->bindParam(':to', $date_to);
$stmt->execute();
$low_attendance_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exam Statistics
$query = "SELECT 
          COUNT(*) as total_exams,
          SUM(CASE WHEN status = 'scheduled' AND exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
          SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
          FROM exams";
$stmt = $conn->prepare($query);
$stmt->execute();
$exam_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly Attendance Comparison
$query = "SELECT 
          DATE_FORMAT(date, '%Y-%m') as month,
          COUNT(*) as total,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
          ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as rate
          FROM attendance
          WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(date, '%Y-%m')
          ORDER BY month";
$stmt = $conn->prepare($query);
$stmt->execute();
$monthly_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="reports.php" class="nav-item active">
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
                    <h1>Reports & Analytics</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Reports</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Report Filter Bar -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.25rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div>
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">From Date</label>
                                    <input type="date" id="dateFrom" value="<?php echo $date_from; ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">To Date</label>
                                    <input type="date" id="dateTo" value="<?php echo $date_to; ?>" max="<?php echo $today; ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                </div>
                                <div style="align-self: flex-end;">
                                    <button class="btn btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;" onclick="applyDateFilter()">
                                        Apply
                                    </button>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;" onclick="exportReport('pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;" onclick="exportReport('excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                                <button class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="card stat-card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05)); border: 1px solid rgba(99, 102, 241, 0.2);">
                        <div class="card-body" style="padding: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Total Students</div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #6366f1;"><?php echo number_format($stats['total_students']); ?></div>
                                </div>
                                <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-graduate" style="font-size: 1.5rem; color: #6366f1;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div class="card-body" style="padding: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Today's Attendance</div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #22c55e;"><?php echo $stats['today_attendance_rate']; ?>%</div>
                                </div>
                                <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-check" style="font-size: 1.5rem; color: #22c55e;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Month Avg</div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['month_attendance_rate']; ?>%</div>
                                </div>
                                <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chart-line" style="font-size: 1.5rem; color: #3b82f6;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div class="card-body" style="padding: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Low Attendance</div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #ef4444;"><?php echo $stats['low_attendance_students']; ?></div>
                                </div>
                                <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; color: #ef4444;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Attendance Trend Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-area"></i> Attendance Trend (Last 7 Days)</h3>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <canvas id="attendanceTrendChart" height="280"></canvas>
                        </div>
                    </div>
                    
                    <!-- Verification Methods Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-pie-chart"></i> Verification Methods</h3>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <canvas id="verificationChart" height="280"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Grade-wise Analytics -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Grade Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Student Distribution by Grade</h3>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <canvas id="gradeDistributionChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <!-- Grade-wise Attendance -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Grade-wise Attendance Rate</h3>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <canvas id="gradeAttendanceChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Comparison -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Monthly Attendance Comparison</h3>
                    </div>
                    <div class="card-body" style="padding: 1.5rem;">
                        <canvas id="monthlyComparisonChart" height="120"></canvas>
                    </div>
                </div>
                
                <!-- Data Tables Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Top Performing Students -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-trophy" style="color: #f59e0b;"></i> Top Attendance Students</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                                <table style="width: 100%;">
                                    <thead style="position: sticky; top: 0; background: var(--bg-secondary); z-index: 1;">
                                        <tr>
                                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">#</th>
                                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Student</th>
                                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Class</th>
                                            <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($top_students)): ?>
                                        <tr>
                                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-secondary);">No data available</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($top_students as $index => $student): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 0.75rem 1rem;">
                                                <?php if ($index < 3): ?>
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: <?php echo $index == 0 ? '#fbbf24' : ($index == 1 ? '#9ca3af' : '#cd7f32'); ?>; color: white; font-size: 0.75rem; font-weight: 700;">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php else: ?>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem; padding-left: 0.25rem;"><?php echo $index + 1; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 0.75rem 1rem;">
                                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($student['student_number']); ?></div>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                                                <?php echo $student['grade']; ?>-<?php echo $student['class_section']; ?>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; background: rgba(34, 197, 94, 0.1); color: #22c55e; font-weight: 600; font-size: 0.875rem;">
                                                    <?php echo $student['rate']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Low Attendance Students -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> Students with Low Attendance (&lt;75%)</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                                <table style="width: 100%;">
                                    <thead style="position: sticky; top: 0; background: var(--bg-secondary); z-index: 1;">
                                        <tr>
                                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Student</th>
                                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Class</th>
                                            <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Absent</th>
                                            <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($low_attendance_list)): ?>
                                        <tr>
                                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                                <i class="fas fa-check-circle" style="color: #22c55e; font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                                All students have good attendance!
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($low_attendance_list as $student): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 0.75rem 1rem;">
                                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($student['student_number']); ?></div>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                                                <?php echo $student['grade']; ?>-<?php echo $student['class_section']; ?>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                                <span style="color: #ef4444; font-weight: 600;"><?php echo $student['absent_days']; ?></span>
                                                <span style="color: var(--text-secondary); font-size: 0.75rem;"> / <?php echo $student['total_days']; ?></span>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-weight: 600; font-size: 0.875rem;">
                                                    <?php echo $student['rate']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grade-wise Summary Table -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-table"></i> Grade-wise Attendance Summary</h3>
                        <span style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>
                        </span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%;">
                                <thead>
                                    <tr style="background: var(--bg-secondary);">
                                        <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Grade</th>
                                        <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Total Records</th>
                                        <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #22c55e; text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Present</th>
                                        <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #ef4444; text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Absent</th>
                                        <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #f59e0b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Late</th>
                                        <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--border-color);">Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_records = 0;
                                    $total_present = 0;
                                    $total_absent = 0;
                                    $total_late = 0;
                                    foreach ($grade_attendance as $grade): 
                                        $rate = $grade['total_records'] > 0 ? round(($grade['present'] / $grade['total_records']) * 100, 1) : 0;
                                        $total_records += $grade['total_records'];
                                        $total_present += $grade['present'];
                                        $total_absent += $grade['absent'];
                                        $total_late += $grade['late'];
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 1rem; font-weight: 600; color: var(--text-primary);">Grade <?php echo htmlspecialchars($grade['grade']); ?></td>
                                        <td style="padding: 1rem; text-align: center; color: var(--text-secondary);"><?php echo number_format($grade['total_records']); ?></td>
                                        <td style="padding: 1rem; text-align: center; color: #22c55e; font-weight: 600;"><?php echo number_format($grade['present']); ?></td>
                                        <td style="padding: 1rem; text-align: center; color: #ef4444; font-weight: 600;"><?php echo number_format($grade['absent']); ?></td>
                                        <td style="padding: 1rem; text-align: center; color: #f59e0b; font-weight: 600;"><?php echo number_format($grade['late']); ?></td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem;">
                                                <div style="width: 100px; height: 8px; background: var(--bg-secondary); border-radius: 4px; overflow: hidden;">
                                                    <div style="width: <?php echo $rate; ?>%; height: 100%; background: <?php echo $rate >= 75 ? '#22c55e' : ($rate >= 50 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 4px;"></div>
                                                </div>
                                                <span style="font-weight: 600; color: <?php echo $rate >= 75 ? '#22c55e' : ($rate >= 50 ? '#f59e0b' : '#ef4444'); ?>;"><?php echo $rate; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--bg-secondary);">
                                        <td style="padding: 1rem; font-weight: 700; color: var(--text-primary);">TOTAL</td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 700; color: var(--text-primary);"><?php echo number_format($total_records); ?></td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 700; color: #22c55e;"><?php echo number_format($total_present); ?></td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 700; color: #ef4444;"><?php echo number_format($total_absent); ?></td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 700; color: #f59e0b;"><?php echo number_format($total_late); ?></td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <?php $overall_rate = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0; ?>
                                            <span style="display: inline-block; padding: 0.5rem 1rem; border-radius: 20px; background: <?php echo $overall_rate >= 75 ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $overall_rate >= 75 ? '#22c55e' : '#ef4444'; ?>; font-weight: 700;">
                                                <?php echo $overall_rate; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Attendance Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Recent Attendance Activity</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%;">
                                <thead>
                                    <tr style="background: var(--bg-secondary);">
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Date</th>
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Student</th>
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Class</th>
                                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Status</th>
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Method</th>
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">Check-in</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_attendance)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-secondary);">No recent attendance records</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_attendance as $record): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem 1rem; color: var(--text-primary); font-size: 0.875rem;">
                                            <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($record['student_number']); ?></div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                                            <?php echo $record['grade']; ?>-<?php echo $record['class_section']; ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <?php
                                            $status_colors = [
                                                'present' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'color' => '#22c55e'],
                                                'absent' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444'],
                                                'late' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                                                'excused' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6']
                                            ];
                                            $s = $status_colors[$record['status']] ?? $status_colors['absent'];
                                            ?>
                                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>; font-weight: 600; font-size: 0.75rem; text-transform: capitalize;">
                                                <?php echo $record['status']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <?php
                                            $method_icons = [
                                                'manual' => ['icon' => 'fa-user-edit', 'color' => '#6366f1'],
                                                'qr_code' => ['icon' => 'fa-qrcode', 'color' => '#3b82f6'],
                                                'face_recognition' => ['icon' => 'fa-smile', 'color' => '#22c55e']
                                            ];
                                            $m = $method_icons[$record['verification_method']] ?? $method_icons['manual'];
                                            ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">
                                                <i class="fas <?php echo $m['icon']; ?>" style="color: <?php echo $m['color']; ?>;"></i>
                                                <?php echo ucwords(str_replace('_', ' ', $record['verification_method'])); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                                            <?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Apply date filter
        function applyDateFilter() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            window.location.href = `reports.php?from=${from}&to=${to}`;
        }
        
        // Export report
        function exportReport(format) {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            
            if (format === 'pdf') {
                // Open PDF in new window for printing/saving
                window.open(`report_handler.php?action=pdf&from=${from}&to=${to}`, '_blank');
            } else if (format === 'excel') {
                // Download Excel/CSV file
                window.location.href = `report_handler.php?action=excel&from=${from}&to=${to}`;
            }
        }
        
        // Chart.js default configuration
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#64748b';
        
        // Attendance Trend Chart
        const trendCtx = document.getElementById('attendanceTrendChart').getContext('2d');
        const trendData = <?php echo json_encode($attendance_trend); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Present',
                        data: trendData.map(d => d.present),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Absent',
                        data: trendData.map(d => d.absent),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Late',
                        data: trendData.map(d => d.late),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Verification Methods Chart
        const verifyCtx = document.getElementById('verificationChart').getContext('2d');
        const verifyData = <?php echo json_encode($verification_methods); ?>;
        
        const verifyColors = {
            'manual': '#6366f1',
            'qr_code': '#3b82f6',
            'face_recognition': '#22c55e'
        };
        
        new Chart(verifyCtx, {
            type: 'doughnut',
            data: {
                labels: verifyData.map(d => d.verification_method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())),
                datasets: [{
                    data: verifyData.map(d => d.count),
                    backgroundColor: verifyData.map(d => verifyColors[d.verification_method] || '#94a3b8'),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Grade Distribution Chart
        const gradeDistCtx = document.getElementById('gradeDistributionChart').getContext('2d');
        const gradeDistData = <?php echo json_encode($grade_distribution); ?>;
        
        new Chart(gradeDistCtx, {
            type: 'bar',
            data: {
                labels: gradeDistData.map(d => 'Grade ' + d.grade),
                datasets: [{
                    label: 'Students',
                    data: gradeDistData.map(d => d.count),
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Grade Attendance Chart
        const gradeAttCtx = document.getElementById('gradeAttendanceChart').getContext('2d');
        const gradeAttData = <?php echo json_encode($grade_attendance); ?>;
        
        new Chart(gradeAttCtx, {
            type: 'bar',
            data: {
                labels: gradeAttData.map(d => 'Grade ' + d.grade),
                datasets: [{
                    label: 'Attendance Rate %',
                    data: gradeAttData.map(d => d.total_records > 0 ? ((d.present / d.total_records) * 100).toFixed(1) : 0),
                    backgroundColor: gradeAttData.map(d => {
                        const rate = d.total_records > 0 ? (d.present / d.total_records) * 100 : 0;
                        return rate >= 75 ? 'rgba(34, 197, 94, 0.7)' : (rate >= 50 ? 'rgba(245, 158, 11, 0.7)' : 'rgba(239, 68, 68, 0.7)');
                    }),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        },
                        ticks: {
                            callback: value => value + '%'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Monthly Comparison Chart
        const monthlyCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_attendance); ?>;
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => {
                    const [year, month] = d.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Attendance Rate %',
                    data: monthlyData.map(d => d.rate),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        },
                        ticks: {
                            callback: value => value + '%'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
    
    <style>
        @media print {
            .sidebar, .top-header, .btn {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                break-inside: avoid;
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
            }
        }
    </style>
</body>
</html>
