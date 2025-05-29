<?php
// Start session
session_start();

// Include configuration and functions
require_once('../config.php');
require_once('../functions.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['ADMIN', 'MANAGER'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set timezone to Eastern Time
date_default_timezone_set('America/New_York');

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$timeRecordId = isset($_POST['time_record_id']) ? intval($_POST['time_record_id']) : 0;
$clockIn = isset($_POST['clock_in']) ? $_POST['clock_in'] : null;
$adminNotes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';

// Validate required fields
if ($timeRecordId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid time record ID']);
    exit;
}

try {
    // Connect to database
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get current date
    $currentDate = date('Y-m-d');
    
    // Format the clock in time for database
    if ($clockIn) {
        // Combine the current date with the time input
        $clockInDateTime = new DateTime($currentDate . ' ' . $clockIn, new DateTimeZone('America/New_York'));
        $formattedClockIn = $clockInDateTime->format('Y-m-d H:i:s');
    } else {
        $formattedClockIn = null;
    }
    
    // Update time record
    $stmt = $conn->prepare("
        UPDATE time_records
        SET clock_in = :clock_in, admin_notes = :admin_notes, last_modified = NOW(), modified_by = :modified_by
        WHERE id = :id
    ");
    $stmt->bindParam(':clock_in', $formattedClockIn);
    $stmt->bindParam(':admin_notes', $adminNotes);
    $stmt->bindParam(':modified_by', $_SESSION['username']);
    $stmt->bindParam(':id', $timeRecordId);
    $stmt->execute();
    
    // Process break records
    if (isset($_POST['break_id']) && is_array($_POST['break_id'])) {
        $breakIds = $_POST['break_id'];
        $breakIns = $_POST['break_in'];
        $breakOuts = $_POST['break_out'];
        
        for ($i = 0; $i < count($breakIds); $i++) {
            $breakId = intval($breakIds[$i]);
            $breakIn = $breakIns[$i];
            $breakOut = isset($breakOuts[$i]) ? $breakOuts[$i] : null;
            
            // Format break times for database
            if ($breakIn) {
                $breakInDateTime = new DateTime($currentDate . ' ' . $breakIn, new DateTimeZone('America/New_York'));
                $formattedBreakIn = $breakInDateTime->format('Y-m-d H:i:s');
            } else {
                $formattedBreakIn = null;
            }
            
            if ($breakOut) {
                $breakOutDateTime = new DateTime($currentDate . ' ' . $breakOut, new DateTimeZone('America/New_York'));
                $formattedBreakOut = $breakOutDateTime->format('Y-m-d H:i:s');
            } else {
                $formattedBreakOut = null;
            }
            
            // Update break record
            $stmt = $conn->prepare("
                UPDATE break_records
                SET break_in = :break_in, break_out = :break_out, last_modified = NOW(), modified_by = :modified_by
                WHERE id = :id AND time_record_id = :time_record_id
            ");
            $stmt->bindParam(':break_in', $formattedBreakIn);
            $stmt->bindParam(':break_out', $formattedBreakOut);
            $stmt->bindParam(':modified_by', $_SESSION['username']);
            $stmt->bindParam(':id', $breakId);
            $stmt->bindParam(':time_record_id', $timeRecordId);
            $stmt->execute();
        }
    }
    
    // Add new break if provided
    if (isset($_POST['new_break_in']) && !empty($_POST['new_break_in'])) {
        $newBreakIn = $_POST['new_break_in'];
        $newBreakOut = isset($_POST['new_break_out']) && !empty($_POST['new_break_out']) ? $_POST['new_break_out'] : null;
        
        // Format new break times for database
        $newBreakInDateTime = new DateTime($currentDate . ' ' . $newBreakIn, new DateTimeZone('America/New_York'));
        $formattedNewBreakIn = $newBreakInDateTime->format('Y-m-d H:i:s');
        
        if ($newBreakOut) {
            $newBreakOutDateTime = new DateTime($currentDate . ' ' . $newBreakOut, new DateTimeZone('America/New_York'));
            $formattedNewBreakOut = $newBreakOutDateTime->format('Y-m-d H:i:s');
        } else {
            $formattedNewBreakOut = null;
        }
        
        // Insert new break record
        $stmt = $conn->prepare("
            INSERT INTO break_records (time_record_id, break_in, break_out, created_at, created_by)
            VALUES (:time_record_id, :break_in, :break_out, NOW(), :created_by)
        ");
        $stmt->bindParam(':time_record_id', $timeRecordId);
        $stmt->bindParam(':break_in', $formattedNewBreakIn);
        $stmt->bindParam(':break_out', $formattedNewBreakOut);
        $stmt->bindParam(':created_by', $_SESSION['username']);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the activity
    logActivity($_SESSION['username'], 'Modified time record #' . $timeRecordId);
    
    echo json_encode(['success' => true, 'message' => 'Time record updated successfully']);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Database error in save_live_time_edit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 