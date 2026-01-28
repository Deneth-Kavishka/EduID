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
$search_query = $_GET['search'] ?? '';
$face_status = $_GET['face_status'] ?? '';

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
$query = "SELECT s.*, u.email, u.status as account_status,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status = 'present') as present_days,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id) as total_days,
          (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = s.user_id AND is_active = 1) as has_face_data
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($selected_grade) {
    $query .= " AND s.grade = :grade";
    $params[':grade'] = $selected_grade;
}

if ($selected_section) {
    $query .= " AND s.class_section = :section";
    $params[':section'] = $selected_section;
}

if ($search_query) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_number LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

if ($face_status === 'registered') {
    $query .= " AND EXISTS (SELECT 1 FROM face_recognition_data WHERE user_id = s.user_id AND is_active = 1)";
} elseif ($face_status === 'not_registered') {
    $query .= " AND NOT EXISTS (SELECT 1 FROM face_recognition_data WHERE user_id = s.user_id AND is_active = 1)";
}

$query .= " ORDER BY s.grade, s.class_section, s.last_name, s.first_name";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total_students,
    COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM face_recognition_data WHERE user_id = s.user_id AND is_active = 1) THEN s.student_id END) as face_registered,
    COUNT(DISTINCT grade) as total_grades
    FROM students s";
