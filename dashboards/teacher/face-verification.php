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

// Get today's face verification count
$query = "SELECT COUNT(*) as count FROM attendance WHERE verified_by = :user_id AND date = CURDATE() AND verification_method = 'face_recognition'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total students with face data registered
$query = "SELECT COUNT(DISTINCT user_id) as count FROM face_recognition_data WHERE is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$registered_faces = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent face verifications
$query = "SELECT a.*, s.first_name, s.last_name, s.student_number, s.grade, s.class_section 
          FROM attendance a 
          JOIN students s ON a.student_id = s.student_id 
          WHERE a.verified_by = :user_id AND a.date = CURDATE() AND a.verification_method = 'face_recognition'
          ORDER BY a.created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$recent_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active face descriptors for comparison
$query = "SELECT frd.face_id, frd.user_id, frd.face_descriptor, s.student_id, s.first_name, s.last_name, s.student_number, s.grade, s.class_section
          FROM face_recognition_data frd
          JOIN students s ON frd.user_id = s.user_id
          WHERE frd.is_active = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$all_face_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Verification - Teacher - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Face-API.js -->
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
                    <a href="face-verification.php" class="nav-item active">
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
                    <h1>Face Verification</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Verification</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Face Verification</span>
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
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); border: 1px solid rgba(139, 92, 246, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-face-smile" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Verified Today</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #8b5cf6;" id="todayCount"><?php echo $today_count; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1.25rem; border-radius: 12px;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-check" style="font-size: 1.5rem; color: #10b981;"></i>
                            </div>
                            <div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Registered Faces</p>
                                <h3 style="font-size: 1.75rem; font-weight: 700; color: #10b981;"><?php echo $registered_faces; ?></h3>
                            </div>
                        </div>
                    </div>
                    

                </div>
                
                <!-- Main Face Verification Section -->
                <div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
                    <!-- Camera Column -->
                    <div>
                        <!-- Camera Card -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title"><i class="fas fa-camera"></i> Face Detection Camera</h3>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                        <input type="checkbox" id="autoVerify" checked style="width: 16px; height: 16px;">
                                        Auto-verify
                                    </label>
                                    <select id="cameraSelect" class="form-select" style="padding: 0.4rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-size: 0.85rem;">
                                        <option value="">Select Camera</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="camera-container" style="position: relative; max-width: 640px; margin: 0 auto;">
                                    <!-- Video Element -->
                                    <div id="videoContainer" style="position: relative; width: 100%; aspect-ratio: 4/3; background: var(--bg-secondary); border-radius: 12px; overflow: hidden; border: 2px dashed var(--border-color);">
                                        <video id="video" autoplay muted playsinline style="width: 100%; height: 100%; object-fit: cover; display: none;"></video>
                                        <canvas id="overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></canvas>
                                        
                                        <!-- Placeholder -->
                                        <div id="placeholder" style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-tertiary);">
                                            <i class="fas fa-camera" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p style="font-size: 1rem;">Click "Start Camera" to begin face verification</p>
                                        </div>
                                        
                                        <!-- Face Circle Guide -->
                                        <div id="faceGuide" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 250px; border: 3px dashed rgba(139, 92, 246, 0.5); border-radius: 50%; pointer-events: none;"></div>
                                    </div>
                                    
                                    <!-- Camera Controls -->
                                    <div class="camera-controls" style="display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                                        <button id="startBtn" onclick="startFaceCamera()" class="btn" style="background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white; padding: 0.75rem 2rem; border-radius: 50px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-play"></i> Start Camera
                                        </button>
                                        <button id="stopBtn" onclick="stopFaceCamera()" class="btn" style="background: var(--bg-secondary); color: var(--text-primary); padding: 0.75rem 2rem; border-radius: 50px; font-weight: 600; border: 1px solid var(--border-color); cursor: pointer; display: none; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-stop"></i> Stop Camera
                                        </button>
                                        <button id="verifyBtn" onclick="verifyCurrentFace()" class="btn" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; padding: 0.75rem 2rem; border-radius: 50px; font-weight: 600; border: none; cursor: pointer; display: none; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-check"></i> Verify Face
                                        </button>
                                    </div>
                                    
                                    <!-- Status Message -->
                                    <div id="statusMessage" style="text-align: center; margin-top: 1rem; padding: 0.75rem; border-radius: 8px; background: var(--bg-secondary);">
                                        <i class="fas fa-info-circle" style="color: var(--text-secondary);"></i>
                                        <span style="color: var(--text-secondary); font-size: 0.9rem;"> Click "Start Camera" to begin face verification</span>
                                    </div>
                                    
                                    <!-- Model Loading Progress -->
                                    <div id="modelProgress" style="display: none; margin-top: 1rem;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Loading face detection models...</span>
                                            <span id="modelPercent" style="font-size: 0.85rem; color: #8b5cf6;">0%</span>
                                        </div>
                                        <div style="height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden;">
                                            <div id="modelProgressBar" style="width: 0%; height: 100%; background: linear-gradient(135deg, #8b5cf6, #a855f7); transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tips Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-lightbulb"></i> Verification Tips</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                    <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; background: rgba(139, 92, 246, 0.05); border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.1);">
                                        <i class="fas fa-sun" style="color: #8b5cf6; margin-top: 2px;"></i>
                                        <div>
                                            <strong style="font-size: 0.85rem;">Good Lighting</strong>
                                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Ensure the student's face is well-lit, preferably with natural light.</p>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; background: rgba(16, 185, 129, 0.05); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.1);">
                                        <i class="fas fa-face-smile" style="color: #10b981; margin-top: 2px;"></i>
                                        <div>
                                            <strong style="font-size: 0.85rem;">Face Forward</strong>
                                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Student should look directly at the camera.</p>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.1);">
                                        <i class="fas fa-ruler" style="color: #3b82f6; margin-top: 2px;"></i>
                                        <div>
                                            <strong style="font-size: 0.85rem;">Proper Distance</strong>
                                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Keep 1-2 feet distance from the camera.</p>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; background: rgba(245, 158, 11, 0.05); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.1);">
                                        <i class="fas fa-glasses" style="color: #f59e0b; margin-top: 2px;"></i>
                                        <div>
                                            <strong style="font-size: 0.85rem;">Remove Accessories</strong>
                                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Sunglasses or hats may affect recognition.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Verification Result Card -->
                        <div class="card" id="resultCard" style="margin-bottom: 1.5rem; display: none;">
                            <div class="card-header" id="resultHeader" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; border-radius: 12px 12px 0 0;">
                                <h3 class="card-title" style="color: white;"><i class="fas fa-user-check"></i> Verification Result</h3>
                            </div>
                            <div class="card-body" id="resultContent">
                                <!-- Result will be populated here -->
                            </div>
                        </div>
                        
                        <!-- Recent Verifications Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Recent Face Verifications</h3>
                            </div>
                            <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;" id="recentVerificationsContainer">
                                <?php if (empty($recent_verifications)): ?>
                                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-face-smile" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p>No face verifications today yet</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($recent_verifications as $verification): ?>
                                <div class="verification-item" style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-face-smile" style="color: #8b5cf6;"></i>
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
                                        <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
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
        /* Face Verification Styles */
        #videoContainer {
            transition: border-color 0.3s ease;
        }
        
        #videoContainer.camera-active {
            border-color: #8b5cf6;
            border-style: solid;
        }
        
        #videoContainer.face-detected {
            border-color: #10b981;
            border-style: solid;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }
        
        #videoContainer.no-face {
            border-color: #f59e0b;
            border-style: solid;
        }
        
        /* Face detection box styling */
        .face-box {
            position: absolute;
            border: 2px solid #10b981;
            border-radius: 8px;
            pointer-events: none;
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
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            overflow: hidden;
        }
        
        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 0.25rem;
        }
        
        /* Confidence Meter */
        .confidence-meter {
            margin-top: 1rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        .confidence-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        
        .confidence-bar {
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .confidence-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .confidence-high { background: linear-gradient(135deg, #10b981, #06b6d4); }
        .confidence-medium { background: linear-gradient(135deg, #f59e0b, #eab308); }
        .confidence-low { background: linear-gradient(135deg, #ef4444, #f87171); }
        
        /* Scanning Animation */
        @keyframes scanning {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .scanning-indicator {
            animation: scanning 1s infinite;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .content-area > div {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script>
        // Face verification state
        let video = null;
        let overlay = null;
        let isRunning = false;
        let modelsLoaded = false;
        let currentFaceDescriptor = null;
        let detectionInterval = null;
        let verificationInProgress = false;
        let lastVerifiedStudentId = null;
        let verificationCooldown = false;
        
        // Pre-loaded face data from database
        const registeredFaces = <?php echo json_encode(array_map(function($face) {
            return [
                'student_id' => $face['student_id'],
                'user_id' => $face['user_id'],
                'first_name' => $face['first_name'],
                'last_name' => $face['last_name'],
                'student_number' => $face['student_number'],
                'grade' => $face['grade'],
                'class_section' => $face['class_section'],
                'descriptor' => json_decode($face['face_descriptor'])
            ];
        }, $all_face_data)); ?>;
        
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
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', async function() {
            video = document.getElementById('video');
            overlay = document.getElementById('overlay');
            
            // Populate camera list
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                const cameraSelect = document.getElementById('cameraSelect');
                
                videoDevices.forEach((device, index) => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    option.text = device.label || `Camera ${index + 1}`;
                    cameraSelect.appendChild(option);
                });
                
                if (videoDevices.length > 0) {
                    cameraSelect.value = videoDevices[0].deviceId;
                }
            } catch (e) {
                console.log('Could not enumerate devices:', e);
            }
        });
        
        // Load Face-API models
        async function loadModels() {
            if (modelsLoaded) return true;
            
            document.getElementById('modelProgress').style.display = 'block';
            showStatus('Loading face detection models...', 'info');
            
            try {
                const MODEL_URL = '../../assets/models';
                
                // Load models with progress simulation
                const models = [
                    { name: 'tinyFaceDetector', loader: () => faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL) },
                    { name: 'faceLandmark68Net', loader: () => faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL) },
                    { name: 'faceRecognitionNet', loader: () => faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL) }
                ];
                
                for (let i = 0; i < models.length; i++) {
                    await models[i].loader();
                    const percent = Math.round(((i + 1) / models.length) * 100);
                    document.getElementById('modelPercent').textContent = percent + '%';
                    document.getElementById('modelProgressBar').style.width = percent + '%';
                }
                
                modelsLoaded = true;
                document.getElementById('modelProgress').style.display = 'none';
                showStatus('Face detection models loaded successfully!', 'success');
                return true;
            } catch (error) {
                console.error('Error loading models:', error);
                document.getElementById('modelProgress').style.display = 'none';
                showStatus('Error loading face detection models. Please refresh the page.', 'error');
                return false;
            }
        }
        
        // Start face camera
        async function startFaceCamera() {
            try {
                // Load models first
                if (!await loadModels()) return;
                
                showStatus('Starting camera...', 'info');
                
                const cameraSelect = document.getElementById('cameraSelect');
                const constraints = {
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    },
                    audio: false
                };
                
                if (cameraSelect.value) {
                    constraints.video.deviceId = { exact: cameraSelect.value };
                }
                
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                video.style.display = 'block';
                
                // Hide placeholder, show face guide
                document.getElementById('placeholder').style.display = 'none';
                document.getElementById('faceGuide').style.display = 'block';
                document.getElementById('videoContainer').classList.add('camera-active');
                
                // Update buttons
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('stopBtn').style.display = 'flex';
                document.getElementById('verifyBtn').style.display = 'flex';
                
                isRunning = true;
                showStatus('Camera started. Position student face in the frame.', 'info');
                
                // Start face detection loop
                startFaceDetectionLoop();
                
            } catch (error) {
                console.error('Error starting camera:', error);
                showStatus('Unable to access camera. Please grant camera permissions.', 'error');
            }
        }
        
        // Stop face camera
        function stopFaceCamera() {
            isRunning = false;
            
            if (video && video.srcObject) {
                const tracks = video.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                video.srcObject = null;
            }
            
            video.style.display = 'none';
            document.getElementById('placeholder').style.display = 'flex';
            document.getElementById('faceGuide').style.display = 'none';
            document.getElementById('videoContainer').classList.remove('camera-active', 'face-detected', 'no-face');
            
            // Clear overlay
            const ctx = overlay.getContext('2d');
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            
            // Update buttons
            document.getElementById('startBtn').style.display = 'flex';
            document.getElementById('stopBtn').style.display = 'none';
            document.getElementById('verifyBtn').style.display = 'none';
            
            currentFaceDescriptor = null;
            showStatus('Camera stopped.', 'info');
        }
        
        // Face detection loop
        async function startFaceDetectionLoop() {
            const videoContainer = document.getElementById('videoContainer');
            
            const detectFaces = async () => {
                if (!isRunning) return;
                
                try {
                    // Set canvas dimensions
                    overlay.width = video.videoWidth;
                    overlay.height = video.videoHeight;
                    
                    const ctx = overlay.getContext('2d');
                    ctx.clearRect(0, 0, overlay.width, overlay.height);
                    
                    // Detect face with descriptor
                    const detection = await faceapi
                        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor();
                    
                    if (detection) {
                        videoContainer.classList.add('face-detected');
                        videoContainer.classList.remove('no-face');
                        
                        // Store current face descriptor
                        currentFaceDescriptor = detection.descriptor;
                        
                        // Draw detection box
                        const box = detection.detection.box;
                        ctx.strokeStyle = '#10b981';
                        ctx.lineWidth = 3;
                        ctx.strokeRect(box.x, box.y, box.width, box.height);
                        
                        // Draw face landmarks
                        faceapi.draw.drawFaceLandmarks(overlay, detection);
                        
                        // Auto-verify if enabled
                        if (document.getElementById('autoVerify').checked && !verificationInProgress && !verificationCooldown) {
                            autoVerifyFace();
                        }
                        
                        showStatus('<i class="fas fa-check-circle" style="color: #10b981;"></i> Face detected. Ready to verify.', 'success');
                    } else {
                        videoContainer.classList.remove('face-detected');
                        videoContainer.classList.add('no-face');
                        currentFaceDescriptor = null;
                        showStatus('<i class="fas fa-search" style="color: #f59e0b;"></i> Scanning for faces...', 'info');
                    }
                    
                } catch (error) {
                    console.error('Detection error:', error);
                }
                
                // Continue detection loop
                if (isRunning) {
                    requestAnimationFrame(detectFaces);
                }
            };
            
            // Wait for video to be ready
            video.addEventListener('loadeddata', () => {
                detectFaces();
            });
            
            if (video.readyState >= 2) {
                detectFaces();
            }
        }
        
        // Auto verify face
        async function autoVerifyFace() {
            if (!currentFaceDescriptor || verificationInProgress) return;
            
            const match = findMatchingFace(currentFaceDescriptor);
            
            if (match && match.distance < 0.5) { // Good match threshold
                // Check if same student was just verified (cooldown)
                if (lastVerifiedStudentId === match.student.student_id) {
                    return; // Skip, already verified recently
                }
                
                await verifyStudent(match.student, match.distance);
            }
        }
        
        // Manual verify current face
        async function verifyCurrentFace() {
            if (!currentFaceDescriptor) {
                showStatus('No face detected. Please position face in the frame.', 'error');
                return;
            }
            
            const match = findMatchingFace(currentFaceDescriptor);
            
            if (match) {
                await verifyStudent(match.student, match.distance);
            } else {
                showNoMatchResult();
            }
        }
        
        // Find matching face from registered faces
        function findMatchingFace(descriptor) {
            let bestMatch = null;
            let bestDistance = Infinity;
            
            for (const face of registeredFaces) {
                if (!face.descriptor) continue;
                
                const distance = faceapi.euclideanDistance(
                    new Float32Array(descriptor),
                    new Float32Array(face.descriptor)
                );
                
                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestMatch = face;
                }
            }
            
            // Return match only if distance is below threshold (0.6 is typical threshold)
            if (bestMatch && bestDistance < 0.6) {
                return { student: bestMatch, distance: bestDistance };
            }
            
            return null;
        }
        
        // Verify student and mark attendance
        async function verifyStudent(student, distance) {
            if (verificationInProgress) return;
            
            verificationInProgress = true;
            verificationCooldown = true;
            
            try {
                // Send verification to server
                const formData = new FormData();
                formData.append('student_id', student.student_id);
                formData.append('confidence', (1 - distance).toFixed(2));
                
                const response = await fetch('includes/verify_face.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccessResult(student, result, distance);
                    lastVerifiedStudentId = student.student_id;
                    
                    // Update today's count
                    const countEl = document.getElementById('todayCount');
                    countEl.textContent = parseInt(countEl.textContent) + 1;
                    
                    // Add to recent verifications
                    addToRecentVerifications(student, result);
                    
                    // Play success sound
                    document.getElementById('successSound').play();
                } else {
                    showErrorResult(result.message);
                    
                    if (result.already_marked) {
                        lastVerifiedStudentId = student.student_id;
                    }
                }
                
            } catch (error) {
                console.error('Verification error:', error);
                showErrorResult('Failed to verify. Please try again.');
            } finally {
                verificationInProgress = false;
                
                // Reset cooldown after 3 seconds
                setTimeout(() => {
                    verificationCooldown = false;
                }, 3000);
            }
        }
        
        // Show success result
        function showSuccessResult(student, result, distance) {
            const resultCard = document.getElementById('resultCard');
            const resultHeader = document.getElementById('resultHeader');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.style.display = 'block';
            resultCard.className = 'card result-success';
            resultHeader.style.background = 'linear-gradient(135deg, #10b981, #06b6d4)';
            
            const confidence = Math.round((1 - distance) * 100);
            const confidenceClass = confidence >= 80 ? 'confidence-high' : (confidence >= 60 ? 'confidence-medium' : 'confidence-low');
            
            const initials = (student.first_name[0] || '') + (student.last_name[0] || '');
            const avatarContent = initials;
            
            resultContent.innerHTML = `
                <div class="student-info-card">
                    <div style="display: inline-block; padding: 0.5rem 1rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 50px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem;">
                        <i class="fas fa-check-circle"></i> Verified Successfully
                    </div>
                    <div class="student-avatar">${avatarContent}</div>
                    <div class="student-name">${student.first_name} ${student.last_name}</div>
                    <div class="student-id">${student.student_number}</div>
                    
                    <div class="confidence-meter">
                        <div class="confidence-label">
                            <span>Match Confidence</span>
                            <span style="font-weight: 600;">${confidence}%</span>
                        </div>
                        <div class="confidence-bar">
                            <div class="confidence-fill ${confidenceClass}" style="width: ${confidence}%;"></div>
                        </div>
                    </div>
                    
                    <div class="student-details">
                        <div class="detail-item">
                            <div class="detail-label">Grade</div>
                            <div class="detail-value">${student.grade}-${student.class_section}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value" style="color: ${result.status === 'present' ? '#10b981' : '#f59e0b'};">
                                ${result.status === 'present' ? 'On Time' : 'Late'}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Check-in Time</div>
                            <div class="detail-value">${result.time}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Method</div>
                            <div class="detail-value">Face Recognition</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Show error result
        function showErrorResult(message) {
            const resultCard = document.getElementById('resultCard');
            const resultHeader = document.getElementById('resultHeader');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.style.display = 'block';
            resultCard.className = 'card result-error';
            resultHeader.style.background = 'linear-gradient(135deg, #ef4444, #f87171)';
            resultHeader.innerHTML = '<h3 class="card-title" style="color: white;"><i class="fas fa-exclamation-triangle"></i> Verification Issue</h3>';
            
            resultContent.innerHTML = `
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; margin: 0 auto 1rem; border-radius: 50%; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; color: #ef4444;"></i>
                    </div>
                    <p style="color: var(--text-primary); font-weight: 500; margin-bottom: 0.5rem;">${message}</p>
                </div>
            `;
            
            document.getElementById('errorSound').play();
        }
        
        // Show no match result
        function showNoMatchResult() {
            const resultCard = document.getElementById('resultCard');
            const resultHeader = document.getElementById('resultHeader');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.style.display = 'block';
            resultCard.className = 'card result-error';
            resultHeader.style.background = 'linear-gradient(135deg, #f59e0b, #eab308)';
            resultHeader.innerHTML = '<h3 class="card-title" style="color: white;"><i class="fas fa-user-xmark"></i> No Match Found</h3>';
            
            resultContent.innerHTML = `
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; margin: 0 auto 1rem; border-radius: 50%; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-xmark" style="font-size: 1.5rem; color: #f59e0b;"></i>
                    </div>
                    <p style="color: var(--text-primary); font-weight: 500; margin-bottom: 0.5rem;">Face Not Recognized</p>
                    <p style="color: var(--text-secondary); font-size: 0.85rem;">The detected face doesn't match any registered student. The student may need to register their face first.</p>
                </div>
            `;
            
            document.getElementById('errorSound').play();
        }
        
        // Add to recent verifications list
        function addToRecentVerifications(student, result) {
            const container = document.getElementById('recentVerificationsContainer');
            
            // Remove "no verifications" message if present
            const emptyMessage = container.querySelector('div[style*="text-align: center"]');
            if (emptyMessage && emptyMessage.textContent.includes('No face verifications')) {
                emptyMessage.remove();
            }
            
            const newItem = document.createElement('div');
            newItem.className = 'verification-item';
            newItem.style.cssText = 'padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem; background: rgba(139, 92, 246, 0.05);';
            
            newItem.innerHTML = `
                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-face-smile" style="color: #8b5cf6;"></i>
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
                    <span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        ${result.status === 'present' ? 'Present' : 'Late'}
                    </span>
                    <div style="font-size: 0.7rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                        ${result.time}
                    </div>
                </div>
            `;
            
            container.insertBefore(newItem, container.firstChild);
            
            // Fade out highlight
            setTimeout(() => {
                newItem.style.background = 'transparent';
            }, 2000);
        }
        
        // Show status message
        function showStatus(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            const colors = {
                info: 'var(--text-secondary)',
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b'
            };
            
            statusDiv.innerHTML = `<span style="color: ${colors[type] || colors.info}; font-size: 0.9rem;">${message}</span>`;
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', stopFaceCamera);
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
