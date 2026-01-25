<?php
/**
 * Admin Password Fix Script
 * This will create/update the admin account with correct password
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Admin Password - EduID</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>🔧 EduID Admin Password Fix</h1>
    <hr>";

try {
    // Connect to database
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<div class='success'>✓ Database connection successful</div>";
    
    // Generate correct password hash for Admin@123
    $password = "Admin@123";
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<div class='info'><strong>Password being set:</strong> Admin@123</div>";
    
    // Check if admin user exists
    $checkStmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE username = 'admin'");
    $checkStmt->execute();
    $existingAdmin = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAdmin) {
        // Update existing admin
        echo "<div class='info'>Admin user found (ID: {$existingAdmin['user_id']}). Updating password...</div>";
        
        $updateStmt = $conn->prepare("UPDATE users SET password_hash = :password_hash, status = 'active' WHERE username = 'admin'");
        $updateStmt->bindParam(':password_hash', $password_hash);
        
        if ($updateStmt->execute()) {
            echo "<div class='success'>✓ Admin password updated successfully!</div>";
        } else {
            throw new Exception("Failed to update admin password");
        }
    } else {
        // Create new admin user
        echo "<div class='info'>Admin user not found. Creating new admin account...</div>";
        
        $insertStmt = $conn->prepare(
            "INSERT INTO users (username, email, password_hash, user_role, status) 
             VALUES ('admin', 'admin@eduid.com', :password_hash, 'admin', 'active')"
        );
        $insertStmt->bindParam(':password_hash', $password_hash);
        
        if ($insertStmt->execute()) {
            echo "<div class='success'>✓ Admin account created successfully!</div>";
        } else {
            throw new Exception("Failed to create admin account");
        }
    }
    
    // Verify the password works
    $verifyStmt = $conn->prepare("SELECT user_id, username, password_hash FROM users WHERE username = 'admin'");
    $verifyStmt->execute();
    $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        echo "<div class='success'>✓ Password verification successful! Login should work now.</div>";
    } else {
        throw new Exception("Password verification failed!");
    }
    
    // Display login credentials
    echo "<div class='info'>
        <h3>📋 Login Credentials:</h3>
        <pre>Username: admin
Email: admin@eduid.com
Password: Admin@123</pre>
    </div>";
    
    echo "<div class='success'>
        <h3>✅ All Done!</h3>
        <p>Your admin account is now ready. You can login at:</p>
        <p><a href='auth/login.php' style='font-size: 18px; font-weight: bold;'>→ Go to Login Page</a></p>
    </div>";
    
    echo "<div class='info'>
        <strong>Note:</strong> You can delete this fix_admin_password.php file after successful login for security.
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
    echo "<div class='info'>
        <h3>Troubleshooting Steps:</h3>
        <ol>
            <li>Make sure MySQL is running in XAMPP Control Panel</li>
            <li>Verify database name is 'eduid_system' in MySQL Workbench</li>
            <li>Check that schema.sql was executed successfully</li>
            <li>Verify database credentials in config/database.php</li>
        </ol>
    </div>";
}

echo "</body></html>";
?>
