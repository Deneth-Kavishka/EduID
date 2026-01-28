<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

// Get face data for students in a class
if (isset($_GET['action']) && $_GET['action'] === 'get_face_data') {
    $grade = trim($_GET['grade'] ?? '');
    $section = trim($_GET['section'] ?? '');
    
    $where = "TRIM(s.grade) = :grade AND u.status = 'active' AND f.is_active = 1";
    $params = [':grade' => $grade];
    
    if ($section) {
        $where .= " AND TRIM(s.class_section) = :section";
        $params[':section'] = $section;
    }
    
    $query = "SELECT s.student_id, s.student_number, CONCAT(s.first_name, ' ', s.last_name) as student_name, 
              f.face_descriptor
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              JOIN face_recognition_data f ON u.user_id = f.user_id
              WHERE {$where}
              ORDER BY s.student_number";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $faces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Face data query for grade={$grade}, section={$section}: Found " . count($faces) . " faces");
    
    echo json_encode(['success' => true, 'faces' => $faces, 'count' => count($faces)]);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Mark attendance via face recognition
        if ($action === 'mark_face_attendance') {
            $student_id = $input['student_id'] ?? 0;
            $date = $input['date'] ?? date('Y-m-d');
            $status = 'present';
            $check_in_time = date('H:i:s');
            $verification_method = 'face_recognition';
            
            // Check if already marked
            $query = "SELECT attendance_id FROM attendance WHERE student_id = :student_id AND date = :date";
            $stmt = $conn->prepare($query);
            $stmt->execute([':student_id' => $student_id, ':date' => $date]);
            
            if ($stmt->fetch()) {
                // Already marked, update if needed
                $query = "UPDATE attendance SET status = :status, check_in_time = :check_in_time, 
                          verification_method = :verification_method 
                          WHERE student_id = :student_id AND date = :date";
            } else {
                // Insert new record
                $query = "INSERT INTO attendance (student_id, date, status, check_in_time, verification_method) 
                          VALUES (:student_id, :date, :status, :check_in_time, :verification_method)";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':student_id' => $student_id,
                ':date' => $date,
                ':status' => $status,
                ':check_in_time' => $check_in_time,
                ':verification_method' => $verification_method
            ]);
            
            // Log access
            $query = "INSERT INTO access_logs (user_id, access_type, status, remarks) 
                      SELECT user_id, 'face_verify', 'success', 'Attendance marked via face recognition'
                      FROM students WHERE student_id = :student_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':student_id' => $student_id]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Attendance marked successfully',
                'time' => date('h:i A')
            ]);
            exit;
        }
        
        throw new Exception('Invalid action');
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
