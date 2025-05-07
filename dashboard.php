<?php
session_start();

// Strict security check - must be logged in AND verified
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    // Store the attempted URL in the session
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header('Location: login.php');
    exit();
}

// Prevent going back after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Logout logic
if (isset($_POST['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get user data from database
require 'config.php';
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT club_name, email FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$conn->close();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fff;
        }
        /* Sidebar styles */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        .sidebar {
            position: fixed;
            top: 0; left: 0;
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
            margin-top: 32px;
            gap: 36px;
            padding-left: 16px;
        }
        .sidebar-menu-item {
            font-size: 1rem;
            color: #fff;
            text-decoration: none;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .sidebar-menu-item:hover {
            color: #ffe033;
        }
        .navbar {
            background: #ff9800;
            color: #fff;
            padding: 0 0 0 0;
            display: flex;
            align-items: center;
            height: 56px;
            position: relative;
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
        .dashboard-content {
            padding: 24px 32px 0 32px;
        }
        .dashboard-title {
            font-size: 2.1rem;
            font-weight: 700;
            margin: 18px 0 18px 0;
            letter-spacing: 1px;
        }
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: flex-start;
            align-items: flex-start;
        }
        .chart-box {
            background: none;
            padding: 0;
            border-radius: 12px;
            width: 340px;
            height: 270px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 900px) {
            .charts-container {
                flex-direction: column;
                align-items: center;
            }
            .chart-box {
                width: 90vw;
                max-width: 340px;
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
            background: #b3b3b3;
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
            <a class="sidebar-menu-item" href="#">VIEW TOP RANKING RCOs</a>
            <a class="sidebar-menu-item" href="#">VIEW UPCOMING EVENTS</a>
            <a class="sidebar-menu-item" href="#">MANAGE USERS</a>
            <a class="sidebar-menu-item" href="#">VIEW USER ACTIVITIES</a>
        </div>
    </nav>
    <div class="navbar">
        <div class="menu-icon" title="Menu" id="menuBtn">
            <span>&#9776;</span>
        </div>
        <div class="navbar-title">
            <span class="navbar-subtitle">NBSC</span>
            RCO CONNECT
        </div>
        <div class="profile-icon" title="Profile" id="profileIcon">
            <span style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#fff3e0;display:flex;align-items:center;justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="7" r="4" stroke="#333" stroke-width="1.5"/>
                    <path d="M3 17c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="#333" stroke-width="1.5"/>
                </svg>
            </span>
        </div>
    </div>
    <div class="dashboard-content">
        <div class="dashboard-title">TOP PERFORMING RCOs</div>
        <div class="charts-container">
            <div class="chart-box">
                <canvas id="donutChart"></canvas>
            </div>
            <div class="chart-box">
                <canvas id="barChart"></canvas>
            </div>
        </div>
    </div>
    <script>
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
        // Donut Chart Data
        const donutData = {
            labels: ['Blue', 'Purple', 'Green', 'Orange', 'Red'],
            datasets: [{
                data: [45.8, 29.2, 8.3, 8.3, 8.3],
                backgroundColor: [
                    '#3575ec', // blue
                    '#a020f0', // purple
                    '#3cb371', // green
                    '#ff9800', // orange
                    '#e74c3c'  // red
                ],
                borderWidth: 2,
            }]
        };
        const donutConfig = {
            type: 'doughnut',
            data: donutData,
            options: {
                cutout: '60%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                }
            }
        };
        new Chart(document.getElementById('donutChart'), donutConfig);

        // Bar Chart Data
        const barData = {
            labels: ['', '', '', ''],
            datasets: [{
                data: [8, 10, 19, 23],
                backgroundColor: [
                    '#a66a2c', // brown
                    '#bdbdbd', // gray
                    '#ffe033', // yellow
                    '#e0e0e0'  // light gray
                ],
                borderRadius: 4,
                barPercentage: 0.7,
                categoryPercentage: 0.7
            }]
        };
        const barConfig = {
            type: 'bar',
            data: barData,
            options: {
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eee' },
                        ticks: { stepSize: 5 }
                    }
                }
            }
        };
        new Chart(document.getElementById('barChart'), barConfig);
        // Logout modal logic
        const profileIcon = document.getElementById('profileIcon');
        const logoutModalOverlay = document.getElementById('logoutModalOverlay');
        const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
        profileIcon.addEventListener('click', () => {
            logoutModalOverlay.style.display = 'flex';
        });
        cancelLogoutBtn.addEventListener('click', () => {
            logoutModalOverlay.style.display = 'none';
        });
    </script>
</body>
</html>
