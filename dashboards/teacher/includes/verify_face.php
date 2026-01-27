<?php
/**
 * Face Verification Handler
 * Handles face recognition verification and attendance marking for teachers
 */

require_once '../../../config/config.php';
checkRole(['teacher']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $student_id = $_POST['student_id'] ?? null;
    $confidence = $_POST['confidence'] ?? 0.8;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        exit;
    }
    
    // Get student details
    $query = "SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = :student_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Check if already marked today
    $today = date('Y-m-d');
    $query = "SELECT * FROM attendance WHERE student_id = :student_id AND date = :date";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':date', $today);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode([
            'success' => false, 
            'already_marked' => true,
            'message' => 'Attendance already marked for ' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ' today at ' . $existing['check_in_time']
        ]);
        exit;
    }
    
    // Determine status (on time or late)
    $current_time = date('H:i:s');
    $late_threshold = '08:30:00'; // 8:30 AM
    $status = ($current_time <= $late_threshold) ? 'present' : 'late';
    
    // Insert attendance record
    $query = "INSERT INTO attendance (student_id, date, status, check_in_time, verification_method, verified_by, notes, created_at) 
              VALUES (:student_id, :date, :status, :check_in_time, 'face_recognition', :verified_by, :notes, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':date', $today);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':check_in_time', $current_time);
    $stmt->bindParam(':verified_by', $_SESSION['user_id']);
    $notes = 'Face recognition verification (Confidence: ' . ($confidence * 100) . '%)';
    $stmt->bindParam(':notes', $notes);
    $stmt->execute();
    
    // Log the activity in access_logs
    $query = "INSERT INTO access_logs (user_id, access_type, ip_address, status, remarks) VALUES (:user_id, 'face_verify', :ip_address, 'success', :remarks)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $student['user_id']); // Log for the student
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->bindParam(':ip_address', $ip_address);
    $remarks = 'Verified by teacher (user_id: ' . $_SESSION['user_id'] . ') - Confidence: ' . ($confidence * 100) . '%';
    $stmt->bindParam(':remarks', $remarks);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance verified successfully',
        'student' => [
            'student_id' => $student['student_id'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'student_number' => $student['student_number'],
            'grade' => $student['grade'],
            'class_section' => $student['class_section']
        ],
        'status' => $status,
        'time' => date('h:i A'),
        'date' => date('M d, Y')
    ]);
    
} catch (Exception $e) {
    error_log('Face verification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during verification. Please try again.'
    ]);
}
?>
