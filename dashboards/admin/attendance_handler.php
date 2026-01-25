<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

// Get sections for a grade (for cascade filter)
if (isset($_GET['get_sections'])) {
    $grade = $_GET['grade'] ?? '';
    
    $query = "SELECT DISTINCT class_section FROM students WHERE grade = :grade ORDER BY class_section";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':grade', $grade);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($sections);
    exit;
}

// Get students by grade
if (isset($_GET['get_students_by_grade'])) {
    $grade = $_GET['grade'] ?? '';
    $section = $_GET['section'] ?? '';
    
    $where = "s.grade = :grade AND u.status = 'active'";
    $params = [':grade' => $grade];
    
    if ($section) {
        $where .= " AND s.class_section = :section";
        $params[':section'] = $section;
    }
    
    $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              WHERE {$where}
              ORDER BY s.student_number";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($students);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Mark class attendance (from modal)
        if ($action === 'mark_class_attendance') {
            $date = $input['date'] ?? date('Y-m-d');
            $attendance = $input['attendance'] ?? [];
            $verification_method = $input['verification_method'] ?? 'manual';
            
            if (empty($attendance)) {
                throw new Exception('No attendance data provided');
            }
            
            $query = "INSERT INTO attendance (student_id, date, status, check_in_time, verification_method) 
                      VALUES (:student_id, :date, :status, :check_in_time, :verification_method)
                      ON DUPLICATE KEY UPDATE status = :status, check_in_time = :check_in_time, verification_method = :verification_method";
            $stmt = $conn->prepare($query);
            
            $check_in_time = date('H:i:s');
            
            foreach ($attendance as $student_id => $status) {
                $time = ($status === 'absent') ? null : $check_in_time;
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':date' => $date,
                    ':status' => $status,
                    ':check_in_time' => $time,
                    ':verification_method' => $verification_method
                ]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            exit;
        }
        
        // Bulk mark attendance
        if ($action === 'bulk_mark') {
            $date = $input['date'] ?? date('Y-m-d');
            $status = $input['status'] ?? 'present';
            $student_ids = $input['student_ids'] ?? [];
            
            if (empty($student_ids)) {
                throw new Exception('No students selected');
            }
            
            $check_in_time = ($status === 'present' || $status === 'late') ? date('H:i:s') : null;
            
            $query = "INSERT INTO attendance (student_id, date, status, check_in_time) 
                      VALUES (:student_id, :date, :status, :check_in_time)
                      ON DUPLICATE KEY UPDATE status = :status, check_in_time = :check_in_time";
            $stmt = $conn->prepare($query);
            
            foreach ($student_ids as $student_id) {
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':date' => $date,
                    ':status' => $status,
                    ':check_in_time' => $check_in_time
                ]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            exit;
        }
        
        // Quick mark attendance
        if ($action === 'quick_mark') {
            $student_id = $input['student_id'] ?? 0;
            $date = $input['date'] ?? date('Y-m-d');
            $status = $input['status'] ?? 'present';
            
            $check_in_time = ($status === 'present' || $status === 'late') ? date('H:i:s') : null;
            
            $query = "INSERT INTO attendance (student_id, date, status, check_in_time) 
                      VALUES (:student_id, :date, :status, :check_in_time)
                      ON DUPLICATE KEY UPDATE status = :status, check_in_time = :check_in_time";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':student_id' => $student_id,
                ':date' => $date,
                ':status' => $status,
                ':check_in_time' => $check_in_time
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            exit;
        }
        
        // Edit attendance
        if ($action === 'edit_attendance') {
            $attendance_id = $input['attendance_id'] ?? 0;
            $status = $input['status'] ?? 'present';
            
            $check_in_time = ($status === 'present' || $status === 'late') ? date('H:i:s') : null;
            
            $query = "UPDATE attendance SET status = :status, check_in_time = :check_in_time WHERE attendance_id = :attendance_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':check_in_time' => $check_in_time,
                ':attendance_id' => $attendance_id
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
            exit;
        }
        
        // Delete attendance
        if ($action === 'delete_attendance') {
            $attendance_id = $input['attendance_id'] ?? 0;
            
            $query = "DELETE FROM attendance WHERE attendance_id = :attendance_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':attendance_id' => $attendance_id]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance deleted successfully']);
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
