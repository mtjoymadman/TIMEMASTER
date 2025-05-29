<?php
// Start session immediately before any output
session_start();

// Start output buffering to prevent any output before headers
ob_start();

// Disable display errors to prevent output before headers
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
date_default_timezone_set('America/New_York');

// Set up custom log file
function custom_log($message) {
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_admin_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('admin_clock_actions.php - Failed to write to custom log file: ' . $log_file);
        error_log('admin_clock_actions.php - ' . $message);
    }
}

custom_log('Step 0: admin_clock_actions.php execution started');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    custom_log('Step 1: User not logged in, redirecting to login');
    header('Location: ../login.php');
    ob_end_flush();
    exit;
}
custom_log('Step 1: User logged in, proceeding');

// Get database connections from global scope
global $time_db, $grok_db;

// Check if database connections are initialized
if (!isset($time_db) || !isset($grok_db)) {
    error_log('Database connections not initialized, attempting to initialize...');
    // Initialize grok database connection
    error_log('Connecting to grok database...');
    $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
    if ($grok_db->connect_error) {
        $error_msg = 'Grok database connection failed: ' . $grok_db->connect_error;
        error_log($error_msg);
        die('Database connection error: ' . $error_msg);
    }
    error_log('Grok database connection successful');
    // Initialize time database connection
    error_log('Connecting to time database...');
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        $error_msg = 'Time database connection failed: ' . $time_db->connect_error;
        error_log($error_msg);
        die('Database connection error: ' . $error_msg);
    }
    error_log('Time database connection successful');
    // Set timezone for both databases
    error_log('Setting timezone for database connections...');
    $timezone = 'America/New_York';
    $grok_db->query("SET time_zone = '$timezone'");
    $time_db->query("SET time_zone = '$timezone'");
    error_log('Timezone set successfully for both databases');
    error_log('Database initialization completed successfully');
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    custom_log('Step 2: User does not have admin role, redirecting');
    header('Location: ../index.php');
    ob_end_flush();
    exit;
}
custom_log('Step 2: User has admin role, proceeding');

// Process actions
$action = isset($_POST['action']) ? $_POST['action'] : '';
$redirect_back = isset($_POST['redirect_back']) && $_POST['redirect_back'] === 'true';
$employee = isset($_POST['employee']) ? $_POST['employee'] : '';
$error = '';
$success = '';

custom_log('Step 3: Action received: ' . $action);

