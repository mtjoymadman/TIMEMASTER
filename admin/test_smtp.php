<?php
session_start();
require_once '../functions.php';

// Set a PHP timeout limit
set_time_limit(30);

// Start output buffering to prevent hanging
ob_start();

// Define constant to prevent direct access to included files
define('INCLUDED_FROM_APP', true);

// Add global use statements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Check if user is admin
$logged_in_user = $_SESSION['username'] ?? '';
if (!hasRole($logged_in_user, 'admin')) {
    header("Location: index.php");
    exit;
}

// Check for PHPMailer installation
$phpmailer_exists = file_exists(__DIR__ . '/../lib/PHPMailer/src/PHPMailer.php');

$message = '';
$status = '';

// Check if test email requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    if (!$phpmailer_exists) {
        $message = "PHPMailer is not installed. Please install PHPMailer first.";
        $status = "error";
    } else {
        // Include SMTP configuration - always include it
        require_once '../lib/smtp_config.php';
        
        // Check if we should just test connectivity
        if (isset($_POST['use_socket_test']) && $_POST['use_socket_test'] == 1) {
            try {
                // Test SMTP connectivity directly
                error_log("Testing Gmail SMTP connectivity with fsockopen");
                echo "<!-- Testing connection... -->";
                ob_flush();
                flush();
                
                $host = 'smtp.gmail.com';
                $port = 587;
                $timeout = 5;
                
                $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
                
                if (!$socket) {
                    $message = "Connection test failed: Unable to connect to Gmail SMTP server ($errno: $errstr). This indicates your server is blocking outgoing connections to Gmail.";
                    $status = "error";
                    error_log("Socket connection failed: $errstr ($errno)");
                } else {
                    fclose($socket);
                    $message = "Connection test successful: Your server can connect to Gmail SMTP. You can now try sending an actual test email.";
                    $status = "success";
                    error_log("Socket connection successful");
                }
                
                // Don't proceed with sending the actual email
                $test_email = "";
            } catch (Exception $e) {
                $message = "Connection test error: " . $e->getMessage();
                $status = "error";
                error_log("Connection test exception: " . $e->getMessage());
                $test_email = "";
            }
        } else {
            $test_email = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL);
        }
        
        if (!$test_email) {
            // If we're here and $message is not set, it means invalid email
            if (empty($message)) {
                $message = "Invalid email address.";
                $status = "error";
            }
        } else {
            // Check if Gmail settings are configured
            if (!defined('SMTP_USERNAME') || empty(SMTP_USERNAME) || !defined('SMTP_PASSWORD') || empty(SMTP_PASSWORD)) {
                $message = "Gmail settings are not configured properly. Please configure your Gmail settings first.";
                $status = "error";
            } 
            else {
                // Prepare test email
                $subject = "TIMEMASTER Gmail SMTP Test Email";
                
                // Use temporary password if provided
                $temp_password = trim($_POST['temp_password'] ?? '');
                if (!empty($temp_password)) {
                    // Format temp password correctly (remove spaces/dashes)
                    $temp_password = preg_replace('/[\s-]/', '', $temp_password);
                    // Override the stored password with the temporary one for this test only
                    define('TEMP_SMTP_PASSWORD', $temp_password);
                }
                
                $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        h1 { color: #c0392b; }
                        .footer { margin-top: 30px; font-size: 12px; color: #888; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h1>TIMEMASTER Gmail SMTP Test</h1>
                        <p>This is a test email from TIMEMASTER to verify your Gmail SMTP configuration is working correctly.</p>
                        <p>If you're seeing this email, your Gmail settings are configured correctly!</p>
                        <p>Test sent by: " . htmlspecialchars($logged_in_user) . "<br>
                        Date/Time: " . date('Y-m-d H:i:s') . "</p>
                        <div class='footer'>
                            <p>This is an automated message from the TIMEMASTER system. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                try {
                    // Add immediate output to prevent spinning
                    echo "<!-- Attempting to send email... -->";
                    ob_flush();
                    flush();
                    
                    // Make sure PHPMailer files are included
                    require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
                    require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
                    require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

                    // Send the test email with minimal options
                    $mail = new PHPMailer(true);
                    
                    // Server settings - ultra simple configuration
                    $mail->SMTPDebug = 2; // Force debug level 2
                    $mail->Debugoutput = function($str, $level) use (&$debug_output) {
                        $debug_output .= htmlspecialchars($str) . "<br>\n";
                        error_log("TEST SMTP: " . $str);
                    };
                    
                    // Set shorter timeout
                    $mail->Timeout = 10;
                    
                    // Basic SMTP setup
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'rlstimeclock@gmail.com';
                    $mail->Password = 'wsilgdzeouzremou'; // New App Password (generated 3/19/2025)
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    
                    // Basic SSL options that worked before
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    
                    // Set sender and recipient
                    $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
                    $mail->addAddress($test_email);
                    
                    // Set content
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    
                    // Send email
                    $mail->send();
                    $message = "Test email sent successfully to: " . htmlspecialchars($test_email);
                    $status = "success";
                } catch (Exception $e) {
                    $message = "Failed to send test email: " . $e->getMessage();
                    $status = "error";
                    error_log("PHPMailer Error: " . $e->getMessage());
                    
                    // Emergency output
                    echo "<!-- ERROR: " . htmlspecialchars($e->getMessage()) . " -->";
                    ob_flush();
                    flush();
                } catch (\Exception $e) {
                    $message = "Failed to send test email: " . $e->getMessage();
                    $status = "error";
                    error_log("General Error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="45;url=test_smtp.php">
    <title>Gmail SMTP Test Result - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 2s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('test-form').style.display = 'none';
            
            // Set a timeout to check if we're hanging
            setTimeout(function() {
                var spinner = document.getElementById('loading');
                if (spinner && spinner.style.display === 'block') {
                    document.getElementById('timeout-message').style.display = 'block';
                }
            }, 15000); // Show timeout message after 15 seconds
            
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Gmail SMTP Test Result</h1>
            <p><a href="smtp_settings.php">Back to Gmail SMTP Settings</a></p>
        </header>
        
        <?php if (!$phpmailer_exists) { ?>
            <div class="error-message">
                <strong>Warning:</strong> PHPMailer is not installed. Email functionality will not work.
                <a href="../setup_phpmailer.php" class="blue-button" style="display: inline-block; margin-top: 10px;">Install PHPMailer</a>
            </div>
        <?php } ?>
        
        <div class="admin-section">
            <?php if (!empty($message)): ?>
                <div class="<?php echo $status === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($status === 'error'): ?>
                <h2>Gmail SMTP Troubleshooting Tips</h2>
                <ul>
                    <li><strong>App Password Required</strong> - Gmail requires an App Password, not your regular Gmail password</li>
                    <li><strong>2-Factor Authentication</strong> - You must enable 2-factor authentication on your Google account to create App Passwords</li>
                    <li><strong>Create App Password</strong> - Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a> to generate one</li>
                    <li><strong>From Email Must Match</strong> - Your From Email Address must match your Gmail address</li>
                    <li><strong>Less Secure Apps</strong> - For older Google accounts, you may need to enable "Less secure app access"</li>
                    <li><strong>Check Gmail Settings</strong> - Verify your Gmail doesn't have any restrictions on SMTP access</li>
                    <li><strong>Firewall Issues</strong> - Your web hosting may block outgoing SMTP connections to Gmail (port 587)</li>
                </ul>
                
                <div class="form-group" style="margin-top: 20px;">
                    <a href="smtp_settings.php?switch_to_mail=1" class="blue-button">Switch to PHP mail() Function</a>
                    <p><small>Click this button to configure the system to use PHP's built-in mail() function instead of SMTP</small></p>
                </div>
            <?php endif; ?>
            
            <form action="test_smtp.php" method="post" id="test-form" onsubmit="return showLoading();">
                <div class="form-group">
                    <label for="test_email">Send Test Email To:</label>
                    <input type="email" name="test_email" id="test_email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="temp_password">Temporary App Password (overrides stored password):</label>
                    <input type="text" name="temp_password" id="temp_password" class="form-control" placeholder="Leave blank to use stored password">
                    <small>Enter a 16-character app password if you want to test without saving it</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="use_socket_test" value="1">
                        Test Gmail SMTP connectivity first (faster diagnosis)
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="try_alternate_auth" value="1">
                        Try alternate authentication methods (if regular method fails)
                    </label>
                    <small>This attempts different SMTP authentication mechanisms that might work better with your Gmail account</small>
                </div>
                
                <button type="submit" name="send_test" class="blue-button">Send Test Email</button>
            </form>
            
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Sending test email... This may take up to 10 seconds.</p>
                <div id="timeout-message" style="display: none; margin-top: 15px; color: #c0392b;">
                    <p><strong>It's taking longer than expected.</strong></p>
                    <p>Try these troubleshooting steps:</p>
                    <ol>
                        <li>Check your server's error log</li>
                        <li>Make sure outgoing connections to smtp.gmail.com:587 are allowed</li>
                        <li>Verify your App Password is correct (16 characters without dashes)</li>
                        <li>Try using PHP mail() function instead in SMTP Settings</li>
                    </ol>
                    <a href="test_smtp.php" class="blue-button">Cancel and Try Again</a>
                </div>
            </div>
            
            <div class="button-group" style="margin-top: 20px;">
                <a href="smtp_settings.php" class="blue-button">Back to Gmail SMTP Settings</a>
            </div>
        </div>
        
        <div class="server-info-section">
            <h2>Server Mail Configuration</h2>
            <p>This information may help identify why emails are not sending:</p>
            <pre><?php
                echo "PHP Version: " . phpversion() . "\n";
                echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
                echo "Sendmail Path: " . (ini_get('sendmail_path') ?: 'Not configured') . "\n";
                echo "SMTP Server: " . (ini_get('SMTP') ?: 'Not configured') . "\n";
                echo "SMTP Port: " . (ini_get('smtp_port') ?: 'Default') . "\n\n";
                
                // Check for common mail issues
                $issues = [];
                
                if (!function_exists('mail')) {
                    $issues[] = "PHP mail() function is disabled";
                }
                
                if (!ini_get('sendmail_path') && !ini_get('SMTP')) {
                    $issues[] = "No mail transport configured (sendmail or SMTP)";
                }
                
                if (empty($issues)) {
                    echo "No obvious mail configuration issues detected.\n";
                } else {
                    echo "Potential issues:\n";
                    foreach ($issues as $issue) {
                        echo "- $issue\n";
                    }
                }
            ?></pre>
            
            <p><strong>Next steps if email is not working:</strong></p>
            <ol>
                <li>Contact your hosting provider to verify if PHP mail() is available</li>
                <li>Ask if they provide a specific SMTP server you should use</li>
                <li>Check server error logs for more detailed error messages</li>
            </ol>
        </div>
    </div>
</body>
</html> 