<?php
// Start session immediately before any output
session_start();

// Disable display errors to prevent output before headers
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone 
// Server timezone is overridden to ensure consistent Eastern Time
date_default_timezone_set('America/New_York');

// Set up custom log file
function custom_log($message) {
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_clock_management.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('clock_management.php - Failed to write to custom log file: ' . $log_file);
        error_log('clock_management.php - ' . $message);
    }
}

custom_log('Step 0: clock_management.php execution started');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    custom_log('Step 1: User not logged in, redirecting to login');
    header('Location: ../login.php');
    exit;
}
custom_log('Step 1: User logged in, proceeding');

// Get database connections from global scope
global $time_db, $grok_db;

// Check if database connections are initialized
if (!isset($time_db) || !isset($grok_db)) {
    error_log("Database connections not initialized, attempting to initialize...");
    
    // Initialize grok database connection
    error_log("Connecting to grok database...");
    $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
    if ($grok_db->connect_error) {
        $error_msg = "Grok database connection failed: " . $grok_db->connect_error;
        error_log($error_msg);
        die("Database connection error: " . $error_msg);
    }
    error_log("Grok database connection successful");
    
    // Initialize time database connection
    error_log("Connecting to time database...");
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        $error_msg = "Time database connection failed: " . $time_db->connect_error;
        error_log($error_msg);
        die("Database connection error: " . $error_msg);
    }
    error_log("Time database connection successful");
    
    // Set timezone for both databases
    error_log("Setting timezone for database connections...");
    $timezone = 'America/New_York';
    $grok_db->query("SET time_zone = '$timezone'");
    $time_db->query("SET time_zone = '$timezone'");
    error_log("Timezone set successfully for both databases");
    
    error_log("Database initialization completed successfully");
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    // Redirect to employee interface
    header('Location: ../index.php');
    exit;
}

// Get logged in user
$logged_in_user = $_SESSION['username'];

// Process form submission
$error = '';
$success = '';
$show_records = false;
$selected_employee = '';
$time_records = [];

// Check for error message in URL parameters (from redirect after failed save)
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = urldecode($_GET['error']);
    custom_log('Step 2: Error message received from URL: ' . $error);
}

// Check for success message in URL parameters (from redirect after successful action)
if (isset($_GET['success']) && !empty($_GET['success'])) {
    $success = urldecode($_GET['success']);
    custom_log('Step 3: Success message received from URL: ' . $success);
}

// Get employees currently on the clock from time database
$on_clock = [];
$result = $time_db->query("SELECT DISTINCT username FROM time_records WHERE clock_out IS NULL");
while ($row = $result->fetch_assoc()) {
    $on_clock[] = $row['username'];
}

// Get all employees from grok database
$employees = [];
$result = $grok_db->query("SELECT username FROM employees ORDER BY username");
while ($row = $result->fetch_assoc()) {
    $employees[] = $row['username'];
}

// Handle admin actions for clocking in/out
if (isset($_POST['admin_action']) && isset($_POST['admin_employee'])) {
    $employee = $_POST['admin_employee'];
    if (!empty($employee)) {
        $action = $_POST['admin_action'];
        switch ($action) {
            case 'clock_in':
                $result = clockIn($employee);
                if ($result) {
                    $success = "Successfully clocked in $employee";
                } else {
                    $error = "Failed to clock in $employee";
                }
                break;
            case 'clock_out':
                $result = clockOut($employee);
                if ($result) {
                    $success = "Successfully clocked out $employee";
                } else {
                    $error = "Failed to clock out $employee";
                }
                break;
            case 'break_in':
                $result = breakIn($employee);
                if ($result) {
                    $success = "Successfully started break for $employee";
                } else {
                    $error = "Failed to start break for $employee";
                }
                break;
            case 'break_out':
                $result = breakOut($employee);
                if ($result) {
                    $success = "Successfully ended break for $employee";
                } else {
                    $error = "Failed to end break for $employee";
                }
                break;
        }
    } else {
        $error = "Please select an employee";
    }
}

// Handle clock out all employees
if (isset($_POST['clock_out_all'])) {
    $count = 0;
    foreach ($on_clock as $emp) {
        if (clockOut($emp)) {
            $count++;
        }
    }
    if ($count > 0) {
        $success = "Successfully clocked out $count employees";
    } else {
        $error = "No employees were clocked out";
    }
}

