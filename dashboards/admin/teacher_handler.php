<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Get teacher details
if (isset($_GET['get_teacher'])) {
    $teacher_id = $_GET['teacher_id'] ?? 0;
    
    $query = "SELECT t.*, u.username, u.email, u.status,
              (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = t.user_id) as has_face_data
              FROM teachers t
              JOIN users u ON t.user_id = u.user_id
              WHERE t.teacher_id = :teacher_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($teacher);
    exit;
}
?>
