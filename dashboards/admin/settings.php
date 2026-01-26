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
    
    if ($action === 'update_general') {
        try {
            $settings = [
                'site_name' => $_POST['site_name'] ?? 'EduID',
                'site_email' => $_POST['site_email'] ?? '',
                'site_phone' => $_POST['site_phone'] ?? '',
                'site_address' => $_POST['site_address'] ?? '',
                'academic_year' => $_POST['academic_year'] ?? date('Y'),
                'semester' => $_POST['semester'] ?? '1'
            ];
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value, updated_by = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'General settings updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
    
    if ($action === 'update_attendance') {
        try {
            $settings = [
                'attendance_grace_period' => $_POST['attendance_grace_period'] ?? '15',
                'late_threshold' => $_POST['late_threshold'] ?? '30',
                'allow_past_attendance' => isset($_POST['allow_past_attendance']) ? '1' : '0',
                'past_attendance_days' => $_POST['past_attendance_days'] ?? '7',
                'attendance_start_time' => $_POST['attendance_start_time'] ?? '08:00',
                'attendance_end_time' => $_POST['attendance_end_time'] ?? '09:00'
            ];
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value, updated_by = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'Attendance settings updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
    
    if ($action === 'update_features') {
        try {
            $settings = [
                'allow_face_recognition' => isset($_POST['allow_face_recognition']) ? '1' : '0',
                'allow_qr_scanning' => isset($_POST['allow_qr_scanning']) ? '1' : '0',
                'allow_manual_attendance' => isset($_POST['allow_manual_attendance']) ? '1' : '0',
                'require_face_for_exam' => isset($_POST['require_face_for_exam']) ? '1' : '0',
                'enable_notifications' => isset($_POST['enable_notifications']) ? '1' : '0',
                'enable_sms_alerts' => isset($_POST['enable_sms_alerts']) ? '1' : '0'
            ];
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value, updated_by = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'Feature settings updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
    
    if ($action === 'update_security') {
        try {
            $settings = [
                'session_timeout' => $_POST['session_timeout'] ?? '30',
                'max_login_attempts' => $_POST['max_login_attempts'] ?? '5',
                'lockout_duration' => $_POST['lockout_duration'] ?? '15',
                'password_min_length' => $_POST['password_min_length'] ?? '8',
                'require_special_char' => isset($_POST['require_special_char']) ? '1' : '0',
                'require_uppercase' => isset($_POST['require_uppercase']) ? '1' : '0'
            ];
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value, updated_by = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'Security settings updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Fetch all settings
$query = "SELECT setting_key, setting_value FROM system_settings";
$stmt = $conn->prepare($query);
$stmt->execute();
$settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Helper function to get setting value with default
function getSetting($key, $default = '') {
    global $settingsData;
    return $settingsData[$key] ?? $default;
}

// Get system statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as count FROM users";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total students
$query = "SELECT COUNT(*) as count FROM students";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total teachers
$query = "SELECT COUNT(*) as count FROM teachers";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_teachers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Database size (approximate)
$query = "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb 
          FROM information_schema.tables 
          WHERE table_schema = DATABASE()";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['db_size'] = round($stmt->fetch(PDO::FETCH_ASSOC)['size_mb'] ?? 0, 2);

// Last backup (placeholder - would need actual backup tracking)
$stats['last_backup'] = 'Not configured';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - EduID</title>
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
                    <a href="settings.php" class="nav-item active">
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
                    <h1>System Settings</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Reports & Settings</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>System Settings</span>
                    </div>
                </div>
                
                <?php include 'includes/header_profile.php'; ?>
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
                
                <!-- System Overview Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Total Users</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo number_format($stats['total_users']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-users" style="color: #3b82f6;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Students</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #22c55e;"><?php echo number_format($stats['total_students']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-graduate" style="color: #22c55e;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Teachers</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo number_format($stats['total_teachers']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chalkboard-teacher" style="color: #8b5cf6;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Database Size</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $stats['db_size']; ?> MB</div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-database" style="color: #f59e0b;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Tabs -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 0;">
                        <div class="settings-tabs" style="display: flex; border-bottom: 1px solid var(--border-color); overflow-x: auto;">
                            <button class="settings-tab active" data-tab="general" style="padding: 1rem 1.5rem; border: none; background: none; color: var(--text-primary); font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent;">
                                <i class="fas fa-cog"></i> General
                            </button>
                            <button class="settings-tab" data-tab="attendance" style="padding: 1rem 1.5rem; border: none; background: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent;">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </button>
                            <button class="settings-tab" data-tab="features" style="padding: 1rem 1.5rem; border: none; background: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent;">
                                <i class="fas fa-puzzle-piece"></i> Features
                            </button>
                            <button class="settings-tab" data-tab="security" style="padding: 1rem 1.5rem; border: none; background: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent;">
                                <i class="fas fa-shield-alt"></i> Security
                            </button>
                            <button class="settings-tab" data-tab="backup" style="padding: 1rem 1.5rem; border: none; background: none; color: var(--text-secondary); font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent;">
                                <i class="fas fa-download"></i> Backup
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- General Settings -->
                <div id="general-panel" class="settings-panel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-building"></i> Institution Information</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_general">
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-school"></i> Institution Name
                                        </label>
                                        <input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars(getSetting('site_name', 'EduID')); ?>" placeholder="Enter institution name">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-envelope"></i> Contact Email
                                        </label>
                                        <input type="email" name="site_email" class="form-input" value="<?php echo htmlspecialchars(getSetting('site_email', '')); ?>" placeholder="admin@school.edu">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-phone"></i> Contact Phone
                                        </label>
                                        <input type="tel" name="site_phone" class="form-input" value="<?php echo htmlspecialchars(getSetting('site_phone', '')); ?>" placeholder="+1 234 567 8900">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-calendar-alt"></i> Academic Year
                                        </label>
                                        <select name="academic_year" class="form-input">
                                            <?php for ($year = date('Y') - 2; $year <= date('Y') + 2; $year++): ?>
                                            <option value="<?php echo $year; ?>" <?php echo getSetting('academic_year', date('Y')) == $year ? 'selected' : ''; ?>>
                                                <?php echo $year . '-' . ($year + 1); ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-layer-group"></i> Current Semester/Term
                                        </label>
                                        <select name="semester" class="form-input">
                                            <option value="1" <?php echo getSetting('semester', '1') == '1' ? 'selected' : ''; ?>>Semester 1</option>
                                            <option value="2" <?php echo getSetting('semester', '1') == '2' ? 'selected' : ''; ?>>Semester 2</option>
                                            <option value="3" <?php echo getSetting('semester', '1') == '3' ? 'selected' : ''; ?>>Semester 3</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label class="form-label">
                                        <i class="fas fa-map-marker-alt"></i> Address
                                    </label>
                                    <textarea name="site_address" class="form-input" rows="2" placeholder="Enter institution address"><?php echo htmlspecialchars(getSetting('site_address', '')); ?></textarea>
                                </div>
                                
                                <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Settings -->
                <div id="attendance-panel" class="settings-panel" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clock"></i> Attendance Configuration</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_attendance">
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-hourglass-half"></i> Grace Period (minutes)
                                        </label>
                                        <input type="number" name="attendance_grace_period" class="form-input" min="0" max="60" value="<?php echo htmlspecialchars(getSetting('attendance_grace_period', '15')); ?>">
                                        <small style="color: var(--text-secondary); font-size: 0.75rem;">Time after start before marking as late</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-stopwatch"></i> Late Threshold (minutes)
                                        </label>
                                        <input type="number" name="late_threshold" class="form-input" min="0" max="120" value="<?php echo htmlspecialchars(getSetting('late_threshold', '30')); ?>">
                                        <small style="color: var(--text-secondary); font-size: 0.75rem;">Time after which student marked absent</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-clock"></i> Attendance Start Time
                                        </label>
                                        <input type="time" name="attendance_start_time" class="form-input" value="<?php echo htmlspecialchars(getSetting('attendance_start_time', '08:00')); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-clock"></i> Attendance End Time
                                        </label>
                                        <input type="time" name="attendance_end_time" class="form-input" value="<?php echo htmlspecialchars(getSetting('attendance_end_time', '09:00')); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-history"></i> Past Attendance Days Limit
                                        </label>
                                        <input type="number" name="past_attendance_days" class="form-input" min="1" max="30" value="<?php echo htmlspecialchars(getSetting('past_attendance_days', '7')); ?>">
                                        <small style="color: var(--text-secondary); font-size: 0.75rem;">How many days back attendance can be marked</small>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);">Allow Past Date Attendance</div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Enable teachers to mark attendance for past dates</div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_past_attendance" <?php echo getSetting('allow_past_attendance', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
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
                </div>
                
                <!-- Features Settings -->
                <div id="features-panel" class="settings-panel" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-sliders-h"></i> Feature Toggles</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_features">
                                
                                <div style="display: grid; gap: 1rem;">
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-smile" style="color: #22c55e;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">Face Recognition</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Enable face recognition for attendance verification</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_face_recognition" <?php echo getSetting('allow_face_recognition', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-qrcode" style="color: #8b5cf6;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">QR Code Scanning</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Enable QR code scanning for attendance</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_qr_scanning" <?php echo getSetting('allow_qr_scanning', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user-edit" style="color: #f59e0b;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">Manual Attendance</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Allow teachers to manually mark attendance</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_manual_attendance" <?php echo getSetting('allow_manual_attendance', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-id-card" style="color: #ef4444;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">Require Face for Exams</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Require face verification for exam attendance</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="require_face_for_exam" <?php echo getSetting('require_face_for_exam', '0') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-bell" style="color: #3b82f6;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">Email Notifications</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Send email notifications to parents/students</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="enable_notifications" <?php echo getSetting('enable_notifications', '0') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-secondary); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-sms" style="color: #10b981;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">SMS Alerts</div>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Send SMS alerts for absences to parents</div>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="enable_sms_alerts" <?php echo getSetting('enable_sms_alerts', '0') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
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
                </div>
                
                <!-- Security Settings -->
                <div id="security-panel" class="settings-panel" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-lock"></i> Security Configuration</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_security">
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-clock"></i> Session Timeout (minutes)
                                        </label>
                                        <input type="number" name="session_timeout" class="form-input" min="5" max="120" value="<?php echo htmlspecialchars(getSetting('session_timeout', '30')); ?>">
                                        <small style="color: var(--text-secondary); font-size: 0.75rem;">Auto logout after inactivity</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-ban"></i> Max Login Attempts
                                        </label>
                                        <input type="number" name="max_login_attempts" class="form-input" min="3" max="10" value="<?php echo htmlspecialchars(getSetting('max_login_attempts', '5')); ?>">
                                        <small style="color: var(--text-secondary); font-size: 0.75rem;">Before account lockout</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-lock"></i> Lockout Duration (minutes)
                                        </label>
                                        <input type="number" name="lockout_duration" class="form-input" min="5" max="60" value="<?php echo htmlspecialchars(getSetting('lockout_duration', '15')); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-key"></i> Minimum Password Length
                                        </label>
                                        <input type="number" name="password_min_length" class="form-input" min="6" max="20" value="<?php echo htmlspecialchars(getSetting('password_min_length', '8')); ?>">
                                    </div>
                                </div>
                                
                                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 8px; display: grid; gap: 1rem;">
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);">Require Special Character</div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Password must contain at least one special character</div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="require_special_char" <?php echo getSetting('require_special_char', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-option" style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);">Require Uppercase Letter</div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Password must contain at least one uppercase letter</div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="require_uppercase" <?php echo getSetting('require_uppercase', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
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
                </div>
                
                <!-- Backup Settings -->
                <div id="backup-panel" class="settings-panel" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-database"></i> Database Backup & Maintenance</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                                <!-- Backup Section -->
                                <div style="padding: 1.5rem; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color);">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-download" style="color: #22c55e; font-size: 1.25rem;"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; color: var(--text-primary);">Create Backup</h4>
                                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary);">Download database backup</p>
                                        </div>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                        Creates a complete backup of all database tables including users, attendance records, and settings.
                                    </p>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="createBackup()">
                                        <i class="fas fa-download"></i> Download Backup
                                    </button>
                                </div>
                                
                                <!-- Clear Logs Section -->
                                <div style="padding: 1.5rem; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color);">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-broom" style="color: #f59e0b; font-size: 1.25rem;"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; color: var(--text-primary);">Clear Old Logs</h4>
                                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary);">Remove logs older than 90 days</p>
                                        </div>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                        Removes access logs and activity records older than 90 days to free up database space.
                                    </p>
                                    <button type="button" class="btn btn-sm" onclick="clearOldLogs()" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">
                                        <i class="fas fa-broom"></i> Clear Old Logs
                                    </button>
                                </div>
                                
                                <!-- Reset Section -->
                                <div style="padding: 1.5rem; background: var(--bg-secondary); border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-exclamation-triangle" style="color: #ef4444; font-size: 1.25rem;"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; color: var(--text-primary);">Reset Attendance</h4>
                                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary);">Clear all attendance data</p>
                                        </div>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                        <strong style="color: #ef4444;">Warning:</strong> This will permanently delete all attendance records. This action cannot be undone.
                                    </p>
                                    <button type="button" class="btn btn-sm" onclick="resetAttendance()" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);">
                                        <i class="fas fa-trash-alt"></i> Reset Attendance Data
                                    </button>
                                </div>
                            </div>
                            
                            <!-- System Info -->
                            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-secondary); border-radius: 10px;">
                                <h4 style="margin: 0 0 1rem; color: var(--text-primary);"><i class="fas fa-info-circle"></i> System Information</h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">PHP Version</div>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo phpversion(); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">MySQL Version</div>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo $conn->getAttribute(PDO::ATTR_SERVER_VERSION); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Server</div>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">EduID Version</div>
                                        <div style="font-weight: 600; color: var(--text-primary);">1.0.0</div>
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
        
        .settings-tab.active {
            color: var(--primary-color) !important;
            border-bottom-color: var(--primary-color) !important;
        }
        .settings-tab:hover {
            color: var(--primary-color);
            background: var(--bg-secondary);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            flex-shrink: 0;
        }
        .toggle-switch input {
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
            background-color: var(--border-color);
            transition: 0.3s;
            border-radius: 26px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background-color: #22c55e;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active from all tabs
                document.querySelectorAll('.settings-tab').forEach(t => {
                    t.classList.remove('active');
                    t.style.color = 'var(--text-secondary)';
                    t.style.borderBottomColor = 'transparent';
                });
                
                // Add active to clicked tab
                this.classList.add('active');
                this.style.color = 'var(--primary-color)';
                this.style.borderBottomColor = 'var(--primary-color)';
                
                // Hide all panels
                document.querySelectorAll('.settings-panel').forEach(panel => {
                    panel.style.display = 'none';
                });
                
                // Show selected panel
                const tabId = this.dataset.tab;
                document.getElementById(tabId + '-panel').style.display = 'block';
            });
        });
        
        // Backup function
        function createBackup() {
            if (confirm('This will create and download a backup of the database. Continue?')) {
                window.location.href = 'settings_handler.php?action=backup';
            }
        }
        
        // Clear old logs
        async function clearOldLogs() {
            if (confirm('This will delete all access logs older than 90 days. Continue?')) {
                try {
                    const response = await fetch('settings_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=clear_logs'
                    });
                    const result = await response.json();
                    alert(result.message);
                    if (result.success) location.reload();
                } catch (error) {
                    alert('Error clearing logs');
                }
            }
        }
        
        // Reset attendance
        async function resetAttendance() {
            const confirmation = prompt('This will PERMANENTLY delete ALL attendance records. Type "RESET" to confirm:');
            if (confirmation === 'RESET') {
                try {
                    const response = await fetch('settings_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=reset_attendance'
                    });
                    const result = await response.json();
                    alert(result.message);
                    if (result.success) location.reload();
                } catch (error) {
                    alert('Error resetting attendance');
                }
            } else if (confirmation !== null) {
                alert('Confirmation text did not match. Operation cancelled.');
            }
        }
    </script>
</body>
</html>
