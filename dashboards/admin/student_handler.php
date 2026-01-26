<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

// Get student details by student_id
if (isset($_GET['get_student'])) {
    $student_id = $_GET['student_id'] ?? 0;
    
    $query = "SELECT s.*, u.username, u.email, u.status, u.user_id,
              p.first_name as parent_fname, p.last_name as parent_lname, 
              p.phone as parent_phone, p.relationship,
              (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = s.user_id) as has_face_data
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              LEFT JOIN parents p ON s.parent_id = p.parent_id
              WHERE s.student_id = :student_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($student);
    exit;
}

// Get student details by user_id (for edit modal)
if (isset($_GET['get_student_by_user'])) {
    $user_id = $_GET['user_id'] ?? 0;
    
    $query = "SELECT s.*, u.username, u.email, u.status, u.user_id,
              p.parent_id, p.first_name as parent_fname, p.last_name as parent_lname, 
              p.phone as parent_phone, p.email as parent_email, p.relationship,
              (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = s.user_id) as has_face_data
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              LEFT JOIN parents p ON s.parent_id = p.parent_id
              WHERE s.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($student);
    exit;
}

// Get student attendance data
if (isset($_GET['get_attendance'])) {
    $student_id = $_GET['student_id'] ?? 0;
    $month = $_GET['month'] ?? date('Y-m');
    
    // Get student info
    $query = "SELECT s.*, u.username, u.email 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.student_id = :student_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    // Get attendance records for the month
    $query = "SELECT a.*, 
              CASE WHEN a.verification_method IS NOT NULL THEN a.verification_method ELSE 'manual' END as method
              FROM attendance a
              WHERE a.student_id = :student_id 
              AND DATE_FORMAT(a.date, '%Y-%m') = :month
              ORDER BY a.date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':month', $month);
    $stmt->execute();
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly statistics
    $stats_query = "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
        FROM attendance 
        WHERE student_id = :student_id 
        AND DATE_FORMAT(date, '%Y-%m') = :month";
    $stmt = $conn->prepare($stats_query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':month', $month);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get overall statistics (all time)
    $overall_query = "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance 
        WHERE student_id = :student_id";
    $stmt = $conn->prepare($overall_query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate attendance percentage
    $total = $overall['total_days'] ?: 1;
    $percentage = round((($overall['present'] + $overall['late']) / $total) * 100, 1);
    
    echo json_encode([
        'student' => $student,
        'attendance' => $attendance,
        'stats' => $stats,
        'overall' => $overall,
        'percentage' => $percentage
    ]);
    exit;
}

// Delete face recognition data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_face_data') {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        
        // Delete face recognition data
        $query = "DELETE FROM face_recognition_data WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Face data deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting face data: ' . $e->getMessage()]);
    }
    exit;
}

// Update student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
    try {
        $conn->beginTransaction();
        
        $student_id = $_POST['student_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        
        // Update users table
        $query = "UPDATE users SET 
                  email = :email,
                  status = :status
                  WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':status', $_POST['status']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Update students table
        $query = "UPDATE students SET 
                  first_name = :first_name,
                  last_name = :last_name,
                  date_of_birth = :date_of_birth,
                  gender = :gender,
                  grade = :grade,
                  class_section = :class_section,
                  phone = :phone,
                  address = :address,
                  blood_group = :blood_group
                  WHERE student_id = :student_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':first_name', $_POST['first_name']);
        $stmt->bindParam(':last_name', $_POST['last_name']);
        $stmt->bindParam(':date_of_birth', $_POST['date_of_birth']);
        $stmt->bindParam(':gender', $_POST['gender']);
        $stmt->bindParam(':grade', $_POST['grade']);
        $stmt->bindParam(':class_section', $_POST['class_section']);
        $stmt->bindParam(':phone', $_POST['phone']);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':blood_group', $_POST['blood_group']);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating student: ' . $e->getMessage()]);
    }
    exit;
}

// Delete student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        
        // Delete user (cascade will handle related records)
        $query = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting student: ' . $e->getMessage()]);
    }
    exit;
}
?>
