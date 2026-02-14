<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h2>Admin Password Reset Tool</h2>";

// New password
$new_password = 'Admin@123';
$hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "<p>New password hash created:</p>";
echo "<code style='background: #f4f4f4; padding: 10px; display: block; margin: 10px 0;'>" . $hash . "</code>";

// Update database
try {
    $stmt = db()->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'agpn_admin'");
    $stmt->execute([$hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ Password updated successfully!</p>";
        echo "<p>Username: <strong>agpn_admin</strong></p>";
        echo "<p>Password: <strong>" . $new_password . "</strong></p>";
        echo "<p><a href='admin/index.php'>Go to Admin Login →</a></p>";
    } else {
        echo "<p style='color: orange;'>No user found. Creating new admin user...</p>";
        
        // Create new admin user
        $insert = db()->prepare("
            INSERT INTO admin_users (username, email, password_hash, full_name, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->execute(['admin', 'admin@afroglobeprime1.net', $hash, 'AGPN Administrator', 'superadmin']);
        
        echo "<p style='color: green;'>✓ New admin user created!</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Show all admin users
echo "<h3>Current Admin Users:</h3>";
$users = db()->query("SELECT id, username, email, role FROM admin_users")->fetchAll();
echo "<ul>";
foreach ($users as $user) {
    echo "<li><strong>{$user['username']}</strong> - {$user['email']} ({$user['role']})</li>";
}
echo "</ul>";

// Delete this file after use!
echo "<p style='color: red; margin-top: 30px;'><strong>⚠ IMPORTANT:</strong> Delete this file (create_hash.php) immediately after logging in!</p>";
?>