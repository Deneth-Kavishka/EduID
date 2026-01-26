<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? '';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$report_type = $_GET['report'] ?? 'attendance';

// Get grade-wise attendance data
function getGradeAttendanceData($conn, $date_from, $date_to) {
    $query = "SELECT 
              s.grade,
              COUNT(DISTINCT a.attendance_id) as total_records,
              SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
              SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
              SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
              FROM students s 
              JOIN users u ON s.user_id = u.user_id
              LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date BETWEEN :from AND :to
              WHERE u.status = 'active'
              GROUP BY s.grade 
              ORDER BY CAST(s.grade AS UNSIGNED), s.grade";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':from', $date_from);
    $stmt->bindParam(':to', $date_to);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get student attendance data
function getStudentAttendanceData($conn, $date_from, $date_to) {
    $query = "SELECT s.student_number, s.first_name, s.last_name, s.grade, s.class_section,
              COUNT(a.attendance_id) as total_days,
              SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
              SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
              SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
              ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(a.attendance_id), 0)), 1) as rate
              FROM students s
              JOIN users u ON s.user_id = u.user_id
              LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date BETWEEN :from AND :to
              WHERE u.status = 'active'
              GROUP BY s.student_id
              ORDER BY s.grade, s.class_section, s.last_name, s.first_name";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':from', $date_from);
    $stmt->bindParam(':to', $date_to);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get daily attendance data
