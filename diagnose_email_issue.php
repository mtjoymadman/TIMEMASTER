<?php
/**
 * Email Notification Diagnostic Tool
 * 
 * This script checks why email notifications are not being sent in TIMEMASTER
 * 
 * IMPORTANT: This diagnostic tool is COMPLETELY STANDALONE - no config, no session, no redirects
 */

// CRITICAL: Destroy any existing session to prevent session-based redirects
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
// Prevent session from auto-starting
ini_set('session.auto_start', '0');

// CRITICAL: Output immediately to prevent any redirects
echo "<!-- DIAGNOSTIC PAGE LOADING -->\n";

// CRITICAL: Set headers FIRST before anything else
header('Content-Type: text/html; charset=utf-8');
header_remove('Location'); // Remove any Location headers

// Prevent any output buffering issues
if (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Verify we're on the correct page
$current_script = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';

// Debug output to see what's happening
if (isset($_GET['debug'])) {
    echo "<!-- DEBUG INFO:\n";
    echo "Current Script: $current_script\n";
    echo "REQUEST_URI: $current_uri\n";
    echo "SCRIPT_NAME: $script_name\n";
    echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
    echo "-->";
}

if ($current_script !== 'diagnose_email_issue.php' && strpos($current_uri, 'diagnose_email_issue.php') === false && strpos($script_name, 'diagnose_email_issue.php') === false) {
    die("ERROR: Redirect detected! Script: $current_script, URI: $current_uri, SCRIPT_NAME: $script_name");
}

// Define flags BEFORE any includes
define('DIAGNOSTIC_MODE', true);
define('NO_REDIRECTS', true);
define('SKIP_SESSION_CHECK', true);
define('SKIP_AUTH_CHECK', true);
define('TIMEMASTER_CONFIG_LOADED', true); // Prevent config.php from loading

// DO NOT start session - prevents session-based redirects
// DO NOT load config.php - it has redirects
// DO NOT load functions.php - it might have redirects

// Load database config manually
define('TIME_DB_HOST', 'localhost');
define('TIME_DB_USER', 'salvageyard_time');
define('TIME_DB_PASS', '7361dead');
define('TIME_DB_NAME', 'salvageyard_time');

// Initialize database connection
try {
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        throw new Exception("Database connection failed: " . $time_db->connect_error);
    }
} catch (Exception $e) {
    $time_db = null;
}

// Define NOTIFY_EMAIL if not defined
if (!defined('NOTIFY_EMAIL')) {
    define('NOTIFY_EMAIL', 'mtjoymadman@gmail.com, ifree2bmenow@yahoo.com, margie@redlionsalvage.net');
}

// Load only the functions we need (not the full functions.php which might have redirects)
date_default_timezone_set('America/New_York');

function getCurrentEasternTime() {
    return new DateTime('now', new DateTimeZone('America/New_York'));
}

