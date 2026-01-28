<?php
require_once '../../config/config.php';
checkRole(['student']);

$db = new Database();
$conn = $db->getConnection();

$student_id = $_SESSION['student_id'];

// Get student details
$query = "SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get attendance statistics for current month
$query = "SELECT 
          COUNT(*) as total_days,
          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
          SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
          FROM attendance 
          WHERE student_id = :student_id 
          AND MONTH(date) = :month 
          AND YEAR(date) = :year";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':month', $month);
$stmt->bindParam(':year', $year);
$stmt->execute();
$monthly_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get overall attendance statistics
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
$overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate attendance percentage
$attendance_percentage = $overall_stats['total_days'] > 0 
    ? round(($overall_stats['present_days'] / $overall_stats['total_days']) * 100, 1) 
    : 0;

// Get attendance records for selected month
$query = "SELECT * FROM attendance 
          WHERE student_id = :student_id 
          AND MONTH(date) = :month 
          AND YEAR(date) = :year 
          ORDER BY date DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':month', $month);
$stmt->bindParam(':year', $year);
$stmt->execute();
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent attendance for quick view
$query = "SELECT * FROM attendance WHERE student_id = :student_id ORDER BY date DESC LIMIT 7";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.present { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-icon.absent { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-icon.late { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-icon.percentage { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        
        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-bar select {
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.95rem;
            min-width: 150px;
        }
        
        .filter-bar button {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-bar button:hover {
            background: var(--primary-dark);
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendance-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .attendance-table tr:hover {
            background: var(--bg-secondary);
        }
        
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-badge.present {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .status-badge.absent {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .status-badge.late {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .status-badge.excused {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.5s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .calendar-day.header {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .calendar-day.present { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .calendar-day.absent { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .calendar-day.late { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .calendar-day.excused { background: rgba(107, 114, 128, 0.2); color: #6b7280; }
        .calendar-day.empty { background: transparent; }
        .calendar-day.today { border: 2px solid var(--primary-color); }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
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
                    <a href="attendance.php" class="nav-item active">
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
                    <h1>My Attendance</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
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
                <!-- Attendance Stats -->
                <div class="attendance-stats">
                    <div class="stat-card">
                        <div class="stat-icon percentage">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $attendance_percentage; ?>%</h3>
                            <p>Overall Attendance</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon present">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $overall_stats['present_days'] ?? 0; ?></h3>
                            <p>Days Present</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon absent">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $overall_stats['absent_days'] ?? 0; ?></h3>
                            <p>Days Absent</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon late">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $overall_stats['late_days'] ?? 0; ?></h3>
                            <p>Days Late</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-2" style="gap: 1.5rem;">
                    <!-- Attendance Calendar View -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-alt"></i> Attendance Calendar</h3>
                        </div>
                        
                        <form method="GET" class="filter-bar">
                            <select name="month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="year">
                                <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                        </form>
                        
                        <div class="calendar-grid">
                            <div class="calendar-day header">Sun</div>
                            <div class="calendar-day header">Mon</div>
                            <div class="calendar-day header">Tue</div>
                            <div class="calendar-day header">Wed</div>
                            <div class="calendar-day header">Thu</div>
                            <div class="calendar-day header">Fri</div>
                            <div class="calendar-day header">Sat</div>
                            
                            <?php
                            $first_day = mktime(0, 0, 0, $month, 1, $year);
                            $days_in_month = date('t', $first_day);
                            $day_of_week = date('w', $first_day);
                            
                            // Create attendance lookup
                            $attendance_lookup = [];
                            foreach ($attendance_records as $record) {
                                $day = date('j', strtotime($record['date']));
                                $attendance_lookup[$day] = $record['status'];
                            }
                            
                            // Empty cells before first day
                            for ($i = 0; $i < $day_of_week; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }
                            
                            // Calendar days
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $status_class = isset($attendance_lookup[$day]) ? $attendance_lookup[$day] : '';
                                $is_today = ($day == date('j') && $month == date('m') && $year == date('Y')) ? 'today' : '';
                                echo "<div class=\"calendar-day {$status_class} {$is_today}\">{$day}</div>";
                            }
                            ?>
                        </div>
                        
                        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
                            <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                                <span style="width: 16px; height: 16px; background: rgba(16, 185, 129, 0.2); border-radius: 4px;"></span> Present
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                                <span style="width: 16px; height: 16px; background: rgba(239, 68, 68, 0.2); border-radius: 4px;"></span> Absent
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                                <span style="width: 16px; height: 16px; background: rgba(245, 158, 11, 0.2); border-radius: 4px;"></span> Late
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                                <span style="width: 16px; height: 16px; background: rgba(107, 114, 128, 0.2); border-radius: 4px;"></span> Excused
                            </span>
                        </div>
                    </div>
                    
                    <!-- Monthly Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?> Summary</h3>
                        </div>
                        
                        <div style="text-align: center; padding: 2rem 0;">
                            <?php 
                            $monthly_percentage = $monthly_stats['total_days'] > 0 
                                ? round(($monthly_stats['present_days'] / $monthly_stats['total_days']) * 100, 1) 
                                : 0;
                            $circumference = 2 * 3.14159 * 45;
                            $offset = $circumference - ($monthly_percentage / 100) * $circumference;
                            ?>
                            <svg class="progress-ring" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="45" fill="none" stroke="var(--border-color)" stroke-width="10"/>
                                <circle class="progress-ring-circle" cx="60" cy="60" r="45" fill="none" 
                                        stroke="<?php echo $monthly_percentage >= 75 ? '#10b981' : ($monthly_percentage >= 50 ? '#f59e0b' : '#ef4444'); ?>" 
                                        stroke-width="10" 
                                        stroke-dasharray="<?php echo $circumference; ?>" 
                                        stroke-dashoffset="<?php echo $offset; ?>"
                                        stroke-linecap="round"/>
                                <text x="60" y="65" text-anchor="middle" style="font-size: 1.5rem; font-weight: 700; fill: var(--text-primary);">
                                    <?php echo $monthly_percentage; ?>%
                                </text>
                            </svg>
                            <p style="color: var(--text-secondary);">Monthly Attendance Rate</p>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
                            <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                    <?php echo $monthly_stats['present_days'] ?? 0; ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">Present</div>
                            </div>
                            <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444;">
                                    <?php echo $monthly_stats['absent_days'] ?? 0; ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">Absent</div>
                            </div>
                            <div style="background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;">
                                    <?php echo $monthly_stats['late_days'] ?? 0; ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">Late</div>
                            </div>
                            <div style="background: rgba(107, 114, 128, 0.1); padding: 1rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #6b7280;">
                                    <?php echo $monthly_stats['excused_days'] ?? 0; ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">Excused</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Records Table -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Attendance Records - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h3>
                    </div>
                    
                    <?php if (count($attendance_records) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Verification</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                            <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $record['status']; ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
                                            <td><?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?></td>
                                            <td>
                                                <?php 
                                                $method_icons = [
                                                    'qr_code' => '<i class="fas fa-qrcode"></i> QR Code',
                                                    'face_recognition' => '<i class="fas fa-face-smile"></i> Face',
                                                    'manual' => '<i class="fas fa-user"></i> Manual'
                                                ];
                                                echo $method_icons[$record['verification_method']] ?? $record['verification_method'];
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-xmark"></i>
                            <h3>No Attendance Records</h3>
                            <p>No attendance records found for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                        </div>
                    <?php endif; ?>
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
            
            if (sidebar) {
                sidebar.addEventListener('scroll', function() {
                    sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
                });
            }
        });
    </script>
</body>
</html>
