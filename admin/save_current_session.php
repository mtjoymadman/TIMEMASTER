<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['username']) || !isset($_POST['time_record_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required data (username or time_record_id)']);
    exit;
}

$username = $_POST['username'];
$time_record_id = intval($_POST['time_record_id']);
$clock_in_time = $_POST['clock_in_time'] ?? null;
$break_time = $_POST['break_time'] ?? null;
$break_out_time = $_POST['break_out_time'] ?? null;
$clock_out_time = $_POST['clock_out_time'] ?? null;

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
date_default_timezone_set('America/New_York');

// Connect to time database
$time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($time_db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $time_db->connect_error]);
    exit;
}

// Start transaction
$time_db->begin_transaction();

try {
    // Check for existing active session
    $stmt = $time_db->prepare("SELECT id, DATE(clock_in) as original_date FROM time_records WHERE id = ? AND username = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement for session check: ' . $time_db->error);
    }
    
    $stmt->bind_param('is', $time_record_id, $username);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute session check: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No time record found with ID: ' . $time_record_id);
    }
    
    $active_session = $result->fetch_assoc();
    $original_date = $active_session['original_date'];
    
    // Use the original date from the record
    $record_date = $original_date;
    
    // Update clock in time (only if provided)
    if ($clock_in_time) {
        $clock_in_full = $record_date . ' ' . $clock_in_time . ':00';
        
        $update_stmt = $time_db->prepare("UPDATE time_records SET clock_in = ? WHERE id = ? AND username = ?");
        if (!$update_stmt) {
            throw new Exception('Failed to prepare statement for updating clock in: ' . $time_db->error);
        }
        
        $update_stmt->bind_param('sis', $clock_in_full, $time_record_id, $username);
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update clock in time: ' . $update_stmt->error);
        }
    }
    
    // If a break time is provided, we need to either add a new break or update the active one
    if (!empty($break_time)) {
        // Format break time with today's date
        $break_time_full = $record_date . ' ' . $break_time . ':00';
        
        // Check if there's an active break
        $break_stmt = $time_db->prepare("SELECT id FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
        $break_stmt->bind_param("i", $time_record_id);
        $break_stmt->execute();
        $active_break_result = $break_stmt->get_result();
        
        if ($active_break_result->num_rows > 0) {
            // There's an active break - update it
            $break_record = $active_break_result->fetch_assoc();
            $break_id = $break_record['id'];
            
            $update_break_stmt = $time_db->prepare("UPDATE break_records SET break_in = ? WHERE id = ?");
            $update_break_stmt->bind_param("si", $break_time_full, $break_id);
            
            if (!$update_break_stmt->execute()) {
                throw new Exception("Failed to update break record: " . $update_break_stmt->error);
            }
        } else {
            // No active break - insert a new one
            $insert_break_stmt = $time_db->prepare("INSERT INTO break_records (time_record_id, break_in) VALUES (?, ?)");
            $insert_break_stmt->bind_param('is', $time_record_id, $break_time_full);
            
            if (!$insert_break_stmt->execute()) {
                throw new Exception("Failed to insert break record: " . $insert_break_stmt->error);
            }
            
            // Refresh active_break_result after adding a new break
            $break_stmt->execute();
            $active_break_result = $break_stmt->get_result();
        }
    }
    
    // If a break out time is provided, we need to update the active break (if there is one)
    if (!empty($break_out_time)) {
        // Format break out time with today's date
        $break_out_full = $record_date . ' ' . $break_out_time . ':00';
        
        if (!isset($active_break_result)) {
            // If we haven't queried for active breaks yet, do so now
            $break_stmt = $time_db->prepare("SELECT id FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
            $break_stmt->bind_param("i", $time_record_id);
            $break_stmt->execute();
            $active_break_result = $break_stmt->get_result();
        }
        
        if ($active_break_result->num_rows > 0) {
            // There's an active break - close it
            $break_record = $active_break_result->fetch_assoc();
            $break_id = $break_record['id'];
            
            $update_break_out_stmt = $time_db->prepare("UPDATE break_records SET break_out = ? WHERE time_record_id = ? AND break_out IS NULL");
            $update_break_out_stmt->bind_param('si', $break_out_full, $time_record_id);
            
            if (!$update_break_out_stmt->execute()) {
                throw new Exception("Failed to update break end: " . $update_break_out_stmt->error);
            }
        }
    }
    
    // Process array of break times if provided
    if (isset($_POST['break_times']) && is_array($_POST['break_times'])) {
        foreach ($_POST['break_times'] as $index => $break_time_value) {
            if (empty($break_time_value)) {
                continue; // Skip empty break time entries
            }
            
            // Format the break time with today's date
            $break_time_full = date('Y-m-d ') . $break_time_value;
            
            // Insert a new break record
            $insert_break_stmt = $time_db->prepare("INSERT INTO break_records (time_record_id, break_in) VALUES (?, ?)");
            $insert_break_stmt->bind_param('is', $time_record_id, $break_time_full);
            
            if (!$insert_break_stmt->execute()) {
                throw new Exception("Failed to insert break record: " . $insert_break_stmt->error);
            }
        }
    }
    
    // Process array of break out times if provided
    if (isset($_POST['break_out_times']) && is_array($_POST['break_out_times'])) {
        foreach ($_POST['break_out_times'] as $index => $break_out_time_value) {
            if (empty($break_out_time_value)) {
                continue; // Skip empty break out time entries
            }
            
            // Format the break out time with today's date
            $break_out_full = date('Y-m-d ') . $break_out_time_value;
            
            // Update the earliest active break
            $update_break_out_stmt = $time_db->prepare("UPDATE break_records SET break_out = ? WHERE time_record_id = ? AND break_out IS NULL");
            $update_break_out_stmt->bind_param('si', $break_out_full, $time_record_id);
            
            if (!$update_break_out_stmt->execute()) {
                throw new Exception("Failed to update break end: " . $update_break_out_stmt->error);
            }
        }
    }
    
    // If clock out time is provided, update time record
    if ($clock_out_time) {
        // Clock out time with seconds added
        $clock_out_full = $record_date . ' ' . $clock_out_time . ':00';
        
        $update_clock_out_stmt = $time_db->prepare("UPDATE time_records SET clock_out = ? WHERE id = ? AND username = ?");
        if (!$update_clock_out_stmt) {
            throw new Exception('Failed to prepare statement for updating clock out: ' . $time_db->error);
        }
        
        $update_clock_out_stmt->bind_param('sis', $clock_out_full, $time_record_id, $username);
        if (!$update_clock_out_stmt->execute()) {
            throw new Exception('Failed to update clock out time: ' . $update_clock_out_stmt->error);
        }
    }
    
    // Commit transaction
    $time_db->commit();
    
    // Send notification
    sendNotification("ADMIN", "Updated time record for $username");
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Time record updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $time_db->rollback();
    
    // Log the error
    error_log("Error saving session for $username: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    // Close database connection
    $time_db->close();
} 