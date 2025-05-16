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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Include RCO ranking data
require_once '../shared_components/rco_ranking_data.php';

if (isset($_POST['logout'])) {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Get user info and profile picture
require '../config.php';
$conn = getDBConnection();

// Get basic user info
$stmt = $conn->prepare("SELECT club_name, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get RCO ranking data
$rco_data = getTopRCOsData(5);

$conn->close();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #fff; }
        .navbar { background: #ff9800; color: #fff; padding: 0; display: flex; align-items: center; height: 56px; position: relative; }
        .menu-icon { font-size: 2rem; margin: 0 18px; cursor: pointer; display: flex; align-items: center; }
        .navbar-title { font-size: 2rem; font-weight: 700; letter-spacing: 1px; margin-left: 0; display: flex; flex-direction: column; line-height: 1.1; }
        .navbar-subtitle { font-size: 0.8rem; font-weight: 400; margin-bottom: -2px; }
        .profile-icon { margin-left: auto; margin-right: 18px; font-size: 1.7rem; cursor: pointer; display: flex; align-items: center; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.2); z-index: 1000; display: none; }
        .sidebar { position: fixed; top: 0; left: 0; width: 300px; height: 100vh; background: linear-gradient(to bottom, #a85b00 0%, #ff9800 100%); color: #fff; z-index: 1100; transform: translateX(-100%); transition: transform 0.3s cubic-bezier(.4,0,.2,1); box-shadow: 2px 0 16px rgba(0,0,0,0.10); display: flex; flex-direction: column; }
        .sidebar.open { transform: translateX(0); }
        .sidebar-header { padding: 18px 16px 8px 16px; }
        .sidebar-title { font-size: 1.6rem; font-weight: 700; letter-spacing: 1px; margin-left: 0; display: flex; flex-direction: column; line-height: 1.1; }
        .sidebar-subtitle { font-size: 0.8rem; font-weight: 400; margin-bottom: -2px; }
        .sidebar-menu { display: flex; flex-direction: column; margin-top: 64px; gap: 48px; padding-left: 32px; }
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
        .sidebar-menu-item:hover { color: #ffe033; }
        .main-content { margin-top: 64px; display: flex; flex-direction: column; align-items: center; }
        .dashboard-title { font-size: 2.2rem; font-weight: 700; margin: 32px 0 24px 0; color: #111; text-align: left; letter-spacing: 1px; }
        .charts-container { 
            display: flex; 
            flex-wrap: wrap;
            gap: 48px; 
            justify-content: center; 
            align-items: flex-start; 
        }
        .chart-box { 
            background: white; 
            border-radius: 16px; 
            padding: 24px; 
            width: 400px; 
            min-width: 300px; 
            max-width: 500px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        /* Ranking list styles */
        .ranking-list-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 400px;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }
        
        .ranking-list-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }
        
        .ranking-item:last-child {
            border-bottom: none;
        }
        
        .ranking-item:hover {
            background-color: #f5f5f5;
        }
        
        .ranking-position {
            font-size: 1.1rem;
            font-weight: 700;
            width: 30px;
            color: #555;
        }
        
        .ranking-position.first {
            color: #f1c40f; /* Gold */
        }
        
        .ranking-position.second {
            color: #95a5a6; /* Silver */
        }
        
        .ranking-position.third {
            color: #d35400; /* Bronze */
        }
        
        .ranking-details {
            flex: 1;
        }
        
        .ranking-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .ranking-stats {
            font-size: 0.85rem;
            color: #777;
        }
        
        .ranking-badge {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 15px;
        }
        
        @media (max-width: 768px) {
            .chart-box,
            .ranking-list-container {
                width: 100%;
            }
            
            .charts-container {
                flex-direction: column;
            }
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
            border-radius: 4px;
            padding: 7px 18px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 500;
        }
        .logout-cancel-btn:hover {
            background: #eee;
        }
        .logout-btn {
            background: #222;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 7px 18px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 500;
        }
        .logout-btn:hover {
            background: #444;
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
            <a class="sidebar-menu-item" href="dashboard.php">
                <i class="fas fa-chart-bar"></i> TOP RANKING RCOs
            </a>
            <a class="sidebar-menu-item" href="../admin_components/upcoming_events.php">
                <i class="fas fa-calendar-alt"></i> UPCOMING EVENTS
            </a>
            <a class="sidebar-menu-item" href="../admin_components/manage_users.php">
                <i class="fas fa-users"></i> MANAGE USERS
            </a>
            <a class="sidebar-menu-item" href="../admin_components/user_activities.php">
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
    <div class="main-content">
        <div class="dashboard-title">TOP PERFORMING RCOs</div>
        <div class="charts-container">
            <div class="chart-box">
                <canvas id="donutChart"></canvas>
            </div>
            <div class="chart-box">
                <canvas id="barChart"></canvas>
            </div>
            <div class="ranking-list-container">
                <div class="ranking-list-title">Ranking</div>
                <ul class="ranking-list">
                    <?php foreach ($rco_data['rcos'] as $rco): ?>
                        <li class="ranking-item">
                            <div class="ranking-badge" style="background-color: <?php echo $rco_data['chart_data']['colors'][$rco['rank']-1]; ?>"></div>
                            <div class="ranking-position <?php echo $rco['rank'] == 1 ? 'first' : ($rco['rank'] == 2 ? 'second' : ($rco['rank'] == 3 ? 'third' : '')); ?>">
                                <?php echo $rco['rank']; ?>
                            </div>
                            <div class="ranking-details">
                                <div class="ranking-name"><?php echo htmlspecialchars($rco['name']); ?></div>
                                <div class="ranking-stats">
                                    <span><?php echo $rco['event_count']; ?> events</span>
                                    <span> • </span>
                                    <span><?php echo $rco['upcoming_events']; ?> upcoming</span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <script>
        // Initialize charts when the page loads
        function initializeCharts() {
            // Get chart data from PHP
            const chartData = <?php echo json_encode($rco_data['chart_data']); ?>;
            
            // Donut Chart Data
            const donutData = {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.percentages,
                    backgroundColor: chartData.colors,
                    borderWidth: 2,
                }]
            };
            
            const donutConfig = {
                type: 'doughnut',
                data: donutData,
                options: {
                    cutout: '60%',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { 
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: { 
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'RCO Event Distribution',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            };
            
            // Clear any previous chart
            if (window.donutChartInstance) {
                window.donutChartInstance.destroy();
            }
            window.donutChartInstance = new Chart(document.getElementById('donutChart'), donutConfig);

            // Bar Chart Data
            const barData = {
                labels: chartData.labels,
                datasets: [{
                    label: 'Events',
                    data: chartData.data,
                    backgroundColor: chartData.colors,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7
                }]
            };
            
            const barConfig = {
                type: 'bar',
                data: barData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: 'Number of Events by RCO',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#eee' },
                            ticks: { stepSize: 5 }
                        }
                    }
                }
            };
            
            // Clear any previous chart
            if (window.barChartInstance) {
                window.barChartInstance.destroy();
            }
            window.barChartInstance = new Chart(document.getElementById('barChart'), barConfig);
        }

        // Initialize charts when the page loads
        initializeCharts();

        // Sidebar toggle logic
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const menuBtn = document.getElementById('menuBtn');
        menuBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
            sidebarOverlay.style.display = 'block';
        });
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarOverlay.style.display = 'none';
        });

        // Logout modal logic
        const profileIcon = document.getElementById('profileIcon');
        const logoutModalOverlay = document.getElementById('logoutModalOverlay');
        const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
        
        // Profile icon click - navigate to profile page
        profileIcon.addEventListener('click', () => {
            window.location.href = '../admin_components/profile.php';
        });
        
        cancelLogoutBtn.addEventListener('click', () => {
            logoutModalOverlay.style.display = 'none';
        });
    </script>
</body>
</html> 