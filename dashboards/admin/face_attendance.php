<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$selected_grade = $_GET['grade'] ?? '';
$selected_section = $_GET['section'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

// Get unique grades (with proper numeric ordering)
$query = "SELECT DISTINCT s.grade FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE u.status = 'active' 
          ORDER BY CAST(s.grade AS UNSIGNED), s.grade";
$stmt = $conn->prepare($query);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get sections for selected grade (only with active students)
$sections = [];
if ($selected_grade) {
    $query = "SELECT DISTINCT s.class_section FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.grade = :grade AND u.status = 'active' 
              ORDER BY s.class_section";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':grade', $selected_grade);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Check if selected date is a holiday
$is_holiday = false;
$holiday_info = null;
if ($selected_date) {
    $query = "SELECT * FROM institute_holidays WHERE holiday_date = :date";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $selected_date);
    $stmt->execute();
    $holiday_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_holiday = $holiday_info ? true : false;
}

// Get students with face data for selected class
$students_with_face = [];
if ($selected_grade) {
    $where = "s.grade = :grade AND u.status = 'active'";
    $params = [':grade' => $selected_grade];
    
    if ($selected_section) {
        $where .= " AND s.class_section = :section";
        $params[':section'] = $selected_section;
    }
    
    $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section, s.user_id,
              (SELECT COUNT(*) FROM face_recognition_data f WHERE f.user_id = s.user_id AND f.is_active = 1) as face_count,
              (SELECT a.status FROM attendance a WHERE a.student_id = s.student_id AND a.date = :selected_date) as today_status
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              WHERE {$where}
              ORDER BY s.student_number";
    $stmt = $conn->prepare($query);
    $params[':selected_date'] = $selected_date;
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students_with_face = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Attendance - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
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
                    <a href="face_attendance.php" class="nav-item active">
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
                    <h1>Face Recognition Attendance</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Attendance</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Face Recognition</span>
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
                <!-- Grade/Section Selection -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.5rem;">
                        <h3 style="color: var(--text-primary); margin-bottom: 1rem;"><i class="fas fa-filter"></i> Select Class for Attendance</h3>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Grade</label>
                                <select id="gradeFilter" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                    <option value="">Select Grade</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo $grade; ?>" <?php echo $selected_grade == $grade ? 'selected' : ''; ?>>Grade <?php echo $grade; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Section</label>
                                <select id="sectionFilter" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" <?php echo !$selected_grade ? 'disabled' : ''; ?>>
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $selected_section == $section ? 'selected' : ''; ?>><?php echo htmlspecialchars($section); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 600; font-size: 0.875rem;">Date</label>
                                <input type="date" id="dateFilter" value="<?php echo $selected_date; ?>" max="<?php echo $today; ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                            <div>
                                <button class="btn btn-primary" style="width: 100%; padding: 0.75rem;" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Load Class
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($selected_grade): ?>
                
                <?php if ($is_holiday): ?>
                <!-- Holiday Notice -->
                <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-calendar-times" style="font-size: 1.5rem; color: #ef4444;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #ef4444; font-size: 1rem;">Institute Closed</div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo htmlspecialchars($holiday_info['reason']); ?> 
                            <span style="opacity: 0.7;">(<?php echo ucfirst(str_replace('_', ' ', $holiday_info['holiday_type'])); ?>)</span>
                        </div>
                    </div>
                </div>
                <?php elseif ($selected_date != $today): ?>
                <!-- Past Date Notice -->
                <div style="background: linear-gradient(135deg, rgba(234, 179, 8, 0.1), rgba(234, 179, 8, 0.05)); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(234, 179, 8, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-history" style="font-size: 1.5rem; color: #eab308;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #eab308; font-size: 1rem;">Viewing Past Date</div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            You are viewing attendance for <?php echo date('F j, Y', strtotime($selected_date)); ?>. Face recognition will mark attendance for this date.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <!-- Camera Section -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-video"></i> Camera Feed</h3>
                            <div style="display: flex; gap: 0.5rem;">
                                <button id="startCameraBtn" class="btn btn-primary" onclick="startCamera()">
                                    <i class="fas fa-play"></i> Start Camera
                                </button>
                                <button id="stopCameraBtn" class="btn btn-secondary" onclick="stopCamera()" style="display: none;">
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <div id="cameraContainer" style="position: relative; width: 100%; aspect-ratio: 4/3; background: #1a1a2e; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                <video id="videoElement" autoplay muted style="width: 100%; height: 100%; object-fit: cover; display: none;"></video>
                                <canvas id="canvasOverlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none;"></canvas>
                                <div id="cameraPlaceholder" style="text-align: center; color: #666;">
                                    <i class="fas fa-camera" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                    <p>Click "Start Camera" to begin face recognition</p>
                                </div>
                            </div>
                            
                            <div id="loadingIndicator" style="display: none; text-align: center; padding: 1rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                                <p style="color: var(--text-secondary); margin-top: 0.5rem;">Loading face recognition models...</p>
                            </div>
                            
                            <div id="recognitionResult" style="margin-top: 1rem; display: none;">
                                <div style="padding: 1rem; border-radius: 8px; background: var(--bg-secondary);">
                                    <h4 style="color: var(--text-primary); margin-bottom: 0.5rem;"><i class="fas fa-user-check"></i> Last Recognition</h4>
                                    <div id="lastRecognized" style="display: flex; align-items: center; gap: 1rem;">
                                        <!-- Will be filled by JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student List -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Students - <?php echo date('M j, Y', strtotime($selected_date)); ?></h3>
                            <span style="font-size: 0.875rem; color: var(--text-secondary);">
                                <?php 
                                $marked = array_filter($students_with_face, fn($s) => $s['today_status'] !== null);
                                echo count($marked) . '/' . count($students_with_face) . ' marked';
                                ?>
                            </span>
                        </div>
                        <div class="card-body" style="padding: 0; max-height: 500px; overflow-y: auto;">
                            <table style="width: 100%;">
                                <tbody>
                                    <?php foreach ($students_with_face as $student): ?>
                                    <tr id="student-row-<?php echo $student['student_id']; ?>" style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $student['face_count'] > 0 ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $student['face_count'] > 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600;">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                                </div>
                                                <div style="flex: 1; overflow: hidden;">
                                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </div>
                                                    <div style="color: var(--text-secondary); font-size: 0.75rem;">
                                                        <?php echo htmlspecialchars($student['student_number']); ?>
                                                        <?php if ($student['face_count'] == 0): ?>
                                                            <span style="color: var(--warning-color);"><i class="fas fa-exclamation-triangle"></i> No face</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem; text-align: center; width: 80px;">
                                            <?php if ($student['today_status']): ?>
                                                <?php
                                                $statusColors = [
                                                    'present' => 'var(--success-color)',
                                                    'absent' => 'var(--danger-color)',
                                                    'late' => 'var(--warning-color)'
                                                ];
                                                $statusColor = $statusColors[$student['today_status']] ?? 'var(--text-secondary)';
                                                ?>
                                                <span style="color: <?php echo $statusColor; ?>; font-size: 0.75rem; font-weight: 600;">
                                                    <?php echo ucfirst($student['today_status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.75rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- No Grade Selected -->
                <div class="card">
                    <div class="card-body" style="padding: 4rem; text-align: center;">
                        <i class="fas fa-camera" style="font-size: 4rem; color: var(--primary-color); opacity: 0.3; margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Select a Grade to Start</h3>
                        <p style="color: var(--text-secondary);">Choose a grade and optionally a section to begin face recognition attendance.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        let videoStream = null;
        let faceApiLoaded = false;
        let isScanning = false;
        let knownFaces = [];
        
        // Student face data for comparison
        const studentData = <?php echo json_encode($students_with_face); ?>;
        
        // Apply filters
        function applyFilters() {
            const grade = document.getElementById('gradeFilter').value;
            const section = document.getElementById('sectionFilter').value;
            const date = document.getElementById('dateFilter').value;
            
            let url = 'face_attendance.php?';
            if (grade) url += `grade=${grade}`;
            if (section) url += `&section=${encodeURIComponent(section)}`;
            if (date) url += `&date=${date}`;
            
            window.location.href = url;
        }
        
        // Load sections when grade changes
        document.getElementById('gradeFilter').addEventListener('change', async function() {
            const grade = this.value;
            const sectionSelect = document.getElementById('sectionFilter');
            
            if (!grade) {
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                sectionSelect.disabled = true;
                return;
            }
            
            try {
                const response = await fetch(`attendance_handler.php?get_sections=1&grade=${grade}`);
                const sections = await response.json();
                
                sectionSelect.innerHTML = '<option value="">All Sections</option>';
                sections.forEach(section => {
                    sectionSelect.innerHTML += `<option value="${section}">${section}</option>`;
                });
                sectionSelect.disabled = false;
            } catch (error) {
                console.error('Error loading sections:', error);
            }
        });
        
        async function loadFaceApiModels() {
            const loading = document.getElementById('loadingIndicator');
            loading.style.display = 'block';
            
            try {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model';
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
                    faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL)
                ]);
                
                faceApiLoaded = true;
                loading.style.display = 'none';
                
                // Load known faces
                await loadKnownFaces();
                
                console.log('Face API models loaded');
            } catch (error) {
                console.error('Error loading face models:', error);
                loading.innerHTML = '<p style="color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Error loading face models</p>';
            }
        }
        
        async function loadKnownFaces() {
            // Fetch stored face descriptors for students in this class
            try {
                const response = await fetch(`face_attendance_handler.php?action=get_face_data&grade=<?php echo urlencode($selected_grade); ?>&section=<?php echo urlencode($selected_section); ?>`);
                const data = await response.json();
                
                if (data.success) {
                    knownFaces = data.faces.map(f => ({
                        student_id: f.student_id,
                        student_name: f.student_name,
                        student_number: f.student_number,
                        descriptor: new Float32Array(JSON.parse(f.face_descriptor))
                    }));
                    console.log(`Loaded ${knownFaces.length} known faces`);
                }
            } catch (error) {
                console.error('Error loading known faces:', error);
            }
        }
        
        async function startCamera() {
            try {
                // Load models first if not loaded
                if (!faceApiLoaded) {
                    await loadFaceApiModels();
                }
                
                const video = document.getElementById('videoElement');
                const placeholder = document.getElementById('cameraPlaceholder');
                const canvas = document.getElementById('canvasOverlay');
                
                videoStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 640, height: 480, facingMode: 'user' }
                });
                
                video.srcObject = videoStream;
                video.style.display = 'block';
                canvas.style.display = 'block';
                placeholder.style.display = 'none';
                
                document.getElementById('startCameraBtn').style.display = 'none';
                document.getElementById('stopCameraBtn').style.display = 'inline-flex';
                
                // Start face detection loop
                isScanning = true;
                detectFaces();
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                alert('Could not access camera. Please ensure you have granted camera permissions.');
            }
        }
        
        function stopCamera() {
            isScanning = false;
            
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            
            const video = document.getElementById('videoElement');
            const placeholder = document.getElementById('cameraPlaceholder');
            const canvas = document.getElementById('canvasOverlay');
            
            video.style.display = 'none';
            canvas.style.display = 'none';
            placeholder.style.display = 'block';
            
            document.getElementById('startCameraBtn').style.display = 'inline-flex';
            document.getElementById('stopCameraBtn').style.display = 'none';
        }
        
        async function detectFaces() {
            if (!isScanning) return;
            
            const video = document.getElementById('videoElement');
            const canvas = document.getElementById('canvasOverlay');
            const ctx = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            try {
                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptors();
                
                // Clear canvas
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Draw detections
                for (const detection of detections) {
                    const box = detection.detection.box;
                    
                    // Match against known faces
                    let match = null;
                    let minDistance = Infinity;
                    
                    for (const knownFace of knownFaces) {
                        const distance = faceapi.euclideanDistance(detection.descriptor, knownFace.descriptor);
                        if (distance < 0.6 && distance < minDistance) {
                            minDistance = distance;
                            match = knownFace;
                        }
                    }
                    
                    // Draw box
                    ctx.strokeStyle = match ? '#22c55e' : '#ef4444';
                    ctx.lineWidth = 3;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                    
                    // Draw label
                    if (match) {
                        ctx.fillStyle = '#22c55e';
                        ctx.font = '16px Inter, sans-serif';
                        ctx.fillText(match.student_name, box.x, box.y - 10);
                        
                        // Mark attendance
                        markFaceAttendance(match);
                    }
                }
            } catch (error) {
                console.error('Detection error:', error);
            }
            
            // Continue detection loop
            requestAnimationFrame(detectFaces);
        }
        
        let recentlyMarked = new Set();
        
        async function markFaceAttendance(student) {
            // Prevent duplicate marking within 5 seconds
            if (recentlyMarked.has(student.student_id)) return;
            
            recentlyMarked.add(student.student_id);
            setTimeout(() => recentlyMarked.delete(student.student_id), 5000);
            
            try {
                const response = await fetch('face_attendance_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mark_face_attendance',
                        student_id: student.student_id,
                        date: '<?php echo $selected_date; ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    const row = document.getElementById(`student-row-${student.student_id}`);
                    if (row) {
                        const statusCell = row.querySelector('td:last-child');
                        statusCell.innerHTML = '<span style="color: var(--success-color); font-size: 0.75rem; font-weight: 600;">Present</span>';
                    }
                    
                    // Show recognition result
                    showRecognitionResult(student, result.time);
                }
            } catch (error) {
                console.error('Error marking attendance:', error);
            }
        }
        
        function showRecognitionResult(student, time) {
            const resultDiv = document.getElementById('recognitionResult');
            const lastRecognized = document.getElementById('lastRecognized');
            
            resultDiv.style.display = 'block';
            lastRecognized.innerHTML = `
                <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center; color: var(--success-color); font-weight: 600; font-size: 1.25rem;">
                    ${student.student_name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div style="font-weight: 600; color: var(--text-primary);">${student.student_name}</div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">${student.student_number} - Marked Present at ${time}</div>
                </div>
                <i class="fas fa-check-circle" style="color: var(--success-color); font-size: 1.5rem; margin-left: auto;"></i>
            `;
        }
    </script>
</body>
</html>
