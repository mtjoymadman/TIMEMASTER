<?php
/**
 * Email Diagnostic - In excluded subdirectory
 * This file is in a subdirectory that should bypass all rewrite rules
 */

// Prevent any session or config loading
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_destroy();
}
@ini_set('session.auto_start', '0');

// Set headers immediately
header('Content-Type: text/html; charset=utf-8');
header_remove('Location');

// Output immediately
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { color: #d32f2f; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #d32f2f; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>Email Diagnostic Tool</h1>
    <p class="success">✓ SUCCESS! If you see this, the file loaded correctly!</p>
    
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<div class='section'>";
    echo "<h2>Debug Information</h2>";
    echo "<pre>";
    echo "PHP_SELF: " . htmlspecialchars($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
    echo "REQUEST_URI: " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
    echo "SCRIPT_NAME: " . htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
    echo "SCRIPT_FILENAME: " . htmlspecialchars($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
    echo "Current File: " . __FILE__ . "\n";
    echo "Document Root: " . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
    echo "</pre>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>1. PHPMailer Check</h2>";
    $phpmailer = __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
    if (file_exists($phpmailer)) {
        echo "<p class='success'>✓ Found: " . htmlspecialchars($phpmailer) . "</p>";
    } else {
        echo "<p class='error'>✗ Not found: " . htmlspecialchars($phpmailer) . "</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>2. SMTP Config Check</h2>";
    $smtp_config = __DIR__ . '/../lib/smtp_config.php';
    if (file_exists($smtp_config)) {
        echo "<p class='success'>✓ Found: " . htmlspecialchars($smtp_config) . "</p>";
        
        try {
            define('NO_REDIRECTS', true);
            define('SKIP_SESSION_CHECK', true);
            define('TIMEMASTER_CONFIG_LOADED', true);
            
            $old_error = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
            ob_start();
            require_once $smtp_config;
            ob_end_clean();
            error_reporting($old_error);
            
            if (function_exists('sendEmail')) {
                echo "<p class='success'>✓ sendEmail() function loaded</p>";
                
                if (isset($_POST['test_email'])) {
                    $email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
                    echo "<p class='info'>Sending test email to: " . htmlspecialchars($email) . "</p>";
                    $result = @sendEmail($email, "Test - " . date('Y-m-d H:i:s'), "<h2>Test Email</h2><p>This is a test.</p>");
                    if ($result) {
                        echo "<p class='success'>✓ Email sent successfully!</p>";
                    } else {
                        echo "<p class='error'>✗ Email failed to send</p>";
                    }
                }
                
                echo "<form method='post'><input type='email' name='test_email' placeholder='test@example.com' required><button type='submit'>Send Test</button></form>";
            } else {
                echo "<p class='error'>✗ sendEmail() function not available</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p class='error'>✗ Not found: " . htmlspecialchars($smtp_config) . "</p>";
    }
    echo "</div>";
    ?>
</div>
</body>
</html>

