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
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_exam = $_GET['exam'] ?? '';
$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';
$search_query = $_GET['search'] ?? '';

// Get available grades and sections
$query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

$query = "SELECT DISTINCT class_section FROM students ORDER BY class_section";
$stmt = $conn->prepare($query);
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get students with their today's attendance and exam entry status
$query = "SELECT s.student_id, s.first_name, s.last_name, s.student_number, s.grade, s.class_section,
          a.status as attendance_status, a.check_in_time,
          e.entry_id, e.verification_status
          FROM students s
          LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date = :date
          LEFT JOIN exam_entries e ON s.student_id = e.student_id 
              AND e.exam_date = :exam_date 
              AND (:exam_name = '' OR e.exam_name = :exam_name2)
          WHERE 1=1";

$params = [
    ':date' => $selected_date,
    ':exam_date' => $selected_date,
    ':exam_name' => $selected_exam,
    ':exam_name2' => $selected_exam
];

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

// Get stats for selected date and exam
$stats_query = "SELECT 
    COUNT(DISTINCT s.student_id) as total_students,
    COUNT(DISTINCT e.student_id) as enrolled,
    COUNT(CASE WHEN e.verification_status = 'verified' THEN 1 END) as verified,
    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_today
    FROM students s
    LEFT JOIN exam_entries e ON s.student_id = e.student_id 
        AND e.exam_date = :exam_date 
        AND (:exam_name = '' OR e.exam_name = :exam_name2)
    LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date = :date
    WHERE 1=1";

$stats_params = [
    ':exam_date' => $selected_date,
    ':exam_name' => $selected_exam,
    ':exam_name2' => $selected_exam,
    ':date' => $selected_date
];

if ($selected_grade) {
    $stats_query .= " AND s.grade = :grade";
    $stats_params[':grade'] = $selected_grade;
}
if ($selected_section) {
    $stats_query .= " AND s.class_section = :section";
    $stats_params[':section'] = $selected_section;
}

