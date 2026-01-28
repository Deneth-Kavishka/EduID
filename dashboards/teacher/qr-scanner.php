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

// Get today's verification count
$query = "SELECT COUNT(*) as count FROM attendance WHERE verified_by = :user_id AND date = CURDATE()";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent verifications
$query = "SELECT a.*, s.first_name, s.last_name, s.student_number, s.grade, s.class_section 
          FROM attendance a 
          JOIN students s ON a.student_id = s.student_id 
          WHERE a.verified_by = :user_id AND a.date = CURDATE()
          ORDER BY a.created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recent_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Teacher - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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
                    <a href="qr-scanner.php" class="nav-item active">
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
                    <a href="events.php" class="nav-item">
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
                    <h1>QR Code Scanner</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Verification</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>QR Scanner</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Row -->
                <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-qrcode" style="font-size: 1.5rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Scanned Today</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #10b981;" id="todayCount"><?php echo $today_count; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-history" style="font-size: 1.5rem; color: #3b82f6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Recent Scans</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #3b82f6;"><?php echo count($recent_verifications); ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-camera" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Scanner Status</p>
                                <h3 style="font-size: 1rem; font-weight: 700; color: #8b5cf6;">Ready</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Scanner Section -->
                <div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
                    <!-- Scanner Column -->
                    <div>
                        <!-- Scanner Card -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title"><i class="fas fa-camera"></i> Camera Scanner</h3>
                                <div style="display: flex; gap: 0.5rem;">
                                    <select id="cameraSelect" class="form-select" style="padding: 0.4rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-size: 0.85rem;">
                                        <option value="">Select Camera</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="scanner-container" style="position: relative;">
                                    <!-- Scanner Preview -->
                                    <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto; border-radius: 12px; overflow: hidden;"></div>
                                    
                                    <!-- Scanner Controls -->
                                    <div class="scanner-controls" style="display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                                        <button id="startBtn" onclick="startScanner()" class="btn" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; padding: 0.75rem 2rem; border-radius: 50px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-play"></i> Start Scanner
                                        </button>
                                        <button id="stopBtn" onclick="stopScanner()" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.75rem 2rem; border-radius: 50px; font-weight: 600; border: 1px solid var(--border-color); cursor: pointer; display: none; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-stop"></i> Stop Scanner
                                        </button>
                                    </div>
                                    
                                    <!-- Scanner Status -->
                                    <div id="scannerStatus" class="scanner-status" style="text-align: center; margin-top: 1rem; padding: 0.75rem; border-radius: 8px; background: var(--bg-secondary);">
                                        <i class="fas fa-info-circle" style="color: var(--text-secondary);"></i>
                                        <span style="color: var(--text-secondary); font-size: 0.9rem;"> Click "Start Scanner" to begin scanning QR codes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manual Entry Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-keyboard"></i> Manual Entry</h3>
                            </div>
                            <div class="card-body">
                                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                    Can't scan the QR code? Enter the student ID manually below.
                                </p>
                                <div style="display: flex; gap: 0.75rem;">
                                    <input type="text" id="manualStudentId" class="form-input" placeholder="Enter Student Number (e.g., STU20240001)" style="flex: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                                    <button onclick="verifyManualEntry()" class="btn" style="background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; white-space: nowrap;">
                                        <i class="fas fa-search"></i> Verify
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Verification Result Card -->
                        <div class="card" id="resultCard" style="margin-bottom: 1.5rem; display: none;">
                            <div class="card-header" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; border-radius: 12px 12px 0 0;">
                                <h3 class="card-title" style="color: white;"><i class="fas fa-user-check"></i> Verification Result</h3>
                            </div>
                            <div class="card-body" id="resultContent">
                                <!-- Result will be populated here -->
                            </div>
                        </div>
                        
                        <!-- Recent Scans Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Recent Scans Today</h3>
                            </div>
                            <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;" id="recentScansContainer">
                                <?php if (empty($recent_verifications)): ?>
                                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-qrcode" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p>No scans today yet</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($recent_verifications as $verification): ?>
                                <div class="scan-item" style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user-graduate" style="color: #10b981;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($verification['student_number']); ?> • Grade <?php echo htmlspecialchars($verification['grade'] . '-' . $verification['class_section']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                            <?php echo ucfirst($verification['status']); ?>
                                        </span>
                                        <div style="font-size: 0.7rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                                            <?php echo $verification['check_in_time']; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Success Sound -->
    <audio id="successSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdH2Onp2XkIWAgIeMkZOQi4eDgoaLjY2Lh4SFhomKiYeFhYaHiIiHhoaGhoaGhoaGhoaGhg==" type="audio/wav">
    </audio>
    
    <!-- Error Sound -->
    <audio id="errorSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdH2Onp2XkIWAgIeMkZOQi4eDgoaLjY2Lh4SFhomKiYeFhYaHiIiHhoaGhoaGhoaGhoaGhg==" type="audio/wav">
    </audio>
    
    <style>
        /* Scanner Styles */
        #qr-reader {
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            min-height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #qr-reader video {
            border-radius: 8px;
        }
        
        #qr-reader__scan_region {
            background: transparent !important;
        }
        
        #qr-reader__dashboard {
            display: none !important;
        }
        
        /* Scanner Active State */
        .scanner-active #qr-reader {
            border-color: #10b981;
            border-style: solid;
        }
        
        /* Result Card Animations */
        .result-success {
            animation: successPulse 0.5s ease;
        }
        
        .result-error {
            animation: errorShake 0.5s ease;
        }
        
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Student Info Display */
        .student-info-card {
            text-align: center;
            padding: 1rem;
        }
        
        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #10b981, #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .student-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .student-id {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            text-align: left;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .detail-item {
            padding: 0.5rem;
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-tertiary);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 0.9rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .verification-badge.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .verification-badge.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .verification-badge.already {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .content-area > div:nth-child(2) {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        
        // Update current time with seconds and date
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
        
        setInterval(updateAllTimeDisplays, 1000);
        updateAllTimeDisplays(); // Initial update
        
        // Initialize camera list
        async function initCameras() {
            try {
                const devices = await Html5Qrcode.getCameras();
                const select = document.getElementById('cameraSelect');
                devices.forEach((device, index) => {
                    const option = document.createElement('option');
                    option.value = device.id;
                    option.text = device.label || `Camera ${index + 1}`;
                    select.appendChild(option);
                });
                if (devices.length > 0) {
                    select.value = devices[0].id;
                }
            } catch (err) {
                console.error('Error getting cameras:', err);
            }
        }
        initCameras();
        
        // Start Scanner
        function startScanner() {
            const cameraId = document.getElementById('cameraSelect').value;
            
            if (!cameraId) {
                showStatus('Please select a camera first', 'warning');
                return;
            }
            
            html5QrcodeScanner = new Html5Qrcode("qr-reader");
            
            html5QrcodeScanner.start(
                cameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                onScanSuccess,
                onScanFailure
            ).then(() => {
                isScanning = true;
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('stopBtn').style.display = 'flex';
                document.querySelector('.scanner-container').classList.add('scanner-active');
                showStatus('Scanner is active. Point camera at QR code', 'info');
            }).catch((err) => {
                showStatus('Error starting scanner: ' + err, 'error');
            });
        }
        
        // Stop Scanner
        function stopScanner() {
            if (html5QrcodeScanner && isScanning) {
                html5QrcodeScanner.stop().then(() => {
                    isScanning = false;
                    document.getElementById('startBtn').style.display = 'flex';
                    document.getElementById('stopBtn').style.display = 'none';
                    document.querySelector('.scanner-container').classList.remove('scanner-active');
                    showStatus('Scanner stopped', 'info');
                });
            }
        }
        
        // On Scan Success
        function onScanSuccess(decodedText, decodedResult) {
            // Pause scanning temporarily
            if (html5QrcodeScanner && isScanning) {
                html5QrcodeScanner.pause();
            }
            
            // Try to parse QR data
            try {
                let qrData;
                try {
                    qrData = JSON.parse(decodedText);
                } catch {
                    // If not JSON, treat as student number
                    qrData = { student_number: decodedText };
                }
                
                verifyStudent(qrData);
            } catch (error) {
                showResult('error', 'Invalid QR Code', 'The scanned QR code is not valid for this system.');
                setTimeout(() => {
                    if (html5QrcodeScanner && isScanning) {
                        html5QrcodeScanner.resume();
                    }
                }, 2000);
            }
        }
        
        // On Scan Failure
        function onScanFailure(error) {
            // Ignore - continuous scanning
        }
        
        // Verify Student
        function verifyStudent(qrData) {
            showStatus('Verifying student...', 'info');
            
            fetch('includes/verify_student.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_number: qrData.student_number || qrData.student_id,
                    student_id: qrData.student_id,
                    verification_method: 'qr_code'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.already_marked) {
                        showResult('already', 'Already Verified', data.message, data.student);
                        playSound('error');
                    } else {
                        showResult('success', 'Verification Successful', data.message, data.student);
                        playSound('success');
                        updateTodayCount();
                        addToRecentScans(data.student);
                    }
                } else {
                    showResult('error', 'Verification Failed', data.message);
                    playSound('error');
                }
                
                // Resume scanning after delay
                setTimeout(() => {
                    if (html5QrcodeScanner && isScanning) {
                        html5QrcodeScanner.resume();
                    }
                }, 3000);
            })
            .catch(error => {
                showResult('error', 'Error', 'Failed to verify student. Please try again.');
                playSound('error');
                setTimeout(() => {
                    if (html5QrcodeScanner && isScanning) {
                        html5QrcodeScanner.resume();
                    }
                }, 2000);
            });
        }
        
        // Manual Entry Verification
        function verifyManualEntry() {
            const studentId = document.getElementById('manualStudentId').value.trim();
            if (!studentId) {
                showStatus('Please enter a student number', 'warning');
                return;
            }
            
            verifyStudent({ student_number: studentId });
        }
        
        // Show Result Card
        function showResult(type, title, message, student = null) {
            const card = document.getElementById('resultCard');
            const content = document.getElementById('resultContent');
            
            card.style.display = 'block';
            card.className = 'card result-' + type;
            
            // Update header color based on type
            const header = card.querySelector('.card-header');
            if (type === 'success') {
                header.style.background = 'linear-gradient(135deg, #10b981, #06b6d4)';
            } else if (type === 'error') {
                header.style.background = 'linear-gradient(135deg, #ef4444, #f97316)';
            } else if (type === 'already') {
                header.style.background = 'linear-gradient(135deg, #f59e0b, #eab308)';
            }
            
            if (student) {
                const initials = (student.first_name?.charAt(0) || '') + (student.last_name?.charAt(0) || '');
                content.innerHTML = `
                    <div class="student-info-card">
                        <div class="student-avatar">${initials}</div>
                        <div class="student-name">${student.first_name} ${student.last_name}</div>
                        <div class="student-id">${student.student_number}</div>
                        
                        <div class="verification-badge ${type}">
                            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'already' ? 'fa-exclamation-circle' : 'fa-times-circle'}"></i>
                            ${title}
                        </div>
                        
                        <div class="student-details">
                            <div class="detail-item">
                                <div class="detail-label">Grade</div>
                                <div class="detail-value">${student.grade || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Class</div>
                                <div class="detail-value">${student.class_section || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Time</div>
                                <div class="detail-value">${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Method</div>
                                <div class="detail-value">QR Code</div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 1.5rem;">
                        <i class="fas fa-times-circle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--text-primary); margin-bottom: 0.5rem;">${title}</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">${message}</p>
                    </div>
                `;
            }
            
            // Scroll result into view
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Show Status
        function showStatus(message, type) {
            const statusEl = document.getElementById('scannerStatus');
            const colors = {
                info: 'var(--text-secondary)',
                success: '#10b981',
                warning: '#f59e0b',
                error: '#ef4444'
            };
            const icons = {
                info: 'fa-info-circle',
                success: 'fa-check-circle',
                warning: 'fa-exclamation-circle',
                error: 'fa-times-circle'
            };
            
            statusEl.innerHTML = `
                <i class="fas ${icons[type]}" style="color: ${colors[type]};"></i>
                <span style="color: ${colors[type]}; font-size: 0.9rem;"> ${message}</span>
            `;
        }
        
        // Play Sound
        function playSound(type) {
            try {
                const audio = document.getElementById(type + 'Sound');
                if (audio) {
                    audio.currentTime = 0;
                    audio.play().catch(() => {});
                }
            } catch (e) {}
        }
        
        // Update Today Count
        function updateTodayCount() {
            const countEl = document.getElementById('todayCount');
            countEl.textContent = parseInt(countEl.textContent) + 1;
        }
        
        // Add to Recent Scans
        function addToRecentScans(student) {
            const container = document.getElementById('recentScansContainer');
            const emptyState = container.querySelector('div[style*="text-align: center"]');
            if (emptyState) {
                emptyState.remove();
            }
            
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            
            const newItem = document.createElement('div');
            newItem.className = 'scan-item';
            newItem.style = 'padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem; animation: fadeIn 0.3s ease;';
            newItem.innerHTML = `
                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-graduate" style="color: #10b981;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary);">
                        ${student.first_name} ${student.last_name}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                        ${student.student_number} • Grade ${student.grade}-${student.class_section}
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        Present
                    </span>
                    <div style="font-size: 0.7rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                        ${time}
                    </div>
                </div>
            `;
            
            container.insertBefore(newItem, container.firstChild);
        }
        
        // Handle Enter key for manual entry
        document.getElementById('manualStudentId').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                verifyManualEntry();
            }
        });
    </script>
</body>
<script>
// Preserve sidebar scroll position and ensure active item is visible
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar-nav');
    const activeItem = document.querySelector('.nav-item.active');
    
    if (sidebar && activeItem) {
        setTimeout(() => {
            activeItem.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 100);
    }
});
</script>
</html>
