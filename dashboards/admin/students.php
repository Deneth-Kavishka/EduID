<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get all students with their details
$query = "SELECT s.*, u.username, u.email, u.status, u.created_at,
          p.first_name as parent_fname, p.last_name as parent_lname, p.phone as parent_phone,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status = 'present' AND MONTH(date) = MONTH(CURDATE())) as monthly_attendance,
          (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = s.user_id) as has_face_data
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN parents p ON s.parent_id = p.parent_id
          ORDER BY s.student_number DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total students
$stats['total'] = count($students);

// Active students
$query = "SELECT COUNT(*) as count FROM students s JOIN users u ON s.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Students with face registration
$query = "SELECT COUNT(DISTINCT s.student_id) as count FROM students s JOIN face_recognition_data f ON s.user_id = f.user_id";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['face_registered'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's attendance
$query = "SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE() AND status = 'present'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['present_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get unique grades for filter
$query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - EduID</title>
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
                    <a href="students.php" class="nav-item active">
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
                    <h1>Students Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Students</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search students..." id="searchInput">
                    </div>
                    
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
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['active']; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['face_registered']; ?></h3>
                            <p>Face Registered</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['present_today']; ?></h3>
                            <p>Present Today</p>
                        </div>
                    </div>
                </div>
                
                <!-- Students Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Students</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="gradeFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Grades</option>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade; ?>">Grade <?php echo $grade; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="statusFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <a href="users.php?action=add_student" class="btn btn-outline" style="border: 2px solid var(--primary-color); color: var(--primary-color); font-weight: 600; padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-plus"></i> Add Student
                            </a>
                        </div>
                    </div>
                    <div class="table-container" style="overflow-x: auto;">
                        <table id="studentsTable" style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 8%;">
                                <col style="width: 22%;">
                                <col style="width: 10%;">
                                <col style="width: 15%;">
                                <col style="width: 11%;">
                                <col style="width: 10%;">
                                <col style="width: 8%;">
                                <col style="width: 16%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Grade</th>
                                    <th>Parent</th>
                                    <th>Contact</th>
                                    <th>Face ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr data-grade="<?php echo $student['grade']; ?>" data-status="<?php echo $student['status']; ?>">
                                        <td>
                                            <strong style="color: var(--primary-color); font-size: 0.875rem;"><?php echo htmlspecialchars($student['student_number']); ?></strong>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                                </div>
                                                <div style="overflow: hidden;">
                                                    <div style="color: var(--text-primary); font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                    <div style="color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem;">Grade <?php echo $student['grade']; ?></div>
                                            <div style="color: var(--text-secondary); font-size: 0.75rem;"><?php echo htmlspecialchars($student['class_section']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($student['parent_fname']): ?>
                                                <div style="color: var(--text-primary); font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($student['parent_fname'] . ' ' . $student['parent_lname']); ?>"><?php echo htmlspecialchars($student['parent_fname'] . ' ' . $student['parent_lname']); ?></div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary); font-size: 0.875rem;"><i>Not Linked</i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['parent_phone']): ?>
                                                <div style="font-size: 0.875rem;">
                                                    <i class="fas fa-phone" style="color: var(--success-color); margin-right: 0.25rem; font-size: 0.75rem;"></i>
                                                    <span><?php echo htmlspecialchars($student['parent_phone']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary); font-size: 0.875rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['has_face_data'] > 0): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-check-circle"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-times-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['status'] === 'active'): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Active</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.3rem; align-items: center; justify-content: center;">
                                                <button class="btn btn-sm btn-view" onclick="viewStudent(<?php echo $student['student_id']; ?>)" title="View Details" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <button class="btn btn-sm btn-edit" onclick="editStudent(<?php echo $student['user_id']; ?>)" title="Edit Student" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <button class="btn btn-sm" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); padding: 0.4rem 0.6rem;" onclick="viewAttendance(<?php echo $student['student_id']; ?>)" title="View Attendance">
                                                    <i class="fas fa-calendar-alt" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- View Student Modal -->
    <div id="viewStudentModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-graduate"></i> Student Details</h2>
                <span class="close" onclick="closeViewStudentModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewStudentContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading student details...</p>
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
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 150px);
            overflow-y: auto;
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
        
        .btn-view {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .btn-view:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
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
        
        .detail-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-section:last-of-type {
            border-bottom: none;
        }
        .detail-section h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .detail-item label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .detail-item span {
            color: var(--text-primary);
            font-size: 1rem;
        }
        .stat-icon.info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Grade filter
        document.getElementById('gradeFilter').addEventListener('change', function(e) {
            filterStudents();
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            filterStudents();
        });
        
        function filterStudents() {
            const grade = document.getElementById('gradeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            
            rows.forEach(row => {
                const rowGrade = row.dataset.grade;
                const rowStatus = row.dataset.status;
                
                const gradeMatch = !grade || rowGrade === grade;
                const statusMatch = !status || rowStatus === status;
                
                row.style.display = (gradeMatch && statusMatch) ? '' : 'none';
            });
        }
        
        // View student details
        async function viewStudent(studentId) {
            document.getElementById('viewStudentModal').style.display = 'block';
            
            try {
                const response = await fetch(`student_handler.php?get_student=1&student_id=${studentId}`);
                const student = await response.json();
                
                if (!student || !student.student_id) {
                    document.getElementById('viewStudentContent').innerHTML = '<div class="alert alert-error">Student not found</div>';
                    return;
                }
                
                let html = '<div class="student-details">';
                
                // Personal Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Student Number:</label>
                            <span>${student.student_number}</span>
                        </div>
                        <div class="detail-item">
                            <label>Full Name:</label>
                            <span>${student.first_name} ${student.last_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Date of Birth:</label>
                            <span>${student.date_of_birth || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Gender:</label>
                            <span>${student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Blood Group:</label>
                            <span>${student.blood_group || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Phone:</label>
                            <span>${student.phone || 'N/A'}</span>
                        </div>
                    </div>
                </div>`;
                
                // Academic Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Grade:</label>
                            <span>Grade ${student.grade}</span>
                        </div>
                        <div class="detail-item">
                            <label>Class/Section:</label>
                            <span>${student.class_section}</span>
                        </div>
                        <div class="detail-item">
                            <label>Enrollment Date:</label>
                            <span>${student.enrollment_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span style="color: ${student.status === 'active' ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${student.status ? student.status.toUpperCase() : 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>`;
                
                // Parent Information
                if (student.parent_fname) {
                    html += `<div class="detail-section">
                        <h3><i class="fas fa-users"></i> Parent/Guardian Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Name:</label>
                                <span>${student.parent_fname} ${student.parent_lname}</span>
                            </div>
                            <div class="detail-item">
                                <label>Phone:</label>
                                <span>${student.parent_phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Relationship:</label>
                                <span>${student.relationship || 'N/A'}</span>
                            </div>
                        </div>
                    </div>`;
                }
                
                // Account Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-key"></i> Account Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Username:</label>
                            <span>${student.username}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${student.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>Face Recognition:</label>
                            <span style="color: ${student.has_face_data ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${student.has_face_data ? 'Registered' : 'Not Set'}
                            </span>
                        </div>
                    </div>
                </div>`;
                
                html += '</div>';
                html += `<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeViewStudentModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button class="btn btn-primary" onclick="closeViewStudentModal(); editStudent(${student.user_id});" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-edit"></i> Edit Student
                    </button>
                </div>`;
                
                document.getElementById('viewStudentContent').innerHTML = html;
            } catch (error) {
                document.getElementById('viewStudentContent').innerHTML = '<div class="alert alert-error">Error loading student details</div>';
            }
        }
        
        function closeViewStudentModal() {
            document.getElementById('viewStudentModal').style.display = 'none';
        }
        
        function editStudent(userId) {
            window.location.href = `users.php?edit_student=${userId}`;
        }
        
        function viewAttendance(studentId) {
            window.location.href = `attendance.php?student_id=${studentId}`;
        }
    </script>
</body>
</html>
