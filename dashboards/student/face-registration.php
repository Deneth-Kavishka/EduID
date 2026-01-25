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
            max-width: 800px;
            margin: 2rem auto;
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
        
        #video {
            width: 100%;
            height: auto;
            display: none;
        }
        
        #canvas {
            display: none;
        }
        
        .video-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            border: 3px dashed var(--primary-color);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .capture-button {
            margin-top: 2rem;
            width: 100%;
            padding: 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.2rem;
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
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
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
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="face-registration-container">
                    <div class="card">
                        <h2 style="font-size: 1.8rem; margin-bottom: 1rem; color: var(--text-primary);">
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
                        
                        <div class="video-container" style="margin-top: 2rem;">
                            <video id="video" autoplay playsinline></video>
                            <canvas id="canvas"></canvas>
                            <div class="video-overlay"></div>
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
            </div>
        </main>
    </div>
    
    <!-- Face API JS Library -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script src="../../assets/js/face-recognition.js"></script>
</body>
</html>
