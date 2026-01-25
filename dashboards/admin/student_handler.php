<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Get student details
if (isset($_GET['get_student'])) {
    $student_id = $_GET['student_id'] ?? 0;
    
    $query = "SELECT s.*, u.username, u.email, u.status,
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
    
    header('Content-Type: application/json');
    echo json_encode($student);
    exit;
}
?>