// Clear output buffer
ob_end_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Notification Diagnostic</title>
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
        
        <?php
        echo "<div class='section'>";
        echo "<h2>1. PHPMailer Library Check</h2>";
        
        $phpmailer_path = __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
        if (file_exists($phpmailer_path)) {
            echo "<p class='success'>✓ PHPMailer library found at: $phpmailer_path</p>";
        } else {
            echo "<p class='error'>✗ PHPMailer library NOT FOUND at: $phpmailer_path</p>";
            echo "<p class='warning'>This is likely the root cause of email failures!</p>";
            
            // Check alternative locations
            $alt_paths = [
                __DIR__ . '/lib/PHPMailer/PHPMailer.php',
                __DIR__ . '/PHPMailer/src/PHPMailer.php',
            ];
            
            echo "<p>Checking alternative locations:</p><ul>";
            foreach ($alt_paths as $alt_path) {
                if (file_exists($alt_path)) {
                    echo "<li class='success'>Found at: $alt_path</li>";
                } else {
                    echo "<li class='error'>Not found: $alt_path</li>";
                }
            }
            echo "</ul>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>2. SMTP Configuration Check</h2>";
        
        // Try to load smtp_config.php
        $smtp_config_path = __DIR__ . '/lib/smtp_config.php';
        if (file_exists($smtp_config_path)) {
            echo "<p class='success'>✓ smtp_config.php found</p>";
            
            // Check if we can load it (but prevent any redirects)
            try {
                // Prevent redirects during loading by checking for NO_REDIRECTS flag
                if (!defined('NO_REDIRECTS')) {
                    define('NO_REDIRECTS', true);
                }
                
                // Suppress any output/redirects during require
                ob_start();
                require_once $smtp_config_path;
                $output = ob_get_clean();
                
                // Check if output contains redirect headers
                if (stripos($output, 'Location:') !== false || stripos($output, 'header') !== false) {
                    echo "<p class='warning'>⚠ smtp_config.php attempted to redirect (output suppressed)</p>";
                } else {
                    echo "<p class='success'>✓ smtp_config.php loaded successfully</p>";
                }
                
                // Check constants
                echo "<h3>SMTP Constants:</h3>";
                echo "<ul>";
                echo "<li>SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "<li>SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "<li>SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "<li>SMTP_PASSWORD: " . (defined('SMTP_PASSWORD') ? (strlen(SMTP_PASSWORD) > 0 ? '***SET***' : '<span class="error">EMPTY</span>') : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "<li>SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "<li>SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : '<span class="error">NOT DEFINED</span>') . "</li>";
                echo "</ul>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error loading smtp_config.php: " . $e->getMessage() . "</p>";
            } catch (Error $e) {
                echo "<p class='error'>✗ Fatal error loading smtp_config.php: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ smtp_config.php NOT FOUND at: $smtp_config_path</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>3. sendEmail Function Check</h2>";
        
        if (function_exists('sendEmail')) {
            echo "<p class='success'>✓ sendEmail() function is available</p>";
        } else {
            echo "<p class='error'>✗ sendEmail() function is NOT available</p>";
            echo "<p class='warning'>This means smtp_config.php either didn't load or failed to define the function.</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>4. sendNotification Function Check</h2>";
        
        if (function_exists('sendNotification')) {
            echo "<p class='success'>✓ sendNotification() function is available</p>";
        } else {
            echo "<p class='error'>✗ sendNotification() function is NOT available</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>5. Notification Email Configuration</h2>";
        
        if (defined('NOTIFY_EMAIL')) {
            echo "<p class='success'>✓ NOTIFY_EMAIL constant is defined</p>";
            echo "<p>Value: " . NOTIFY_EMAIL . "</p>";
            $emails = array_map('trim', explode(',', NOTIFY_EMAIL));
            echo "<p>Parsed emails:</p><ul>";
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo "<li class='success'>✓ Valid: $email</li>";
                } else {
                    echo "<li class='error'>✗ Invalid: $email</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p class='error'>✗ NOTIFY_EMAIL constant is NOT defined</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>6. Error Log Check</h2>";
        
        $error_log_path = __DIR__ . '/logs/errors.log';
        if (file_exists($error_log_path)) {
            echo "<p class='success'>✓ Error log file exists</p>";
            $log_content = file_get_contents($error_log_path);
            $recent_errors = array_slice(explode("\n", $log_content), -20);
            echo "<p>Last 20 lines of error log:</p>";
            echo "<pre>" . htmlspecialchars(implode("\n", $recent_errors)) . "</pre>";
        } else {
            echo "<p class='warning'>⚠ Error log file not found at: $error_log_path</p>";
        }
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>7. Test Email Sending</h2>";
        
        if (isset($_POST['test_email'])) {
            $test_email = $_POST['test_email'];
            echo "<p class='info'>Attempting to send test email to: $test_email</p>";
            
            if (function_exists('sendEmail')) {
                $test_subject = "TIMEMASTER Email Test - " . date('Y-m-d H:i:s');
                $test_message = "<html><body><h2>Test Email</h2><p>This is a test email from TIMEMASTER diagnostic tool.</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p></body></html>";
                
                $result = sendEmail($test_email, $test_subject, $test_message);
                
                if ($result) {
                    echo "<p class='success'>✓ Test email sent successfully!</p>";
                } else {
                    echo "<p class='error'>✗ Test email failed to send. Check error logs above.</p>";
                }
            } else {
                echo "<p class='error'>✗ Cannot send test email - sendEmail() function not available</p>";
            }
        }
        
        echo "<form method='post'>";
        echo "<p>Enter email address to send test email:</p>";
        echo "<input type='email' name='test_email' placeholder='test@example.com' required style='padding: 8px; width: 300px;'>";
        echo "<button type='submit' class='test-btn'>Send Test Email</button>";
        echo "</form>";
        echo "</div>";
        
        echo "<div class='section'>";
        echo "<h2>8. Test sendNotification Function</h2>";
        
        if (isset($_POST['test_notification'])) {
            echo "<p class='info'>Attempting to send test notification...</p>";
            
            if (function_exists('sendNotification')) {
                $result = sendNotification('TEST_USER', 'test action', 'This is a test notification from the diagnostic tool.');
                
                if ($result) {
                    echo "<p class='success'>✓ Test notification sent successfully!</p>";
                } else {
                    echo "<p class='error'>✗ Test notification failed to send. Check error logs above.</p>";
                }
            } else {
                echo "<p class='error'>✗ Cannot send test notification - sendNotification() function not available</p>";
            }
        }
        
        echo "<form method='post'>";
        echo "<button type='submit' name='test_notification' value='1' class='test-btn'>Send Test Notification</button>";
        echo "</form>";
        echo "</div>";
        ?>
        
        <div class="section">
            <h2>Summary</h2>
            <p>This diagnostic tool checks all components of the email notification system.</p>
            <p><strong>Common Issues:</strong></p>
            <ul>
                <li><strong>PHPMailer missing:</strong> The library files are not in the expected location</li>
                <li><strong>SMTP credentials wrong:</strong> Gmail password or username incorrect</li>
                <li><strong>Function not loaded:</strong> smtp_config.php failed to load due to missing dependencies</li>
                <li><strong>Error logging:</strong> Check the error log above for specific error messages</li>
            </ul>
        </div>
    </div>
</body>
</html>

