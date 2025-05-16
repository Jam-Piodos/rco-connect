<?php
// Security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    echo "<div class='alert alert-error'>Unauthorized access. Please log in.</div>";
    exit();
}

require_once '../config.php';

// Initialize form variables
$title = $description = $event_date = $start_time = $end_time = $location = '';

// Handle form submissions for CRUD operations
$success_message = "";
$error_message = "";

// Check for session success message (set after redirect)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after use
}

// Function to check for time conflicts
function checkTimeConflict($conn, $event_date, $start_time, $end_time, $location, $event_id = null) {
    // Query to find events on the same date with overlapping times
    $query = "SELECT * FROM events WHERE event_date = ? AND (
        (start_time <= ? AND end_time > ?) OR 
        (start_time < ? AND end_time >= ?) OR
        (start_time >= ? AND end_time <= ?)
    )";
    
    $params = [$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time];
    $types = 'sssssss';
    
    // If updating an existing event, exclude it from the conflict check
    if ($event_id) {
        $query .= " AND id != ?";
        $params[] = $event_id;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Time conflict exists
        $conflicting_events = [];
        while ($row = $result->fetch_assoc()) {
            $conflicting_events[] = [
                'title' => $row['title'],
                'date' => $row['event_date'],
                'start' => date('h:i A', strtotime($row['start_time'])),
                'end' => date('h:i A', strtotime($row['end_time'])),
                'location' => $row['location']
            ];
        }
        return $conflicting_events;
    }
    
    return false;
}

// Function to track user activity for event CRUD operations
function trackEventActivity($conn, $event_id, $event_title, $action_type) {
    $user_id = $_SESSION['user_id'];
    $action_time = date('Y-m-d H:i:s');
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_activities (user_id, event_id, event_title, action_type, action_time) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iisss', $user_id, $event_id, $event_title, $action_type, $action_time);
        $success = $stmt->execute();
        
        if (!$success) {
            // Log error but continue - this is non-critical
            error_log("Failed to track user activity: " . $conn->error);
        }
        
        // Add activity notification to session for user feedback
        switch($action_type) {
            case 'added':
                $notification = "Added new event: " . htmlspecialchars($event_title);
                break;
            case 'updated':
                $notification = "Updated event: " . htmlspecialchars($event_title);
                break;
            case 'deleted':
                $notification = "Deleted event: " . htmlspecialchars($event_title);
                break;
        }
        
        $_SESSION['activity_notification'] = $notification;
        return $success;
    } catch (Exception $e) {
        // Log error but don't prevent main operation
        error_log("Exception in trackEventActivity: " . $e->getMessage());
        return false;
    }
}

