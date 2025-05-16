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

// Handle user activation/deactivation
if (isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status']; // 'active' or 'inactive'
    
    // Prevent admin from deactivating their own account
    if ($user_id == $_SESSION['user_id'] && $new_status === 'inactive') {
        $error_message = "You cannot deactivate your own account.";
    } else {
        $conn = getDBConnection();
        $is_active = ($new_status === 'active') ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $is_active, $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success_message = "User status updated successfully!";
        } else {
            $error_message = "Failed to update user status.";
        }
        
        $conn->close();
    }
}

// Get all users
$conn = getDBConnection();

// Get sorting parameters
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Validate sort parameters to prevent SQL injection
$allowed_sort_fields = ['club_name', 'email', 'created_at', 'is_active'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$allowed_sort_orders = ['ASC', 'DESC'];
if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'DESC';
}

// Build the query with search functionality
$query = "SELECT id, club_name, email, role, created_at, is_active, profile_picture, description 
          FROM users WHERE role != 'admin'";

// Add search condition if search term is provided
if (!empty($search_term)) {
    $query .= " AND (club_name LIKE ? OR email LIKE ?)";
}

// Add sorting
$query .= " ORDER BY $sort_by $sort_order";

$stmt = $conn->prepare($query);

// Bind search parameters if necessary
if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param('ss', $search_param, $search_param);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total events count per user
$events_count = [];
$stmt = $conn->prepare("SELECT created_by, COUNT(*) as count FROM events GROUP BY created_by");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $events_count[$row['created_by']] = $row['count'];
}

$conn->close();

// Include admin header
include 'admin_header.php';
?>

