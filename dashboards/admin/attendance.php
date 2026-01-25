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

// Get unique grades
$query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique sections for selected grade
$sections = [];
if ($selected_grade) {
    $query = "SELECT DISTINCT class_section FROM students WHERE grade = :grade ORDER BY class_section";
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
          ORDER BY s.grade, s.class_section, s.student_number";
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
          ORDER BY s.grade, s.class_section, s.student_number";
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
                        <h3 style="color: var(--text-primary); margin-bottom: 1rem;"><i class="fas fa-filter"></i> Select Class</h3>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Date</label>
                                <input type="date" id="dateFilter" value="<?php echo $selected_date; ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary);">
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
                    </div>
                </div>
                
                <?php if ($selected_grade): ?>
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
                                <col style="width: 25%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 17%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Section</th>
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
                                                <span style="font-size: 0.875rem; color: var(--text-primary);"><?php echo htmlspecialchars($record['class_section']); ?></span>
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
    
    <!-- Mark Attendance Modal -->
    <div id="markAttendanceModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2><i class="fas fa-check-double"></i> Mark Attendance - Grade <?php echo $selected_grade; ?><?php echo $selected_section ? " ({$selected_section})" : ''; ?></h2>
                <span class="close" onclick="closeMarkAttendanceModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <button class="btn btn-sm" style="background: var(--success-color); color: white;" onclick="markAllAs('present')">
                            <i class="fas fa-check"></i> Mark All Present
                        </button>
                        <button class="btn btn-sm" style="background: var(--danger-color); color: white;" onclick="markAllAs('absent')">
                            <i class="fas fa-times"></i> Mark All Absent
                        </button>
                    </div>
                </div>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                    <table style="width: 100%;">
                        <thead style="position: sticky; top: 0; background: var(--bg-primary);">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">Student</th>
                                <th style="padding: 1rem; text-align: center; width: 200px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmarked_students as $student): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600;">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                            <div style="color: var(--text-secondary); font-size: 0.875rem;"><?php echo htmlspecialchars($student['student_number']); ?> | <?php echo htmlspecialchars($student['class_section']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem; text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <button class="status-btn" data-student="<?php echo $student['student_id']; ?>" data-status="present" onclick="selectStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--success-color); background: transparent; color: var(--success-color); border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-check"></i> P
                                        </button>
                                        <button class="status-btn" data-student="<?php echo $student['student_id']; ?>" data-status="absent" onclick="selectStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--danger-color); background: transparent; color: var(--danger-color); border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-times"></i> A
                                        </button>
                                        <button class="status-btn" data-student="<?php echo $student['student_id']; ?>" data-status="late" onclick="selectStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--warning-color); background: transparent; color: var(--warning-color); border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-clock"></i> L
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <button class="btn btn-outline" onclick="closeMarkAttendanceModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" onclick="submitAttendance()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            margin: 2% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            max-width: 600px;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
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
        
        .btn-edit {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }
        .btn-edit:hover {
            background: rgba(168, 85, 247, 0.2);
            border-color: #a855f7;
        }
        
        .status-btn.selected {
            color: white !important;
        }
        .status-btn.selected[data-status="present"] {
            background: var(--success-color) !important;
        }
        .status-btn.selected[data-status="absent"] {
            background: var(--danger-color) !important;
        }
        .status-btn.selected[data-status="late"] {
            background: var(--warning-color) !important;
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
        }
        
        function markAllAs(status) {
            const buttons = document.querySelectorAll(`.status-btn[data-status="${status}"]`);
            buttons.forEach(btn => {
                const siblings = btn.parentElement.querySelectorAll('.status-btn');
                siblings.forEach(s => s.classList.remove('selected'));
                btn.classList.add('selected');
                attendanceData[btn.dataset.student] = status;
            });
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
    </script>
</body>
</html>
