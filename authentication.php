<?php
session_start();
require 'config.php';

// Redirect if not logged in or already verified
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} elseif (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp'])) {
        $entered_otp = $_POST['otp'];
        $stored_otp = $_SESSION['otp'] ?? '';
        $otp_time = $_SESSION['otp_time'] ?? 0;
        
        // Check if OTP is expired (10 minutes)
        if (time() - $otp_time > OTP_EXPIRY) {
            $error = 'OTP has expired. Please request a new one.';
        } 
        // Verify OTP
        elseif ($entered_otp === $stored_otp) {
            $_SESSION['is_verified'] = true;
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid OTP. Please try again.';
        }
    }
    // Handle resend OTP
    elseif (isset($_POST['resend'])) {
        // Generate new OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_time'] = time();
        
        // Send new OTP email
        $email = $_SESSION['email'];
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
                    <p>Your new OTP for authentication is:</p>
                    <div class="otp">' . $otp . '</div>
                    <p>This OTP is valid for 10 minutes.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>';
        
        if (sendEmail($email, 'Your New OTP for RCO CONNECT', $emailBody)) {
            $success = 'New OTP has been sent to your email.';
        } else {
            $error = 'Failed to send OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Authentication</title>
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
        .auth-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 36px 32px 28px 32px;
            width: 350px;
            box-sizing: border-box;
            text-align: left;
        }
        .auth-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }
        .auth-subtitle {
            font-size: 0.9rem;
            font-weight: 400;
            margin-bottom: 24px;
            color: #222;
        }
        .otp-input {
            width: 100%;
            padding: 12px;
            font-size: 1.2rem;
            border: 1px solid #bbb;
            border-radius: 4px;
            margin-bottom: 16px;
            text-align: center;
            letter-spacing: 4px;
            box-sizing: border-box;
        }
        .verify-btn {
            width: 100%;
            background: linear-gradient(90deg, #f7c948 0%, #e2b007 100%);
            color: #222;
            font-weight: 600;
            border: none;
            border-radius: 4px;
            padding: 12px 0;
            font-size: 1rem;
            margin-bottom: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .verify-btn:hover {
            background: linear-gradient(90deg, #e2b007 0%, #f7c948 100%);
        }
        .resend-btn {
            width: 100%;
            background: none;
            border: 1px solid #bbb;
            border-radius: 4px;
            padding: 10px 0;
            font-size: 0.9rem;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }
        .resend-btn:hover {
            background: #f5f5f5;
            border-color: #999;
        }
        .message {
            text-align: center;
            margin: 12px 0;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .error {
            background: #ffebee;
            color: #c62828;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-subtitle">NBSC</div>
        <div class="auth-title">RCO CONNECT</div>
        <p class="auth-subtitle">Please enter the OTP sent to your email</p>
        
        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="post" action="authentication.php">
            <input type="text" name="otp" class="otp-input" placeholder="Enter OTP" maxlength="6" required>
            <button type="submit" class="verify-btn">VERIFY OTP</button>
        </form>
        
        <form method="post" action="authentication.php">
            <button type="submit" name="resend" class="resend-btn">RESEND OTP</button>
        </form>
    </div>
</body>
</html>
