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

// Get filter parameters
$report_type = $_GET['report'] ?? 'attendance';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';

// Get available grades and sections
$query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

$query = "SELECT DISTINCT class_section FROM students ORDER BY class_section";
$stmt = $conn->prepare($query);
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get overall attendance stats for date range
$stats_query = "SELECT 
    COUNT(DISTINCT a.student_id) as total_students_attended,
    COUNT(DISTINCT a.date) as total_days,
    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
    COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
    COUNT(*) as total_records
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.date BETWEEN :from AND :to";

$params = [':from' => $date_from, ':to' => $date_to];

if ($selected_grade) {
    $stats_query .= " AND s.grade = :grade";
    $params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $stats_query .= " AND s.class_section = :section";
    $params[':section'] = $selected_section;
}

$stmt = $conn->prepare($stats_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate percentages
$total = $stats['total_records'] ?: 1;
$present_pct = round(($stats['present_count'] / $total) * 100, 1);
$late_pct = round(($stats['late_count'] / $total) * 100, 1);
$absent_pct = round(($stats['absent_count'] / $total) * 100, 1);
$excused_pct = round(($stats['excused_count'] / $total) * 100, 1);

// Get data based on report type
$report_data = [];

if ($report_type === 'attendance') {
    // Daily attendance summary
    $query = "SELECT 
        a.date,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused,
        COUNT(*) as total
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE a.date BETWEEN :from AND :to";
    
    $params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_grade) {
        $query .= " AND s.grade = :grade";
        $params[':grade'] = $selected_grade;
    }
    if ($selected_section) {
        $query .= " AND s.class_section = :section";
        $params[':section'] = $selected_section;
    }
    
    $query .= " GROUP BY a.date ORDER BY a.date DESC";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($report_type === 'student') {
    // Student-wise attendance summary
    $query = "SELECT 
        s.student_id, s.first_name, s.last_name, s.student_number, s.grade, s.class_section,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused,
        COUNT(a.attendance_id) as total_days
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date BETWEEN :from AND :to
        WHERE 1=1";
    
    $params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_grade) {
        $query .= " AND s.grade = :grade";
        $params[':grade'] = $selected_grade;
    }
    if ($selected_section) {
        $query .= " AND s.class_section = :section";
        $params[':section'] = $selected_section;
    }
    
    $query .= " GROUP BY s.student_id ORDER BY s.grade, s.class_section, s.last_name";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($report_type === 'class') {
    // Class-wise attendance summary
    $query = "SELECT 
        s.grade, s.class_section,
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
        COUNT(a.attendance_id) as total_records
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date BETWEEN :from AND :to
        WHERE 1=1";
    
    $params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_grade) {
        $query .= " AND s.grade = :grade";
        $params[':grade'] = $selected_grade;
    }
    
    $query .= " GROUP BY s.grade, s.class_section ORDER BY s.grade, s.class_section";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($report_type === 'exam') {
    // Exam verification report
    $query = "SELECT 
        e.exam_name, e.exam_date,
        COUNT(*) as total_entries,
        COUNT(CASE WHEN e.verification_status = 'verified' THEN 1 END) as verified,
        COUNT(CASE WHEN e.verification_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN e.verification_status = 'rejected' THEN 1 END) as rejected
        FROM exam_entries e
        JOIN students s ON e.student_id = s.student_id
        WHERE e.exam_date BETWEEN :from AND :to";
    
    $params = [':from' => $date_from, ':to' => $date_to];
    
    if ($selected_grade) {
        $query .= " AND s.grade = :grade";
        $params[':grade'] = $selected_grade;
    }
    if ($selected_section) {
        $query .= " AND s.class_section = :section";
        $params[':section'] = $selected_section;
    }
    
    $query .= " GROUP BY e.exam_name, e.exam_date ORDER BY e.exam_date DESC";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get chart data (last 7 days attendance)
$chart_query = "SELECT 
    a.date,
    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";

if ($selected_grade) {
    $chart_query .= " AND s.grade = :grade";
}
if ($selected_section) {
    $chart_query .= " AND s.class_section = :section";
}

$chart_query .= " GROUP BY a.date ORDER BY a.date ASC";

$stmt = $conn->prepare($chart_query);
if ($selected_grade) $stmt->bindParam(':grade', $selected_grade);
if ($selected_section) $stmt->bindParam(':section', $selected_section);
$stmt->execute();
$chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Teacher - EduID</title>
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
                    <a href="reports.php" class="nav-item active">
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
                    <h1>Reports</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Management</span>
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
                <!-- Stats Row -->
                <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-check-circle" style="font-size: 1.25rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Present</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $present_pct; ?>%</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock" style="font-size: 1.25rem; color: #f59e0b;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Late</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $late_pct; ?>%</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-times-circle" style="font-size: 1.25rem; color: #ef4444;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Absent</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo $absent_pct; ?>%</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calendar-check" style="font-size: 1.25rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Total Days</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_days']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            <div style="min-width: 140px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Report Type</label>
                                <select name="report" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Daily Attendance</option>
                                    <option value="student" <?php echo $report_type === 'student' ? 'selected' : ''; ?>>Student-wise</option>
                                    <option value="class" <?php echo $report_type === 'class' ? 'selected' : ''; ?>>Class-wise</option>
                                    <option value="exam" <?php echo $report_type === 'exam' ? 'selected' : ''; ?>>Exam Verification</option>
                                </select>
                            </div>
                            
                            <div style="min-width: 130px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">From Date</label>
                                <input type="date" name="from" value="<?php echo $date_from; ?>" class="form-input" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                            
                            <div style="min-width: 130px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">To Date</label>
                                <input type="date" name="to" value="<?php echo $date_to; ?>" class="form-input" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                            
                            <div style="min-width: 100px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Grade</label>
                                <select name="grade" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="">All</option>
                                    <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $selected_grade === $grade ? 'selected' : ''; ?>><?php echo htmlspecialchars($grade); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="min-width: 100px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Section</label>
                                <select name="section" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="">All</option>
                                    <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section === $section ? 'selected' : ''; ?>><?php echo htmlspecialchars($section); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer;">
                                    <i class="fas fa-filter"></i> Generate
                                </button>
                                <button type="button" onclick="exportReport()" class="btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2); cursor: pointer;">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem;">
                    <!-- Main Content -->
                    <div>
                        <!-- Report Table -->
                        <div class="card">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title">
                                    <i class="fas fa-table"></i> 
                                    <?php 
                                    $titles = [
                                        'attendance' => 'Daily Attendance Report',
                                        'student' => 'Student-wise Attendance Report',
                                        'class' => 'Class-wise Attendance Report',
                                        'exam' => 'Exam Verification Report'
                                    ];
                                    echo $titles[$report_type];
                                    ?>
                                </h3>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>
                                </span>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($report_data)): ?>
                                <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-chart-bar" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p style="font-size: 1rem;">No data found for the selected criteria</p>
                                </div>
                                <?php else: ?>
                                <div style="overflow-x: auto; max-height: 500px;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead style="position: sticky; top: 0; background: var(--bg-primary); z-index: 10;">
                                            <tr style="background: var(--bg-secondary);">
                                                <?php if ($report_type === 'attendance'): ?>
                                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Date</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Present</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Late</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Absent</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Excused</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Rate</th>
                                                
                                                <?php elseif ($report_type === 'student'): ?>
                                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Student</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Class</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Present</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Late</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Absent</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Rate</th>
                                                
                                                <?php elseif ($report_type === 'class'): ?>
                                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Class</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Students</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Present</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Late</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Absent</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Avg Rate</th>
                                                
                                                <?php elseif ($report_type === 'exam'): ?>
                                                <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Exam</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Date</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Total</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Verified</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Pending</th>
                                                <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Rejected</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <?php if ($report_type === 'attendance'): 
                                                    $rate = $row['total'] > 0 ? round((($row['present'] + $row['late']) / $row['total']) * 100) : 0;
                                                ?>
                                                <td style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-primary);"><?php echo date('D, M d, Y', strtotime($row['date'])); ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo $row['present']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #f59e0b; font-weight: 600;"><?php echo $row['late']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo $row['absent']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #3b82f6; font-weight: 600;"><?php echo $row['excused']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                                                        <div style="width: 50px; height: 6px; background: var(--border-color); border-radius: 3px; overflow: hidden;">
                                                            <div style="height: 100%; width: <?php echo $rate; ?>%; background: <?php echo $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 3px;"></div>
                                                        </div>
                                                        <span style="font-weight: 600; color: <?php echo $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; font-size: 0.85rem;"><?php echo $rate; ?>%</span>
                                                    </div>
                                                </td>
                                                
                                                <?php elseif ($report_type === 'student'): 
                                                    $rate = $row['total_days'] > 0 ? round((($row['present'] + $row['late']) / $row['total_days']) * 100) : 0;
                                                ?>
                                                <td style="padding: 0.75rem 1rem;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.7rem;">
                                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight: 500; color: var(--text-primary); font-size: 0.85rem;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                                            <div style="font-size: 0.7rem; color: var(--text-tertiary);"><?php echo $row['student_number']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; text-align: center; font-size: 0.85rem; color: var(--text-secondary);"><?php echo $row['grade'] . '-' . $row['class_section']; ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo $row['present']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #f59e0b; font-weight: 600;"><?php echo $row['late']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo $row['absent']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <span style="padding: 0.25rem 0.5rem; background: <?php echo $rate >= 80 ? 'rgba(16, 185, 129, 0.1)' : ($rate >= 60 ? 'rgba(245, 158, 11, 0.1)' : 'rgba(239, 68, 68, 0.1)'); ?>; color: <?php echo $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 6px; font-weight: 600; font-size: 0.8rem;"><?php echo $rate; ?>%</span>
                                                </td>
                                                
                                                <?php elseif ($report_type === 'class'): 
                                                    $rate = $row['total_records'] > 0 ? round((($row['present'] + $row['late']) / $row['total_records']) * 100) : 0;
                                                ?>
                                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--text-primary);">Grade <?php echo $row['grade']; ?>-<?php echo $row['class_section']; ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center; color: var(--text-secondary);"><?php echo $row['total_students']; ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo $row['present']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #f59e0b; font-weight: 600;"><?php echo $row['late']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo $row['absent']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                                                        <div style="width: 60px; height: 6px; background: var(--border-color); border-radius: 3px; overflow: hidden;">
                                                            <div style="height: 100%; width: <?php echo $rate; ?>%; background: <?php echo $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 3px;"></div>
                                                        </div>
                                                        <span style="font-weight: 600; color: <?php echo $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; font-size: 0.85rem;"><?php echo $rate; ?>%</span>
                                                    </div>
                                                </td>
                                                
                                                <?php elseif ($report_type === 'exam'): ?>
                                                <td style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($row['exam_name']); ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center; color: var(--text-secondary); font-size: 0.85rem;"><?php echo date('M d, Y', strtotime($row['exam_date'])); ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600; color: var(--text-primary);"><?php echo $row['total_entries']; ?></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #10b981; font-weight: 600;"><?php echo $row['verified']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #f59e0b; font-weight: 600;"><?php echo $row['pending']; ?></span></td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;"><span style="color: #ef4444; font-weight: 600;"><?php echo $row['rejected']; ?></span></td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Sidebar -->
                    <div>
                        <!-- Chart -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-line"></i> Last 7 Days</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Pie Chart -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie"></i> Distribution</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="pieChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle"></i> Summary</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; gap: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Total Records</span>
                                        <span style="font-weight: 600; color: var(--text-primary);"><?php echo number_format($stats['total_records']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Present Count</span>
                                        <span style="font-weight: 600; color: #10b981;"><?php echo number_format($stats['present_count']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Late Count</span>
                                        <span style="font-weight: 600; color: #f59e0b;"><?php echo number_format($stats['late_count']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Absent Count</span>
                                        <span style="font-weight: 600; color: #ef4444;"><?php echo number_format($stats['absent_count']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Excused Count</span>
                                        <span style="font-weight: 600; color: #3b82f6;"><?php echo number_format($stats['excused_count']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        @media (max-width: 1024px) {
            .content-area > div:last-child {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script>
        // Chart data from PHP
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        // Line Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Present',
                        data: chartData.map(d => d.present),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Late',
                        data: chartData.map(d => d.late),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Absent',
                        data: chartData.map(d => d.absent),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
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
        
        // Pie Chart
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'Absent', 'Excused'],
                datasets: [{
                    data: [
                        <?php echo $stats['present_count']; ?>,
                        <?php echo $stats['late_count']; ?>,
                        <?php echo $stats['absent_count']; ?>,
                        <?php echo $stats['excused_count']; ?>
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#3b82f6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Export report
        function exportReport() {
            const table = document.querySelector('table');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = [];
                cells.forEach(cell => {
                    let text = cell.innerText.replace(/"/g, '""');
                    rowData.push(`"${text}"`);
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'report_<?php echo $report_type; ?>_<?php echo date('Y-m-d'); ?>.csv';
            link.click();
        }
        
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
        });        
        // Update current time with seconds and date
        setInterval(() => {
            const now = new Date();
            const timeElement = document.getElementById('currentTime');
            const dateElement = document.getElementById('currentDate');
            if (timeElement && dateElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                dateElement.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit', year: 'numeric' });
            }
        }, 1000);    </script>
</body>
</html>
