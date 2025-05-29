<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    // Redirect to employee interface
    header('Location: ../index.php');
    exit;
}

// Get logged in user (admin)
$admin_username = $_SESSION['username'];

// Process form submission for editing time records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_times') {
    $response = array();
    
    // Get form data
    $username = $_POST['edit_username'] ?? '';
    $time_record_id = intval($_POST['edit_time_record_id'] ?? 0);
    $date = $_POST['edit_date'] ?? '';
    $clock_in_time = $_POST['edit_clock_in'] ?? '';
    $clock_out_time = $_POST['edit_clock_out'] ?? '';
    $notes = $_POST['edit_notes'] ?? '';
    
    // Validate required fields
    if (empty($username) || $time_record_id <= 0 || empty($date) || empty($clock_in_time)) {
        $_SESSION['success'] = false;
        $_SESSION['message'] = "Missing required fields for time record edit.";
        header('Location: time_records.php?username=' . urlencode($username));
        exit;
    }
    
    // Format the date and time properly
    $clock_in = $date . ' ' . $clock_in_time;
    $clock_out = !empty($clock_out_time) ? $date . ' ' . $clock_out_time : null;
    
    // Connect to database
    $db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($db->connect_error) {
        error_log("Time database connection failed: " . $db->connect_error);
        $_SESSION['success'] = false;
        $_SESSION['message'] = "Database connection failed: " . $db->connect_error;
        header('Location: time_records.php?username=' . urlencode($username));
        exit;
    }
    
    // Debug: Log database info
    error_log("Connected to database: " . TIME_DB_NAME);
    error_log("Updating time record: ID=" . $time_record_id . ", Username=" . $username);
    error_log("Clock in: " . $clock_in . ", Clock out: " . ($clock_out ?? 'null'));
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Update time record with new clock times and notes
        $stmt = $db->prepare("UPDATE time_records SET clock_in = ?, clock_out = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("sssi", $clock_in, $clock_out, $notes, $time_record_id);
        
        if (!$stmt->execute()) {
            error_log("Failed to update time record: " . $stmt->error);
            throw new Exception("Failed to update time record: " . $stmt->error);
        }
        
        // Debug: Verify update
        $verify_stmt = $db->prepare("SELECT id, username, clock_in, clock_out FROM time_records WHERE id = ?");
        $verify_stmt->bind_param("i", $time_record_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $updated_record = $result->fetch_assoc();
        error_log("Updated record: " . print_r($updated_record, true));
        
        // Process break records
        if (isset($_POST['break_id']) && is_array($_POST['break_id'])) {
            $break_ids = $_POST['break_id'];
            $break_ins = $_POST['break_in'] ?? array();
            $break_outs = $_POST['break_out'] ?? array();
            $break_notes = $_POST['break_notes'] ?? array();
            
            for ($i = 0; $i < count($break_ids); $i++) {
                $break_id = $break_ids[$i];
                $break_in = $break_ins[$i] ?? '';
                $break_out = $break_outs[$i] ?? '';
                $break_note = $break_notes[$i] ?? '';
                
                if (empty($break_in)) {
                    continue; // Skip entries with no break-in time
                }
                
                if ($break_id === 'new') {
                    // Insert new break record
                    $stmt = $db->prepare("INSERT INTO break_records (time_record_id, break_in, break_out, notes) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $time_record_id, $break_in, $break_out, $break_note);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add break record: " . $stmt->error);
                    }
                } else {
                    // Update existing break record
                    $stmt = $db->prepare("UPDATE break_records SET break_in = ?, break_out = ?, notes = ? WHERE id = ? AND time_record_id = ?");
                    $stmt->bind_param("sssii", $break_in, $break_out, $break_note, $break_id, $time_record_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update break record: " . $stmt->error);
                    }
                }
            }
        }
        
        // Try to log the activity - but don't fail if the table doesn't exist
        try {
            // Check if activity_log table exists
            $table_check = $db->query("SHOW TABLES LIKE 'activity_log'");
            
            if ($table_check && $table_check->num_rows > 0) {
                // Log the activity if the table exists
                $activity_type = 'edit_time_record';
                $activity_details = json_encode([
                    'time_record_id' => $time_record_id,
                    'username' => $username,
                    'admin_notes' => $notes
                ]);
                
                $stmt = $db->prepare("INSERT INTO activity_log (username, activity_type, activity_details) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $admin_username, $activity_type, $activity_details);
                $stmt->execute();
            } else {
                // If table doesn't exist, create it
                $create_table_sql = "CREATE TABLE activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL,
                    activity_type VARCHAR(50) NOT NULL,
                    activity_details TEXT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if ($db->query($create_table_sql)) {
                    // Log the activity after creating the table
                    $activity_type = 'edit_time_record';
                    $activity_details = json_encode([
                        'time_record_id' => $time_record_id,
                        'username' => $username,
                        'admin_notes' => $notes
                    ]);
                    
                    $stmt = $db->prepare("INSERT INTO activity_log (username, activity_type, activity_details) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $admin_username, $activity_type, $activity_details);
                    $stmt->execute();
                }
                // If table creation fails, we'll just continue without logging
            }
        } catch (Exception $e) {
            // Log the error but don't stop the process
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        // Commit transaction
        $db->commit();
        
        // Send notification
        $notification_message = "Time record updated for $username by $admin_username";
        if (!empty($notes)) {
            $notification_message .= ". Notes: $notes";
        }
        sendNotification("ADMIN", $notification_message);
        
        // Set success message
        $_SESSION['success'] = true;
        $_SESSION['message'] = "Successfully updated time record for $username";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        
        // Set error message
        $_SESSION['success'] = false;
        $_SESSION['message'] = "Error updating time record: " . $e->getMessage();
    }
    
    // Close database connection
    $db->close();
    
    // Redirect back to time records page
    header('Location: time_records.php?username=' . urlencode($username));
    exit;
}

// If we get here, it means the form wasn't submitted correctly
$_SESSION['success'] = false;
$_SESSION['message'] = "Invalid form submission";
header('Location: time_records.php');
exit;
?> 