<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get today's date
$today = date('Y-m-d');
$selected_date = $_GET['date'] ?? $today;
$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';

// Check if selected date is a holiday
$query = "SELECT * FROM institute_holidays WHERE holiday_date = :date";
$stmt = $conn->prepare($query);
$stmt->bindParam(':date', $selected_date);
$stmt->execute();
$holiday_info = $stmt->fetch(PDO::FETCH_ASSOC);
$is_holiday = $holiday_info ? true : false;

// Get unique grades (sorted numerically)
$query = "SELECT DISTINCT grade FROM students WHERE grade IS NOT NULL AND grade != '' ORDER BY CAST(grade AS UNSIGNED), grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique sections for selected grade (only sections that have students)
$sections = [];
if ($selected_grade) {
    $query = "SELECT DISTINCT s.class_section 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.grade = :grade 
              AND s.class_section IS NOT NULL 
              AND s.class_section != ''
              AND u.status = 'active'
              ORDER BY s.class_section";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':grade', $selected_grade);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Build attendance query
$where_conditions = ["a.date = :selected_date"];
$params = [':selected_date' => $selected_date];

if ($selected_grade) {
    $where_conditions[] = "s.grade = :grade";
    $params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $where_conditions[] = "s.class_section = :section";
    $params[':section'] = $selected_section;
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT a.*, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
          u.email, a.verification_method
          FROM attendance a
          JOIN students s ON a.student_id = s.student_id
          JOIN users u ON s.user_id = u.user_id
          WHERE {$where_clause}
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade, s.class_section, s.student_number";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for today with filters
$stats_where = ["a.date = :today"];
$stats_params = [':today' => $today];

if ($selected_grade) {
    $stats_where[] = "s.grade = :grade";
    $stats_params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $stats_where[] = "s.class_section = :section";
    $stats_params[':section'] = $selected_section;
}

$stats_where_clause = implode(' AND ', $stats_where);

// Total students in selection
$student_where = [];
$student_params = [];
if ($selected_grade) {
    $student_where[] = "s.grade = :grade";
    $student_params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $student_where[] = "s.class_section = :section";
    $student_params[':section'] = $selected_section;
}

$student_where_clause = count($student_where) > 0 ? "WHERE u.status = 'active' AND " . implode(' AND ', $student_where) : "WHERE u.status = 'active'";

$query = "SELECT COUNT(*) as count FROM students s JOIN users u ON s.user_id = u.user_id {$student_where_clause}";
$stmt = $conn->prepare($query);
foreach ($student_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Present today
$query = "SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE {$stats_where_clause} AND a.status = 'present'";
$stmt = $conn->prepare($query);
foreach ($stats_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats['present_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Absent today
$query = "SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE {$stats_where_clause} AND a.status = 'absent'";
$stmt = $conn->prepare($query);
foreach ($stats_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats['absent_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Late today
$query = "SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE {$stats_where_clause} AND a.status = 'late'";
$stmt = $conn->prepare($query);
foreach ($stats_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats['late_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Face verified count
$query = "SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE {$stats_where_clause} AND a.verification_method = 'face'";
$stmt = $conn->prepare($query);
foreach ($stats_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats['face_verified'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate attendance percentage
$stats['attendance_percentage'] = $stats['total_students'] > 0 
    ? round(($stats['present_today'] / $stats['total_students']) * 100, 1) 
    : 0;

// Get unmarked students for selected grade/section
$unmarked_where = ["u.status = 'active'", "s.student_id NOT IN (SELECT student_id FROM attendance WHERE date = :selected_date)"];
$unmarked_params = [':selected_date' => $selected_date];

if ($selected_grade) {
    $unmarked_where[] = "s.grade = :grade";
    $unmarked_params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $unmarked_where[] = "s.class_section = :section";
    $unmarked_params[':section'] = $selected_section;
}

$unmarked_where_clause = implode(' AND ', $unmarked_where);

$query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section, s.user_id
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          WHERE {$unmarked_where_clause}
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade, s.class_section, s.student_number";
$stmt = $conn->prepare($query);
foreach ($unmarked_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$unmarked_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Attendance - EduID</title>
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
                    <a href="attendance.php" class="nav-item active">
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
                    <h1>Class Attendance</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Attendance</span>
                        <?php if ($selected_grade): ?>
                            <i class="fas fa-chevron-right"></i>
                            <span>Grade <?php echo $selected_grade; ?><?php echo $selected_section ? " - {$selected_section}" : ''; ?></span>
                        <?php endif; ?>
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
                <!-- Grade/Section Selection -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="color: var(--text-primary); margin: 0;"><i class="fas fa-filter"></i> Select Class & Date</h3>
                            <button class="btn" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); padding: 0.4rem 0.75rem; font-size: 0.75rem;" onclick="showHolidayModal()">
                                <i class="fas fa-calendar-times"></i> Mark Closed
                            </button>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Date</label>
                                <input type="date" id="dateFilter" value="<?php echo $selected_date; ?>" max="<?php echo $today; ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Grade</label>
                                <select id="gradeFilter" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                    <option value="">Select Grade</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo $grade; ?>" <?php echo $selected_grade == $grade ? 'selected' : ''; ?>>Grade <?php echo $grade; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Section</label>
                                <select id="sectionFilter" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" <?php echo !$selected_grade ? 'disabled' : ''; ?>>
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section == $section ? 'selected' : ''; ?>><?php echo htmlspecialchars($section); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button class="btn btn-primary" style="width: 100%; padding: 0.75rem;" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Load Class
                                </button>
                            </div>
                        </div>
                        <?php if ($selected_date !== $today): ?>
                        <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-history" style="color: #f59e0b;"></i>
                            <span style="color: var(--text-primary); font-size: 0.9rem;">
                                <strong>Past Date:</strong> You are viewing/marking attendance for <strong><?php echo date('F d, Y', strtotime($selected_date)); ?></strong>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($is_holiday): ?>
                <!-- Holiday Notice -->
                <div class="card" style="margin-bottom: 1.5rem; border: 2px solid #ef4444;">
                    <div class="card-body" style="padding: 2rem; text-align: center; background: rgba(239, 68, 68, 0.05);">
                        <i class="fas fa-building-circle-xmark" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                        <h3 style="color: #ef4444; margin-bottom: 0.5rem;">Institute Closed</h3>
                        <p style="color: var(--text-primary); font-size: 1.1rem; margin-bottom: 0.5rem;">
                            <strong><?php echo date('l, F d, Y', strtotime($selected_date)); ?></strong>
                        </p>
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                            <span class="holiday-type-badge" style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">
                                <?php echo str_replace('_', ' ', $holiday_info['holiday_type']); ?>
                            </span>
                        </p>
                        <p style="color: var(--text-primary); font-size: 1rem;">
                            <i class="fas fa-quote-left" style="color: var(--text-secondary); margin-right: 0.5rem;"></i>
                            <?php echo htmlspecialchars($holiday_info['reason']); ?>
                            <i class="fas fa-quote-right" style="color: var(--text-secondary); margin-left: 0.5rem;"></i>
                        </p>
                        <button class="btn" style="margin-top: 1.5rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;" onclick="removeHoliday(<?php echo $holiday_info['holiday_id']; ?>)">
                            <i class="fas fa-trash"></i> Remove Holiday & Enable Attendance
                        </button>
                    </div>
                </div>
                <?php elseif ($selected_grade): ?>
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['present_today']; ?></h3>
                            <p>Present</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['absent_today']; ?></h3>
                            <p>Absent</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['face_verified']; ?></h3>
                            <p>Face Verified</p>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Progress -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="color: var(--text-primary); margin: 0;">
                                Grade <?php echo $selected_grade; ?><?php echo $selected_section ? " - Section {$selected_section}" : ''; ?> Attendance
                            </h3>
                            <span style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $stats['attendance_percentage']; ?>%</span>
                        </div>
                        <div style="width: 100%; height: 12px; background: var(--bg-secondary); border-radius: 6px; overflow: hidden;">
                            <div style="width: <?php echo $stats['attendance_percentage']; ?>%; height: 100%; background: linear-gradient(90deg, var(--success-color), var(--primary-color)); transition: width 0.5s;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 0.75rem; font-size: 0.875rem; color: var(--text-secondary);">
                            <span><i class="fas fa-circle" style="color: var(--success-color); font-size: 0.5rem;"></i> Present: <?php echo $stats['present_today']; ?></span>
                            <span><i class="fas fa-circle" style="color: var(--danger-color); font-size: 0.5rem;"></i> Absent: <?php echo $stats['absent_today']; ?></span>
                            <span><i class="fas fa-circle" style="color: var(--warning-color); font-size: 0.5rem;"></i> Late: <?php echo $stats['late_today']; ?></span>
                            <span><i class="fas fa-circle" style="color: var(--info-color); font-size: 0.5rem;"></i> Face Verified: <?php echo $stats['face_verified']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Unmarked Students Alert -->
                <?php if (count($unmarked_students) > 0): ?>
                <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?php echo count($unmarked_students); ?> student<?php echo count($unmarked_students) > 1 ? 's' : ''; ?> not marked!</strong>
                    <div style="margin-top: 0.5rem;">
                        <button class="btn btn-sm" style="background: var(--warning-color); color: white; border: none; padding: 0.4rem 1rem; margin-right: 0.5rem;" onclick="showMarkAttendanceModal()">
                            <i class="fas fa-edit"></i> Mark Manually
                        </button>
                        <a href="face_attendance.php?grade=<?php echo $selected_grade; ?>&section=<?php echo urlencode($selected_section); ?>" class="btn btn-sm" style="background: var(--primary-color); color: white; border: none; padding: 0.4rem 1rem;">
                            <i class="fas fa-camera"></i> Use Face Recognition
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Attendance Records - <?php echo date('M d, Y', strtotime($selected_date)); ?></h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="searchInput" placeholder="Search students..." style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                            <select id="statusFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-container" style="overflow-x: auto;">
                        <table id="attendanceTable" style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 10%;">
                                <col style="width: 22%;">
                                <col style="width: 13%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 19%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Time</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($attendance_records) > 0): ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr data-status="<?php echo $record['status']; ?>">
                                            <td>
                                                <strong style="color: var(--primary-color); font-size: 0.875rem;"><?php echo htmlspecialchars($record['student_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                                                        <?php echo strtoupper(substr($record['first_name'], 0, 1)); ?>
                                                    </div>
                                                    <div style="overflow: hidden;">
                                                        <div style="color: var(--text-primary); font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="display: inline-block; padding: 0.25rem 0.5rem; background: rgba(37, 99, 235, 0.1); color: var(--primary-color); border-radius: 4px; font-size: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($record['grade'] . '-' . $record['class_section']); ?></span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (isset($record['verification_method']) && $record['verification_method'] === 'face'): ?>
                                                    <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-camera"></i> Face</span>
                                                <?php elseif (isset($record['verification_method']) && $record['verification_method'] === 'qr'): ?>
                                                    <span style="color: var(--info-color); font-size: 0.875rem;"><i class="fas fa-qrcode"></i> QR</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-secondary); font-size: 0.875rem;"><i class="fas fa-hand"></i> Manual</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'present' => 'var(--success-color)',
                                                    'absent' => 'var(--danger-color)',
                                                    'late' => 'var(--warning-color)'
                                                ];
                                                $statusColor = $statusColors[$record['status']] ?? 'var(--text-secondary)';
                                                ?>
                                                <span style="color: <?php echo $statusColor; ?>; font-size: 0.875rem; font-weight: 600;">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i> <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.3rem; align-items: center; justify-content: center;">
                                                    <button class="btn btn-sm btn-edit" onclick="editAttendance(<?php echo $record['attendance_id']; ?>, '<?php echo $record['status']; ?>')" title="Edit Status" style="padding: 0.4rem 0.6rem;">
                                                        <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.4rem 0.6rem;" onclick="deleteAttendance(<?php echo $record['attendance_id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p style="font-size: 1.1rem;">No attendance records found</p>
                                            <button class="btn btn-primary" style="margin-top: 1rem;" onclick="showMarkAttendanceModal()">
                                                <i class="fas fa-plus"></i> Mark Attendance
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <!-- No Grade Selected -->
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-graduation-cap" style="font-size: 4rem; color: var(--primary-color); opacity: 0.3; margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Select a Grade to View Attendance</h3>
                        <p style="color: var(--text-secondary);">Choose a grade and optionally a section from the filters above to view and manage attendance records.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Holiday/Institute Closed Modal -->
    <div id="holidayModal" class="modal">
        <div class="modal-content attendance-modal" style="max-width: 550px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <div class="modal-title-wrapper">
                    <div class="modal-icon" style="background: rgba(255, 255, 255, 0.2);">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div>
                        <h2>Mark Day as Closed</h2>
                        <p class="modal-subtitle">Institute will be closed on this day</p>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="closeHolidayModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-primary); font-weight: 600;">Date</label>
                    <input type="date" id="holidayDate" value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 1rem;">
                </div>
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-primary); font-weight: 600;">Type</label>
                    <select id="holidayType" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 1rem;">
                        <option value="public_holiday">Public Holiday</option>
                        <option value="institute_holiday" selected>Institute Holiday</option>
                        <option value="emergency">Emergency Closure</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-primary); font-weight: 600;">Reason</label>
                    <textarea id="holidayReason" rows="3" placeholder="Enter the reason for closure..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 1rem; resize: vertical;"></textarea>
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button class="footer-btn footer-btn-cancel" onclick="closeHolidayModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="footer-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none;" onclick="saveHoliday()">
                        <i class="fas fa-calendar-times"></i> Mark as Closed
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mark Attendance Modal -->
    <div id="markAttendanceModal" class="modal">
        <div class="modal-content attendance-modal">
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <div class="modal-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h2>Mark Attendance</h2>
                        <p class="modal-subtitle">Grade <?php echo $selected_grade; ?><?php echo $selected_section ? " - Section {$selected_section}" : ''; ?> • <?php echo date('M d, Y', strtotime($selected_date)); ?></p>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="closeMarkAttendanceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <span class="quick-actions-label">Quick Actions:</span>
                    <button class="action-btn action-btn-success" onclick="markAllAs('present')">
                        <i class="fas fa-check-circle"></i> All Present
                    </button>
                    <button class="action-btn action-btn-danger" onclick="markAllAs('absent')">
                        <i class="fas fa-times-circle"></i> All Absent
                    </button>
                    <button class="action-btn action-btn-warning" onclick="markAllAs('late')">
                        <i class="fas fa-clock"></i> All Late
                    </button>
                </div>
                
                <!-- Students List -->
                <div class="students-list-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th style="text-align: center; width: 280px;">Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmarked_students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                            <div class="student-meta">
                                                <span class="student-id"><?php echo htmlspecialchars($student['student_number']); ?></span>
                                                <span class="student-class"><?php echo htmlspecialchars($student['grade'] . '-' . $student['class_section']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-buttons">
                                        <button class="status-btn status-present" data-student="<?php echo $student['student_id']; ?>" data-status="present" onclick="selectStatus(this)">
                                            <i class="fas fa-check"></i>
                                            <span>Present</span>
                                        </button>
                                        <button class="status-btn status-absent" data-student="<?php echo $student['student_id']; ?>" data-status="absent" onclick="selectStatus(this)">
                                            <i class="fas fa-times"></i>
                                            <span>Absent</span>
                                        </button>
                                        <button class="status-btn status-late" data-student="<?php echo $student['student_id']; ?>" data-status="late" onclick="selectStatus(this)">
                                            <i class="fas fa-clock"></i>
                                            <span>Late</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer Actions -->
                <div class="modal-footer">
                    <div class="attendance-summary">
                        <span id="summaryText">Select attendance status for each student</span>
                    </div>
                    <div class="footer-buttons">
                        <button class="footer-btn footer-btn-cancel" onclick="closeMarkAttendanceModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="footer-btn footer-btn-save" onclick="submitAttendance()">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Modal Base Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            overflow-y: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal[style*="display: block"] {
            opacity: 1;
        }
        
        /* Attendance Modal Specific */
        .attendance-modal {
            background: var(--bg-primary);
            margin: 3% auto;
            border-radius: 16px;
            max-width: 900px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.3s ease-out;
            overflow: hidden;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Modal Header */
        .attendance-modal .modal-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
        }
        
        .modal-title-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .modal-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .attendance-modal .modal-header h2 {
            margin: 0;
            color: white;
            font-size: 1.35rem;
            font-weight: 700;
        }
        
        .modal-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: rotate(90deg);
        }
        
        /* Modal Body */
        .attendance-modal .modal-body {
            padding: 1.5rem 2rem 2rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .quick-actions-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: transparent;
        }
        
        .action-btn-success {
            border: 2px solid #22c55e;
            color: #22c55e;
        }
        .action-btn-success:hover {
            background: #22c55e;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .action-btn-danger {
            border: 2px solid #ef4444;
            color: #ef4444;
        }
        .action-btn-danger:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .action-btn-warning {
            border: 2px solid #f59e0b;
            color: #f59e0b;
        }
        .action-btn-warning:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        /* Students List */
        .students-list-container {
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
        }
        
        .students-list-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .students-list-container::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }
        
        .students-list-container::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        
        .students-list-container::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendance-table thead {
            position: sticky;
            top: 0;
            background: var(--bg-secondary);
            z-index: 10;
        }
        
        .attendance-table th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .attendance-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s ease;
        }
        
        .attendance-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .attendance-table tbody tr:hover {
            background: var(--bg-secondary);
        }
        
        .attendance-table td {
            padding: 0.875rem 1.25rem;
        }
        
        /* Student Info */
        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .student-avatar {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        
        .student-details {
            flex: 1;
        }
        
        .student-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .student-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }
        
        .student-id {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .student-class {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Status Buttons */
        .status-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .status-btn {
            padding: 0.5rem 0.875rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: transparent;
        }
        
        .status-btn i {
            font-size: 0.75rem;
        }
        
        .status-present {
            border: 2px solid #22c55e;
            color: #22c55e;
        }
        .status-present:hover {
            background: rgba(34, 197, 94, 0.1);
            transform: translateY(-1px);
        }
        .status-present.selected {
            background: #22c55e !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.35);
        }
        
        .status-absent {
            border: 2px solid #ef4444;
            color: #ef4444;
        }
        .status-absent:hover {
            background: rgba(239, 68, 68, 0.1);
            transform: translateY(-1px);
        }
        .status-absent.selected {
            background: #ef4444 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
        }
        
        .status-late {
            border: 2px solid #f59e0b;
            color: #f59e0b;
        }
        .status-late:hover {
            background: rgba(245, 158, 11, 0.1);
            transform: translateY(-1px);
        }
        .status-late.selected {
            background: #f59e0b !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        }
        
        /* Modal Footer */
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .attendance-summary {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .footer-buttons {
            display: flex;
            gap: 0.75rem;
        }
        
        .footer-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-btn-cancel {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
        }
        .footer-btn-cancel:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
            background: var(--bg-secondary);
        }
        
        .footer-btn-save {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            border: none;
            color: white;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        .footer-btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
        }
        
        /* Other Button Styles */
        .btn-edit {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }
        .btn-edit:hover {
            background: rgba(168, 85, 247, 0.2);
            border-color: #a855f7;
        }
        
        /* Close button in general modals */
        .close {
            color: var(--text-secondary);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: var(--text-primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .attendance-modal {
                margin: 1% auto;
                max-width: 95%;
            }
            
            .attendance-modal .modal-header,
            .attendance-modal .modal-body {
                padding: 1rem;
            }
            
            .quick-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-buttons {
                flex-direction: column;
            }
            
            .status-btn span {
                display: none;
            }
            
            .footer-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .footer-btn {
                justify-content: center;
            }
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Attendance data
        let attendanceData = {};
        
        // Apply filters
        function applyFilters() {
            const date = document.getElementById('dateFilter').value;
            const grade = document.getElementById('gradeFilter').value;
            const section = document.getElementById('sectionFilter').value;
            
            let url = `attendance.php?date=${date}`;
            if (grade) url += `&grade=${grade}`;
            if (section) url += `&section=${encodeURIComponent(section)}`;
            
            window.location.href = url;
        }
        
        // Load sections when grade changes
        document.getElementById('gradeFilter').addEventListener('change', async function() {
            const grade = this.value;
            const sectionSelect = document.getElementById('sectionFilter');
            
            if (!grade) {
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                sectionSelect.disabled = true;
                return;
            }
            
            try {
                const response = await fetch(`attendance_handler.php?get_sections=1&grade=${grade}`);
                const sections = await response.json();
                
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                sections.forEach(section => {
                    sectionSelect.innerHTML += `<option value="${section}">${section}</option>`;
                });
                sectionSelect.disabled = false;
            } catch (error) {
                console.error('Error loading sections:', error);
            }
        });
        
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#attendanceTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Status filter
        document.getElementById('statusFilter')?.addEventListener('change', function(e) {
            const status = e.target.value;
            const rows = document.querySelectorAll('#attendanceTable tbody tr');
            
            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                row.style.display = (!status || rowStatus === status) ? '' : 'none';
            });
        });
        
        function showMarkAttendanceModal() {
            document.getElementById('markAttendanceModal').style.display = 'block';
        }
        
        function closeMarkAttendanceModal() {
            document.getElementById('markAttendanceModal').style.display = 'none';
        }
        
        function selectStatus(btn) {
            const studentId = btn.dataset.student;
            const status = btn.dataset.status;
            
            // Remove selected from siblings
            const siblings = btn.parentElement.querySelectorAll('.status-btn');
            siblings.forEach(s => s.classList.remove('selected'));
            
            // Add selected to clicked button
            btn.classList.add('selected');
            
            // Store in attendance data
            attendanceData[studentId] = status;
            
            // Update summary
            updateSummary();
        }
        
        function updateSummary() {
            const total = Object.keys(attendanceData).length;
            const present = Object.values(attendanceData).filter(s => s === 'present').length;
            const absent = Object.values(attendanceData).filter(s => s === 'absent').length;
            const late = Object.values(attendanceData).filter(s => s === 'late').length;
            
            const summaryEl = document.getElementById('summaryText');
            if (total === 0) {
                summaryEl.textContent = 'Select attendance status for each student';
            } else {
                summaryEl.innerHTML = `<strong>${total}</strong> marked: <span style="color: #22c55e">${present} present</span>, <span style="color: #ef4444">${absent} absent</span>, <span style="color: #f59e0b">${late} late</span>`;
            }
        }
        
        function markAllAs(status) {
            const buttons = document.querySelectorAll(`.status-btn[data-status="${status}"]`);
            buttons.forEach(btn => {
                const siblings = btn.parentElement.querySelectorAll('.status-btn');
                siblings.forEach(s => s.classList.remove('selected'));
                btn.classList.add('selected');
                attendanceData[btn.dataset.student] = status;
            });
            updateSummary();
        }
        
        async function submitAttendance() {
            if (Object.keys(attendanceData).length === 0) {
                alert('Please mark at least one student');
                return;
            }
            
            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mark_class_attendance',
                        date: '<?php echo $selected_date; ?>',
                        attendance: attendanceData
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Attendance saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving attendance');
            }
        }
        
        async function editAttendance(attendanceId, currentStatus) {
            const newStatus = prompt('Change status to (present/absent/late):', currentStatus);
            if (!newStatus || !['present', 'absent', 'late'].includes(newStatus.toLowerCase())) {
                return;
            }
            
            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'edit_attendance',
                        attendance_id: attendanceId,
                        status: newStatus.toLowerCase()
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error updating attendance');
            }
        }
        
        async function deleteAttendance(attendanceId) {
            if (!confirm('Are you sure you want to delete this attendance record?')) {
                return;
            }
            
            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_attendance',
                        attendance_id: attendanceId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting attendance');
            }
        }
        
        // Holiday/Institute Closed Functions
        function showHolidayModal() {
            document.getElementById('holidayModal').style.display = 'block';
        }
        
        function closeHolidayModal() {
            document.getElementById('holidayModal').style.display = 'none';
        }
        
        async function saveHoliday() {
            const date = document.getElementById('holidayDate').value;
            const type = document.getElementById('holidayType').value;
            const reason = document.getElementById('holidayReason').value.trim();
            
            if (!date) {
                alert('Please select a date');
                return;
            }
            
            if (!reason) {
                alert('Please enter a reason for closure');
                return;
            }
            
            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_holiday',
                        date: date,
                        type: type,
                        reason: reason
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Day marked as closed successfully!');
                    window.location.href = `attendance.php?date=${date}`;
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving holiday');
            }
        }
        
        async function removeHoliday(holidayId) {
            if (!confirm('Are you sure you want to remove this holiday and enable attendance for this day?')) {
                return;
            }
            
            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'remove_holiday',
                        holiday_id: holidayId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error removing holiday');
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
