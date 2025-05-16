<?php
session_start();
// Security check - must be logged in AND verified
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header('Location: ../login.php');
    exit();
}

// Get user ID from URL parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// If no user_id provided, use the logged-in user
if ($user_id === 0) {
    $user_id = $_SESSION['user_id'];
}

// Include database connection
require_once '../config.php';
$conn = getDBConnection();

// Get user information
$stmt = $conn->prepare("SELECT club_name, email, profile_picture, description, role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Check if user exists
if (!$user) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : 'dashboard.php'));
    exit();
}

// Get events created by the user
$stmt = $conn->prepare("SELECT * FROM events WHERE created_by = ? ORDER BY event_date DESC, start_time ASC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$events_result = $stmt->get_result();
$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}

// Get archived events for this user if the viewer is admin
$archived_events = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $conn->prepare("SELECT * FROM archived_events WHERE created_by = ? ORDER BY archived_at DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $archived_result = $stmt->get_result();
    while ($row = $archived_result->fetch_assoc()) {
        $archived_events[] = $row;
    }
}

$conn->close();

// Determine if viewing own events or someone else's
$viewing_own = ($_SESSION['user_id'] == $user_id);
$is_admin = ($_SESSION['role'] === 'admin');

// Set page title based on context
$page_title = $viewing_own ? "My Events" : "Events by " . htmlspecialchars($user['club_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f0f0f0;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .user-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #ff9800;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: bold;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .user-role {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .user-email {
            font-size: 0.95rem;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-description {
            margin-top: 10px;
            color: #555;
            line-height: 1.5;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 30px 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .events-count {
            background-color: #ff9800;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .event-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .event-header {
            background-color: #ff9800;
            color: white;
            padding: 15px;
        }
        
        .event-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .event-date {
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .event-body {
            padding: 15px;
        }
        
        .event-time, .event-location {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #555;
        }
        
        .event-description {
            margin-top: 15px;
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .no-events {
            background-color: white;
            padding: 40px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            color: #666;
        }
        
        .archived-event-card {
            opacity: 0.7;
            position: relative;
        }
        
        .archived-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #f44336;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                align-items: flex-start;
                text-align: center;
            }
            
            .user-avatar {
                margin: 0 auto 15px auto;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?php echo $is_admin ? '../admin/dashboard.php' : 'dashboard.php'; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="user-header">
            <div class="user-avatar">
                <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['club_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h1 class="user-name"><?php echo htmlspecialchars($user['club_name']); ?></h1>
                <div class="user-role">
                    <span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <div class="user-email">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                </div>
                <?php if (!empty($user['description'])): ?>
                    <div class="user-description"><?php echo htmlspecialchars($user['description']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Current Events Section -->
        <div class="section-title">
            <span>Current Events</span>
            <span class="events-count"><?php echo count($events); ?></span>
        </div>
        
        <?php if (empty($events)): ?>
            <div class="no-events">
                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                <p>No events found for this user.</p>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-header">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-date">
                                <i class="fas fa-calendar-day"></i> 
                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <div class="event-body">
                            <div class="event-time">
                                <i class="fas fa-clock"></i>
                                <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                            </div>
                            <div class="event-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </div>
                            <div class="event-description">
                                <?php echo htmlspecialchars(substr($event['description'], 0, 150)); ?>
                                <?php if (strlen($event['description']) > 150): ?>...<?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Archived Events Section (Admin Only) -->
        <?php if ($is_admin && !empty($archived_events)): ?>
            <div class="section-title">
                <span>Archived Events</span>
                <span class="events-count"><?php echo count($archived_events); ?></span>
            </div>
            
            <div class="events-grid">
                <?php foreach ($archived_events as $event): ?>
                    <div class="event-card archived-event-card">
                        <div class="archived-badge">Archived</div>
                        <div class="event-header">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-date">
                                <i class="fas fa-calendar-day"></i> 
                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <div class="event-body">
                            <div class="event-time">
                                <i class="fas fa-clock"></i>
                                <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                            </div>
                            <div class="event-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </div>
                            <div class="event-description">
                                <?php echo htmlspecialchars(substr($event['description'], 0, 150)); ?>
                                <?php if (strlen($event['description']) > 150): ?>...<?php endif; ?>
                            </div>
                            <div style="margin-top: 10px; color: #999; font-size: 0.85rem;">
                                <i class="fas fa-archive"></i> Archived on: <?php echo date('F j, Y g:i A', strtotime($event['archived_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 