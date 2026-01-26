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
    
    // Create Event
    if ($action === 'create_event') {
        try {
            $event_name = trim($_POST['event_name'] ?? '');
            $event_type = trim($_POST['event_type'] ?? '');
            $event_date = trim($_POST['event_date'] ?? '');
            $start_time = trim($_POST['start_time'] ?? '') ?: null;
            $end_time = trim($_POST['end_time'] ?? '') ?: null;
            $venue = trim($_POST['venue'] ?? '');
            $max_participants = intval($_POST['max_participants'] ?? 0) ?: null;
            $description = trim($_POST['description'] ?? '');
            
            // Validate
            if (empty($event_name) || empty($event_date)) {
                throw new Exception('Event name and date are required');
            }
            
            // Insert event
            $query = "INSERT INTO events (event_name, event_type, event_date, start_time, end_time, venue, max_participants, description, created_by) 
                      VALUES (:name, :type, :date, :start, :end, :venue, :max, :desc, :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $event_name);
            $stmt->bindParam(':type', $event_type);
            $stmt->bindParam(':date', $event_date);
            $stmt->bindParam(':start', $start_time);
            $stmt->bindParam(':end', $end_time);
            $stmt->bindParam(':venue', $venue);
            $stmt->bindParam(':max', $max_participants);
            $stmt->bindParam(':desc', $description);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $success = 'Event "' . htmlspecialchars($event_name) . '" created successfully!';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Update Event
    if ($action === 'update_event') {
        try {
            $event_id = intval($_POST['event_id'] ?? 0);
            $event_name = trim($_POST['event_name'] ?? '');
            $event_type = trim($_POST['event_type'] ?? '');
            $event_date = trim($_POST['event_date'] ?? '');
            $start_time = trim($_POST['start_time'] ?? '') ?: null;
            $end_time = trim($_POST['end_time'] ?? '') ?: null;
            $venue = trim($_POST['venue'] ?? '');
            $max_participants = intval($_POST['max_participants'] ?? 0) ?: null;
            $description = trim($_POST['description'] ?? '');
            $status = trim($_POST['status'] ?? 'upcoming');
            
            if (empty($event_name) || empty($event_date) || !$event_id) {
                throw new Exception('Event name and date are required');
            }
            
            $query = "UPDATE events SET event_name = :name, event_type = :type, event_date = :date, 
                      start_time = :start, end_time = :end, venue = :venue, max_participants = :max, 
                      description = :desc, status = :status WHERE event_id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $event_name);
            $stmt->bindParam(':type', $event_type);
            $stmt->bindParam(':date', $event_date);
            $stmt->bindParam(':start', $start_time);
            $stmt->bindParam(':end', $end_time);
            $stmt->bindParam(':venue', $venue);
            $stmt->bindParam(':max', $max_participants);
            $stmt->bindParam(':desc', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $event_id);
            $stmt->execute();
            
            $success = 'Event updated successfully!';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Delete Event
    if ($action === 'delete_event') {
        try {
            $event_id = intval($_POST['event_id'] ?? 0);
            
            if (!$event_id) {
                throw new Exception('Invalid event ID');
            }
            
            $query = "DELETE FROM events WHERE event_id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $event_id);
            $stmt->execute();
            
            $success = 'Event deleted successfully!';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Redirect to prevent form resubmission if came from dashboard
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false) {
        header('Location: index.php?success=' . urlencode($success ?: $error));
        exit;
    }
}

// Filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if ($filter_type) {
    $where_conditions[] = "event_type = :type";
    $params[':type'] = $filter_type;
}

if ($filter_status) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_month) {
    $where_conditions[] = "DATE_FORMAT(event_date, '%Y-%m') = :month";
    $params[':month'] = $filter_month;
}

if ($search) {
    $where_conditions[] = "(event_name LIKE :search OR venue LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get events
$query = "SELECT e.*, u.username as created_by_name,
          (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registration_count
          FROM events e 
          LEFT JOIN users u ON e.created_by = u.user_id 
          WHERE $where_clause
          ORDER BY e.event_date DESC, e.start_time ASC";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM events";
$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - EduID Admin</title>
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
                        <i class="fas fa-users"></i>
                        <span>Parents</span>
                    </a>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Attendance</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Events & Exams</div>
                    <a href="events.php" class="nav-item active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Events</span>
                    </a>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Examinations</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Reports & Settings</div>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="logs.php" class="nav-item">
                        <i class="fas fa-list-alt"></i>
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
                    <h1>Events Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Events & Exams</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Events</span>
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
                
                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Total Events</div>
                                <div style="font-size: 1.75rem; font-weight: 700; color: #3b82f6;"><?php echo number_format($stats['total']); ?></div>
                            </div>
                            <div style="width: 45px; height: 45px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calendar-alt" style="color: #3b82f6; font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Upcoming</div>
                                <div style="font-size: 1.75rem; font-weight: 700; color: #22c55e;"><?php echo number_format($stats['upcoming']); ?></div>
                            </div>
                            <div style="width: 45px; height: 45px; border-radius: 12px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock" style="color: #22c55e; font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Ongoing</div>
                                <div style="font-size: 1.75rem; font-weight: 700; color: #f59e0b;"><?php echo number_format($stats['ongoing']); ?></div>
                            </div>
                            <div style="width: 45px; height: 45px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-play-circle" style="color: #f59e0b; font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Completed</div>
                                <div style="font-size: 1.75rem; font-weight: 700; color: #8b5cf6;"><?php echo number_format($stats['completed']); ?></div>
                            </div>
                            <div style="width: 45px; height: 45px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-check-circle" style="color: #8b5cf6; font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters & Actions -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            <div style="flex: 1; min-width: 150px;">
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Event name, venue..." style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.85rem;">
                            </div>
                            <div style="min-width: 130px;">
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">Type</label>
                                <select name="type" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.85rem;">
                                    <option value="">All Types</option>
                                    <option value="academic" <?php echo $filter_type === 'academic' ? 'selected' : ''; ?>>Academic</option>
                                    <option value="sports" <?php echo $filter_type === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                    <option value="cultural" <?php echo $filter_type === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                    <option value="exam" <?php echo $filter_type === 'exam' ? 'selected' : ''; ?>>Examination</option>
                                    <option value="meeting" <?php echo $filter_type === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                    <option value="holiday" <?php echo $filter_type === 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                                    <option value="other" <?php echo $filter_type === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div style="min-width: 130px;">
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">Status</label>
                                <select name="status" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.85rem;">
                                    <option value="">All Status</option>
                                    <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div style="min-width: 140px;">
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">Month</label>
                                <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.85rem;">
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="events.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    Reset
                                </a>
                                <button type="button" class="btn btn-primary" onclick="openCreateEventModal()" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    <i class="fas fa-plus"></i> Add Event
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Events Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                    <?php if (empty($events)): ?>
                    <div class="card" style="grid-column: 1 / -1; padding: 3rem; text-align: center;">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--text-tertiary); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: 0.5rem;">No Events Found</h3>
                        <p style="color: var(--text-tertiary); font-size: 0.9rem;">Try adjusting your filters or create a new event.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($events as $event): ?>
                    <?php
                    $type_colors = [
                        'academic' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#3b82f6'],
                        'sports' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'color' => '#22c55e'],
                        'cultural' => ['bg' => 'rgba(236, 72, 153, 0.1)', 'color' => '#ec4899'],
                        'exam' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444'],
                        'meeting' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'color' => '#6366f1'],
                        'holiday' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                        'other' => ['bg' => 'rgba(107, 114, 128, 0.1)', 'color' => '#6b7280']
                    ];
                    $type_style = $type_colors[$event['event_type']] ?? $type_colors['other'];
                    
                    $status_colors = [
                        'upcoming' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'color' => '#22c55e'],
                        'ongoing' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#f59e0b'],
                        'completed' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'color' => '#8b5cf6'],
                        'cancelled' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#ef4444']
                    ];
                    $status_style = $status_colors[$event['status']] ?? $status_colors['upcoming'];
                    ?>
                    <div class="card" style="overflow: hidden;">
                        <div style="padding: 1.25rem;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
                                <div>
                                    <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: <?php echo $type_style['bg']; ?>; color: <?php echo $type_style['color']; ?>; text-transform: capitalize; margin-bottom: 0.5rem;">
                                        <?php echo $event['event_type'] ?: 'Event'; ?>
                                    </span>
                                    <h3 style="font-size: 1.05rem; color: var(--text-primary); margin: 0;"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                                </div>
                                <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; background: <?php echo $status_style['bg']; ?>; color: <?php echo $status_style['color']; ?>; text-transform: capitalize;">
                                    <?php echo $event['status']; ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-calendar" style="width: 16px; color: var(--primary-color);"></i>
                                    <?php echo date('D, M j, Y', strtotime($event['event_date'])); ?>
                                </div>
                                <?php if ($event['start_time']): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-clock" style="width: 16px; color: var(--primary-color);"></i>
                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?>
                                    <?php if ($event['end_time']): ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?><?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['venue']): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-map-marker-alt" style="width: 16px; color: var(--primary-color);"></i>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['max_participants']): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-users" style="width: 16px; color: var(--primary-color);"></i>
                                    <?php echo $event['registration_count']; ?> / <?php echo $event['max_participants']; ?> registered
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['description']): ?>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                                <button onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" class="btn btn-secondary" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteEvent(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES); ?>')" class="btn" style="flex: 1; padding: 0.4rem; font-size: 0.8rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Create Event Modal -->
    <div id="createEventModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus" style="color: var(--warning-color);"></i> Create New Event</h3>
                <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_event">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label>Event Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="event_name" required placeholder="Enter event name">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Event Type</label>
                            <select name="event_type">
                                <option value="">Select Type</option>
                                <option value="academic">Academic</option>
                                <option value="sports">Sports</option>
                                <option value="cultural">Cultural</option>
                                <option value="exam">Examination</option>
                                <option value="meeting">Meeting</option>
                                <option value="holiday">Holiday</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Event Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" name="venue" placeholder="e.g., Main Auditorium">
                        </div>
                        <div class="form-group">
                            <label>Max Participants</label>
                            <input type="number" name="max_participants" min="1" placeholder="e.g., 100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Enter event description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Create Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Event Modal -->
    <div id="editEventModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary-color);"></i> Edit Event</h3>
                <button class="modal-close" onclick="closeModal('editEventModal')">&times;</button>
            </div>
            <form method="POST" id="editEventForm">
                <input type="hidden" name="action" value="update_event">
                <input type="hidden" name="event_id" id="edit_event_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label>Event Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="event_name" id="edit_event_name" required placeholder="Enter event name">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Event Type</label>
                            <select name="event_type" id="edit_event_type">
                                <option value="">Select Type</option>
                                <option value="academic">Academic</option>
                                <option value="sports">Sports</option>
                                <option value="cultural">Cultural</option>
                                <option value="exam">Examination</option>
                                <option value="meeting">Meeting</option>
                                <option value="holiday">Holiday</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Event Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="event_date" id="edit_event_date" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" id="edit_start_time">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" id="edit_end_time">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" name="venue" id="edit_venue" placeholder="e.g., Main Auditorium">
                        </div>
                        <div class="form-group">
                            <label>Max Participants</label>
                            <input type="number" name="max_participants" id="edit_max_participants" min="1" placeholder="e.g., 100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3" placeholder="Enter event description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="event_id" id="delete_event_id">
                <div class="modal-body" style="padding: 1.5rem; text-align: center;">
                    <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">Are you sure you want to delete this event?</p>
                    <p style="font-weight: 600; color: var(--text-primary);" id="delete_event_name_display"></p>
                    <p style="color: #ef4444; font-size: 0.85rem; margin-top: 1rem;">This action cannot be undone.</p>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn" style="background: #ef4444; color: white;"><i class="fas fa-trash"></i> Delete Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-container {
            background: var(--bg-primary);
            border-radius: 16px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .modal-close:hover { color: var(--text-primary); }
        
        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea { resize: vertical; }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function openCreateEventModal() {
            document.getElementById('createEventModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function editEvent(event) {
            document.getElementById('edit_event_id').value = event.event_id;
            document.getElementById('edit_event_name').value = event.event_name;
            document.getElementById('edit_event_type').value = event.event_type || '';
            document.getElementById('edit_event_date').value = event.event_date;
            document.getElementById('edit_start_time').value = event.start_time || '';
            document.getElementById('edit_end_time').value = event.end_time || '';
            document.getElementById('edit_venue').value = event.venue || '';
            document.getElementById('edit_max_participants').value = event.max_participants || '';
            document.getElementById('edit_status').value = event.status;
            document.getElementById('edit_description').value = event.description || '';
            
            document.getElementById('editEventModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function deleteEvent(eventId, eventName) {
            document.getElementById('delete_event_id').value = eventId;
            document.getElementById('delete_event_name_display').textContent = eventName;
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
