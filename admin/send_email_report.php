<?php
// Prevent any output before we're ready
ob_start();

// Start session
session_start();

// Include required files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
define('INCLUDED_FROM_APP', true);

// Function to return a clean JSON response
function json_response($success, $message) {
    // Clear any buffered output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Log the message regardless of success/failure
    error_log("Email response: " . ($success ? "SUCCESS" : "ERROR") . " - " . $message);
    
    // Create the response array
    $response = ['success' => $success, 'message' => $message];
    
    // Return valid JSON - handle any errors that might occur during encoding
    try {
        $json = json_encode($response);
        if ($json === false) {
            // If JSON encoding fails, send a simple error message
            echo '{"success":false,"message":"JSON encoding error: ' . json_last_error_msg() . '"}';
        } else {
            echo $json;
        }
    } catch (Exception $e) {
        echo '{"success":false,"message":"Error generating JSON response"}';
    }
    
    exit;
}

// Function to capture and log detailed error info
function log_detailed_error($message, $additional = null) {
    error_log("DETAILED ERROR: " . $message);
    
    if ($additional) {
        if (is_array($additional) || is_object($additional)) {
            error_log("Additional info: " . print_r($additional, true));
        } else {
            error_log("Additional info: " . $additional);
        }
    }
    
    // Capture the PHP error if any
    $error = error_get_last();
    if ($error) {
        error_log("PHP Error: " . print_r($error, true));
    }
}

// Disable error display but log them
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set error handler to catch and log fatal errors too
function fatal_error_handler() {
    $error = error_get_last();
    if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
        error_log("FATAL ERROR: " . print_r($error, true));
    }
}
register_shutdown_function('fatal_error_handler');

// Set a longer timeout for report emails
set_time_limit(120); // 2 minutes should be plenty for email operations

try {
    // Security check - ensure this file is being accessed by an admin user
    if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
        json_response(false, 'Access denied');
    }
    
    // Check if PHPMailer configuration exists
    if (!file_exists(__DIR__ . '/../lib/smtp_config.php')) {
        json_response(false, 'Email configuration not found');
    }
    
    require_once __DIR__ . '/../lib/smtp_config.php';
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, 'Invalid request method');
    }
    
    // Get and validate input data
    $emails = isset($_POST['emails']) ? $_POST['emails'] : '';
    $report_html = isset($_POST['report_html']) ? $_POST['report_html'] : '';
    
    if (empty($emails)) {
        json_response(false, 'No email addresses provided');
    }
    
    if (empty($report_html)) {
        json_response(false, 'No report content provided');
    }
    
    // Process email addresses (comma-separated)
    $email_addresses = array_map('trim', explode(',', $emails));
    $valid_emails = [];
    
    // Validate email addresses
    foreach ($email_addresses as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valid_emails[] = $email;
        }
    }
    
    if (empty($valid_emails)) {
        json_response(false, 'No valid email addresses found');
    }
    
    error_log("Valid email addresses: " . implode(', ', $valid_emails));
    
    // Prepare email subject
    $subject = 'TIMEMASTER Report - ' . date('M d, Y');
    
    // Prepare email body with proper HTML structure
    $body = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>TIMEMASTER Report</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 800px; margin: 0 auto; }
            .header { background-color: #c0392b; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; }
            .footer { font-size: 12px; color: #777; text-align: center; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>TIMEMASTER Report</h1>
                <p>Generated on ' . date('F j, Y') . '</p>
            </div>
            <div class="content">
                ' . $report_html . '
            </div>
            <div class="footer">
                <p>This is an automated message from the TIMEMASTER system.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Send the email
    error_log("Attempting to send report email to: " . implode(', ', $valid_emails));
    
    // Ultra simple direct email - bypass all the fancy stuff
    try {
        // Verify we have the SMTP lib
        if (!function_exists('send_smtp_email')) {
            require_once '../lib/smtp_config.php';
        }
        
        // Ensure this file doesn't timeout
        ini_set('max_execution_time', 90);
        set_time_limit(90);
        
        // Setup PHPMailer directly
        require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rlstimeclock@gmail.com';
        $mail->Password = 'wsilgdzeouzremou'; // New App Password (generated 3/19/2025)
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Get first recipient
        $recipient = is_array($valid_emails) ? $valid_emails[0] : $valid_emails;
        $recipient = trim($recipient);
        
        // Set from/to
        $mail->setFrom('rlstimeclock@gmail.com', 'TIMEMASTER');
        $mail->addAddress($recipient);
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Send it
        $mail->send();
        
        // If we get here, it worked
        json_response(true, 'Report sent successfully to ' . $recipient);
    } catch (Exception $e) {
        error_log("DIRECT EMAIL ERROR: " . $e->getMessage());
        json_response(false, 'Error sending email: ' . $e->getMessage());
    }
} catch (Exception $e) {
    log_detailed_error("Exception in send_email_report.php", $e->getMessage());
    json_response(false, 'An error occurred: ' . $e->getMessage());
}

// If we somehow get here (we shouldn't due to the exit calls in json_response)
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown error occurred']);
exit; 