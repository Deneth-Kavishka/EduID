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

$child_ids = array_column($children, 'student_id');

// Filter
$filter = $_GET['filter'] ?? 'upcoming';
$selected_child = $_GET['child'] ?? 'all';

// Get exam entries for children
$exam_entries = [];
if (!empty($child_ids)) {
    $query = "SELECT ee.*, s.first_name, s.last_name, s.student_number, s.grade, s.class_section
              FROM exam_entries ee
              JOIN students s ON ee.student_id = s.student_id
              WHERE ee.student_id IN (" . implode(',', array_fill(0, count($child_ids), '?')) . ")";
    
    $params = $child_ids;
    
    if ($filter === 'upcoming') {
        $query .= " AND ee.exam_date >= CURDATE()";
    } elseif ($filter === 'past') {
        $query .= " AND ee.exam_date < CURDATE()";
    }
    
    if ($selected_child !== 'all') {
        $query .= " AND ee.student_id = ?";
        $params[] = $selected_child;
    }
    
    $query .= " ORDER BY ee.exam_date " . ($filter === 'past' ? 'DESC' : 'ASC');
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $exam_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group exams by date
$exams_by_date = [];
foreach ($exam_entries as $exam) {
    $date = $exam['exam_date'];
    if (!isset($exams_by_date[$date])) {
        $exams_by_date[$date] = [];
    }
    $exams_by_date[$date][] = $exam;
}

// Stats
$upcoming_count = 0;
$today_count = 0;
$verified_count = 0;

if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    
    // Upcoming exams
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exam_entries WHERE student_id IN ($placeholders) AND exam_date > CURDATE()");
    $stmt->execute($child_ids);
    $upcoming_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Today's exams
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exam_entries WHERE student_id IN ($placeholders) AND exam_date = CURDATE()");
    $stmt->execute($child_ids);
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Verified entries
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exam_entries WHERE student_id IN ($placeholders) AND verification_status = 'verified'");
    $stmt->execute($child_ids);
    $verified_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedule - Parent - EduID</title>
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
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance History</span>
                    </a>
                    <a href="exams.php" class="nav-item active">
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
                    <h1>Exam Schedule</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Monitoring</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Exams</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-alt" style="color: #8b5cf6; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Upcoming</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo $upcoming_count; ?></div>
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
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Today</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $today_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div class="card-body" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-check-double" style="color: #10b981; font-size: 1.1rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Verified</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $verified_count; ?></div>
                                </div>
                            </div>
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
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Filter</label>
                                <select name="filter" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary);">
                                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Exams</option>
                                    <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past Exams</option>
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Exams</option>
                                </select>
                            </div>
                            <button type="submit" style="padding: 0.6rem 1.5rem; background: #8b5cf6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Exam Schedule -->
                <?php if (empty($exams_by_date)): ?>
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-clipboard-list" style="font-size: 4rem; color: var(--text-tertiary); margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Exams Found</h3>
                        <p style="color: var(--text-secondary);">
                            <?php echo $filter === 'upcoming' ? 'No upcoming exams scheduled for your children.' : 'No exam records found for the selected filters.'; ?>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                
                <?php foreach ($exams_by_date as $date => $exams): ?>
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-header" style="background: var(--bg-secondary);">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-day" style="color: #8b5cf6;"></i>
                            <?php 
                            $exam_date = strtotime($date);
                            $today = strtotime(date('Y-m-d'));
                            if ($exam_date === $today) {
                                echo '<span style="color: #f59e0b;">Today</span> - ';
                            } elseif ($exam_date === $today + 86400) {
                                echo '<span style="color: #3b82f6;">Tomorrow</span> - ';
                            }
                            echo date('l, F d, Y', $exam_date);
                            ?>
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php foreach ($exams as $exam): ?>
                        <div style="display: flex; align-items: center; gap: 1.5rem; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                            <div style="width: 50px; height: 50px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-file-alt" style="font-size: 1.25rem;"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($exam['exam_name']); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-user" style="width: 16px;"></i> <?php echo htmlspecialchars($exam['first_name'] . ' ' . $exam['last_name']); ?>
                                    <span style="margin: 0 0.5rem;">|</span>
                                    <i class="fas fa-graduation-cap" style="width: 16px;"></i> <?php echo htmlspecialchars($exam['grade'] . ' - ' . $exam['class_section']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <?php if ($exam['exam_hall']): ?>
                                <div style="font-size: 0.85rem; color: var(--text-primary); margin-bottom: 0.25rem;">
                                    <i class="fas fa-door-open" style="color: #8b5cf6;"></i> <?php echo htmlspecialchars($exam['exam_hall']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($exam['seat_number']): ?>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                    Seat: <?php echo htmlspecialchars($exam['seat_number']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php 
                                $status_styles = [
                                    'pending' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                                    'verified' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#10b981'],
                                    'rejected' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444']
                                ];
                                $style = $status_styles[$exam['verification_status']] ?? $status_styles['pending'];
                                ?>
                                <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                                    <?php echo ucfirst($exam['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
</body>
</html>