<div class="main-content">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="manage-header">
        <div class="manage-title">MANAGE USERS</div>
        <div class="manage-actions">
            <form method="get" class="search-form">
                <div class="search-container">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <div class="sort-container">
                <label for="sort_by">Sort by:</label>
                <select name="sort_by" id="sort_by" onchange="updateSort()">
                    <option value="club_name" <?php echo $sort_by === 'club_name' ? 'selected' : ''; ?>>Club Name</option>
                    <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Joined</option>
                    <option value="is_active" <?php echo $sort_by === 'is_active' ? 'selected' : ''; ?>>Status</option>
                </select>
                <select name="sort_order" id="sort_order" onchange="updateSort()">
                    <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="user-count"><?php echo count($users); ?> user<?php echo count($users) !== 1 ? 's' : ''; ?> found</div>
    
    <?php if (empty($users)): ?>
        <div class="no-users">No users found. <?php echo !empty($search_term) ? 'Try a different search term.' : ''; ?></div>
    <?php else: ?>
        <div class="user-list">
            <?php foreach ($users as $user): ?>
                <div class="user-item <?php echo isset($user['is_active']) && $user['is_active'] == 0 ? 'inactive-user' : ''; ?>">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-img">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($user['club_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name-section">
                            <span class="user-name"><?php echo htmlspecialchars($user['club_name']); ?></span>
                            <?php if (isset($user['is_active']) && $user['is_active'] == 0): ?>
                                <span class="status-badge inactive">Inactive</span>
                            <?php else: ?>
                                <span class="status-badge active">Active</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <span class="user-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                            <span class="user-joined"><i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            <span class="user-events"><i class="fas fa-calendar-check"></i> Events: <?php echo $events_count[$user['id']] ?? 0; ?></span>
                        </div>
                        <?php if (!empty($user['description'])): ?>
                            <div class="user-description"><?php echo htmlspecialchars(substr($user['description'], 0, 100)); ?><?php echo strlen($user['description']) > 100 ? '...' : ''; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="user-actions">
                        <form method="post" class="status-form">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            
                            <?php if (isset($user['is_active']) && $user['is_active'] == 0): ?>
                                <input type="hidden" name="new_status" value="active">
                                <button type="submit" name="toggle_status" class="activate-btn" title="Activate User">
                                    <i class="fas fa-user-check"></i> ACTIVATE
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="inactive">
                                <button type="submit" name="toggle_status" class="deactivate-btn" title="Deactivate User">
                                    <i class="fas fa-user-slash"></i> DEACTIVATE
                                </button>
                            <?php endif; ?>
                        </form>
                        <a href="../user/view_events.php?user_id=<?php echo $user['id']; ?>" class="view-btn" title="View User's Events">
                            <i class="fas fa-calendar-alt"></i> EVENTS
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .main-content {
        padding: 30px 40px;
    }
    
    .manage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .manage-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #111;
    }
    
    .manage-actions {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-container {
        display: flex;
        align-items: center;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .search-container input {
        padding: 8px 12px;
        border: none;
        outline: none;
        width: 200px;
    }
    
    .search-container button {
        background: none;
        border: none;
        padding: 8px 12px;
        cursor: pointer;
        color: #666;
    }
    
    .sort-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sort-container select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: white;
    }
    
    .user-count {
        color: #666;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }
    
    .no-users {
        background: #f5f5f5;
        padding: 40px;
        text-align: center;
        border-radius: 8px;
        color: #666;
        font-size: 1.1rem;
    }
    
    .user-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
        max-width: 900px;
    }
    
    .user-item {
        display: flex;
        align-items: flex-start;
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        gap: 20px;
        border-left: 5px solid #4CAF50;
    }
    
    .inactive-user {
        border-left-color: #9e9e9e;
        opacity: 0.8;
    }
    
    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #e0e0e0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.7rem;
        color: #bdbdbd;
        overflow: hidden;
    }
    
    .profile-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #ff9800;
        color: white;
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .user-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .user-name-section {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 5px;
    }
    
    .user-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #222;
    }
    
    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-badge.active {
        background-color: rgba(76, 175, 80, 0.15);
        color: #2e7d32;
    }
    
    .status-badge.inactive {
        background-color: rgba(158, 158, 158, 0.15);
        color: #424242;
    }
    
    .user-details {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }
    
    .user-email, .user-joined, .user-events {
        font-size: 0.9rem;
        color: #666;
    }
    
    .user-description {
        font-size: 0.9rem;
        color: #777;
        line-height: 1.4;
        margin-top: 5px;
    }
    
    .user-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .deactivate-btn, .activate-btn, .view-btn {
        color: white;
        border: none;
        border-radius: 6px;
        padding: 7px 15px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .deactivate-btn {
        background: #d50000;
    }
    
    .deactivate-btn:hover {
        background: #b71c1c;
    }
    
    .activate-btn {
        background: #00c853;
    }
    
    .activate-btn:hover {
        background: #009624;
    }
    
    .view-btn {
        background: #2196F3;
    }
    
    .view-btn:hover {
        background: #1976D2;
    }
    
    /* Alert messages */
    .alert {
        padding: 15px;
        margin: 15px 0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
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
    
    @media (max-width: 900px) {
        .main-content {
            padding-left: 20px;
            padding-right: 20px;
        }
        
        .manage-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .search-container input {
            width: 100%;
        }
        
        .user-list {
            width: 100%;
            max-width: 100%;
        }
        
        .user-item {
            flex-direction: column;
        }
        
        .user-avatar {
            margin-bottom: 10px;
        }
        
        .user-actions {
            flex-direction: row;
            width: 100%;
            margin-top: 15px;
        }
        
        .deactivate-btn, .activate-btn, .view-btn {
            flex: 1;
            text-align: center;
        }
    }
</style>

<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    });
    
    // Update sorting
    function updateSort() {
        const sortBy = document.getElementById('sort_by').value;
        const sortOrder = document.getElementById('sort_order').value;
        const searchParams = new URLSearchParams(window.location.search);
        
        searchParams.set('sort_by', sortBy);
        searchParams.set('sort_order', sortOrder);
        
        window.location.href = window.location.pathname + '?' + searchParams.toString();
    }
</script>