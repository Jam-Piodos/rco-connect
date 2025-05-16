<?php
session_start();
// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}
// Strict security check - must be logged in AND verified
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header('Location: ../login.php');
    exit();
}

require '../config.php';

// Get filter parameters
$club_filter = isset($_GET['club']) ? $_GET['club'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';

// Get all club names for filter dropdown
$conn = getDBConnection();
$club_stmt = $conn->prepare("SELECT DISTINCT u.id, u.club_name FROM users u 
                           JOIN user_activities ua ON u.id = ua.user_id 
                           WHERE u.role = 'user' 
                           ORDER BY u.club_name");
$club_stmt->execute();
$clubs = $club_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get date filter parameters
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Prepare base query for activities
$query = "
    SELECT 
        ua.id,
        ua.event_id,
        ua.event_title,
        ua.action_type,
        ua.action_time,
        u.club_name,
        u.profile_picture
    FROM 
        user_activities ua
    JOIN 
        users u ON ua.user_id = u.id
    WHERE 1=1
";

// Apply filters
$params = [];
$types = '';

if (!empty($club_filter)) {
    $query .= " AND u.id = ?";
    $params[] = $club_filter;
    $types .= 'i';
}

if (!empty($action_filter)) {
    $query .= " AND ua.action_type = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    switch($date_filter) {
        case 'today':
            $query .= " AND DATE(ua.action_time) = CURDATE()";
            break;
        case 'yesterday':
            $query .= " AND DATE(ua.action_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $query .= " AND YEARWEEK(ua.action_time, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'this_month':
            $query .= " AND MONTH(ua.action_time) = MONTH(CURDATE()) AND YEAR(ua.action_time) = YEAR(CURDATE())";
            break;
    }
}

// Finalize query with ordering
$query .= " ORDER BY ua.action_time DESC LIMIT 100";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get activity count statistics
$stats_query = "
    SELECT 
        action_type, 
        COUNT(*) as count
    FROM 
        user_activities
    GROUP BY 
        action_type
";
$stats_result = $conn->query($stats_query);
$activity_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $activity_stats[$row['action_type']] = $row['count'];
}

$conn->close();

// Include admin header
include 'admin_header.php';
?>

<div class="main-content">
    <div class="activities-header">
        <div class="activities-title">USER ACTIVITIES</div>
        <div class="activities-stats">
            <div class="stat-box stat-added">
                <div class="stat-number"><?php echo $activity_stats['added'] ?? 0; ?></div>
                <div class="stat-label">Added</div>
            </div>
            <div class="stat-box stat-updated">
                <div class="stat-number"><?php echo $activity_stats['updated'] ?? 0; ?></div>
                <div class="stat-label">Updated</div>
            </div>
            <div class="stat-box stat-deleted">
                <div class="stat-number"><?php echo $activity_stats['deleted'] ?? 0; ?></div>
                <div class="stat-label">Deleted</div>
            </div>
        </div>
        <div class="activities-filter">
            <form method="GET" action="" id="filterForm">
                <div class="filter-group">
                    <label for="club">Filter by Club:</label>
                    <select name="club" id="club" onchange="this.form.submit()">
                        <option value="">All Clubs</option>
                        <?php foreach ($clubs as $club): ?>
                            <option value="<?php echo $club['id']; ?>" <?php echo ($club_filter == $club['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="action">Filter by Action:</label>
                    <select name="action" id="action" onchange="this.form.submit()">
                        <option value="">All Actions</option>
                        <option value="added" <?php echo ($action_filter == 'added') ? 'selected' : ''; ?>>Added</option>
                        <option value="updated" <?php echo ($action_filter == 'updated') ? 'selected' : ''; ?>>Updated</option>
                        <option value="deleted" <?php echo ($action_filter == 'deleted') ? 'selected' : ''; ?>>Deleted</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date_filter">Time Period:</label>
                    <select name="date_filter" id="date_filter" onchange="this.form.submit()">
                        <option value="">All Time</option>
                        <option value="today" <?php echo ($date_filter == 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo ($date_filter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo ($date_filter == 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo ($date_filter == 'this_month') ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                <?php if (!empty($club_filter) || !empty($action_filter) || !empty($date_filter)): ?>
                    <a href="user_activities.php" class="reset-filter">Reset Filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="activity-list">
        <?php if (empty($activities)): ?>
            <div class="no-activities">No activities found with the current filters.</div>
        <?php else: ?>
            <?php foreach ($activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-avatar">
                        <?php if (!empty($activity['profile_picture']) && file_exists('../' . $activity['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($activity['profile_picture']); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($activity['club_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="activity-info">
                        <span class="activity-name"><?php echo htmlspecialchars($activity['club_name']); ?></span>
                        <span class="activity-role"><?php echo htmlspecialchars($activity['event_title']); ?></span>
                    </div>
                    <div class="activity-action activity-<?php echo $activity['action_type']; ?>">
                        <?php echo strtoupper($activity['action_type']); ?> 
                        <?php if ($activity['action_type'] == 'deleted'): ?>
                            <i class="fas fa-trash-alt"></i>
                        <?php elseif ($activity['action_type'] == 'updated'): ?>
                            <i class="fas fa-edit"></i>
                        <?php else: ?>
                            <i class="fas fa-calendar-plus"></i>
                        <?php endif; ?>
                    </div>
                    <div class="activity-date"><?php echo date('F j, Y g:i A', strtotime($activity['action_time'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .main-content {
        padding: 30px 40px;
        max-width: 100%;
    }
    
    .activities-header {
        display: flex;
        flex-direction: column;
        margin-bottom: 30px;
    }
    
    .activities-title {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 20px;
    }
    
    .activities-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        width: 120px;
        color: white;
    }
    
    .stat-added {
        background-color: #4CAF50;
    }
    
    .stat-updated {
        background-color: #2196F3;
    }
    
    .stat-deleted {
        background-color: #F44336;
    }
    
    .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.9rem;
    }
    
    .activities-filter {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }
    
    .filter-group label {
        font-size: 0.85rem;
        margin-bottom: 5px;
        color: #555;
    }
    
    .filter-group select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: white;
    }
    
    .reset-filter {
        padding: 8px 15px;
        background-color: #f5f5f5;
        border-radius: 4px;
        text-decoration: none;
        color: #555;
        font-size: 0.9rem;
        align-self: flex-end;
    }
    
    .reset-filter:hover {
        background-color: #e0e0e0;
    }
    
    .activity-list {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        width: 100%;
        max-width: 1000px;
    }
    
    .activity-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-avatar {
        width: 40px;
        height: 40px;
        margin-right: 15px;
    }
    
    .activity-avatar .profile-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background-color: #ff9800;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border-radius: 50%;
    }
    
    .activity-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .activity-name {
        font-weight: 600;
        color: #333;
    }
    
    .activity-role {
        font-size: 0.9rem;
        color: #666;
    }
    
    .activity-action {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin: 0 15px;
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 80px;
        justify-content: center;
    }
    
    .activity-added {
        background-color: rgba(76, 175, 80, 0.15);
        color: #2e7d32;
    }
    
    .activity-updated {
        background-color: rgba(33, 150, 243, 0.15);
        color: #0d47a1;
    }
    
    .activity-deleted {
        background-color: rgba(244, 67, 54, 0.15);
        color: #c62828;
    }
    
    .activity-date {
        color: #777;
        font-size: 0.85rem;
        min-width: 180px;
        text-align: right;
    }
    
    .no-activities {
        padding: 40px 20px;
        text-align: center;
        color: #666;
        font-size: 1.1rem;
    }
    
    @media (max-width: 900px) {
        .main-content {
            padding-left: 20px;
            padding-right: 20px;
        }
        
        .activities-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .activities-stats {
            justify-content: space-between;
            width: 100%;
        }
        
        .stat-box {
            width: 30%;
        }
        
        .activities-filter {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .filter-group select {
            width: 100%;
        }
        
        .activity-list {
            width: 100%;
            max-width: 100%;
        }
        
        .activity-item {
            flex-wrap: wrap;
        }
        
        .activity-action {
            padding: 4px 8px;
            margin-left: 55px;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        
        .activity-date {
            width: 100%;
            text-align: left;
            margin-left: 55px;
            margin-top: 5px;
        }
    }
</style> 