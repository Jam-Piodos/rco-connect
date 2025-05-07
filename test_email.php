<?php
require 'config.php';

// Test email parameters
$to = isset($_GET['email']) ? $_GET['email'] : 'test@example.com';
$subject = 'Test Email from RCO CONNECT';
$body = '
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
            <p>Hello,</p>
            <p>This is a test email from RCO CONNECT.</p>
            <p>If you receive this, the email system is working properly.</p>
        </div>
        <div class="footer">
            <p>This is an automated test message.</p>
        </div>
    </div>
</body>
</html>';

// Try to send the email
echo "<h2>Testing Email Configuration</h2>";
echo "<pre>";
echo "Sending test email to: " . htmlspecialchars($to) . "\n\n";

if (sendEmail($to, $subject, $body)) {
    echo "Email sent successfully!\n";
} else {
    echo "Failed to send email. Check the error log for details.\n";
}
echo "</pre>";

// Display a form to test with different email
echo '
<form method="get">
    <p>
        <input type="email" name="email" placeholder="Enter email to test" required>
        <button type="submit">Send Test Email</button>
    </p>
</form>';
?> 