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
$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$search_query = $_GET['search'] ?? '';

// Get unique grades and sections
$query = "SELECT DISTINCT grade FROM students WHERE grade IS NOT NULL ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

$query = "SELECT DISTINCT class_section FROM students WHERE class_section IS NOT NULL ORDER BY class_section";
$stmt = $conn->prepare($query);
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build students query with filters
$query = "SELECT s.*, 
          a.attendance_id, a.status as attendance_status, a.check_in_time, a.verification_method,
          u.email
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date = :date
          WHERE 1=1";

$params = [':date' => $selected_date];

if ($selected_grade) {
    $query .= " AND s.grade = :grade";
    $params[':grade'] = $selected_grade;
}

if ($selected_section) {
    $query .= " AND s.class_section = :section";
    $params[':section'] = $selected_section;
}

if ($search_query) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_number LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

$query .= " ORDER BY s.grade, s.class_section, s.last_name, s.first_name";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's attendance stats
$stats_query = "SELECT 
    COUNT(DISTINCT s.student_id) as total_students,
    COUNT(DISTINCT CASE WHEN a.status = 'present' THEN s.student_id END) as present_count,
    COUNT(DISTINCT CASE WHEN a.status = 'late' THEN s.student_id END) as late_count,
    COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN s.student_id END) as absent_count,
    COUNT(DISTINCT CASE WHEN a.status = 'excused' THEN s.student_id END) as excused_count
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date = :date";

$params_stats = [':date' => $selected_date];
if ($selected_grade) {
    $stats_query .= " WHERE s.grade = :grade";
    $params_stats[':grade'] = $selected_grade;
    if ($selected_section) {
        $stats_query .= " AND s.class_section = :section";
        $params_stats[':section'] = $selected_section;
    }
} elseif ($selected_section) {
    $stats_query .= " WHERE s.class_section = :section";
    $params_stats[':section'] = $selected_section;
}

