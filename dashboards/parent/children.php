<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

// Fetch parent details
$query_parent = "SELECT p.*, u.email 
                 FROM parents p 
                 JOIN users u ON p.user_id = u.user_id 
                 WHERE p.parent_id = :parent_id";
$stmt_parent = $conn->prepare($query_parent);
$stmt_parent->bindParam(':parent_id', $_SESSION['parent_id']);
$stmt_parent->execute();
$parent = $stmt_parent->fetch(PDO::FETCH_ASSOC);

// Fetch children linked to this parent
$query = "SELECT s.*, u.profile_picture, u.status as account_status
          FROM students s
          LEFT JOIN users u ON s.user_id = u.user_id
          WHERE s.parent_id = :parent_id
          ORDER BY s.first_name";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $_SESSION['parent_id']);
$stmt->execute();
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If viewing specific child details
$selected_child = null;
$child_attendance = [];
$attendance_stats = [];

if (isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
    
    // Verify this student belongs to this parent
    $verify_query = "SELECT s.*, u.profile_picture, u.status as account_status, u.email, u.username
                     FROM students s
                     LEFT JOIN users u ON s.user_id = u.user_id
                     WHERE s.student_id = :student_id AND s.parent_id = :parent_id";
    $stmt_verify = $conn->prepare($verify_query);
    $stmt_verify->execute([':student_id' => $student_id, ':parent_id' => $_SESSION['parent_id']]);
    $selected_child = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_child) {
        // Get attendance stats for this month
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        
        $query_stats = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                        FROM attendance 
                        WHERE student_id = :student_id 
                        AND date BETWEEN :start_date AND :end_date";
        $stmt_stats = $conn->prepare($query_stats);
        $stmt_stats->execute([
            ':student_id' => $student_id,
            ':start_date' => $month_start,
            ':end_date' => $month_end
        ]);
        $attendance_stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        // Get recent attendance records
        $query_attendance = "SELECT a.* 
                             FROM attendance a
                             WHERE a.student_id = :student_id 
                             ORDER BY a.date DESC, a.check_in_time DESC 
                             LIMIT 15";
        $stmt_attendance = $conn->prepare($query_attendance);
        $stmt_attendance->bindParam(':student_id', $student_id);
        $stmt_attendance->execute();
        $child_attendance = $stmt_attendance->fetchAll(PDO::FETCH_ASSOC);
    }
}

