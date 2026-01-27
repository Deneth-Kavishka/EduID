<?php
/**
 * Mark Attendance Handler
 * Handles manual attendance marking, clearing, and remark updates
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
    
    $action = $_POST['action'] ?? 'mark';
    $date = $_POST['date'] ?? date('Y-m-d');
    $student_id = $_POST['student_id'] ?? null;
    
    switch ($action) {
        case 'clear':
            // Clear attendance for a single student
            if (!$student_id) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            
            $query = "DELETE FROM attendance WHERE student_id = :student_id AND date = :date";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Attendance cleared']);
            break;
            
        case 'clear_all':
            // Clear all attendance for the date (within filtered scope if applicable)
            $query = "DELETE FROM attendance WHERE date = :date AND verified_by = :verified_by";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':verified_by', $_SESSION['user_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'All attendance cleared', 'deleted' => $stmt->rowCount()]);
            break;
            
        case 'add_remark':
            // Add/update remark for attendance
            if (!$student_id) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            
            $remark = $_POST['remark'] ?? '';
            
            // Check if attendance exists
            $query = "SELECT attendance_id FROM attendance WHERE student_id = :student_id AND date = :date";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update remark
                $query = "UPDATE attendance SET remarks = :remarks WHERE attendance_id = :attendance_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':remarks', $remark);
                $stmt->bindParam(':attendance_id', $existing['attendance_id']);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Remark updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Please mark attendance first before adding a remark']);
            }
            break;
            
        case 'mark':
        default:
            // Mark attendance
            if (!$student_id) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            
            $status = $_POST['status'] ?? 'present';
            $valid_statuses = ['present', 'absent', 'late', 'excused'];
            
            if (!in_array($status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            // Check if attendance already exists
            $query = "SELECT attendance_id FROM attendance WHERE student_id = :student_id AND date = :date";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_time = date('H:i:s');
            
            if ($existing) {
                // Update existing attendance
                $query = "UPDATE attendance SET status = :status, verification_method = 'manual', verified_by = :verified_by WHERE attendance_id = :attendance_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':verified_by', $_SESSION['user_id']);
                $stmt->bindParam(':attendance_id', $existing['attendance_id']);
                $stmt->execute();
                
                $attendance_id = $existing['attendance_id'];
            } else {
                // Insert new attendance
                $query = "INSERT INTO attendance (student_id, date, status, check_in_time, verification_method, verified_by, created_at) 
                          VALUES (:student_id, :date, :status, :check_in_time, 'manual', :verified_by, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':date', $date);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':check_in_time', $current_time);
                $stmt->bindParam(':verified_by', $_SESSION['user_id']);
                $stmt->execute();
                
                $attendance_id = $conn->lastInsertId();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked',
                'attendance_id' => $attendance_id,
                'status' => $status,
                'check_in_time' => date('h:i A', strtotime($current_time))
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log('Mark attendance error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
