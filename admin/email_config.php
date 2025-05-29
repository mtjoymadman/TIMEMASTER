<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email Configuration - Only define if not already defined
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.supremecenter.com');
if (!defined('SMTP_USER')) define('SMTP_USER', 'time@time.redlionsalvage.net');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '736-Dead');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Time Clock System');

// Function to send email - currently disabled to prevent errors
function sendEmail($to, $subject, $html_content) {
    // Email sending is disabled, log attempt and return false
    error_log("Email sending is disabled. Attempted to send email to: " . $to);
    return false;
    
    /* 
    // Original function - kept for reference but not executed
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        // Enable verbose debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_content;

        // Send email
        if (!$mail->send()) {
            error_log('Failed to send email to: ' . $to);
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Email sending error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return false;
    }
    */
} 