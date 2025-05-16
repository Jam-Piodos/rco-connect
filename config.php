<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'rco_connect');

// Email Configuration
if (!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', 'piodosjam98@gmail.com');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', 'rzyz jhhe ztwy eynl'); // Updated to the working app password
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'piodosjam98@gmail.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'RCO CONNECT');
if (!defined('MAIL_DEBUG')) define('MAIL_DEBUG', false); // Disable debug mode in production

// OTP Configuration
if (!defined('OTP_EXPIRY')) define('OTP_EXPIRY', 600); // 10 minutes in seconds

// Create database connection
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }
        return $conn;
    }
}

// Function to send email using PHPMailer
if (!function_exists('sendEmail')) {
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
}
?>
