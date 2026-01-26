<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');
$selected_exam = $_GET['exam_id'] ?? '';

// Get today's exams and upcoming exams
$query = "SELECT e.*, h.hall_name, h.capacity,
          (SELECT COUNT(*) FROM exam_seat_assignments esa WHERE esa.exam_id = e.exam_id) as assigned_students,
          (SELECT COUNT(*) FROM exam_attendance ea WHERE ea.exam_id = e.exam_id AND ea.status = 'present') as present_count
          FROM exams e 
          LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
          WHERE e.status IN ('scheduled', 'ongoing') AND e.exam_date >= CURDATE()
          ORDER BY e.exam_date ASC, e.start_time";
$stmt = $conn->prepare($query);
$stmt->execute();
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get exam details and students if exam selected
$exam = null;
$students = [];
$attendance_stats = ['present' => 0, 'absent' => 0, 'total' => 0];

if ($selected_exam) {
    // Get exam details
    $query = "SELECT e.*, h.hall_name FROM exams e 
              LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
              WHERE e.exam_id = :exam_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':exam_id' => $selected_exam]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exam) {
        // Get assigned students with attendance status
        $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                  esa.seat_number, esa.is_eligible,
                  ea.status as attendance_status, ea.entry_time, ea.verification_method
                  FROM exam_seat_assignments esa
                  JOIN students s ON esa.student_id = s.student_id
                  LEFT JOIN exam_attendance ea ON esa.exam_id = ea.exam_id AND esa.student_id = ea.student_id
                  WHERE esa.exam_id = :exam_id AND esa.is_eligible = 1
                  ORDER BY esa.seat_number, s.student_number";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':exam_id' => $selected_exam]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no assigned students, get all eligible from grade
        if (count($students) == 0) {
            $where = "s.grade = :grade AND u.status = 'active'";
            $params = [':grade' => $exam['grade']];
            
            if ($exam['class_section']) {
                $where .= " AND s.class_section = :section";
                $params[':section'] = $exam['class_section'];
            }
            
            $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                      NULL as seat_number, 1 as is_eligible,
                      ea.status as attendance_status, ea.entry_time, ea.verification_method
                      FROM students s
                      JOIN users u ON s.user_id = u.user_id
                      LEFT JOIN exam_attendance ea ON s.student_id = ea.student_id AND ea.exam_id = :exam_id
                      WHERE {$where}
                      ORDER BY s.student_number";
            
            $params[':exam_id'] = $selected_exam;
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Calculate stats
        foreach ($students as $student) {
            if ($student['attendance_status'] === 'present') {
                $attendance_stats['present']++;
            } else {
                $attendance_stats['absent']++;
            }
        }
        $attendance_stats['total'] = count($students);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Attendance - EduID</title>
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
                    <a href="exam_attendance.php" class="nav-item active">
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
                    <h1>Exam Attendance</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Examinations</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Attendance</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Select Exam -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <h3 style="color: var(--text-primary); margin-bottom: 1rem;"><i class="fas fa-clipboard-list"></i> Select Exam for Attendance</h3>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Exam</label>
                                <select id="examSelect" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                    <option value="">Select an Exam</option>
                                    <?php foreach ($exams as $e): ?>
                                        <option value="<?php echo $e['exam_id']; ?>" <?php echo $selected_exam == $e['exam_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($e['exam_name']); ?> - Grade <?php echo $e['grade']; ?><?php echo $e['class_section'] ? " ({$e['class_section']})" : ''; ?> | <?php echo date('M d, Y', strtotime($e['exam_date'])); ?> <?php echo date('h:i A', strtotime($e['start_time'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button class="btn btn-primary" style="width: 100%; padding: 0.75rem;" onclick="loadExam()">
                                    <i class="fas fa-search"></i> Load Exam
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($exam): ?>
                <!-- Exam Info -->
                <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--primary-color), #6366f1);">
                    <div class="card-body" style="padding: 1.5rem; color: white;">
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                            <div>
                                <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.25rem;">Exam</div>
                                <div style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.25rem;">Hall</div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($exam['hall_name'] ?: 'Not Assigned'); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.25rem;">Time</div>
                                <div style="font-weight: 600;"><?php echo date('h:i A', strtotime($exam['start_time'])); ?> - <?php echo date('h:i A', strtotime($exam['end_time'])); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.25rem;">Status</div>
                                <div style="font-weight: 600;"><?php echo ucfirst($exam['status']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="stats-grid" style="margin-bottom: 1.5rem;">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['total']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['present']; ?></h3>
                            <p>Present</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['absent']; ?></h3>
                            <p>Absent</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $attendance_stats['total'] > 0 ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 1) : 0; ?>%</h3>
                            <p>Attendance</p>
                        </div>
                    </div>
                </div>
                
                <!-- Students Attendance List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-check"></i> Mark Exam Attendance</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="searchInput" placeholder="Search students..." style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                            <button class="btn" style="background: var(--success-color); color: white;" onclick="markAllPresent()">
                                <i class="fas fa-check-double"></i> All Present
                            </button>
                            <button class="btn btn-primary" onclick="saveExamAttendance()">
                                <i class="fas fa-save"></i> Save Attendance
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 12%;">
                                <col style="width: 25%;">
                                <col style="width: 10%;">
                                <col style="width: 10%;">
                                <col style="width: 12%;">
                                <col style="width: 31%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Section</th>
                                    <th>Seat</th>
                                    <th>Entry Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr data-student="<?php echo $student['student_id']; ?>">
                                            <td>
                                                <strong style="color: var(--primary-color); font-size: 0.875rem;"><?php echo htmlspecialchars($student['student_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; flex-shrink: 0;">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                                    </div>
                                                    <div style="overflow: hidden;">
                                                        <div style="font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.875rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['class_section']); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.875rem; color: var(--text-secondary);"><?php echo $student['seat_number'] ?: '-'; ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?php echo $student['entry_time'] ? date('h:i A', strtotime($student['entry_time'])) : '-'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="attendance-buttons" style="display: flex; gap: 0.5rem;">
                                                    <button class="status-btn <?php echo $student['attendance_status'] === 'present' ? 'selected' : ''; ?>" data-status="present" onclick="selectExamStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--success-color); background: <?php echo $student['attendance_status'] === 'present' ? 'var(--success-color)' : 'transparent'; ?>; color: <?php echo $student['attendance_status'] === 'present' ? 'white' : 'var(--success-color)'; ?>; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem;">
                                                        <i class="fas fa-check"></i> Present
                                                    </button>
                                                    <button class="status-btn <?php echo $student['attendance_status'] === 'absent' ? 'selected' : ''; ?>" data-status="absent" onclick="selectExamStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--danger-color); background: <?php echo $student['attendance_status'] === 'absent' ? 'var(--danger-color)' : 'transparent'; ?>; color: <?php echo $student['attendance_status'] === 'absent' ? 'white' : 'var(--danger-color)'; ?>; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem;">
                                                        <i class="fas fa-times"></i> Absent
                                                    </button>
                                                    <button class="status-btn <?php echo $student['attendance_status'] === 'late' ? 'selected' : ''; ?>" data-status="late" onclick="selectExamStatus(this)" style="padding: 0.5rem 1rem; border: 2px solid var(--warning-color); background: <?php echo $student['attendance_status'] === 'late' ? 'var(--warning-color)' : 'transparent'; ?>; color: <?php echo $student['attendance_status'] === 'late' ? 'white' : 'var(--warning-color)'; ?>; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem;">
                                                        <i class="fas fa-clock"></i> Late
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p>No students assigned to this exam yet.</p>
                                            <a href="exam_eligibility.php?exam_id=<?php echo $selected_exam; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                                <i class="fas fa-check-double"></i> Check Eligibility & Assign
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <!-- No Exam Selected -->
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-user-check" style="font-size: 4rem; color: var(--primary-color); opacity: 0.3; margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Select an Exam</h3>
                        <p style="color: var(--text-secondary);">Choose an exam from the dropdown above to mark attendance.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <style>
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
        let examAttendance = {};
        
        function loadExam() {
            const examId = document.getElementById('examSelect').value;
            if (examId) {
                window.location.href = `exam_attendance.php?exam_id=${examId}`;
            }
        }
        
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        function selectExamStatus(btn) {
            const row = btn.closest('tr');
            const studentId = row.dataset.student;
            const status = btn.dataset.status;
            
            // Remove selected from siblings
            const siblings = row.querySelectorAll('.status-btn');
            siblings.forEach(s => {
                s.classList.remove('selected');
                s.style.background = 'transparent';
                s.style.color = s.style.borderColor;
            });
            
            // Add selected to clicked button
            btn.classList.add('selected');
            btn.style.color = 'white';
            
            // Store in attendance data
            examAttendance[studentId] = status;
        }
        
        function markAllPresent() {
            document.querySelectorAll('.status-btn[data-status="present"]').forEach(btn => {
                selectExamStatus(btn);
            });
        }
        
        async function saveExamAttendance() {
            if (Object.keys(examAttendance).length === 0) {
                alert('Please mark at least one student');
                return;
            }
            
            try {
                const response = await fetch('exam_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'bulk_exam_attendance',
                        exam_id: '<?php echo $selected_exam; ?>',
                        attendance: examAttendance
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Exam attendance saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving attendance');
            }
        }
    </script>
</body>
</html>
