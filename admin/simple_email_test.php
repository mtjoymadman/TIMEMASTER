<?php
// Start session
session_start();

// Set error reporting level
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    die('Please login first');
}

// Initialize variables
$sent = false;
$error = '';
$debug = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $to = $_POST['email'];
    $subject = "Simple Test Email from TIMEMASTER";
    $message = "This is a test email sent using PHP mail() function at " . date('Y-m-d H:i:s');
    $headers = "From: noreply@example.com\r\n";
    $headers .= "Reply-To: noreply@example.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // Log the attempt
    error_log("Simple email test to: $to");
    
    // Try to send the email using PHP's mail function
    try {
        // Capture any warnings
        ob_start();
        $result = mail($to, $subject, $message, $headers);
        $debug = ob_get_clean();
        
        if ($result) {
            $sent = true;
        } else {
            $error = "Email not sent. Check server configuration.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Email Test - TIMEMASTER</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #eee; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #2a2a2a; border-radius: 5px; }
        h1 { color: #3498db; }
        form { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; margin-bottom: 15px; background: #333; border: 1px solid #444; color: #eee; }
        button { background: #3498db; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: rgba(46, 204, 113, 0.2); border-left: 4px solid #2ecc71; }
        .error { background-color: rgba(231, 76, 60, 0.2); border-left: 4px solid #e74c3c; }
        .back { display: inline-block; margin-top: 20px; color: #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Email Test</h1>
        
        <?php if ($sent): ?>
            <div class="message success">Email sent successfully to: <?php echo htmlspecialchars($to); ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="message error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? 'mtjoymadman@gmail.com'); ?>">
            
            <button type="submit">Send Basic Test Email</button>
        </form>
        
        <p>This page uses PHP's basic mail() function rather than PHPMailer to test if email sending works at all.</p>
        
        <?php if (!empty($debug)): ?>
            <h3>Debug Info</h3>
            <pre><?php echo htmlspecialchars($debug); ?></pre>
        <?php endif; ?>
        
        <a href="reports.php" class="back">Return to Reports</a>
    </div>
</body>
</html> 