<?php
// Disable all error display and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'syslog');

// Include required files - config.php must be first to set up session handling
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('America/New_York');

// Log file for debugging
function log_error($message) {
    error_log("TIMEMASTER: " . $message);
}

// Function to send JSON response
function send_json_response($success, $message = '', $error = '', $data = null) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    
    // Clean any HTML tags from messages
    $message = trim(strip_tags($message));
    $error = trim(strip_tags($error));
    
    // Create response array
    $response = [
        'success' => $success,
        'message' => $message,
        'error' => $error
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Encode to JSON with error checking
    $json_response = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    if ($json_response === false) {
        // If encoding fails, send minimal error response
        $json_response = json_encode([
            'success' => false,
            'error' => 'Invalid response data'
        ]);
    }
    
    echo $json_response;
    exit;
}

try {
    // Check if user is logged in using GROK session
    if (!isset($_SESSION['username'])) {
        log_error('User not logged in');
        send_json_response(false, '', 'Not logged in');
    }

    // Get logged in user from GROK
    $admin_username = $_SESSION['username'];
    
    // Get employee data from GROK
    $admin = getEmployee($admin_username);
    if (!$admin) {
        log_error('Admin user not found in GROK: ' . $admin_username);
        send_json_response(false, '', 'Admin user not found');
    }
    
    // Check if user has admin role in GROK
    if (!hasRole($admin_username, 'admin')) {
        log_error('User not admin: ' . $admin_username);
        send_json_response(false, '', 'Unauthorized');
    }

    // Get target username from request
    $username = $_GET['username'] ?? '';
    if (empty($username)) {
        send_json_response(false, '', 'Username is required');
    }
    
    // Verify target employee exists in GROK
    $employee = getEmployee($username);
    if (!$employee) {
        log_error('Target employee not found in GROK: ' . $username);
        send_json_response(false, '', 'Employee not found');
    }

    // Get time record from TIME database
    $stmt = $GLOBALS['time_db']->prepare("
        SELECT id, 
               CASE 
                   WHEN clock_in IS NULL OR clock_in = '0000-00-00 00:00:00' THEN NULL
                   ELSE DATE_FORMAT(clock_in, '%Y-%m-%d %H:%i:%s')
               END as clock_in,
               CASE 
                   WHEN clock_out IS NULL OR clock_out = '0000-00-00 00:00:00' THEN NULL
                   ELSE DATE_FORMAT(clock_out, '%Y-%m-%d %H:%i:%s')
               END as clock_out
        FROM time_records 
        WHERE username = ? 
        AND clock_out IS NULL
        ORDER BY clock_in DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        log_error("No active time record found for user: " . $username);
        send_json_response(false, '', 'No active time record found');
    }

    $time_record = $result->fetch_assoc();
    log_error("Found time record for user " . $username . ": " . json_encode($time_record));
    
    // Get break records from TIME database
    $stmt = $GLOBALS['time_db']->prepare("
        SELECT id, 
               CASE 
                   WHEN break_in IS NULL OR break_in = '0000-00-00 00:00:00' THEN NULL
                   ELSE DATE_FORMAT(break_in, '%Y-%m-%d %H:%i:%s')
               END as break_in,
               CASE 
                   WHEN break_out IS NULL OR break_out = '0000-00-00 00:00:00' THEN NULL
                   ELSE DATE_FORMAT(break_out, '%Y-%m-%d %H:%i:%s')
               END as break_out
        FROM break_records 
        WHERE time_record_id = ? 
        ORDER BY break_in
    ");
    $stmt->bind_param("i", $time_record['id']);
    $stmt->execute();
    $breaks_result = $stmt->get_result();
    
    $breaks = [];
    while ($break = $breaks_result->fetch_assoc()) {
        $breaks[] = [
            'id' => $break['id'],
            'break_in' => $break['break_in'],
            'break_out' => $break['break_out'],
            'is_external' => $break['is_external'],
            'location' => $break['location']
        ];
    }
    
    // Prepare response data
    $response_data = [
        'time_record_id' => $time_record['id'],
        'clock_in' => $time_record['clock_in'],
        'clock_out' => $time_record['clock_out'],
        'breaks' => $breaks
    ];
    
    send_json_response(true, '', '', $response_data);

} catch (Exception $e) {
    log_error("Error in get_current_session.php: " . $e->getMessage());
    send_json_response(false, '', $e->getMessage());
}
?> 