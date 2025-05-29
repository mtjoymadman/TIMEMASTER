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
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_add_break.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('add_break.php - Failed to write to custom log file: ' . $log_file);
        error_log('add_break.php - ' . $message);
    }
}

custom_log('Step 0: add_break.php execution started');

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
$time_record_id = '';
$employee = '';
$db_source = 'time_db'; // Default to time_db

// Process form submission or URL parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    custom_log('Step 3: Form submission detected');
    $time_record_id = isset($_POST['time_record_id']) ? $_POST['time_record_id'] : '';
    $employee = isset($_POST['employee']) ? $_POST['employee'] : '';
    $db_source = isset($_POST['db_source']) ? $_POST['db_source'] : '';
    custom_log('Step 3.1: time_record_id received: ' . $time_record_id);
    custom_log('Step 3.2: employee received: ' . $employee);
    custom_log('Step 3.3: db_source received: ' . $db_source);
    
    if (isset($_POST['action']) && $_POST['action'] === 'add_break') {
        $break_date = isset($_POST['break_date']) ? $_POST['break_date'] : '';
        $break_start = isset($_POST['break_start']) ? $_POST['break_start'] : '';
        $break_end = isset($_POST['break_end']) ? $_POST['break_end'] : '';
        custom_log('Step 3.4: break_date: ' . $break_date . ', break_start: ' . $break_start . ', break_end: ' . $break_end);
        
        if (empty($break_start)) {
            custom_log('Step 3.5: Error - Break start time is required');
            $error = 'Break start time is required';
        } else {
            // Process break addition
            $break_in = $break_date . ' ' . $break_start;
            $break_out = !empty($break_end) ? $break_date . ' ' . $break_end : '';
            custom_log('Step 3.6: Calculated break_in: ' . $break_in . ', break_out: ' . $break_out);
            
            // Use time_db for adding breaks
            $primary_db = $time_db;
            custom_log('Step 4.1: Using time_db for adding breaks');
            
            // Verify the time record exists and belongs to the correct employee
            $verify_stmt = $primary_db->prepare('SELECT id FROM time_records WHERE id = ? AND username = ?');
            if ($verify_stmt === false) {
                $error = 'Database error: Failed to prepare verification statement';
                custom_log('Step 4.2: Error preparing verification statement: ' . $primary_db->error);
            } else {
                $verify_stmt->bind_param('is', $time_record_id, $employee);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    $error = 'Invalid time record or employee mismatch';
                    custom_log('Step 4.3: Error - Time record not found or employee mismatch');
                } else {
                    // Prepare statement to insert break
                    $stmt = $primary_db->prepare('INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)');
                    if ($stmt === false) {
                        $error = 'Database error: Failed to prepare statement for time_db';
                        custom_log('Step 4.4: Error preparing statement for time_db: ' . $primary_db->error);
                    } else {
                        // If break_out is empty, set it to NULL
                        $break_out_val = !empty($break_out) ? $break_out : null;
                        $stmt->bind_param('iss', $time_record_id, $break_in, $break_out_val);
                        if ($stmt->execute()) {
                            $break_id = $primary_db->insert_id;
                            custom_log('Step 4.5: Break inserted successfully into time_db, Break ID: ' . $break_id);
                            $success = 'Break added successfully';
                        } else {
                            $error = 'Failed to add break to time_db';
                            custom_log('Step 4.5: Failed to insert break into time_db: ' . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
                $verify_stmt->close();
            }
        }
    } else {
        custom_log('Step 3.5: Initial form load with parameters');
    }
} else {
    custom_log('Step 3: No form submission, checking for GET parameters');
    // Check if parameters are passed via GET for pre-filling the form
    if (isset($_GET['time_record_id']) && !empty($_GET['time_record_id'])) {
        $time_record_id = $_GET['time_record_id'];
        custom_log('Step 3.1: time_record_id received via GET: ' . $time_record_id);
    }
    if (isset($_GET['employee']) && !empty($_GET['employee'])) {
        $employee = $_GET['employee'];
        custom_log('Step 3.2: employee received via GET: ' . $employee);
    }
    if (isset($_GET['db_source']) && !empty($_GET['db_source'])) {
        $db_source = $_GET['db_source'];
        custom_log('Step 3.3: db_source received via GET: ' . $db_source);
    }
}

if (!empty($error)) {
    custom_log('Step 4: Error detected: ' . $error);
    header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&error=' . urlencode($error));
    exit;
}

if (empty($time_record_id) || empty($employee)) {
    custom_log('Step 5: Missing required parameters');
    header('Location: time_record_editor.php?error=' . urlencode('Missing required parameters for adding a break'));
    exit;
}

// If we reach here, render the form
custom_log('Step 6: Rendering form for adding break');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Break - TIMEMASTER</title>
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
        .form-group input {
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Add Break</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="time_record_editor.php?employee=<?php echo urlencode($employee); ?>" class="back-button"><i class="fas fa-arrow-left"></i> Back to Records</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Add Break for <?php echo htmlspecialchars($employee); ?></h2>
            <form method="POST" action="add_break.php" class="button-group">
                <input type="hidden" name="action" value="add_break">
                <input type="hidden" name="time_record_id" value="<?php echo htmlspecialchars($time_record_id); ?>">
                <input type="hidden" name="employee" value="<?php echo htmlspecialchars($employee); ?>">
                <input type="hidden" name="db_source" value="<?php echo htmlspecialchars($db_source); ?>">
                <div class="form-group">
                    <label for="break_date">Break Date:</label>
                    <input type="date" name="break_date" id="break_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="break_start">Break Start Time:</label>
                    <input type="time" name="break_start" id="break_start" required>
                </div>
                <div class="form-group">
                    <label for="break_end">Break End Time:</label>
                    <input type="time" name="break_end" id="break_end">
                </div>
                <button type="submit" class="edit-btn">Save Break</button>
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