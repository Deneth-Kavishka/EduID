<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

$parent_id = $_SESSION['parent_id'];

// Get parent details
$query = "SELECT p.*, u.email, u.profile_picture FROM parents p JOIN users u ON p.user_id = u.user_id WHERE p.parent_id = :parent_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Get children
$query = "SELECT s.student_id, s.first_name, s.last_name, s.student_number, s.grade, s.class_section
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.parent_id = :parent_id AND u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filters
$selected_child = $_GET['child'] ?? 'all';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_status = $_GET['status'] ?? 'all';

// Build child IDs array
$child_ids = array_column($children, 'student_id');

// Prepare date range
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get attendance records
$attendance_records = [];
$stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];

if (!empty($child_ids)) {
    // Build query
    $query = "SELECT a.*, s.first_name, s.last_name, s.student_number, s.grade, s.class_section
              FROM attendance a
              JOIN students s ON a.student_id = s.student_id
              WHERE a.student_id IN (" . implode(',', array_fill(0, count($child_ids), '?')) . ")
              AND a.date BETWEEN ? AND ?";
    
    $params = $child_ids;
    $params[] = $month_start;
    $params[] = $month_end;
    
    if ($selected_child !== 'all') {
        $query .= " AND a.student_id = ?";
        $params[] = $selected_child;
    }
    
    if ($selected_status !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $selected_status;
    }
    
    $query .= " ORDER BY a.date DESC, s.first_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats for selected filters
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
                    FROM attendance 
                    WHERE student_id IN (" . implode(',', array_fill(0, count($child_ids), '?')) . ")
                    AND date BETWEEN ? AND ?";
    
    $stats_params = $child_ids;
    $stats_params[] = $month_start;
    $stats_params[] = $month_end;
    
    if ($selected_child !== 'all') {
        $stats_query .= " AND student_id = ?";
        $stats_params[] = $selected_child;
    }
    
    $stmt = $conn->prepare($stats_query);
    $stmt->execute($stats_params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

$attendance_rate = $stats['total'] > 0 ? round((($stats['present'] + $stats['late']) / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Parent - EduID</title>
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
                    <a href="children.php" class="nav-item">
                        <i class="fas fa-children"></i>
                        <span>My Children</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Monitoring</div>
                    <a href="attendance.php" class="nav-item active">
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
                    <h1>Attendance History</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Monitoring</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Attendance</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div class="card-body" style="padding: 0.75rem; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 1.1rem; color: #10b981; margin-bottom: 0.25rem;"></i>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #10b981;"><?php echo $stats['present'] ?? 0; ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase;">Present</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div class="card-body" style="padding: 0.75rem; text-align: center;">
                            <i class="fas fa-times-circle" style="font-size: 1.1rem; color: #ef4444; margin-bottom: 0.25rem;"></i>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #ef4444;"><?php echo $stats['absent'] ?? 0; ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase;">Absent</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div class="card-body" style="padding: 0.75rem; text-align: center;">
                            <i class="fas fa-clock" style="font-size: 1.1rem; color: #f59e0b; margin-bottom: 0.25rem;"></i>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b;"><?php echo $stats['late'] ?? 0; ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase;">Late</div>
                        </div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div class="card-body" style="padding: 0.75rem; text-align: center;">
                            <i class="fas fa-percentage" style="font-size: 1.1rem; color: #3b82f6; margin-bottom: 0.25rem;"></i>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #3b82f6;"><?php echo $attendance_rate; ?>%</div>
                            <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase;">Rate</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Child</label>
                                <select name="child" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                    <option value="all">All Children</option>
                                    <?php foreach ($children as $child): ?>
                                    <option value="<?php echo $child['student_id']; ?>" <?php echo $selected_child == $child['student_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Month</label>
                                <input type="month" name="month" value="<?php echo $selected_month; ?>" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                            </div>
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Status</label>
                                <select name="status" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                    <option value="all" <?php echo $selected_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="present" <?php echo $selected_status === 'present' ? 'selected' : ''; ?>>Present</option>
                                    <option value="absent" <?php echo $selected_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                    <option value="late" <?php echo $selected_status === 'late' ? 'selected' : ''; ?>>Late</option>
                                    <option value="excused" <?php echo $selected_status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                                </select>
                            </div>
                            <button type="submit" style="padding: 0.6rem 1.5rem; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Attendance Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-check"></i> Attendance Records - <?php echo date('F Y', strtotime($month_start)); ?></h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($attendance_records)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i class="fas fa-calendar-xmark" style="font-size: 3rem; color: var(--text-tertiary); margin-bottom: 1rem;"></i>
                            <p style="color: var(--text-secondary);">No attendance records found for the selected filters.</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--bg-secondary);">
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Date</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Child</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Status</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Check In</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Check Out</th>
                                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase;">Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 1rem; color: var(--text-primary);">
                                            <div style="font-weight: 500;"><?php echo date('D, M d', strtotime($record['date'])); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-tertiary);"><?php echo date('Y', strtotime($record['date'])); ?></div>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-tertiary);"><?php echo htmlspecialchars($record['grade'] . ' - ' . $record['class_section']); ?></div>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            $status_styles = [
                                                'present' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
                                                'absent' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
                                                'late' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
                                                'excused' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6', 'icon' => 'fa-info-circle']
                                            ];
                                            $style = $status_styles[$record['status']] ?? $status_styles['absent'];
                                            ?>
                                            <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                                                <i class="fas <?php echo $style['icon']; ?>"></i> <?php echo ucfirst($record['status']); ?>
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
                                            $methods = [
                                                'qr_code' => ['icon' => 'fa-qrcode', 'label' => 'QR Code'],
                                                'face_recognition' => ['icon' => 'fa-face-smile', 'label' => 'Face'],
                                                'manual' => ['icon' => 'fa-hand-point-up', 'label' => 'Manual']
                                            ];
                                            $method = $methods[$record['verification_method']] ?? ['icon' => 'fa-question', 'label' => 'Unknown'];
                                            ?>
                                            <i class="fas <?php echo $method['icon']; ?>" style="margin-right: 0.25rem;"></i>
                                            <?php echo $method['label']; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
