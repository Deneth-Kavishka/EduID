<?php
/**
 * Generate Sample Notifications
 * Run this script once to populate the notifications table with test data
 * Access via: http://localhost/EduID/dashboards/admin/includes/generate_notifications.php
 */

session_start();
require_once '../../../config/database.php';
require_once '../../../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized. Please log in as admin first.');
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Sample notifications
$notifications = [
    [
        'title' => 'Welcome to EduID',
        'message' => 'Welcome to the EduID Admin Portal! Here you can manage students, teachers, attendance, and more.',
        'type' => 'info',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
    ],
    [
        'title' => 'New Student Registered',
        'message' => 'A new student "John Smith" has been registered and is awaiting face recognition setup.',
        'type' => 'success',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
    ],
    [
        'title' => 'Attendance Report Ready',
        'message' => 'The weekly attendance report for all classes is now ready for review. Click to view.',
        'type' => 'info',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
    ],
    [
        'title' => 'Low Attendance Alert',
        'message' => 'Student "Emily Johnson" has attendance below 75% this month. Please take action.',
        'type' => 'warning',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ],
    [
        'title' => 'System Backup Completed',
        'message' => 'The daily system backup has been completed successfully. All data is secure.',
        'type' => 'success',
        'is_read' => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
    ],
    [
        'title' => 'Database Error Resolved',
        'message' => 'A database connection issue was detected and automatically resolved at 2:30 PM.',
        'type' => 'error',
        'is_read' => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
    ],
    [
        'title' => 'New Teacher Onboarded',
        'message' => 'Teacher "Dr. Sarah Williams" has been successfully added to the system.',
        'type' => 'success',
        'is_read' => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
    ],
    [
        'title' => 'Upcoming Event Reminder',
        'message' => 'Don\'t forget! Parent-Teacher meeting is scheduled for tomorrow at 3:00 PM.',
        'type' => 'warning',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'title' => 'Security Alert',
        'message' => 'Multiple failed login attempts detected from IP 192.168.1.100. Please review security settings.',
        'type' => 'error',
        'is_read' => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'title' => 'Class Schedule Updated',
        'message' => 'The class schedule for Grade 10 has been updated. New timetable is now active.',
        'type' => 'info',
        'is_read' => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
    ]
];

// Clear existing notifications for this user (optional - comment out if you want to keep existing)
// $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id")->execute([':user_id' => $user_id]);

// Insert sample notifications
$query = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (:user_id, :title, :message, :type, :is_read, :created_at)";
$stmt = $conn->prepare($query);

$count = 0;
foreach ($notifications as $notif) {
    try {
        $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $notif['title'],
            ':message' => $notif['message'],
            ':type' => $notif['type'],
            ':is_read' => $notif['is_read'],
            ':created_at' => $notif['created_at']
        ]);
        $count++;
    } catch (PDOException $e) {
        echo "Error inserting notification: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>✅ Sample Notifications Generated</h2>";
echo "<p>Successfully created <strong>$count</strong> sample notifications for your account.</p>";
echo "<p><a href='../notifications.php'>View Notifications</a> | <a href='../index.php'>Back to Dashboard</a></p>";
?>
