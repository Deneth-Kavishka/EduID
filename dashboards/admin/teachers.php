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
    
    // Add new teacher
    if ($action === 'add_teacher') {
        try {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');
            $password = $_POST['password'] ?? '';
            $department = $_POST['department'] ?? null;
            $subject = $_POST['subject'] ?? null;
            $phone = $_POST['phone'] ?? null;
            $qualification = $_POST['qualification'] ?? null;
            
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email) || empty($employee_id) || empty($password)) {
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
            
            // Check if employee ID already exists
            $query = "SELECT teacher_id FROM teachers WHERE employee_id = :employee_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->execute();
            if ($stmt->fetch()) {
                throw new Exception('Employee ID already exists');
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
                      VALUES (:username, :email, :password, 'teacher', 'active', :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $user_id = $conn->lastInsertId();
            
            // Create teacher record
            $query = "INSERT INTO teachers (user_id, employee_id, first_name, last_name, department, subject, phone, qualification) 
                      VALUES (:user_id, :employee_id, :first_name, :last_name, :department, :subject, :phone, :qualification)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':qualification', $qualification);
            $stmt->execute();
            
            $conn->commit();
            
            $success = 'Teacher "' . htmlspecialchars($first_name . ' ' . $last_name) . '" added successfully!';
            
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

// Get all teachers with their details
$query = "SELECT t.*, u.username, u.email, u.status, u.created_at,
          (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = t.user_id) as has_face_data
          FROM teachers t
          JOIN users u ON t.user_id = u.user_id
          ORDER BY t.teacher_id DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total teachers
$stats['total'] = count($teachers);

// Active teachers
$query = "SELECT COUNT(*) as count FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total unique subjects
$query = "SELECT COUNT(DISTINCT subject) as count FROM teachers WHERE subject IS NOT NULL AND subject != ''";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_subjects'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get unique subjects for filter
$query = "SELECT DISTINCT subject FROM teachers WHERE subject IS NOT NULL AND subject != '' ORDER BY subject";
$stmt = $conn->prepare($query);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Management - EduID</title>
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
                    <a href="teachers.php" class="nav-item active">
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
                    <h1>Teachers Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Teachers</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search teachers..." id="searchInput">
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
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Teachers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['active']; ?></h3>
                            <p>Active Teachers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_subjects']; ?></h3>
                            <p>Total Subjects</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Teaching Staff</p>
                        </div>
                    </div>
                </div>
                
                <!-- Teachers Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Teachers</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="subjectFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="statusFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <a href="users.php?action=add_teacher" class="btn btn-outline" style="border: 2px solid var(--primary-color); color: var(--primary-color); font-weight: 600; padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-plus"></i> Add Teacher
                            </a>
                        </div>
                    </div>
                    <div class="table-container" style="overflow-x: auto;">
                        <table id="teachersTable" style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 8%;">
                                <col style="width: 24%;">
                                <col style="width: 15%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 10%;">
                                <col style="width: 8%;">
                                <col style="width: 11%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Subject</th>
                                    <th>Qualification</th>
                                    <th>Contact</th>
                                    <th>Face ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr data-subject="<?php echo htmlspecialchars($teacher['subject'] ?? ''); ?>" data-status="<?php echo $teacher['status']; ?>">
                                        <td>
                                            <strong style="color: var(--primary-color); font-size: 0.875rem;">T-<?php echo str_pad($teacher['teacher_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1)); ?>
                                                </div>
                                                <div style="overflow: hidden;">
                                                    <div style="color: var(--text-primary); font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                                                    <div style="color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($teacher['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: var(--text-primary); font-size: 0.875rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($teacher['subject'] ?? 'Not Assigned'); ?>">
                                                <?php echo htmlspecialchars($teacher['subject'] ?? 'Not Assigned'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: var(--text-primary); font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($teacher['qualification'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($teacher['qualification'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($teacher['phone']): ?>
                                                <div style="font-size: 0.875rem;">
                                                    <i class="fas fa-phone" style="color: var(--success-color); margin-right: 0.25rem; font-size: 0.75rem;"></i>
                                                    <span><?php echo htmlspecialchars($teacher['phone']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary); font-size: 0.875rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($teacher['has_face_data'] > 0): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-check-circle"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-times-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($teacher['status'] === 'active'): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Active</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.3rem; align-items: center; justify-content: center;">
                                                <button class="btn btn-sm btn-view" onclick="viewTeacher(<?php echo $teacher['teacher_id']; ?>)" title="View Details" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <button class="btn btn-sm btn-edit" onclick="editTeacher(<?php echo $teacher['user_id']; ?>)" title="Edit Teacher" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
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
    
    <!-- View Teacher Modal -->
    <div id="viewTeacherModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Teacher Details</h2>
                <span class="close" onclick="closeViewTeacherModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewTeacherContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading teacher details...</p>
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
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#teachersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Subject filter
        document.getElementById('subjectFilter').addEventListener('change', function(e) {
            filterTeachers();
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            filterTeachers();
        });
        
        function filterTeachers() {
            const subject = document.getElementById('subjectFilter').value;
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#teachersTable tbody tr');
            
            rows.forEach(row => {
                const rowSubject = row.dataset.subject;
                const rowStatus = row.dataset.status;
                
                const subjectMatch = !subject || rowSubject === subject;
                const statusMatch = !status || rowStatus === status;
                
                row.style.display = (subjectMatch && statusMatch) ? '' : 'none';
            });
        }
        
        // View teacher details
        async function viewTeacher(teacherId) {
            document.getElementById('viewTeacherModal').style.display = 'block';
            
            try {
                const response = await fetch(`teacher_handler.php?get_teacher=1&teacher_id=${teacherId}`);
                const teacher = await response.json();
                
                if (!teacher || !teacher.teacher_id) {
                    document.getElementById('viewTeacherContent').innerHTML = '<div class="alert alert-error">Teacher not found</div>';
                    return;
                }
                
                let html = '<div class="teacher-details">';
                
                // Personal Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Teacher ID:</label>
                            <span>T-${String(teacher.teacher_id).padStart(4, '0')}</span>
                        </div>
                        <div class="detail-item">
                            <label>Full Name:</label>
                            <span>${teacher.first_name} ${teacher.last_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Date of Birth:</label>
                            <span>${teacher.date_of_birth || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Gender:</label>
                            <span>${teacher.gender ? teacher.gender.charAt(0).toUpperCase() + teacher.gender.slice(1) : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Phone:</label>
                            <span>${teacher.phone || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Address:</label>
                            <span>${teacher.address || 'N/A'}</span>
                        </div>
                    </div>
                </div>`;
                
                // Professional Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Subject:</label>
                            <span>${teacher.subject || 'Not Assigned'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Qualification:</label>
                            <span>${teacher.qualification || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Experience:</label>
                            <span>${teacher.experience ? teacher.experience + ' years' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Join Date:</label>
                            <span>${teacher.join_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span style="color: ${teacher.status === 'active' ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${teacher.status ? teacher.status.toUpperCase() : 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>`;
                
                // Account Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-key"></i> Account Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Username:</label>
                            <span>${teacher.username}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${teacher.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>Face Recognition:</label>
                            <span style="color: ${teacher.has_face_data ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${teacher.has_face_data ? 'Registered' : 'Not Set'}
                            </span>
                        </div>
                    </div>
                </div>`;
                
                html += '</div>';
                html += `<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeViewTeacherModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button class="btn btn-primary" onclick="closeViewTeacherModal(); editTeacher(${teacher.user_id});" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-edit"></i> Edit Teacher
                    </button>
                </div>`;
                
                document.getElementById('viewTeacherContent').innerHTML = html;
            } catch (error) {
                document.getElementById('viewTeacherContent').innerHTML = '<div class="alert alert-error">Error loading teacher details</div>';
            }
        }
        
        function closeViewTeacherModal() {
            document.getElementById('viewTeacherModal').style.display = 'none';
        }
        
        function editTeacher(userId) {
            window.location.href = `users.php?edit_teacher=${userId}`;
        }
    </script>
</body>
</html>
