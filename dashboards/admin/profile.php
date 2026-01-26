<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get current user data
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update profile
    if ($action === 'update_profile') {
        try {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            // Validate
            if (empty($username) || empty($email)) {
                throw new Exception('Username and email are required');
            }
            
            // Check if username is taken by another user
            $query = "SELECT user_id FROM users WHERE username = :username AND user_id != :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->fetch()) {
                throw new Exception('Username is already taken');
            }
            
            // Check if email is taken by another user
            $query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->fetch()) {
                throw new Exception('Email is already taken');
            }
            
            // Update user
            $query = "UPDATE users SET username = :username, email = :email WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Update session
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $query = "SELECT * FROM users WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Change password
    if ($action === 'change_password') {
        try {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':password', $new_hash);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Log the action
            $query = "INSERT INTO access_logs (user_id, access_type, status, details) VALUES (:user_id, 'system', 'success', 'Password changed')";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $success = 'Password changed successfully!';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Update profile picture
    if ($action === 'update_picture') {
        try {
            if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please select a valid image file');
            }
            
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
            }
            
            if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                throw new Exception('File size must be less than 5MB');
            }
            
            // Create upload directory if not exists
            $upload_dir = '../../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Delete old profile picture if exists
            if (!empty($user['profile_picture']) && file_exists('../../' . $user['profile_picture'])) {
                unlink('../../' . $user['profile_picture']);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload file');
            }
            
            // Update database
            $db_path = 'uploads/profiles/' . $filename;
            $query = "UPDATE users SET profile_picture = :picture WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':picture', $db_path);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $success = 'Profile picture updated successfully!';
            
            // Refresh user data
            $query = "SELECT * FROM users WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get activity statistics
$stats = [];

