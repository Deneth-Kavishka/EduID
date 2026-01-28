<?php
require_once '../../config/config.php';
checkRole(['teacher']);

$db = new Database();
$conn = $db->getConnection();

$teacher_id = $_SESSION['teacher_id'];

// Get teacher details
$query = "SELECT t.*, u.email FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE t.teacher_id = :teacher_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_type = $_GET['type'] ?? '';
$search_query = $_GET['search'] ?? '';

// Parse month for date range
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get events for selected month
$query = "SELECT * FROM events WHERE event_date BETWEEN :start AND :end";

$params = [
    ':start' => $month_start,
    ':end' => $month_end
];

if ($selected_type) {
    $query .= " AND event_type = :type";
    $params[':type'] = $selected_type;
}

if ($search_query) {
    $query .= " AND (event_name LIKE :search OR description LIKE :search2)";
    $params[':search'] = '%' . $search_query . '%';
    $params[':search2'] = '%' . $search_query . '%';
}

$query .= " ORDER BY event_date ASC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events (next 7 days)
$query = "SELECT * FROM events WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          ORDER BY event_date ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event types for filter
$query = "SELECT DISTINCT event_type FROM events WHERE event_type IS NOT NULL ORDER BY event_type";
$stmt = $conn->prepare($query);
$stmt->execute();
$event_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$query = "SELECT 
    COUNT(*) as total_events,
    COUNT(CASE WHEN event_date >= CURDATE() THEN 1 END) as upcoming,
    COUNT(CASE WHEN event_type = 'exam' THEN 1 END) as exams,
    COUNT(CASE WHEN event_type = 'holiday' THEN 1 END) as holidays
    FROM events 
    WHERE event_date BETWEEN :start AND :end";
$stmt = $conn->prepare($query);
$stmt->bindParam(':start', $month_start);
$stmt->bindParam(':end', $month_end);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Event type colors
$type_colors = [
    'exam' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444', 'icon' => 'file-alt'],
    'holiday' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#10b981', 'icon' => 'umbrella-beach'],
    'meeting' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6', 'icon' => 'users'],
    'sports' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b', 'icon' => 'futbol'],
    'cultural' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'color' => '#8b5cf6', 'icon' => 'music'],
    'academic' => ['bg' => 'rgba(6, 182, 212, 0.1)', 'color' => '#06b6d4', 'icon' => 'graduation-cap'],
    'other' => ['bg' => 'rgba(107, 114, 128, 0.1)', 'color' => '#6b7280', 'icon' => 'calendar-day'],
];

// Generate calendar data
$calendar_start = strtotime('first day of ' . $selected_month);
$calendar_end = strtotime('last day of ' . $selected_month);
$first_day_of_week = date('w', $calendar_start);
$days_in_month = date('t', $calendar_start);

