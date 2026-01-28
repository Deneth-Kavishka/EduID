<?php
require_once '../../config/config.php';
checkRole(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_POST['user_id'] ?? null;
    $face_image = $_POST['face_image'] ?? null;
    $face_descriptor = $_POST['face_descriptor'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    if (!$face_image) {
        echo json_encode(['success' => false, 'message' => 'No face image received']);
        exit;
    }
    
    if (!$face_descriptor) {
        echo json_encode(['success' => false, 'message' => 'No face descriptor received. Please ensure face is detected properly.']);
        exit;
    }
    
    // Validate face descriptor is a valid JSON array
    $descriptor_array = json_decode($face_descriptor, true);
    if (!is_array($descriptor_array) || count($descriptor_array) < 128) {
        echo json_encode(['success' => false, 'message' => 'Invalid face descriptor format']);
        exit;
    }
    
    // Verify user exists and is a student
    $query = "SELECT u.user_id, s.student_id FROM users u 
              JOIN students s ON u.user_id = s.user_id 
              WHERE u.user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Process base64 image
    $image_path = null;
    if (strpos($face_image, 'data:image') === 0) {
        $upload_dir = '../../uploads/face_data/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Extract base64 data
        $image_parts = explode(',', $face_image);
        $image_data = base64_decode($image_parts[1]);
        
        $filename = 'face_' . $user_id . '_' . time() . '.jpg';
        $filepath = $upload_dir . $filename;
        
        if (file_put_contents($filepath, $image_data)) {
            $image_path = $filename;
        }
    }
    
    // Deactivate old face data
    $query = "UPDATE face_recognition_data SET is_active = 0 WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    // Insert new face data with actual face descriptor from face-api.js
    $query = "INSERT INTO face_recognition_data (user_id, face_descriptor, image_path, is_active, created_at) 
              VALUES (:user_id, :face_descriptor, :image_path, 1, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':face_descriptor', $face_descriptor);
    $stmt->bindParam(':image_path', $image_path);
    $stmt->execute();
    
    // Also update face_encoding in students table for quick reference
    $query = "UPDATE students SET face_encoding = :face_encoding WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':face_encoding', $face_descriptor);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Face data saved successfully',
        'image_path' => $image_path,
        'descriptor_length' => count($descriptor_array)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
