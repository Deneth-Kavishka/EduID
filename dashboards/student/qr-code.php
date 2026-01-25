<?php
require_once '../../config/config.php';
require_once '../../vendor/phpqrcode/qrlib.php'; // We'll create instructions for installing this

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

// Generate QR code data
$qr_data = json_encode([
    'student_id' => $student['student_id'],
    'student_number' => $student['student_number'],
    'name' => $student['first_name'] . ' ' . $student['last_name'],
    'grade' => $student['grade'],
    'class' => $student['class_section'],
    'timestamp' => time()
]);

// QR code file path
$qr_filename = 'student_' . $student['student_number'] . '.png';
$qr_filepath = QR_CODES_PATH . $qr_filename;

// Generate QR code if not exists
if (!file_exists($qr_filepath)) {
    if (!is_dir(QR_CODES_PATH)) {
        mkdir(QR_CODES_PATH, 0777, true);
    }
    QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_H, 10, 2);
    
    // Update database
    $update_query = "UPDATE students SET qr_code = :qr_code WHERE student_id = :student_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':qr_code', $qr_filename);
    $update_stmt->bindParam(':student_id', $student_id);
    $update_stmt->execute();
}

$qr_url = '../../uploads/qr_codes/' . $qr_filename;
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
                            <img src="<?php echo $qr_url; ?>" alt="Student QR Code">
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
                            <a href="<?php echo $qr_url; ?>" download="my_qr_code.png" class="btn-download">
                                <i class="fas fa-download"></i> Download QR Code
                            </a>
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
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function printQRCode() {
            const qrImage = document.getElementById('qrCodeImage');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print QR Code</title>
                    <style>
                        body {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            height: 100vh;
                            margin: 0;
                            font-family: Arial, sans-serif;
                        }
                        .print-container {
                            text-align: center;
                        }
                        img {
                            max-width: 400px;
                        }
                        h2 {
                            margin-top: 1rem;
                        }
                    </style>
                </head>
                <body>
                    <div class="print-container">
                        <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                        <p>Student ID: <?php echo htmlspecialchars($student['student_number']); ?></p>
                        ${qrImage.innerHTML}
                        <p style="margin-top: 1rem; font-size: 0.9rem;">EduID - Educational Identity Verification System</p>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    </script>
</body>
</html>
