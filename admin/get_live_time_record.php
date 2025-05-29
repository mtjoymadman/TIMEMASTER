<?php
// Start session
session_start();

// Include configuration and functions
require_once('../config.php');
require_once('../functions.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set timezone to Eastern Time
date_default_timezone_set('America/New_York');

// Check if username and time_record_id parameters are provided
if (!isset($_GET['username']) || empty($_GET['username'])) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

$username = $_GET['username'];

// Connect to the database
$conn = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);

// Check connection
if ($conn->connect_error) {
    send_json_response(false, '', 'Database connection failed: ' . $conn->connect_error);
}

try {
    // Get the current time record for the employee
    $stmt = $conn->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $timeRecord = $result->fetch_assoc();
    
    if (!$timeRecord) {
        // Log the error to help diagnose issues
        error_log("get_live_time_record.php: No active time record found for user $username");
        echo json_encode(['success' => false, 'message' => "No active time record found for $username. Please make sure the employee is clocked in."]);
        exit;
    }
    
    // Format the timestamps to be in Eastern Time
    if ($timeRecord['clock_in']) {
        $clockIn = new DateTime($timeRecord['clock_in'], new DateTimeZone('America/New_York'));
        $timeRecord['clock_in'] = $clockIn->format('Y-m-d H:i:s');
    }
    
    if ($timeRecord['clock_out']) {
        $clockOut = new DateTime($timeRecord['clock_out'], new DateTimeZone('America/New_York'));
        $timeRecord['clock_out'] = $clockOut->format('Y-m-d H:i:s');
    }
    
    // Get all break records for this time record
    $stmt = $conn->prepare("
        SELECT id, break_in, break_out
        FROM break_records
        WHERE time_record_id = ?
        ORDER BY break_in ASC
    ");
    $stmt->bind_param("i", $timeRecord['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $breaks = [];
    while ($row = $result->fetch_assoc()) {
        $breaks[] = $row;
    }
    
    // Format break timestamps to be in Eastern Time
    foreach ($breaks as &$break) {
        if ($break['break_in']) {
            $breakIn = new DateTime($break['break_in'], new DateTimeZone('America/New_York'));
            $break['break_in'] = $breakIn->format('Y-m-d H:i:s');
        }
        
        if ($break['break_out']) {
            $breakOut = new DateTime($break['break_out'], new DateTimeZone('America/New_York'));
            $break['break_out'] = $breakOut->format('Y-m-d H:i:s');
        }
    }
    
    $timeRecord['breaks'] = $breaks;
    
    echo json_encode(['success' => true, 'timeRecord' => $timeRecord]);
    
} catch (Exception $e) {
    error_log("Database error in get_live_time_record.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 