<?php
require_once 'config.php';

// Connect to database
$conn = getDBConnection();

// The correct admin account that should be kept
$correct_admin_email = "admin@rcoconnect.com";

// Start transaction
$conn->begin_transaction();

try {
    // Check if correct admin account exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param('s', $correct_admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("The correct admin account ($correct_admin_email) does not exist. Please run create_admin.php first.");
    }
    
    // Get all other admin accounts to delete
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE role = 'admin' AND email != ?");
    $stmt->bind_param('s', $correct_admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deleted_accounts = [];
    
    while ($row = $result->fetch_assoc()) {
        $account_id = $row['id'];
        $account_email = $row['email'];
        
        // Delete the admin account
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param('i', $account_id);
        $delete_stmt->execute();
        
        $deleted_accounts[] = $account_email;
    }
    
    // Update correct admin account to make sure it has the right details
    $club_name = "System Administrator";
    $description = "System Administrator Account";
    
    $update_stmt = $conn->prepare("UPDATE users SET club_name = ?, description = ? WHERE email = ?");
    $update_stmt->bind_param('sss', $club_name, $description, $correct_admin_email);
    $update_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo "<h2>Admin Account Fixed</h2>";
    
    if (!empty($deleted_accounts)) {
        echo "<p>Deleted the following incorrect admin accounts:</p>";
        echo "<ul>";
        foreach ($deleted_accounts as $email) {
            echo "<li>" . htmlspecialchars($email) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No incorrect admin accounts were found.</p>";
    }
    
    echo "<p>The correct admin account (" . htmlspecialchars($correct_admin_email) . ") has been verified and updated.</p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>

<p><a href="check_admin.php">Check Admin Accounts</a></p>
<p><a href="login.php">Proceed to Login</a></p> 