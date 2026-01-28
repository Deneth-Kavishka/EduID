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

// Get upcoming exams
$query = "SELECT * FROM exam_entries 
          WHERE student_id = :student_id AND exam_date >= CURDATE() 
          ORDER BY exam_date ASC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get past exams
$query = "SELECT * FROM exam_entries 
          WHERE student_id = :student_id AND exam_date < CURDATE() 
          ORDER BY exam_date DESC 
          LIMIT 20";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$past_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get exam statistics
$query = "SELECT 
          COUNT(*) as total_exams,
          SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_entries,
          SUM(CASE WHEN exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_count
          FROM exam_entries 
          WHERE student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$exam_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .exam-stats {
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
        
        .stat-icon.total { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-icon.upcoming { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-icon.verified { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .exam-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .exam-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .exam-card.upcoming {
            border-left: 4px solid #f59e0b;
        }
        
        .exam-card.today {
            border-left: 4px solid #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .exam-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .exam-date {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .exam-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .exam-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .exam-badge.verified {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .exam-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .exam-badge.today-badge {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .exam-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .exam-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .exam-detail i {
            color: var(--primary-color);
            width: 20px;
        }
        
        .countdown {
            text-align: center;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 1.5rem;
        }
        
        .countdown-title {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .countdown-exam {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .countdown-item {
            background: var(--bg-primary);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: center;
            min-width: 70px;
        }
        
        .countdown-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .countdown-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: var(--primary-color);
        }
        
        .tab.active {
            color: var(--primary-color);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
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
        
        .exam-instructions {
            background: rgba(59, 130, 246, 0.05);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .exam-instructions h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .exam-instructions ul {
            list-style: none;
            padding: 0;
        }
        
        .exam-instructions li {
            padding: 0.5rem 0;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .exam-instructions li i {
            color: var(--primary-color);
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
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Attendance</span>
                    </a>
                    <a href="exams.php" class="nav-item active">
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
                    <h1>My Exams</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
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
                <!-- Exam Stats -->
                <div class="exam-stats">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $exam_stats['total_exams'] ?? 0; ?></h3>
                            <p>Total Exams</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon upcoming">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $exam_stats['upcoming_count'] ?? 0; ?></h3>
                            <p>Upcoming Exams</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon verified">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $exam_stats['verified_entries'] ?? 0; ?></h3>
                            <p>Verified Entries</p>
                        </div>
                    </div>
                </div>
                
                <?php if (count($upcoming_exams) > 0): ?>
                    <!-- Next Exam Countdown -->
                    <div class="countdown">
                        <div class="countdown-title">Next Exam</div>
                        <div class="countdown-exam"><?php echo htmlspecialchars($upcoming_exams[0]['exam_name']); ?></div>
                        <div class="countdown-timer" id="countdown">
                            <div class="countdown-item">
                                <div class="countdown-value" id="days">--</div>
                                <div class="countdown-label">Days</div>
                            </div>
                            <div class="countdown-item">
                                <div class="countdown-value" id="hours">--</div>
                                <div class="countdown-label">Hours</div>
                            </div>
                            <div class="countdown-item">
                                <div class="countdown-value" id="minutes">--</div>
                                <div class="countdown-label">Minutes</div>
                            </div>
                            <div class="countdown-item">
                                <div class="countdown-value" id="seconds">--</div>
                                <div class="countdown-label">Seconds</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('upcoming')">
                        <i class="fas fa-calendar-alt"></i> Upcoming Exams
                    </button>
                    <button class="tab" onclick="switchTab('past')">
                        <i class="fas fa-history"></i> Past Exams
                    </button>
                </div>
                
                <!-- Upcoming Exams Tab -->
                <div id="upcomingTab" class="tab-content active">
                    <?php if (count($upcoming_exams) > 0): ?>
                        <?php foreach ($upcoming_exams as $exam): 
                            $is_today = date('Y-m-d') === $exam['exam_date'];
                        ?>
                            <div class="exam-card <?php echo $is_today ? 'today' : 'upcoming'; ?>">
                                <div class="exam-header">
                                    <div>
                                        <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                        <div class="exam-date">
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo date('l, F d, Y', strtotime($exam['exam_date'])); ?>
                                        </div>
                                    </div>
                                    <?php if ($is_today): ?>
                                        <span class="exam-badge today-badge">TODAY</span>
                                    <?php else: ?>
                                        <span class="exam-badge <?php echo $exam['verification_status']; ?>">
                                            <?php echo ucfirst($exam['verification_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="exam-details">
                                    <div class="exam-detail">
                                        <i class="fas fa-door-open"></i>
                                        <span>Hall: <?php echo htmlspecialchars($exam['exam_hall'] ?? 'TBA'); ?></span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-chair"></i>
                                        <span>Seat: <?php echo htmlspecialchars($exam['seat_number'] ?? 'TBA'); ?></span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-clock"></i>
                                        <span>
                                            <?php 
                                            $days_until = (strtotime($exam['exam_date']) - strtotime(date('Y-m-d'))) / 86400;
                                            if ($days_until == 0) echo 'Today';
                                            elseif ($days_until == 1) echo 'Tomorrow';
                                            else echo $days_until . ' days left';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-qrcode"></i>
                                        <span>Verification: <?php echo ucwords(str_replace('_', ' ', $exam['verification_method'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="exam-instructions">
                            <h4><i class="fas fa-info-circle"></i> Exam Entry Instructions</h4>
                            <ul>
                                <li><i class="fas fa-id-card"></i> Carry your student ID card</li>
                                <li><i class="fas fa-qrcode"></i> Have your QR code ready on your phone or printed</li>
                                <li><i class="fas fa-clock"></i> Arrive at least 30 minutes before the exam</li>
                                <li><i class="fas fa-face-smile"></i> Face verification will be done at the exam hall</li>
                                <li><i class="fas fa-mobile-alt"></i> Electronic devices must be switched off during exams</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <h3>No Upcoming Exams</h3>
                            <p>You don't have any upcoming exams scheduled</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Past Exams Tab -->
                <div id="pastTab" class="tab-content">
                    <?php if (count($past_exams) > 0): ?>
                        <?php foreach ($past_exams as $exam): ?>
                            <div class="exam-card">
                                <div class="exam-header">
                                    <div>
                                        <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                        <div class="exam-date">
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo date('l, F d, Y', strtotime($exam['exam_date'])); ?>
                                        </div>
                                    </div>
                                    <span class="exam-badge <?php echo $exam['verification_status']; ?>">
                                        <?php echo ucfirst($exam['verification_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="exam-details">
                                    <div class="exam-detail">
                                        <i class="fas fa-door-open"></i>
                                        <span>Hall: <?php echo htmlspecialchars($exam['exam_hall'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-chair"></i>
                                        <span>Seat: <?php echo htmlspecialchars($exam['seat_number'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-clock"></i>
                                        <span>Entry: <?php echo $exam['entry_time'] ? date('h:i A', strtotime($exam['entry_time'])) : 'N/A'; ?></span>
                                    </div>
                                    <div class="exam-detail">
                                        <i class="fas fa-qrcode"></i>
                                        <span>Verified: <?php echo ucwords(str_replace('_', ' ', $exam['verification_method'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Past Exams</h3>
                            <p>You haven't taken any exams yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'upcoming') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('upcomingTab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('pastTab').classList.add('active');
            }
        }
        
        // Countdown timer
        <?php if (count($upcoming_exams) > 0): ?>
        const examDate = new Date('<?php echo $upcoming_exams[0]['exam_date']; ?>T09:00:00').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = examDate - now;
            
            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('days').textContent = String(days).padStart(2, '0');
                document.getElementById('hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
            } else {
                document.getElementById('countdown').innerHTML = '<p style="color: var(--primary-color); font-weight: 600;">Exam time!</p>';
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
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
