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

// Get student info
$query = "SELECT first_name, last_name, student_number FROM students WHERE student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Student not found</div>';
    exit;
}

// Get attendance history (last 30 days)
$query = "SELECT a.*, u.email as verified_by_email 
          FROM attendance a 
          LEFT JOIN users u ON a.verified_by = u.user_id
          WHERE a.student_id = :student_id 
          ORDER BY a.date DESC 
          LIMIT 30";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary stats
$query = "SELECT 
          COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
          COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
          COUNT(CASE WHEN status = 'excused' THEN 1 END) as excused,
          COUNT(*) as total
          FROM attendance WHERE student_id = :student_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div style="text-align: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
    <h4 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
    </h4>
    <p style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($student['student_number']); ?></p>
</div>

<!-- Summary Stats -->
<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-bottom: 1.5rem;">
    <div style="text-align: center; padding: 0.75rem 0.5rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total']; ?></div>
        <div style="font-size: 0.65rem; color: var(--text-secondary);">Total</div>
    </div>
    <div style="text-align: center; padding: 0.75rem 0.5rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #10b981;"><?php echo $stats['present']; ?></div>
        <div style="font-size: 0.65rem; color: var(--text-secondary);">Present</div>
    </div>
    <div style="text-align: center; padding: 0.75rem 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b;"><?php echo $stats['late']; ?></div>
        <div style="font-size: 0.65rem; color: var(--text-secondary);">Late</div>
    </div>
    <div style="text-align: center; padding: 0.75rem 0.5rem; background: rgba(239, 68, 68, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #ef4444;"><?php echo $stats['absent']; ?></div>
        <div style="font-size: 0.65rem; color: var(--text-secondary);">Absent</div>
    </div>
    <div style="text-align: center; padding: 0.75rem 0.5rem; background: rgba(139, 92, 246, 0.1); border-radius: 8px;">
        <div style="font-size: 1.25rem; font-weight: 700; color: #8b5cf6;"><?php echo $stats['excused']; ?></div>
        <div style="font-size: 0.65rem; color: var(--text-secondary);">Excused</div>
    </div>
</div>

<!-- Attendance History -->
<h5 style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
    <i class="fas fa-history" style="margin-right: 0.5rem;"></i>Recent Attendance (Last 30 Days)
</h5>

<?php if (empty($attendance_records)): ?>
<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
    <i class="fas fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
    <p>No attendance records found</p>
</div>
<?php else: ?>
<div style="max-height: 350px; overflow-y: auto;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--bg-secondary);">
                <th style="padding: 0.6rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Date</th>
                <th style="padding: 0.6rem 0.75rem; text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Status</th>
                <th style="padding: 0.6rem 0.75rem; text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Check-in</th>
                <th style="padding: 0.6rem 0.75rem; text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Method</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_records as $record): 
                $status_colors = [
                    'present' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'text' => '#10b981', 'icon' => 'check-circle'],
                    'late' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'text' => '#f59e0b', 'icon' => 'clock'],
                    'absent' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'text' => '#ef4444', 'icon' => 'times-circle'],
                    'excused' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'text' => '#8b5cf6', 'icon' => 'file-medical']
                ];
                $color = $status_colors[$record['status']] ?? $status_colors['absent'];
                
                $method_icons = [
                    'qr_code' => 'qrcode',
                    'face_recognition' => 'face-smile',
                    'manual' => 'hand'
                ];
            ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 0.6rem 0.75rem;">
                    <div style="font-weight: 500; color: var(--text-primary); font-size: 0.85rem;">
                        <?php echo date('D, M d', strtotime($record['date'])); ?>
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-tertiary);">
                        <?php echo date('Y', strtotime($record['date'])); ?>
                    </div>
                </td>
                <td style="padding: 0.6rem 0.75rem; text-align: center;">
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; border-radius: 6px; font-size: 0.7rem; font-weight: 600;">
                        <i class="fas fa-<?php echo $color['icon']; ?>"></i>
                        <?php echo ucfirst($record['status']); ?>
                    </span>
                </td>
                <td style="padding: 0.6rem 0.75rem; text-align: center; font-size: 0.85rem; color: var(--text-secondary);">
                    <?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?>
                </td>
                <td style="padding: 0.6rem 0.75rem; text-align: center;">
                    <span style="color: var(--text-secondary); font-size: 0.8rem;" title="<?php echo ucfirst(str_replace('_', ' ', $record['verification_method'])); ?>">
                        <i class="fas fa-<?php echo $method_icons[$record['verification_method']] ?? 'question'; ?>"></i>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
