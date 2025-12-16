<?php
/**
 * Email Diagnostic Tool - Standalone Version
 * 
 * This file uses a different name to avoid any .htaccess rules
 * and is completely isolated from the rest of the system
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
        .info { color: #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-btn { padding: 10px 20px; background: #d32f2f; color: white; border: none; cursor: pointer; border-radius: 3px; margin: 10px 5px; }
        .test-btn:hover { background: #b71c1c; }
    </style>
</head>
<body>
<div class="container">
    <h1>Email Notification Diagnostic Tool</h1>
    <p class="success">✓ Page loaded successfully! If you see this, the file is accessible.</p>
    
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    try {
        echo "<div class='section'>";
        echo "<h2>1. PHPMailer Library Check</h2>";
        
        $phpmailer_path = __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
        if (file_exists($phpmailer_path)) {
            echo "<p class='success'>✓ PHPMailer library found at: " . htmlspecialchars($phpmailer_path) . "</p>";
        } else {
            echo "<p class='error'>✗ PHPMailer library NOT FOUND at: " . htmlspecialchars($phpmailer_path) . "</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>2. SMTP Configuration Check</h2>";
        
        $smtp_config_path = __DIR__ . '/lib/smtp_config.php';
        if (file_exists($smtp_config_path)) {
            echo "<p class='success'>✓ smtp_config.php found</p>";
            
            // Try to load it
            try {
                define('NO_REDIRECTS', true);
                define('SKIP_SESSION_CHECK', true);
                define('SKIP_AUTH_CHECK', true);
                define('TIMEMASTER_CONFIG_LOADED', true);
                
                $old_error = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
                ob_start();
                require_once $smtp_config_path;
                ob_end_clean();
                error_reporting($old_error);
                
                echo "<p class='success'>✓ smtp_config.php loaded</p>";
                
                if (defined('SMTP_HOST')) {
                    echo "<p>SMTP_HOST: " . htmlspecialchars(SMTP_HOST) . "</p>";
                }
                if (defined('SMTP_PORT')) {
                    echo "<p>SMTP_PORT: " . htmlspecialchars(SMTP_PORT) . "</p>";
                }
                if (defined('SMTP_USERNAME')) {
                    echo "<p>SMTP_USERNAME: " . htmlspecialchars(SMTP_USERNAME) . "</p>";
                }
                if (defined('SMTP_PASSWORD')) {
                    echo "<p>SMTP_PASSWORD: " . (strlen(SMTP_PASSWORD) > 0 ? '***SET***' : 'EMPTY') . "</p>";
                }
                
                if (function_exists('sendEmail')) {
                    echo "<p class='success'>✓ sendEmail() function is available</p>";
                } else {
                    echo "<p class='error'>✗ sendEmail() function is NOT available</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error loading: " . htmlspecialchars($e->getMessage()) . "</p>";
            } catch (Error $e) {
                echo "<p class='error'>✗ Fatal error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='error'>✗ smtp_config.php NOT FOUND</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>3. Test Email Sending</h2>";
        
        if (isset($_POST['test_email']) && function_exists('sendEmail')) {
            $test_email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
            echo "<p class='info'>Attempting to send test email to: " . htmlspecialchars($test_email) . "</p>";
            
            $result = @sendEmail($test_email, "TIMEMASTER Test - " . date('Y-m-d H:i:s'), 
                "<html><body><h2>Test Email</h2><p>This is a test from the diagnostic tool.</p></body></html>");
            
            if ($result) {
                echo "<p class='success'>✓ Test email sent successfully!</p>";
            } else {
                echo "<p class='error'>✗ Test email failed to send</p>";
            }
        }
        
        echo "<form method='post'>";
        echo "<p>Enter email address to send test email:</p>";
        echo "<input type='email' name='test_email' placeholder='test@example.com' required style='padding: 8px; width: 300px;'>";
        echo "<button type='submit' class='test-btn'>Send Test Email</button>";
        echo "</form>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='section'><p class='error'>Exception: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    } catch (Error $e) {
        echo "<div class='section'><p class='error'>Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
    }
    ?>
</div>
</body>
</html>

