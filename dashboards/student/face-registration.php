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

// Check if face is already registered
$query = "SELECT * FROM face_recognition_data WHERE user_id = :user_id AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$face_registered = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Registration - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .face-registration-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .tabs-container {
            display: flex;
            gap: 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .tab-btn {
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
        
        .tab-btn:hover {
            color: var(--primary-color);
        }
        
        .tab-btn.active {
            color: var(--primary-color);
        }
        
        .tab-btn.active::after {
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
        
        .video-container {
            position: relative;
            background: var(--bg-tertiary);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            aspect-ratio: 4/3;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #video, #verifyVideo {
            width: 100%;
            height: auto;
            display: none;
        }
        
        #canvas, #verifyCanvas {
            display: none;
        }
        
        .video-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            border: 3px dashed var(--primary-color);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .video-overlay.success {
            border-color: var(--success-color);
            border-style: solid;
        }
        
        .video-overlay.error {
            border-color: var(--danger-color);
            border-style: solid;
        }
        
        .capture-button {
            margin-top: 1.5rem;
            width: 100%;
            padding: 1.25rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .capture-button:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .capture-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .status-message {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            text-align: center;
            font-weight: 500;
        }
        
        .status-message.info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .status-message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .status-message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .verification-result {
            margin-top: 1.5rem;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
        }
        
        .verification-result.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            border: 2px solid rgba(16, 185, 129, 0.3);
        }
        
        .verification-result.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 2px solid rgba(239, 68, 68, 0.3);
        }
        
        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .verification-icon.success {
            color: #10b981;
        }
        
        .verification-icon.error {
            color: #ef4444;
        }
        
        .detection-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-item .value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-item .label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar (Same as other student pages) -->
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
                    <a href="face-registration.php" class="nav-item active">
                        <i class="fas fa-face-smile"></i>
                        <span>Face Registration</span>
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
                    <h1>Face Registration</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Face Registration</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="face-registration-container">
                    <!-- Tabs -->
                    <div class="tabs-container">
                        <button class="tab-btn active" onclick="switchTab('register')">
                            <i class="fas fa-camera"></i> Register Face
                        </button>
                        <button class="tab-btn" onclick="switchTab('verify')">
                            <i class="fas fa-user-check"></i> Verify Face
                        </button>
                    </div>
                    
                    <!-- Register Face Tab -->
                    <div id="registerTab" class="tab-content active">
                        <div class="card">
                            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: var(--text-primary);">
                                <i class="fas fa-face-smile"></i> Register Your Face
                            </h2>
                            
                            <?php if ($face_registered): ?>
                                <div class="status-message success">
                                    <i class="fas fa-check-circle"></i> Your face is already registered! 
                                    You can update it by capturing a new photo below.
                                </div>
                            <?php else: ?>
                                <div class="status-message info">
                                    <i class="fas fa-info-circle"></i> Register your face for secure biometric verification
                                </div>
                            <?php endif; ?>
                            
                            <div class="video-container" style="margin-top: 1.5rem;">
                                <video id="video" autoplay playsinline></video>
                                <canvas id="canvas"></canvas>
                                <div class="video-overlay" id="registerOverlay"></div>
                                <div id="placeholder" style="text-align: center; color: var(--text-tertiary);">
                                    <i class="fas fa-camera" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                    <p style="font-size: 1.2rem;">Click "Start Camera" to begin</p>
                                </div>
                            </div>
                            
                            <button id="startBtn" class="capture-button">
                                <i class="fas fa-video"></i> Start Camera
                            </button>
                            
                            <button id="captureBtn" class="capture-button" style="display: none; background: var(--success-color);">
                                <i class="fas fa-camera"></i> Capture Face
                            </button>
                            
                            <button id="saveBtn" class="capture-button" style="display: none; background: var(--success-color);">
                                <i class="fas fa-save"></i> Save Face Data
                            </button>
                            
                            <div id="statusMessage"></div>
                            
                            <div class="card mt-3" style="background: rgba(59, 130, 246, 0.05);">
                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                                    <i class="fas fa-lightbulb"></i> Instructions
                                </h3>
                                <ol style="color: var(--text-secondary); line-height: 2; padding-left: 1.5rem;">
                                    <li>Position your face within the circular guide</li>
                                    <li>Ensure good lighting and remove glasses if possible</li>
                                    <li>Look directly at the camera with a neutral expression</li>
                                    <li>Click "Capture Face" when ready</li>
                                    <li>Review the captured image and save if satisfied</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Verify Face Tab -->
                    <div id="verifyTab" class="tab-content">
                        <div class="card">
                            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: var(--text-primary);">
                                <i class="fas fa-user-check"></i> Verify Your Face
                            </h2>
                            
                            <?php if ($face_registered): ?>
                                <div class="status-message success" style="margin-bottom: 1.5rem;">
                                    <i class="fas fa-check-circle"></i> Face data found! Start verification to test face detection.
                                </div>
                            <?php else: ?>
                                <div class="status-message error" style="margin-bottom: 1.5rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No face registered yet. Please register your face first.
                                </div>
                            <?php endif; ?>
                            
                            <div class="video-container">
                                <video id="verifyVideo" autoplay playsinline></video>
                                <canvas id="verifyCanvas"></canvas>
                                <div class="video-overlay" id="verifyOverlay"></div>
                                <div id="verifyPlaceholder" style="text-align: center; color: var(--text-tertiary);">
                                    <i class="fas fa-user-check" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                    <p style="font-size: 1.2rem;">Click "Start Verification" to test face detection</p>
                                </div>
                            </div>
                            
                            <button id="startVerifyBtn" class="capture-button" <?php echo !$face_registered ? 'disabled' : ''; ?>>
                                <i class="fas fa-play"></i> Start Verification
                            </button>
                            
                            <button id="stopVerifyBtn" class="capture-button" style="display: none; background: var(--danger-color);">
                                <i class="fas fa-stop"></i> Stop Verification
                            </button>
                            
                            <div id="verificationResult"></div>
                            
                            <div id="detectionStats" class="detection-stats" style="display: none;">
                                <div class="stat-item">
                                    <div class="value" id="faceDetected">-</div>
                                    <div class="label">Face Detected</div>
                                </div>
                                <div class="stat-item">
                                    <div class="value" id="confidenceScore">-</div>
                                    <div class="label">Confidence</div>
                                </div>
                                <div class="stat-item">
                                    <div class="value" id="matchStatus">-</div>
                                    <div class="label">Match Status</div>
                                </div>
                                <div class="stat-item">
                                    <div class="value" id="detectionTime">-</div>
                                    <div class="label">Detection Time</div>
                                </div>
                            </div>
                            
                            <div class="card mt-3" style="background: rgba(16, 185, 129, 0.05);">
                                <h3 style="color: var(--success-color); margin-bottom: 1rem;">
                                    <i class="fas fa-shield-halved"></i> Verification Info
                                </h3>
                                <ul style="color: var(--text-secondary); line-height: 2; padding-left: 1.5rem;">
                                    <li>This will test if your face can be detected properly</li>
                                    <li>Ensure same lighting conditions as registration</li>
                                    <li>Face should be clearly visible without obstructions</li>
                                    <li>The system will show real-time detection status</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Face API JS Library -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script src="../../assets/js/face-recognition.js"></script>
    <script>
        // Tab switching
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            if (tab === 'register') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.getElementById('registerTab').classList.add('active');
                stopVerification();
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.getElementById('verifyTab').classList.add('active');
            }
        }
        
        // Verification variables
        let verifyStream = null;
        let verifyInterval = null;
        let modelsLoaded = false;
        let registeredDescriptor = null;
        let matchCount = 0;
        let totalAttempts = 0;
        
        // Registered face data from database
        <?php
        $stored_descriptor = null;
        if ($face_registered && !empty($face_registered['face_descriptor'])) {
            $stored_descriptor = $face_registered['face_descriptor'];
        }
        ?>
        const storedDescriptorJson = <?php echo $stored_descriptor ? "'" . addslashes($stored_descriptor) . "'" : 'null'; ?>;
        
        // Load face-api models
        async function loadFaceModels() {
            if (modelsLoaded) return true;
            
            try {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model';
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                modelsLoaded = true;
                
                // Parse stored descriptor if available
                if (storedDescriptorJson) {
                    try {
                        const parsed = JSON.parse(storedDescriptorJson);
                        registeredDescriptor = new Float32Array(parsed);
                    } catch (e) {
                        console.error('Failed to parse stored descriptor:', e);
                    }
                }
                
                return true;
            } catch (error) {
                console.error('Failed to load models:', error);
                return false;
            }
        }
        
        // Calculate face distance (similarity)
        function euclideanDistance(desc1, desc2) {
            let sum = 0;
            for (let i = 0; i < desc1.length; i++) {
                sum += Math.pow(desc1[i] - desc2[i], 2);
            }
            return Math.sqrt(sum);
        }
        
        // Start verification
        document.getElementById('startVerifyBtn')?.addEventListener('click', async function() {
            const video = document.getElementById('verifyVideo');
            const placeholder = document.getElementById('verifyPlaceholder');
            const startBtn = document.getElementById('startVerifyBtn');
            const stopBtn = document.getElementById('stopVerifyBtn');
            const statsDiv = document.getElementById('detectionStats');
            const resultDiv = document.getElementById('verificationResult');
            
            startBtn.disabled = true;
            startBtn.innerHTML = '<span class="loading-spinner"></span> Loading...';
            resultDiv.innerHTML = '';
            
            // Load models
            const loaded = await loadFaceModels();
            if (!loaded) {
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-play"></i> Start Verification';
                resultDiv.innerHTML = '<div class="status-message error"><i class="fas fa-exclamation-circle"></i> Failed to load face detection models</div>';
                return;
            }
            
            try {
                verifyStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user', width: 640, height: 480 } 
                });
                
                video.srcObject = verifyStream;
                video.style.display = 'block';
                placeholder.style.display = 'none';
                
                startBtn.style.display = 'none';
                stopBtn.style.display = 'block';
                statsDiv.style.display = 'grid';
                
                // Start detection loop
                verifyInterval = setInterval(() => detectFace(video), 500);
                
            } catch (error) {
                console.error('Camera error:', error);
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-play"></i> Start Verification';
                resultDiv.innerHTML = '<div class="status-message error"><i class="fas fa-exclamation-circle"></i> Could not access camera</div>';
            }
        });
        
        // Detect face
        async function detectFace(video) {
            if (!video || video.paused || video.ended) return;
            
            const startTime = performance.now();
            const overlay = document.getElementById('verifyOverlay');
            const resultDiv = document.getElementById('verificationResult');
            
            try {
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                
                const endTime = performance.now();
                const detectionTime = Math.round(endTime - startTime);
                totalAttempts++;
                
                document.getElementById('detectionTime').textContent = detectionTime + 'ms';
                
                if (detection) {
                    const confidence = Math.round(detection.detection.score * 100);
                    let matchStatus = 'Detecting...';
                    let matchColor = '#f59e0b';
                    let isMatch = false;
                    let matchPercentage = 0;
                    
                    document.getElementById('faceDetected').textContent = 'Yes';
                    document.getElementById('faceDetected').style.color = '#10b981';
                    document.getElementById('confidenceScore').textContent = confidence + '%';
                    document.getElementById('confidenceScore').style.color = confidence > 70 ? '#10b981' : '#f59e0b';
                    
                    // Compare with registered face if available
                    if (registeredDescriptor) {
                        const currentDescriptor = detection.descriptor;
                        const distance = euclideanDistance(currentDescriptor, registeredDescriptor);
                        
                        // Distance < 0.6 is typically a match
                        // Convert distance to percentage (lower distance = better match)
                        matchPercentage = Math.max(0, Math.round((1 - distance) * 100));
                        
                        if (distance < 0.45) {
                            matchStatus = 'VERIFIED ✓';
                            matchColor = '#10b981';
                            isMatch = true;
                            matchCount++;
                        } else if (distance < 0.6) {
                            matchStatus = 'Possible Match';
                            matchColor = '#f59e0b';
                        } else {
                            matchStatus = 'No Match';
                            matchColor = '#ef4444';
                        }
                    } else {
                        matchStatus = 'No Registered Face';
                        matchColor = '#6b7280';
                    }
                    
                    document.getElementById('matchStatus').textContent = matchStatus;
                    document.getElementById('matchStatus').style.color = matchColor;
                    
                    overlay.classList.remove('error');
                    overlay.classList.add('success');
                    
                    if (isMatch && confidence > 70) {
                        resultDiv.innerHTML = `
                            <div class="verification-result success">
                                <i class="fas fa-shield-check verification-icon success" style="font-size: 4rem;"></i>
                                <h3 style="color: #10b981; margin-bottom: 0.5rem;">Identity Verified!</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Your face matches the registered profile</p>
                                <div style="display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">${confidence}%</div>
                                        <div style="font-size: 0.8rem; color: var(--text-tertiary);">Detection Confidence</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">${matchPercentage}%</div>
                                        <div style="font-size: 0.8rem; color: var(--text-tertiary);">Match Score</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">${matchCount}/${totalAttempts}</div>
                                        <div style="font-size: 0.8rem; color: var(--text-tertiary);">Successful Matches</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (registeredDescriptor && !isMatch) {
                        resultDiv.innerHTML = `
                            <div class="verification-result error">
                                <i class="fas fa-user-xmark verification-icon error" style="font-size: 4rem;"></i>
                                <h3 style="color: #ef4444; margin-bottom: 0.5rem;">Face Does Not Match</h3>
                                <p style="color: var(--text-secondary);">The detected face doesn't match your registered profile (${matchPercentage}% similarity)</p>
                            </div>
                        `;
                    } else if (!registeredDescriptor) {
                        resultDiv.innerHTML = `
                            <div class="verification-result" style="background: rgba(107, 114, 128, 0.1); border: 2px solid rgba(107, 114, 128, 0.3);">
                                <i class="fas fa-face-smile verification-icon" style="font-size: 4rem; color: #6b7280;"></i>
                                <h3 style="color: #6b7280; margin-bottom: 0.5rem;">Face Detected (${confidence}%)</h3>
                                <p style="color: var(--text-secondary);">No registered face data to compare. Please register your face first.</p>
                            </div>
                        `;
                    }
                } else {
                    document.getElementById('faceDetected').textContent = 'No';
                    document.getElementById('faceDetected').style.color = '#ef4444';
                    document.getElementById('confidenceScore').textContent = '-';
                    document.getElementById('matchStatus').textContent = 'Waiting...';
                    document.getElementById('matchStatus').style.color = '#6b7280';
                    
                    overlay.classList.remove('success');
                    overlay.classList.add('error');
                    
                    resultDiv.innerHTML = `
                        <div class="verification-result error">
                            <i class="fas fa-face-frown verification-icon error" style="font-size: 4rem;"></i>
                            <h3 style="color: #ef4444; margin-bottom: 0.5rem;">No Face Detected</h3>
                            <p style="color: var(--text-secondary);">Position your face within the circle and ensure good lighting</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Detection error:', error);
            }
        }
        
        // Stop verification
        document.getElementById('stopVerifyBtn')?.addEventListener('click', stopVerification);
        
        function stopVerification() {
            if (verifyInterval) {
                clearInterval(verifyInterval);
                verifyInterval = null;
            }
            
            if (verifyStream) {
                verifyStream.getTracks().forEach(track => track.stop());
                verifyStream = null;
            }
            
            // Reset counters
            matchCount = 0;
            totalAttempts = 0;
            
            const video = document.getElementById('verifyVideo');
            const placeholder = document.getElementById('verifyPlaceholder');
            const startBtn = document.getElementById('startVerifyBtn');
            const stopBtn = document.getElementById('stopVerifyBtn');
            const overlay = document.getElementById('verifyOverlay');
            const resultDiv = document.getElementById('verificationResult');
            const statsDiv = document.getElementById('detectionStats');
            
            if (video) {
                video.style.display = 'none';
                video.srcObject = null;
            }
            if (placeholder) placeholder.style.display = 'block';
            if (startBtn) {
                startBtn.style.display = 'block';
                startBtn.disabled = <?php echo !$face_registered ? 'true' : 'false'; ?>;
                startBtn.innerHTML = '<i class="fas fa-play"></i> Start Verification';
            }
            if (stopBtn) stopBtn.style.display = 'none';
            if (overlay) {
                overlay.classList.remove('success', 'error');
            }
            if (resultDiv) resultDiv.innerHTML = '';
            if (statsDiv) {
                statsDiv.style.display = 'none';
                document.getElementById('faceDetected').textContent = '-';
                document.getElementById('confidenceScore').textContent = '-';
                document.getElementById('matchStatus').textContent = '-';
                document.getElementById('detectionTime').textContent = '-';
            }
        }
        
        // Sidebar scroll position preservation
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar-nav');
            const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos && sidebar) {
                sidebar.scrollTop = parseInt(savedScrollPos);
            }
            
            const activeItem = document.querySelector('.nav-item.active');
            if (activeItem && sidebar) {
                const sidebarRect = sidebar.getBoundingClientRect();
                const itemRect = activeItem.getBoundingClientRect();
                if (itemRect.top < sidebarRect.top || itemRect.bottom > sidebarRect.bottom) {
                    activeItem.scrollIntoView({ block: 'center', behavior: 'auto' });
                }
            }
            
            if (sidebar) {
                sidebar.addEventListener('scroll', function() {
                    sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
                });
            }
        });
        
        // Stop verification when leaving page
        window.addEventListener('beforeunload', stopVerification);
    </script>
</body>
</html>
