<?php
require_once '../../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$teacher_id = $_SESSION['teacher_id'];
$user_id = $_SESSION['user_id'];

$action = $_POST['action'] ?? 'verify';

try {
    if ($action === 'add') {
        // Add single student to exam by student_id
        $student_id = $_POST['student_id'] ?? null;
        $student_number = $_POST['student_number'] ?? '';
        $exam_name = $_POST['exam_name'] ?? '';
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        
        if (empty($exam_name)) {
            echo json_encode(['success' => false, 'message' => 'Exam name is required']);
            exit;
        }
        
        // Find student by student_id or student_number
        if ($student_id) {
            $query = "SELECT student_id, first_name, last_name FROM students WHERE student_id = :student_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
        } else if ($student_number) {
            $query = "SELECT student_id, first_name, last_name FROM students WHERE student_number = :student_number";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_number', $student_number);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student ID or number is required']);
            exit;
        }
        
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Check if entry already exists
        $query = "SELECT entry_id FROM exam_entries 
                  WHERE student_id = :student_id AND exam_name = :exam_name AND exam_date = :exam_date";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student['student_id']);
        $stmt->bindParam(':exam_name', $exam_name);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Already enrolled']);
            exit;
        }
        
        // Insert new entry
        $query = "INSERT INTO exam_entries (student_id, exam_name, exam_date, verification_status) 
                  VALUES (:student_id, :exam_name, :exam_date, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student['student_id']);
        $stmt->bindParam(':exam_name', $exam_name);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => $student['first_name'] . ' added to exam',
            'entry_id' => $conn->lastInsertId()
        ]);
        
    } else if ($action === 'remove') {
        // Remove single student from exam
        $student_id = $_POST['student_id'] ?? null;
        $exam_name = $_POST['exam_name'] ?? '';
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        
        if (!$student_id || empty($exam_name)) {
            echo json_encode(['success' => false, 'message' => 'Student ID and exam name are required']);
            exit;
        }
        
        // Get student name first
        $query = "SELECT first_name, last_name FROM students WHERE student_id = :student_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete entry
        $query = "DELETE FROM exam_entries 
                  WHERE student_id = :student_id AND exam_name = :exam_name AND exam_date = :exam_date";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':exam_name', $exam_name);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->execute();
        
        $name = $student ? $student['first_name'] : 'Student';
        echo json_encode([
            'success' => true, 
            'message' => $name . ' removed from exam'
        ]);
        
    } else if ($action === 'add_all') {
        // Add multiple students to exam
        $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
        $exam_name = $_POST['exam_name'] ?? '';
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        
        if (empty($student_ids) || empty($exam_name)) {
            echo json_encode(['success' => false, 'message' => 'Student IDs and exam name are required']);
            exit;
        }
        
        $added = 0;
        foreach ($student_ids as $student_id) {
            // Check if entry exists
            $query = "SELECT entry_id FROM exam_entries 
                      WHERE student_id = :student_id AND exam_name = :exam_name AND exam_date = :exam_date";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':exam_name', $exam_name);
            $stmt->bindParam(':exam_date', $exam_date);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                // Insert new entry
                $query = "INSERT INTO exam_entries (student_id, exam_name, exam_date, verification_status) 
                          VALUES (:student_id, :exam_name, :exam_date, 'pending')";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':exam_name', $exam_name);
                $stmt->bindParam(':exam_date', $exam_date);
                $stmt->execute();
                $added++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $added . ' student(s) added to exam'
        ]);
        
    } else if ($action === 'remove_all') {
        // Remove multiple students from exam
        $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
        $exam_name = $_POST['exam_name'] ?? '';
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        
        if (empty($student_ids) || empty($exam_name)) {
            echo json_encode(['success' => false, 'message' => 'Student IDs and exam name are required']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $query = "DELETE FROM exam_entries 
                  WHERE student_id IN ($placeholders) AND exam_name = ? AND exam_date = ?";
        $stmt = $conn->prepare($query);
        
        $params = array_merge($student_ids, [$exam_name, $exam_date]);
        $stmt->execute($params);
        
        $removed = $stmt->rowCount();
        
        echo json_encode([
            'success' => true, 
            'message' => $removed . ' student(s) removed from exam'
        ]);
        
    } else {
        // Verify/Update status (original functionality)
        $entry_id = $_POST['entry_id'] ?? null;
        $status = $_POST['status'] ?? 'verified';
        $verification_method = $_POST['method'] ?? 'manual';
        $remarks = $_POST['remarks'] ?? '';
        
        if (!$entry_id) {
            echo json_encode(['success' => false, 'message' => 'Entry ID is required']);
            exit;
        }
        
        if (!in_array($status, ['pending', 'verified', 'rejected'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        if (!in_array($verification_method, ['qr_code', 'face_recognition', 'manual'])) {
            $verification_method = 'manual';
        }
        
        // Check if entry exists
        $query = "SELECT e.*, s.first_name, s.last_name FROM exam_entries e 
                  JOIN students s ON e.student_id = s.student_id 
                  WHERE e.entry_id = :entry_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':entry_id', $entry_id);
        $stmt->execute();
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entry) {
            echo json_encode(['success' => false, 'message' => 'Entry not found']);
            exit;
        }
        
        // Update entry
        if ($status === 'pending') {
            // Reset entry
            $query = "UPDATE exam_entries SET 
                      verification_status = 'pending',
                      verified_by = NULL,
                      verification_method = NULL,
                      entry_time = NULL,
                      remarks = NULL
                      WHERE entry_id = :entry_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':entry_id', $entry_id);
        } else {
            // Verify or reject
            $query = "UPDATE exam_entries SET 
                      verification_status = :status,
                      verified_by = :verified_by,
                      verification_method = :method,
                      entry_time = NOW(),
                      remarks = :remarks
                      WHERE entry_id = :entry_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':verified_by', $user_id);
            $stmt->bindParam(':method', $verification_method);
            $stmt->bindParam(':remarks', $remarks);
            $stmt->bindParam(':entry_id', $entry_id);
        }
        
        $stmt->execute();
        
        $status_labels = [
            'verified' => 'verified',
            'rejected' => 'rejected',
            'pending' => 'reset to pending'
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => $entry['first_name'] . ' ' . $entry['last_name'] . ' has been ' . $status_labels[$status],
            'entry' => [
                'id' => $entry_id,
                'status' => $status,
                'student_name' => $entry['first_name'] . ' ' . $entry['last_name']
            ]
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