function getDailyAttendanceData($conn, $date_from, $date_to) {
    $query = "SELECT 
              a.date,
              COUNT(*) as total,
              SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
              SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
              SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
              ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as rate
              FROM attendance a
              WHERE a.date BETWEEN :from AND :to
              GROUP BY a.date
              ORDER BY a.date";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':from', $date_from);
    $stmt->bindParam(':to', $date_to);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Export to Excel (CSV format)
if ($action === 'excel') {
    $filename = "EduID_Attendance_Report_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel to recognize UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Report Header
    fputcsv($output, ['EduID - Attendance Report']);
    fputcsv($output, ['Generated on: ' . date('F j, Y, g:i A')]);
    fputcsv($output, ['Date Range: ' . date('M j, Y', strtotime($date_from)) . ' to ' . date('M j, Y', strtotime($date_to))]);
    fputcsv($output, []);
    
    // Grade-wise Summary
    fputcsv($output, ['=== GRADE-WISE ATTENDANCE SUMMARY ===']);
    fputcsv($output, ['Grade', 'Total Records', 'Present', 'Absent', 'Late', 'Attendance Rate (%)']);
    
    $grade_data = getGradeAttendanceData($conn, $date_from, $date_to);
    $total_records = 0;
    $total_present = 0;
    $total_absent = 0;
    $total_late = 0;
    
    foreach ($grade_data as $row) {
        $rate = $row['total_records'] > 0 ? round(($row['present'] / $row['total_records']) * 100, 1) : 0;
        fputcsv($output, [
            'Grade ' . $row['grade'],
            $row['total_records'],
            $row['present'],
            $row['absent'],
            $row['late'],
            $rate . '%'
        ]);
        $total_records += $row['total_records'];
        $total_present += $row['present'];
        $total_absent += $row['absent'];
        $total_late += $row['late'];
    }
    
    $overall_rate = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0;
    fputcsv($output, ['TOTAL', $total_records, $total_present, $total_absent, $total_late, $overall_rate . '%']);
    fputcsv($output, []);
    
    // Daily Attendance
    fputcsv($output, ['=== DAILY ATTENDANCE REPORT ===']);
    fputcsv($output, ['Date', 'Total', 'Present', 'Absent', 'Late', 'Attendance Rate (%)']);
    
    $daily_data = getDailyAttendanceData($conn, $date_from, $date_to);
    foreach ($daily_data as $row) {
        fputcsv($output, [
            date('Y-m-d', strtotime($row['date'])),
            $row['total'],
            $row['present'],
            $row['absent'],
            $row['late'],
            $row['rate'] . '%'
        ]);
    }
    fputcsv($output, []);
    
    // Student-wise Attendance
    fputcsv($output, ['=== STUDENT-WISE ATTENDANCE REPORT ===']);
    fputcsv($output, ['Student Number', 'Name', 'Grade', 'Section', 'Total Days', 'Present', 'Absent', 'Late', 'Attendance Rate (%)']);
    
    $student_data = getStudentAttendanceData($conn, $date_from, $date_to);
    foreach ($student_data as $row) {
        fputcsv($output, [
            $row['student_number'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['grade'],
            $row['class_section'],
            $row['total_days'] ?? 0,
            $row['present_days'] ?? 0,
            $row['absent_days'] ?? 0,
            $row['late_days'] ?? 0,
            ($row['rate'] ?? 0) . '%'
        ]);
    }
    
    fclose($output);
    exit;
}

// Export to PDF (HTML-based)
if ($action === 'pdf') {
    $grade_data = getGradeAttendanceData($conn, $date_from, $date_to);
    $student_data = getStudentAttendanceData($conn, $date_from, $date_to);
    $daily_data = getDailyAttendanceData($conn, $date_from, $date_to);
    
    // Calculate totals
    $total_records = 0;
    $total_present = 0;
    $total_absent = 0;
    $total_late = 0;
    foreach ($grade_data as $row) {
        $total_records += $row['total_records'];
        $total_present += $row['present'];
        $total_absent += $row['absent'];
        $total_late += $row['late'];
    }
    $overall_rate = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - EduID</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #6366f1;
        }
        .header h1 {
            font-size: 24px;
            color: #6366f1;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 14px;
            color: #666;
        }
        .header .date-range {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            background: #f1f5f9;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 4px solid #6366f1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
        }
        td {
            font-size: 11px;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-success { color: #22c55e; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
        .text-primary { color: #6366f1; }
        .font-bold { font-weight: 600; }
        .total-row {
            background: #e0e7ff !important;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-box .value {
            font-size: 24px;
            font-weight: 700;
            color: #6366f1;
        }
        .stat-box .label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
            <span style="margin-right: 5px;">🖨️</span> Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            Close
        </button>
        <p style="margin-top: 10px; color: #666; font-size: 12px;">Use your browser's print function (Ctrl+P) and select "Save as PDF" to download</p>
    </div>

    <div class="header">
        <h1>EduID</h1>
        <div class="subtitle">Attendance Report</div>
        <div class="date-range">
            <?php echo date('F j, Y', strtotime($date_from)); ?> - <?php echo date('F j, Y', strtotime($date_to)); ?>
        </div>
        <div class="date-range">Generated on: <?php echo date('F j, Y, g:i A'); ?></div>
    </div>

    <!-- Summary Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="value"><?php echo number_format($total_records); ?></div>
            <div class="label">Total Records</div>
        </div>
        <div class="stat-box">
            <div class="value" style="color: #22c55e;"><?php echo number_format($total_present); ?></div>
            <div class="label">Present</div>
        </div>
        <div class="stat-box">
            <div class="value" style="color: #ef4444;"><?php echo number_format($total_absent); ?></div>
            <div class="label">Absent</div>
        </div>
        <div class="stat-box">
            <div class="value" style="color: #6366f1;"><?php echo $overall_rate; ?>%</div>
            <div class="label">Attendance Rate</div>
        </div>
    </div>

    <!-- Grade-wise Summary -->
    <div class="section">
        <div class="section-title">📊 Grade-wise Attendance Summary</div>
        <table>
            <thead>
                <tr>
                    <th>Grade</th>
                    <th class="text-center">Total Records</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade_data as $row): 
                    $rate = $row['total_records'] > 0 ? round(($row['present'] / $row['total_records']) * 100, 1) : 0;
                ?>
                <tr>
                    <td class="font-bold">Grade <?php echo htmlspecialchars($row['grade']); ?></td>
                    <td class="text-center"><?php echo number_format($row['total_records']); ?></td>
                    <td class="text-center text-success font-bold"><?php echo number_format($row['present']); ?></td>
                    <td class="text-center text-danger font-bold"><?php echo number_format($row['absent']); ?></td>
                    <td class="text-center text-warning font-bold"><?php echo number_format($row['late']); ?></td>
                    <td class="text-center">
                        <span class="<?php echo $rate >= 75 ? 'text-success' : ($rate >= 50 ? 'text-warning' : 'text-danger'); ?> font-bold">
                            <?php echo $rate; ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="text-center"><?php echo number_format($total_records); ?></td>
                    <td class="text-center text-success"><?php echo number_format($total_present); ?></td>
                    <td class="text-center text-danger"><?php echo number_format($total_absent); ?></td>
                    <td class="text-center text-warning"><?php echo number_format($total_late); ?></td>
                    <td class="text-center text-primary font-bold"><?php echo $overall_rate; ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Daily Attendance -->
    <?php if (!empty($daily_data)): ?>
    <div class="section">
        <div class="section-title">📅 Daily Attendance Report</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_data as $row): ?>
                <tr>
                    <td><?php echo date('D, M j, Y', strtotime($row['date'])); ?></td>
                    <td class="text-center"><?php echo $row['total']; ?></td>
                    <td class="text-center text-success font-bold"><?php echo $row['present']; ?></td>
                    <td class="text-center text-danger font-bold"><?php echo $row['absent']; ?></td>
                    <td class="text-center text-warning font-bold"><?php echo $row['late']; ?></td>
                    <td class="text-center">
                        <span class="<?php echo $row['rate'] >= 75 ? 'text-success' : ($row['rate'] >= 50 ? 'text-warning' : 'text-danger'); ?> font-bold">
                            <?php echo $row['rate']; ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Student-wise Attendance (on new page) -->
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">👨‍🎓 Student-wise Attendance Report</div>
        <table>
            <thead>
                <tr>
                    <th>Student No.</th>
                    <th>Name</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">Section</th>
                    <th class="text-center">Days</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($student_data as $row): 
                    $rate = $row['rate'] ?? 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($row['grade']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($row['class_section']); ?></td>
                    <td class="text-center"><?php echo $row['total_days'] ?? 0; ?></td>
                    <td class="text-center text-success font-bold"><?php echo $row['present_days'] ?? 0; ?></td>
                    <td class="text-center text-danger font-bold"><?php echo $row['absent_days'] ?? 0; ?></td>
                    <td class="text-center text-warning font-bold"><?php echo $row['late_days'] ?? 0; ?></td>
                    <td class="text-center">
                        <span class="<?php echo $rate >= 75 ? 'text-success' : ($rate >= 50 ? 'text-warning' : 'text-danger'); ?> font-bold">
                            <?php echo $rate; ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>EduID - Educational Identity Verification System</p>
        <p>This report was automatically generated. For any queries, contact the administrator.</p>
    </div>
</body>
</html>
<?php
    exit;
}

// Default response
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid action']);
?>
