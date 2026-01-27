<?php
require_once '../../config/config.php';
checkRole(['parent']);

$db = new Database();
$conn = $db->getConnection();

$parent_id = $_SESSION['parent_id'];

// Get parent details
$query = "SELECT p.*, u.email FROM parents p JOIN users u ON p.user_id = u.user_id WHERE p.parent_id = :parent_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Get children
$query = "SELECT s.*, u.username, u.email as student_email 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.parent_id = :parent_id AND u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':parent_id', $parent_id);
$stmt->execute();
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get combined statistics for all children
$child_ids = array_column($children, 'student_id');
$stats = [
    'total_children' => count($children),
    'present_today' => 0,
    'absent_today' => 0,
    'total_attendance' => 0
];

if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    
    // Today's attendance
    $query = "SELECT COUNT(*) as count FROM attendance 
              WHERE student_id IN ($placeholders) AND date = CURDATE() AND status = 'present'";
    $stmt = $conn->prepare($query);
    $stmt->execute($child_ids);
    $stats['present_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Absent today
    $query = "SELECT COUNT(*) as count FROM attendance 
              WHERE student_id IN ($placeholders) AND date = CURDATE() AND status = 'absent'";
    $stmt = $conn->prepare($query);
    $stmt->execute($child_ids);
    $stats['absent_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total attendance records
    $query = "SELECT COUNT(*) as count FROM attendance WHERE student_id IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $stmt->execute($child_ids);
    $stats['total_attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - EduID</title>
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
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="index.php" class="nav-item active">
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
                    <h1>Welcome, <?php echo htmlspecialchars($parent['first_name']); ?>!</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Dashboard</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">2</span>
                    </div>
                    
                    <div class="user-menu">
                        <img src="../../assets/images/default-avatar.png" alt="Parent" class="user-avatar" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Ccircle cx=%2712%27 cy=%278%27 r=%274%27 fill=%27%23cbd5e1%27/%3E%3Cpath d=%27M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z%27 fill=%27%23cbd5e1%27/%3E%3C/svg%3E';">
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Parent Info Card -->
                <div class="card mb-3" style="background: linear-gradient(135deg, #7c3aed, #ec4899); color: white; padding: 2rem;">
                    <div>
                        <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                        </h2>
                        <p style="opacity: 0.95; font-size: 1.1rem;">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($parent['phone']); ?> | 
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($parent['email']); ?>
                        </p>
                        <p style="opacity: 0.95; font-size: 1.1rem;">
                            <i class="fas fa-users"></i> Monitoring <?php echo $stats['total_children']; ?> child<?php echo $stats['total_children'] != 1 ? 'ren' : ''; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-children"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_children']; ?></h3>
                            <p>Total Children</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['present_today']; ?></h3>
                            <p>Present Today</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['absent_today']; ?></h3>
                            <p>Absent Today</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_attendance']; ?></h3>
                            <p>Total Records</p>
                        </div>
                    </div>
                </div>
                
                <!-- Children Overview -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-children"></i> My Children
                        </h3>
                        <a href="children.php" style="color: var(--primary-color); font-size: 0.875rem;">View Details</a>
                    </div>
                    
                    <?php if (empty($children)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p style="font-size: 1.1rem;">No children linked to your account</p>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Contact administration to link student profiles</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; padding: 1rem 0;">
                            <?php foreach ($children as $child): ?>
                                <?php
                                // Get child's attendance percentage
                                $query = "SELECT 
                                          COUNT(*) as total_days,
                                          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
                                          FROM attendance WHERE student_id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([$child['student_id']]);
                                $child_stats = $stmt->fetch(PDO::FETCH_ASSOC);
                                $attendance_rate = $child_stats['total_days'] > 0 
                                    ? round(($child_stats['present_days'] / $child_stats['total_days']) * 100, 1) 
                                    : 0;
                                ?>
                                <div class="card" style="padding: 1.5rem;">
                                    <div style="display: flex; align-items: start; gap: 1rem; margin-bottom: 1rem;">
                                        <?php $childAvatar = !empty($child['profile_picture']) ? $child['profile_picture'] : '../../assets/images/default-avatar.png'; ?>
                                        <img src="<?php echo htmlspecialchars($childAvatar); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Ccircle cx=%2712%27 cy=%278%27 r=%274%27 fill=%27%23cbd5e1%27/%3E%3Cpath d=%27M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z%27 fill=%27%23cbd5e1%27/%3E%3C/svg%3E';">
                                        <div style="flex: 1;">
                                            <h4 style="font-size: 1.1rem; margin-bottom: 0.25rem; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                            </h4>
                                            <p style="font-size: 0.85rem; color: var(--text-tertiary);">
                                                ID: <?php echo htmlspecialchars($child['student_number']); ?>
                                            </p>
                                            <p style="font-size: 0.85rem; color: var(--text-secondary);">
                                                Grade: <?php echo htmlspecialchars($child['grade'] . '-' . $child['class_section']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Attendance Rate</span>
                                            <span style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);"><?php echo $attendance_rate; ?>%</span>
                                        </div>
                                        <div style="background: var(--bg-tertiary); height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: <?php echo $attendance_rate >= 75 ? 'var(--success-color)' : ($attendance_rate >= 50 ? 'var(--warning-color)' : 'var(--danger-color)'); ?>; 
                                                        height: 100%; width: <?php echo $attendance_rate; ?>%; transition: width 0.3s;"></div>
                                        </div>
                                    </div>
                                    
                                    <a href="children.php?student_id=<?php echo $child['student_id']; ?>" 
                                       style="display: block; text-align: center; background: var(--primary-color); color: white; 
                                              padding: 0.75rem; border-radius: var(--border-radius); font-weight: 600; transition: all 0.3s;">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Links -->
                <div class="mt-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="attendance.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Attendance History</h3>
                    </a>
                    
                    <a href="exams.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-clipboard-list" style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Exam Schedule</h3>
                    </a>
                    
                    <a href="events.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-calendar-days" style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Events</h3>
                    </a>
                    
                    <a href="notifications.php" class="card" style="text-align: center; padding: 1.5rem; cursor: pointer;">
                        <i class="fas fa-bell" style="font-size: 2rem; color: var(--danger-color); margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1rem; color: var(--text-primary);">Notifications</h3>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
