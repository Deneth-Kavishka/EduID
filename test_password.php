<?php
/**
 * Password Hash Generator and Tester
 * Use this to generate correct password hash for Admin@123
 */

echo "<h2>EduID Password Hash Tester</h2>";
echo "<hr>";

// Test password
$password = "Admin@123";

// Generate new hash
$new_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>1. New Generated Hash:</h3>";
echo "<p><strong>Password:</strong> Admin@123</p>";
echo "<p><strong>Hash:</strong> <code>$new_hash</code></p>";
echo "<hr>";

// Test the hash from database
$db_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "<h3>2. Testing Database Hash:</h3>";
echo "<p><strong>Database Hash:</strong> <code>$db_hash</code></p>";

if (password_verify($password, $db_hash)) {
    echo "<p style='color: green;'>✓ Database hash MATCHES password 'Admin@123'</p>";
} else {
    echo "<p style='color: red;'>✗ Database hash DOES NOT MATCH password 'Admin@123'</p>";
}
echo "<hr>";

echo "<h3>3. Testing New Hash:</h3>";
if (password_verify($password, $new_hash)) {
    echo "<p style='color: green;'>✓ New hash MATCHES password 'Admin@123'</p>";
} else {
    echo "<p style='color: red;'>✗ New hash DOES NOT MATCH password 'Admin@123'</p>";
}
echo "<hr>";

echo "<h3>4. SQL Update Command:</h3>";
echo "<p>Run this SQL command in MySQL Workbench to fix the admin password:</p>";
echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>";
echo "UPDATE users SET password_hash = '$new_hash' WHERE username = 'admin';";
echo "</textarea>";
echo "<hr>";

// Test database connection
echo "<h3>5. Database Connection Test:</h3>";
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT user_id, username, email, user_role, status FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<p style='color: green;'>✓ Admin user exists in database</p>";
        echo "<pre>";
        print_r($admin);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Admin user NOT FOUND in database</p>";
        echo "<p>You may need to run the schema.sql file again</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
}
?>
