<?php
require_once 'config.php';

// Admin account details
$club_name = "System Administrator";
$email = "admin@rcoconnect.com";
$password = "Admin@123"; // This is the default password that should be changed after first login
$role = "admin";
$description = "System Administrator Account";

// Create password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Connect to database
$conn = getDBConnection();

// Check if admin account already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<h2>Admin Account Creation</h2>";
    echo "<p>Admin account with email '$email' already exists.</p>";
    echo "<p>If you need to reset the admin password, please run reset_admin_password.php</p>";
} else {
    // Insert admin account
    $stmt = $conn->prepare("INSERT INTO users (club_name, email, password_hash, role, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $club_name, $email, $password_hash, $role, $description);

    if ($stmt->execute()) {
        echo "<h2>Admin Account Created</h2>";
        echo "<p>Admin account has been created successfully!</p>";
        echo "<p>Email: $email</p>";
        echo "<p>Password: $password</p>";
        echo "<p>Please keep these credentials secure and change the password after first login.</p>";
    } else {
        echo "<h2>Error</h2>";
        echo "<p>Failed to create admin account: " . $conn->error . "</p>";
    }
}

$conn->close();
?>

<p><a href="login.php">Proceed to Login</a></p> 