<?php
/**
 * RCO Connect System Initialization Script
 * 
 * This script initializes the RCO Connect system by:
 * 1. Creating necessary directories
 * 2. Running database schema update scripts
 * 3. Ensuring admin account exists
 * 4. Creating sample user account (if requested)
 */

echo "<h1>RCO Connect System Initialization</h1>";

// Include database configuration
require_once 'config.php';

// Step 1: Create necessary directories
echo "<h2>Step 1: Creating necessary directories</h2>";
$directories = [
    'uploads',
    'uploads/profile_pictures'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "<p style='color:green'>✓ Created directory: {$dir}</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to create directory: {$dir}. Please check permissions.</p>";
        }
    } else {
        echo "<p>Directory already exists: {$dir}</p>";
    }
}

// Step 2: Create and update database tables
echo "<h2>Step 2: Setting up database</h2>";

try {
    $conn = getDBConnection();
    echo "<p>Database connection established successfully.</p>";
    
    // Read the SQL file
    $sql = file_get_contents('rco_connect.sql');
    
    // Split the SQL file into individual statements
    $statements = explode(';', $sql);
    
    // Execute each statement
    $successCount = 0;
    $totalStatements = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $totalStatements++;
            try {
                $conn->query($statement);
                $successCount++;
                echo "<p style='color:green'>✓ Executed SQL statement successfully.</p>";
            } catch (Exception $e) {
                echo "<p style='color:orange'>⚠ SQL statement issue: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<p>Executed {$successCount} of {$totalStatements} SQL statements.</p>";
    
    // Step 3: Verify admin account exists
    echo "<h2>Step 3: Verifying admin account</h2>";
    $result = $conn->query("SELECT * FROM users WHERE email = 'admin@rcoconnect.com' AND role = 'admin'");
    
    if ($result->num_rows > 0) {
        echo "<p style='color:green'>✓ Admin account exists.</p>";
    } else {
        // Create admin account if it doesn't exist
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (club_name, email, password_hash, role) VALUES ('System Administrator', 'admin@rcoconnect.com', ?, 'admin')");
        $stmt->bind_param('s', $password_hash);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Created admin account. Username: admin@rcoconnect.com, Password: admin123</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to create admin account: " . $conn->error . "</p>";
        }
    }

    // Step 4: Create a demo user account if requested
    echo "<h2>Step 4: Demo user account</h2>";
    
    if (isset($_GET['create_demo_user']) && $_GET['create_demo_user'] === 'true') {
        $result = $conn->query("SELECT * FROM users WHERE email = 'demouser@rcoconnect.com' AND role = 'user'");
        
        if ($result->num_rows > 0) {
            echo "<p>Demo user account already exists.</p>";
        } else {
            $password_hash = password_hash('demo123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (club_name, email, password_hash, role, description) 
                                  VALUES ('Demo Club', 'demouser@rcoconnect.com', ?, 'user', 'This is a demo account for testing purposes')");
            $stmt->bind_param('s', $password_hash);
            
            if ($stmt->execute()) {
                echo "<p style='color:green'>✓ Created demo user account. Username: demouser@rcoconnect.com, Password: demo123</p>";
                
                // Create a sample event for the demo user
                $user_id = $conn->insert_id;
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                
                $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, created_by) 
                                      VALUES ('Demo Event', 'This is a sample event for demonstration', ?, '10:00:00', '12:00:00', 'Main Campus', ?)");
                $stmt->bind_param('si', $tomorrow, $user_id);
                
                if ($stmt->execute()) {
                    echo "<p style='color:green'>✓ Created sample event for demo user.</p>";
                } else {
                    echo "<p style='color:orange'>⚠ Failed to create sample event: " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:red'>✗ Failed to create demo user account: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p>To create a demo user account with sample data, <a href='initialize_system.php?create_demo_user=true'>click here</a>.</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>System Initialization Complete</h2>";
echo "<div style='margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 8px;'>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>You can now <a href='login.php'>log in to the system</a> using the admin account.</li>";
echo "<li><strong>Important:</strong> For security, change the admin password immediately after first login!</li>";
echo "<li>Create user accounts for RCOs or use the demo account if you created one.</li>";
echo "</ol>";
echo "</div>";

echo "<p style='color:#777; font-size: 0.9rem; margin-top: 30px;'>System initialization timestamp: " . date('Y-m-d H:i:s') . "</p>";
?> 