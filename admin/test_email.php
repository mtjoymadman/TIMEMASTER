<?php
// Start session
session_start();

// Debug mode - show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Import PHPMailer classes at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include required files first to prevent undefined constants
require_once '../config.php';
require_once '../functions.php';
require_once '../lib/smtp_config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    die('Please login first');
}

// Process form submission before any HTML output
$sent = false;
$error_message = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    // Get form data
    $to = $_POST['email'];
    $subject = "TIMEMASTER Test Email";
    $message = "This is a test email sent from TIMEMASTER system at " . date('Y-m-d H:i:s');
    
    // Log the attempt
    error_log("[EMAIL_TEST] Attempting to send test email to: $to");
    
    try {
        // Load PHPMailer
        require_once '../lib/PHPMailer/src/Exception.php';
        require_once '../lib/PHPMailer/src/PHPMailer.php';
        require_once '../lib/PHPMailer/src/SMTP.php';
        
        // Create a new PHPMailer instance with exceptions enabled
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 3; // Verbose debug output
        ob_start(); // Capture debug output
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Optional settings that might help with problematic servers
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set a short timeout for quicker failure
        $mail->Timeout = 10;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "<h1>TIMEMASTER Email Test</h1><p>$message</p>";
        $mail->AltBody = "TIMEMASTER Email Test: $message";
        
        // Send email
        $mail->send();
        $debug_info = ob_get_clean();
        $sent = true;
        
    } catch (Exception $e) {
        $debug_info = ob_get_clean();
        $error_message = $e->getMessage();
        error_log("[EMAIL_TEST] Failed: $error_message");
    }
}

// HTML output
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email - TIMEMASTER</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #eee; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; background: #2a2a2a; border-radius: 5px; }
        h1 { color: #3498db; }
        form { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; margin-bottom: 15px; background: #333; border: 1px solid #444; color: #eee; }
        button { background: #3498db; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: rgba(46, 204, 113, 0.2); border-left: 4px solid #2ecc71; }
        .error { background-color: rgba(231, 76, 60, 0.2); border-left: 4px solid #e74c3c; }
        .back { display: inline-block; margin-top: 20px; color: #3498db; }
        .debug { font-family: monospace; padding: 15px; background: #333; overflow: auto; white-space: pre-wrap; font-size: 12px; border-radius: 4px; max-height: 300px; overflow-y: auto; }
        .config { border: 1px solid #444; padding: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>TIMEMASTER - Email Test</h1>
        
        <?php if ($sent): ?>
            <div class="message success">Test email sent successfully to: <?php echo htmlspecialchars($to); ?></div>
        <?php elseif (!empty($error_message)): ?>
            <div class="message error">Error sending email: <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="post" action="test_email.php">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? 'mtjoymadman@gmail.com'); ?>">
            
            <button type="submit">Send Test Email</button>
        </form>
        
        <p>Use this tool to test the email configuration without generating a full report.</p>
        
        <!-- SMTP Configuration Information -->
        <div class="config">
            <h3>Current SMTP Configuration</h3>
            <ul>
                <li>Host: <?php echo defined('SMTP_HOST') ? SMTP_HOST : 'Not defined'; ?></li>
                <li>Port: <?php echo defined('SMTP_PORT') ? SMTP_PORT : 'Not defined'; ?></li>
                <li>Username: <?php echo defined('SMTP_USERNAME') ? SMTP_USERNAME : 'Not defined'; ?></li>
                <li>Encryption: <?php echo defined('SMTP_SECURE') ? SMTP_SECURE : 'Not defined'; ?></li>
                <li>From Email: <?php echo defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'Not defined'; ?></li>
            </ul>
        </div>
        
        <?php if (!empty($debug_info)): ?>
            <h3>Debug Information</h3>
            <div class="debug"><?php echo htmlspecialchars($debug_info); ?></div>
        <?php endif; ?>
        
        <a href="reports.php" class="back">Return to Reports</a>
    </div>
</body>
</html> 