// Total logins this month
$query = "SELECT COUNT(*) as count FROM access_logs WHERE user_id = :user_id AND access_type = 'login' AND MONTH(access_time) = MONTH(CURDATE())";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats['logins_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent activities
$query = "SELECT * FROM access_logs WHERE user_id = :user_id ORDER BY access_time DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Account age
$created = new DateTime($user['created_at']);
$now = new DateTime();
$account_age = $created->diff($now);

// Get profile picture URL
$profile_pic = !empty($user['profile_picture']) ? '../../' . $user['profile_picture'] : null;
$default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%23cbd5e1'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%23cbd5e1'/%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EduID</title>
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
                    <a href="profile.php" class="nav-item active">
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
                    <h1>My Profile</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Account</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>My Profile</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1rem; padding: 1rem; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; color: #22c55e; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; color: #ef4444; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <!-- Profile Header Card -->
                <div class="card" style="margin-bottom: 1.5rem; overflow: hidden;">
                    <div class="profile-header-bg" style="height: 120px; background: linear-gradient(135deg, var(--primary-color), #8b5cf6);"></div>
                    <div class="card-body" style="padding: 0 1.5rem 1.5rem;">
                        <div style="display: flex; align-items: flex-end; gap: 1.5rem; margin-top: -50px; flex-wrap: wrap;">
                            <div class="profile-avatar-container" style="position: relative;">
                                <img src="<?php echo htmlspecialchars($profile_pic ?? $default_avatar); ?>" alt="Profile" class="profile-large-avatar" onerror="this.src='<?php echo htmlspecialchars($default_avatar); ?>'" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--bg-primary); object-fit: cover; background: var(--bg-secondary);">
                                <label for="profile_picture_input" class="profile-avatar-edit" style="position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--bg-primary);">
                                    <i class="fas fa-camera" style="font-size: 0.75rem;"></i>
                                </label>
                                <form id="pictureForm" method="POST" enctype="multipart/form-data" style="display: none;">
                                    <input type="hidden" name="action" value="update_picture">
                                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" onchange="document.getElementById('pictureForm').submit();">
                                </form>
                            </div>
                            <div style="flex: 1; padding-bottom: 0.5rem;">
                                <h2 style="margin: 0; color: var(--text-primary); font-size: 1.5rem;"><?php echo htmlspecialchars($user['username']); ?></h2>
                                <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-shield-alt" style="color: var(--primary-color);"></i> System Administrator
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem; padding-bottom: 0.5rem;">
                                <span class="status-badge status-active" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sign-in-alt" style="color: #3b82f6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Logins This Month</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['logins_this_month']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar" style="color: #22c55e; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Account Age</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #22c55e;">
                                        <?php 
                                        if ($account_age->y > 0) echo $account_age->y . 'y';
                                        elseif ($account_age->m > 0) echo $account_age->m . 'm';
                                        else echo $account_age->d . 'd';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-clock" style="color: #8b5cf6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Last Login</div>
                                    <div style="font-size: 1rem; font-weight: 600; color: #8b5cf6;">
                                        <?php echo $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-shield" style="color: #f59e0b; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Role</div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b; text-transform: capitalize;"><?php echo $user['user_role']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem;">
                    <!-- Left Column -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Profile Information -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user-edit"></i> Profile Information</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-user"></i> Username
                                            </label>
                                            <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-envelope"></i> Email Address
                                            </label>
                                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-id-badge"></i> User ID
                                            </label>
                                            <input type="text" class="form-input" value="<?php echo $user['user_id']; ?>" disabled style="background: var(--bg-secondary);">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-calendar-plus"></i> Member Since
                                            </label>
                                            <input type="text" class="form-input" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled style="background: var(--bg-secondary);">
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-lock"></i> Change Password</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group" style="margin-bottom: 1rem;">
                                        <label class="form-label">
                                            <i class="fas fa-key"></i> Current Password
                                        </label>
                                        <div style="position: relative;">
                                            <input type="password" name="current_password" id="current_password" class="form-input" required style="padding-right: 40px;">
                                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-lock"></i> New Password
                                            </label>
                                            <div style="position: relative;">
                                                <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8" style="padding-right: 40px;">
                                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-lock"></i> Confirm New Password
                                            </label>
                                            <div style="position: relative;">
                                                <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8" style="padding-right: 40px;">
                                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; font-size: 0.8rem; color: var(--text-secondary);">
                                        <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                                        Password must be at least 8 characters long.
                                    </div>
                                    
                                    <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-key"></i> Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Recent Activity</h3>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="activity-list">
                                    <?php if (empty($recent_activities)): ?>
                                    <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                        <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                        <p>No recent activity</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item" style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="activity-icon" style="width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                                            <?php 
                                            $bg = 'rgba(59, 130, 246, 0.1)'; 
                                            $color = '#3b82f6';
                                            if ($activity['access_type'] === 'login') { $bg = 'rgba(34, 197, 94, 0.1)'; $color = '#22c55e'; }
                                            elseif ($activity['access_type'] === 'logout') { $bg = 'rgba(245, 158, 11, 0.1)'; $color = '#f59e0b'; }
                                            elseif ($activity['status'] === 'failed') { $bg = 'rgba(239, 68, 68, 0.1)'; $color = '#ef4444'; }
                                            echo "background: $bg;";
                                            ?>">
                                            <i class="fas <?php 
                                            if ($activity['access_type'] === 'login') echo 'fa-sign-in-alt';
                                            elseif ($activity['access_type'] === 'logout') echo 'fa-sign-out-alt';
                                            else echo 'fa-cog';
                                            ?>" style="color: <?php echo $color; ?>; font-size: 0.8rem;"></i>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.85rem; font-weight: 500; color: var(--text-primary); text-transform: capitalize;">
                                                <?php echo $activity['access_type']; ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: var(--text-secondary);">
                                                <?php echo date('M j, g:i A', strtotime($activity['access_time'])); ?>
                                            </div>
                                        </div>
                                        <span class="status-badge" style="padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600;
                                            <?php if ($activity['status'] === 'success'): ?>
                                            background: rgba(34, 197, 94, 0.1); color: #22c55e;
                                            <?php else: ?>
                                            background: rgba(239, 68, 68, 0.1); color: #ef4444;
                                            <?php endif; ?>">
                                            <?php echo $activity['status']; ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div style="padding: 0.75rem 1rem;">
                                    <a href="logs.php" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                                        View all activity <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="card-body" style="display: grid; gap: 0.5rem;">
                                <a href="settings.php" class="quick-action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; text-decoration: none; color: var(--text-primary); transition: all 0.2s;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-cog" style="color: #3b82f6;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem;">System Settings</div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Configure system</div>
                                    </div>
                                </a>
                                
                                <a href="logs.php" class="quick-action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; text-decoration: none; color: var(--text-primary); transition: all 0.2s;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-list" style="color: #8b5cf6;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem;">Access Logs</div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">View system logs</div>
                                    </div>
                                </a>
                                
                                <a href="reports.php" class="quick-action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; text-decoration: none; color: var(--text-primary); transition: all 0.2s;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-chart-bar" style="color: #22c55e;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem;">Reports</div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Generate reports</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Account Security -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shield-alt"></i> Security Status</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-lock" style="color: #22c55e;"></i>
                                            <span style="font-size: 0.85rem;">Password</span>
                                        </div>
                                        <span style="font-size: 0.75rem; color: #22c55e; font-weight: 600;">Secure</span>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-envelope" style="color: #22c55e;"></i>
                                            <span style="font-size: 0.85rem;">Email Verified</span>
                                        </div>
                                        <span style="font-size: 0.75rem; color: #22c55e; font-weight: 600;">Yes</span>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-user-check" style="color: #22c55e;"></i>
                                            <span style="font-size: 0.85rem;">Account Status</span>
                                        </div>
                                        <span style="font-size: 0.75rem; color: #22c55e; font-weight: 600; text-transform: capitalize;"><?php echo $user['status']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        /* Form styles */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .form-label i {
            margin-right: 0.4rem;
            color: var(--primary-color);
        }
        .form-input {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .form-group {
            margin-bottom: 0;
        }
        
        /* Quick action hover */
        .quick-action-btn:hover {
            background: var(--border-color) !important;
            transform: translateX(5px);
        }
        
        /* Profile Dropdown Styles - Google-like */
        .profile-dropdown-container {
            position: relative;
        }
        
        .profile-trigger {
            background: none;
            border: none;
            padding: 2px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .profile-trigger:hover {
            background: var(--bg-secondary);
        }
        
        .profile-trigger .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 320px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.2s ease;
        }
        
        .profile-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        
        .profile-dropdown-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            text-align: left;
        }
        
        .profile-dropdown-avatar img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-dropdown-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .profile-dropdown-email {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .profile-dropdown-manage {
            padding: 0 1rem 1rem;
        }
        
        .manage-account-btn {
            display: block;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            text-align: center;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .manage-account-btn:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .profile-dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0;
        }
        
        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }
        
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        .profile-dropdown-item:hover {
            background: var(--bg-secondary);
        }
        
        .profile-dropdown-item i {
            width: 20px;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .profile-dropdown-item.logout-item {
            color: #ef4444;
        }
        
        .profile-dropdown-item.logout-item i {
            color: #ef4444;
        }
        
        .theme-indicator {
            margin-left: auto;
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: var(--bg-secondary);
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
        }
        
        .profile-dropdown-footer {
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .profile-dropdown-footer a {
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        .profile-dropdown-footer a:hover {
            color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .content-area > div:last-child {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Profile dropdown toggle
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const container = document.querySelector('.profile-dropdown-container');
            const dropdown = document.getElementById('profileDropdown');
            
            if (container && !container.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Theme toggle function for dropdown
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update indicator
            const indicator = document.getElementById('themeIndicator');
            if (indicator) {
                indicator.textContent = newTheme === 'dark' ? 'On' : 'Off';
            }
            
            // Update icon
            const themeIcon = document.querySelector('#themeToggleTop i');
            if (themeIcon) {
                themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
        
        // Initialize theme indicator
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            const indicator = document.getElementById('themeIndicator');
            if (indicator) {
                indicator.textContent = currentTheme === 'dark' ? 'On' : 'Off';
            }
        });
    </script>
</body>
</html>
