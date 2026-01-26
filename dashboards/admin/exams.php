<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');

// Get all exams
$query = "SELECT e.*, h.hall_name, h.capacity,
          (SELECT COUNT(*) FROM exam_seat_assignments esa WHERE esa.exam_id = e.exam_id) as assigned_students,
          (SELECT COUNT(*) FROM exam_attendance ea WHERE ea.exam_id = e.exam_id AND ea.status = 'present') as present_count
          FROM exams e 
          LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
          ORDER BY e.exam_date DESC, e.start_time";
$stmt = $conn->prepare($query);
$stmt->execute();
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique grades
$query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get exam halls
$query = "SELECT * FROM exam_halls WHERE status = 'active' ORDER BY hall_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$halls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [
    'total_exams' => count($exams),
    'upcoming' => count(array_filter($exams, fn($e) => $e['exam_date'] >= $today && $e['status'] === 'scheduled')),
    'completed' => count(array_filter($exams, fn($e) => $e['status'] === 'completed')),
    'today' => count(array_filter($exams, fn($e) => $e['exam_date'] === $today))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Management - EduID</title>
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
                    <a href="exams.php" class="nav-item active">
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
                    <h1>Exam Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Examinations</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Exams</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggleTop" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    
                    <div class="user-menu">
                        <img src="../../assets/images/default-avatar.png" alt="Admin" class="user-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><circle cx=%2212%22 cy=%228%22 r=%224%22 fill=%22%23cbd5e1%22/><path d=%22M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z%22 fill=%22%23cbd5e1%22/></svg>'">
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_exams']; ?></h3>
                            <p>Total Exams</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['upcoming']; ?></h3>
                            <p>Upcoming</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['completed']; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['today']; ?></h3>
                            <p>Today's Exams</p>
                        </div>
                    </div>
                </div>
                
                <!-- Exams List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clipboard-list"></i> All Exams</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="filterStatus" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Status</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button class="btn btn-primary" onclick="showAddExamModal()">
                                <i class="fas fa-plus"></i> Add Exam
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 10%;">
                                <col style="width: 20%;">
                                <col style="width: 12%;">
                                <col style="width: 12%;">
                                <col style="width: 14%;">
                                <col style="width: 10%;">
                                <col style="width: 10%;">
                                <col style="width: 12%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Exam Name</th>
                                    <th>Subject</th>
                                    <th>Grade</th>
                                    <th>Date/Time</th>
                                    <th>Hall</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($exams) > 0): ?>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr data-status="<?php echo $exam['status']; ?>">
                                            <td>
                                                <strong style="color: var(--primary-color); font-size: 0.875rem;"><?php echo htmlspecialchars($exam['exam_code']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="overflow: hidden;">
                                                    <div style="font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                                        <?php echo ucfirst($exam['exam_type']); ?> • <?php echo $exam['duration_minutes']; ?> min
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.875rem; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($exam['subject']); ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600;">Grade <?php echo $exam['grade']; ?></span>
                                                <?php if ($exam['class_section']): ?>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;"> (<?php echo $exam['class_section']; ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></div>
                                                    <div style="color: var(--text-secondary);"><?php echo date('h:i A', strtotime($exam['start_time'])); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($exam['hall_name']): ?>
                                                <span style="color: var(--text-primary); font-size: 0.875rem;"><?php echo htmlspecialchars($exam['hall_name']); ?></span>
                                                <?php else: ?>
                                                <span style="color: var(--warning-color); font-size: 0.875rem;">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'scheduled' => 'var(--info-color)',
                                                    'ongoing' => 'var(--warning-color)',
                                                    'completed' => 'var(--success-color)',
                                                    'cancelled' => 'var(--danger-color)',
                                                    'postponed' => 'var(--text-secondary)'
                                                ];
                                                $statusColor = $statusColors[$exam['status']] ?? 'var(--text-secondary)';
                                                ?>
                                                <span style="padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: <?php echo $statusColor; ?>22; color: <?php echo $statusColor; ?>;">
                                                    <?php echo ucfirst($exam['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.3rem; justify-content: center;">
                                                    <button class="btn btn-sm btn-view" onclick="viewExam(<?php echo $exam['exam_id']; ?>)" title="View">
                                                        <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-edit" onclick="editExam(<?php echo $exam['exam_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.4rem 0.6rem;" onclick="deleteExam(<?php echo $exam['exam_id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p>No exams found. Click "Add Exam" to create one.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Exam Modal -->
    <div id="examModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 id="examModalTitle"><i class="fas fa-clipboard-list"></i> Add Exam</h2>
                <span class="close" onclick="closeExamModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="examForm">
                    <input type="hidden" id="exam_id" name="exam_id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Exam Code *</label>
                            <input type="text" id="exam_code" name="exam_code" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="e.g., MID-2024-01">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Exam Name *</label>
                            <input type="text" id="exam_name" name="exam_name" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="Midterm Examination 2024">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Exam Type *</label>
                            <select id="exam_type" name="exam_type" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="midterm">Midterm</option>
                                <option value="final">Final</option>
                                <option value="quiz">Quiz</option>
                                <option value="practical">Practical</option>
                                <option value="assignment">Assignment</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Subject *</label>
                            <input type="text" id="subject" name="subject" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="Mathematics">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Exam Hall</label>
                            <select id="hall_id" name="hall_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">Select Hall</option>
                                <?php foreach ($halls as $hall): ?>
                                <option value="<?php echo $hall['hall_id']; ?>"><?php echo htmlspecialchars($hall['hall_name']); ?> (<?php echo $hall['capacity']; ?> seats)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Grade *</label>
                            <select id="grade" name="grade" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">Select Grade</option>
                                <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade; ?>">Grade <?php echo $grade; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Section (Optional)</label>
                            <select id="class_section" name="class_section" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Sections</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Exam Date *</label>
                            <input type="date" id="exam_date" name="exam_date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">End Time *</label>
                            <input type="time" id="end_time" name="end_time" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Duration (min)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" value="60" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Total Marks</label>
                            <input type="number" id="total_marks" name="total_marks" value="100" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Pass Marks</label>
                            <input type="number" id="passing_marks" name="passing_marks" value="40" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Min Attendance %</label>
                            <input type="number" id="min_attendance_percent" name="min_attendance_percent" value="75" step="0.01" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Instructions</label>
                        <textarea id="instructions" name="instructions" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); resize: vertical;" placeholder="Special instructions for the exam..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn btn-secondary" onclick="closeExamModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Exam
                        </button>
                    </div>
                </form>
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
            margin: 2% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            max-width: 600px;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close {
            color: var(--text-secondary);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 0.4rem 0.6rem;
        }
        .btn-edit {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.2);
            padding: 0.4rem 0.6rem;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function showAddExamModal() {
            document.getElementById('examModalTitle').innerHTML = '<i class="fas fa-clipboard-list"></i> Add Exam';
            document.getElementById('examForm').reset();
            document.getElementById('exam_id').value = '';
            document.getElementById('examModal').style.display = 'block';
        }
        
        function closeExamModal() {
            document.getElementById('examModal').style.display = 'none';
        }
        
        // Load sections when grade changes
        document.getElementById('grade').addEventListener('change', async function() {
            const grade = this.value;
            const sectionSelect = document.getElementById('class_section');
            
            if (!grade) {
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                return;
            }
            
            try {
                const response = await fetch(`attendance_handler.php?get_sections=1&grade=${grade}`);
                const sections = await response.json();
                
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                sections.forEach(section => {
                    sectionSelect.innerHTML += `<option value="${section}">${section}</option>`;
                });
            } catch (error) {
                console.error('Error loading sections:', error);
            }
        });
        
        async function editExam(examId) {
            try {
                const response = await fetch(`exam_handler.php?action=get_exam&id=${examId}`);
                const data = await response.json();
                
                if (data.success) {
                    const exam = data.exam;
                    document.getElementById('examModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Exam';
                    document.getElementById('exam_id').value = exam.exam_id;
                    document.getElementById('exam_code').value = exam.exam_code;
                    document.getElementById('exam_name').value = exam.exam_name;
                    document.getElementById('exam_type').value = exam.exam_type;
                    document.getElementById('subject').value = exam.subject;
                    document.getElementById('grade').value = exam.grade;
                    
                    // Load sections first
                    const gradeSelect = document.getElementById('grade');
                    gradeSelect.dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        document.getElementById('class_section').value = exam.class_section || '';
                    }, 300);
                    
                    document.getElementById('exam_date').value = exam.exam_date;
                    document.getElementById('start_time').value = exam.start_time;
                    document.getElementById('end_time').value = exam.end_time;
                    document.getElementById('duration_minutes').value = exam.duration_minutes;
                    document.getElementById('total_marks').value = exam.total_marks;
                    document.getElementById('passing_marks').value = exam.passing_marks;
                    document.getElementById('min_attendance_percent').value = exam.min_attendance_percent;
                    document.getElementById('hall_id').value = exam.hall_id || '';
                    document.getElementById('instructions').value = exam.instructions || '';
                    
                    document.getElementById('examModal').style.display = 'block';
                }
            } catch (error) {
                alert('Error loading exam data');
            }
        }
        
        async function deleteExam(examId) {
            if (!confirm('Are you sure you want to delete this exam?')) return;
            
            try {
                const response = await fetch('exam_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_exam', exam_id: examId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting exam');
            }
        }
        
        document.getElementById('examForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('exam_id').value ? 'update_exam' : 'add_exam',
                exam_id: document.getElementById('exam_id').value,
                exam_code: document.getElementById('exam_code').value,
                exam_name: document.getElementById('exam_name').value,
                exam_type: document.getElementById('exam_type').value,
                subject: document.getElementById('subject').value,
                grade: document.getElementById('grade').value,
                class_section: document.getElementById('class_section').value,
                exam_date: document.getElementById('exam_date').value,
                start_time: document.getElementById('start_time').value,
                end_time: document.getElementById('end_time').value,
                duration_minutes: document.getElementById('duration_minutes').value,
                total_marks: document.getElementById('total_marks').value,
                passing_marks: document.getElementById('passing_marks').value,
                min_attendance_percent: document.getElementById('min_attendance_percent').value,
                hall_id: document.getElementById('hall_id').value,
                instructions: document.getElementById('instructions').value
            };
            
            try {
                const response = await fetch('exam_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving exam');
            }
        });
        
        // Filter by status
        document.getElementById('filterStatus').addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (!status || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
