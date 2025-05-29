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
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_add_time_record.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('add_time_record.php - Failed to write to custom log file: ' . $log_file);
        error_log('add_time_record.php - ' . $message);
    }
}

custom_log('Step 0: add_time_record.php execution started');

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

// Initialize variables
$error = '';
$success = '';
$employee = '';
$record_date = '';
$start_time = '';
$out_time = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    custom_log('Step 3: Form submission detected');
    $employee = isset($_POST['employee']) ? $_POST['employee'] : '';
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $out_time = isset($_POST['out_time']) ? $_POST['out_time'] : '';
    
    custom_log('Step 3.1: Processing time record addition');
    custom_log('Employee: ' . $employee);
    custom_log('Date: ' . $record_date);
    custom_log('Start Time: ' . $start_time);
    custom_log('End Time: ' . $out_time);
    
    if (empty($employee) || empty($record_date) || empty($start_time)) {
        $error = 'Missing required fields';
        custom_log('Step 3.2: Error - Missing required fields');
    } else {
        // Process time record addition
        $clock_in = $record_date . ' ' . $start_time;
        $clock_out = !empty($out_time) ? $record_date . ' ' . $out_time : null;
        custom_log('Step 3.3: Calculated clock_in: ' . $clock_in . ', clock_out: ' . $clock_out);
        
        // Use time_db for adding records
        $stmt = $time_db->prepare('INSERT INTO time_records (username, clock_in, clock_out) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('sss', $employee, $clock_in, $clock_out);
            if ($stmt->execute()) {
                $success = 'Time record added successfully';
                custom_log('Step 3.4: Time record added successfully');
                header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&success=' . urlencode($success));
                ob_end_flush();
                exit;
            } else {
                $error = 'Failed to add time record: ' . $stmt->error;
                custom_log('Step 3.4: Failed to add time record: ' . $stmt->error);
            }
        } else {
            $error = 'Database error: Unable to add record: ' . $time_db->error;
            custom_log('Step 3.4: Database error: ' . $time_db->error);
        }
    }
} else {
    // Get parameters from GET request
    $employee = isset($_GET['employee']) ? $_GET['employee'] : '';
    $record_date = isset($_GET['record_date']) ? $_GET['record_date'] : date('Y-m-d');
    $start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '';
    $out_time = isset($_GET['out_time']) ? $_GET['out_time'] : '';
    custom_log('Step 3: Initial form load with parameters');
    custom_log('Employee: ' . $employee);
    custom_log('Date: ' . $record_date);
    custom_log('Start Time: ' . $start_time);
    custom_log('End Time: ' . $out_time);
}

// If we reach here, render the form
custom_log('Step 4: Rendering form for adding time record');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Time Record - TIMEMASTER</title>
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: inline-block;
            width: 120px;
        }
        .form-group input, .form-group select {
            padding: 5px;
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Add Time Record</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="time_record_editor.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Records</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Add Time Record</h2>
            <form method="POST" action="add_time_record.php" class="button-group">
                <div class="form-group">
                    <label for="employee">Employee:</label>
                    <select name="employee" id="employee" required>
                        <option value="">Select Employee</option>
                        <?php
                        $result = $grok_db->query('SELECT username FROM employees ORDER BY username');
                        while ($row = $result->fetch_assoc()) {
                            $selected = ($row['username'] === $employee) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($row['username']) . '" ' . $selected . '>' . htmlspecialchars($row['username']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="record_date">Date:</label>
                    <input type="date" name="record_date" id="record_date" required value="<?php echo htmlspecialchars($record_date); ?>">
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time:</label>
                    <input type="time" name="start_time" id="start_time" required value="<?php echo htmlspecialchars($start_time); ?>">
                </div>
                <div class="form-group">
                    <label for="out_time">End Time:</label>
                    <input type="time" name="out_time" id="out_time" value="<?php echo htmlspecialchars($out_time); ?>">
                </div>
                <button type="submit" class="edit-btn">Save Time Record</button>
            </form>
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