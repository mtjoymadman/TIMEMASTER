<?php
// Simplified PHPMailer test
session_start();
require_once '../functions.php';
define('INCLUDED_FROM_APP', true);

// Check if user is admin
$logged_in_user = $_SESSION['username'] ?? '';
if (!hasRole($logged_in_user, 'admin')) {
    header("Location: index.php");
    exit;
}

// Start output buffering
header("Content-Type: text/html; charset=utf-8");
echo "<!DOCTYPE html><html><head><title>Simple PHPMailer Test</title>";
echo "<style>body{font-family:Arial,sans-serif;line-height:1.6;margin:20px;} .error{color:red;} .success{color:green;} pre{background:#f5f5f5;padding:10px;}</style>";
echo "</head><body>";
echo "<h1>Simple PHPMailer Test</h1>";
echo "<p><a href='smtp_settings.php'>Return to SMTP Settings</a></p>";

// Get settings from config
$gmail_user = '';
$gmail_pass = '';

if (file_exists('../lib/smtp_config.php')) {
    include_once '../lib/smtp_config.php';
    if (defined('SMTP_USERNAME')) $gmail_user = SMTP_USERNAME;
    if (defined('SMTP_PASSWORD')) $gmail_pass = SMTP_PASSWORD;
}

// Function to log and display messages
function log_msg($message, $is_error = false) {
    $class = $is_error ? 'error' : 'success';
    echo "<p class='$class'>$message</p>";
    flush();
    error_log($message);
}

// Check for PHPMailer
if (!file_exists('../lib/PHPMailer/src/PHPMailer.php')) {
    log_msg("PHPMailer not found. Please install it first.", true);
    echo "<p><a href='../setup_phpmailer.php'>Install PHPMailer</a></p>";
    echo "</body></html>";
    exit;
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once '../lib/PHPMailer/src/Exception.php';
require_once '../lib/PHPMailer/src/PHPMailer.php';
require_once '../lib/PHPMailer/src/SMTP.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $to_email = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$to_email) {
        log_msg("Invalid email address.", true);
    } else {
        try {
            log_msg("Starting PHPMailer with minimal configuration...");
            
            // Create a new PHPMailer instance with exceptions enabled
            $mail = new PHPMailer(true);
            
            // Enable debug output
            $mail->SMTPDebug = 3; // Verbose debug output
            $mail->Debugoutput = function($str, $level) {
                echo "<pre>" . htmlspecialchars($str) . "</pre>";
                flush();
            };
            
            // Use SMTP
            $mail->isSMTP();
            
            // Set the Gmail SMTP server
            $mail->Host = 'smtp.gmail.com';
            
            // Set authentication
            $mail->SMTPAuth = true;
            $mail->Username = $gmail_user;
            $mail->Password = $gmail_pass;
            
            // Set the encryption mechanism
            $mail->SMTPSecure = 'tls';
            
            // Set the port number
            $mail->Port = 587;
            
            // Set timeouts
            $mail->Timeout = 10;
            
            // Set SSL options to be lenient
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set sender
            $mail->setFrom($gmail_user, 'TIMEMASTER Test');
            
            // Add recipient
            $mail->addAddress($to_email);
            
            // Set email subject
            $mail->Subject = 'Simple PHPMailer Test';
            
            // Set email body
            $mail->isHTML(true);
            $mail->Body = '<h1>Test Email</h1><p>This is a test email from TIMEMASTER using PHPMailer with minimal configuration.</p>';
            
            // Send the email
            log_msg("Attempting to send email...");
            
            if ($mail->send()) {
                log_msg("Email sent successfully!");
            } else {
                log_msg("Mailer Error: " . $mail->ErrorInfo, true);
            }
        } catch (Exception $e) {
            log_msg("Exception: " . $e->getMessage(), true);
        } catch (\Exception $e) {
            log_msg("General Exception: " . $e->getMessage(), true);
        }
    }
}

// Form to send test email
echo "<h2>Send Test Email with Minimal PHPMailer</h2>";
echo "<form method='post'>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='test_email'>Email Address:</label><br>";
echo "<input type='email' name='test_email' id='test_email' required style='padding:5px; width:300px;'>";
echo "</div>";
echo "<button type='submit' name='send_test' style='padding:8px 15px; background:#4285f4; color:white; border:none; cursor:pointer;'>Send Test Email</button>";
echo "</form>";

echo "</body></html>"; 