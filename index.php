<?php
session_start();

// If user is logged in and verified, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_verified']) && $_SESSION['is_verified'] === true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: user/dashboard.php');
        }
        exit();
    }
}

// If not logged in, redirect to login page
header('Location: login.php');
exit();
?>
