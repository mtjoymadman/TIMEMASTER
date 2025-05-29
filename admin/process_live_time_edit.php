<?php
// Disable all error display and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'syslog');

// Start output buffering with error handler
ob_start(function($buffer) {
    // If we detect HTML tags in the buffer, something went wrong
    if (preg_match('/<[^>]+>/', $buffer)) {
        error_log("HTML content detected in output buffer: " . substr($buffer, 0, 100));
        return json_encode(['success' => false, 'error' => 'Internal server error']);
    }
    return $buffer;
});

// Include required files
require_once '../config.php';
require_once '../functions.php';

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
function send_json_response($success, $message = '', $error = '') {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start new clean buffer
    ob_start();
    
    // Set headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    
    // Clean any HTML tags from messages
    $message = trim(strip_tags($message));
    $error = trim(strip_tags($error));
    
    // Create response array
    $response_array = [
        'success' => $success,
        'message' => $message
    ];
    
    // Only add error field if there's an error
    if (!empty($error)) {
        $response_array['error'] = $error;
    }
    
    // Encode to JSON with error checking
    $json_response = json_encode($response_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    if ($json_response === false) {
        // If encoding fails, send minimal error response
        $json_response = json_encode([
            'success' => false,
            'error' => 'Invalid response data'
        ]);
    }
    
    echo $json_response;
    ob_end_flush();
    exit;
}

// Start tracking issues
log_error('Process live time edit started');

try {
    // Ensure no output before this point
    if (ob_get_length()) ob_clean();
    
    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        log_error('User not logged in');
        send_json_response(false, '', 'Not logged in');
    }

    // Get logged in user
    $admin_username = $_SESSION['username'];
    
    // Check if user has admin role using grok database
    if (!hasRole($admin_username, 'admin')) {
        log_error('User not admin: ' . $admin_username);
        send_json_response(false, '', 'Unauthorized');
    }

    // Process form submission for editing live time records
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_live_time') {
        log_error('Form submitted with action: edit_live_time');
        
        // Get form data
        $username = $_POST['live_edit_username'] ?? '';
        $time_record_id = intval($_POST['live_edit_time_record_id'] ?? 0);
        $clock_in_time = $_POST['live_edit_clock_in'] ?? '';
        $notes = $_POST['live_edit_notes'] ?? '';
        
        log_error("Form data - Username: $username, Record ID: $time_record_id, Clock in: $clock_in_time");
        
        // Make sure we have today's date in the clock in time
        if (!empty($clock_in_time)) {
            // Check if the clock_in_time already includes a date
            if (strpos($clock_in_time, ':') !== false && strpos($clock_in_time, '-') === false) {
                // It's just a time, add today's date
                $clock_in = date('Y-m-d ') . $clock_in_time;
            } else {
                // It already has a date or is empty
                $clock_in = $clock_in_time;
            }
        } else {
            $clock_in = '';
        }
        
        log_error("Formatted clock in time: $clock_in");
        
        // Validate required fields
        if (empty($username) || $time_record_id <= 0) {
            log_error("Validation failed - Username: $username, Record ID: $time_record_id, Clock in: $clock_in");
            send_json_response(false, '', 'Missing required fields');
        }
        
        // Connect to database
        log_error("Connecting to database");
        $db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
        if ($db->connect_error) {
            log_error("Database connection failed: " . $db->connect_error);
            send_json_response(false, '', 'Database connection failed');
        }
        
        // Check if the time record exists and is active
        log_error("Checking if time record exists and is active: $time_record_id");
        $check_stmt = $db->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE id = ? AND username = ?");
        $check_stmt->bind_param("is", $time_record_id, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            log_error("No time record found with ID: $time_record_id");
            send_json_response(false, '', 'Time record not found');
        }
        
        $time_record = $check_result->fetch_assoc();
        if ($time_record['clock_out'] !== null) {
            log_error("Time record is already clocked out: $time_record_id");
            send_json_response(false, '', 'Time record is already clocked out');
        }
        
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Update the time record
            if (!empty($clock_in)) {
                $stmt = $db->prepare("UPDATE time_records SET clock_in = ? WHERE id = ?");
                $stmt->bind_param("si", $clock_in, $time_record_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update clock in time: " . $stmt->error);
                }
            }
            
            // Process break records
            if (isset($_POST['break_in']) && is_array($_POST['break_in'])) {
                foreach ($_POST['break_in'] as $index => $break_in) {
                    $break_id = $_POST['break_id'][$index] ?? 'new';
                    $break_out = $_POST['break_out'][$index] ?? '';
                    
                    if ($break_id === 'new') {
                        // Insert new break
                        $stmt = $db->prepare("INSERT INTO break_records (time_record_id, break_in, break_out) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $time_record_id, $break_in, $break_out);
                    } else {
                        // Update existing break
                        $stmt = $db->prepare("UPDATE break_records SET break_in = ?, break_out = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $break_in, $break_out, $break_id);
                    }
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to process break record: " . $stmt->error);
                    }
                }
            }
            
            // Log the edit
            $activity_details = json_encode([
                'action' => 'edit_live_time',
                'time_record_id' => $time_record_id,
                'clock_in' => $clock_in,
                'notes' => strip_tags($notes)
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            $stmt = $db->prepare("INSERT INTO activity_log (username, activity_type, activity_details) VALUES (?, 'time_edit', ?)");
            $stmt->bind_param("ss", $admin_username, $activity_details);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            // Send success response
            send_json_response(true, 'Time record updated successfully');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            log_error("Error processing time edit: " . $e->getMessage());
            send_json_response(false, '', $e->getMessage());
        }
        
        // Close database connection
        $db->close();
        
    } else {
        // Invalid request
        send_json_response(false, '', 'Invalid request');
    }
    
} catch (Exception $e) {
    log_error("Unexpected error: " . $e->getMessage());
    send_json_response(false, '', 'An unexpected error occurred');
}
?> 