// Create event map by date
$events_by_date = [];
foreach ($events as $event) {
    $event_date = $event['event_date'];
    if ($event_date) {
        $date_key = date('Y-m-d', strtotime($event_date));
        if (!isset($events_by_date[$date_key])) {
            $events_by_date[$date_key] = [];
        }
        $events_by_date[$date_key][] = $event;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Teacher - EduID</title>
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
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Verification</div>
                    <a href="qr-scanner.php" class="nav-item">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Scanner</span>
                    </a>
                    <a href="face-verification.php" class="nav-item">
                        <i class="fas fa-face-smile"></i>
                        <span>Face Verification</span>
                    </a>
                    <a href="mark-attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Mark Attendance</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="students.php" class="nav-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>My Students</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Verification</span>
                    </a>
                    <a href="events.php" class="nav-item active">
                        <i class="fas fa-calendar-days"></i>
                        <span>Events</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
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
                    <h1>Events</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Management</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Events</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Row -->
                <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calendar-days" style="font-size: 1.25rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Total Events</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_events']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock" style="font-size: 1.25rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Upcoming</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $stats['upcoming']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-alt" style="font-size: 1.25rem; color: #ef4444;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Exams</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo $stats['exams']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 45px; height: 45px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-umbrella-beach" style="font-size: 1.25rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.15rem;">Holidays</p>
                                <h3 style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6;"><?php echo $stats['holidays']; ?></h3>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem;">
                    <!-- Main Content -->
                    <div>
                        <!-- Month Navigation & Filters -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-body" style="padding: 1rem;">
                                <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                                    <div style="flex: 1; min-width: 150px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Month</label>
                                        <input type="month" name="month" value="<?php echo $selected_month; ?>" class="form-input" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 130px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Type</label>
                                        <select name="type" class="form-select" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                            <option value="">All Types</option>
                                            <?php foreach ($event_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $selected_type === $type ? 'selected' : ''; ?>><?php echo ucfirst(htmlspecialchars($type)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div style="flex: 1.5; min-width: 180px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                                        <div style="position: relative;">
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search events..." class="form-input" style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                            <i class="fas fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);"></i>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer;">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="events.php" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.6rem 1rem; border-radius: 8px; font-weight: 500; border: 1px solid var(--border-color); text-decoration: none;">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Calendar View -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title"><i class="fas fa-calendar"></i> <?php echo date('F Y', strtotime($selected_month)); ?></h3>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="?month=<?php echo date('Y-m', strtotime($selected_month . ' -1 month')); ?>" class="btn" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); text-decoration: none;">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <a href="?month=<?php echo date('Y-m'); ?>" class="btn" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); text-decoration: none; font-size: 0.8rem;">
                                        Today
                                    </a>
                                    <a href="?month=<?php echo date('Y-m', strtotime($selected_month . ' +1 month')); ?>" class="btn" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); text-decoration: none;">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card-body" style="padding: 0.5rem;">
                                <!-- Calendar Grid -->
                                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                                    <!-- Day Headers -->
                                    <?php 
                                    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                                    foreach ($days as $day): 
                                    ?>
                                    <div style="padding: 0.5rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">
                                        <?php echo $day; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Empty cells before first day -->
                                    <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                                    <div style="padding: 0.5rem; min-height: 80px; background: var(--bg-secondary); border-radius: 4px; opacity: 0.5;"></div>
                                    <?php endfor; ?>
                                    
                                    <!-- Calendar Days -->
                                    <?php for ($day = 1; $day <= $days_in_month; $day++): 
                                        $current_date = $selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                        $is_today = $current_date === date('Y-m-d');
                                        $day_events = $events_by_date[$current_date] ?? [];
                                    ?>
                                    <div class="calendar-day" style="padding: 0.5rem; min-height: 80px; background: <?php echo $is_today ? 'rgba(59, 130, 246, 0.1)' : 'var(--bg-secondary)'; ?>; border-radius: 4px; border: <?php echo $is_today ? '2px solid #3b82f6' : '1px solid var(--border-color)'; ?>; cursor: pointer;" onclick="showDayEvents('<?php echo $current_date; ?>')">
                                        <div style="font-weight: <?php echo $is_today ? '700' : '500'; ?>; color: <?php echo $is_today ? '#3b82f6' : 'var(--text-primary)'; ?>; font-size: 0.85rem; margin-bottom: 0.25rem;">
                                            <?php echo $day; ?>
                                        </div>
                                        <?php if (!empty($day_events)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 2px;">
                                            <?php foreach (array_slice($day_events, 0, 2) as $event): 
                                                $type_style = $type_colors[$event['event_type']] ?? $type_colors['other'];
                                            ?>
                                            <div style="font-size: 0.65rem; padding: 2px 4px; background: <?php echo $type_style['bg']; ?>; color: <?php echo $type_style['color']; ?>; border-radius: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars(substr($event['event_name'], 0, 12)); ?>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (count($day_events) > 2): ?>
                                            <div style="font-size: 0.6rem; color: var(--text-tertiary);">+<?php echo count($day_events) - 2; ?> more</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Events List -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-list"></i> Events This Month</h3>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($events)): ?>
                                <div style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-calendar-xmark" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p style="font-size: 1rem;">No events found this month</p>
                                </div>
                                <?php else: ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($events as $event): 
                                        $type_style = $type_colors[$event['event_type']] ?? $type_colors['other'];
                                        $event_date = $event['event_date'] ?? $event['start_date'];
                                        $is_past = strtotime($event_date) < strtotime('today');
                                        $is_multi_day = !empty($event['start_date']) && !empty($event['end_date']) && $event['start_date'] !== $event['end_date'];
                                    ?>
                                    <div class="event-item" style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid var(--border-color); opacity: <?php echo $is_past ? '0.6' : '1'; ?>;">
                                        <!-- Date Box -->
                                        <div style="min-width: 60px; text-align: center;">
                                            <div style="background: <?php echo $type_style['bg']; ?>; border-radius: 8px; padding: 0.5rem;">
                                                <div style="font-size: 1.25rem; font-weight: 700; color: <?php echo $type_style['color']; ?>;">
                                                    <?php echo date('d', strtotime($event_date)); ?>
                                                </div>
                                                <div style="font-size: 0.7rem; color: <?php echo $type_style['color']; ?>; text-transform: uppercase;">
                                                    <?php echo date('M', strtotime($event_date)); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Event Details -->
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                                                <div>
                                                    <h4 style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem; margin-bottom: 0.25rem;">
                                                        <?php echo htmlspecialchars($event['event_name']); ?>
                                                    </h4>
                                                    <?php if ($event['description']): ?>
                                                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                                        <?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?><?php echo strlen($event['description']) > 100 ? '...' : ''; ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.75rem; color: var(--text-tertiary);">
                                                        <!-- Single day events only -->
                                                        <?php if ($event['venue']): ?>
                                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($event['start_time']): ?>
                                                        <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['start_time'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: <?php echo $type_style['bg']; ?>; color: <?php echo $type_style['color']; ?>; border-radius: 6px; font-size: 0.7rem; font-weight: 600; white-space: nowrap;">
                                                    <i class="fas fa-<?php echo $type_style['icon']; ?>"></i>
                                                    <?php echo ucfirst($event['event_type'] ?? 'other'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Sidebar -->
                    <div>
                        <!-- Upcoming Events -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bell"></i> Coming Up</h3>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($upcoming_events)): ?>
                                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-calendar-check" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                                    <p style="font-size: 0.85rem;">No upcoming events</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($upcoming_events as $event): 
                                    $type_style = $type_colors[$event['event_type']] ?? $type_colors['other'];
                                    $event_date = $event['event_date'] ?? $event['start_date'];
                                    $days_until = (strtotime($event_date) - strtotime('today')) / 86400;
                                ?>
                                <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color);">
                                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: <?php echo $type_style['bg']; ?>; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-<?php echo $type_style['icon']; ?>" style="color: <?php echo $type_style['color']; ?>; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem;"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.15rem;">
                                                <?php echo date('D, M d', strtotime($event_date)); ?>
                                                <?php if ($days_until == 0): ?>
                                                <span style="margin-left: 0.25rem; padding: 0.1rem 0.35rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 4px; font-weight: 600;">Today</span>
                                                <?php elseif ($days_until == 1): ?>
                                                <span style="margin-left: 0.25rem; padding: 0.1rem 0.35rem; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 4px; font-weight: 600;">Tomorrow</span>
                                                <?php elseif ($days_until <= 3): ?>
                                                <span style="margin-left: 0.25rem; padding: 0.1rem 0.35rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 4px; font-weight: 600;">In <?php echo (int)$days_until; ?> days</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Event Types Legend -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-tags"></i> Event Types</h3>
                            </div>
                            <div class="card-body" style="padding: 1rem;">
                                <div style="display: grid; gap: 0.5rem;">
                                    <?php foreach ($type_colors as $type => $style): ?>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 28px; height: 28px; border-radius: 6px; background: <?php echo $style['bg']; ?>; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-<?php echo $style['icon']; ?>" style="font-size: 0.75rem; color: <?php echo $style['color']; ?>;"></i>
                                        </div>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary); text-transform: capitalize;"><?php echo $type; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Event Details Modal -->
    <div id="eventModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
        <div style="background: var(--bg-primary); border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 500px; width: 90%; max-height: 90vh; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 id="modalDate" style="font-weight: 600; color: var(--text-primary);"></h3>
                <button onclick="closeModal()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.25rem;">&times;</button>
            </div>
            <div id="modalContent" style="padding: 1.5rem; max-height: 60vh; overflow-y: auto;">
                <!-- Events will be loaded here -->
            </div>
        </div>
    </div>
    
    <style>
        .calendar-day:hover {
            background: var(--bg-secondary) !important;
            border-color: #3b82f6 !important;
        }
        
        .event-item:hover {
            background: var(--bg-secondary);
        }
        
        @media (max-width: 1024px) {
            .content-area > div {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script>
        const eventsData = <?php echo json_encode($events); ?>;
        const typeColors = <?php echo json_encode($type_colors); ?>;
        
        function showDayEvents(date) {
            const dayEvents = eventsData.filter(e => {
                const eventDate = e.event_date || e.start_date;
                return eventDate === date;
            });
            
            const modal = document.getElementById('eventModal');
            const modalDate = document.getElementById('modalDate');
            const modalContent = document.getElementById('modalContent');
            
            const dateObj = new Date(date + 'T00:00:00');
            modalDate.innerHTML = '<i class="fas fa-calendar-day" style="color: #3b82f6; margin-right: 0.5rem;"></i>' + 
                dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            if (dayEvents.length === 0) {
                modalContent.innerHTML = '<div style="text-align: center; color: var(--text-secondary); padding: 2rem;"><i class="fas fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i><p>No events on this day</p></div>';
            } else {
                let html = '';
                dayEvents.forEach(event => {
                    const style = typeColors[event.event_type] || typeColors['other'];
                    html += `
                        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: 8px; margin-bottom: 0.75rem; border-left: 4px solid ${style.color};">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                                <h4 style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">${event.title}</h4>
                                <span style="padding: 0.2rem 0.5rem; background: ${style.bg}; color: ${style.color}; border-radius: 4px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize;">${event.event_type || 'other'}</span>
                            </div>
                            ${event.description ? `<p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">${event.description}</p>` : ''}
                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-tertiary);">
                                ${event.location ? `<span><i class="fas fa-map-marker-alt"></i> ${event.location}</span>` : ''}
                                ${event.start_time ? `<span><i class="fas fa-clock"></i> ${event.start_time}</span>` : ''}
                            </div>
                        </div>
                    `;
                });
                modalContent.innerHTML = html;
            }
            
            modal.style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Preserve sidebar scroll position and ensure active item is visible
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar-nav');
            const activeItem = document.querySelector('.nav-item.active');
            
            if (sidebar && activeItem) {
                // Scroll the active item into view smoothly
                setTimeout(() => {
                    activeItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 100);
            }
        });
        
        // Universal time update function for navbar and stats cards
        function updateAllTimeDisplays() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit', year: 'numeric' });
            
            // Update navbar time
            const navbarTime = document.getElementById('navbarTime');
            const navbarDate = document.getElementById('navbarDate');
            if (navbarTime && navbarDate) {
                navbarTime.textContent = timeStr;
                navbarDate.textContent = dateStr;
            }
            
            // Update stats card time if exists
            const currentTime = document.getElementById('currentTime');
            const currentDate = document.getElementById('currentDate');
            if (currentTime && currentDate) {
                currentTime.textContent = timeStr;
                currentDate.textContent = dateStr;
            }
        }
        
        // Update time every second
        setInterval(updateAllTimeDisplays, 1000);
        updateAllTimeDisplays(); // Initial update
    </script>
</body>
</html>
