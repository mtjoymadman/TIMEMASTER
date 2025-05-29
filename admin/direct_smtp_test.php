<?php
// Simple direct SMTP test without PHPMailer
session_start();
require_once '../functions.php';
define('INCLUDED_FROM_APP', true);

// Check if user is admin
$logged_in_user = $_SESSION['username'] ?? '';
if (!hasRole($logged_in_user, 'admin')) {
    header("Location: index.php");
    exit;
}

// Set a strict time limit
set_time_limit(20);

// Start with clean output
header("Content-Type: text/html; charset=utf-8");
echo "<!DOCTYPE html><html><head><title>Direct SMTP Test</title>";
echo "<meta http-equiv='refresh' content='30;url=smtp_settings.php'>";
echo "<style>body{font-family:Arial,sans-serif;line-height:1.6;margin:20px;} .error{color:red;} .success{color:green;}</style>";
echo "</head><body>";
echo "<h1>Direct SMTP Test</h1>";
echo "<p><a href='smtp_settings.php'>Return to SMTP Settings</a></p>";

// Get Gmail credentials from config
$gmail_user = '';
$gmail_pass = '';

if (file_exists('../lib/smtp_config.php')) {
    include_once '../lib/smtp_config.php';
    if (defined('SMTP_USERNAME')) $gmail_user = SMTP_USERNAME;
    if (defined('SMTP_PASSWORD')) $gmail_pass = SMTP_PASSWORD;
}

echo "<h2>Testing Direct Connection to Gmail</h2>";
echo "<p>Username: " . htmlspecialchars($gmail_user) . "</p>";
echo "<p>Password: " . (empty($gmail_pass) ? "Not set" : "********") . "</p>";

// Function to log and display messages
function log_message($message, $is_error = false) {
    $class = $is_error ? 'error' : 'success';
    echo "<p class='$class'>$message</p>";
    echo str_pad('', 4096) . "\n";
    ob_flush();
    flush();
    error_log($message);
}

// 1. Test socket connection
log_message("Step 1: Testing socket connection to smtp.gmail.com:587");
$socket = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
if (!$socket) {
    log_message("Socket connection failed: $errstr ($errno)", true);
    echo "</body></html>";
    exit;
}
log_message("Socket connection successful!");

// 2. Basic SMTP conversation
log_message("Step 2: Starting SMTP conversation");
$response = fgets($socket, 515);
log_message("Server greeting: " . trim($response));

// 3. Send EHLO
log_message("Step 3: Sending EHLO");
fputs($socket, "EHLO timemaster.com\r\n");
do {
    $response = fgets($socket, 515);
    log_message("Response: " . trim($response));
} while (substr($response, 3, 1) == '-');

// 4. Start TLS
log_message("Step 4: Starting TLS");
fputs($socket, "STARTTLS\r\n");
$response = fgets($socket, 515);
log_message("Response: " . trim($response));

if (substr($response, 0, 3) != '220') {
    log_message("Failed to start TLS", true);
    fclose($socket);
    echo "</body></html>";
    exit;
}

// 5. Enable crypto on the socket
log_message("Step 5: Enabling TLS encryption");
stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
log_message("TLS encryption enabled");

// 6. Send EHLO again after TLS
log_message("Step 6: Sending EHLO after TLS");
fputs($socket, "EHLO timemaster.com\r\n");
do {
    $response = fgets($socket, 515);
    log_message("Response: " . trim($response));
} while (substr($response, 3, 1) == '-');

// 7. AUTH LOGIN
log_message("Step 7: Attempting authentication");
fputs($socket, "AUTH LOGIN\r\n");
$response = fgets($socket, 515);
log_message("Response: " . trim($response));

// 8. Send username (base64 encoded)
log_message("Step 8: Sending username");
fputs($socket, base64_encode($gmail_user) . "\r\n");
$response = fgets($socket, 515);
log_message("Response: " . trim($response));

// 9. Send password (base64 encoded)
log_message("Step 9: Sending password");
fputs($socket, base64_encode($gmail_pass) . "\r\n");
$response = fgets($socket, 515);
log_message("Response: " . trim($response));

if (substr($response, 0, 3) != '235') {
    log_message("Authentication failed: " . trim($response), true);
    log_message("This confirms it's an authentication issue with Gmail, not a server connectivity problem.", true);
    fclose($socket);
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure 2-Step Verification is enabled on your Google account</li>";
    echo "<li>Generate a new App Password specifically for this application</li>";
    echo "<li>Ensure your App Password doesn't contain spaces or dashes</li>";
    echo "<li>Verify your Gmail account allows less secure apps or app passwords</li>";
    echo "</ul>";
    echo "</body></html>";
    exit;
}

log_message("Authentication successful!");

// 10. Send QUIT
log_message("Step 10: Closing connection");
fputs($socket, "QUIT\r\n");
fclose($socket);
log_message("Connection closed");

echo "<h2>Test Completed Successfully!</h2>";
echo "<p>This confirms your server CAN connect to Gmail SMTP and authenticate correctly.</p>";
echo "<p>The issue is likely in PHPMailer's implementation or configuration.</p>";
echo "<p><a href='smtp_settings.php' class='success'>Return to SMTP Settings</a></p>";
echo "</body></html>"; 