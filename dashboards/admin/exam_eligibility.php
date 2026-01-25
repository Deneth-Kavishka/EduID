<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$selected_exam = $_GET['exam_id'] ?? '';

// Get all exams
$query = "SELECT e.*, h.hall_name FROM exams e 
          LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
          WHERE e.status IN ('scheduled', 'ongoing')
          ORDER BY e.exam_date ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get exam details and students if exam selected
$exam = null;
$students = [];
$eligibility_stats = ['eligible' => 0, 'not_eligible' => 0, 'total' => 0];

if ($selected_exam) {
    // Get exam details
    $query = "SELECT e.*, h.hall_name FROM exams e 
              LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
              WHERE e.exam_id = :exam_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':exam_id' => $selected_exam]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exam) {
        // Get students with attendance
        $where = "s.grade = :grade AND u.status = 'active'";
        $params = [':grade' => $exam['grade']];
        
        if ($exam['class_section']) {
            $where .= " AND s.class_section = :section";
            $params[':section'] = $exam['class_section'];
        }
        
        $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                  COUNT(a.attendance_id) as total_classes,
                  SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_classes,
                  ROUND(
                      COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) * 100.0 / 
                      NULLIF(COUNT(a.attendance_id), 0), 0), 2
                  ) as attendance_percentage,
                  esa.is_eligible as override_eligible,
                  esa.eligibility_reason
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN attendance a ON s.student_id = a.student_id
                  LEFT JOIN exam_seat_assignments esa ON s.student_id = esa.student_id AND esa.exam_id = :exam_id
                  WHERE {$where}
                  GROUP BY s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                           esa.is_eligible, esa.eligibility_reason
                  ORDER BY s.student_number";
        
        $params[':exam_id'] = $selected_exam;
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate stats
        foreach ($students as &$student) {
            $student['is_eligible'] = $student['attendance_percentage'] >= $exam['min_attendance_percent'];
            if ($student['is_eligible']) {
                $eligibility_stats['eligible']++;
            } else {
                $eligibility_stats['not_eligible']++;
            }
        }
        $eligibility_stats['total'] = count($students);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Eligibility - EduID</title>
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
                    <a href="exam_attendance.php" class="nav-item">
                        <i class="fas fa-user-check"></i>
                        <span>Exam Attendance</span>
                    </a>
                    <a href="exam_eligibility.php" class="nav-item active">
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
                    <h1>Exam Eligibility Check</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Examinations</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Eligibility</span>
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
                <!-- Select Exam -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <h3 style="color: var(--text-primary); margin-bottom: 1rem;"><i class="fas fa-clipboard-list"></i> Select Exam</h3>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Exam</label>
                                <select id="examSelect" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                    <option value="">Select an Exam</option>
                                    <?php foreach ($exams as $e): ?>
                                        <option value="<?php echo $e['exam_id']; ?>" <?php echo $selected_exam == $e['exam_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($e['exam_name']); ?> - Grade <?php echo $e['grade']; ?><?php echo $e['class_section'] ? " ({$e['class_section']})" : ''; ?> | <?php echo date('M d, Y', strtotime($e['exam_date'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button class="btn btn-primary" style="width: 100%; padding: 0.75rem;" onclick="loadExam()">
                                    <i class="fas fa-search"></i> Check Eligibility
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($exam): ?>
                <!-- Exam Info -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Exam Name</div>
                                <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Grade/Section</div>
                                <div style="font-weight: 600; color: var(--text-primary);">Grade <?php echo $exam['grade']; ?><?php echo $exam['class_section'] ? " - {$exam['class_section']}" : ''; ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Exam Date</div>
                                <div style="font-weight: 600; color: var(--text-primary);"><?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Required Attendance</div>
                                <div style="font-weight: 600; color: var(--danger-color);"><?php echo $exam['min_attendance_percent']; ?>%</div>
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
                            <h3><?php echo $eligibility_stats['total']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $eligibility_stats['eligible']; ?></h3>
                            <p>Eligible</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $eligibility_stats['not_eligible']; ?></h3>
                            <p>Not Eligible</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $eligibility_stats['total'] > 0 ? round(($eligibility_stats['eligible'] / $eligibility_stats['total']) * 100, 1) : 0; ?>%</h3>
                            <p>Eligibility Rate</p>
                        </div>
                    </div>
                </div>
                
                <!-- Students Eligibility List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list-check"></i> Student Eligibility List</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="searchInput" placeholder="Search students..." style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                            <select id="eligibilityFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Students</option>
                                <option value="eligible">Eligible Only</option>
                                <option value="not_eligible">Not Eligible Only</option>
                            </select>
                            <button class="btn btn-primary" onclick="assignEligibleToExam()">
                                <i class="fas fa-user-check"></i> Assign Eligible Students
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
                                <col style="width: 15%;">
                                <col style="width: 12%;">
                                <col style="width: 16%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Section</th>
                                    <th>Classes</th>
                                    <th>Attendance %</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr data-eligible="<?php echo $student['is_eligible'] ? 'eligible' : 'not_eligible'; ?>">
                                            <td>
                                                <strong style="color: var(--primary-color); font-size: 0.875rem;"><?php echo htmlspecialchars($student['student_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo $student['is_eligible'] ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $student['is_eligible'] ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600; flex-shrink: 0;">
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
                                                <span style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?php echo $student['attended_classes']; ?>/<?php echo $student['total_classes']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="flex: 1; height: 8px; background: var(--bg-secondary); border-radius: 4px; overflow: hidden;">
                                                        <div style="width: <?php echo min($student['attendance_percentage'], 100); ?>%; height: 100%; background: <?php echo $student['attendance_percentage'] >= $exam['min_attendance_percent'] ? 'var(--success-color)' : 'var(--danger-color)'; ?>;"></div>
                                                    </div>
                                                    <span style="font-weight: 600; color: <?php echo $student['attendance_percentage'] >= $exam['min_attendance_percent'] ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-size: 0.875rem; min-width: 45px;">
                                                        <?php echo $student['attendance_percentage']; ?>%
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($student['is_eligible']): ?>
                                                    <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: rgba(34, 197, 94, 0.15); color: var(--success-color);">
                                                        <i class="fas fa-check"></i> Eligible
                                                    </span>
                                                <?php else: ?>
                                                    <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: var(--danger-color);">
                                                        <i class="fas fa-times"></i> Not Eligible
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.3rem; justify-content: center;">
                                                    <?php if (!$student['is_eligible']): ?>
                                                    <button class="btn btn-sm" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 0.4rem 0.6rem;" onclick="overrideEligibility(<?php echo $student['student_id']; ?>, true)" title="Grant Eligibility">
                                                        <i class="fas fa-check" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.4rem 0.6rem;" onclick="overrideEligibility(<?php echo $student['student_id']; ?>, false)" title="Revoke Eligibility">
                                                        <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-view" onclick="viewAttendanceDetails(<?php echo $student['student_id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p>No students found for this exam.</p>
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
                        <i class="fas fa-check-double" style="font-size: 4rem; color: var(--primary-color); opacity: 0.3; margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Select an Exam</h3>
                        <p style="color: var(--text-secondary);">Choose an exam from the dropdown above to check student eligibility based on attendance records.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <style>
        .btn-view {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 0.4rem 0.6rem;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function loadExam() {
            const examId = document.getElementById('examSelect').value;
            if (examId) {
                window.location.href = `exam_eligibility.php?exam_id=${examId}`;
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
        
        // Eligibility filter
        document.getElementById('eligibilityFilter')?.addEventListener('change', function(e) {
            const filter = e.target.value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const eligible = row.dataset.eligible;
                row.style.display = (!filter || eligible === filter) ? '' : 'none';
            });
        });
        
        async function assignEligibleToExam() {
            if (!confirm('Assign all eligible students to this exam?')) return;
            
            const examId = '<?php echo $selected_exam; ?>';
            const eligibleStudents = [];
            
            document.querySelectorAll('tbody tr[data-eligible="eligible"]').forEach(row => {
                const studentNumber = row.querySelector('td:first-child strong').textContent;
                // Find student_id - this is a simplified example
            });
            
            alert('Students will be assigned to the exam. This feature needs backend implementation for seat assignment.');
        }
        
        function overrideEligibility(studentId, eligible) {
            const reason = prompt(eligible ? 'Reason for granting eligibility:' : 'Reason for revoking eligibility:');
            if (!reason) return;
            
            alert(`Eligibility override for student ${studentId}: ${eligible ? 'Granted' : 'Revoked'} - ${reason}`);
            // This would make an API call to update the eligibility override
        }
        
        function viewAttendanceDetails(studentId) {
            alert('View attendance details for student ' + studentId);
            // This would open a modal with detailed attendance history
        }
    </script>
</body>
</html>
