<?php
/**
 * Break Records Table Consistency Fix
 * 
 * This script will check the database schema and fix issues with break_records table column names.
 * It standardizes column usage to match what's actually in the database.
 */

session_start();

// Start output buffering to prevent any output before headers
ob_start();

// Disable display errors to prevent output before headers
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

date_default_timezone_set('America/New_York');

function custom_log($message) {
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_edit_time_record.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('edit_time_record.php - Failed to write to custom log file: ' . $log_file);
        error_log('edit_time_record.php - ' . $message);
    }
}

custom_log('Step 0: edit_time_record.php execution started');

if (!isset($_SESSION['username'])) {
    custom_log('Step 1: User not logged in, redirecting to login');
    header('Location: ../login.php');
    ob_end_flush();
    exit;
}
custom_log('Step 1: User logged in, proceeding');

if (!hasRole($_SESSION['username'], 'admin')) {
    custom_log('Step 2: User does not have admin role, redirecting');
    header('Location: ../index.php');
    ob_end_flush();
    exit;
}
custom_log('Step 2: User has admin role, proceeding');

global $time_db, $grok_db;

if (!isset($time_db) || !isset($grok_db)) {
    error_log('Database connections not initialized, attempting to initialize...');
    $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
    if ($grok_db->connect_error) {
        $error_msg = 'Grok database connection failed: ' . $grok_db->connect_error;
        error_log($error_msg);
        die('Database connection error: ' . $error_msg);
    }
    error_log('Grok database connection successful');
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        $error_msg = 'Time database connection failed: ' . $time_db->connect_error;
        error_log($error_msg);
        die('Database connection error: ' . $error_msg);
    }
    error_log('Time database connection successful');
    $timezone = 'America/New_York';
    $grok_db->query("SET time_zone = '$timezone'");
    $time_db->query("SET time_zone = '$timezone'");
    error_log('Timezone set successfully for both databases');
}

$time_record_id = isset($_GET['time_record_id']) ? $_GET['time_record_id'] : null;
$employee = isset($_GET['employee']) ? $_GET['employee'] : null;
$error = '';
$success = '';
$record = null;

if (!$time_record_id || !$employee) {
    $error = 'Missing required parameters';
    custom_log('Step 3: Missing required parameters');
} else {
    custom_log('Step 3: Fetching time record ID ' . $time_record_id);
    $primary_db = $time_db;
    custom_log('Step 4: Using time_db only for fetching time record details');
    $stmt = $primary_db->prepare('SELECT username, clock_in, clock_out FROM time_records WHERE id = ?');
    if ($stmt === false) {
        $error = 'Database error: Failed to prepare statement for time_db';
        custom_log('Step 4.1: Error preparing statement for time_db: ' . $primary_db->error);
    } else {
        $stmt->bind_param('i', $time_record_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $record = $result->fetch_assoc();
                $employee = $record['username'];
                $clock_in = new DateTime($record['clock_in'], new DateTimeZone('America/New_York'));
                $clock_out = $record['clock_out'] ? new DateTime($record['clock_out'], new DateTimeZone('America/New_York')) : null;
                custom_log('Step 4.2: Time record fetched from time_db for ID ' . $time_record_id);
                custom_log('Step 4.3: Record details - username: ' . $employee . ', clock_in: ' . $clock_in->format('Y-m-d H:i') . ', clock_out: ' . ($clock_out ? $clock_out->format('Y-m-d H:i') : 'NULL'));
            } else {
                $error = 'Time record not found in time_db';
                custom_log('Step 4.2: Time record not found in time_db for ID ' . $time_record_id);
            }
        } else {
            $error = 'Failed to fetch time record from time_db';
            custom_log('Step 4.2: Failed to fetch time record from time_db: ' . $stmt->error);
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_time_record') {
    custom_log('Step 5: Processing form submission for editing time record');
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $out_time = isset($_POST['out_time']) && !empty($_POST['out_time']) ? $_POST['out_time'] : '';
    custom_log('Step 5.1: record_date: ' . $record_date . ', start_time: ' . $start_time . ', out_time: ' . $out_time);
    
    if (empty($start_time)) {
        custom_log('Step 5.2: Error - Start time is required');
        $error = 'Start time is required';
    } else {
        $clock_in = $record_date . ' ' . $start_time;
        $clock_out = !empty($out_time) ? $record_date . ' ' . $out_time : null;
        custom_log('Step 5.3: Calculated clock_in: ' . $clock_in . ', clock_out: ' . ($clock_out ? $clock_out : 'NULL'));
        $primary_db = $time_db;
        custom_log('Step 5.4: Updating time record in time_db only');
        $stmt = $primary_db->prepare('UPDATE time_records SET clock_in = ?, clock_out = ? WHERE id = ?');
        if ($stmt === false) {
            $error = 'Database error: Failed to prepare update statement for time_db';
            custom_log('Step 5.5: Error preparing update statement for time_db: ' . $primary_db->error);
        } else {
            $stmt->bind_param('ssi', $clock_in, $clock_out, $time_record_id);
            if ($stmt->execute()) {
                custom_log('Step 5.5: Time record updated successfully in time_db');
                $success = 'Time record updated successfully';
            } else {
                $error = 'Failed to update time record in time_db';
                custom_log('Step 5.5: Failed to update time record in time_db: ' . $stmt->error);
            }
            $stmt->close();
        }
    }
}

custom_log('Step 6: Rendering HTML output');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Time Record - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .back-button, .edit-btn { display: inline-block; padding: 10px 15px; background-color: #333; color: white; text-decoration: none; border-radius: 4px; margin: 5px 0; }
        .back-button:hover, .edit-btn:hover { background-color: #444; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: inline-block; width: 120px; }
        .form-group input { padding: 5px; width: 200px; }
        .error-message { color: red; }
        .success-message { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Edit Time Record</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="time_record_editor.php?employee=<?php echo urlencode($employee); ?>" class="back-button"><i class="fas fa-arrow-left"></i> Back to Records</a>
        
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        
        <?php if ($record): ?>
            <div class="admin-section">
                <h2>Edit Time Record for <?php echo htmlspecialchars($record['username']); ?></h2>
                <p style="color: blue; font-weight: bold;">Debug: Displaying record for ID <?php echo $time_record_id; ?>. If fields are empty, check logs for data issues.</p>
                <form method="POST" action="edit_time_record.php">
                    <input type="hidden" name="action" value="edit_time_record">
                    <input type="hidden" name="time_record_id" value="<?php echo $time_record_id; ?>">
                    <input type="hidden" name="employee" value="<?php echo htmlspecialchars($employee); ?>">
                    <div class="form-group">
                        <label for="record_date">Date:</label>
                        <input type="date" name="record_date" id="record_date" required value="<?php echo $clock_in->format('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time:</label>
                        <input type="time" name="start_time" id="start_time" required value="<?php echo $clock_in->format('H:i'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="out_time">End Time:</label>
                        <input type="time" name="out_time" id="out_time" value="<?php echo $clock_out ? $clock_out->format('H:i') : ''; ?>">
                    </div>
                    <button type="submit" class="edit-btn">Update Record</button>
                </form>
            </div>
        <?php else: ?>
            <p>Record not found or unable to load.</p>
        <?php endif; ?>
    </div>
    
    <?php
    $time_db->close();
    $grok_db->close();
    custom_log('Step 7: Script execution completed');
    ob_end_flush();
    ?>
</body>
</html> 