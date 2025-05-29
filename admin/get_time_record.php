<?php
// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if record ID is provided
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Record ID is required']);
    exit;
}

$recordId = intval($_GET['id']);

// Connect to database
$db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($db->connect_error) {
    error_log("Time record fetch error: " . $db->connect_error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
date_default_timezone_set('America/New_York');

try {
    // Get time record
    $stmt = $db->prepare("SELECT id, username, clock_in, clock_out, notes, admin_notes FROM time_records WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare time record statement: " . $db->error);
    }
    
    $stmt->bind_param("i", $recordId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute time record query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Time record not found");
    }
    
    $timeRecord = $result->fetch_assoc();
    
    // Format the timestamps in Eastern Time
    if ($timeRecord['clock_in']) {
        $clockIn = new DateTime($timeRecord['clock_in'], new DateTimeZone('America/New_York'));
        $timeRecord['clock_in'] = $clockIn->format('Y-m-d H:i:s');
    }
    
    if ($timeRecord['clock_out']) {
        $clockOut = new DateTime($timeRecord['clock_out'], new DateTimeZone('America/New_York'));
        $timeRecord['clock_out'] = $clockOut->format('Y-m-d H:i:s');
    }
    
    // Get break records
    $stmt = $db->prepare("SELECT id, break_in, break_out, break_time FROM break_records WHERE time_record_id = ? ORDER BY break_in");
    if (!$stmt) {
        throw new Exception("Failed to prepare break records statement: " . $db->error);
    }
    
    $stmt->bind_param("i", $recordId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute break records query: " . $stmt->error);
    }
    
    $breakResult = $stmt->get_result();
    $breaks = [];
    
    while ($break = $breakResult->fetch_assoc()) {
        // Format break times in Eastern Time
        if ($break['break_in']) {
            $breakIn = new DateTime($break['break_in'], new DateTimeZone('America/New_York'));
            $break['break_in'] = $breakIn->format('Y-m-d H:i:s');
        }
        
        if ($break['break_out']) {
            $breakOut = new DateTime($break['break_out'], new DateTimeZone('America/New_York'));
            $break['break_out'] = $breakOut->format('Y-m-d H:i:s');
        }
        
        $breaks[] = $break;
    }
    
    $timeRecord['breaks'] = $breaks;
    
    // Return time record data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timeRecord' => $timeRecord
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 