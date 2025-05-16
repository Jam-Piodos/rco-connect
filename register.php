<?php
session_start();
require 'config.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $club_name = $conn->real_escape_string(trim($_POST['club_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    //conditional statement if sakto or dili ang possword
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = 'Email is already registered.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (club_name, email, description, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $club_name, $email, $description, $password_hash);
            if ($stmt->execute()) {
                // Send welcome email using the config function
                $emailBody = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #ff9800; color: white; padding: 10px; text-align: center; }
                        .content { padding: 20px; background: #f9f9f9; }
                        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>RCO CONNECT</h2>
                        </div>
                        <div class="content">
                            <p>Hello ' . htmlspecialchars($club_name) . ',</p>
                            <p>Welcome to RCO CONNECT! Your account has been successfully created.</p>
                            <p>You can now log in and explore our platform.</p>
                        </div>
                        <div class="footer">
                            <p>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                $emailSent = sendEmail($email, 'Welcome to RCO CONNECT', $emailBody);
                
                // Store success message in session
                $_SESSION['registration_success'] = true;
                $_SESSION['email_status'] = $emailSent ? 'sent' : 'failed';
                
                // Clean redirect
                header('Location: login.php');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCO CONNECT - Register</title>
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
        .register-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 36px 32px 28px 32px;
            width: 400px;
            box-sizing: border-box;
            text-align: left;
        }
        .register-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }
        .register-subtitle {
            font-size: 0.9rem;
            font-weight: 400;
            margin-bottom: 0;
            color: #222;
        }
        .register-label {
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 18px;
            margin-bottom: 4px;
            display: block;
        }
        .register-input {
            width: 100%;
            padding: 8px 10px;
            font-size: 1rem;
            border: 1px solid #bbb;
            border-radius: 4px;
            margin-bottom: 8px;
            box-sizing: border-box;
        }
        .register-textarea {
            width: 100%;
            padding: 8px 10px;
            font-size: 1rem;
            border: 1px solid #bbb;
            border-radius: 4px;
            margin-bottom: 8px;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
        }
        .register-btn {
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
        .register-btn:hover {
            background: linear-gradient(90deg, #e2b007 0%, #f7c948 100%);
        }
        .login-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 0.97rem;
            color: #222;
        }
        .login-link a {
            color: #e2b007;
            text-decoration: none;
            font-weight: 500;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-subtitle">NBSC</div>
        <div class="register-title">RCO CONNECT</div>
        <form method="post" action="register.php">
            <label class="register-label" for="club_name">Club Name</label>
            <input class="register-input" type="text" id="club_name" name="club_name" placeholder="Enter your club name" required>

            <label class="register-label" for="email">Email</label>
            <input class="register-input" type="email" id="email" name="email" placeholder="Enter your email" required>
            
            <label class="register-label" for="description">Club Description</label>
            <textarea class="register-textarea" id="description" name="description" placeholder="Briefly describe your club (optional)"></textarea>

            <label class="register-label" for="password">Password</label>
            <input class="register-input" type="password" id="password" name="password" placeholder="Enter password" required>

            <label class="register-label" for="confirm_password">Confirm Password</label>
            <input class="register-input" type="password" id="confirm_password" name="confirm_password" placeholder="Enter password" required>

            <!-- kani motunga if error and success -->
            <?php if (!empty($error)): ?>
                <div style="color: red; text-align: center; margin-bottom: 10px; font-size: 0.97rem;"> <?= htmlspecialchars($error) ?> </div>
            <?php elseif (!empty($success)): ?>
                <div style="color: green; text-align: center; margin-bottom: 10px; font-size: 0.97rem;"> <?= $success ?> </div>
            <?php endif; ?>

            <button class="register-btn" type="submit">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