// Add new event
if (isset($_POST['add_event'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = $_POST['location'];
    
    // Validate end time is after start time
    if ($end_time <= $start_time) {
        $error_message = "End time must be after start time.";
    } else {
        $conn = getDBConnection();
        
        // Check for time conflicts
        $conflicts = checkTimeConflict($conn, $event_date, $start_time, $end_time, $location);
        
        if ($conflicts) {
            $error_message = "Time conflict detected with existing events. Please choose a different time.";
            foreach ($conflicts as $index => $conflict) {
                if ($index < 3) { // Show max 3 conflicts
                    $error_message .= "<br>- \"{$conflict['title']}\" on {$conflict['date']} from {$conflict['start']} to {$conflict['end']} at {$conflict['location']}";
                }
            }
            if (count($conflicts) > 3) {
                $error_message .= "<br>...and " . (count($conflicts) - 3) . " more conflicts.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssi', $title, $description, $event_date, $start_time, $end_time, $location, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $event_id = $conn->insert_id;
                // Track activity
                trackEventActivity($conn, $event_id, $title, 'added');
                
                // Set success message in session to persist after redirect
                $_SESSION['success_message'] = "Event added successfully!";
                
                // Redirect to refresh the page and show updated data
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error adding event: " . $conn->error;
            }
        }
        $conn->close();
    }
}

// Update existing event
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = $_POST['location'];
    
    // Validate end time is after start time
    if ($end_time <= $start_time) {
        $error_message = "End time must be after start time.";
    } else {
        $conn = getDBConnection();
        
        // Check if event belongs to the current user
        $stmt = $conn->prepare("SELECT created_by FROM events WHERE id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        
        if ($event && $event['created_by'] == $_SESSION['user_id']) {
            // Check for time conflicts, excluding this event
            $conflicts = checkTimeConflict($conn, $event_date, $start_time, $end_time, $location, $event_id);
            
            if ($conflicts) {
                $error_message = "Time conflict detected with existing events. Please choose a different time.";
                foreach ($conflicts as $index => $conflict) {
                    if ($index < 3) { // Show max 3 conflicts
                        $error_message .= "<br>- \"{$conflict['title']}\" on {$conflict['date']} from {$conflict['start']} to {$conflict['end']} at {$conflict['location']}";
                    }
                }
                if (count($conflicts) > 3) {
                    $error_message .= "<br>...and " . (count($conflicts) - 3) . " more conflicts.";
                }
            } else {
                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, location = ? WHERE id = ?");
                $stmt->bind_param('ssssssi', $title, $description, $event_date, $start_time, $end_time, $location, $event_id);
                
                if ($stmt->execute()) {
                    // Track activity
                    trackEventActivity($conn, $event_id, $title, 'updated');
                    
                    // Set success message in session to persist after redirect
                    $_SESSION['success_message'] = "Event updated successfully!";
                    
                    // Redirect to refresh the page and show updated data
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $error_message = "Error updating event: " . $conn->error;
                }
            }
        } else {
            $error_message = "You do not have permission to update this event!";
        }
        $conn->close();
    }
}

// Delete event
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    
    $conn = getDBConnection();
    
    // Check if event belongs to the current user
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
    $stmt->bind_param('ii', $event_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if ($event) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Save event title for activity tracking
            $event_title = $event['title'];
            
            // Insert into archived_events
            $archive_stmt = $conn->prepare("INSERT INTO archived_events 
                (original_id, title, description, event_date, start_time, end_time, location, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $archive_stmt->bind_param('isssssss', 
                $event['id'], 
                $event['title'], 
                $event['description'], 
                $event['event_date'], 
                $event['start_time'], 
                $event['end_time'], 
                $event['location'], 
                $event['created_by']
            );
            $archive_stmt->execute();
            
            // Track activity before deleting
            trackEventActivity($conn, $event_id, $event_title, 'deleted');
            
            // Delete from events table
            $delete_stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $delete_stmt->bind_param('i', $event_id);
            $delete_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Set success message in session to persist after redirect
            $_SESSION['success_message'] = "Event deleted successfully!";
            
            // Redirect to refresh the page and show updated data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            $error_message = "Error deleting event: " . $e->getMessage();
        }
    } else {
        $error_message = "You do not have permission to delete this event!";
    }
    $conn->close();
}

// Get all events created by this user
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM events WHERE created_by = ? ORDER BY event_date DESC, start_time ASC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$conn->close();

// Check for session messages (success/error)
$success_message = '';
$notification_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['activity_notification'])) {
    $notification_message = $_SESSION['activity_notification'];
    unset($_SESSION['activity_notification']);
}

// Get event for editing if specified
$edit_event = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
    $stmt->bind_param('ii', $edit_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_event = $result->fetch_assoc();
    }
    $conn->close();
}

// Location options
$location_options = [
    'NBSC Covered Court',
    'SC Building',
    'NBSC Field',
    'Science Lab',
    'Speech Lab',
    'IT Lab 1',
    'IT Lab 2'
];
?>

<!-- Display messages -->
<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($notification_message)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> <?php echo $notification_message; ?>
    </div>
<?php endif; ?>