if ($action === 'create_time_record') {
    custom_log('Step 4: Processing create_time_record action');
    if (!empty($employee)) {
        $record_date = $_POST['record_date'];
        $start_time = $_POST['start_time'];
        $break_start = isset($_POST['break_start']) ? $_POST['break_start'] : '';
        $break_end = isset($_POST['break_end']) ? $_POST['break_end'] : '';
        $out_time = isset($_POST['out_time']) ? $_POST['out_time'] : '';
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
        custom_log('Step 4.1: Parameters - employee: ' . $employee . ', record_date: ' . $record_date . ', start_time: ' . $start_time . ', break_start: ' . $break_start . ', break_end: ' . $break_end . ', out_time: ' . $out_time . ', overwrite: ' . ($overwrite ? 'true' : 'false'));
        // Validate input
        if (empty($record_date) || empty($start_time)) {
            $error = 'Date and Start Time are required fields';
            custom_log('Step 4.2: Error - Missing required fields');
        } else {
            $clock_in = $record_date . ' ' . $start_time;
            $clock_out = !empty($out_time) ? $record_date . ' ' . $out_time : null;
            if ($clock_out && strtotime($clock_out) <= strtotime($clock_in)) {
                $clock_out = date('Y-m-d', strtotime($record_date . ' +1 day')) . ' ' . $out_time;
                custom_log('Step 4.3: Adjusted clock_out to next day: ' . $clock_out);
            }
            // Check for conflicting records if not overwriting
            if (!$overwrite) {
                $stmt = $time_db->prepare("SELECT id FROM time_records WHERE username = ? AND DATE(clock_in) = ? AND clock_out IS NOT NULL");
                if ($stmt) {
                    $stmt->bind_param("ss", $employee, $record_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $error = 'A time record already exists for this date. Check "Overwrite Conflicts" to replace it.';
                        custom_log('Step 4.4: Error - Conflicting record found in time_db');
                    }
                    $stmt->close();
                }
            }
            if (empty($error)) {
                // Delete existing record if overwrite is enabled
                if ($overwrite) {
                    custom_log('Step 4.5: Overwrite enabled, deleting existing records for ' . $employee . ' on ' . $record_date);
                    $stmt = $time_db->prepare("DELETE FROM time_records WHERE username = ? AND DATE(clock_in) = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $employee, $record_date);
                        $stmt->execute();
                        custom_log('Step 4.6: Deleted ' . $stmt->affected_rows . ' records from time_db');
                        $stmt->close();
                    }
                    $stmt = $grok_db->prepare("DELETE FROM time_records WHERE username = ? AND DATE(clock_in) = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $employee, $record_date);
                        $stmt->execute();
                        custom_log('Step 4.7: Deleted ' . $stmt->affected_rows . ' records from grok_db');
                        $stmt->close();
                    }
                }
                // Insert new record into both databases
                custom_log('Step 4.8: Inserting new time record into time_db');
                $stmt = $time_db->prepare("INSERT INTO time_records (username, clock_in, clock_out) VALUES (?, ?, ?)");
                if ($stmt === false) {
                    $error = 'Database error: Failed to prepare statement for time_db';
                    custom_log('Step 4.9: Error preparing statement for time_db: ' . $time_db->error);
                } else {
                    $stmt->bind_param("sss", $employee, $clock_in, $clock_out);
                    if ($stmt->execute()) {
                        $time_record_id = $stmt->insert_id;
                        custom_log('Step 4.9: Time record inserted into time_db with ID: ' . $time_record_id);
                        $success = 'Time record created successfully';
                        // Insert into grok_db for consistency
                        $stmt2 = $grok_db->prepare("INSERT INTO time_records (username, clock_in, clock_out) VALUES (?, ?, ?)");
                        if ($stmt2) {
                            $stmt2->bind_param("sss", $employee, $clock_in, $clock_out);
                            if ($stmt2->execute()) {
                                custom_log('Step 4.10: Time record synced to grok_db with ID: ' . $grok_db->insert_id);
                            } else {
                                custom_log('Step 4.10: Failed to sync to grok_db: ' . $stmt2->error);
                            }
                            $stmt2->close();
                        }
                        // Add break if provided
                        if (!empty($break_start)) {
                            $break_in = $record_date . ' ' . $break_start;
                            $break_out = !empty($break_end) ? $record_date . ' ' . $break_end : null;
                            if ($break_out && strtotime($break_out) <= strtotime($break_in)) {
                                $break_out = date('Y-m-d', strtotime($record_date . ' +1 day')) . ' ' . $break_end;
                                custom_log('Step 4.11: Adjusted break_out to next day: ' . $break_out);
                            }
                            custom_log('Step 4.12: Inserting break for time record ID ' . $time_record_id);
                            $break_stmt = $time_db->prepare("INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)");
                            if ($break_stmt) {
                                $break_stmt->bind_param("iss", $time_record_id, $break_in, $break_out);
                                if ($break_stmt->execute()) {
                                    custom_log('Step 4.13: Break inserted into time_db with ID: ' . $time_db->insert_id);
                                    // Sync break to grok_db
                                    $break_stmt2 = $grok_db->prepare("INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)");
                                    if ($break_stmt2) {
                                        $break_stmt2->bind_param("iss", $time_record_id, $break_in, $break_out);
                                        if ($break_stmt2->execute()) {
                                            custom_log('Step 4.14: Break synced to grok_db with ID: ' . $grok_db->insert_id);
                                        } else {
                                            custom_log('Step 4.14: Failed to sync break to grok_db: ' . $break_stmt2->error);
                                        }
                                        $break_stmt2->close();
                                    }
                                } else {
                                    custom_log('Step 4.13: Failed to insert break into time_db: ' . $break_stmt->error);
                                }
                                $break_stmt->close();
                            }
                        }
                    } else {
                        $error = 'Failed to create time record in time_db';
                        custom_log('Step 4.9: Failed to insert into time_db: ' . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
    } else {
        $error = 'No employee selected';
        custom_log('Step 4.1: Error - No employee selected');
    }
    if ($redirect_back) {
        custom_log('Step 5: Redirecting back to time_record_editor.php');
        if (!empty($success)) {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&success=' . urlencode($success));
        } else {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&error=' . urlencode($error));
        }
        ob_end_flush();
        exit;
    }
} elseif ($action === 'delete_time_record') {
    custom_log('Step 4: Processing delete_time_record action');
    $time_record_id = isset($_POST['time_record_id']) ? $_POST['time_record_id'] : '';
    $db_source = isset($_POST['db_source']) ? $_POST['db_source'] : 'time_db';
    if (!empty($time_record_id)) {
        custom_log('Step 4.1: Deleting time record ID ' . $time_record_id . ' from ' . $db_source);
        $primary_db = ($db_source === 'grok_db') ? $grok_db : $time_db;
        // Delete associated breaks first
        $stmt = $primary_db->prepare("DELETE FROM breaks WHERE time_record_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $time_record_id);
            if ($stmt->execute()) {
                custom_log('Step 4.2: Deleted ' . $stmt->affected_rows . ' breaks from primary DB (' . $db_source . ')');
            } else {
                custom_log('Step 4.2: Failed to delete breaks from primary DB: ' . $stmt->error);
            }
            $stmt->close();
        }
        // Delete time record
        $stmt = $primary_db->prepare("DELETE FROM time_records WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $time_record_id);
            if ($stmt->execute()) {
                custom_log('Step 4.3: Time record deleted from primary DB (' . $db_source . ')');
                $success = 'Time record deleted successfully';
                // Sync deletion to secondary database
                $secondary_db = ($db_source === 'grok_db') ? $time_db : $grok_db;
                $stmt2 = $secondary_db->prepare("DELETE FROM breaks WHERE time_record_id = ?");
                if ($stmt2) {
                    $stmt2->bind_param("i", $time_record_id);
                    if ($stmt2->execute()) {
                        custom_log('Step 4.4: Deleted ' . $stmt2->affected_rows . ' breaks from secondary DB');
                    }
                    $stmt2->close();
                }
                $stmt2 = $secondary_db->prepare("DELETE FROM time_records WHERE id = ?");
                if ($stmt2) {
                    $stmt2->bind_param("i", $time_record_id);
                    if ($stmt2->execute()) {
                        custom_log('Step 4.5: Time record synced deletion to secondary DB');
                    }
                    $stmt2->close();
                }
            } else {
                $error = 'Failed to delete time record from primary database';
                custom_log('Step 4.3: Failed to delete time record from primary DB: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            $error = 'Database error: Failed to prepare statement for deletion';
            custom_log('Step 4.3: Error preparing statement for deletion: ' . $primary_db->error);
        }
    } else {
        $error = 'No time record ID provided';
        custom_log('Step 4.1: Error - No time record ID provided');
    }
    if ($redirect_back) {
        custom_log('Step 5: Redirecting back to clock_management.php');
        if (!empty($success)) {
            header('Location: clock_management.php?show_records=true&employee=' . urlencode($employee) . '&success=' . urlencode($success));
        } else {
            header('Location: clock_management.php?show_records=true&employee=' . urlencode($employee) . '&error=' . urlencode($error));
        }
        ob_end_flush();
        exit;
    }
} elseif ($action === 'delete_break') {
    custom_log('Step 4: Processing delete_break action');
    $break_id = isset($_POST['break_id']) ? $_POST['break_id'] : '';
    if (!empty($break_id)) {
        custom_log('Step 4.1: Deleting break ID ' . $break_id);
        // Delete from time_db
        $stmt = $time_db->prepare("DELETE FROM breaks WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $break_id);
            if ($stmt->execute()) {
                custom_log('Step 4.2: Break deleted from time_db');
                $success = 'Break deleted successfully';
            } else {
                custom_log('Step 4.2: Failed to delete break from time_db: ' . $stmt->error);
                $error = 'Failed to delete break';
            }
            $stmt->close();
        }
    } else {
        $error = 'No break ID provided';
        custom_log('Step 4.1: Error - No break ID provided');
    }
    if ($redirect_back) {
        custom_log('Step 5: Redirecting back to time_record_editor.php');
        if (!empty($success)) {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&success=' . urlencode($success));
        } else {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&error=' . urlencode($error));
        }
        ob_end_flush();
        exit;
    }
} elseif ($action === 'add_break') {
    custom_log('Step 4: Processing add_break action');
    $time_record_id = isset($_POST['time_record_id']) ? $_POST['time_record_id'] : '';
    $break_date = isset($_POST['break_date']) ? $_POST['break_date'] : '';
    $break_start = isset($_POST['break_start']) ? $_POST['break_start'] : '';
    $break_end = isset($_POST['break_end']) ? $_POST['break_end'] : '';
    
    if (!empty($time_record_id) && !empty($break_date) && !empty($break_start)) {
        custom_log('Step 4.1: Adding break for time record ID ' . $time_record_id);
        $break_in = $break_date . ' ' . $break_start;
        $break_out = !empty($break_end) ? $break_date . ' ' . $break_end : null;
        
        if ($break_out && strtotime($break_out) <= strtotime($break_in)) {
            $break_out = date('Y-m-d', strtotime($break_date . ' +1 day')) . ' ' . $break_end;
            custom_log('Step 4.2: Adjusted break_out to next day: ' . $break_out);
        }
        
        // Insert into time_db
        $stmt = $time_db->prepare("INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $time_record_id, $break_in, $break_out);
            if ($stmt->execute()) {
                custom_log('Step 4.3: Break added to time_db');
                $success = 'Break added successfully';
            } else {
                custom_log('Step 4.3: Failed to add break to time_db: ' . $stmt->error);
                $error = 'Failed to add break';
            }
            $stmt->close();
        }
    } else {
        $error = 'Missing required fields for break';
        custom_log('Step 4.1: Error - Missing required fields for break');
    }
    if ($redirect_back) {
        custom_log('Step 5: Redirecting back to time_record_editor.php');
        if (!empty($success)) {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&success=' . urlencode($success));
        } else {
            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&error=' . urlencode($error));
        }
        ob_end_flush();
        exit;
    }
} else {
    custom_log('Step 4: Unknown or no action specified');
    $error = 'Unknown action';
    if ($redirect_back && !empty($employee)) {
        header('Location: clock_management.php?show_records=true&employee=' . urlencode($employee) . '&error=' . urlencode($error));
        ob_end_flush();
        exit;
    }
}

// If no redirect happened, render a response page
custom_log('Step 6: Rendering response page (no redirect happened)');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Result - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            background-color: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .back-button:hover {
            background-color: #444;
        }
        .back-button i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Action Result</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="clock_management.php?show_records=true&employee=<?php echo urlencode($employee); ?>" class="back-button"><i class="fas fa-arrow-left"></i> Back to Records</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Action Completed</h2>
            <p>Click the button below to return to the clock management page.</p>
            <a href="clock_management.php?show_records=true&employee=<?php echo urlencode($employee); ?>" class="edit-btn">Return to Clock Management</a>
        </div>
    </div>
    
    <?php
    // Close the database connection at the end of the file, after all operations
    $time_db->close();
    $grok_db->close();
    
    // Flush output buffer at the end
    ob_end_flush();
    ?>
</body>
</html> 