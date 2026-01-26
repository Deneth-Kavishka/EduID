<?php
// Set JSON header first to prevent any HTML output
header('Content-Type: application/json');

// Suppress HTML error output
ini_set('display_errors', 0);
error_reporting(0);

require_once '../../config/config.php';

// Check if user is logged in and is admin - return JSON error instead of redirect
if (!isLoggedIn() || getUserRole() !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get parent details
if (isset($_GET['get_parent'])) {
    $parent_id = $_GET['parent_id'] ?? 0;
    
    $query = "SELECT p.*, u.username, u.email, u.status,
              (SELECT COUNT(*) FROM students WHERE parent_id = p.parent_id) as children_count,
              (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = p.user_id) as has_face_data
              FROM parents p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.parent_id = :parent_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':parent_id', $parent_id);
    $stmt->execute();
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($parent);
    exit;
}

// Get children of a parent
if (isset($_GET['get_children'])) {
    $parent_id = $_GET['parent_id'] ?? 0;
    
    $query = "SELECT s.*, u.email 
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              WHERE s.parent_id = :parent_id
              ORDER BY s.student_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':parent_id', $parent_id);
    $stmt->execute();
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($children);
    exit;
}
?>
