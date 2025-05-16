<?php
require_once 'config.php';

// New password for admin
$new_password = "Admin@123"; // Default reset password
$admin_email = "admin@rcoconnect.com";

// Create password hash
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Connect to database
$conn = getDBConnection();

// Check if admin account exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param('s', $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $admin_id = $admin['id'];
    
    // Update admin password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $password_hash, $admin_id);
    
    if ($stmt->execute()) {
        echo "<h2>Admin Password Reset</h2>";
        echo "<p>Admin password has been reset successfully!</p>";
        echo "<p>Email: $admin_email</p>";
        echo "<p>New Password: $new_password</p>";
        echo "<p>Please change this password immediately after login.</p>";
    } else {
        echo "<h2>Error</h2>";
        echo "<p>Failed to reset admin password: " . $conn->error . "</p>";
    }
} else {
    echo "<h2>Error</h2>";
    echo "<p>Admin account not found. Please run create_admin.php first.</p>";
}

$conn->close();
?>

<p><a href="login.php">Proceed to Login</a></p> 