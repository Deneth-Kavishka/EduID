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

// Generate QR code data (this will be used by JavaScript QR generator)
$qr_data = json_encode([
    'type' => 'eduid_student',
    'student_id' => $student['student_id'],
    'student_number' => $student['student_number'],
    'name' => $student['first_name'] . ' ' . $student['last_name'],
    'grade' => $student['grade'],
    'class' => $student['class_section']
]);

// Update database with QR code reference if not set
if (empty($student['qr_code'])) {
    $qr_ref = 'student_' . $student['student_number'];
    $update_query = "UPDATE students SET qr_code = :qr_code WHERE student_id = :student_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':qr_code', $qr_ref);
    $update_stmt->bindParam(':student_id', $student_id);
    $update_stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Code - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .qr-container {
            max-width: 600px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .qr-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
        }
        
        .qr-image {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            display: inline-block;
            margin: 2rem 0;
            box-shadow: var(--shadow-lg);
        }
        
        .qr-image img {
            display: block;
            max-width: 300px;
            height: auto;
        }
        
        .qr-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-download {
            background: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-download:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-print {
            background: var(--success-color);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-print:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar (Same as student dashboard) -->
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
                    <a href="qr-code.php" class="nav-item active">
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
                    <h1>My QR Code</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>QR Code</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php include 'includes/profile_dropdown.php'; ?>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="qr-container">
                    <div class="qr-card">
                        <h2 style="font-size: 2rem; margin-bottom: 1rem; color: var(--text-primary);">
                            Your Personal QR Code
                        </h2>
                        <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 1rem;">
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </p>
                        <p style="color: var(--text-tertiary); font-size: 0.95rem;">
                            Student ID: <?php echo htmlspecialchars($student['student_number']); ?> | 
                            Grade: <?php echo htmlspecialchars($student['grade']); ?>-<?php echo htmlspecialchars($student['class_section']); ?>
                        </p>
                        
                        <div class="qr-image" id="qrCodeImage">
                            <div id="qrcode" style="display: inline-block;"></div>
                        </div>
                        
                        <div style="background: rgba(37, 99, 235, 0.1); padding: 1.5rem; border-radius: var(--border-radius); margin-top: 2rem;">
                            <h3 style="color: var(--primary-color); font-size: 1.1rem; margin-bottom: 0.75rem;">
                                <i class="fas fa-info-circle"></i> How to Use
                            </h3>
                            <ul style="text-align: left; color: var(--text-secondary); line-height: 1.8; max-width: 400px; margin: 0 auto;">
                                <li>Show this QR code at exam halls for verification</li>
                                <li>Use it for event check-ins</li>
                                <li>Present it for daily attendance marking</li>
                                <li>Keep it saved on your phone for quick access</li>
                            </ul>
                        </div>
                        
                        <div class="qr-actions">
                            <button onclick="downloadQRCode()" class="btn-download">
                                <i class="fas fa-download"></i> Download QR Code
                            </button>
                            <button onclick="printQRCode()" class="btn-print">
                                <i class="fas fa-print"></i> Print QR Code
                            </button>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <h3 style="font-size: 1.3rem; margin-bottom: 1rem; color: var(--text-primary);">
                            <i class="fas fa-shield-halved"></i> Security Tips
                        </h3>
                        <div style="text-align: left; color: var(--text-secondary); line-height: 1.8;">
                            <p style="margin-bottom: 0.75rem;"><i class="fas fa-check" style="color: var(--success-color);"></i> Do not share your QR code with others</p>
                            <p style="margin-bottom: 0.75rem;"><i class="fas fa-check" style="color: var(--success-color);"></i> If your QR code is compromised, contact the admin immediately</p>
                            <p style="margin-bottom: 0.75rem;"><i class="fas fa-check" style="color: var(--success-color);"></i> Your QR code contains your student information</p>
                            <p style="margin-bottom: 0.75rem;"><i class="fas fa-check" style="color: var(--success-color);"></i> Always present your QR code in person</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- QR Code Generator Library - Using qrcodejs which is more reliable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="../../assets/js/theme.js"></script>
    <script>
        // QR Code Data
        const qrData = <?php echo $qr_data; ?>;
        let qrCodeInstance = null;
        
        // Generate QR Code on page load
        document.addEventListener('DOMContentLoaded', function() {
            generateQRCode();
        });
        
        function generateQRCode() {
            const qrContainer = document.getElementById('qrcode');
            qrContainer.innerHTML = '';
            
            try {
                // Create QR code using qrcodejs library
                qrCodeInstance = new QRCode(qrContainer, {
                    text: JSON.stringify(qrData),
                    width: 280,
                    height: 280,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
                console.log('QR Code generated successfully');
            } catch (error) {
                console.error('QR Code generation error:', error);
                qrContainer.innerHTML = '<p style="color: red;">Error generating QR code. Please refresh the page.</p>';
            }
        }
        
        function downloadQRCode() {
            // qrcodejs creates an img element inside the container
            const qrImg = document.querySelector('#qrcode img');
            const qrCanvas = document.querySelector('#qrcode canvas');
            
            if (!qrImg && !qrCanvas) {
                alert('QR Code not generated yet. Please wait and try again.');
                return;
            }
            
            // Create a canvas for download
            const downloadCanvas = document.createElement('canvas');
            const ctx = downloadCanvas.getContext('2d');
            const padding = 40;
            const qrSize = 280;
            
            downloadCanvas.width = qrSize + (padding * 2);
            downloadCanvas.height = qrSize + (padding * 2) + 80;
            
            // White background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, downloadCanvas.width, downloadCanvas.height);
            
            // Draw QR code from image or canvas
            const source = qrImg || qrCanvas;
            ctx.drawImage(source, padding, padding, qrSize, qrSize);
            
            // Add student info text
            ctx.fillStyle = '#333333';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', downloadCanvas.width / 2, qrSize + padding + 30);
            
            ctx.font = '14px Arial';
            ctx.fillText('ID: <?php echo htmlspecialchars($student['student_number']); ?>', downloadCanvas.width / 2, qrSize + padding + 55);
            
            ctx.font = '12px Arial';
            ctx.fillStyle = '#666666';
            ctx.fillText('EduID Verification', downloadCanvas.width / 2, qrSize + padding + 75);
            
            // Download
            const link = document.createElement('a');
            link.download = 'EduID_QR_<?php echo $student['student_number']; ?>.png';
            link.href = downloadCanvas.toDataURL('image/png', 1.0);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function printQRCode() {
            // qrcodejs creates an img element inside the container
            const qrImg = document.querySelector('#qrcode img');
            const qrCanvas = document.querySelector('#qrcode canvas');
            
            if (!qrImg && !qrCanvas) {
                alert('QR Code not generated yet. Please wait and try again.');
                return;
            }
            
            // Get data URL from image or canvas
            let dataUrl;
            if (qrImg) {
                dataUrl = qrImg.src;
            } else {
                dataUrl = qrCanvas.toDataURL('image/png');
            }
            
            const printWindow = window.open('', '_blank', 'width=600,height=700');
            
            if (!printWindow) {
                alert('Please allow popups to print the QR code.');
                return;
            }
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print QR Code - EduID</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                            font-family: 'Segoe UI', Arial, sans-serif;
                            background: white;
                        }
                        .print-container {
                            text-align: center;
                            padding: 40px;
                            border: 3px solid #2563eb;
                            border-radius: 20px;
                            max-width: 400px;
                        }
                        .logo {
                            font-size: 28px;
                            font-weight: 800;
                            color: #2563eb;
                            margin-bottom: 20px;
                        }
                        .qr-image {
                            background: white;
                            padding: 15px;
                            border-radius: 10px;
                            display: inline-block;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                        }
                        .qr-image img {
                            display: block;
                            width: 250px;
                            height: 250px;
                        }
                        .student-name {
                            font-size: 22px;
                            font-weight: 700;
                            color: #1e293b;
                            margin-top: 20px;
                        }
                        .student-id {
                            font-size: 16px;
                            color: #64748b;
                            margin-top: 8px;
                        }
                        .student-class {
                            font-size: 14px;
                            color: #94a3b8;
                            margin-top: 5px;
                        }
                        .footer {
                            margin-top: 25px;
                            padding-top: 15px;
                            border-top: 1px solid #e2e8f0;
                            font-size: 12px;
                            color: #94a3b8;
                        }
                        @media print {
                            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                            .print-container { border: 3px solid #2563eb !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-container">
                        <div class="logo">🎓 EduID</div>
                        <div class="qr-image">
                            <img src="${dataUrl}" alt="QR Code">
                        </div>
                        <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                        <div class="student-id">Student ID: <?php echo htmlspecialchars($student['student_number']); ?></div>
                        <div class="student-class">Grade <?php echo htmlspecialchars($student['grade']); ?> - Section <?php echo htmlspecialchars($student['class_section']); ?></div>
                        <div class="footer">Educational Identity Verification System</div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.focus();
                    printWindow.print();
                }, 250);
            };
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
    </script>
</body>
</html>