$stmt = $conn->prepare($stats_query);
foreach ($params_stats as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$unmarked_count = $stats['total_students'] - ($stats['present_count'] + $stats['late_count'] + $stats['absent_count'] + $stats['excused_count']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Teacher - EduID</title>
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
                    <a href="mark-attendance.php" class="nav-item active">
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
                    <h1>Mark Attendance</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Verification</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Mark Attendance</span>
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
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-users" style="font-size: 1.1rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Total</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_students']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-check-circle" style="font-size: 1.1rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Present</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #10b981;" id="presentCount"><?php echo $stats['present_count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock" style="font-size: 1.1rem; color: #f59e0b;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Late</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;" id="lateCount"><?php echo $stats['late_count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-times-circle" style="font-size: 1.1rem; color: #ef4444;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Absent</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #ef4444;" id="absentCount"><?php echo $stats['absent_count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-medical" style="font-size: 1.1rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Excused</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;" id="excusedCount"><?php echo $stats['excused_count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.05)); border: 1px solid rgba(107, 114, 128, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(107, 114, 128, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-question-circle" style="font-size: 1.1rem; color: #6b7280;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Unmarked</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #6b7280;" id="unmarkedCount"><?php echo $unmarked_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters Card -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Date</label>
                                <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" class="form-input" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                            
                            <div style="flex: 1; min-width: 120px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Grade</label>
                                <select name="grade" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="">All Grades</option>
                                    <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $selected_grade === $grade ? 'selected' : ''; ?>><?php echo htmlspecialchars($grade); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="flex: 1; min-width: 120px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Section</label>
                                <select name="section" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section === $section ? 'selected' : ''; ?>><?php echo htmlspecialchars($section); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="flex: 2; min-width: 200px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                                <div style="position: relative;">
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name or ID..." class="form-input" style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="mark-attendance.php" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.6rem 1rem; border-radius: 8px; font-weight: 500; border: 1px solid var(--border-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Quick Actions:</span>
                            <button onclick="markAllAs('present')" class="btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2); cursor: pointer; font-size: 0.85rem;">
                                <i class="fas fa-check-double"></i> Mark All Present
                            </button>
                            <button onclick="markAllAs('absent')" class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer; font-size: 0.85rem;">
                                <i class="fas fa-times-circle"></i> Mark All Absent
                            </button>
                            <button onclick="markUnmarkedAs('present')" class="btn" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(59, 130, 246, 0.2); cursor: pointer; font-size: 0.85rem;">
                                <i class="fas fa-user-check"></i> Mark Unmarked as Present
                            </button>
                            <button onclick="clearAllAttendance()" class="btn" style="background: rgba(107, 114, 128, 0.1); color: #6b7280; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(107, 114, 128, 0.2); cursor: pointer; font-size: 0.85rem;">
                                <i class="fas fa-eraser"></i> Clear All
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Students Table Card -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title"><i class="fas fa-list-check"></i> Student Attendance - <?php echo date('F d, Y', strtotime($selected_date)); ?></h3>
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?php echo count($students); ?> student<?php echo count($students) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($students)): ?>
                        <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-user-graduate" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p style="font-size: 1rem;">No students found matching your filters</p>
                            <p style="font-size: 0.85rem; margin-top: 0.5rem;">Try adjusting your search criteria</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--bg-secondary);">
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Student</th>
                                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">ID</th>
                                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Grade</th>
                                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Check-in</th>
                                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Attendance Status</th>
                                        <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody">
                                    <?php foreach ($students as $student): ?>
                                    <tr class="student-row" data-student-id="<?php echo $student['student_id']; ?>" data-attendance-id="<?php echo $student['attendance_id'] ?? ''; ?>" data-status="<?php echo $student['attendance_status'] ?? ''; ?>" style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.85rem;">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-tertiary);"><?php echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <code style="font-size: 0.8rem; background: var(--bg-secondary); padding: 0.25rem 0.5rem; border-radius: 4px; color: var(--text-secondary);"><?php echo htmlspecialchars($student['student_number']); ?></code>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($student['grade'] . '-' . $student['class_section']); ?></span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span class="check-in-time" style="font-size: 0.85rem; color: var(--text-secondary);">
                                                <?php echo $student['check_in_time'] ? date('h:i A', strtotime($student['check_in_time'])) : '-'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <div class="status-buttons" style="display: flex; justify-content: center; gap: 0.25rem;">
                                                <button onclick="markAttendance(<?php echo $student['student_id']; ?>, 'present', this)" class="status-btn <?php echo $student['attendance_status'] === 'present' ? 'active' : ''; ?>" data-status="present" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid <?php echo $student['attendance_status'] === 'present' ? '#10b981' : 'var(--border-color)'; ?>; background: <?php echo $student['attendance_status'] === 'present' ? 'rgba(16, 185, 129, 0.15)' : 'var(--bg-secondary)'; ?>; color: <?php echo $student['attendance_status'] === 'present' ? '#10b981' : 'var(--text-secondary)'; ?>; cursor: pointer; font-size: 0.75rem; font-weight: 600;">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="markAttendance(<?php echo $student['student_id']; ?>, 'late', this)" class="status-btn <?php echo $student['attendance_status'] === 'late' ? 'active' : ''; ?>" data-status="late" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid <?php echo $student['attendance_status'] === 'late' ? '#f59e0b' : 'var(--border-color)'; ?>; background: <?php echo $student['attendance_status'] === 'late' ? 'rgba(245, 158, 11, 0.15)' : 'var(--bg-secondary)'; ?>; color: <?php echo $student['attendance_status'] === 'late' ? '#f59e0b' : 'var(--text-secondary)'; ?>; cursor: pointer; font-size: 0.75rem; font-weight: 600;">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                                <button onclick="markAttendance(<?php echo $student['student_id']; ?>, 'absent', this)" class="status-btn <?php echo $student['attendance_status'] === 'absent' ? 'active' : ''; ?>" data-status="absent" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid <?php echo $student['attendance_status'] === 'absent' ? '#ef4444' : 'var(--border-color)'; ?>; background: <?php echo $student['attendance_status'] === 'absent' ? 'rgba(239, 68, 68, 0.15)' : 'var(--bg-secondary)'; ?>; color: <?php echo $student['attendance_status'] === 'absent' ? '#ef4444' : 'var(--text-secondary)'; ?>; cursor: pointer; font-size: 0.75rem; font-weight: 600;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button onclick="markAttendance(<?php echo $student['student_id']; ?>, 'excused', this)" class="status-btn <?php echo $student['attendance_status'] === 'excused' ? 'active' : ''; ?>" data-status="excused" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid <?php echo $student['attendance_status'] === 'excused' ? '#8b5cf6' : 'var(--border-color)'; ?>; background: <?php echo $student['attendance_status'] === 'excused' ? 'rgba(139, 92, 246, 0.15)' : 'var(--bg-secondary)'; ?>; color: <?php echo $student['attendance_status'] === 'excused' ? '#8b5cf6' : 'var(--text-secondary)'; ?>; cursor: pointer; font-size: 0.75rem; font-weight: 600;">
                                                    <i class="fas fa-file-medical"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <button onclick="showRemarkModal(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" class="btn" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); cursor: pointer; font-size: 0.75rem;" title="Add Remark">
                                                <i class="fas fa-comment"></i>
                                            </button>
                                            <?php if ($student['attendance_id']): ?>
                                            <button onclick="clearAttendance(<?php echo $student['student_id']; ?>)" class="btn" style="padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05); color: #ef4444; cursor: pointer; font-size: 0.75rem; margin-left: 0.25rem;" title="Clear Attendance">
                                                <i class="fas fa-eraser"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Remark Modal -->
    <div id="remarkModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 450px; width: 90%; max-height: 90vh; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-weight: 600; color: var(--text-primary);"><i class="fas fa-comment" style="color: #8b5cf6; margin-right: 0.5rem;"></i> Add Remark</h3>
                <button onclick="closeRemarkModal()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.25rem;">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
                <input type="hidden" id="remarkStudentId">
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem;">Adding remark for: <strong id="remarkStudentName"></strong></p>
                <textarea id="remarkText" placeholder="Enter attendance remark (e.g., reason for absence, late arrival note, etc.)" style="width: 100%; min-height: 100px; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); resize: vertical; font-family: inherit;"></textarea>
            </div>
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button onclick="closeRemarkModal()" style="padding: 0.6rem 1.25rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); cursor: pointer; font-weight: 500;">Cancel</button>
                <button onclick="saveRemark()" style="padding: 0.6rem 1.25rem; border-radius: 8px; border: none; background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white; cursor: pointer; font-weight: 600;">Save Remark</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); z-index: 1001; display: flex; align-items: center; gap: 0.75rem; max-width: 350px;">
        <i id="toastIcon" class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>
    
    <style>
        .status-btn {
            transition: all 0.2s ease;
        }
        
        .status-btn:hover {
            transform: scale(1.1);
        }
        
        .student-row {
            transition: background 0.2s ease;
        }
        
        .student-row:hover {
            background: var(--bg-secondary);
        }
        
        /* Toast Animations */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .toast-show {
            animation: slideIn 0.3s ease forwards;
        }
        
        .toast-hide {
            animation: slideOut 0.3s ease forwards;
        }
    </style>
    
    <script>
        const selectedDate = '<?php echo $selected_date; ?>';
        
        // Mark attendance for a single student
        async function markAttendance(studentId, status, buttonEl) {
            try {
                const row = buttonEl.closest('.student-row');
                const buttons = row.querySelectorAll('.status-btn');
                
                // Optimistic UI update
                buttons.forEach(btn => {
                    btn.style.border = '1px solid var(--border-color)';
                    btn.style.background = 'var(--bg-secondary)';
                    btn.style.color = 'var(--text-secondary)';
                });
                
                const colors = {
                    present: { border: '#10b981', bg: 'rgba(16, 185, 129, 0.15)', text: '#10b981' },
                    late: { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.15)', text: '#f59e0b' },
                    absent: { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.15)', text: '#ef4444' },
                    excused: { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.15)', text: '#8b5cf6' }
                };
                
                buttonEl.style.border = `1px solid ${colors[status].border}`;
                buttonEl.style.background = colors[status].bg;
                buttonEl.style.color = colors[status].text;
                
                // Send request
                const formData = new FormData();
                formData.append('student_id', studentId);
                formData.append('status', status);
                formData.append('date', selectedDate);
                
                const response = await fetch('includes/mark_attendance_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    row.dataset.status = status;
                    row.dataset.attendanceId = result.attendance_id;
                    
                    // Update check-in time
                    const timeEl = row.querySelector('.check-in-time');
                    if (timeEl && result.check_in_time) {
                        timeEl.textContent = result.check_in_time;
                    }
                    
                    updateStats();
                    showToast(`Marked as ${status}`, 'success');
                } else {
                    showToast(result.message || 'Failed to mark attendance', 'error');
                    // Revert UI on error
                    location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Clear attendance for a student
        async function clearAttendance(studentId) {
            if (!confirm('Are you sure you want to clear this attendance record?')) return;
            
            try {
                const formData = new FormData();
                formData.append('student_id', studentId);
                formData.append('date', selectedDate);
                formData.append('action', 'clear');
                
                const response = await fetch('includes/mark_attendance_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Attendance cleared', 'success');
                    location.reload();
                } else {
                    showToast(result.message || 'Failed to clear attendance', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Mark all students as a specific status
        async function markAllAs(status) {
            if (!confirm(`Are you sure you want to mark ALL students as "${status}"?`)) return;
            
            const rows = document.querySelectorAll('.student-row');
            for (const row of rows) {
                const studentId = row.dataset.studentId;
                const btn = row.querySelector(`[data-status="${status}"]`);
                await markAttendance(studentId, status, btn);
                await new Promise(resolve => setTimeout(resolve, 50)); // Small delay
            }
        }
        
        // Mark only unmarked students
        async function markUnmarkedAs(status) {
            if (!confirm(`Mark all unmarked students as "${status}"?`)) return;
            
            const rows = document.querySelectorAll('.student-row');
            for (const row of rows) {
                if (!row.dataset.status) {
                    const studentId = row.dataset.studentId;
                    const btn = row.querySelector(`[data-status="${status}"]`);
                    await markAttendance(studentId, status, btn);
                    await new Promise(resolve => setTimeout(resolve, 50));
                }
            }
        }
        
        // Clear all attendance
        async function clearAllAttendance() {
            if (!confirm('Are you sure you want to clear ALL attendance records for this date?')) return;
            
            try {
                const formData = new FormData();
                formData.append('date', selectedDate);
                formData.append('action', 'clear_all');
                
                const response = await fetch('includes/mark_attendance_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('All attendance cleared', 'success');
                    location.reload();
                } else {
                    showToast(result.message || 'Failed to clear attendance', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Update stats
        function updateStats() {
            let present = 0, late = 0, absent = 0, excused = 0, unmarked = 0;
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const status = row.dataset.status;
                if (status === 'present') present++;
                else if (status === 'late') late++;
                else if (status === 'absent') absent++;
                else if (status === 'excused') excused++;
                else unmarked++;
            });
            
            document.getElementById('presentCount').textContent = present;
            document.getElementById('lateCount').textContent = late;
            document.getElementById('absentCount').textContent = absent;
            document.getElementById('excusedCount').textContent = excused;
            document.getElementById('unmarkedCount').textContent = unmarked;
        }
        
        // Remark modal functions
        function showRemarkModal(studentId, studentName) {
            document.getElementById('remarkStudentId').value = studentId;
            document.getElementById('remarkStudentName').textContent = studentName;
            document.getElementById('remarkText').value = '';
            document.getElementById('remarkModal').style.display = 'flex';
        }
        
        function closeRemarkModal() {
            document.getElementById('remarkModal').style.display = 'none';
        }
        
        async function saveRemark() {
            const studentId = document.getElementById('remarkStudentId').value;
            const remark = document.getElementById('remarkText').value;
            
            if (!remark.trim()) {
                showToast('Please enter a remark', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('student_id', studentId);
                formData.append('date', selectedDate);
                formData.append('remark', remark);
                formData.append('action', 'add_remark');
                
                const response = await fetch('includes/mark_attendance_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Remark saved', 'success');
                    closeRemarkModal();
                } else {
                    showToast(result.message || 'Failed to save remark', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            const config = {
                success: { bg: 'linear-gradient(135deg, #10b981, #06b6d4)', icon: 'fa-check-circle' },
                error: { bg: 'linear-gradient(135deg, #ef4444, #f87171)', icon: 'fa-times-circle' },
                warning: { bg: 'linear-gradient(135deg, #f59e0b, #eab308)', icon: 'fa-exclamation-triangle' }
            };
            
            toast.style.background = config[type].bg;
            toast.style.color = 'white';
            toastIcon.className = `fas ${config[type].icon}`;
            toastMessage.textContent = message;
            
            toast.style.display = 'flex';
            toast.className = 'toast-show';
            
            setTimeout(() => {
                toast.className = 'toast-hide';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            }, 3000);
        }
        
        // Close modal on outside click
        document.getElementById('remarkModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRemarkModal();
            }
        });
    </script>
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
});
</script>
</html>
