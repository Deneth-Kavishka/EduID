<?php
/**
 * Parent Notification Handler
 * AJAX handler for notification operations
 */

require_once '../../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a parent
if (!isLoggedIn() || getUserRole() !== 'parent') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'mark_all_read':
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'mark_read':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            try {
                $query = "UPDATE notifications SET is_read = 1 
                          WHERE notification_id = :notification_id AND user_id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':notification_id' => $notification_id,
                    ':user_id' => $_SESSION['user_id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
        break;
        
    case 'get_notifications':
        try {
            $query = "SELECT * FROM notifications 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC LIMIT 20";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_unread_count':
        try {
            $query = "SELECT COUNT(*) FROM notifications 
                      WHERE user_id = :user_id AND is_read = 0";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
