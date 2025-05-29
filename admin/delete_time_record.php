<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
// Server timezone is overridden to ensure consistent Eastern Time
date_default_timezone_set('America/New_York');

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['time_record_id']) || !isset($_POST['username']) || !isset($_POST['action'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$time_record_id = intval($_POST['time_record_id']);
$username = $_POST['username'];
$action = $_POST['action'];

// Connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Only allow delete action for now
if ($action !== 'delete') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Start transaction
$db->begin_transaction();

try {
    // Verify that the time record exists and belongs to the specified user
    $stmt = $db->prepare("SELECT id FROM time_records WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $time_record_id, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Time record not found or does not belong to the specified user');
    }
    
    // Delete associated break records
    $stmt = $db->prepare("DELETE FROM break_records WHERE record_id = ?");
    $stmt->bind_param("i", $time_record_id);
    $stmt->execute();
    
    // Now delete the time record
    $stmt = $db->prepare("DELETE FROM time_records WHERE id = ?");
    $stmt->bind_param("i", $time_record_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete time record: ' . $stmt->error);
    }
    
    // Log the activity
    $activity_details = json_encode([
        'time_record_id' => $time_record_id,
        'username' => $username
    ]);
    
    $stmt = $db->prepare("INSERT INTO activity_log (username, activity_type, activity_details) VALUES (?, 'delete_time_record', ?)");
    $stmt->bind_param("ss", $_SESSION['username'], $activity_details);
    $stmt->execute();
    
    // Send notification
    sendNotification("ADMIN", "deleted time record for $username (ID: $time_record_id)");
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    // Log the error
    error_log("Error deleting time record (ID: $time_record_id): " . $e->getMessage());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 