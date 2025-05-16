<?php
// Strict security check - must be logged in AND verified as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get user info
require_once '../config.php';
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT club_name, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$conn->close();

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            margin: 0; 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: #f5f5f5; 
        }
        .navbar { 
            background: #ff9800; 
            color: #fff; 
            padding: 0; 
            display: flex; 
            align-items: center; 
            height: 56px; 
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .menu-icon { 
            font-size: 2rem; 
            margin: 0 18px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
        }
        .navbar-title { 
            font-size: 2rem; 
            font-weight: 700; 
            letter-spacing: 1px; 
            margin-left: 0; 
            display: flex; 
            flex-direction: column; 
            line-height: 1.1; 
        }
        .navbar-subtitle { 
            font-size: 0.8rem; 
            font-weight: 400; 
            margin-bottom: -2px; 
        }
        .profile-icon { 
            margin-left: auto; 
            margin-right: 18px; 
            font-size: 1.7rem; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
        }
        .sidebar-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.2); 
            z-index: 1000; 
            display: none; 
        }
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 300px; 
            height: 100vh; 
            background: linear-gradient(to bottom, #a85b00 0%, #ff9800 100%); 
            color: #fff; 
            z-index: 1100; 
            transform: translateX(-100%); 
            transition: transform 0.3s cubic-bezier(.4,0,.2,1); 
            box-shadow: 2px 0 16px rgba(0,0,0,0.10); 
            display: flex; 
            flex-direction: column; 
        }
        .sidebar.open { 
            transform: translateX(0); 
        }
        .sidebar-header { 
            padding: 18px 16px 8px 16px; 
        }
        .sidebar-title { 
            font-size: 1.6rem; 
            font-weight: 700; 
            letter-spacing: 1px; 
            margin-left: 0; 
            display: flex; 
            flex-direction: column; 
            line-height: 1.1; 
        }
        .sidebar-subtitle { 
            font-size: 0.8rem; 
            font-weight: 400; 
            margin-bottom: -2px; 
        }
        .sidebar-menu { 
            display: flex; 
            flex-direction: column; 
            margin-top: 64px; 
            gap: 32px; 
            padding-left: 32px; 
        }
        .sidebar-menu-item { 
            font-size: 1rem; 
            color: #fff; 
            text-decoration: none; 
            letter-spacing: 0.5px; 
            cursor: pointer; 
            transition: color 0.2s; 
            font-weight: 500; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-menu-item:hover { 
            color: #ffe033; 
        }
        .sidebar-menu-item.active {
            color: #ffe033;
            font-weight: 600;
        }
        .logout-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .logout-modal {
            background: #fff;
            border-radius: 6px;
            padding: 32px 28px 24px 28px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.18);
            min-width: 260px;
            text-align: left;
        }
        .logout-modal p {
            margin: 0 0 24px 0;
            font-size: 1.08rem;
            color: #222;
        }
        .logout-modal-btns {
            display: flex;
            gap: 16px;
            justify-content: flex-start;
        }
        .logout-cancel-btn {
            background: #fff;
            color: #222;
            border: 1px solid #888;
            border-radius: 6px;
            padding: 7px 18px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .logout-cancel-btn:hover {
            background: #eee;
        }
        .logout-btn {
            background: #d50000;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 7px 18px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background: #b71c1c;
        }
        .content-wrapper {
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Logout Modal -->
    <div class="logout-modal-overlay" id="logoutModalOverlay">
        <div class="logout-modal">
            <p>Logout Account?</p>
            <div class="logout-modal-btns">
                <button class="logout-cancel-btn" id="cancelLogoutBtn">CANCEL</button>
                <form method="post" style="display:inline;">
                    <button class="logout-btn" name="logout" type="submit">LOGOUT</button>
                </form>
            </div>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-subtitle">NBSC</span>
            <span class="sidebar-title">RCO CONNECT</span>
        </div>
        <div class="sidebar-menu">
            <a class="sidebar-menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="../admin/dashboard.php">
                <i class="fas fa-chart-bar"></i> TOP RANKING RCOs
            </a>
            <a class="sidebar-menu-item <?php echo ($current_page == 'upcoming_events.php') ? 'active' : ''; ?>" href="../admin_components/upcoming_events.php">
                <i class="fas fa-calendar-alt"></i> UPCOMING EVENTS
            </a>
            <a class="sidebar-menu-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="../admin_components/manage_users.php">
                <i class="fas fa-users"></i> MANAGE USERS
            </a>
            <a class="sidebar-menu-item <?php echo ($current_page == 'user_activities.php') ? 'active' : ''; ?>" href="../admin_components/user_activities.php">
                <i class="fas fa-history"></i> USER ACTIVITIES
            </a>
        </div>
    </nav>
    <div class="navbar">
        <div class="menu-icon" title="Menu" id="menuBtn">
            <i class="fas fa-bars"></i>
        </div>
        <div class="navbar-title">
            <span class="navbar-subtitle">NBSC</span>
            RCO CONNECT
        </div>
        <div class="profile-icon" title="Profile" id="profileIcon">
            <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                <span style="display:inline-block;width:28px;height:28px;border-radius:50%;overflow:hidden;">
                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                </span>
            <?php else: ?>
                <span style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#fff3e0;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user" style="color:#333;"></i>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-wrapper">
        <!-- Page content will be placed here -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar and overlay handling
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.getElementById('menuBtn');
            
            menuBtn.addEventListener('click', function() {
                sidebar.classList.add('open');
                sidebarOverlay.style.display = 'block';
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                sidebarOverlay.style.display = 'none';
            });
            
            // Logout modal handling
            const logoutModalOverlay = document.getElementById('logoutModalOverlay');
            const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
            
            if (cancelLogoutBtn) {
                cancelLogoutBtn.addEventListener('click', function() {
                    logoutModalOverlay.style.display = 'none';
                });
            }
            
            // Profile icon link - directly navigate to profile page
            const profileIcon = document.getElementById('profileIcon');
            profileIcon.addEventListener('click', function() {
                window.location.href = '../admin_components/profile.php';
            });
        });
    </script>
</body>
</html> 