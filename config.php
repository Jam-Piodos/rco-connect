<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rco_connect');

// Email Configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'piodosjam98@gmail.com');
define('MAIL_PASSWORD', 'rzyz jhhe ztwy eynl'); // Updated to the working app password
define('MAIL_FROM', 'piodosjam98@gmail.com');
define('MAIL_FROM_NAME', 'RCO CONNECT');
define('MAIL_DEBUG', false); // Disable debug mode in production

// OTP Configuration
define('OTP_EXPIRY', 600); // 10 minutes in seconds

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    return $conn;
}

// Function to send email using PHPMailer
function sendEmail($to, $subject, $body) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = MAIL_DEBUG ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email exception: " . $e->getMessage());
        return false;
    }
}
?>
