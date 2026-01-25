<?php
require_once '../config/config.php';

// Log the logout
if (isLoggedIn()) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $logQuery = "INSERT INTO access_logs (user_id, access_type, ip_address, user_agent, status) 
                 VALUES (:user_id, 'logout', :ip, :user_agent, 'success')";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bindParam(':user_id', $_SESSION['user_id']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $logStmt->bindParam(':ip', $ip);
    $logStmt->bindParam(':user_agent', $user_agent);
    $logStmt->execute();
}

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>
