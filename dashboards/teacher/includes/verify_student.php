<?php
/**
 * Student Verification Handler
 * Processes QR code scans and marks attendance
 */
require_once '../../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$student_number = $data['student_number'] ?? null;
$student_id = $data['student_id'] ?? null;
$verification_method = $data['verification_method'] ?? 'qr_code';

$db = new Database();
$conn = $db->getConnection();

try {
    // Find student by student_number or student_id
    if ($student_number) {
        $query = "SELECT s.*, u.status as user_status 
                  FROM students s 
                  JOIN users u ON s.user_id = u.user_id 
                  WHERE s.student_number = :identifier";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':identifier', $student_number);
    } elseif ($student_id) {
        $query = "SELECT s.*, u.status as user_status 
                  FROM students s 
                  JOIN users u ON s.user_id = u.user_id 
                  WHERE s.student_id = :identifier";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':identifier', $student_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'No student identifier provided']);
        exit;
    }
    
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found in the system']);
        exit;
    }
    
    // Check if student account is active
    if ($student['user_status'] !== 'active') {
        echo json_encode([
            'success' => false, 
            'message' => 'Student account is ' . $student['user_status'],
            'student' => [
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'student_number' => $student['student_number'],
                'grade' => $student['grade'],
                'class_section' => $student['class_section']
            ]
        ]);
        exit;
    }
    
    // Check if attendance already marked for today
    $today = date('Y-m-d');
    $query = "SELECT * FROM attendance WHERE student_id = :student_id AND date = :date";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->bindParam(':date', $today);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Already marked
        echo json_encode([
            'success' => true,
            'already_marked' => true,
            'message' => 'Attendance already marked at ' . $existing['check_in_time'],
            'student' => [
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'student_number' => $student['student_number'],
                'grade' => $student['grade'],
                'class_section' => $student['class_section']
            ],
            'attendance' => [
                'status' => $existing['status'],
                'check_in_time' => $existing['check_in_time'],
                'verification_method' => $existing['verification_method']
            ]
        ]);
        exit;
    }
    
    // Determine attendance status based on time
    $current_time = date('H:i:s');
    $late_threshold = '08:30:00'; // After 8:30 AM is late
    $status = ($current_time > $late_threshold) ? 'late' : 'present';
    
    // Mark attendance
    $query = "INSERT INTO attendance (student_id, date, check_in_time, status, verification_method, verified_by, location, created_at) 
              VALUES (:student_id, :date, :check_in_time, :status, :verification_method, :verified_by, :location, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->bindParam(':date', $today);
    $stmt->bindParam(':check_in_time', $current_time);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':verification_method', $verification_method);
    $stmt->bindParam(':verified_by', $_SESSION['user_id']);
    $location = 'QR Scanner - Teacher Portal';
    $stmt->bindParam(':location', $location);
    $stmt->execute();
    
    $attendance_id = $conn->lastInsertId();
    
    // Log the verification
    $query = "INSERT INTO access_logs (user_id, access_type, status, ip_address, details) 
              VALUES (:user_id, 'qr_scan', 'success', :ip, :details)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bindParam(':ip', $ip);
    $details = 'Verified student: ' . $student['student_number'] . ' via QR code';
    $stmt->bindParam(':details', $details);
    $stmt->execute();
    
    // Success response
    echo json_encode([
        'success' => true,
        'already_marked' => false,
        'message' => 'Attendance marked successfully',
        'student' => [
            'student_id' => $student['student_id'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'student_number' => $student['student_number'],
            'grade' => $student['grade'],
            'class_section' => $student['class_section']
        ],
        'attendance' => [
            'attendance_id' => $attendance_id,
            'status' => $status,
            'check_in_time' => $current_time,
            'verification_method' => $verification_method
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
}