<div class="events-management">
    <div class="event-form-container">
        <h2>Add New Event</h2>
        <form method="post" class="event-form" id="add-event-form">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required 
                    value="<?php echo isset($_POST['add_event']) && isset($title) ? htmlspecialchars($title) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo isset($_POST['add_event']) && isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="event_date">Date</label>
                    <input type="date" id="event_date" name="event_date" required
                        value="<?php echo isset($_POST['add_event']) && isset($event_date) ? htmlspecialchars($event_date) : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" required
                        value="<?php echo isset($_POST['add_event']) && isset($start_time) ? htmlspecialchars($start_time) : ''; ?>">
                </div>
                
                <div class="form-group half">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" required
                        value="<?php echo isset($_POST['add_event']) && isset($end_time) ? htmlspecialchars($end_time) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <select id="location" name="location" required>
                    <option value="" disabled selected>Select a location</option>
                    <?php foreach($location_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" 
                            <?php echo (isset($_POST['add_event']) && isset($location) && $location === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
            </div>
        </form>
    </div>
    
    <div class="events-list-container">
        <h2>Your Events</h2>
        
        <?php if (empty($events)): ?>
            <p class="no-events">You haven't created any events yet.</p>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-date">
                            <?php echo date('M d', strtotime($event['event_date'])); ?>
                            <div class="event-year"><?php echo date('Y', strtotime($event['event_date'])); ?></div>
                        </div>
                        <div class="event-details">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars($event['description']); ?></p>
                            <div class="event-meta">
                                <span class="event-time">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('h:i A', strtotime($event['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                                </span>
                                <span class="event-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                </span>
                            </div>
                            <div class="event-actions">
                                <div class="event-more-menu">
                                    <button class="more-btn"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a href="#" onclick="openEditModal(<?php echo $event['id']; ?>); return false;" class="menu-item">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="delete_event" class="menu-item text-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Event Modal -->
<div id="editEventModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Edit Event</h2>
        <form method="post" id="edit-event-form">
            <input type="hidden" id="edit_event_id" name="event_id">
            
            <div class="form-group">
                <label for="edit_title">Title</label>
                <input type="text" id="edit_title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" rows="4"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="edit_event_date">Date</label>
                    <input type="date" id="edit_event_date" name="event_date" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="edit_start_time">Start Time</label>
                    <input type="time" id="edit_start_time" name="start_time" required>
                </div>
                
                <div class="form-group half">
                    <label for="edit_end_time">End Time</label>
                    <input type="time" id="edit_end_time" name="end_time" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_location">Location</label>
                <select id="edit_location" name="location" required>
                    <?php foreach($location_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>">
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_event" class="btn btn-primary">Update Event</button>
                <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
                <button type="submit" name="delete_event" class="btn btn-danger" style="margin-left: auto;" onclick="return confirm('Are you sure you want to delete this event?');">Delete Event</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal handling
const modal = document.getElementById('editEventModal');
const closeModalBtn = document.getElementsByClassName('close-modal')[0];
const cancelBtn = document.getElementsByClassName('close-modal-btn')[0];

function openEditModal(eventId) {
    // Fetch event data and populate the form
    fetch(`get_event.php?id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_event_id').value = data.event.id;
                document.getElementById('edit_title').value = data.event.title;
                document.getElementById('edit_description').value = data.event.description;
                document.getElementById('edit_event_date').value = data.event.event_date;
                document.getElementById('edit_start_time').value = data.event.start_time;
                document.getElementById('edit_end_time').value = data.event.end_time;
                document.getElementById('edit_location').value = data.event.location;
                
                // Show the modal
                modal.style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Fallback if fetch fails - use direct data loading
            const events = <?php echo json_encode($events); ?>;
            const event = events.find(e => e.id == eventId);
            
            if (event) {
                document.getElementById('edit_event_id').value = event.id;
                document.getElementById('edit_title').value = event.title;
                document.getElementById('edit_description').value = event.description;
                document.getElementById('edit_event_date').value = event.event_date;
                document.getElementById('edit_start_time').value = event.start_time;
                document.getElementById('edit_end_time').value = event.end_time;
                document.getElementById('edit_location').value = event.location;
                
                // Show the modal
                modal.style.display = 'block';
            } else {
                alert('Error loading event data.');
            }
        });
}

// Close the modal
function closeModal() {
    modal.style.display = 'none';
}

closeModalBtn.addEventListener('click', closeModal);
cancelBtn.addEventListener('click', closeModal);

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    if (event.target == modal) {
        closeModal();
    }
});

// Close modal when ESC key is pressed
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closeModal();
    }
});

// Handle dropdown menus for event cards
document.querySelectorAll('.more-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdown menus
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu !== this.nextElementSibling) {
                menu.classList.remove('active');
            }
        });
        
        // Toggle this dropdown
        const dropdown = this.nextElementSibling;
        dropdown.classList.toggle('active');
    });
});

// Close all dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('active');
    });
});

// Handle success message auto-dismiss
const successAlert = document.querySelector('.alert-success');
if (successAlert) {
    setTimeout(() => {
        successAlert.style.opacity = '0';
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 500);
    }, 3000);
}

// If there was a successful update or delete, close the modal
<?php if (isset($success_message) && (isset($_POST['update_event']) || isset($_POST['delete_event']))): ?>
    // Close the modal after successful update/delete
    document.addEventListener('DOMContentLoaded', function() {
        closeModal();
    });
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    // Time validation for add form
    document.getElementById('add-event-form').addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (endTime <= startTime) {
            e.preventDefault();
            alert('End time must be after start time.');
        }
    });
    
    // Time validation for edit form
    document.getElementById('edit-event-form').addEventListener('submit', function(e) {
        const startTime = document.getElementById('edit_start_time').value;
        const endTime = document.getElementById('edit_end_time').value;
        
        if (endTime <= startTime) {
            e.preventDefault();
            alert('End time must be after start time.');
        }
    });
});
</script>

<style>
.events-management {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

.event-form-container {
    flex: 1;
    min-width: 400px;
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.events-list-container {
    flex: 1;
    min-width: 400px;
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 15px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group.half {
    flex: 1;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

input[type="text"],
input[type="date"],
input[type="time"],
textarea,
select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

textarea {
    resize: vertical;
}

.form-actions {
    margin-top: 20px;
}

.btn {
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-primary {
    background: linear-gradient(90deg, #f7c948 0%, #e2b007 100%);
    color: #222;
}

.btn-primary:hover {
    background: linear-gradient(90deg, #e2b007 0%, #f7c948 100%);
}

.btn-secondary {
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: inline-block;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background: #eee;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.9em;
}

.btn-edit {
    background: #2196F3;
    color: white;
    text-decoration: none;
    display: inline-block;
    margin-right: 6px;
}

.btn-edit:hover {
    background: #0d8aee;
}

.btn-delete {
    background: #d50000;
    color: white;
}

.btn-delete:hover {
    background: #b71c1c;
}

.btn-danger {
    background: #d50000;
    color: white;
}

.btn-danger:hover {
    background: #b71c1c;
}

.events-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.event-card {
    display: flex;
    background: #f5f5f5;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 16px;
}

.event-date {
    background: #ff9800;
    color: white;
    padding: 10px;
    text-align: center;
    min-width: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.event-year {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-top: 3px;
}

.event-details {
    padding: 15px;
    flex: 1;
}

.event-details h3 {
    margin: 0 0 10px 0;
}

.event-details p {
    margin: 0 0 10px 0;
    color: #666;
}

.event-meta {
    display: flex;
    gap: 15px;
    color: #888;
    margin-bottom: 10px;
}

.event-actions {
    display: flex;
    align-items: center;
}

.no-events {
    color: #888;
    font-style: italic;
    text-align: center;
}

@media (max-width: 900px) {
    .events-management {
        flex-direction: column;
    }
    
    .event-form-container,
    .events-list-container {
        min-width: 100%;
    }
}

.event-more-menu {
    position: relative;
}

.more-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: #666;
    cursor: pointer;
    padding: 5px;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    z-index: 10;
    min-width: 120px;
    display: none;
}

.dropdown-menu.active {
    display: block;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    transition: background 0.2s;
    cursor: pointer;
}

.menu-item:hover {
    background: #f5f5f5;
}

.menu-item i {
    font-size: 0.9rem;
}

.text-danger {
    color: #d50000;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow: auto;
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    width: 80%;
    max-width: 600px;
    position: relative;
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close-modal:hover,
.close-modal:focus {
    color: #555;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    animation: fadeInDown 0.5s;
}

.alert i {
    margin-right: 10px;
    font-size: 1.2rem;
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

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style> 