// Check if redirected back with employee data to show records or from form submission
if (isset($_GET['show_records']) && isset($_GET['employee']) && !empty($_GET['employee'])) {
    $selected_employee = $_GET['employee'];
    $show_records = true;
    custom_log('Step 4: Showing records for employee: ' . $selected_employee);
    
    // Get start of the current week
    custom_log('Step 4.1: Calculating start and end of current week');
    $now = new DateTime('now', new DateTimeZone('America/New_York'));
    $day_of_week = $now->format('N'); // 1 (Monday) to 7 (Sunday)
    $start_of_week = $now->modify('-' . ($day_of_week - 1) . ' days')->format('Y-m-d');
    $end_of_week = $now->modify('+6 days')->format('Y-m-d');
    custom_log('Step 4.2: Week range calculated - Start: ' . $start_of_week . ', End: ' . $end_of_week);
    
    // Fetch time records for the selected employee for the current week
    custom_log('Step 4.3: Preparing to fetch time records from time_db for ' . $selected_employee);
    $stmt = $time_db->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND DATE(clock_in) >= ? AND DATE(clock_in) <= ? ORDER BY clock_in DESC");
    if ($stmt === false) {
        custom_log('Step 4.4: Failed to prepare statement for time_db: ' . $time_db->error);
        $error = 'Database error: Failed to prepare statement';
    } else {
        $stmt->bind_param("sss", $selected_employee, $start_of_week, $end_of_week);
        $stmt->execute();
        $result = $stmt->get_result();
        $time_records = [];
        while ($row = $result->fetch_assoc()) {
            $row['db_source'] = 'time_db';
            $time_records[$row['id']] = $row;
        }
        custom_log('Step 4.4: Fetched ' . count($time_records) . ' records from time_db for ' . $selected_employee);
    }

    // If no records or partial records in time_db, check grok_db
    if (empty($time_records) || count($time_records) < 1) {
        custom_log('Step 4.5: No records found in time_db, checking grok_db for ' . $selected_employee);
        $stmt = $grok_db->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND DATE(clock_in) >= ? AND DATE(clock_in) <= ? ORDER BY clock_in DESC");
        if ($stmt === false) {
            custom_log('Step 4.6: Failed to prepare statement for grok_db: ' . $grok_db->error);
            $error = 'Database error: Failed to prepare statement';
        } else {
            $stmt->bind_param("sss", $selected_employee, $start_of_week, $end_of_week);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['db_source'] = 'grok_db';
                $time_records[$row['id']] = $row;
            }
            custom_log('Step 4.6: Fetched ' . count($time_records) . ' records from grok_db for ' . $selected_employee);
        }
    } else {
        // Also check grok_db for additional records
        custom_log('Step 4.5: Checking grok_db for additional records for ' . $selected_employee);
        $stmt = $grok_db->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND DATE(clock_in) >= ? AND DATE(clock_in) <= ? ORDER BY clock_in DESC");
        if ($stmt === false) {
            custom_log('Step 4.6: Failed to prepare statement for grok_db: ' . $grok_db->error);
            $error = 'Database error: Failed to prepare statement';
        } else {
            $stmt->bind_param("sss", $selected_employee, $start_of_week, $end_of_week);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['db_source'] = 'grok_db';
                if (!isset($time_records[$row['id']])) {
                    $time_records[$row['id']] = $row;
                }
            }
            custom_log('Step 4.6: Total records after checking grok_db: ' . count($time_records) . ' for ' . $selected_employee);
        }
    }

    // Sort records by clock_in descending
    custom_log('Step 4.7: Sorting time records by clock_in descending');
    usort($time_records, function($a, $b) {
        return strtotime($b['clock_in']) - strtotime($a['clock_in']);
    });
    custom_log('Step 4.8: Records sorted successfully');

    if (empty($error)) {
        $success = "Time record saved successfully for $selected_employee";
        custom_log('Step 4.9: Success message set for ' . $selected_employee);
    }
} else {
    custom_log('Step 4: Not showing records, default view');
}

