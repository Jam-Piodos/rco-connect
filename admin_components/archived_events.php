<?php
// Security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';

// Handle restore action
$success_message = "";
$error_message = "";

if (isset($_POST['restore_event'])) {
    $archived_id = $_POST['archived_id'];
    
    $conn = getDBConnection();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get archived event details
        $stmt = $conn->prepare("SELECT * FROM archived_events WHERE id = ?");
        $stmt->bind_param('i', $archived_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $archived_event = $result->fetch_assoc();
        
        if ($archived_event) {
            // Restore to events table
            $restore_stmt = $conn->prepare("INSERT INTO events 
                (title, description, event_date, start_time, end_time, location, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $restore_stmt->bind_param('ssssssi', 
                $archived_event['title'], 
                $archived_event['description'], 
                $archived_event['event_date'], 
                $archived_event['start_time'], 
                $archived_event['end_time'], 
                $archived_event['location'], 
                $archived_event['created_by']
            );
            $restore_stmt->execute();
            
            // Delete from archived_events table
            $delete_stmt = $conn->prepare("DELETE FROM archived_events WHERE id = ?");
            $delete_stmt->bind_param('i', $archived_id);
            $delete_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success_message = "Event restored successfully!";
        } else {
            $error_message = "Archived event not found!";
            $conn->rollback();
        }
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        $error_message = "Error restoring event: " . $e->getMessage();
    }
    
    $conn->close();
}

// Handle permanent delete action
if (isset($_POST['permanent_delete'])) {
    $archived_id = $_POST['archived_id'];
    
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM archived_events WHERE id = ?");
    $stmt->bind_param('i', $archived_id);
    
    if ($stmt->execute()) {
        $success_message = "Event permanently deleted!";
    } else {
        $error_message = "Error deleting event: " . $conn->error;
    }
    
    $conn->close();
}

// Get all archived events
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT a.*, u.club_name 
                       FROM archived_events a 
                       JOIN users u ON a.created_by = u.id 
                       ORDER BY a.archived_at DESC");
$stmt->execute();
$archived_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Include admin header
include 'admin_header.php';
?>

<div class="container">
    <a href="../admin/dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <h1>Archived Events</h1>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <?php if (empty($archived_events)): ?>
        <div class="empty-message">No archived events found.</div>
    <?php else: ?>
        <table class="archived-events-table">
            <thead>
                <tr>
                    <th>Event Title</th>
                    <th>RCO</th>
                    <th>Date & Time</th>
                    <th>Archived On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archived_events as $event): ?>
                    <tr>
                        <td>
                            <div class="event-details">
                                <div><strong><?php echo htmlspecialchars($event['title']); ?></strong></div>
                                <div class="event-description"><?php echo htmlspecialchars($event['description']); ?></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?> at
                            <?php echo date('h:i A', strtotime($event['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($event['archived_at'])); ?></td>
                        <td class="action-buttons">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="archived_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" name="restore_event" class="btn btn-restore" 
                                        onclick="return confirm('Are you sure you want to restore this event?')">
                                    <i class="fas fa-trash-restore"></i> Restore
                                </button>
                            </form>
                            
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="archived_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" name="permanent_delete" class="btn btn-delete"
                                        onclick="return confirm('Are you sure you want to permanently delete this event? This action cannot be undone.')">
                                    <i class="fas fa-trash-alt"></i> Delete Permanently
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 20px;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    h1 {
        color: #333;
        border-bottom: 2px solid #ff9800;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .archived-events-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .archived-events-table th, 
    .archived-events-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .archived-events-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    
    .archived-events-table tr:hover {
        background-color: #f5f5f5;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.2s;
    }
    
    .btn-restore {
        background-color: #28a745;
        color: white;
    }
    
    .btn-restore:hover {
        background-color: #218838;
    }
    
    .btn-delete {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-delete:hover {
        background-color: #c82333;
    }
    
    .empty-message {
        text-align: center;
        padding: 40px;
        color: #666;
        font-style: italic;
    }
    
    .back-button {
        display: inline-block;
        margin-bottom: 20px;
        padding: 8px 16px;
        background-color: #ff9800;
        color: white;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
    }
    
    .back-button:hover {
        background-color: #e68a00;
    }
    
    .event-details {
        max-width: 400px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .event-description {
        color: #666;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
    }
</style> 