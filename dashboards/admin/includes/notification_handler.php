<?php
/**
 * Notification Handler
 * Handles all notification-related AJAX requests
 */

// Set JSON header first
header('Content-Type: application/json');

// Suppress HTML error output
ini_set('display_errors', 0);
error_reporting(0);

require_once '../../../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'get_notifications':
        getNotifications($conn, $user_id);
        break;
    case 'get_unread_count':
        getUnreadCount($conn, $user_id);
        break;
    case 'mark_read':
        markAsRead($conn, $user_id);
        break;
    case 'mark_unread':
        markAsUnread($conn, $user_id);
        break;
    case 'mark_all_read':
        markAllAsRead($conn, $user_id);
        break;
    case 'delete_notification':
    case 'delete':
        deleteNotification($conn, $user_id);
        break;
    case 'clear_all':
        clearAllNotifications($conn, $user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getNotifications($conn, $user_id) {
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    
    $query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    // Get unread count
    $query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $unread = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'total' => $total,
            'unread_count' => $unread
        ]
    ]);
}

function getUnreadCount($conn, $user_id) {
    $query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'data' => ['unread_count' => $count]]);
}

function markAsRead($conn, $user_id) {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }
    
    $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function markAsUnread($conn, $user_id) {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }
    
    $query = "UPDATE notifications SET is_read = 0 WHERE notification_id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function markAllAsRead($conn, $user_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function deleteNotification($conn, $user_id) {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }
    
    $query = "DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function clearAllNotifications($conn, $user_id) {
    $query = "DELETE FROM notifications WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

/**
 * Helper function to create a notification (can be called from other files)
 */
function createNotification($conn, $user_id, $title, $message, $type = 'info') {
    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, :type)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    return $stmt->execute();
}

/**
 * Create notifications for all admins
 */
function notifyAllAdmins($conn, $title, $message, $type = 'info') {
    $query = "SELECT user_id FROM users WHERE user_role = 'admin' AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($admins as $admin_id) {
        createNotification($conn, $admin_id, $title, $message, $type);
    }
}
?>