$stmt = $conn->prepare($stats_query);
foreach ($stats_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming exams
$query = "SELECT exam_name, exam_date, COUNT(*) as student_count 
          FROM exam_entries 
          WHERE exam_date >= CURDATE() 
          GROUP BY exam_name, exam_date 
          ORDER BY exam_date LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique exam names for dropdown
$query = "SELECT DISTINCT exam_name FROM exam_entries ORDER BY exam_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$exam_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Verification - Teacher - EduID</title>
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
                    <a href="exams.php" class="nav-item active">
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
                    <h1>Exam Verification</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Management</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Exam Verification</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Row -->
                <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-users" style="font-size: 1.25rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Total Students</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_students']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clipboard-check" style="font-size: 1.25rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Enrolled</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo $stats['enrolled']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-check-circle" style="font-size: 1.25rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Verified</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $stats['verified']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-check" style="font-size: 1.25rem; color: #f59e0b;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Present Today</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $stats['present_today']; ?></h3>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem;">
                    <!-- Main Content -->
                    <div>
                        <!-- Exam Setup Card -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-file-alt"></i> Exam Setup</h3>
                            </div>
                            <div class="card-body" style="padding: 1rem;">
                                <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                                    <div style="flex: 1.5; min-width: 200px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Exam Name *</label>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <select id="examNameSelect" class="form-select" style="flex: 1; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                                <option value="">Select existing exam...</option>
                                                <?php foreach ($exam_names as $exam): ?>
                                                <option value="<?php echo htmlspecialchars($exam); ?>" <?php echo $selected_exam === $exam ? 'selected' : ''; ?>><?php echo htmlspecialchars($exam); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" id="examNameInput" placeholder="Or type new exam name" style="flex: 1; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);" value="<?php echo htmlspecialchars($selected_exam); ?>">
                                        </div>
                                    </div>
                                    <div style="min-width: 150px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Exam Date</label>
                                        <input type="date" id="examDate" value="<?php echo htmlspecialchars($selected_date); ?>" class="form-input" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters Card -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-body" style="padding: 1rem;">
                                <form method="GET" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                                    <input type="hidden" name="exam" value="<?php echo htmlspecialchars($selected_exam); ?>">
                                    
                                    <div style="flex: 1; min-width: 100px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Grade</label>
                                        <select name="grade" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                            <option value="">All Grades</option>
                                            <?php foreach ($grades as $grade): ?>
                                            <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $selected_grade === $grade ? 'selected' : ''; ?>>Grade <?php echo htmlspecialchars($grade); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 100px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Section</label>
                                        <select name="section" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                            <option value="">All Sections</option>
                                            <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section === $section ? 'selected' : ''; ?>>Section <?php echo htmlspecialchars($section); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div style="flex: 1.5; min-width: 180px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                                        <div style="position: relative;">
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search student..." class="form-input" style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                            <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer;">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="exams.php" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.6rem 1rem; border-radius: 8px; font-weight: 500; border: 1px solid var(--border-color); text-decoration: none;">
                                            <i class="fas fa-times"></i>
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
                                    <button onclick="toggleAllStudents(true)" class="btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2); cursor: pointer; font-size: 0.85rem;">
                                        <i class="fas fa-check-double"></i> Add All
                                    </button>
                                    <button onclick="toggleAllStudents(false)" class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer; font-size: 0.85rem;">
                                        <i class="fas fa-times"></i> Remove All
                                    </button>
                                    <button onclick="addPresentOnly()" class="btn" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(245, 158, 11, 0.2); cursor: pointer; font-size: 0.85rem;">
                                        <i class="fas fa-user-check"></i> Add Present Only
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Students List -->
                        <div class="card">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title"><i class="fas fa-user-graduate"></i> Students - <?php echo date('F d, Y', strtotime($selected_date)); ?></h3>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo count($students); ?> student<?php echo count($students) !== 1 ? 's' : ''; ?>
                                </span>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($students)): ?>
                                <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p style="font-size: 1rem;">No students found</p>
                                    <p style="font-size: 0.85rem; margin-top: 0.5rem;">Try adjusting your filters</p>
                                </div>
                                <?php else: ?>
                                <div style="max-height: 600px; overflow-y: auto;">
                                    <?php foreach ($students as $student): 
                                        $is_enrolled = !empty($student['entry_id']);
                                        $attendance = $student['attendance_status'] ?? null;
                                        
                                        // Attendance colors
                                        $att_config = [
                                            'present' => ['bg' => '#10b981', 'text' => 'Present', 'icon' => 'check-circle'],
                                            'late' => ['bg' => '#f59e0b', 'text' => 'Late', 'icon' => 'clock'],
                                            'absent' => ['bg' => '#ef4444', 'text' => 'Absent', 'icon' => 'times-circle'],
                                            'excused' => ['bg' => '#3b82f6', 'text' => 'Excused', 'icon' => 'info-circle'],
                                        ];
                                        $att_style = $att_config[$attendance] ?? ['bg' => '#6b7280', 'text' => 'Not Marked', 'icon' => 'minus-circle'];
                                    ?>
                                    <div class="student-row" data-student-id="<?php echo $student['student_id']; ?>" data-attendance="<?php echo $attendance ?: 'none'; ?>" style="display: flex; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); gap: 1rem;">
                                        <!-- Attendance Indicator Bar -->
                                        <div style="width: 4px; height: 50px; border-radius: 2px; background: <?php echo $att_style['bg']; ?>;" title="<?php echo $att_style['text']; ?>"></div>
                                        
                                        <!-- Student Info -->
                                        <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                                            <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.85rem;">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-tertiary);"><?php echo htmlspecialchars($student['student_number']); ?> • Grade <?php echo htmlspecialchars($student['grade'] . '-' . $student['class_section']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Attendance Badge -->
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; background: <?php echo $att_style['bg']; ?>20; color: <?php echo $att_style['bg']; ?>; border-radius: 6px; font-size: 0.7rem; font-weight: 600;">
                                                <i class="fas fa-<?php echo $att_style['icon']; ?>"></i>
                                                <?php echo $att_style['text']; ?>
                                            </span>
                                            <?php if ($student['check_in_time']): ?>
                                            <span style="font-size: 0.7rem; color: var(--text-tertiary);"><?php echo date('h:i A', strtotime($student['check_in_time'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Toggle Switch -->
                                        <label class="toggle-switch" style="position: relative; display: inline-block; width: 50px; height: 28px; margin-left: 0.5rem;">
                                            <input type="checkbox" class="exam-toggle" data-student-id="<?php echo $student['student_id']; ?>" <?php echo $is_enrolled ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                            <span class="toggle-slider" style="position: absolute; cursor: pointer; inset: 0; background: <?php echo $is_enrolled ? '#10b981' : 'var(--border-color)'; ?>; border-radius: 28px; transition: 0.3s;">
                                                <span style="position: absolute; content: ''; height: 22px; width: 22px; left: <?php echo $is_enrolled ? '25px' : '3px'; ?>; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                            </span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Sidebar -->
                    <div>
                        <!-- Upcoming Exams Card -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Upcoming Exams</h3>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($upcoming_exams)): ?>
                                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                                    <p style="font-size: 0.85rem;">No upcoming exams</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($upcoming_exams as $exam): 
                                    $is_today = $exam['exam_date'] === date('Y-m-d');
                                ?>
                                <a href="?date=<?php echo $exam['exam_date']; ?>&exam=<?php echo urlencode($exam['exam_name']); ?>" class="exam-item" style="display: block; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); text-decoration: none; transition: background 0.2s;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                                <i class="fas fa-calendar" style="margin-right: 0.25rem;"></i>
                                                <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?>
                                                <?php if ($is_today): ?>
                                                <span style="margin-left: 0.5rem; padding: 0.15rem 0.4rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 4px; font-weight: 600;">Today</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span style="padding: 0.25rem 0.5rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                            <?php echo $exam['student_count']; ?> students
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Attendance Legend -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle"></i> Attendance Legend</h3>
                            </div>
                            <div class="card-body" style="padding: 1rem;">
                                <div style="display: grid; gap: 0.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 4px; height: 20px; border-radius: 2px; background: #10b981;"></div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Present</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 4px; height: 20px; border-radius: 2px; background: #f59e0b;"></div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Late</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 4px; height: 20px; border-radius: 2px; background: #ef4444;"></div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Absent</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 4px; height: 20px; border-radius: 2px; background: #3b82f6;"></div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Excused</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 4px; height: 20px; border-radius: 2px; background: #6b7280;"></div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Not Marked</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Stats Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie"></i> Enrollment Progress</h3>
                            </div>
                            <div class="card-body">
                                <?php 
                                $total = $stats['total_students'] ?: 1;
                                $enrolled_pct = round(($stats['enrolled'] / $total) * 100);
                                $verified_pct = $stats['enrolled'] > 0 ? round(($stats['verified'] / $stats['enrolled']) * 100) : 0;
                                ?>
                                
                                <div style="margin-bottom: 1rem;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Enrolled</span>
                                        <span style="font-size: 0.8rem; font-weight: 600; color: #8b5cf6;"><?php echo $enrolled_pct; ?>%</span>
                                    </div>
                                    <div style="height: 8px; background: var(--border-color); border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $enrolled_pct; ?>%; background: #8b5cf6; border-radius: 4px;"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Verified</span>
                                        <span style="font-size: 0.8rem; font-weight: 600; color: #10b981;"><?php echo $verified_pct; ?>%</span>
                                    </div>
                                    <div style="height: 8px; background: var(--border-color); border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $verified_pct; ?>%; background: #10b981; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); z-index: 1001; align-items: center; gap: 0.75rem; max-width: 350px;">
        <i id="toastIcon" class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>
    
    <style>
        .exam-item:hover {
            background: var(--bg-secondary);
        }
        
        .student-row {
            transition: background 0.2s ease;
        }
        
        .student-row:hover {
            background: var(--bg-secondary);
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: #10b981;
        }
        
        .toggle-switch input:checked + .toggle-slider span {
            left: 25px;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .toast-show { animation: slideIn 0.3s ease forwards; }
        .toast-hide { animation: slideOut 0.3s ease forwards; }
        
        @media (max-width: 1024px) {
            .content-area > div {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script>
        // Sync exam name select and input
        document.getElementById('examNameSelect').addEventListener('change', function() {
            document.getElementById('examNameInput').value = this.value;
        });
        
        document.getElementById('examNameInput').addEventListener('input', function() {
            document.getElementById('examNameSelect').value = '';
        });
        
        // Get current exam name and date
        function getExamInfo() {
            const examName = document.getElementById('examNameInput').value || document.getElementById('examNameSelect').value;
            const examDate = document.getElementById('examDate').value;
            return { examName, examDate };
        }
        
        // Toggle student exam entry
        document.querySelectorAll('.exam-toggle').forEach(toggle => {
            toggle.addEventListener('change', async function() {
                const studentId = this.dataset.studentId;
                const isChecked = this.checked;
                const { examName, examDate } = getExamInfo();
                
                if (!examName) {
                    showToast('Please enter or select an exam name first', 'warning');
                    this.checked = !isChecked;
                    return;
                }
                
                const slider = this.nextElementSibling;
                const sliderDot = slider.querySelector('span');
                
                // Update toggle appearance
                slider.style.background = isChecked ? '#10b981' : 'var(--border-color)';
                sliderDot.style.left = isChecked ? '25px' : '3px';
                
                try {
                    const formData = new FormData();
                    formData.append('action', isChecked ? 'add' : 'remove');
                    formData.append('student_id', studentId);
                    formData.append('exam_name', examName);
                    formData.append('exam_date', examDate);
                    
                    const response = await fetch('includes/verify_exam_entry.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                    } else {
                        showToast(result.message || 'Failed to update', 'error');
                        // Revert toggle
                        this.checked = !isChecked;
                        slider.style.background = !isChecked ? '#10b981' : 'var(--border-color)';
                        sliderDot.style.left = !isChecked ? '25px' : '3px';
                    }
                } catch (error) {
                    showToast('Network error. Please try again.', 'error');
                    this.checked = !isChecked;
                    slider.style.background = !isChecked ? '#10b981' : 'var(--border-color)';
                    sliderDot.style.left = !isChecked ? '25px' : '3px';
                }
            });
        });
        
        // Toggle all students
        async function toggleAllStudents(add) {
            const { examName, examDate } = getExamInfo();
            
            if (!examName) {
                showToast('Please enter or select an exam name first', 'warning');
                return;
            }
            
            const toggles = document.querySelectorAll('.exam-toggle');
            const action = add ? 'add_all' : 'remove_all';
            
            try {
                const studentIds = Array.from(toggles).map(t => t.dataset.studentId);
                
                const formData = new FormData();
                formData.append('action', action);
                formData.append('student_ids', JSON.stringify(studentIds));
                formData.append('exam_name', examName);
                formData.append('exam_date', examDate);
                
                const response = await fetch('includes/verify_exam_entry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    // Update all toggles visually
                    toggles.forEach(toggle => {
                        toggle.checked = add;
                        const slider = toggle.nextElementSibling;
                        const sliderDot = slider.querySelector('span');
                        slider.style.background = add ? '#10b981' : 'var(--border-color)';
                        sliderDot.style.left = add ? '25px' : '3px';
                    });
                } else {
                    showToast(result.message || 'Failed to update', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }
        
        // Add only present students
        async function addPresentOnly() {
            const { examName, examDate } = getExamInfo();
            
            if (!examName) {
                showToast('Please enter or select an exam name first', 'warning');
                return;
            }
            
            const rows = document.querySelectorAll('.student-row');
            const presentStudentIds = [];
            
            rows.forEach(row => {
                const attendance = row.dataset.attendance;
                if (attendance === 'present' || attendance === 'late') {
                    presentStudentIds.push(row.dataset.studentId);
                }
            });
            
            if (presentStudentIds.length === 0) {
                showToast('No present students found', 'warning');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_all');
                formData.append('student_ids', JSON.stringify(presentStudentIds));
                formData.append('exam_name', examName);
                formData.append('exam_date', examDate);
                
                const response = await fetch('includes/verify_exam_entry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Added ${presentStudentIds.length} present students to exam`, 'success');
                    // Update toggles for present students
                    rows.forEach(row => {
                        const attendance = row.dataset.attendance;
                        if (attendance === 'present' || attendance === 'late') {
                            const toggle = row.querySelector('.exam-toggle');
                            toggle.checked = true;
                            const slider = toggle.nextElementSibling;
                            const sliderDot = slider.querySelector('span');
                            slider.style.background = '#10b981';
                            sliderDot.style.left = '25px';
                        }
                    });
                } else {
                    showToast(result.message || 'Failed to update', 'error');
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
                warning: { bg: 'linear-gradient(135deg, #f59e0b, #eab308)', icon: 'fa-exclamation-triangle' },
                info: { bg: 'linear-gradient(135deg, #3b82f6, #60a5fa)', icon: 'fa-info-circle' }
            };
            
            toast.style.background = config[type].bg;
            toast.style.color = 'white';
            toastIcon.className = `fas ${config[type].icon}`;
            toastMessage.textContent = message;
            
            toast.style.display = 'flex';
            toast.className = 'toast-show';
            
            setTimeout(() => {
                toast.className = 'toast-hide';
                setTimeout(() => { toast.style.display = 'none'; }, 300);
            }, 3000);
        }
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
