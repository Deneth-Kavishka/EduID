<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get all users with their role-specific data
$query = "SELECT u.*, 
          s.student_id, s.student_number, s.first_name as student_fname, s.last_name as student_lname, s.grade, s.class_section,
          t.teacher_id, t.employee_number, t.first_name as teacher_fname, t.last_name as teacher_lname, t.department,
          p.parent_id, p.first_name as parent_fname, p.last_name as parent_lname, p.phone
          FROM users u
          LEFT JOIN students s ON u.user_id = s.user_id
          LEFT JOIN teachers t ON u.user_id = t.user_id
          LEFT JOIN parents p ON u.user_id = p.user_id
          ORDER BY u.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts by role
$role_counts = [];
foreach (['admin', 'student', 'teacher', 'parent'] as $role) {
    $query = "SELECT COUNT(*) as count FROM users WHERE user_role = :role AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    $role_counts[$role] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - EduID</title>
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
                    <a href="users.php" class="nav-item active">
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
                    <h1>User Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>User Management</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search users..." id="searchInput">
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
                            <h3><?php echo $role_counts['student']; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $role_counts['teacher']; ?></h3>
                            <p>Active Teachers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $role_counts['parent']; ?></h3>
                            <p>Active Parents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $role_counts['admin']; ?></h3>
                            <p>Administrators</p>
                        </div>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">All Users</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="roleFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="parent">Parent</option>
                            </select>
                            <button class="btn btn-outline" onclick="showAddUserModal()" style="border: 2px solid var(--primary-color); color: var(--primary-color); font-weight: 600; padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-plus"></i> Add User
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                        // Get full name based on role
                                        $full_name = 'N/A';
                                        $role_info = '';
                                        
                                        switch ($user['user_role']) {
                                            case 'student':
                                                $full_name = ($user['student_fname'] ?? '') . ' ' . ($user['student_lname'] ?? '');
                                                $role_info = ($user['student_number'] ?? '') . ' - ' . ($user['grade'] ?? '') . ' ' . ($user['class_section'] ?? '');
                                                break;
                                            case 'teacher':
                                                $full_name = ($user['teacher_fname'] ?? '') . ' ' . ($user['teacher_lname'] ?? '');
                                                $role_info = ($user['employee_number'] ?? '') . ' - ' . ($user['department'] ?? '');
                                                break;
                                            case 'parent':
                                                $full_name = ($user['parent_fname'] ?? '') . ' ' . ($user['parent_lname'] ?? '');
                                                $role_info = $user['phone'] ?? '';
                                                break;
                                            case 'admin':
                                                $full_name = 'Administrator';
                                                $role_info = 'System Admin';
                                                break;
                                        }
                                    ?>
                                    <tr data-role="<?php echo $user['user_role']; ?>">
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($role_info): ?>
                                                <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($role_info); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($full_name); ?></td>
                                        <td>
                                            <?php
                                                $role_colors = [
                                                    'admin' => 'background: rgba(239, 68, 68, 0.1); color: #dc2626;',
                                                    'student' => 'background: rgba(37, 99, 235, 0.1); color: #2563eb;',
                                                    'teacher' => 'background: rgba(34, 197, 94, 0.1); color: #16a34a;',
                                                    'parent' => 'background: rgba(251, 146, 60, 0.1); color: #ea580c;'
                                                ];
                                            ?>
                                            <span style="padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; <?php echo $role_colors[$user['user_role']]; ?>">
                                                <?php echo ucfirst($user['user_role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <span style="color: var(--success-color);"><i class="fas fa-circle"></i> Active</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color);"><i class="fas fa-circle"></i> <?php echo ucfirst($user['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if ($user['last_login']) {
                                                    echo date('M d, Y H:i', strtotime($user['last_login']));
                                                } else {
                                                    echo '<span style="color: var(--text-tertiary);">Never</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.4rem; align-items: center; justify-content: space-between;">
                                                <div style="display: flex; gap: 0.4rem;">
                                                    <button class="btn btn-sm btn-view" onclick="viewUser(<?php echo $user['user_id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-edit" onclick="editUser(<?php echo $user['user_id']; ?>)" title="Edit User">
                                                        <i class="fas fa-edit" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                </div>
                                                <?php if ($user['user_role'] !== 'admin' || $user['user_id'] != $_SESSION['user_id']): ?>
                                                <label class="status-toggle" title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> User">
                                                    <input type="checkbox" 
                                                           <?php echo $user['status'] === 'active' ? 'checked' : ''; ?>
                                                           onchange="toggleUserStatus(<?php echo $user['user_id']; ?>, '<?php echo $user['status']; ?>')">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <?php endif; ?>
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
    
    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> User Details</h2>
                <span class="close" onclick="closeViewUserModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewUserContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading user details...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <span class="close" onclick="closeEditUserModal()">&times;</span>
            </div>
            <div class="modal-body" id="editUserContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading user information...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close" onclick="closeAddUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- User Type Selection -->
                <div id="userTypeSelection">
                    <h3 style="margin-bottom: 1.5rem; text-align: center;">Select User Type</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="user-type-card" onclick="selectUserType('student')">
                            <i class="fas fa-user-graduate" style="font-size: 3rem; color: var(--primary-color);"></i>
                            <h4>Student</h4>
                            <p>Add a new student</p>
                        </div>
                        <div class="user-type-card" onclick="selectUserType('teacher')">
                            <i class="fas fa-chalkboard-teacher" style="font-size: 3rem; color: var(--success-color);"></i>
                            <h4>Teacher</h4>
                            <p>Add a new teacher</p>
                        </div>
                        <div class="user-type-card" onclick="selectUserType('parent')">
                            <i class="fas fa-users" style="font-size: 3rem; color: var(--warning-color);"></i>
                            <h4>Parent</h4>
                            <p>Add a parent/guardian</p>
                        </div>
                        <div class="user-type-card" onclick="selectUserType('admin')">
                            <i class="fas fa-user-shield" style="font-size: 3rem; color: var(--danger-color);"></i>
                            <h4>Administrator</h4>
                            <p>Add an admin user</p>
                        </div>
                    </div>
                </div>
                
                <!-- Student Form -->
                <div id="studentForm" class="user-form" style="display: none;">
                    <button class="btn btn-outline mb-3" onclick="backToUserTypeSelection()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h3>Add New Student</h3>
                    <form id="addStudentForm" onsubmit="submitStudentForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth *</label>
                                <input type="date" name="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone">
                            </div>
                            <div class="form-group">
                                <label>Grade *</label>
                                <select name="grade" required>
                                    <option value="">Select Grade</option>
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Class Section</label>
                                <input type="text" name="class_section" placeholder="e.g., A, B, C">
                            </div>
                            <div class="form-group">
                                <label>Enrollment Date *</label>
                                <input type="date" name="enrollment_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Blood Group <small style="color: var(--text-secondary);">(Optional - for medical emergencies)</small></label>
                                <select name="blood_group">
                                    <option value="">Select Blood Group (Optional)</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact</label>
                                <input type="tel" name="emergency_contact" placeholder="Emergency contact number">
                            </div>
                            <div class="form-group">
                                <label>Parent/Guardian <small style="color: var(--text-secondary);">(Optional - link to parent account)</small></label>
                                <select name="parent_id" id="parentSelect">
                                    <option value="">No Parent Account (Optional)</option>
                                </select>
                                <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                    <i class="fas fa-info-circle"></i> Search by name, phone, or NIC number
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3" placeholder="Student's home address"></textarea>
                        </div>
                        
                        <!-- Quick Add Parent Button -->
                        <div class="alert" style="background: var(--bg-secondary); border-left: 4px solid var(--primary-color); padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 250px;">
                                    <strong><i class="fas fa-lightbulb"></i> Need to add a parent first?</strong>
                                    <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.875rem;">
                                        If the parent doesn't exist yet, you can create their account first, then return to add the student.
                                    </p>
                                </div>
                                <button type="button" class="btn btn-outline" onclick="openParentFormFromStudent()" style="white-space: nowrap;">
                                    <i class="fas fa-user-plus"></i> Add Parent First
                                </button>
                            </div>
                        </div>
                        
                        <!-- Face Registration Section -->
                        <div class="card mt-3" style="background: var(--bg-secondary);">
                            <h4><i class="fas fa-face-smile"></i> Face Registration (Optional)</h4>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem;">Capture student's face for biometric verification</p>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div>
                                    <div class="video-container" style="position: relative; background: #000; border-radius: 8px; overflow: hidden; aspect-ratio: 4/3;">
                                        <video id="studentVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; display: block;"></video>
                                        <canvas id="studentCanvas" style="display: none;"></canvas>
                                        <div id="studentPlaceholder" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: white; background: #000;">
                                            <i class="fas fa-camera" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                            <p>Click "Start Camera" to begin</p>
                                        </div>
                                        <div class="video-overlay" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; border: 3px dashed #4ade80; border-radius: 50%; pointer-events: none;"></div>
                                    </div>
                                    <button type="button" class="btn btn-primary mt-2" id="startStudentCamera" onclick="startStudentCamera()" style="width: 100%;">
                                        <i class="fas fa-camera"></i> Start Camera
                                    </button>
                                    <button type="button" class="btn btn-success mt-2" id="captureStudentFace" onclick="captureStudentFace()" style="width: 100%; display: none;">
                                        <i class="fas fa-camera"></i> Capture Face
                                    </button>
                                </div>
                                <div>
                                    <div id="facePreview" style="display: none;">
                                        <h4>Captured Face</h4>
                                        <img id="capturedFaceImg" style="width: 100%; border-radius: 8px; margin-bottom: 1rem;">
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle"></i> Face captured successfully!
                                        </div>
                                    </div>
                                    <div id="faceStatus" style="padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                                        <p style="color: var(--text-secondary); margin: 0;">
                                            <i class="fas fa-info-circle"></i> Face registration helps with biometric attendance and verification
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="face_descriptor" id="faceDescriptor">
                        </div>
                        
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Student
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Teacher Form -->
                <div id="teacherForm" class="user-form" style="display: none;">
                    <button class="btn btn-outline mb-3" onclick="backToUserTypeSelection()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h3>Add New Teacher</h3>
                    <form id="addTeacherForm" onsubmit="submitTeacherForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth *</label>
                                <input type="date" name="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Phone *</label>
                                <input type="tel" name="phone" required>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" name="department" placeholder="e.g., Science, Mathematics">
                            </div>
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" name="subject" placeholder="e.g., Physics, Chemistry">
                            </div>
                            <div class="form-group">
                                <label>Qualification</label>
                                <input type="text" name="qualification" placeholder="e.g., B.Sc, M.Sc">
                            </div>
                            <div class="form-group">
                                <label>Joining Date *</label>
                                <input type="date" name="joining_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3"></textarea>
                        </div>
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Add Teacher
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Parent Form -->
                <div id="parentForm" class="user-form" style="display: none;">
                    <button class="btn btn-outline mb-3" onclick="backToUserTypeSelection()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h3>Add New Parent/Guardian</h3>
                    <form id="addParentForm" onsubmit="submitParentForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Phone *</label>
                                <input type="tel" name="phone" required placeholder="e.g., 0771234567">
                            </div>
                            <div class="form-group">
                                <label>NIC (National Identity Card) <small style="color: var(--text-secondary);">(Optional - for easy identification)</small></label>
                                <input type="text" name="nic" placeholder="e.g., 199012345678 or 901234567V">
                                <small style="color: var(--text-secondary); display: block; margin-top: 0.25rem;">
                                    <i class="fas fa-info-circle"></i> NIC helps link students to parents easily
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Alternative Phone</label>
                                <input type="tel" name="alternative_phone" placeholder="Additional contact number">
                            </div>
                            <div class="form-group">
                                <label>Relationship *</label>
                                <select name="relationship" required>
                                    <option value="">Select Relationship</option>
                                    <option value="father">Father</option>
                                    <option value="mother">Mother</option>
                                    <option value="guardian">Guardian</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Occupation</label>
                                <input type="text" name="occupation">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3"></textarea>
                        </div>
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Add Parent
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Admin Form -->
                <div id="adminForm" class="user-form" style="display: none;">
                    <button class="btn btn-outline mb-3" onclick="backToUserTypeSelection()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h3>Add New Administrator</h3>
                    <form id="addAdminForm" onsubmit="submitAdminForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Administrators have full system access. Only create admin accounts for trusted users.
                        </div>
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save"></i> Add Administrator
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                        </div>
                    </form>
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
        
        .user-type-card {
            padding: 2rem;
            text-align: center;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .user-type-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .user-type-card h4 {
            margin: 1rem 0 0.5rem;
            color: var(--text-primary);
        }
        
        .user-type-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .mt-3 {
            margin-top: 1.5rem;
        }
        
        .mb-3 {
            margin-bottom: 1.5rem;
        }
        
        /* Ensure buttons in modals are fully visible and properly styled */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
            border-color: var(--text-secondary);
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script>
        let studentVideoStream = null;
        let faceDetectionInterval = null;
        let capturedFaceDescriptor = null;
        let modelsLoaded = false;
        
        // Load Face-API models
        async function loadFaceModels() {
            const MODEL_URL = '../../assets/models';
            try {
                console.log('Loading face detection models...');
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                
                modelsLoaded = true;
                console.log('✓ Face detection models loaded successfully');
            } catch (error) {
                console.error('Error loading face detection models:', error);
                console.log('Face recognition will not be available');
                modelsLoaded = false;
            }
        }
        
        // Load models on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadFaceModels();
        });
        
        // Modal functions
        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
            document.getElementById('userTypeSelection').style.display = 'block';
            hideAllForms();
            loadParentsList();
        }
        
        function loadParentsList() {
            // Load parents for student form
            fetch('add_user_handler.php?get_parents=1')
                .then(response => response.json())
                .then(parents => {
                    const select = document.getElementById('parentSelect');
                    select.innerHTML = '<option value="">No Parent Account (Optional)</option>';
                    
                    if (parents.length === 0) {
                        select.innerHTML += '<option value="" disabled>-- No parents available --</option>';
                    } else {
                        parents.forEach(parent => {
                            const nicInfo = parent.nic ? ` - NIC: ${parent.nic}` : '';
                            select.innerHTML += `<option value="${parent.parent_id}">${parent.first_name} ${parent.last_name} - ${parent.phone}${nicInfo}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading parents:', error);
                });
        }
        
        function openParentFormFromStudent() {
            if (confirm('You will now switch to the Parent form. Your current student information will be cleared.\n\nAfter adding the parent, you can return to add the student.\n\nContinue?')) {
                selectUserType('parent');
            }
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            stopStudentCamera();
            hideAllForms();
            document.querySelectorAll('form').forEach(form => form.reset());
            capturedFaceDescriptor = null;
        }
        
        function hideAllForms() {
            document.getElementById('studentForm').style.display = 'none';
            document.getElementById('teacherForm').style.display = 'none';
            document.getElementById('parentForm').style.display = 'none';
            document.getElementById('adminForm').style.display = 'none';
        }
        
        function selectUserType(type) {
            document.getElementById('userTypeSelection').style.display = 'none';
            hideAllForms();
            document.getElementById(type + 'Form').style.display = 'block';
        }
        
        function backToUserTypeSelection() {
            stopStudentCamera();
            hideAllForms();
            document.getElementById('userTypeSelection').style.display = 'block';
            capturedFaceDescriptor = null;
        }
        
        // Face recognition functions
        async function startStudentCamera() {
            console.log('startStudentCamera() called');
            
            try {
                updateFaceStatus('Requesting camera access...', 'info');
                
                const video = document.getElementById('studentVideo');
                console.log('Video element:', video);
                
                // Check if camera is supported
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera is not supported in this browser. Please use Chrome, Firefox, or Edge.');
                }
                
                console.log('Requesting camera permissions...');
                
                // Request camera access
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    },
                    audio: false
                });
                
                console.log('Camera stream obtained:', stream);
                
                // Set video source and play
                video.srcObject = stream;
                video.style.display = 'block';
                
                // Wait for video to be ready
                await new Promise((resolve, reject) => {
                    video.onloadedmetadata = () => {
                        console.log('Video metadata loaded');
                        video.play()
                            .then(() => {
                                console.log('Video playing');
                                resolve();
                            })
                            .catch(error => {
                                console.error('Error playing video:', error);
                                resolve(); // Still resolve to continue
                            });
                    };
                    
                    // Timeout after 5 seconds
                    setTimeout(() => {
                        console.log('Video load timeout, continuing anyway');
                        resolve();
                    }, 5000);
                });
                
                studentVideoStream = stream;
                
                // Update UI
                const placeholder = document.getElementById('studentPlaceholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                document.getElementById('startStudentCamera').style.display = 'none';
                document.getElementById('captureStudentFace').style.display = 'block';
                
                updateFaceStatus('Camera started! Position your face in the circle.', 'success');
                console.log('Camera UI updated');
                
                // Start face detection only if models are loaded
                if (modelsLoaded && faceapi.nets.tinyFaceDetector.isLoaded) {
                    console.log('Starting face detection...');
                    startFaceDetection();
                } else {
                    updateFaceStatus('Camera ready. Face detection models are loading...', 'info');
                    console.log('Waiting for face detection models to load...');
                }
            } catch (error) {
                console.error('Error starting camera:', error);
                let errorMessage = 'Unable to access camera. ';
                
                if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                    errorMessage += 'Please grant camera permissions and try again.';
                } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                    errorMessage += 'No camera found on your device.';
                } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                    errorMessage += 'Camera is being used by another application.';
                } else {
                    errorMessage += error.message || 'Please check your camera settings.';
                }
                
                updateFaceStatus(errorMessage, 'error');
                alert(errorMessage);
            }
        }
        
        function stopStudentCamera() {
            if (studentVideoStream) {
                studentVideoStream.getTracks().forEach(track => track.stop());
                studentVideoStream = null;
            }
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
        }
        
        function startFaceDetection() {
            const video = document.getElementById('studentVideo');
            
            // Check if video is ready
            if (video.readyState !== video.HAVE_ENOUGH_DATA) {
                console.log('Video not ready yet, waiting...');
                setTimeout(startFaceDetection, 500);
                return;
            }
            
            faceDetectionInterval = setInterval(async () => {
                try {
                    if (!faceapi.nets.tinyFaceDetector.isLoaded) {
                        console.log('Face detection models not loaded yet');
                        return;
                    }
                    
                    const detections = await faceapi.detectAllFaces(
                        video,
                        new faceapi.TinyFaceDetectorOptions()
                    ).withFaceLandmarks();
                    
                    if (detections.length > 0) {
                        updateFaceStatus('Face detected! Click "Capture Face" to save.', 'success');
                    } else {
                        updateFaceStatus('No face detected. Please position your face in the circle.', 'warning');
                    }
                } catch (error) {
                    console.error('Face detection error:', error);
                }
            }, 1000);
        }
        
        async function captureStudentFace() {
            const video = document.getElementById('studentVideo');
            const canvas = document.getElementById('studentCanvas');
            
            try {
                const detection = await faceapi
                    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                
                if (!detection) {
                    updateFaceStatus('No face detected. Please try again.', 'error');
                    return;
                }
                
                // Draw captured face on canvas
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                
                // Show preview
                const capturedImg = document.getElementById('capturedFaceImg');
                capturedImg.src = canvas.toDataURL('image/jpeg');
                document.getElementById('facePreview').style.display = 'block';
                
                // Store face descriptor
                capturedFaceDescriptor = Array.from(detection.descriptor);
                document.getElementById('faceDescriptor').value = JSON.stringify(capturedFaceDescriptor);
                
                updateFaceStatus('Face captured successfully!', 'success');
                
                // Stop camera
                stopStudentCamera();
                document.getElementById('captureStudentFace').style.display = 'none';
            } catch (error) {
                console.error('Error capturing face:', error);
                updateFaceStatus('Error capturing face. Please try again.', 'error');
            }
        }
        
        function updateFaceStatus(message, type) {
            const statusDiv = document.getElementById('faceStatus');
            const colors = {
                'info': 'var(--primary-color)',
                'success': 'var(--success-color)',
                'warning': 'var(--warning-color)',
                'error': 'var(--danger-color)'
            };
            const icons = {
                'info': 'fa-info-circle',
                'success': 'fa-check-circle',
                'warning': 'fa-exclamation-triangle',
                'error': 'fa-times-circle'
            };
            statusDiv.innerHTML = `<p style="color: ${colors[type]}; margin: 0;">
                <i class="fas ${icons[type]}"></i> ${message}
            </p>`;
        }
        
        // Form submission functions
        async function submitStudentForm(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_student');
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: ' + result.message);
                    closeAddUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitTeacherForm(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_teacher');
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: ' + result.message);
                    closeAddUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitParentForm(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_parent');
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: ' + result.message + '\n\nYou can now add a student and link them to this parent.');
                    
                    // Reload parents list
                    loadParentsList();
                    
                    // Ask if they want to add a student now
                    if (confirm('Parent added successfully!\n\nWould you like to add a student now and link them to this parent?')) {
                        selectUserType('student');
                    } else {
                        closeAddUserModal();
                        location.reload();
                    }
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitAdminForm(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add_admin');
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: ' + result.message);
                    closeAddUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target === modal) {
                closeAddUserModal();
            }
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Role filter
        document.getElementById('roleFilter').addEventListener('change', function(e) {
            const role = e.target.value;
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                if (!role || row.dataset.role === role) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // User actions
        async function viewUser(userId) {
            document.getElementById('viewUserModal').style.display = 'block';
            
            try {
                const response = await fetch(`add_user_handler.php?get_user=1&user_id=${userId}`);
                const user = await response.json();
                
                if (!user || !user.user_id) {
                    document.getElementById('viewUserContent').innerHTML = '<div class="alert alert-error">User not found</div>';
                    return;
                }
                
                let html = '<div class="user-details">';
                
                // User Account Info
                html += `<div class="detail-section">
                    <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Username:</label>
                            <span>${user.username || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${user.email || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Role:</label>
                            <span class="badge badge-${user.user_role}">${user.user_role ? user.user_role.toUpperCase() : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="badge badge-${user.status === 'active' ? 'success' : 'danger'}">${user.status ? user.status.toUpperCase() : 'INACTIVE'}</span>
                        </div>
                    </div>
                </div>`;
                
                // Role-specific info
                if (user.user_role === 'student') {
                    html += `<div class="detail-section">
                        <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Student Number:</label>
                                <span>${user.student_number || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span>${user.student_fname} ${user.student_lname}</span>
                            </div>
                            <div class="detail-item">
                                <label>Date of Birth:</label>
                                <span>${user.student_dob || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Gender:</label>
                                <span>${user.student_gender || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Grade:</label>
                                <span>${user.grade || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Class:</label>
                                <span>${user.class_section || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Phone:</label>
                                <span>${user.student_phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Blood Group:</label>
                                <span>${user.blood_group || 'N/A'}</span>
                            </div>
                            <div class="detail-item full-width">
                                <label>Address:</label>
                                <span>${user.student_address || 'N/A'}</span>
                            </div>
                        </div>
                    </div>`;
                } else if (user.user_role === 'teacher') {
                    html += `<div class="detail-section">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Employee Number:</label>
                                <span>${user.employee_number || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span>${user.teacher_fname} ${user.teacher_lname}</span>
                            </div>
                            <div class="detail-item">
                                <label>Department:</label>
                                <span>${user.department || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Subject:</label>
                                <span>${user.subject || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Phone:</label>
                                <span>${user.teacher_phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Qualification:</label>
                                <span>${user.qualification || 'N/A'}</span>
                            </div>
                        </div>
                    </div>`;
                } else if (user.user_role === 'parent') {
                    html += `<div class="detail-section">
                        <h3><i class="fas fa-users"></i> Parent Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span>${user.parent_fname} ${user.parent_lname}</span>
                            </div>
                            <div class="detail-item">
                                <label>Relationship:</label>
                                <span>${user.relationship || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Phone:</label>
                                <span>${user.parent_phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Alternative Phone:</label>
                                <span>${user.alternative_phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Occupation:</label>
                                <span>${user.occupation || 'N/A'}</span>
                            </div>
                        </div>
                    </div>`;
                }
                
                html += '</div>';
                html += `<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeViewUserModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" onclick="closeViewUserModal(); editUser(${userId}, '${user.user_role}');" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-edit"></i> Edit User
                    </button>
                </div>`;
                
                document.getElementById('viewUserContent').innerHTML = html;
            } catch (error) {
                document.getElementById('viewUserContent').innerHTML = '<div class="alert alert-error">Error loading user details</div>';
            }
        }
        
        function closeViewUserModal() {
            document.getElementById('viewUserModal').style.display = 'none';
        }
        
        async function editUser(userId, userRole) {
            document.getElementById('editUserModal').style.display = 'block';
            
            try {
                const response = await fetch(`add_user_handler.php?get_user=1&user_id=${userId}`);
                const user = await response.json();
                
                if (!user || !user.user_id) {
                    document.getElementById('editUserContent').innerHTML = '<div class="alert alert-error">User not found</div>';
                    return;
                }
                
                let html = '';
                
                if (user.user_role === 'student') {
                    html = `
                        <h3>Edit Student Information</h3>
                        <form id="editStudentForm" onsubmit="submitEditStudentForm(event, ${userId})">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Username *</label>
                                    <input type="text" name="username" value="${user.username}" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="${user.email}" required>
                                </div>
                                <div class="form-group">
                                    <label>New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" minlength="6" placeholder="Enter new password or leave blank">
                                </div>
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" value="${user.student_fname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" value="${user.student_lname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth *</label>
                                    <input type="date" name="date_of_birth" value="${user.student_dob}" required>
                                </div>
                                <div class="form-group">
                                    <label>Gender *</label>
                                    <select name="gender" required>
                                        <option value="male" ${user.student_gender === 'male' ? 'selected' : ''}>Male</option>
                                        <option value="female" ${user.student_gender === 'female' ? 'selected' : ''}>Female</option>
                                        <option value="other" ${user.student_gender === 'other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Phone *</label>
                                    <input type="tel" name="phone" value="${user.student_phone}" required>
                                </div>
                                <div class="form-group">
                                    <label>Grade *</label>
                                    <select name="grade" required>
                                        ${['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13'].map(g => 
                                            `<option value="${g}" ${user.grade == g ? 'selected' : ''}>${g}</option>`
                                        ).join('')}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Class/Section *</label>
                                    <input type="text" name="class_section" value="${user.class_section}" placeholder="e.g., A, B, Science" required>
                                </div>
                                <div class="form-group">
                                    <label>Blood Group</label>
                                    <select name="blood_group">
                                        <option value="" ${!user.blood_group ? 'selected' : ''}>Select Blood Group</option>
                                        ${['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map(bg => 
                                            `<option value="${bg}" ${user.blood_group === bg ? 'selected' : ''}>${bg}</option>`
                                        ).join('')}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Parent/Guardian</label>
                                    <select name="parent_id" id="editParentSelect">
                                        <option value="">Select Parent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${user.student_address || ''}</textarea>
                            </div>
                            <div class="form-actions mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Student
                                </button>
                                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancel</button>
                            </div>
                        </form>
                    `;
                } else if (user.user_role === 'teacher') {
                    html = `
                        <h3>Edit Teacher Information</h3>
                        <form id="editTeacherForm" onsubmit="submitEditTeacherForm(event, ${userId})">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Username *</label>
                                    <input type="text" name="username" value="${user.username}" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="${user.email}" required>
                                </div>
                                <div class="form-group">
                                    <label>New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" minlength="6" placeholder="Enter new password or leave blank">
                                </div>
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" value="${user.teacher_fname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" value="${user.teacher_lname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth *</label>
                                    <input type="date" name="date_of_birth" value="${user.teacher_dob}" required>
                                </div>
                                <div class="form-group">
                                    <label>Gender *</label>
                                    <select name="gender" required>
                                        <option value="male" ${user.teacher_gender === 'male' ? 'selected' : ''}>Male</option>
                                        <option value="female" ${user.teacher_gender === 'female' ? 'selected' : ''}>Female</option>
                                        <option value="other" ${user.teacher_gender === 'other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Phone *</label>
                                    <input type="tel" name="phone" value="${user.teacher_phone}" required>
                                </div>
                                <div class="form-group">
                                    <label>Department</label>
                                    <input type="text" name="department" value="${user.department || ''}" placeholder="e.g., Science, Mathematics">
                                </div>
                                <div class="form-group">
                                    <label>Subject</label>
                                    <input type="text" name="subject" value="${user.subject || ''}" placeholder="e.g., Physics, Chemistry">
                                </div>
                                <div class="form-group">
                                    <label>Qualification</label>
                                    <input type="text" name="qualification" value="${user.qualification || ''}" placeholder="e.g., B.Sc, M.Sc">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${user.teacher_address || ''}</textarea>
                            </div>
                            <div class="form-actions mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Teacher
                                </button>
                                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancel</button>
                            </div>
                        </form>
                    `;
                } else if (user.user_role === 'parent') {
                    html = `
                        <h3>Edit Parent Information</h3>
                        <form id="editParentForm" onsubmit="submitEditParentForm(event, ${userId})">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Username *</label>
                                    <input type="text" name="username" value="${user.username}" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="${user.email}" required>
                                </div>
                                <div class="form-group">
                                    <label>New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" minlength="6" placeholder="Enter new password or leave blank">
                                </div>
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" value="${user.parent_fname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" value="${user.parent_lname}" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone *</label>
                                    <input type="tel" name="phone" value="${user.parent_phone}" required>
                                </div>
                                <div class="form-group">
                                    <label>NIC Number</label>
                                    <input type="text" name="nic" value="${user.nic || ''}" placeholder="e.g., 199012345678 or 901234567V">
                                </div>
                                <div class="form-group">
                                    <label>Alternative Phone</label>
                                    <input type="tel" name="alternative_phone" value="${user.alternative_phone || ''}">
                                </div>
                                <div class="form-group">
                                    <label>Relationship *</label>
                                    <select name="relationship" required>
                                        <option value="father" ${user.relationship === 'father' ? 'selected' : ''}>Father</option>
                                        <option value="mother" ${user.relationship === 'mother' ? 'selected' : ''}>Mother</option>
                                        <option value="guardian" ${user.relationship === 'guardian' ? 'selected' : ''}>Guardian</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <input type="text" name="occupation" value="${user.occupation || ''}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${user.parent_address || ''}</textarea>
                            </div>
                            <div class="form-actions mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Parent
                                </button>
                                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancel</button>
                            </div>
                        </form>
                    `;
                } else if (user.user_role === 'admin') {
                    html = `
                        <h3>Edit Admin Information</h3>
                        <form id="editAdminForm" onsubmit="submitEditAdminForm(event, ${userId})">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Username *</label>
                                    <input type="text" name="username" value="${user.username}" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="${user.email}" required>
                                </div>
                                <div class="form-group">
                                    <label>New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" minlength="6" placeholder="Enter new password or leave blank">
                                </div>
                            </div>
                            <div class="form-actions mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Admin
                                </button>
                                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancel</button>
                            </div>
                        </form>
                    `;
                }
                
                document.getElementById('editUserContent').innerHTML = html;
                
                // Load parents list for student edit
                if (user.user_role === 'student') {
                    loadParentsListForEdit(user.parent_id);
                }
            } catch (error) {
                document.getElementById('editUserContent').innerHTML = '<div class="alert alert-error">Error loading user information</div>';
            }
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        async function loadParentsListForEdit(selectedParentId) {
            try {
                const response = await fetch('add_user_handler.php?get_parents=1');
                const parents = await response.json();
                
                const select = document.getElementById('editParentSelect');
                select.innerHTML = '<option value="">Select Parent</option>';
                
                parents.forEach(parent => {
                    const nicInfo = parent.nic ? ` - NIC: ${parent.nic}` : '';
                    const option = new Option(
                        `${parent.first_name} ${parent.last_name} - ${parent.phone}${nicInfo}`,
                        parent.parent_id
                    );
                    if (parent.parent_id == selectedParentId) {
                        option.selected = true;
                    }
                    select.add(option);
                });
            } catch (error) {
                console.error('Error loading parents:', error);
            }
        }
        
        async function submitEditStudentForm(e, userId) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit_student');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: Student updated successfully!');
                    closeEditUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitEditTeacherForm(e, userId) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit_teacher');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: Teacher updated successfully!');
                    closeEditUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitEditParentForm(e, userId) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit_parent');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: Parent updated successfully!');
                    closeEditUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function submitEditAdminForm(e, userId) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit_admin');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('add_user_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Success: Admin updated successfully!');
                    closeEditUserModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function toggleUserStatus(userId, currentStatus) {
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            const confirmMsg = currentStatus === 'active' 
                ? 'Are you sure you want to DEACTIVATE this user?\n\nThe user will not be able to login.'
                : 'Are you sure you want to ACTIVATE this user?\n\nThe user will be able to login again.';
            
            if (!confirm(confirmMsg)) return;
            
            try {
                const response = await fetch(`add_user_handler.php?toggle_status=1&user_id=${userId}`);
                const result = await response.json();
                
                if (result.success) {
                    alert(`User ${action}d successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
    <style>
        .user-details {
            padding: 1rem 0;
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
        .detail-item.full-width {
            grid-column: 1 / -1;
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
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-student { background: rgba(37, 99, 235, 0.1); color: #2563eb; }
        .badge-teacher { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .badge-parent { background: rgba(251, 146, 60, 0.1); color: #ea580c; }
        .badge-admin { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
        .badge-success { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
        
        /* Action buttons with better visibility */
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
        
        /* Toggle Switch Styling */
        .status-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            cursor: pointer;
        }
        .status-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ef4444;
            transition: 0.3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #22c55e;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
        .toggle-slider:hover {
            opacity: 0.9;
        }
    </style>
</body>
</html>