$default_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%233b82f6'/%3E%3Cpath d='M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z' fill='%233b82f6'/%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Parent - EduID</title>
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
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="children.php" class="nav-item active">
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
                    <h1>My Children</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Family</span>
                        <i class="fas fa-chevron-right"></i>
                        <span><?php echo $selected_child ? htmlspecialchars($selected_child['first_name']) : 'My Children'; ?></span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                
                <?php if ($selected_child): ?>
                <!-- Child Detail View -->
                <a href="children.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); text-decoration: none; margin-bottom: 1.5rem; font-size: 0.9rem; transition: color 0.2s;">
                    <i class="fas fa-arrow-left"></i> Back to All Children
                </a>
                
                <!-- Child Profile Header -->
                <div class="card" style="margin-bottom: 1.5rem; overflow: hidden;">
                    <div class="profile-header-bg" style="height: 100px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);"></div>
                    <div class="card-body" style="padding: 0 1.5rem 1.5rem;">
                        <div style="display: flex; align-items: flex-end; gap: 1.5rem; margin-top: -40px; flex-wrap: wrap;">
                            <?php 
                            $child_pic = !empty($selected_child['profile_picture']) ? '../../' . $selected_child['profile_picture'] : null;
                            ?>
                            <img src="<?php echo htmlspecialchars($child_pic ?? $default_avatar); ?>" alt="Profile" onerror="this.src='<?php echo htmlspecialchars($default_avatar); ?>'" style="width: 80px; height: 80px; border-radius: 50%; border: 4px solid var(--bg-primary); object-fit: cover; background: var(--bg-secondary);">
                            <div style="flex: 1; padding-bottom: 0.5rem;">
                                <h2 style="margin: 0; color: var(--text-primary); font-size: 1.4rem;"><?php echo htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['last_name']); ?></h2>
                                <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-id-card" style="color: #3b82f6;"></i> 
                                    <?php echo htmlspecialchars($selected_child['student_number']); ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem; padding-bottom: 0.5rem;">
                                <span style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                    <?php echo htmlspecialchars($selected_child['grade'] . ' - ' . $selected_child['class_section']); ?>
                                </span>
                                <span style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i> <?php echo ucfirst($selected_child['account_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Stats for This Month -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem; text-align: center;">
                            <i class="fas fa-calendar" style="font-size: 1.5rem; color: #3b82f6; margin-bottom: 0.5rem;"></i>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #3b82f6;"><?php echo $attendance_stats['total'] ?? 0; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Total Days</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div class="card-body" style="padding: 1rem; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 1.5rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #10b981;"><?php echo $attendance_stats['present'] ?? 0; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Present</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div class="card-body" style="padding: 1rem; text-align: center;">
                            <i class="fas fa-times-circle" style="font-size: 1.5rem; color: #ef4444; margin-bottom: 0.5rem;"></i>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #ef4444;"><?php echo $attendance_stats['absent'] ?? 0; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Absent</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 1rem; text-align: center;">
                            <i class="fas fa-clock" style="font-size: 1.5rem; color: #f59e0b; margin-bottom: 0.5rem;"></i>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #f59e0b;"><?php echo $attendance_stats['late'] ?? 0; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Late</div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <!-- Student Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user"></i> Student Information</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 1rem;">
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Full Name</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['last_name']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Student ID</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['student_number']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Grade</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['grade'] ?? 'Not Assigned'); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Class Section</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['class_section'] ?? 'Not Assigned'); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Date of Birth</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo $selected_child['date_of_birth'] ? date('M d, Y', strtotime($selected_child['date_of_birth'])) : 'N/A'; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Gender</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo ucfirst($selected_child['gender'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-shield-alt"></i> Account Details</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 1rem;">
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Username</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['username'] ?? 'N/A'); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Email</span>
                                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($selected_child['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Account Status</span>
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;"><?php echo ucfirst($selected_child['account_status']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">Face Registration</span>
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; background: <?php echo !empty($selected_child['face_encoding']) ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo !empty($selected_child['face_encoding']) ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo !empty($selected_child['face_encoding']) ? 'Registered' : 'Not Registered'; ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                    <span style="color: var(--text-secondary); font-size: 0.85rem;">QR Code</span>
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; background: <?php echo !empty($selected_child['qr_code']) ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo !empty($selected_child['qr_code']) ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo !empty($selected_child['qr_code']) ? 'Generated' : 'Not Generated'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Attendance -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-check"></i> Recent Attendance (This Month: <?php echo date('F Y'); ?>)</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($child_attendance)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i class="fas fa-calendar-xmark" style="font-size: 3rem; color: var(--text-tertiary); margin-bottom: 1rem;"></i>
                            <p style="color: var(--text-secondary);">No attendance records found for this month.</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--bg-secondary);">
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Date</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Status</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Check In</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Check Out</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($child_attendance as $record): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 1rem; color: var(--text-primary);">
                                            <?php echo date('D, M d, Y', strtotime($record['date'])); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            $status_colors = [
                                                'present' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#10b981'],
                                                'absent' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444'],
                                                'late' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                                                'excused' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6']
                                            ];
                                            $colors = $status_colors[$record['status']] ?? $status_colors['absent'];
                                            ?>
                                            <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?php echo $colors['bg']; ?>; color: <?php echo $colors['color']; ?>;">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; color: var(--text-primary);">
                                            <?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--text-primary);">
                                            <?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--text-secondary);">
                                            <?php 
                                            $method_icons = [
                                                'qr_code' => 'fa-qrcode',
                                                'face_recognition' => 'fa-face-smile',
                                                'manual' => 'fa-hand-point-up'
                                            ];
                                            $icon = $method_icons[$record['verification_method']] ?? 'fa-question';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>" style="margin-right: 0.5rem;"></i>
                                            <?php echo ucwords(str_replace('_', ' ', $record['verification_method'] ?? 'Unknown')); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- All Children Grid View -->
                
                <?php if (empty($children)): ?>
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-children" style="font-size: 4rem; color: var(--text-tertiary); margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Children Linked</h3>
                        <p style="color: var(--text-secondary);">There are no students linked to your account yet. Please contact the school administration to link your children.</p>
                    </div>
                </div>
                <?php else: ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($children as $child): ?>
                    <?php 
                    $child_pic = !empty($child['profile_picture']) ? '../../' . $child['profile_picture'] : null;
                    
                    // Get child's attendance stats for this week
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $week_end = date('Y-m-d', strtotime('friday this week'));
                    
                    $query_week = "SELECT 
                                       COUNT(*) as total,
                                       SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                                   FROM attendance 
                                   WHERE student_id = :student_id 
                                   AND date BETWEEN :start_date AND :end_date";
                    $stmt_week = $conn->prepare($query_week);
                    $stmt_week->execute([
                        ':student_id' => $child['student_id'],
                        ':start_date' => $week_start,
                        ':end_date' => $week_end
                    ]);
                    $week_stats = $stmt_week->fetch(PDO::FETCH_ASSOC);
                    $week_rate = $week_stats['total'] > 0 ? round(($week_stats['present'] / $week_stats['total']) * 100) : 0;
                    ?>
                    <div class="card" style="overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onclick="window.location='children.php?student_id=<?php echo $child['student_id']; ?>'">
                        <div style="height: 80px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);"></div>
                        <div class="card-body" style="padding: 0 1.25rem 1.25rem;">
                            <div style="display: flex; align-items: flex-end; gap: 1rem; margin-top: -35px;">
                                <img src="<?php echo htmlspecialchars($child_pic ?? $default_avatar); ?>" alt="<?php echo htmlspecialchars($child['first_name']); ?>" onerror="this.src='<?php echo htmlspecialchars($default_avatar); ?>'" style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid var(--bg-primary); object-fit: cover; background: var(--bg-secondary);">
                                <div style="padding-bottom: 0.5rem; flex: 1;">
                                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h3>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($child['student_number']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div style="background: var(--bg-secondary); padding: 0.75rem; border-radius: 8px;">
                                    <div style="font-size: 0.65rem; color: var(--text-tertiary); text-transform: uppercase; margin-bottom: 0.25rem;">Grade</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($child['grade'] ?? 'N/A'); ?></div>
                                </div>
                                <div style="background: var(--bg-secondary); padding: 0.75rem; border-radius: 8px;">
                                    <div style="font-size: 0.65rem; color: var(--text-tertiary); text-transform: uppercase; margin-bottom: 0.25rem;">Section</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($child['class_section'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 0.5rem;">
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; background: <?php echo !empty($child['face_encoding']) ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo !empty($child['face_encoding']) ? '#10b981' : '#ef4444'; ?>;">
                                        <i class="fas <?php echo !empty($child['face_encoding']) ? 'fa-face-smile' : 'fa-face-frown'; ?>"></i> Face
                                    </span>
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.7rem; font-weight: 600; background: <?php echo !empty($child['qr_code']) ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo !empty($child['qr_code']) ? '#10b981' : '#ef4444'; ?>;">
                                        <i class="fas fa-qrcode"></i> QR
                                    </span>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.65rem; color: var(--text-tertiary); text-transform: uppercase;">This Week</div>
                                    <div style="font-size: 1rem; font-weight: 700; color: <?php echo $week_rate >= 80 ? '#10b981' : ($week_rate >= 50 ? '#f59e0b' : '#ef4444'); ?>;">
                                        <?php echo $week_rate; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <i class="fas fa-info-circle" style="color: #3b82f6;"></i> Click to view details
                                </span>
                                <i class="fas fa-chevron-right" style="color: var(--text-tertiary);"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <style>
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</body>
</html>
