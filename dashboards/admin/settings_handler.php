<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Handle GET requests (backup download)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'backup') {
        try {
            // Get all tables
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            // Generate SQL backup
            $backup = "-- EduID Database Backup\n";
            $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: eduid_system\n\n";
            $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Get table structure
                $result = $conn->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch(PDO::FETCH_NUM);
                $backup .= "-- Table structure for `$table`\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup .= $row[1] . ";\n\n";
                
                // Get table data
                $result = $conn->query("SELECT * FROM `$table`");
                $numFields = $result->columnCount();
                
                $rows = $result->fetchAll(PDO::FETCH_NUM);
                if (count($rows) > 0) {
                    $backup .= "-- Data for `$table`\n";
                    foreach ($rows as $row) {
                        $backup .= "INSERT INTO `$table` VALUES(";
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $backup .= implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Send as download
            $filename = 'eduid_backup_' . date('Y-m-d_His') . '.sql';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($backup));
            
            echo $backup;
            
            // Log backup action
            $query = "INSERT INTO access_logs (user_id, access_type, status, details) VALUES (:user_id, 'system', 'success', 'Database backup created')";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            exit;
        } catch (Exception $e) {
            header('Location: settings.php?error=' . urlencode('Backup failed: ' . $e->getMessage()));
            exit;
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    // Clear old logs
    if ($action === 'clear_logs') {
        try {
            $query = "DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
            
            // Log the action
            $query = "INSERT INTO access_logs (user_id, access_type, status, details) VALUES (:user_id, 'system', 'success', :details)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Cleared $deletedCount old access logs";
            $stmt->bindParam(':details', $details);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => "Successfully deleted $deletedCount old log entries."]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Reset attendance data
    if ($action === 'reset_attendance') {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Delete attendance records
            $query = "DELETE FROM attendance";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $attendanceCount = $stmt->rowCount();
            
            // Delete exam attendance records
            $query = "DELETE FROM exam_attendance";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $examCount = $stmt->rowCount();
            
            $conn->commit();
            
            // Log the action
            $query = "INSERT INTO access_logs (user_id, access_type, status, details) VALUES (:user_id, 'system', 'success', :details)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Reset all attendance data: $attendanceCount attendance records, $examCount exam attendance records deleted";
            $stmt->bindParam(':details', $details);
            $stmt->execute();
            
            echo json_encode([
                'success' => true, 
                'message' => "Successfully deleted $attendanceCount attendance records and $examCount exam attendance records."
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Get setting value
    if ($action === 'get_setting') {
        try {
            $key = $_POST['key'] ?? '';
            $query = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'value' => $result ? $result['setting_value'] : null
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Update setting value
    if ($action === 'update_setting') {
        try {
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';
            
            $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                      VALUES (:key, :value, :user_id)
                      ON DUPLICATE KEY UPDATE setting_value = :value, updated_by = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Export settings
    if ($action === 'export_settings') {
        try {
            $query = "SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'settings' => $settings,
                'exported_at' => date('Y-m-d H:i:s')
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Invalid request
header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
