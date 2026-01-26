-- =============================================
-- EduID - Educational Identity Verification System
-- Database Schema
-- =============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS eduid_system;
USE eduid_system;

-- =============================================
-- Users Table (Main authentication table)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_role ENUM('admin', 'student', 'teacher', 'parent') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    created_by INT,
    INDEX idx_user_role (user_role),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Parents Table (Created before Students - required for FK)
-- =============================================
CREATE TABLE IF NOT EXISTS parents (
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    alternative_phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    occupation VARCHAR(50),
    relationship ENUM('father', 'mother', 'guardian') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Teachers Table
-- =============================================
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    employee_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address TEXT,
    department VARCHAR(50),
    subject VARCHAR(50),
    qualification VARCHAR(100),
    joining_date DATE NOT NULL,
    face_encoding TEXT,
    qr_code VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_employee_number (employee_number),
    INDEX idx_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Students Table
-- =============================================
CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    student_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    grade VARCHAR(10),
    class_section VARCHAR(10),
    enrollment_date DATE NOT NULL,
    qr_code VARCHAR(255),
    face_encoding TEXT,
    emergency_contact VARCHAR(15),
    blood_group VARCHAR(5),
    parent_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(parent_id) ON DELETE SET NULL,
    INDEX idx_student_number (student_number),
    INDEX idx_grade_class (grade, class_section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Attendance Table
-- =============================================
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    verification_method ENUM('qr_code', 'face_recognition', 'manual') NOT NULL,
    verified_by INT,
    location VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_date (student_id, date),
    INDEX idx_date (date),
    INDEX idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Exam Entry Table
-- =============================================
CREATE TABLE IF NOT EXISTS exam_entries (
    entry_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_name VARCHAR(100) NOT NULL,
    exam_date DATE NOT NULL,
    exam_hall VARCHAR(50),
    seat_number VARCHAR(20),
    entry_time TIMESTAMP,
    verification_method ENUM('qr_code', 'face_recognition', 'manual') NOT NULL,
    verified_by INT,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_exam_date (exam_date),
    INDEX idx_student_exam (student_id, exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Institute Holidays Table
-- =============================================
CREATE TABLE IF NOT EXISTS institute_holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    holiday_type ENUM('public_holiday', 'institute_holiday', 'emergency', 'other') DEFAULT 'institute_holiday',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Events Table
-- =============================================
CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_type VARCHAR(50),
    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    venue VARCHAR(100),
    description TEXT,
    max_participants INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Event Registrations Table
-- =============================================
CREATE TABLE IF NOT EXISTS event_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attendance_status ENUM('registered', 'attended', 'absent') DEFAULT 'registered',
    check_in_time TIMESTAMP NULL,
    verification_method ENUM('qr_code', 'face_recognition', 'manual'),
    verified_by INT,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_event_student (event_id, student_id),
    INDEX idx_event (event_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Access Logs Table
-- =============================================
CREATE TABLE IF NOT EXISTS access_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_type ENUM('login', 'logout', 'qr_scan', 'face_verify', 'manual_entry') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location VARCHAR(100),
    status ENUM('success', 'failed', 'blocked') NOT NULL,
    remarks TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_access_time (access_time),
    INDEX idx_user_time (user_id, access_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Face Recognition Data Table
-- =============================================
CREATE TABLE IF NOT EXISTS face_recognition_data (
    face_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    face_descriptor TEXT NOT NULL,
    image_path VARCHAR(255),
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Notifications Table
-- =============================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- System Settings Table
-- =============================================
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Insert Default Admin User
-- =============================================
-- Password: Admin@123 (hashed using PHP password_hash)
INSERT INTO users (username, email, password_hash, user_role, status) 
VALUES ('admin', 'admin@eduid.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- =============================================
-- Insert Default System Settings
-- =============================================
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'EduID', 'System Name'),
('allow_face_recognition', '1', 'Enable Face Recognition Feature'),
('allow_qr_scanning', '1', 'Enable QR Code Scanning Feature'),
('attendance_grace_period', '15', 'Grace period in minutes for late attendance'),
('session_timeout', '30', 'Session timeout in minutes'),
('default_theme', 'light', 'Default theme mode (light/dark)');

-- =============================================
-- Views for Reporting
-- =============================================

-- Student Attendance Summary View
CREATE OR REPLACE VIEW v_student_attendance_summary AS
SELECT 
    s.student_id,
    s.student_number,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    s.grade,
    s.class_section,
    COUNT(a.attendance_id) AS total_days,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_days,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / 
           NULLIF(COUNT(a.attendance_id), 0)), 2) AS attendance_percentage
FROM students s
LEFT JOIN attendance a ON s.student_id = a.student_id
GROUP BY s.student_id, s.student_number, s.first_name, s.last_name, s.grade, s.class_section;

-- Daily Attendance Report View
CREATE OR REPLACE VIEW v_daily_attendance_report AS
SELECT 
    a.date,
    s.grade,
    s.class_section,
    COUNT(DISTINCT s.student_id) AS total_students,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / 
           COUNT(DISTINCT s.student_id)), 2) AS attendance_rate
FROM attendance a
JOIN students s ON a.student_id = s.student_id
GROUP BY a.date, s.grade, s.class_section;
