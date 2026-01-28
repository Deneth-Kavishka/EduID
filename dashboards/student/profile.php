<?php
require_once '../../config/config.php';
checkRole(['student']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

$student_id = $_SESSION['student_id'];

// Get student and user details
$query = "SELECT s.*, u.email, u.username, u.profile_picture, u.status, u.created_at, u.last_login, u.password_hash, u.user_role 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Change password
    if ($action === 'change_password') {
        try {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }
            
            if (!password_verify($current_password, $student['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':password', $new_hash);
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
            
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size must be less than 5MB');
            }
            
            $upload_dir = '../../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $student_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (!empty($student['profile_picture']) && file_exists('../../' . $student['profile_picture'])) {
                unlink('../../' . $student['profile_picture']);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload file');
            }
            
            $db_path = 'uploads/profiles/' . $filename;
            $query = "UPDATE users SET profile_picture = :picture WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':picture', $db_path);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $success = 'Profile picture updated successfully!';
            
            // Refresh student data
            $query = "SELECT s.*, u.email, u.username, u.profile_picture, u.status, u.created_at, u.last_login, u.password_hash, u.user_role 
                      FROM students s 
                      JOIN users u ON s.user_id = u.user_id 
                      WHERE s.student_id = :student_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get attendance statistics
$query = "SELECT 
          COUNT(*) as total_days,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
          FROM attendance 
          WHERE student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$attendance_percentage = $attendance_stats['total_days'] > 0 
    ? round(($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100, 1) 
    : 0;

// Check face registration status
$query = "SELECT * FROM face_recognition_data WHERE user_id = :user_id AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$face_registered = $stmt->fetch(PDO::FETCH_ASSOC);

// Check QR code status
$qr_exists = !empty($student['qr_code']) && file_exists('../../uploads/qr_codes/' . $student['qr_code']);

// Account age
$created = new DateTime($student['created_at']);
$now = new DateTime();
$account_age = $created->diff($now);

// Get profile picture URL
$profile_pic = !empty($student['profile_picture']) ? '../../' . $student['profile_picture'] : null;
$default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%233b82f6'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%233b82f6'/%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student - EduID</title>
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
                    <a href="profile.php" class="nav-item active">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="qr-code.php" class="nav-item">
                        <i class="fas fa-qrcode"></i>
                        <span>My QR Code</span>
                    </a>
                    <a href="face-registration.php" class="nav-item">
                        <i class="fas fa-face-smile"></i>
                        <span>Face Registration</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Academic</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Attendance</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exams</span>
                    </a>
                    <a href="events.php" class="nav-item">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
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
                <div class="alert alert-success" style="margin-bottom: 1rem; padding: 1rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; color: #10b981; display: flex; align-items: center; gap: 0.5rem;">
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
                    <div class="profile-header-bg" style="height: 120px; background: linear-gradient(135deg, #3b82f6, #8b5cf6);"></div>
                    <div class="card-body" style="padding: 0 1.5rem 1.5rem;">
                        <div style="display: flex; align-items: flex-end; gap: 1.5rem; margin-top: -50px; flex-wrap: wrap;">
                            <div class="profile-avatar-container" style="position: relative;">
                                <img src="<?php echo htmlspecialchars($profile_pic ?? $default_avatar); ?>" alt="Profile" class="profile-large-avatar" onerror="this.src='<?php echo htmlspecialchars($default_avatar); ?>'" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--bg-primary); object-fit: cover; background: var(--bg-secondary);">
                                <label for="profile_picture_input" class="profile-avatar-edit" style="position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--bg-primary);">
                                    <i class="fas fa-camera" style="font-size: 0.75rem;"></i>
                                </label>
                                <form id="pictureForm" method="POST" enctype="multipart/form-data" style="display: none;">
                                    <input type="hidden" name="action" value="update_picture">
                                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" onchange="document.getElementById('pictureForm').submit();">
                                </form>
                            </div>
                            <div style="flex: 1; padding-bottom: 0.5rem;">
                                <h2 style="margin: 0; color: var(--text-primary); font-size: 1.5rem;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                                <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-user-graduate" style="color: #3b82f6;"></i> Student - Grade <?php echo htmlspecialchars($student['grade']); ?>-<?php echo htmlspecialchars($student['class_section']); ?>
                                </p>
                                <p style="margin: 0.25rem 0 0; color: var(--text-tertiary); font-size: 0.8rem;">
                                    <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($student['student_number']); ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem; padding-bottom: 0.5rem;">
                                <span class="status-badge status-active" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chart-pie" style="color: #10b981; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Attendance Rate</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $attendance_percentage; ?>%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-check" style="color: #3b82f6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Days Present</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $attendance_stats['present_days'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-clock" style="color: #f59e0b; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Days Late</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $attendance_stats['late_days'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar" style="color: #8b5cf6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Account Age</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;">
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
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <!-- Left Column -->
                    <div>
                        <!-- Personal Information -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user"></i> Personal Information</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">First Name</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['first_name']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Last Name</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['last_name']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Student Number</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['student_number']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Email</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Grade</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['grade']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Section</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($student['class_section']); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Date of Birth</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : 'Not set'; ?></p>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Gender</label>
                                        <p style="color: var(--text-primary); font-size: 1rem;"><?php echo ucfirst($student['gender'] ?? 'Not set'); ?></p>
                                    </div>
                                </div>
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
                                    <div style="display: grid; gap: 1rem;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Current Password</label>
                                            <input type="password" name="current_password" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">New Password</label>
                                                <input type="password" name="new_password" required minlength="8" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Confirm Password</label>
                                                <input type="password" name="confirm_password" required minlength="8" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width: fit-content; padding: 0.75rem 1.5rem; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                            <i class="fas fa-save"></i> Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Verification Status -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shield-halved"></i> Verification Status</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <!-- QR Code Status -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: <?php echo $qr_exists ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <i class="fas fa-qrcode" style="font-size: 1.25rem; color: <?php echo $qr_exists ? '#10b981' : '#ef4444'; ?>;"></i>
                                            <span style="font-weight: 500; color: var(--text-primary);">QR Code</span>
                                        </div>
                                        <?php if ($qr_exists): ?>
                                            <span style="font-size: 0.8rem; color: #10b981; font-weight: 600;"><i class="fas fa-check-circle"></i> Generated</span>
                                        <?php else: ?>
                                            <a href="qr-code.php" style="font-size: 0.8rem; color: #ef4444; font-weight: 600; text-decoration: none;"><i class="fas fa-plus-circle"></i> Generate</a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Face Registration Status -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: <?php echo $face_registered ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <i class="fas fa-face-smile" style="font-size: 1.25rem; color: <?php echo $face_registered ? '#10b981' : '#ef4444'; ?>;"></i>
                                            <span style="font-weight: 500; color: var(--text-primary);">Face Data</span>
                                        </div>
                                        <?php if ($face_registered): ?>
                                            <span style="font-size: 0.8rem; color: #10b981; font-weight: 600;"><i class="fas fa-check-circle"></i> Registered</span>
                                        <?php else: ?>
                                            <a href="face-registration.php" style="font-size: 0.8rem; color: #ef4444; font-weight: 600; text-decoration: none;"><i class="fas fa-plus-circle"></i> Register</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle"></i> Account Info</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                                        <span style="color: var(--text-secondary); font-size: 0.85rem;">Username</span>
                                        <span style="color: var(--text-primary); font-weight: 500; font-size: 0.85rem;"><?php echo htmlspecialchars($student['username']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                                        <span style="color: var(--text-secondary); font-size: 0.85rem;">Account Created</span>
                                        <span style="color: var(--text-primary); font-weight: 500; font-size: 0.85rem;"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                                        <span style="color: var(--text-secondary); font-size: 0.85rem;">Last Login</span>
                                        <span style="color: var(--text-primary); font-weight: 500; font-size: 0.85rem;"><?php echo $student['last_login'] ? date('M d, Y H:i', strtotime($student['last_login'])) : 'Never'; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary); font-size: 0.85rem;">Status</span>
                                        <span style="color: #10b981; font-weight: 600; font-size: 0.85rem;"><?php echo ucfirst($student['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-link"></i> Quick Links</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <a href="qr-code.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                                        <i class="fas fa-qrcode" style="color: #3b82f6;"></i>
                                        <span>View My QR Code</span>
                                    </a>
                                    <a href="face-registration.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                                        <i class="fas fa-face-smile" style="color: #10b981;"></i>
                                        <span>Face Registration</span>
                                    </a>
                                    <a href="attendance.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                                        <i class="fas fa-calendar-check" style="color: #f59e0b;"></i>
                                        <span>View Attendance</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Sidebar scroll position preservation
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar-nav');
            const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos && sidebar) {
                sidebar.scrollTop = parseInt(savedScrollPos);
            }
            
            const activeItem = document.querySelector('.nav-item.active');
            if (activeItem && sidebar) {
                const sidebarRect = sidebar.getBoundingClientRect();
                const itemRect = activeItem.getBoundingClientRect();
                if (itemRect.top < sidebarRect.top || itemRect.bottom > sidebarRect.bottom) {
                    activeItem.scrollIntoView({ block: 'center', behavior: 'auto' });
                }
            }
            
            if (sidebar) {
                sidebar.addEventListener('scroll', function() {
                    sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
                });
            }
        });
    </script>
</body>
</html>
