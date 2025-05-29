<?php
// Fix PHPMailer implementation
session_start();
require_once 'functions.php';
define('INCLUDED_FROM_APP', true);

// Check if user is admin
$logged_in_user = $_SESSION['username'] ?? '';
if (!hasRole($logged_in_user, 'admin')) {
    header("Location: admin/index.php");
    exit;
}

// Start output
header("Content-Type: text/html; charset=utf-8");
echo "<!DOCTYPE html><html><head><title>Fix PHPMailer Configuration</title>";
echo "<style>body{font-family:Arial,sans-serif;line-height:1.6;margin:20px;} .error{color:red;} .success{color:green;}</style>";
echo "</head><body>";
echo "<h1>Fix PHPMailer Configuration</h1>";

// Define the configuration file path
$config_file = __DIR__ . '/smtp_config.php';

// Check if the configuration file exists
if (!file_exists($config_file)) {
    echo "<p class='error'>Configuration file not found. Please set up Gmail SMTP settings first.</p>";
    echo "<p><a href='admin/smtp_settings.php'>Set up Gmail SMTP Settings</a></p>";
    echo "</body></html>";
    exit;
}

// Get current settings
include_once $config_file;
$gmail_user = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
$gmail_pass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
$smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
$smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 587;
$smtp_secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
$smtp_from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'TIMEMASTER';

echo "<p>Updating PHPMailer configuration with optimal settings.</p>";

// Create the new configuration content
$config_content = <<<EOT
<?php
// SMTP Configuration - Auto-configured for Gmail
if (!defined('INCLUDED_FROM_APP')) { exit('No direct script access allowed'); }

@define('SMTP_HOST', '{$smtp_host}');
@define('SMTP_PORT', {$smtp_port});
@define('SMTP_SECURE', '{$smtp_secure}');
@define('SMTP_AUTH', true);
@define('SMTP_USERNAME', '{$gmail_user}');
@define('SMTP_PASSWORD', '{$gmail_pass}');
@define('SMTP_FROM_EMAIL', '{$gmail_user}');
@define('SMTP_FROM_NAME', '{$smtp_from_name}');
@define('SMTP_DEBUG', 0);
@define('SEND_METHOD', 'smtp');

// Simple SMTP email function with reliable error handling
function send_smtp_email(\$to, \$subject, \$body, \$attachments = []) {
    try {
        // Check if we should use PHP's mail() function instead of SMTP
        if (defined('SEND_METHOD') && SEND_METHOD === 'mail') {
            \$headers = "MIME-Version: 1.0\r\n";
            \$headers .= "Content-type: text/html; charset=utf-8\r\n";
            \$headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
            
            if (mail(\$to, \$subject, \$body, \$headers)) {
                return ['success' => true, 'message' => 'Email sent via PHP mail()'];
            } else {
                error_log('PHP mail() failed');
                return ['success' => false, 'message' => 'Failed to send email via PHP mail()'];
            }
        }
        
        // Check if PHPMailer is available
        if (!file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
            error_log('PHPMailer not installed');
            return ['success' => false, 'message' => 'PHPMailer is not installed'];
        }
        
        // Include PHPMailer files
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        
        // Import PHPMailer classes
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;
        use PHPMailer\PHPMailer\SMTP;
        
        // Create new PHPMailer instance
        \$mail = new PHPMailer(true);
        
        // Configure PHPMailer with minimal required settings
        \$mail->isSMTP();
        \$mail->Host = SMTP_HOST;
        \$mail->SMTPAuth = SMTP_AUTH;
        \$mail->Username = SMTP_USERNAME;
        \$mail->Password = SMTP_PASSWORD;
        \$mail->SMTPSecure = SMTP_SECURE;
        \$mail->Port = SMTP_PORT;
        \$mail->CharSet = 'UTF-8';
        \$mail->Timeout = 10;
        
        // Add SSL options to work with Gmail reliably
        \$mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set sender - must match Gmail username for authentication
        \$mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Add recipients
        if (is_array(\$to)) {
            foreach (\$to as \$recipient) {
                \$mail->addAddress(\$recipient);
            }
        } else {
            \$mail->addAddress(\$to);
        }
        
        // Set content
        \$mail->isHTML(true);
        \$mail->Subject = \$subject;
        \$mail->Body = \$body;
        
        // Add attachments if any
        if (!empty(\$attachments) && is_array(\$attachments)) {
            foreach (\$attachments as \$attachment) {
                if (isset(\$attachment['path']) && file_exists(\$attachment['path'])) {
                    \$name = isset(\$attachment['name']) ? \$attachment['name'] : basename(\$attachment['path']);
                    \$mail->addAttachment(\$attachment['path'], \$name);
                }
            }
        }
        
        // Send the email
        if (!\$mail->send()) {
            error_log('Email sending failed: ' . \$mail->ErrorInfo);
            return ['success' => false, 'message' => \$mail->ErrorInfo];
        }
        
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception \$e) {
        error_log('PHPMailer Exception: ' . \$e->getMessage());
        return ['success' => false, 'message' => \$e->getMessage()];
    } catch (\Exception \$e) {
        error_log('General Exception: ' . \$e->getMessage());
        return ['success' => false, 'message' => \$e->getMessage()];
    }
}

/**
 * Send email using PHP's built-in mail() function
 * 
 * @param string|array \$to Recipient email address(es)
 * @param string \$subject Email subject
 * @param string \$body Email body (HTML)
 * @return array ['success' => bool, 'message' => string]
 */
function send_php_mail(\$to, \$subject, \$body) {
    try {
        \$headers = "MIME-Version: 1.0\r\n";
        \$headers .= "Content-type: text/html; charset=utf-8\r\n";
        \$headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        
        if (is_array(\$to)) {
            \$to = implode(', ', \$to);
        }
        
        \$result = mail(\$to, \$subject, \$body, \$headers);
        
        if (\$result) {
            return ['success' => true, 'message' => 'Email sent via PHP mail()'];
        } else {
            error_log('Failed to send email via PHP mail()');
            return ['success' => false, 'message' => 'Failed to send email via PHP mail()'];
        }
    } catch (\Exception \$e) {
        error_log('PHP mail() error: ' . \$e->getMessage());
        return ['success' => false, 'message' => \$e->getMessage()];
    }
}
EOT;

// Save the file
if (file_put_contents($config_file, $config_content)) {
    echo "<p class='success'>PHPMailer configuration has been successfully updated with optimized settings!</p>";
    echo "<p>The following changes were made:</p>";
    echo "<ul>";
    echo "<li>Simplified PHPMailer implementation with only the essential settings</li>";
    echo "<li>Added SSL options to ensure proper Gmail connectivity</li>";
    echo "<li>Improved error handling and reporting</li>";
    echo "<li>Optimized email sending function for better reliability</li>";
    echo "</ul>";
} else {
    echo "<p class='error'>Failed to update configuration file. Please check file permissions.</p>";
}

echo "<p><a href='admin/simple_phpmailer_test.php'>Test the optimized PHPMailer configuration</a></p>";
echo "<p><a href='admin/smtp_settings.php'>Return to SMTP Settings</a></p>";
echo "</body></html>"; 