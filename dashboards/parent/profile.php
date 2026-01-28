<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Fetch parent details
$query = "SELECT p.*, u.email, u.username, u.profile_picture, u.status, u.created_at, u.password_hash 
          FROM parents p 
          JOIN users u ON p.user_id = u.user_id 
          WHERE p.parent_id = :parent_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $_SESSION['parent_id']);
$stmt->execute();
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Count linked children
$query_children = "SELECT COUNT(*) FROM students WHERE parent_id = :parent_id";
$stmt_children = $conn->prepare($query_children);
$stmt_children->bindParam(':parent_id', $_SESSION['parent_id']);
$stmt_children->execute();
$children_count = $stmt_children->fetchColumn();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update profile
    if ($action === 'update_profile') {
        try {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $alternative_phone = trim($_POST['alternative_phone']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            $occupation = trim($_POST['occupation']);
            $relationship = $_POST['relationship'];
            
            // Update parent table
            $update_parent = "UPDATE parents SET 
                              first_name = :first_name,
                              last_name = :last_name,
                              phone = :phone,
                              alternative_phone = :alternative_phone,
                              email = :email,
                              address = :address,
                              occupation = :occupation,
                              relationship = :relationship
                              WHERE parent_id = :parent_id";
            $stmt_update = $conn->prepare($update_parent);
            $stmt_update->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':phone' => $phone,
                ':alternative_phone' => $alternative_phone,
                ':email' => $email,
                ':address' => $address,
                ':occupation' => $occupation,
                ':relationship' => $relationship,
                ':parent_id' => $_SESSION['parent_id']
            ]);
            
            // Update users table email
            $update_user = "UPDATE users SET email = :email WHERE user_id = :user_id";
            $stmt_user = $conn->prepare($update_user);
            $stmt_user->execute([':email' => $email, ':user_id' => $_SESSION['user_id']]);
            
            $success = 'Profile updated successfully!';
            
            // Refresh parent data
            $stmt->execute();
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
    
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
            
            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            if (!password_verify($current_password, $parent['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
            $stmt_pwd = $conn->prepare($query);
            $stmt_pwd->execute([':password' => $new_hash, ':user_id' => $_SESSION['user_id']]);
            
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
            $filename = 'parent_' . $_SESSION['parent_id'] . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (!empty($parent['profile_picture']) && file_exists('../../' . $parent['profile_picture'])) {
                unlink('../../' . $parent['profile_picture']);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload file');
            }
            
            $db_path = 'uploads/profiles/' . $filename;
            $query = "UPDATE users SET profile_picture = :picture WHERE user_id = :user_id";
            $stmt_pic = $conn->prepare($query);
            $stmt_pic->execute([':picture' => $db_path, ':user_id' => $_SESSION['user_id']]);
            
            $success = 'Profile picture updated successfully!';
            
            // Refresh parent data
            $stmt->execute();
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Account age
$created = new DateTime($parent['created_at']);
$now = new DateTime();
$account_age = $created->diff($now);

// Get profile picture URL
$profile_pic = !empty($parent['profile_picture']) ? '../../' . $parent['profile_picture'] : null;
$default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%2310b981'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%2310b981'/%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Parent - EduID</title>
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
                    <a href="children.php" class="nav-item">
                        <i class="fas fa-children"></i>
                        <span>My Children</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Monitoring</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance History</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Schedule</span>
                    </a>
                    <a href="events.php" class="nav-item">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
                    </a>
                    <a href="notifications.php" class="nav-item">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
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
                    <div class="profile-header-bg" style="height: 120px; background: linear-gradient(135deg, #10b981, #059669);"></div>
                    <div class="card-body" style="padding: 0 1.5rem 1.5rem;">
                        <div style="display: flex; align-items: flex-end; gap: 1.5rem; margin-top: -50px; flex-wrap: wrap;">
                            <div class="profile-avatar-container" style="position: relative;">
                                <img src="<?php echo htmlspecialchars($profile_pic ?? $default_avatar); ?>" alt="Profile" class="profile-large-avatar" onerror="this.src='<?php echo htmlspecialchars($default_avatar); ?>'" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--bg-primary); object-fit: cover; background: var(--bg-secondary);">
                                <label for="profile_picture_input" class="profile-avatar-edit" style="position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--bg-primary);">
                                    <i class="fas fa-camera" style="font-size: 0.75rem;"></i>
                                </label>
                                <form id="pictureForm" method="POST" enctype="multipart/form-data" style="display: none;">
                                    <input type="hidden" name="action" value="update_picture">
                                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" onchange="document.getElementById('pictureForm').submit();">
                                </form>
                            </div>
                            <div style="flex: 1; padding-bottom: 0.5rem;">
                                <h2 style="margin: 0; color: var(--text-primary); font-size: 1.5rem;"><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h2>
                                <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-user-tie" style="color: #10b981;"></i> <?php echo ucfirst($parent['relationship']); ?>
                                </p>
                                <p style="margin: 0.25rem 0 0; color: var(--text-tertiary); font-size: 0.8rem;">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($parent['email']); ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem; padding-bottom: 0.5rem;">
                                <span style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <i class="fas fa-check-circle"></i> <?php echo ucfirst($parent['status']); ?>
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
                                    <i class="fas fa-children" style="color: #10b981; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Children</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $children_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar" style="color: #3b82f6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Account Age</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;">
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
                                    <i class="fas fa-user-check" style="color: #8b5cf6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Status</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo ucfirst($parent['status']); ?></div>
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
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">First Name</label>
                                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($parent['first_name']); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Last Name</label>
                                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($parent['last_name']); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Email</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($parent['email']); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Phone</label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($parent['phone']); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Alternative Phone</label>
                                            <input type="tel" name="alternative_phone" value="<?php echo htmlspecialchars($parent['alternative_phone'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Relationship</label>
                                            <select name="relationship" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                                <option value="father" <?php echo $parent['relationship'] === 'father' ? 'selected' : ''; ?>>Father</option>
                                                <option value="mother" <?php echo $parent['relationship'] === 'mother' ? 'selected' : ''; ?>>Mother</option>
                                                <option value="guardian" <?php echo $parent['relationship'] === 'guardian' ? 'selected' : ''; ?>>Guardian</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Occupation</label>
                                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($parent['occupation'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div style="grid-column: 1 / -1;">
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Address</label>
                                            <textarea name="address" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary); resize: vertical;"><?php echo htmlspecialchars($parent['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div style="margin-top: 1.5rem;">
                                        <button type="submit" style="padding: 0.75rem 1.5rem; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
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
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Current Password</label>
                                            <input type="password" name="current_password" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">New Password</label>
                                            <input type="password" name="new_password" required minlength="6" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Confirm Password</label>
                                            <input type="password" name="confirm_password" required minlength="6" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                        </div>
                                    </div>
                                    <div style="margin-top: 1.5rem;">
                                        <button type="submit" style="padding: 0.75rem 1.5rem; background: #f59e0b; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                            <i class="fas fa-key"></i> Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Account Information -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shield-alt"></i> Account Info</h3>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Username</label>
                                    <p style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($parent['username']); ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Account Status</label>
                                    <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <?php echo ucfirst($parent['status']); ?>
                                    </span>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Role</label>
                                    <p style="color: var(--text-primary); font-size: 1rem;">Parent / Guardian</p>
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Member Since</label>
                                    <p style="color: var(--text-primary); font-size: 1rem;"><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-link"></i> Quick Links</h3>
                            </div>
                            <div class="card-body">
                                <a href="children.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; color: var(--text-primary); text-decoration: none; margin-bottom: 0.5rem; transition: background 0.2s;">
                                    <i class="fas fa-children" style="color: #10b981;"></i>
                                    <span>View My Children</span>
                                    <i class="fas fa-chevron-right" style="margin-left: auto; font-size: 0.75rem; color: var(--text-tertiary);"></i>
                                </a>
                                <a href="attendance.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; color: var(--text-primary); text-decoration: none; margin-bottom: 0.5rem; transition: background 0.2s;">
                                    <i class="fas fa-calendar-check" style="color: #3b82f6;"></i>
                                    <span>Attendance History</span>
                                    <i class="fas fa-chevron-right" style="margin-left: auto; font-size: 0.75rem; color: var(--text-tertiary);"></i>
                                </a>
                                <a href="notifications.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: background 0.2s;">
                                    <i class="fas fa-bell" style="color: #f59e0b;"></i>
                                    <span>Notifications</span>
                                    <i class="fas fa-chevron-right" style="margin-left: auto; font-size: 0.75rem; color: var(--text-tertiary);"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
