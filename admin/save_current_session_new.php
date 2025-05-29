<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    http_response_code(403);
    exit('Unauthorized');
}

// Check if required data is provided
if (!isset($_POST['username']) || !isset($_POST['time_record_id']) || !isset($_POST['clock_in_time'])) {
    http_response_code(400);
    exit('Missing required data');
}

$username = $_POST['username'];
$time_record_id = intval($_POST['time_record_id']);
$clock_in_time = $_POST['clock_in_time'];
$break_time = $_POST['break_time'] ?? null;
$break_out_time = $_POST['break_out_time'] ?? null;
$clock_out_time = $_POST['clock_out_time'] ?? null;

// Connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

// Start transaction
$db->begin_transaction();

try {
    // TIMEMASTER POLICY: Eastern Time Only (America/New_York)
    // This application only uses America/New_York timezone
    date_default_timezone_set('America/New_York');
    
    // Get current date
    $current_date = date('Y-m-d');
    
    // Update clock in time
    if ($clock_in_time) {
        $clock_in_datetime = new DateTime($current_date . ' ' . $clock_in_time, new DateTimeZone('America/New_York'));
        // Store as Eastern Time
        $clock_in = $clock_in_datetime->format('Y-m-d H:i:s');
        
        $stmt = $db->prepare("UPDATE time_records SET clock_in = ? WHERE id = ?");
        $stmt->bind_param('si', $clock_in, $time_record_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update clock in time: ' . $stmt->error);
        }
    }
    
    // Update break in time
    if ($break_time) {
        $break_datetime = new DateTime($current_date . ' ' . $break_time, new DateTimeZone('America/New_York'));
        // Store as Eastern Time
        $break_edt = $break_datetime->format('Y-m-d H:i:s');
        
        // Check if break exists
        $stmt = $db->prepare("SELECT id FROM break_records WHERE time_record_id = ? AND id = ?");
        $stmt->bind_param("ii", $time_record_id, $break_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Create new break record (in Eastern Time)
            $break_datetime = new DateTime($current_date . ' ' . $break_time, new DateTimeZone('America/New_York'));
            // Store as Eastern Time
            $break_edt = $break_datetime->format('Y-m-d H:i:s');
            
            $stmt = $db->prepare("INSERT INTO breaks (time_record_id, break_in) VALUES (?, ?)");
            $stmt->bind_param('is', $time_record_id, $break_edt);
        } else {
            // Update existing break record
            $stmt = $db->prepare("UPDATE breaks SET break_in = ? WHERE id = ?");
            $stmt->bind_param('si', $break_edt, $break_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update break time: ' . $stmt->error);
        }
    }
    
    // Update break out time
    if ($break_out_time) {
        $break_out_datetime = new DateTime($current_date . ' ' . $break_out_time, new DateTimeZone('America/New_York'));
        // Store as Eastern Time
        $break_out_edt = $break_out_datetime->format('Y-m-d H:i:s');
        
        $stmt = $db->prepare("UPDATE breaks SET break_out = ? WHERE id = ?");
        $stmt->bind_param('si', $break_out_edt, $break_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update break out time: ' . $stmt->error);
        }
    }
    
    // Update clock out time
    if ($clock_out_time) {
        $clock_out_datetime = new DateTime($current_date . ' ' . $clock_out_time, new DateTimeZone('America/New_York'));
        // Store as Eastern Time
        $clock_out_edt = $clock_out_datetime->format('Y-m-d H:i:s');
        
        $stmt = $db->prepare("UPDATE time_records SET clock_out = ? WHERE id = ?");
        $stmt->bind_param('si', $clock_out_edt, $time_record_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update clock out time: ' . $stmt->error);
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Log the activity
    $activity_details = json_encode([
        'time_record_id' => $time_record_id,
        'clock_in_time' => $clock_in_time,
        'break_time' => $break_time,
        'break_out_time' => $break_out_time,
        'clock_out_time' => $clock_out_time
    ]);
    
    $stmt = $db->prepare("INSERT INTO activity_log (username, activity_type, activity_details) VALUES (?, 'edit_current_session', ?)");
    $stmt->bind_param('ss', $_SESSION['username'], $activity_details);
    $stmt->execute();
    
    // Send notification
    sendNotification("ADMIN", "Updated current session times for $username");
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 