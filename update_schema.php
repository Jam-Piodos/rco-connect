<?php
require_once 'config.php';

// Add profile_picture column to users table if it doesn't exist
function addProfilePictureColumn() {
    $conn = getDBConnection();
    
    // Check if the column already exists
    $query = "SHOW COLUMNS FROM users LIKE 'profile_picture'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $alterQuery = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL";
        if ($conn->query($alterQuery) === TRUE) {
            echo "Profile picture column added successfully!";
        } else {
            echo "Error adding profile picture column: " . $conn->error;
        }
    } else {
        echo "Profile picture column already exists.";
    }
    
    $conn->close();
}

// Create uploads directory if it doesn't exist
function createUploadsDirectory() {
    $uploadPath = 'uploads/profile_pictures';
    
    if (!file_exists($uploadPath)) {
        if (mkdir($uploadPath, 0777, true)) {
            echo "<br>Uploads directory created successfully!";
        } else {
            echo "<br>Error creating uploads directory.";
        }
    } else {
        echo "<br>Uploads directory already exists.";
    }
}

// Create archived_events table if it doesn't exist
function createArchivedEventsTable() {
    $conn = getDBConnection();
    
    // Check if table exists
    $query = "SHOW TABLES LIKE 'archived_events'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Table doesn't exist, create it
        $createQuery = "CREATE TABLE archived_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            location VARCHAR(255) NOT NULL,
            created_by INT NOT NULL,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($createQuery) === TRUE) {
            echo "<br>Archived events table created successfully!";
        } else {
            echo "<br>Error creating archived events table: " . $conn->error;
        }
    } else {
        echo "<br>Archived events table already exists.";
    }
    
    $conn->close();
}

// Update events table structure for time fields
function updateEventsTableStructure() {
    $conn = getDBConnection();
    
    // Check if start_time column already exists
    $query = "SHOW COLUMNS FROM events LIKE 'start_time'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Start transaction to ensure data integrity
        $conn->begin_transaction();
        
        try {
            // 1. Add new columns
            $conn->query("ALTER TABLE events ADD COLUMN start_time TIME DEFAULT NULL");
            $conn->query("ALTER TABLE events ADD COLUMN end_time TIME DEFAULT NULL");
            
            // 2. Copy data from event_time to start_time
            $conn->query("UPDATE events SET start_time = event_time");
            
            // 3. Calculate end_time as start_time + 1 hour for existing events
            $conn->query("UPDATE events SET end_time = ADDTIME(start_time, '01:00:00')");
            
            // 4. Set NOT NULL constraints after data is populated
            $conn->query("ALTER TABLE events MODIFY start_time TIME NOT NULL");
            $conn->query("ALTER TABLE events MODIFY end_time TIME NOT NULL");
            
            // 5. Drop the old column
            $conn->query("ALTER TABLE events DROP COLUMN event_time");
            
            // Commit transaction
            $conn->commit();
            echo "<br>Events table structure updated successfully!";
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            echo "<br>Error updating events table structure: " . $e->getMessage();
        }
    } else {
        echo "<br>Events table structure already updated.";
    }
    
    // Do the same for archived_events table
    $query = "SHOW COLUMNS FROM archived_events LIKE 'start_time'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Start transaction to ensure data integrity
        $conn->begin_transaction();
        
        try {
            // 1. Add new columns
            $conn->query("ALTER TABLE archived_events ADD COLUMN start_time TIME DEFAULT NULL");
            $conn->query("ALTER TABLE archived_events ADD COLUMN end_time TIME DEFAULT NULL");
            
            // 2. Copy data from event_time to start_time
            $conn->query("UPDATE archived_events SET start_time = event_time");
            
            // 3. Calculate end_time as start_time + 1 hour for existing events
            $conn->query("UPDATE archived_events SET end_time = ADDTIME(start_time, '01:00:00')");
            
            // 4. Set NOT NULL constraints after data is populated
            $conn->query("ALTER TABLE archived_events MODIFY start_time TIME NOT NULL");
            $conn->query("ALTER TABLE archived_events MODIFY end_time TIME NOT NULL");
            
            // 5. Drop the old column
            $conn->query("ALTER TABLE archived_events DROP COLUMN event_time");
            
            // Commit transaction
            $conn->commit();
            echo "<br>Archived events table structure updated successfully!";
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            echo "<br>Error updating archived events table structure: " . $e->getMessage();
        }
    } else {
        echo "<br>Archived events table structure already updated.";
    }
    
    $conn->close();
}

// Create user_activities table for tracking CRUD operations
function createUserActivitiesTable() {
    $conn = getDBConnection();
    
    // Check if table exists
    $query = "SHOW TABLES LIKE 'user_activities'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 0) {
        // Table doesn't exist, create it
        $createQuery = "CREATE TABLE user_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT,
            event_title VARCHAR(255) NOT NULL,
            action_type ENUM('added', 'updated', 'deleted') NOT NULL,
            action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($createQuery) === TRUE) {
            echo "<br>User activities table created successfully!";
        } else {
            echo "<br>Error creating user activities table: " . $conn->error;
        }
    } else {
        echo "<br>User activities table already exists.";
    }
    
    $conn->close();
}

// Execute functions
addProfilePictureColumn();
createUploadsDirectory();
createArchivedEventsTable();
updateEventsTableStructure();
createUserActivitiesTable();

echo "<br><br>Schema update completed.";
echo "<br><a href='user/dashboard.php'>Return to Dashboard</a>";
?> 