$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's attendance
$query = "SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE date = CURDATE() AND status IN ('present', 'late')";
$stmt = $conn->prepare($query);
$stmt->execute();
$today_present = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Teacher - EduID</title>
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
                    <a href="students.php" class="nav-item active">
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
                    <h1>My Students</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Management</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>My Students</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Row -->
                <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-graduate" style="font-size: 1.5rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Total Students</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_students']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-face-smile" style="font-size: 1.5rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Face Registered</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #10b981;"><?php echo $stats['face_registered']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-school" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Total Grades</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #8b5cf6;"><?php echo $stats['total_grades']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calendar-check" style="font-size: 1.5rem; color: #f59e0b;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Present Today</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #f59e0b;"><?php echo $today_present; ?></h3>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <!-- Filters Card -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
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
                            
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Face Status</label>
                                <select name="face_status" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="">All Students</option>
                                    <option value="registered" <?php echo $face_status === 'registered' ? 'selected' : ''; ?>>Face Registered</option>
                                    <option value="not_registered" <?php echo $face_status === 'not_registered' ? 'selected' : ''; ?>>Not Registered</option>
                                </select>
                            </div>
                            
                            <div style="flex: 2; min-width: 200px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                                <div style="position: relative;">
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name, ID or email..." class="form-input" style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="students.php" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.6rem 1rem; border-radius: 8px; font-weight: 500; border: 1px solid var(--border-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Students Grid -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title"><i class="fas fa-user-graduate"></i> Students List</h3>
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?php echo count($students); ?> student<?php echo count($students) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="card-body" style="padding: 1rem;">
                        <?php if (empty($students)): ?>
                        <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-user-graduate" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p style="font-size: 1rem;">No students found matching your filters</p>
                        </div>
                        <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                            <?php foreach ($students as $student): 
                                $attendance_rate = $student['total_days'] > 0 ? round(($student['present_days'] / $student['total_days']) * 100) : 0;
                            ?>
                            <div class="student-card" style="background: var(--bg-secondary); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); transition: all 0.2s ease;">
                                <div style="display: flex; gap: 1rem;">
                                    <!-- Avatar -->
                                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.25rem; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                    </div>
                                    
                                    <!-- Info -->
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                                            <div>
                                                <h4 style="font-weight: 600; color: var(--text-primary); font-size: 1rem; margin-bottom: 0.25rem;">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </h4>
                                                <p style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($student['student_number']); ?></p>
                                            </div>
                                            
                                            <!-- Face Status Badge -->
                                            <?php if ($student['has_face_data']): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 6px; font-size: 0.7rem; font-weight: 600;">
                                                <i class="fas fa-face-smile"></i> Face
                                            </span>
                                            <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 6px; font-size: 0.7rem; font-weight: 600;">
                                                <i class="fas fa-face-frown"></i> No Face
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Details -->
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-secondary);">
                                            <span><i class="fas fa-graduation-cap" style="margin-right: 0.25rem; color: #8b5cf6;"></i> Grade <?php echo htmlspecialchars($student['grade'] . '-' . $student['class_section']); ?></span>
                                            <span><i class="fas fa-envelope" style="margin-right: 0.25rem; color: #3b82f6;"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                        </div>
                                        
                                        <!-- Attendance Bar -->
                                        <div style="margin-top: 0.75rem;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="font-size: 0.7rem; color: var(--text-tertiary);">Attendance Rate</span>
                                                <span style="font-size: 0.7rem; font-weight: 600; color: <?php echo $attendance_rate >= 80 ? '#10b981' : ($attendance_rate >= 60 ? '#f59e0b' : '#ef4444'); ?>"><?php echo $attendance_rate; ?>%</span>
                                            </div>
                                            <div style="height: 4px; background: var(--border-color); border-radius: 2px; overflow: hidden;">
                                                <div style="height: 100%; width: <?php echo $attendance_rate; ?>%; background: <?php echo $attendance_rate >= 80 ? '#10b981' : ($attendance_rate >= 60 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 2px;"></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                            <button onclick="viewStudent(<?php echo $student['student_id']; ?>)" class="btn" style="flex: 1; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; font-size: 0.8rem; font-weight: 500;">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button onclick="viewAttendance(<?php echo $student['student_id']; ?>)" class="btn" style="flex: 1; padding: 0.5rem; border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.1); color: #3b82f6; cursor: pointer; font-size: 0.8rem; font-weight: 500;">
                                                <i class="fas fa-calendar"></i> Attendance
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Student Detail Modal -->
    <div id="studentModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 600px; width: 90%; max-height: 90vh; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-weight: 600; color: var(--text-primary);"><i class="fas fa-user-graduate" style="color: #8b5cf6; margin-right: 0.5rem;"></i> Student Details</h3>
                <button onclick="closeModal('studentModal')" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.25rem;">&times;</button>
            </div>
            <div id="studentModalContent" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    
    <!-- Attendance Modal -->
    <div id="attendanceModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 700px; width: 90%; max-height: 90vh; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-weight: 600; color: var(--text-primary);"><i class="fas fa-calendar-check" style="color: #3b82f6; margin-right: 0.5rem;"></i> Attendance History</h3>
                <button onclick="closeModal('attendanceModal')" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.25rem;">&times;</button>
            </div>
            <div id="attendanceModalContent" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    
    <style>
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: rgba(139, 92, 246, 0.3);
        }
    </style>
    
    <script>
        async function viewStudent(studentId) {
            document.getElementById('studentModalContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-secondary);"></i></div>';
            document.getElementById('studentModal').style.display = 'flex';
            
            try {
                const response = await fetch(`includes/get_student_details.php?student_id=${studentId}`);
                const html = await response.text();
                document.getElementById('studentModalContent').innerHTML = html;
            } catch (error) {
                document.getElementById('studentModalContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Failed to load student details</div>';
            }
        }
        
        async function viewAttendance(studentId) {
            document.getElementById('attendanceModalContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-secondary);"></i></div>';
            document.getElementById('attendanceModal').style.display = 'flex';
            
            try {
                const response = await fetch(`includes/get_student_attendance.php?student_id=${studentId}`);
                const html = await response.text();
                document.getElementById('attendanceModalContent').innerHTML = html;
            } catch (error) {
                document.getElementById('attendanceModalContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Failed to load attendance history</div>';
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal on outside click
        document.querySelectorAll('#studentModal, #attendanceModal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
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
