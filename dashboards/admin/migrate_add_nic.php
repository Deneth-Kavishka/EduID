<?php
/**
 * Database Migration: Add NIC column to parents table
 * Run this once to add NIC support
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add NIC Column</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Database Migration: Add NIC Column</h1>
    <hr>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<div class='success'>✓ Database connection successful</div>";
    
    // Check if NIC column already exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM parents LIKE 'nic'");
    $stmt->execute();
    $nicExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($nicExists) {
        echo "<div class='info'>ℹ NIC column already exists in parents table. No migration needed.</div>";
    } else {
        echo "<div class='info'>Adding NIC column to parents table...</div>";
        
        // Add NIC column
        $sql = "ALTER TABLE parents ADD COLUMN nic VARCHAR(20) UNIQUE AFTER phone";
        $conn->exec($sql);
        
        echo "<div class='success'>✓ NIC column added successfully!</div>";
        
        // Add index for better search performance
        try {
            $conn->exec("CREATE INDEX idx_nic ON parents(nic)");
            echo "<div class='success'>✓ Index added for NIC column</div>";
        } catch (Exception $e) {
            echo "<div class='info'>Note: Index might already exist</div>";
        }
    }
    
    // Show table structure
    echo "<h3>Current parents table structure:</h3>";
    $stmt = $conn->query("DESCRIBE parents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='success' style='margin-top: 2rem;'>
        <h3>✅ Migration Complete!</h3>
        <p>The NIC column is now available in the parents table.</p>
        <p><strong>What's New:</strong></p>
        <ul>
            <li>Parents can now have a National Identity Card (NIC) number</li>
            <li>NIC is unique for each parent</li>
            <li>Students can be linked to parents using NIC</li>
            <li>Parent dropdown shows: Name - Phone - NIC</li>
        </ul>
        <p><a href='users.php' style='font-size: 18px; font-weight: bold;'>→ Go to User Management</a></p>
    </div>";
    
    echo "<div class='info'>
        <strong>Note:</strong> You can safely run this migration multiple times. It will only add the column if it doesn't exist.
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
    echo "<div class='info'>
        <h3>Troubleshooting:</h3>
        <ul>
            <li>Make sure MySQL is running</li>
            <li>Check database credentials in config/database.php</li>
            <li>Verify you have ALTER TABLE permissions</li>
        </ul>
    </div>";
}

echo "</body></html>";
?>
