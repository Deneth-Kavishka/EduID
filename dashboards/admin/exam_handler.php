<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Get single hall
    if ($action === 'get_hall') {
        $hall_id = $_GET['id'] ?? 0;
        $query = "SELECT * FROM exam_halls WHERE hall_id = :hall_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':hall_id' => $hall_id]);
        $hall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'hall' => $hall]);
        exit;
    }
    
    // Get all halls
    if ($action === 'get_halls') {
        $query = "SELECT * FROM exam_halls WHERE status = 'active' ORDER BY hall_name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $halls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'halls' => $halls]);
        exit;
    }
    
    // Get single exam
    if ($action === 'get_exam') {
        $exam_id = $_GET['id'] ?? 0;
        $query = "SELECT e.*, h.hall_name FROM exams e 
                  LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
                  WHERE e.exam_id = :exam_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':exam_id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'exam' => $exam]);
        exit;
    }
    
    // Get exams for a grade
    if ($action === 'get_exams_by_grade') {
        $grade = $_GET['grade'] ?? '';
        $query = "SELECT e.*, h.hall_name FROM exams e 
                  LEFT JOIN exam_halls h ON e.hall_id = h.hall_id 
                  WHERE e.grade = :grade 
                  ORDER BY e.exam_date DESC, e.start_time";
        $stmt = $conn->prepare($query);
        $stmt->execute([':grade' => $grade]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'exams' => $exams]);
        exit;
    }
    
    // Get exam eligibility for a student
    if ($action === 'check_eligibility') {
        $student_id = $_GET['student_id'] ?? 0;
        $exam_id = $_GET['exam_id'] ?? 0;
        
        // Get exam requirements
        $query = "SELECT min_attendance_percent FROM exams WHERE exam_id = :exam_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':exam_id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }
        
        // Calculate student attendance
        $query = "SELECT 
                    COUNT(a.attendance_id) as total_classes,
                    SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended
                  FROM attendance a
                  WHERE a.student_id = :student_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':student_id' => $student_id]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $percentage = $attendance['total_classes'] > 0 
            ? round(($attendance['attended'] / $attendance['total_classes']) * 100, 2) 
            : 0;
        
        $is_eligible = $percentage >= $exam['min_attendance_percent'];
        
        echo json_encode([
            'success' => true,
            'is_eligible' => $is_eligible,
            'attendance_percentage' => $percentage,
            'required_percentage' => $exam['min_attendance_percent'],
            'total_classes' => $attendance['total_classes'],
            'attended_classes' => $attendance['attended']
        ]);
        exit;
    }
    
    // Get students with eligibility for an exam
    if ($action === 'get_exam_students') {
        $exam_id = $_GET['exam_id'] ?? 0;
        
        // Get exam details
        $query = "SELECT * FROM exams WHERE exam_id = :exam_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':exam_id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }
        
        // Get students with attendance stats
        $where = "s.grade = :grade AND u.status = 'active'";
        $params = [':grade' => $exam['grade'], ':exam_id' => $exam_id];
        
        if ($exam['class_section']) {
            $where .= " AND s.class_section = :section";
            $params[':section'] = $exam['class_section'];
        }
        
        $query = "SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                  COUNT(a.attendance_id) as total_classes,
                  SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_classes,
                  ROUND(
                      COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) * 100.0 / 
                      NULLIF(COUNT(a.attendance_id), 0), 0), 2
                  ) as attendance_percentage,
                  esa.assignment_id, esa.seat_number, esa.is_eligible,
                  ea.status as exam_status
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN attendance a ON s.student_id = a.student_id
                  LEFT JOIN exam_seat_assignments esa ON s.student_id = esa.student_id AND esa.exam_id = :exam_id
                  LEFT JOIN exam_attendance ea ON s.student_id = ea.student_id AND ea.exam_id = :exam_id
                  WHERE {$where}
                  GROUP BY s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
                           esa.assignment_id, esa.seat_number, esa.is_eligible, ea.status
                  ORDER BY s.student_number";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate eligibility
        foreach ($students as &$student) {
            $student['calculated_eligible'] = $student['attendance_percentage'] >= $exam['min_attendance_percent'];
        }
        
        echo json_encode(['success' => true, 'students' => $students, 'exam' => $exam]);
        exit;
    }
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Add exam hall
        if ($action === 'add_hall') {
            $query = "INSERT INTO exam_halls (hall_code, hall_name, location, capacity, description, has_cctv, has_ac, status)
                      VALUES (:hall_code, :hall_name, :location, :capacity, :description, :has_cctv, :has_ac, :status)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':hall_code' => $input['hall_code'],
                ':hall_name' => $input['hall_name'],
                ':location' => $input['location'] ?? null,
                ':capacity' => $input['capacity'],
                ':description' => $input['description'] ?? null,
                ':has_cctv' => $input['has_cctv'] ? 1 : 0,
                ':has_ac' => $input['has_ac'] ? 1 : 0,
                ':status' => $input['status'] ?? 'active'
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Hall added successfully']);
            exit;
        }
        
        // Update exam hall
        if ($action === 'update_hall') {
            $query = "UPDATE exam_halls SET 
                      hall_code = :hall_code, hall_name = :hall_name, location = :location,
                      capacity = :capacity, description = :description, has_cctv = :has_cctv,
                      has_ac = :has_ac, status = :status
                      WHERE hall_id = :hall_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':hall_id' => $input['hall_id'],
                ':hall_code' => $input['hall_code'],
                ':hall_name' => $input['hall_name'],
                ':location' => $input['location'] ?? null,
                ':capacity' => $input['capacity'],
                ':description' => $input['description'] ?? null,
                ':has_cctv' => $input['has_cctv'] ? 1 : 0,
                ':has_ac' => $input['has_ac'] ? 1 : 0,
                ':status' => $input['status'] ?? 'active'
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Hall updated successfully']);
            exit;
        }
        
        // Delete exam hall
        if ($action === 'delete_hall') {
            $query = "DELETE FROM exam_halls WHERE hall_id = :hall_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':hall_id' => $input['hall_id']]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Hall deleted successfully']);
            exit;
        }
        
        // Add exam
        if ($action === 'add_exam') {
            $query = "INSERT INTO exams (exam_code, exam_name, exam_type, subject, grade, class_section,
                      exam_date, start_time, end_time, duration_minutes, total_marks, passing_marks,
                      min_attendance_percent, hall_id, instructions, created_by)
                      VALUES (:exam_code, :exam_name, :exam_type, :subject, :grade, :class_section,
                      :exam_date, :start_time, :end_time, :duration_minutes, :total_marks, :passing_marks,
                      :min_attendance_percent, :hall_id, :instructions, :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':exam_code' => $input['exam_code'],
                ':exam_name' => $input['exam_name'],
                ':exam_type' => $input['exam_type'],
                ':subject' => $input['subject'],
                ':grade' => $input['grade'],
                ':class_section' => $input['class_section'] ?? null,
                ':exam_date' => $input['exam_date'],
                ':start_time' => $input['start_time'],
                ':end_time' => $input['end_time'],
                ':duration_minutes' => $input['duration_minutes'] ?? 60,
                ':total_marks' => $input['total_marks'] ?? 100,
                ':passing_marks' => $input['passing_marks'] ?? 40,
                ':min_attendance_percent' => $input['min_attendance_percent'] ?? 75,
                ':hall_id' => $input['hall_id'] ?: null,
                ':instructions' => $input['instructions'] ?? null,
                ':created_by' => $_SESSION['user_id']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Exam added successfully', 'exam_id' => $conn->lastInsertId()]);
            exit;
        }
        
        // Update exam
        if ($action === 'update_exam') {
            $query = "UPDATE exams SET 
                      exam_code = :exam_code, exam_name = :exam_name, exam_type = :exam_type,
                      subject = :subject, grade = :grade, class_section = :class_section,
                      exam_date = :exam_date, start_time = :start_time, end_time = :end_time,
                      duration_minutes = :duration_minutes, total_marks = :total_marks,
                      passing_marks = :passing_marks, min_attendance_percent = :min_attendance_percent,
                      hall_id = :hall_id, instructions = :instructions, status = :status
                      WHERE exam_id = :exam_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':exam_id' => $input['exam_id'],
                ':exam_code' => $input['exam_code'],
                ':exam_name' => $input['exam_name'],
                ':exam_type' => $input['exam_type'],
                ':subject' => $input['subject'],
                ':grade' => $input['grade'],
                ':class_section' => $input['class_section'] ?? null,
                ':exam_date' => $input['exam_date'],
                ':start_time' => $input['start_time'],
                ':end_time' => $input['end_time'],
                ':duration_minutes' => $input['duration_minutes'] ?? 60,
                ':total_marks' => $input['total_marks'] ?? 100,
                ':passing_marks' => $input['passing_marks'] ?? 40,
                ':min_attendance_percent' => $input['min_attendance_percent'] ?? 75,
                ':hall_id' => $input['hall_id'] ?: null,
                ':instructions' => $input['instructions'] ?? null,
                ':status' => $input['status'] ?? 'scheduled'
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Exam updated successfully']);
            exit;
        }
        
        // Delete exam
        if ($action === 'delete_exam') {
            $query = "DELETE FROM exams WHERE exam_id = :exam_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':exam_id' => $input['exam_id']]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Exam deleted successfully']);
            exit;
        }
        
        // Assign students to exam
        if ($action === 'assign_students') {
            $exam_id = $input['exam_id'];
            $hall_id = $input['hall_id'];
            $students = $input['students']; // Array of {student_id, seat_number, is_eligible}
            
            $query = "INSERT INTO exam_seat_assignments (exam_id, student_id, hall_id, seat_number, is_eligible, attendance_percentage)
                      VALUES (:exam_id, :student_id, :hall_id, :seat_number, :is_eligible, :attendance_percentage)
                      ON DUPLICATE KEY UPDATE 
                      hall_id = :hall_id, seat_number = :seat_number, is_eligible = :is_eligible, attendance_percentage = :attendance_percentage";
            $stmt = $conn->prepare($query);
            
            foreach ($students as $student) {
                $stmt->execute([
                    ':exam_id' => $exam_id,
                    ':student_id' => $student['student_id'],
                    ':hall_id' => $hall_id,
                    ':seat_number' => $student['seat_number'] ?? null,
                    ':is_eligible' => $student['is_eligible'] ? 1 : 0,
                    ':attendance_percentage' => $student['attendance_percentage'] ?? 0
                ]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Students assigned successfully']);
            exit;
        }
        
        // Mark exam attendance
        if ($action === 'mark_exam_attendance') {
            $exam_id = $input['exam_id'];
            $student_id = $input['student_id'];
            $status = $input['status'] ?? 'present';
            $verification_method = $input['verification_method'] ?? 'manual';
            
            // Get hall_id from seat assignment
            $query = "SELECT hall_id, seat_number FROM exam_seat_assignments WHERE exam_id = :exam_id AND student_id = :student_id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':exam_id' => $exam_id, ':student_id' => $student_id]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $hall_id = $assignment['hall_id'] ?? null;
            $seat_number = $assignment['seat_number'] ?? null;
            
            $query = "INSERT INTO exam_attendance (exam_id, student_id, hall_id, seat_number, entry_time, status, verification_method, verified_by)
                      VALUES (:exam_id, :student_id, :hall_id, :seat_number, :entry_time, :status, :verification_method, :verified_by)
                      ON DUPLICATE KEY UPDATE 
                      entry_time = :entry_time, status = :status, verification_method = :verification_method, verified_by = :verified_by";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':exam_id' => $exam_id,
                ':student_id' => $student_id,
                ':hall_id' => $hall_id,
                ':seat_number' => $seat_number,
                ':entry_time' => date('H:i:s'),
                ':status' => $status,
                ':verification_method' => $verification_method,
                ':verified_by' => $_SESSION['user_id']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Exam attendance marked']);
            exit;
        }
        
        // Bulk mark exam attendance
        if ($action === 'bulk_exam_attendance') {
            $exam_id = $input['exam_id'];
            $attendance_data = $input['attendance']; // {student_id: status}
            
            foreach ($attendance_data as $student_id => $status) {
                // Get hall_id from seat assignment
                $query = "SELECT hall_id, seat_number FROM exam_seat_assignments WHERE exam_id = :exam_id AND student_id = :student_id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':exam_id' => $exam_id, ':student_id' => $student_id]);
                $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $query = "INSERT INTO exam_attendance (exam_id, student_id, hall_id, seat_number, entry_time, status, verification_method, verified_by)
                          VALUES (:exam_id, :student_id, :hall_id, :seat_number, :entry_time, :status, 'manual', :verified_by)
                          ON DUPLICATE KEY UPDATE 
                          entry_time = :entry_time, status = :status, verified_by = :verified_by";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':exam_id' => $exam_id,
                    ':student_id' => $student_id,
                    ':hall_id' => $assignment['hall_id'] ?? null,
                    ':seat_number' => $assignment['seat_number'] ?? null,
                    ':entry_time' => $status === 'present' ? date('H:i:s') : null,
                    ':status' => $status,
                    ':verified_by' => $_SESSION['user_id']
                ]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Exam attendance saved']);
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
