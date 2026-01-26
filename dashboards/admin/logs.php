<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');

// Filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_role = $_GET['role'] ?? '';
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['to'] ?? $today;
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["DATE(al.access_time) BETWEEN :date_from AND :date_to"];
$params = [':date_from' => $date_from, ':date_to' => $date_to];

if ($filter_type) {
    $where_conditions[] = "al.access_type = :type";
    $params[':type'] = $filter_type;
}

if ($filter_status) {
    $where_conditions[] = "al.status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_role) {
    $where_conditions[] = "u.user_role = :role";
    $params[':role'] = $filter_role;
}

if ($search) {
    $where_conditions[] = "(u.username LIKE :search OR u.email LIKE :search OR al.ip_address LIKE :search OR al.location LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM access_logs al 
                JOIN users u ON al.user_id = u.user_id 
                WHERE $where_clause";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get logs with pagination
$query = "SELECT al.*, u.username, u.email, u.user_role, u.profile_picture
          FROM access_logs al 
          JOIN users u ON al.user_id = u.user_id 
          WHERE $where_clause
          ORDER BY al.access_time DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN al.access_type = 'login' THEN 1 ELSE 0 END) as logins,
    SUM(CASE WHEN al.access_type = 'logout' THEN 1 ELSE 0 END) as logouts,
    SUM(CASE WHEN al.access_type = 'qr_scan' THEN 1 ELSE 0 END) as qr_scans,
    SUM(CASE WHEN al.access_type = 'face_verify' THEN 1 ELSE 0 END) as face_verifies,
    SUM(CASE WHEN al.status = 'success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN al.status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN al.status = 'blocked' THEN 1 ELSE 0 END) as blocked
    FROM access_logs al
    JOIN users u ON al.user_id = u.user_id
    WHERE DATE(al.access_time) BETWEEN :date_from AND :date_to";
$stmt = $conn->prepare($stats_query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's activity
$today_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM access_logs WHERE DATE(access_time) = CURDATE()";
$stmt = $conn->prepare($today_query);
$stmt->execute();
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent activity by hour (last 24 hours)
$hourly_query = "SELECT 
    HOUR(access_time) as hour,
    COUNT(*) as count,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM access_logs 
    WHERE access_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(access_time)
    ORDER BY hour";
$stmt = $conn->prepare($hourly_query);
$stmt->execute();
$hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Activity by type
$type_query = "SELECT al.access_type, COUNT(*) as count 
               FROM access_logs al
               JOIN users u ON al.user_id = u.user_id
               WHERE DATE(al.access_time) BETWEEN :date_from AND :date_to
               GROUP BY al.access_type";
$stmt = $conn->prepare($type_query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$type_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top users by activity
$top_users_query = "SELECT u.user_id, u.username, u.email, u.user_role, COUNT(*) as activity_count
                    FROM access_logs al
                    JOIN users u ON al.user_id = u.user_id
                    WHERE DATE(al.access_time) BETWEEN :date_from AND :date_to
                    GROUP BY u.user_id
                    ORDER BY activity_count DESC
                    LIMIT 5";
$stmt = $conn->prepare($top_users_query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Failed attempts
$failed_query = "SELECT al.*, u.username, u.email, u.user_role
                 FROM access_logs al
                 JOIN users u ON al.user_id = u.user_id
                 WHERE al.status IN ('failed', 'blocked')
                 AND DATE(al.access_time) BETWEEN :date_from AND :date_to
                 ORDER BY al.access_time DESC
                 LIMIT 10";
$stmt = $conn->prepare($failed_query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$failed_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter dropdown
$users_query = "SELECT user_id, username, email, user_role FROM users ORDER BY username";
$stmt = $conn->prepare($users_query);
$stmt->execute();
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Logs - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="logs.php" class="nav-item active">
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
                    <h1>Access Logs</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Reports & Settings</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Access Logs</span>
                    </div>
                </div>
                
                <?php include 'includes/header_profile.php'; ?>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Quick Stats -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05)); border: 1px solid rgba(99, 102, 241, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Today's Activity</div>
                                    <div style="font-size: 1.75rem; font-weight: 700; color: #6366f1;"><?php echo number_format($today_stats['total']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-clock" style="color: #6366f1;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Successful</div>
                                    <div style="font-size: 1.75rem; font-weight: 700; color: #22c55e;"><?php echo number_format($stats['successful']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-check-circle" style="color: #22c55e;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Failed</div>
                                    <div style="font-size: 1.75rem; font-weight: 700; color: #ef4444;"><?php echo number_format($stats['failed']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Blocked</div>
                                    <div style="font-size: 1.75rem; font-weight: 700; color: #f59e0b;"><?php echo number_format($stats['blocked']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-ban" style="color: #f59e0b;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Logins</div>
                                    <div style="font-size: 1.75rem; font-weight: 700; color: #3b82f6;"><?php echo number_format($stats['logins']); ?></div>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sign-in-alt" style="color: #3b82f6;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Activity Timeline -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-area"></i> Activity (Last 24 Hours)</h3>
                        </div>
                        <div class="card-body" style="padding: 1rem;">
                            <canvas id="activityChart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <!-- Activity by Type -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-pie-chart"></i> Activity by Type</h3>
                        </div>
                        <div class="card-body" style="padding: 1rem;">
                            <canvas id="typeChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" id="filterForm">
                            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
                                <div style="flex: 1; min-width: 150px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">From Date</label>
                                    <input type="date" name="from" value="<?php echo $date_from; ?>" style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                </div>
                                <div style="flex: 1; min-width: 150px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">To Date</label>
                                    <input type="date" name="to" value="<?php echo $date_to; ?>" max="<?php echo $today; ?>" style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                </div>
                                <div style="flex: 1; min-width: 120px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">Type</label>
                                    <select name="type" style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                        <option value="">All Types</option>
                                        <option value="login" <?php echo $filter_type === 'login' ? 'selected' : ''; ?>>Login</option>
                                        <option value="logout" <?php echo $filter_type === 'logout' ? 'selected' : ''; ?>>Logout</option>
                                        <option value="qr_scan" <?php echo $filter_type === 'qr_scan' ? 'selected' : ''; ?>>QR Scan</option>
                                        <option value="face_verify" <?php echo $filter_type === 'face_verify' ? 'selected' : ''; ?>>Face Verify</option>
                                        <option value="manual_entry" <?php echo $filter_type === 'manual_entry' ? 'selected' : ''; ?>>Manual Entry</option>
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 100px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">Status</label>
                                    <select name="status" style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                        <option value="">All Status</option>
                                        <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Success</option>
                                        <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="blocked" <?php echo $filter_status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 100px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">Role</label>
                                    <select name="role" style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                        <option value="">All Roles</option>
                                        <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="teacher" <?php echo $filter_role === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                        <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="parent" <?php echo $filter_role === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                    </select>
                                </div>
                                <div style="flex: 2; min-width: 180px;">
                                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">Search</label>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, email, IP..." style="width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.8rem;">
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                                        Apply
                                    </button>
                                    <a href="logs.php" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Logs Layout -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Logs Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list-alt"></i> Access Logs</h3>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                Showing <?php echo count($logs); ?> of <?php echo number_format($total_logs); ?> records
                            </span>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div style="overflow-x: auto;">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr style="background: var(--bg-secondary);">
                                            <th style="padding: 0.6rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">User</th>
                                            <th style="padding: 0.6rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Type</th>
                                            <th style="padding: 0.6rem 0.5rem; text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Status</th>
                                            <th style="padding: 0.6rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">IP</th>
                                            <th style="padding: 0.6rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Time</th>
                                            <th style="padding: 0.6rem 0.5rem; text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                                                <i class="fas fa-search" style="font-size: 2rem; opacity: 0.3; margin-bottom: 0.5rem; display: block;"></i>
                                                No logs found for the selected filters
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 0.5rem 0.75rem;">
                                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 600; color: var(--primary-color); flex-shrink: 0;">
                                                        <?php echo strtoupper(substr($log['username'], 0, 2)); ?>
                                                    </div>
                                                    <div style="min-width: 0;">
                                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($log['username']); ?></div>
                                                        <div style="font-size: 0.65rem; color: var(--text-secondary);">
                                                            <span style="display: inline-block; padding: 0.05rem 0.3rem; border-radius: 8px; background: <?php 
                                                                echo $log['user_role'] === 'admin' ? 'rgba(99, 102, 241, 0.1)' : 
                                                                    ($log['user_role'] === 'teacher' ? 'rgba(34, 197, 94, 0.1)' : 
                                                                    ($log['user_role'] === 'student' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(245, 158, 11, 0.1)')); 
                                                            ?>; color: <?php 
                                                                echo $log['user_role'] === 'admin' ? '#6366f1' : 
                                                                    ($log['user_role'] === 'teacher' ? '#22c55e' : 
                                                                    ($log['user_role'] === 'student' ? '#3b82f6' : '#f59e0b')); 
                                                            ?>; text-transform: capitalize;">
                                                                <?php echo $log['user_role']; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 0.5rem 0.75rem;">
                                                <?php
                                                $type_icons = [
                                                    'login' => ['icon' => 'fa-sign-in-alt', 'color' => '#3b82f6'],
                                                    'logout' => ['icon' => 'fa-sign-out-alt', 'color' => '#64748b'],
                                                    'qr_scan' => ['icon' => 'fa-qrcode', 'color' => '#8b5cf6'],
                                                    'face_verify' => ['icon' => 'fa-smile', 'color' => '#22c55e'],
                                                    'manual_entry' => ['icon' => 'fa-user-edit', 'color' => '#f59e0b']
                                                ];
                                                $t = $type_icons[$log['access_type']] ?? ['icon' => 'fa-question', 'color' => '#94a3b8'];
                                                ?>
                                                <span style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: var(--text-primary);">
                                                    <i class="fas <?php echo $t['icon']; ?>" style="color: <?php echo $t['color']; ?>; font-size: 0.7rem;"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $log['access_type'])); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.5rem 0.5rem; text-align: center;">
                                                <?php
                                                $status_styles = [
                                                    'success' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'color' => '#22c55e', 'icon' => 'fa-check'],
                                                    'failed' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444', 'icon' => 'fa-times'],
                                                    'blocked' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b', 'icon' => 'fa-ban']
                                                ];
                                                $s = $status_styles[$log['status']] ?? $status_styles['failed'];
                                                ?>
                                                <span style="display: inline-flex; align-items: center; gap: 0.2rem; padding: 0.15rem 0.5rem; border-radius: 20px; background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>; font-size: 0.65rem; font-weight: 600; text-transform: capitalize;">
                                                    <i class="fas <?php echo $s['icon']; ?>" style="font-size: 0.55rem;"></i>
                                                    <?php echo $log['status']; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.5rem 0.75rem; font-size: 0.7rem; color: var(--text-secondary); font-family: monospace;">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                            </td>
                                            <td style="padding: 0.5rem 0.75rem;">
                                                <div style="font-size: 0.75rem; color: var(--text-primary);">
                                                    <?php echo date('M j', strtotime($log['access_time'])); ?>
                                                </div>
                                                <div style="font-size: 0.65rem; color: var(--text-secondary);">
                                                    <?php echo date('g:i A', strtotime($log['access_time'])); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 0.5rem 0.5rem; text-align: center;">
                                                <button onclick="viewLogDetails(<?php echo $log['log_id']; ?>)" style="padding: 0.25rem 0.4rem; border: none; background: var(--bg-secondary); color: var(--text-secondary); border-radius: 4px; cursor: pointer; font-size: 0.7rem;" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div style="padding: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: between; gap: 1rem;">
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                </div>
                                <div style="display: flex; gap: 0.25rem; margin-left: auto;">
                                    <?php
                                    $query_params = $_GET;
                                    unset($query_params['page']);
                                    $base_url = 'logs.php?' . http_build_query($query_params);
                                    ?>
                                    
                                    <?php if ($page > 1): ?>
                                    <a href="<?php echo $base_url . '&page=1'; ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-secondary); text-decoration: none; font-size: 0.8rem;">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?php echo $base_url . '&page=' . ($page - 1); ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-secondary); text-decoration: none; font-size: 0.8rem;">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="<?php echo $base_url . '&page=' . $i; ?>" style="padding: 0.4rem 0.7rem; border: 1px solid <?php echo $i === $page ? 'var(--primary-color)' : 'var(--border-color)'; ?>; border-radius: 4px; background: <?php echo $i === $page ? 'var(--primary-color)' : 'transparent'; ?>; color: <?php echo $i === $page ? 'white' : 'var(--text-secondary)'; ?>; text-decoration: none; font-size: 0.8rem;">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo $base_url . '&page=' . ($page + 1); ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-secondary); text-decoration: none; font-size: 0.8rem;">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?php echo $base_url . '&page=' . $total_pages; ?>" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-secondary); text-decoration: none; font-size: 0.8rem;">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Activity Cards - Horizontal Layout -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                        <!-- Top Active Users -->
                        <div class="card">
                            <div class="card-header" style="padding: 0.75rem 1rem;">
                                <h3 class="card-title" style="font-size: 0.9rem;"><i class="fas fa-users"></i> Top Active Users</h3>
                            </div>
                            <div class="card-body" style="padding: 0; max-height: 250px; overflow-y: auto;">
                                <?php if (empty($top_users)): ?>
                                <div style="padding: 1.5rem; text-align: center; color: var(--text-secondary); font-size: 0.85rem;">
                                    No activity data
                                </div>
                                <?php else: ?>
                                <?php foreach ($top_users as $index => $user): ?>
                                <div style="padding: 0.6rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 24px; height: 24px; border-radius: 50%; background: <?php echo $index < 3 ? ($index == 0 ? '#fbbf24' : ($index == 1 ? '#9ca3af' : '#cd7f32')) : 'var(--bg-secondary)'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $index < 3 ? 'white' : 'var(--text-secondary)'; ?>; font-size: 0.65rem; font-weight: 700;">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                        <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: capitalize;">
                                            <?php echo $user['user_role']; ?>
                                        </div>
                                    </div>
                                    <div style="font-weight: 700; color: var(--primary-color); font-size: 0.85rem;">
                                        <?php echo number_format($user['activity_count']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Failed Attempts -->
                        <div class="card">
                            <div class="card-header" style="padding: 0.75rem 1rem;">
                                <h3 class="card-title" style="font-size: 0.9rem;"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Failed Attempts</h3>
                            </div>
                            <div class="card-body" style="padding: 0; max-height: 280px; overflow-y: auto;">
                                <?php if (empty($failed_attempts)): ?>
                                <div style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-shield-alt" style="color: #22c55e; font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
                                    <span style="font-size: 0.8rem;">No failed attempts</span>
                                </div>
                                <?php else: ?>
                                <?php foreach ($failed_attempts as $attempt): ?>
                                <div style="padding: 0.6rem 1rem; border-bottom: 1px solid var(--border-color);">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <span style="font-weight: 600; color: var(--text-primary); font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($attempt['username']); ?>
                                        </span>
                                        <span style="font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 10px; background: <?php echo $attempt['status'] === 'blocked' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $attempt['status'] === 'blocked' ? '#f59e0b' : '#ef4444'; ?>; text-transform: capitalize;">
                                            <?php echo $attempt['status']; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary);">
                                        <?php echo ucwords(str_replace('_', ' ', $attempt['access_type'])); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($attempt['access_time'])); ?>
                                    </div>
                                    <?php if ($attempt['ip_address']): ?>
                                    <div style="font-size: 0.65rem; color: var(--text-tertiary); font-family: monospace;">
                                        IP: <?php echo htmlspecialchars($attempt['ip_address']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Log Details Modal -->
    <div id="logDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Log Details</h3>
                <button class="modal-close" onclick="closeLogDetailsModal()">&times;</button>
            </div>
            <div id="logDetailsContent" style="padding: 1.5rem;">
                <!-- Filled by JS -->
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
            margin: 5% auto;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            max-width: 90%;
            animation: modalSlide 0.3s ease;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        .modal-header h3 {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        .detail-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            width: 120px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
            word-break: break-word;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Store logs data for detail view
        const logsData = <?php echo json_encode($logs); ?>;
        
        function viewLogDetails(logId) {
            const log = logsData.find(l => l.log_id == logId);
            if (!log) return;
            
            const statusColors = {
                'success': '#22c55e',
                'failed': '#ef4444',
                'blocked': '#f59e0b'
            };
            
            const typeLabels = {
                'login': 'Login',
                'logout': 'Logout',
                'qr_scan': 'QR Code Scan',
                'face_verify': 'Face Verification',
                'manual_entry': 'Manual Entry'
            };
            
            const content = document.getElementById('logDetailsContent');
            content.innerHTML = `
                <div class="detail-row">
                    <div class="detail-label">Log ID</div>
                    <div class="detail-value">#${log.log_id}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">User</div>
                    <div class="detail-value">
                        <strong>${log.username}</strong><br>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">${log.email}</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Role</div>
                    <div class="detail-value" style="text-transform: capitalize;">${log.user_role}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Access Type</div>
                    <div class="detail-value">${typeLabels[log.access_type] || log.access_type}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span style="color: ${statusColors[log.status] || '#64748b'}; font-weight: 600; text-transform: capitalize;">
                            ${log.status}
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">${new Date(log.access_time).toLocaleString()}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">IP Address</div>
                    <div class="detail-value" style="font-family: monospace;">${log.ip_address || '-'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Location</div>
                    <div class="detail-value">${log.location || '-'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">User Agent</div>
                    <div class="detail-value" style="font-size: 0.75rem; color: var(--text-secondary);">${log.user_agent || '-'}</div>
                </div>
                ${log.remarks ? `
                <div class="detail-row">
                    <div class="detail-label">Remarks</div>
                    <div class="detail-value">${log.remarks}</div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('logDetailsModal').style.display = 'block';
        }
        
        function closeLogDetailsModal() {
            document.getElementById('logDetailsModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('logDetailsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Charts
        Chart.defaults.font.family = 'Inter, sans-serif';
        
        // Activity Chart (24 hours)
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const hourlyData = <?php echo json_encode($hourly_data); ?>;
        
        // Fill missing hours
        const hours = [];
        const successData = [];
        const failedData = [];
        for (let i = 0; i < 24; i++) {
            hours.push(i.toString().padStart(2, '0') + ':00');
            const hourData = hourlyData.find(d => d.hour == i);
            successData.push(hourData ? parseInt(hourData.success) : 0);
            failedData.push(hourData ? parseInt(hourData.failed) : 0);
        }
        
        new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: hours,
                datasets: [
                    {
                        label: 'Success',
                        data: successData,
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderRadius: 4
                    },
                    {
                        label: 'Failed',
                        data: failedData,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 15, font: { size: 11 } }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 }, maxRotation: 45 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
        
        // Type Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeData = <?php echo json_encode($type_data); ?>;
        
        const typeColors = {
            'login': '#3b82f6',
            'logout': '#64748b',
            'qr_scan': '#8b5cf6',
            'face_verify': '#22c55e',
            'manual_entry': '#f59e0b'
        };
        
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: typeData.map(d => d.access_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())),
                datasets: [{
                    data: typeData.map(d => d.count),
                    backgroundColor: typeData.map(d => typeColors[d.access_type] || '#94a3b8'),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 12, font: { size: 10 } }
                    }
                },
                cutout: '55%'
            }
        });
    </script>
</body>
</html>
