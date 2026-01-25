<?php
require_once '../../config/config.php';
checkRole(['student']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Get face descriptor from POST
    $face_descriptor = $_POST['face_descriptor'] ?? null;
    
    if (!$face_descriptor) {
        echo json_encode(['success' => false, 'message' => 'No face data received']);
        exit;
    }
    
    // Handle uploaded image
    $image_path = null;
    if (isset($_FILES['face_image']) && $_FILES['face_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = FACE_DATA_PATH;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'face_' . $user_id . '_' . time() . '.jpg';
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['face_image']['tmp_name'], $filepath)) {
            $image_path = $filename;
        }
    }
    
    // Deactivate old face data
    $query = "UPDATE face_recognition_data SET is_active = 0 WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    // Insert new face data
    $query = "INSERT INTO face_recognition_data (user_id, face_descriptor, image_path, is_active) 
              VALUES (:user_id, :face_descriptor, :image_path, 1)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':face_descriptor', $face_descriptor);
    $stmt->bindParam(':image_path', $image_path);
    $stmt->execute();
    
    // Update student record
    if (isset($_SESSION['student_id'])) {
        $updateQuery = "UPDATE students SET face_encoding = :face_descriptor WHERE student_id = :student_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':face_descriptor', $face_descriptor);
        $updateStmt->bindParam(':student_id', $_SESSION['student_id']);
        $updateStmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Face registered successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
