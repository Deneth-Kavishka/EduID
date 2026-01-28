<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new student
    if ($action === 'add_student') {
        try {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $student_id = trim($_POST['student_id'] ?? '');
            $password = $_POST['password'] ?? '';
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            $gender = $_POST['gender'] ?? null;
            $grade = $_POST['grade'] ?? null;
            $section = $_POST['section'] ?? null;
            $phone = $_POST['phone'] ?? null;
            
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email) || empty($student_id) || empty($password)) {
                throw new Exception('All required fields must be filled');
            }
            
            // Check if email already exists
            $query = "SELECT user_id FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            // Check if student ID already exists
            $query = "SELECT student_id FROM students WHERE student_number = :student_number";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_number', $student_id);
            $stmt->execute();
            if ($stmt->fetch()) {
                throw new Exception('Student ID already exists');
            }
            
            $conn->beginTransaction();
            
            // Create username from first name and last name
            $username = strtolower($first_name . '.' . $last_name);
            $username = preg_replace('/[^a-z0-9.]/', '', $username);
            
            // Check if username exists, append number if needed
            $base_username = $username;
            $counter = 1;
            while (true) {
                $query = "SELECT user_id FROM users WHERE username = :username";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                if (!$stmt->fetch()) break;
                $username = $base_username . $counter;
                $counter++;
            }
            
            // Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password_hash, user_role, status, created_by) 
                      VALUES (:username, :email, :password, 'student', 'active', :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $user_id = $conn->lastInsertId();
            
            // Create student record
            $query = "INSERT INTO students (user_id, student_number, first_name, last_name, date_of_birth, gender, grade, section, phone) 
                      VALUES (:user_id, :student_number, :first_name, :last_name, :dob, :gender, :grade, :section, :phone)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':student_number', $student_id);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':dob', $date_of_birth);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':grade', $grade);
            $stmt->bindParam(':section', $section);
            $stmt->bindParam(':phone', $phone);
            $stmt->execute();
            
            $conn->commit();
            
            $success = 'Student "' . htmlspecialchars($first_name . ' ' . $last_name) . '" added successfully!';
            
            // Redirect if came from dashboard
            if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false) {
                header('Location: index.php?success=' . urlencode($success));
                exit;
            }
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

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
    <!-- Use vladmandic face-api fork for better compatibility -->
    <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js"></script>
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
                    
                    <?php include 'includes/profile_dropdown.php'; ?>
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
                                <col style="width: 12%;">
                                <col style="width: 20%;">
                                <col style="width: 10%;">
                                <col style="width: 13%;">
                                <col style="width: 11%;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
                                <col style="width: 14%;">
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
    
    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #a855f7, #6366f1); color: white; border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;">
                <h2 style="color: white;"><i class="fas fa-user-edit"></i> Edit Student</h2>
                <span class="close" onclick="closeEditStudentModal()" style="color: white;">&times;</span>
            </div>
            <div class="modal-body" id="editStudentContent">
                <form id="editStudentForm">
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-user" style="margin-right: 0.25rem;"></i> First Name *
                            </label>
                            <input type="text" name="first_name" id="edit_first_name" required 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-user" style="margin-right: 0.25rem;"></i> Last Name *
                            </label>
                            <input type="text" name="last_name" id="edit_last_name" required 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-envelope" style="margin-right: 0.25rem;"></i> Email *
                            </label>
                            <input type="email" name="email" id="edit_email" required 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-phone" style="margin-right: 0.25rem;"></i> Phone
                            </label>
                            <input type="tel" name="phone" id="edit_phone" 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-calendar" style="margin-right: 0.25rem;"></i> Date of Birth
                            </label>
                            <input type="date" name="date_of_birth" id="edit_date_of_birth" 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-venus-mars" style="margin-right: 0.25rem;"></i> Gender
                            </label>
                            <select name="gender" id="edit_gender" 
                                    style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-tint" style="margin-right: 0.25rem;"></i> Blood Group
                            </label>
                            <select name="blood_group" id="edit_blood_group" 
                                    style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                                <option value="">Select</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-graduation-cap" style="margin-right: 0.25rem;"></i> Grade *
                            </label>
                            <select name="grade" id="edit_grade" required 
                                    style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                                <?php for ($i = 1; $i <= 13; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-chalkboard" style="margin-right: 0.25rem;"></i> Class/Section *
                            </label>
                            <input type="text" name="class_section" id="edit_class_section" required 
                                   style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                                <i class="fas fa-toggle-on" style="margin-right: 0.25rem;"></i> Status *
                            </label>
                            <select name="status" id="edit_status" required 
                                    style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary); font-size: 0.85rem;">
                            <i class="fas fa-map-marker-alt" style="margin-right: 0.25rem;"></i> Address
                        </label>
                        <textarea name="address" id="edit_address" rows="2" 
                                  style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem; resize: vertical;"></textarea>
                    </div>
                    
                    <!-- Face Recognition Section -->
                    <div id="faceRecognitionSection" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <label style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <i class="fas fa-camera" style="margin-right: 0.5rem; color: #22c55e;"></i> Face Recognition
                            </label>
                            <span id="faceStatusBadge" style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;"></span>
                        </div>
                        <div id="faceActionButtons" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <!-- Buttons will be populated by JS -->
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn btn-secondary" onclick="closeEditStudentModal()" style="padding: 0.6rem 1.25rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Student Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;">
                <h2 style="color: white;"><i class="fas fa-calendar-check"></i> Student Attendance</h2>
                <span class="close" onclick="closeAttendanceModal()" style="color: white;">&times;</span>
            </div>
            <div class="modal-body" id="attendanceContent" style="padding: 1.5rem;">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading attendance data...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Face Registration Modal -->
    <div id="faceRegModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;">
                <h2 style="color: white;"><i class="fas fa-camera"></i> <span id="faceRegTitle">Register Face</span></h2>
                <span class="close" onclick="closeFaceRegModal()" style="color: white;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="faceRegStudentInfo" style="margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #22c55e, #16a34a); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;" id="faceRegAvatar">ST</div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary);" id="faceRegStudentName">Student Name</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);" id="faceRegStudentClass">Class</div>
                    </div>
                </div>
                
                <!-- Face API Status -->
                <div id="faceApiStatus" style="margin-bottom: 1rem; padding: 0.75rem; border-radius: 8px; text-align: center; display: none;">
                    <i class="fas fa-spinner fa-spin"></i> <span id="faceApiStatusText">Loading face detection models...</span>
                </div>
                
                <div style="position: relative; width: 100%; max-width: 400px; margin: 0 auto;">
                    <video id="faceRegVideo" style="width: 100%; border-radius: 10px; background: #000;" autoplay playsinline></video>
                    <canvas id="faceRegCanvas" style="display: none;"></canvas>
                    <canvas id="faceDetectionCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
                    <div id="faceRegOverlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.7); border-radius: 10px;">
                        <div style="text-align: center; color: white;">
                            <i class="fas fa-video" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>Click "Start Camera" to begin</p>
                        </div>
                    </div>
                </div>
                
                <!-- Face Detection Feedback -->
                <div id="faceDetectionFeedback" style="margin-top: 0.75rem; padding: 0.5rem; border-radius: 8px; text-align: center; font-size: 0.85rem; display: none;">
                </div>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        <i class="fas fa-info-circle"></i> Position your face clearly in the camera view
                    </p>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <button type="button" id="startCameraBtn" class="btn btn-primary" onclick="startFaceCamera()" style="padding: 0.5rem 1rem;">
                            <i class="fas fa-video"></i> Start Camera
                        </button>
                        <button type="button" id="captureFaceBtn" class="btn btn-primary" onclick="captureFaceWithDescriptor()" style="padding: 0.5rem 1rem; display: none;" disabled>
                            <i class="fas fa-camera"></i> Capture Face
                        </button>
                        <button type="button" id="saveFaceBtn" class="btn btn-primary" onclick="saveFaceData()" style="padding: 0.5rem 1rem; display: none; background: #22c55e;">
                            <i class="fas fa-save"></i> Save Face Data
                        </button>
                        <button type="button" id="retakeFaceBtn" class="btn btn-secondary" onclick="retakeFace()" style="padding: 0.5rem 1rem; display: none;">
                            <i class="fas fa-redo"></i> Retake
                        </button>
                    </div>
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
        
        // Current student data for face registration
        let currentStudentData = null;
        let faceStream = null;
        let capturedImageData = null;
        let capturedFaceDescriptor = null;
        let faceDetectionInterval = null;
        
        // Edit student - open modal
        async function editStudent(userId) {
            document.getElementById('editStudentModal').style.display = 'block';
            
            try {
                const response = await fetch(`student_handler.php?get_student_by_user=1&user_id=${userId}`);
                const student = await response.json();
                
                if (!student || !student.student_id) {
                    alert('Student not found');
                    closeEditStudentModal();
                    return;
                }
                
                currentStudentData = student;
                
                // Populate form fields
                document.getElementById('edit_student_id').value = student.student_id;
                document.getElementById('edit_user_id').value = student.user_id;
                document.getElementById('edit_first_name').value = student.first_name || '';
                document.getElementById('edit_last_name').value = student.last_name || '';
                document.getElementById('edit_email').value = student.email || '';
                document.getElementById('edit_phone').value = student.phone || '';
                document.getElementById('edit_date_of_birth').value = student.date_of_birth || '';
                document.getElementById('edit_gender').value = student.gender || '';
                document.getElementById('edit_blood_group').value = student.blood_group || '';
                document.getElementById('edit_grade').value = student.grade || '';
                document.getElementById('edit_class_section').value = student.class_section || '';
                document.getElementById('edit_status').value = student.status || 'active';
                document.getElementById('edit_address').value = student.address || '';
                
                // Update face recognition section
                updateFaceRecognitionSection(student);
            } catch (error) {
                console.error('Error loading student:', error);
                alert('Error loading student details');
                closeEditStudentModal();
            }
        }
        
        function updateFaceRecognitionSection(student) {
            const badge = document.getElementById('faceStatusBadge');
            const buttons = document.getElementById('faceActionButtons');
            
            if (student.has_face_data > 0) {
                badge.innerHTML = '<i class="fas fa-check-circle"></i> Registered';
                badge.style.background = 'rgba(34, 197, 94, 0.1)';
                badge.style.color = '#22c55e';
                
                buttons.innerHTML = `
                    <button type="button" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2);" onclick="openFaceRegModal(true)">
                        <i class="fas fa-sync-alt"></i> Update Face Data
                    </button>
                    <button type="button" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);" onclick="deleteFaceData()">
                        <i class="fas fa-trash"></i> Delete Face Data
                    </button>
                `;
            } else {
                badge.innerHTML = '<i class="fas fa-times-circle"></i> Not Registered';
                badge.style.background = 'rgba(239, 68, 68, 0.1)';
                badge.style.color = '#ef4444';
                
                buttons.innerHTML = `
                    <button type="button" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2);" onclick="openFaceRegModal(false)">
                        <i class="fas fa-camera"></i> Register Face
                    </button>
                `;
            }
        }
        
        function openFaceRegModal(isUpdate) {
            if (!currentStudentData) return;
            
            document.getElementById('faceRegTitle').textContent = isUpdate ? 'Update Face Data' : 'Register Face';
            document.getElementById('faceRegStudentName').textContent = `${currentStudentData.first_name} ${currentStudentData.last_name}`;
            document.getElementById('faceRegStudentClass').textContent = `Grade ${currentStudentData.grade} - ${currentStudentData.class_section}`;
            document.getElementById('faceRegAvatar').textContent = (currentStudentData.first_name[0] + currentStudentData.last_name[0]).toUpperCase();
            
            // Reset UI
            document.getElementById('faceRegOverlay').style.display = 'flex';
            document.getElementById('startCameraBtn').style.display = 'inline-flex';
            document.getElementById('captureFaceBtn').style.display = 'none';
            document.getElementById('captureFaceBtn').disabled = true;
            document.getElementById('saveFaceBtn').style.display = 'none';
            document.getElementById('retakeFaceBtn').style.display = 'none';
            document.getElementById('faceApiStatus').style.display = 'none';
            document.getElementById('faceDetectionFeedback').style.display = 'none';
            capturedImageData = null;
            capturedFaceDescriptor = null;
            faceDetectionInterval = null;
            
            document.getElementById('faceRegModal').style.display = 'block';
        }
        
        function closeFaceRegModal() {
            stopFaceCamera();
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            document.getElementById('faceRegModal').style.display = 'none';
        }
        
        // Load face-api.js models
        let faceApiModelsLoaded = false;
        async function loadFaceApiModels() {
            if (faceApiModelsLoaded) return true;
            
            const statusDiv = document.getElementById('faceApiStatus');
            const statusText = document.getElementById('faceApiStatusText');
            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(59, 130, 246, 0.1)';
            statusDiv.style.color = '#3b82f6';
            statusText.textContent = 'Loading face detection models...';
            
            try {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model';
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                
                faceApiModelsLoaded = true;
                statusDiv.style.background = 'rgba(34, 197, 94, 0.1)';
                statusDiv.style.color = '#22c55e';
                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> <span>Face detection models loaded!</span>';
                
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 2000);
                
                return true;
            } catch (error) {
                console.error('Error loading face-api models:', error);
                statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                statusDiv.style.color = '#ef4444';
                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span>Error loading models. Please refresh.</span>';
                return false;
            }
        }
        
        async function startFaceCamera() {
            try {
                // Load face-api models first
                const modelsLoaded = await loadFaceApiModels();
                if (!modelsLoaded) {
                    alert('Failed to load face detection models. Please refresh the page.');
                    return;
                }
                
                faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } });
                const video = document.getElementById('faceRegVideo');
                video.srcObject = faceStream;
                
                // Wait for video to be ready
                await new Promise(resolve => {
                    video.onloadedmetadata = resolve;
                });
                
                document.getElementById('faceRegOverlay').style.display = 'none';
                document.getElementById('startCameraBtn').style.display = 'none';
                document.getElementById('captureFaceBtn').style.display = 'inline-flex';
                
                // Start real-time face detection
                startRealTimeFaceDetection();
                
            } catch (err) {
                alert('Unable to access camera. Please ensure camera permissions are granted.');
                console.error('Camera error:', err);
            }
        }
        
        function stopFaceCamera() {
            if (faceStream) {
                faceStream.getTracks().forEach(track => track.stop());
                faceStream = null;
            }
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
        }
        
        // Real-time face detection for visual feedback
        function startRealTimeFaceDetection() {
            const video = document.getElementById('faceRegVideo');
            const canvas = document.getElementById('faceDetectionCanvas');
            const feedback = document.getElementById('faceDetectionFeedback');
            const captureBtn = document.getElementById('captureFaceBtn');
            
            feedback.style.display = 'block';
            
            faceDetectionInterval = setInterval(async () => {
                if (!faceStream) return;
                
                try {
                    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks();
                    
                    const ctx = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    if (detection) {
                        // Draw face box
                        const box = detection.detection.box;
                        ctx.strokeStyle = '#22c55e';
                        ctx.lineWidth = 3;
                        ctx.strokeRect(box.x, box.y, box.width, box.height);
                        
                        feedback.style.background = 'rgba(34, 197, 94, 0.1)';
                        feedback.style.color = '#22c55e';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> Face detected! Click Capture when ready.';
                        captureBtn.disabled = false;
                    } else {
                        feedback.style.background = 'rgba(245, 158, 11, 0.1)';
                        feedback.style.color = '#f59e0b';
                        feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No face detected. Please position your face in view.';
                        captureBtn.disabled = true;
                    }
                } catch (error) {
                    console.error('Face detection error:', error);
                }
            }, 200);
        }
        
        // Capture face with descriptor extraction
        async function captureFaceWithDescriptor() {
            const video = document.getElementById('faceRegVideo');
            const canvas = document.getElementById('faceRegCanvas');
            const feedback = document.getElementById('faceDetectionFeedback');
            
            // Stop real-time detection
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            
            feedback.style.background = 'rgba(59, 130, 246, 0.1)';
            feedback.style.color = '#3b82f6';
            feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting face data...';
            
            try {
                // Detect face with full descriptor
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                
                if (!detection) {
                    feedback.style.background = 'rgba(239, 68, 68, 0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.innerHTML = '<i class="fas fa-times-circle"></i> No face detected. Please try again.';
                    startRealTimeFaceDetection();
                    return;
                }
                
                // Store face descriptor (128-dimension Float32Array)
                capturedFaceDescriptor = Array.from(detection.descriptor);
                
                // Capture image
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                capturedImageData = canvas.toDataURL('image/jpeg', 0.9);
                
                // Show captured image
                video.style.display = 'none';
                canvas.style.display = 'block';
                canvas.style.width = '100%';
                canvas.style.borderRadius = '10px';
                document.getElementById('faceDetectionCanvas').style.display = 'none';
                
                stopFaceCamera();
                
                feedback.style.background = 'rgba(34, 197, 94, 0.1)';
                feedback.style.color = '#22c55e';
                feedback.innerHTML = '<i class="fas fa-check-circle"></i> Face captured successfully! (128-point descriptor extracted)';
                
                document.getElementById('captureFaceBtn').style.display = 'none';
                document.getElementById('saveFaceBtn').style.display = 'inline-flex';
                document.getElementById('retakeFaceBtn').style.display = 'inline-flex';
                
            } catch (error) {
                console.error('Error capturing face:', error);
                feedback.style.background = 'rgba(239, 68, 68, 0.1)';
                feedback.style.color = '#ef4444';
                feedback.innerHTML = '<i class="fas fa-times-circle"></i> Error capturing face. Please try again.';
                startRealTimeFaceDetection();
            }
        }
        
        function retakeFace() {
            const video = document.getElementById('faceRegVideo');
            const canvas = document.getElementById('faceRegCanvas');
            
            video.style.display = 'block';
            canvas.style.display = 'none';
            document.getElementById('faceDetectionCanvas').style.display = 'block';
            capturedImageData = null;
            capturedFaceDescriptor = null;
            
            document.getElementById('saveFaceBtn').style.display = 'none';
            document.getElementById('retakeFaceBtn').style.display = 'none';
            document.getElementById('captureFaceBtn').style.display = 'inline-flex';
            document.getElementById('captureFaceBtn').disabled = true;
            
            startFaceCamera();
        }
        
        async function saveFaceData() {
            if (!capturedImageData || !capturedFaceDescriptor || !currentStudentData) {
                alert('No face data captured. Please capture face first.');
                return;
            }
            
            const btn = document.getElementById('saveFaceBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            try {
                const formData = new FormData();
                formData.append('user_id', currentStudentData.user_id);
                formData.append('face_image', capturedImageData);
                formData.append('face_descriptor', JSON.stringify(capturedFaceDescriptor));
                
                const response = await fetch('save_face_admin.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Face data saved successfully! (' + result.descriptor_length + '-point descriptor stored)');
                    currentStudentData.has_face_data = 1;
                    updateFaceRecognitionSection(currentStudentData);
                    closeFaceRegModal();
                } else {
                    alert(result.message || 'Error saving face data');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving face data');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Face Data';
            }
        }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving face data');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Face Data';
            }
        }
        
        async function deleteFaceData() {
            if (!currentStudentData) return;
            
            if (!confirm('Are you sure you want to delete the face recognition data for this student?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_face_data');
                formData.append('user_id', currentStudentData.user_id);
                
                const response = await fetch('student_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Face data deleted successfully!');
                    currentStudentData.has_face_data = 0;
                    updateFaceRecognitionSection(currentStudentData);
                } else {
                    alert(result.message || 'Error deleting face data');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting face data');
            }
        }
        
        function closeEditStudentModal() {
            document.getElementById('editStudentModal').style.display = 'none';
            currentStudentData = null;
        }
        
        // Handle edit form submission
        document.getElementById('editStudentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            try {
                const response = await fetch('student_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Student updated successfully!');
                    closeEditStudentModal();
                    location.reload();
                } else {
                    alert(result.message || 'Error updating student');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating student');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        // View Attendance
        let currentAttendanceMonth = new Date().toISOString().slice(0, 7);
        
        async function viewAttendance(studentId) {
            document.getElementById('attendanceModal').style.display = 'block';
            document.getElementById('attendanceContent').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading attendance data...</p>
                </div>
            `;
            
            await loadAttendanceData(studentId, currentAttendanceMonth);
        }
        
        async function loadAttendanceData(studentId, month) {
            try {
                const response = await fetch(`student_handler.php?get_attendance=1&student_id=${studentId}&month=${month}`);
                const data = await response.json();
                
                if (data.error) {
                    document.getElementById('attendanceContent').innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                    return;
                }
                
                const student = data.student;
                const stats = data.stats || { total_days: 0, present: 0, absent: 0, late: 0, excused: 0 };
                const overall = data.overall || { total_days: 0, present: 0, absent: 0, late: 0 };
                const percentage = data.percentage || 0;
                
                let html = `
                    <!-- Student Info -->
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #d97706); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem; font-weight: 700;">
                            ${(student.first_name[0] + student.last_name[0]).toUpperCase()}
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.2rem;">${student.first_name} ${student.last_name}</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                ${student.student_number} • Grade ${student.grade} - ${student.class_section}
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 2rem; font-weight: 700; color: ${percentage >= 75 ? '#22c55e' : percentage >= 50 ? '#f59e0b' : '#ef4444'};">${percentage}%</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">Overall Attendance</div>
                        </div>
                    </div>
                    
                    <!-- Month Selector -->
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; color: var(--text-primary);"><i class="fas fa-calendar-alt" style="margin-right: 0.5rem; color: #f59e0b;"></i> Monthly Report</h4>
                        <input type="month" value="${month}" onchange="loadAttendanceData(${studentId}, this.value)" 
                               style="padding: 0.4rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.85rem;">
                    </div>
                    
                    <!-- Stats Cards -->
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
                        <div style="text-align: center; padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;">${stats.total_days || 0}</div>
                            <div style="font-size: 0.7rem; color: var(--text-secondary);">Total Days</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.2);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #22c55e;">${stats.present || 0}</div>
                            <div style="font-size: 0.7rem; color: var(--text-secondary);">Present</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444;">${stats.absent || 0}</div>
                            <div style="font-size: 0.7rem; color: var(--text-secondary);">Absent</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;">${stats.late || 0}</div>
                            <div style="font-size: 0.7rem; color: var(--text-secondary);">Late</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: rgba(139, 92, 246, 0.1); border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.2);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;">${stats.excused || 0}</div>
                            <div style="font-size: 0.7rem; color: var(--text-secondary);">Excused</div>
                        </div>
                    </div>
                    
                    <!-- Attendance Records -->
                    <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="position: sticky; top: 0; background: var(--bg-secondary);">
                                <tr>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Status</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Method</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Time</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (data.attendance && data.attendance.length > 0) {
                    const statusStyles = {
                        'present': { bg: 'rgba(34, 197, 94, 0.1)', color: '#22c55e', icon: 'fa-check-circle' },
                        'absent': { bg: 'rgba(239, 68, 68, 0.1)', color: '#ef4444', icon: 'fa-times-circle' },
                        'late': { bg: 'rgba(245, 158, 11, 0.1)', color: '#f59e0b', icon: 'fa-clock' },
                        'excused': { bg: 'rgba(139, 92, 246, 0.1)', color: '#8b5cf6', icon: 'fa-user-shield' }
                    };
                    
                    const methodIcons = {
                        'face': { icon: 'fa-smile', color: '#22c55e' },
                        'qr': { icon: 'fa-qrcode', color: '#8b5cf6' },
                        'manual': { icon: 'fa-user-edit', color: '#f59e0b' }
                    };
                    
                    data.attendance.forEach(record => {
                        const style = statusStyles[record.status] || statusStyles['absent'];
                        const method = methodIcons[record.method] || methodIcons['manual'];
                        const date = new Date(record.date);
                        const formattedDate = date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                        
                        html += `
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.6rem 1rem; font-size: 0.85rem; color: var(--text-primary);">${formattedDate}</td>
                                <td style="padding: 0.6rem 1rem; text-align: center;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 20px; background: ${style.bg}; color: ${style.color}; font-size: 0.7rem; font-weight: 600; text-transform: capitalize;">
                                        <i class="fas ${style.icon}" style="font-size: 0.65rem;"></i> ${record.status}
                                    </span>
                                </td>
                                <td style="padding: 0.6rem 1rem; text-align: center;">
                                    <i class="fas ${method.icon}" style="color: ${method.color};" title="${record.method}"></i>
                                </td>
                                <td style="padding: 0.6rem 1rem; font-size: 0.8rem; color: var(--text-secondary);">
                                    ${record.check_in_time ? record.check_in_time.substring(0, 5) : '-'}
                                </td>
                                <td style="padding: 0.6rem 1rem; font-size: 0.8rem; color: var(--text-secondary);">
                                    ${record.remarks || '-'}
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html += `
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; opacity: 0.3; margin-bottom: 0.5rem; display: block;"></i>
                                No attendance records for this month
                            </td>
                        </tr>
                    `;
                }
                
                html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <button class="btn btn-secondary" onclick="closeAttendanceModal()" style="padding: 0.6rem 1.25rem;">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                `;
                
                document.getElementById('attendanceContent').innerHTML = html;
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('attendanceContent').innerHTML = '<div class="alert alert-error">Error loading attendance data</div>';
            }
        }
        
        function closeAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'none';
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                if (event.target.id === 'faceRegModal') {
                    stopFaceCamera();
                }
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
