<?php
require_once '../../../config/config.php';
checkRole(['teacher']);

$db = new Database();
$conn = $db->getConnection();

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    echo '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Student not found</div>';
    exit;
}

// Get student details
$query = "SELECT s.*, u.email, u.status as account_status, u.created_at as account_created,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status = 'present') as present_days,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status = 'late') as late_days,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status = 'absent') as absent_days,
          (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id) as total_days,
          (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = s.user_id AND is_active = 1) as has_face_data,
          p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone as parent_phone
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN parents p ON s.parent_id = p.parent_id
          WHERE s.student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Student not found</div>';
    exit;
}

$attendance_rate = $student['total_days'] > 0 ? round(($student['present_days'] / $student['total_days']) * 100) : 0;
?>

<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="width: 80px; height: 80px; margin: 0 auto 1rem; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #a855f7); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.75rem;">
        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
    </div>
    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
    </h3>
    <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($student['student_number']); ?></p>
    
    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 0.75rem;">
        <?php if ($student['has_face_data']): ?>
        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.75rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
            <i class="fas fa-face-smile"></i> Face Registered
        </span>
        <?php else: ?>
        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.75rem; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
            <i class="fas fa-face-frown"></i> No Face Data
        </span>
        <?php endif; ?>
        
        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.75rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
            <i class="fas fa-graduation-cap"></i> Grade <?php echo htmlspecialchars($student['grade'] . '-' . $student['class_section']); ?>
        </span>
    </div>
</div>

<!-- Attendance Stats -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
    <div style="text-align: center; padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #10b981;"><?php echo $student['present_days']; ?></div>
        <div style="font-size: 0.7rem; color: var(--text-secondary);">Present</div>
    </div>
    <div style="text-align: center; padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b;"><?php echo $student['late_days']; ?></div>
        <div style="font-size: 0.7rem; color: var(--text-secondary);">Late</div>
    </div>
    <div style="text-align: center; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #ef4444;"><?php echo $student['absent_days']; ?></div>
        <div style="font-size: 0.7rem; color: var(--text-secondary);">Absent</div>
    </div>
    <div style="text-align: center; padding: 0.75rem; background: rgba(139, 92, 246, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #8b5cf6;"><?php echo $attendance_rate; ?>%</div>
        <div style="font-size: 0.7rem; color: var(--text-secondary);">Rate</div>
    </div>
</div>

<!-- Student Information -->
<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Email</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['email']); ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Phone</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Date of Birth</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Gender</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo ucfirst($student['gender'] ?: 'Not specified'); ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Blood Group</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['blood_group'] ?: 'Not specified'); ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Enrollment Date</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo $student['enrollment_date'] ? date('M d, Y', strtotime($student['enrollment_date'])) : 'Not provided'; ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; grid-column: span 2;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Address</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['address'] ?: 'Not provided'); ?></div>
    </div>
    
    <?php if ($student['parent_first_name']): ?>
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; grid-column: span 2;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Parent/Guardian</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);">
            <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
            <?php if ($student['parent_phone']): ?> 
                <span style="color: var(--text-secondary);">• <?php echo htmlspecialchars($student['parent_phone']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Emergency Contact</div>
        <div style="font-size: 0.9rem; color: var(--text-primary);"><?php echo htmlspecialchars($student['emergency_contact'] ?: 'Not provided'); ?></div>
    </div>
    
    <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Account Status</div>
        <div style="font-size: 0.9rem;">
            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.5rem; background: <?php echo $student['account_status'] === 'active' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $student['account_status'] === 'active' ? '#10b981' : '#ef4444'; ?>; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                <i class="fas fa-<?php echo $student['account_status'] === 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                <?php echo ucfirst($student['account_status']); ?>
            </span>
        </div>
    </div>
</div>
