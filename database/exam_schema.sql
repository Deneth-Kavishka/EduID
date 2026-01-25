-- =============================================
-- EduID - Exam Management Schema Extension
-- =============================================

USE eduid_system;

-- =============================================
-- Exam Halls Table (Different from Class Halls)
-- =============================================
CREATE TABLE IF NOT EXISTS exam_halls (
    hall_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_name VARCHAR(50) NOT NULL,
    hall_code VARCHAR(20) UNIQUE NOT NULL,
    capacity INT NOT NULL DEFAULT 30,
    location VARCHAR(100),
    description TEXT,
    has_cctv BOOLEAN DEFAULT FALSE,
    has_ac BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hall_code (hall_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Exams Table
-- =============================================
CREATE TABLE IF NOT EXISTS exams (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(100) NOT NULL,
    exam_code VARCHAR(30) UNIQUE NOT NULL,
    exam_type ENUM('midterm', 'final', 'quiz', 'practical', 'assignment') NOT NULL,
    subject VARCHAR(100) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    class_section VARCHAR(10),
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 60,
    total_marks INT NOT NULL DEFAULT 100,
    passing_marks INT NOT NULL DEFAULT 40,
    min_attendance_percent DECIMAL(5,2) DEFAULT 75.00,
    hall_id INT,
    instructions TEXT,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES exam_halls(hall_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_exam_date (exam_date),
    INDEX idx_grade (grade),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Exam Seat Assignments Table
-- =============================================
CREATE TABLE IF NOT EXISTS exam_seat_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    hall_id INT NOT NULL,
    seat_number VARCHAR(20),
    is_eligible BOOLEAN DEFAULT TRUE,
    eligibility_reason VARCHAR(255),
    attendance_percentage DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (hall_id) REFERENCES exam_halls(hall_id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_student (exam_id, student_id),
    INDEX idx_exam_hall (exam_id, hall_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Exam Attendance Table (Separate from Class Attendance)
-- =============================================
CREATE TABLE IF NOT EXISTS exam_attendance (
    exam_attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    hall_id INT NOT NULL,
    seat_number VARCHAR(20),
    entry_time TIME,
    exit_time TIME,
    status ENUM('present', 'absent', 'disqualified', 'late') NOT NULL DEFAULT 'absent',
    verification_method ENUM('qr_code', 'face_recognition', 'manual') NOT NULL,
    verified_by INT,
    face_verified BOOLEAN DEFAULT FALSE,
    id_verified BOOLEAN DEFAULT FALSE,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (hall_id) REFERENCES exam_halls(hall_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_exam_student_attendance (exam_id, student_id),
    INDEX idx_exam_status (exam_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Insert Sample Exam Halls
-- =============================================
INSERT INTO exam_halls (hall_name, hall_code, capacity, location, has_cctv, has_ac) VALUES
('Examination Hall A', 'HALL-A', 50, 'Main Building - Ground Floor', TRUE, TRUE),
('Examination Hall B', 'HALL-B', 40, 'Main Building - First Floor', TRUE, TRUE),
('Examination Hall C', 'HALL-C', 30, 'Science Block - Ground Floor', TRUE, FALSE),
('Computer Lab 1', 'COMP-LAB-1', 25, 'IT Block - Second Floor', TRUE, TRUE),
('Auditorium', 'AUDITORIUM', 200, 'Administrative Block', TRUE, TRUE)
ON DUPLICATE KEY UPDATE hall_name = hall_name;

-- =============================================
-- View: Student Exam Eligibility
-- =============================================
CREATE OR REPLACE VIEW v_student_exam_eligibility AS
SELECT 
    s.student_id,
    s.student_number,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    s.grade,
    s.class_section,
    COUNT(a.attendance_id) AS total_classes,
    SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) AS attended_classes,
    ROUND(
        (SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) * 100.0 / 
        NULLIF(COUNT(a.attendance_id), 0)), 2
    ) AS attendance_percentage
FROM students s
LEFT JOIN attendance a ON s.student_id = a.student_id
GROUP BY s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section;

-- =============================================
-- View: Exam Attendance Summary
-- =============================================
CREATE OR REPLACE VIEW v_exam_attendance_summary AS
SELECT 
    e.exam_id,
    e.exam_name,
    e.exam_code,
    e.subject,
    e.grade,
    e.class_section,
    e.exam_date,
    e.hall_id,
    h.hall_name,
    COUNT(DISTINCT esa.student_id) AS total_assigned,
    SUM(CASE WHEN esa.is_eligible = TRUE THEN 1 ELSE 0 END) AS eligible_count,
    SUM(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN ea.status = 'absent' THEN 1 ELSE 0 END) AS absent_count
FROM exams e
LEFT JOIN exam_halls h ON e.hall_id = h.hall_id
LEFT JOIN exam_seat_assignments esa ON e.exam_id = esa.exam_id
LEFT JOIN exam_attendance ea ON e.exam_id = ea.exam_id AND esa.student_id = ea.student_id
GROUP BY e.exam_id, e.exam_name, e.exam_code, e.subject, e.grade, e.class_section, e.exam_date, e.hall_id, h.hall_name;
