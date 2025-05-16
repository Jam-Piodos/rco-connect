<?php
require_once 'config.php';

// Connect to database
$conn = getDBConnection();

// Get admin accounts
$stmt = $conn->prepare("SELECT id, club_name, email, role, description, created_at FROM users WHERE role = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Admin Accounts</h2>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Club Name</th><th>Email</th><th>Role</th><th>Description</th><th>Created At</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['club_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['description'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No admin accounts found in the database.</p>";
}

// Check for other accounts that might be causing issues
$query = "SELECT id, club_name, email, role FROM users WHERE role != 'admin' OR role IS NULL";
$result = $conn->query($query);

echo "<h2>Other Accounts</h2>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Club Name</th><th>Email</th><th>Role</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['club_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No other accounts found in the database.</p>";
}

$conn->close();
?> 