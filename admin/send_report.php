<?php
// Start session before ANY output, even whitespace
session_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 1 for debugging
ini_set('log_errors', 1);

// Include required files
require_once '../config.php'; 
require_once '../functions.php';

// Start output buffering to prevent headers already sent errors
ob_start();

// Initialize variables
$sent = false;
$error_message = '';

// Begin output
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sending Report - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .content-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            border-radius: 5px;
        }
        .section-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #3498db;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: rgba(46, 204, 113, 0.2);
            border-left: 4px solid #2ecc71;
        }
        .error {
            background-color: rgba(231, 76, 60, 0.2);
            border-left: 4px solid #e74c3c;
        }
        .info {
            background-color: rgba(52, 152, 219, 0.2);
            border-left: 4px solid #3498db;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            margin: 5px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            border: none;
            color: white;
        }
        .btn-primary {
            background-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .button-container {
            margin-top: 20px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="content-container">
        <div class="section-header">
            <h1>TIMEMASTER - Sending Report</h1>
        </div>
        
<?php
// Flush output buffer
ob_flush();
flush();

// Enforce strict time limit
set_time_limit(30);

// Check authentication
if (!isset($_SESSION['username'])) {
    echo "<div class='message error'>Authentication required. Please log in.</div>";
    echo "<div class='button-container'>
        <a href='../login.php' class='btn btn-primary'>Go to Login</a>
    </div>";
    echo "</div></body></html>";
    exit;
}

// Check for admin role
if (!function_exists('hasRole') || !hasRole($_SESSION['username'], 'admin')) {
    echo "<div class='message error'>Admin access required.</div>";
    echo "<div class='button-container'>
        <a href='../index.php' class='btn btn-primary'>Return to Home</a>
    </div>";
    echo "</div></body></html>";
    exit;
}

// Get email address from form submission
$email_addresses = isset($_POST['email_addresses']) ? $_POST['email_addresses'] : '';

// Validate email addresses
if (empty($email_addresses)) {
    echo "<div class='message error'>No email addresses provided.</div>";
    echo "<div class='button-container'>
        <a href='reports.php' class='btn btn-primary'>Return to Reports</a>
    </div>";
    echo "</div></body></html>";
    exit;
}

// Get report data from session
$subject = isset($_SESSION['report_subject']) ? $_SESSION['report_subject'] : '';
$report_content = isset($_SESSION['report_content']) ? $_SESSION['report_content'] : '';
$report_type = isset($_SESSION['report_type']) ? $_SESSION['report_type'] : 'report';

if (empty($subject) || empty($report_content)) {
    echo "<div class='message error'>Missing report data. Please generate a report first.</div>";
    echo "<div class='button-container'>
        <a href='reports.php' class='btn btn-primary'>Return to Reports</a>
    </div>";
    echo "</div></body></html>";
    exit;
}

// Display processing message
echo "<div class='message info'>Preparing to send report to: " . htmlspecialchars($email_addresses) . "</div>";
ob_flush();
flush();

// Log attempt
error_log("[REPORT_SEND] Attempting to send report to: $email_addresses");

// Store the original email for notifications
$original_notify_email = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : '';

// Override notification email temporarily
if (defined('NOTIFY_EMAIL')) {
    // Cannot redefine constants, so we'll create a function to get the right email
    function get_report_email() {
        global $email_addresses;
        return $email_addresses;
    }
} else {
    // Define it if not already defined
    define('NOTIFY_EMAIL', $email_addresses);
}

// Create a report message
$message = "Report: $subject\n\n$report_content";

try {
    // Use the existing notification system to send the report
    if (function_exists('get_report_email')) {
        // If we created a function to handle the email override
        $notifyEmail = NOTIFY_EMAIL; // Store original
        sendNotification($message);
    } else {
        // Otherwise NOTIFY_EMAIL is already set to our target
        sendNotification($message);
    }
    $sent = true;
    
    // Show success message
    echo "<div class='message success'>
        <strong>Success!</strong> Report was sent successfully to: " . htmlspecialchars($email_addresses) . "
    </div>";
    
    // Store success message in session
    $_SESSION['message'] = "Report was sent successfully!";
    $_SESSION['success'] = true;
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    
    // Log error
    error_log("[REPORT_SEND] Failed to send: $error_message");
    
    // Show error message
    echo "<div class='message error'>
        <strong>Error sending email:</strong> " . htmlspecialchars($error_message) . "
    </div>";
    
    // Store error message in session
    $_SESSION['message'] = "Error sending report: " . $error_message;
    $_SESSION['success'] = false;
}

// Navigation buttons - removed test email link
echo "<div class='button-container'>
    <a href='reports.php' class='btn btn-primary'>Return to Reports</a>
</div>";

?>
    </div>
</body>
</html> 