// Create a backup of this file in the log directory
function createBackup() {
    $backup_file = '/home/www/time.redlionsalvage.net/logs/timemaster_backup_clock_management.php';
    $backup_content = "<?php\n// Backup of clock_management.php created on " . date('Y-m-d H:i:s') . "\n\n// Original content follows:\n\n" . file_get_contents(__FILE__) . "\n?>";
    if (!file_put_contents($backup_file, $backup_content)) {
        error_log('clock_management.php - Failed to write backup file: ' . $backup_file);
    } else {
        custom_log('Backup created successfully at: ' . $backup_file);
    }
}

// Call to create backup on script execution
createBackup();

custom_log('Step 5: About to render HTML output, ensuring no prior output');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clock Management - TIMEMASTER</title>
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
            <h1>TIMEMASTER - Clock Management</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Clock Management</h2>
            <form method="post" class="button-group">
                <select name="admin_employee">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp); ?>">
                            <?php echo htmlspecialchars($emp); ?>
                        </option>
                    <?php } ?>
                </select>
                <button type="submit" name="admin_action" value="clock_in" class="edit-btn">Clock In</button>
                <button type="submit" name="admin_action" value="clock_out" class="edit-btn">Clock Out</button>
                <button type="submit" name="admin_action" value="break_in" class="edit-btn">Start Break</button>
                <button type="submit" name="admin_action" value="break_out" class="edit-btn">End Break</button>
            </form>
            <?php if (hasRole($logged_in_user, 'admin')) { ?>
                <form method="post" class="button-group">
                    <button type="submit" name="clock_out_all" class="edit-btn">Clock Out All Employees</button>
                </form>
            <?php } ?>
        </div>
        
        <div class="admin-section">
            <h2>Employees Currently Clocked In</h2>
            <table>
                <tr>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Duration</th>
                </tr>
                <?php foreach ($on_clock as $emp) { 
                    // Get current time record
                    $stmt = $time_db->prepare("SELECT id, clock_in FROM time_records WHERE username = ? AND clock_out IS NULL");
                    $stmt->bind_param("s", $emp);
                    $stmt->execute();
                    $time_result = $stmt->get_result()->fetch_assoc();
                    
                    if ($time_result) {
                        // Create clock_in DateTime object with Eastern Time zone
                        $clock_in_dt = new DateTime($time_result['clock_in'], new DateTimeZone('America/New_York'));
                        $now_dt = new DateTime('now', new DateTimeZone('America/New_York'));
                        
                        // Check if the employee is currently on break
                        $stmt = $time_db->prepare("SELECT break_in FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
                        $stmt->bind_param("i", $time_result['id']);
                        $stmt->execute();
                        $break_result = $stmt->get_result()->fetch_assoc();
                        
                        if ($break_result) {
                            // Employee is on break
                            $status_class = 'status-break';
                            $status_text = 'On Break';
                            // Create break_in DateTime object with Eastern Time zone
                            $break_in_dt = new DateTime($break_result['break_in'], new DateTimeZone('America/New_York'));
                            $break_duration = $now_dt->getTimestamp() - $break_in_dt->getTimestamp();
                            $minutes = floor($break_duration / 60);
                            $duration = $minutes . ' min';
                        } else {
                            // Employee is clocked in
                            $status_class = 'status-active';
                            $status_text = 'Clocked In';
                            $shift_duration = $now_dt->getTimestamp() - $clock_in_dt->getTimestamp();
                            $hours = floor($shift_duration / 3600);
                            $minutes = floor(($shift_duration % 3600) / 60);
                            $duration = $hours . 'h ' . $minutes . 'm';
                        }
                    } else {
                        // Employee is not clocked in
                        $status_class = 'status-inactive';
                        $status_text = 'Not Clocked In';
                        $duration = '';
                    }
                ?>
                    <tr>
                        <td>
                            <span class="employee-link <?php echo $status_class; ?>">
                                <strong><?php echo htmlspecialchars($emp); ?></strong>
                            </span>
                        </td>
                        <td><?php echo $status_text; ?></td>
                        <td><?php echo $duration; ?></td>
                    </tr>
                <?php } ?>
                <?php if (count($on_clock) === 0) { ?>
                    <tr><td colspan="3" style="text-align: center;">No employees currently clocked in</td></tr>
                <?php } ?>
            </table>
        </div>
        
        <?php if ($show_records): ?>
            <a href="clock_management.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Add Record</a>
            <h2>Time Records for <?php echo htmlspecialchars($selected_employee); ?> (Current Week)</h2>
            <!-- Debug: Confirming show_records view -->
            <p style="color: blue; font-weight: bold;">Debug: You are in the detailed records view for <?php echo htmlspecialchars($selected_employee); ?>. If you do not see action buttons, there might be an issue.</p>
            <?php if (!empty($time_records)): ?>
                <p style="color: green; font-weight: bold;">Debug: Time records found. 'Add Break' buttons should be visible below under 'Actions' for each record.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Total Hours</th>
                            <th>Breaks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_records as $record): ?>
                            <?php
                                $clock_in = new DateTime($record['clock_in'], new DateTimeZone('America/New_York'));
                                $clock_out = $record['clock_out'] ? new DateTime($record['clock_out'], new DateTimeZone('America/New_York')) : null;
                                $total_hours = $clock_out ? ($clock_out->getTimestamp() - $clock_in->getTimestamp()) / 3600 : 'N/A';
                                if ($total_hours !== 'N/A') {
                                    $total_hours = number_format($total_hours, 2) . ' hours';
                                }
                                // Log db_source for this record
                                custom_log('Processing time record ID ' . $record['id'] . ' with db_source: ' . $record['db_source']);
                                // Fetch breaks for this time record from the correct database
                                $db_to_use = ($record['db_source'] === 'grok_db') ? $grok_db : $time_db;
                                custom_log('Fetching breaks for time record ID ' . $record['id'] . ' from database: ' . ($record['db_source'] === 'grok_db' ? 'grok_db' : 'time_db'));
                                $break_stmt = $db_to_use->prepare("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = ?");
                                if ($break_stmt === false) {
                                    custom_log('Error preparing statement for breaks fetch for time record ID ' . $record['id'] . ': ' . $db_to_use->error);
                                }
                                $break_stmt->bind_param("i", $record['id']);
                                $break_stmt->execute();
                                $break_result = $break_stmt->get_result();
                                $breaks = [];
                                while ($break_row = $break_result->fetch_assoc()) {
                                    $breaks[] = $break_row;
                                }
                                custom_log('Break records fetched for time record ID ' . $record['id'] . ': ' . count($breaks) . ' breaks found from primary db_source');
                                // If no breaks found, try the other database as a fallback for all records
                                if (count($breaks) == 0) {
                                    custom_log('No breaks found in primary database for time record ID ' . $record['id'] . ', trying the other database as fallback');
                                    $fallback_db = ($record['db_source'] === 'grok_db') ? $time_db : $grok_db;
                                    $fallback_break_stmt = $fallback_db->prepare("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = ?");
                                    if ($fallback_break_stmt === false) {
                                        custom_log('Error preparing fallback statement for breaks fetch for time record ID ' . $record['id'] . ': ' . $fallback_db->error);
                                    }
                                    $fallback_break_stmt->bind_param("i", $record['id']);
                                    $fallback_break_stmt->execute();
                                    $fallback_break_result = $fallback_break_stmt->get_result();
                                    while ($fallback_break_row = $fallback_break_result->fetch_assoc()) {
                                        $breaks[] = $fallback_break_row;
                                    }
                                    custom_log('Break records fetched for time record ID ' . $record['id'] . ' from fallback database (' . ($record['db_source'] === 'grok_db' ? 'time_db' : 'grok_db') . '): ' . count($breaks) . ' breaks found');
                                }
                                // Log details of breaks found after checking both databases
                                custom_log('Final check for time record ID ' . $record['id'] . ': ' . count($breaks) . ' breaks found after checking both databases');
                                if (count($breaks) > 0) {
                                    foreach ($breaks as $break) {
                                        custom_log('Break ID ' . $break['id'] . ' for time record ID ' . $record['id'] . ' - break_in: ' . $break['break_in'] . ', break_out: ' . ($break['break_out'] ? $break['break_out'] : 'not set'));
                                    }
                                } else {
                                    custom_log('No breaks fetched for time record ID ' . $record['id'] . ' even after checking both databases - possible data issue');
                                }
                                // Additional debug for table name verification in both databases
                                custom_log('Verifying table name for breaks in both databases for time record ID ' . $record['id']);
                                $time_db_tables = $time_db->query("SHOW TABLES LIKE 'breaks'");
                                $grok_db_tables = $grok_db->query("SHOW TABLES LIKE 'breaks'");
                                custom_log('Table "breaks" exists in time_db: ' . ($time_db_tables->num_rows > 0 ? 'Yes' : 'No'));
                                custom_log('Table "breaks" exists in grok_db: ' . ($grok_db_tables->num_rows > 0 ? 'Yes' : 'No'));
                                // Check for any breaks in time_db explicitly
                                $explicit_time_check = $time_db->query("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = " . $record['id']);
                                if ($explicit_time_check) {
                                    $time_breaks_count = $explicit_time_check->num_rows;
                                    custom_log('Explicit check in time_db for breaks with time_record_id=' . $record['id'] . ': ' . $time_breaks_count . ' breaks found');
                                    while ($tb_row = $explicit_time_check->fetch_assoc()) {
                                        custom_log('Explicit time_db break ID ' . $tb_row['id'] . ' - break_in: ' . $tb_row['break_in'] . ', break_out: ' . ($tb_row['break_out'] ? $tb_row['break_out'] : 'not set'));
                                    }
                                } else {
                                    custom_log('Error in explicit time_db check for time_record_id=' . $record['id'] . ': ' . $time_db->error);
                                }
                                // Check for any breaks in grok_db explicitly
                                $explicit_grok_check = $grok_db->query("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = " . $record['id']);
                                if ($explicit_grok_check) {
                                    $grok_breaks_count = $explicit_grok_check->num_rows;
                                    custom_log('Explicit check in grok_db for breaks with time_record_id=' . $record['id'] . ': ' . $grok_breaks_count . ' breaks found');
                                    while ($gb_row = $explicit_grok_check->fetch_assoc()) {
                                        custom_log('Explicit grok_db break ID ' . $gb_row['id'] . ' - break_in: ' . $gb_row['break_in'] . ', break_out: ' . ($gb_row['break_out'] ? $gb_row['break_out'] : 'not set'));
                                    }
                                } else {
                                    custom_log('Error in explicit grok_db check for time_record_id=' . $record['id'] . ': ' . $grok_db->error);
                                }
                            ?>
                            <tr>
                                <td><?php echo $clock_in->format('Y-m-d'); ?></td>
                                <td><?php echo $clock_in->format('h:i A'); ?></td>
                                <td><?php echo $clock_out ? $clock_out->format('h:i A') : 'N/A'; ?></td>
                                <td><?php echo $total_hours; ?></td>
                                <td>
                                    <?php if (!empty($breaks)): ?>
                                        <ul style="margin: 0; padding-left: 20px;">
                                            <?php foreach ($breaks as $break): ?>
                                                <?php
                                                    $break_in = new DateTime($break['break_in'], new DateTimeZone('America/New_York'));
                                                    $break_out = $break['break_out'] ? new DateTime($break['break_out'], new DateTimeZone('America/New_York')) : null;
                                                ?>
                                                <li>
                                                    <?php echo $break_in->format('h:i A'); ?> - 
                                                    <?php echo $break_out ? $break_out->format('h:i A') : 'N/A'; ?>
                                                    <?php custom_log('Displaying break ID ' . $break['id'] . ' for time record ID ' . $record['id']); ?>
                                                    <span style="color: green; font-size: 10px;">Debug: Break ID <?php echo $break['id']; ?> displayed</span>
                                                    <form action="admin_clock_actions.php" method="POST" style="display: inline;" onsubmit="logFormData(this);">
                                                        <input type="hidden" name="action" value="delete_break">
                                                        <input type="hidden" name="break_id" value="<?php echo $break['id']; ?>">
                                                        <input type="hidden" name="redirect_back" value="true">
                                                        <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                                        <input type="hidden" name="db_source" value="<?php echo $record['db_source']; ?>">
                                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this break?');" style="color: red; border: none; background: none; cursor: pointer;">Remove</button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <?php custom_log('No breaks found to display for time record ID ' . $record['id'] . ', despite database checks'); ?>
                                        <span style="color: red; font-size: 10px;">Debug: No breaks found for ID <?php echo $record['id']; ?></span>
                                        No breaks
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <form action="edit_time_record.php" method="GET" style="display: inline;">
                                            <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                            <button type="submit" class="edit-btn" style="background-color: #4CAF50; color: white;">Edit Times</button>
                                        </form>
                                        <!-- Debug: Confirming Add Break button rendering -->
                                        <p style="color: purple; font-size: 10px; margin: 0;">Debug: Add Break button should be below</p>
                                        <form action="add_break.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="add_break">
                                            <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                            <input type="hidden" name="db_source" value="<?php echo $record['db_source']; ?>">
                                            <button type="submit" class="edit-btn" style="background-color: #2196F3; color: white;">Add Break</button>
                                        </form>
                                        <form action="admin_clock_actions.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_time_record">
                                            <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="redirect_back" value="true">
                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                            <input type="hidden" name="db_source" value="<?php echo $record['db_source']; ?>">
                                            <button type="submit" onclick="return confirm('Are you sure you want to delete this time record?');" class="edit-btn" style="background-color: #ff4d4d; color: white;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No time records found for <?php echo htmlspecialchars($selected_employee); ?> in the current week.</p>
                <p style="color: red; font-weight: bold;">Debug: No time records found. 'Add Break' button cannot be displayed without records. Please ensure there are time records or create one if needed.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-section">
                <h2>Create/Edit Time Record</h2>
                <form id="timeRecordForm" method="post" action="admin_clock_actions.php" class="button-group" onsubmit="return validateTimeRecordForm()" enctype="application/x-www-form-urlencoded">
                    <input type="hidden" name="action" value="create_time_record">
                    <input type="hidden" name="redirect_back" value="true">
                    <input type="hidden" name="session_id" value="<?php echo session_id(); ?>">
                    <div style="margin-bottom: 15px;">
                        <label for="employee" style="display: inline-block; width: 120px;">Employee:</label>
                        <select name="employee" id="employee" required style="padding: 5px;">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="record_date" style="display: inline-block; width: 120px;">Date:</label>
                        <input type="date" name="record_date" id="record_date" required style="padding: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="start_time" style="display: inline-block; width: 120px;">Start Time:</label>
                        <input type="time" name="start_time" id="start_time" required style="padding: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="break_start" style="display: inline-block; width: 120px;">Break Start:</label>
                        <input type="time" name="break_start" id="break_start" style="padding: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="break_end" style="display: inline-block; width: 120px;">Break End:</label>
                        <input type="time" name="break_end" id="break_end" style="padding: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="out_time" style="display: inline-block; width: 120px;">Out Time:</label>
                        <input type="time" name="out_time" id="out_time" style="padding: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="overwrite" style="display: inline-block; width: 120px;">Overwrite Conflicts:</label>
                        <input type="checkbox" name="overwrite" id="overwrite" value="1" style="vertical-align: middle;">
                    </div>
                    <button type="submit" class="edit-btn">Save Time Record</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function validateTimeRecordForm() {
            var employee = document.getElementById('employee').value;
            var recordDate = document.getElementById('record_date').value;
            var startTime = document.getElementById('start_time').value;
            if (!employee || !recordDate || !startTime) {
                alert('Please fill in all required fields: Employee, Date, and Start Time.');
                return false;
            }
            return true;
        }
        
        // Debug script to log presence of buttons in the DOM
        window.onload = function() {
            console.log('Page loaded, checking for buttons...');
            var editButtons = document.querySelectorAll('button.edit-btn[style*="background-color: #4CAF50"]');
            var deleteButtons = document.querySelectorAll('button.edit-btn[style*="background-color: #ff4d4d"]');
            var addBreakButtons = document.querySelectorAll('button.edit-btn[style*="background-color: #2196F3"]');
            console.log('Edit Times buttons found: ', editButtons.length);
            console.log('Delete buttons found: ', deleteButtons.length);
            console.log('Add Break buttons found: ', addBreakButtons.length);
            if (editButtons.length > 0 || deleteButtons.length > 0 || addBreakButtons.length > 0) {
                console.log('Buttons are in the DOM. If not visible, check if you are viewing records for a selected employee.');
            } else {
                console.log('No buttons found. You may need to select an employee to view their records.');
            }
        };
        
        // Function to log form data on submission for debugging
        function logFormData(form) {
            console.log('Form submission data:');
            var formData = new FormData(form);
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            return true; // Allow form submission to proceed
        }
    </script>
    
    <?php
    // Close the database connection at the end of the file, after all operations
    $time_db->close();
    $grok_db->close();
    ?>
</body>
</html> 