<?php
session_start();
require 'config.php';

// Handle logout if requested
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    // Destroy the session
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    $success_message = "You have been successfully logged out.";
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Redirect if already logged in and verified
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, email, password_hash, role, is_active FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['is_active'] != 1) {
                $error = 'This account has been deactivated. Please contact the administrator.';
            } 
            // Verify password
            elseif (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                // Generate and send OTP
                $otp = generateOTP();
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_time'] = time();
                
                // Send OTP via email
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #ff9800; color: white; padding: 10px; text-align: center; }
                        .content { padding: 20px; background: #f9f9f9; }
                        .otp { font-size: 24px; font-weight: bold; color: #ff9800; text-align: center; margin: 20px 0; }
                        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>RCO CONNECT</h2>
                        </div>
                        <div class="content">
                            <p>Hello,</p>
                            <p>Your OTP for authentication is:</p>
                            <div class="otp">' . $otp . '</div>
                            <p>This OTP is valid for 10 minutes.</p>
                        </div>
                        <div class="footer">
                            <p>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                sendEmail($user['email'], 'Your OTP for RCO CONNECT', $emailBody);
                
                header('Location: authentication.php');
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
        $conn->close();
    }
}

// Helper function to generate 6-digit OTP
function generateOTP() {
    return sprintf('%06d', mt_rand(0, 999999));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Login</title>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: radial-gradient(circle at 20% 20%, #fff 0%, #c0c0c0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 36px 32px 28px 32px;
            width: 350px;
            box-sizing: border-box;
            text-align: left;
        }
        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }
        .login-subtitle {
            font-size: 0.9rem;
            font-weight: 400;
            margin-bottom: 0;
            color: #222;
        }
        .login-label {
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 18px;
            margin-bottom: 4px;
            display: block;
        }
        .login-input {
            width: 100%;
            padding: 8px 10px;
            font-size: 1rem;
            border: 1px solid #bbb;
            border-radius: 4px;
            margin-bottom: 8px;
            box-sizing: border-box;
        }
        .login-btn {
            width: 100%;
            background: linear-gradient(90deg, #f7c948 0%, #e2b007 100%);
            color: #222;
            font-weight: 600;
            border: none;
            border-radius: 4px;
            padding: 10px 0;
            font-size: 1rem;
            margin-top: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: linear-gradient(90deg, #e2b007 0%, #f7c948 100%);
        }
        .register-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 0.97rem;
            color: #222;
        }
        .register-link a {
            color: #e2b007;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        .warning {
            background-color: #fcf8e3;
            border: 1px solid #faebcc;
            color: #8a6d3b;
        }
        .error {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-subtitle">NBSC</div>
        <div class="login-title">RCO CONNECT</div>
        
        <?php 
        // Display registration success message
        if (isset($_SESSION['registration_success'])) {
            $messageClass = $_SESSION['email_status'] === 'sent' ? 'success' : 'warning';
            $message = $_SESSION['email_status'] === 'sent' 
                ? 'Registration successful! Welcome email has been sent.' 
                : 'Account created successfully! Email delivery failed - please check your email address.';
            echo '<div class="message ' . $messageClass . '">' . htmlspecialchars($message) . '</div>';
            // Clear the session messages
            unset($_SESSION['registration_success']);
            unset($_SESSION['email_status']);
        }
        
        // Display logout success message
        if (isset($success_message)): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" action="login.php">
            <label class="login-label" for="email">Email</label>
            <input class="login-input" type="email" id="email" name="email" placeholder="Enter your email" required>

            <label class="login-label" for="password">Password</label>
            <input class="login-input" type="password" id="password" name="password" placeholder="Enter password" required>

            <button class="login-btn" type="submit">LOGIN</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>
