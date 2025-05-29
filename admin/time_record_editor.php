<?php
// Start session and necessary configurations
session_start();

// Start output buffering to prevent any output before headers
ob_start();

// Disable display errors to prevent output before headers
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Set timezone to Eastern Time
date_default_timezone_set('America/New_York');

// Custom logging
function custom_log($message) {
    $log_file = '/home/www/time.redlionsalvage.net/logs/timemaster_debug_time_record_editor.log';
    $timestamp = date('Y-m-d H:i:s');
    if (!file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND)) {
        error_log('time_record_editor.php - Failed to write to custom log file: ' . $log_file);
        error_log('time_record_editor.php - ' . $message);
    }
}

custom_log('Step 0: time_record_editor.php execution started');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    custom_log('Step 1: User not logged in, redirecting to login');
    header('Location: ../login.php');
    ob_end_flush();
    exit;
}
custom_log('Step 1: User logged in, proceeding');

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    custom_log('Step 2: User does not have admin role, redirecting');
    header('Location: ../index.php');
    ob_end_flush();
    exit;
}
custom_log('Step 2: User has admin role, proceeding');

// Get database connections
global $time_db, $grok_db;

// Initialize database connections if not set
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

// Initialize variables
$employees = [];
$selected_employee = '';
$time_records = [];
$error = '';
$success = '';
$show_records = false;

// Get all employees from grok database
$result = $grok_db->query('SELECT username FROM employees ORDER BY username');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row['username'];
    }
    custom_log('Step 3.0: Fetched ' . count($employees) . ' employees from grok_db');
} else {
    custom_log('Step 3.0: Failed to fetch employees from grok_db');
    $error = 'Failed to load employee list';
}

// Check for URL parameters or form submission
if (isset($_GET['employee']) && !empty($_GET['employee'])) {
    $selected_employee = $_GET['employee'];
    $show_records = true;
    custom_log('Step 3: Showing records for employee: ' . $selected_employee);
    
    // Get start and end of current week
    $now = new DateTime('now', new DateTimeZone('America/New_York'));
    $day_of_week = $now->format('N');
    $start_of_week = $now->modify('-' . ($day_of_week - 1) . ' days')->format('Y-m-d');
    $end_of_week = $now->modify('+6 days')->format('Y-m-d');
    custom_log('Step 3.1: Week range calculated - Start: ' . $start_of_week . ', End: ' . $end_of_week);
    
    // Fetch time records from time_db only, ignoring grok_db due to missing table
    $stmt = $time_db->prepare('SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND DATE(clock_in) >= ? AND DATE(clock_in) <= ? ORDER BY clock_in DESC');
    if ($stmt) {
        $stmt->bind_param('sss', $selected_employee, $start_of_week, $end_of_week);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['db_source'] = 'time_db';
            $time_records[$row['id']] = $row;
        }
        custom_log('Step 3.2: Fetched ' . count($time_records) . ' records from time_db');
    } else {
        custom_log('Step 3.2: Failed to prepare statement for time_db');
        $error = 'Database error: Unable to fetch records';
    }
    // Removed grok_db query attempts due to missing table
    custom_log('Step 3.3: Skipping grok_db query due to known missing table issue');
} elseif (isset($_POST['employee']) && !empty($_POST['employee'])) {
    $selected_employee = $_POST['employee'];
    $show_records = true;
    custom_log('Step 3: Showing records for employee from POST: ' . $selected_employee);
    header('Location: time_record_editor.php?employee=' . urlencode($selected_employee));
    ob_end_flush();
    exit;
} else {
    custom_log('Step 3: No employee selected, showing default view');
}

// Handle form submissions for adding/editing records or breaks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    custom_log('Step 4: Form submission detected');
    custom_log('POST data: ' . print_r($_POST, true));
    
    if (isset($_POST['action'])) {
        custom_log('Step 4.1: Processing form submission with action: ' . $_POST['action']);
        switch ($_POST['action']) {
            case 'add_time_record':
                custom_log('Step 4.2: Processing add_time_record action');
                if (isset($_POST['employee'], $_POST['record_date'], $_POST['start_time'])) {
                    $employee = $_POST['employee'];
                    $record_date = $_POST['record_date'];
                    $start_time = $_POST['start_time'];
                    $clock_in = $record_date . ' ' . $start_time;
                    $clock_out = isset($_POST['out_time']) && !empty($_POST['out_time']) ? $record_date . ' ' . $_POST['out_time'] : null;
                    custom_log('Step 4.3: Adding time record for ' . $employee . ' at ' . $clock_in);
                    
                    // Use time_db for adding records
                    $stmt = $time_db->prepare('INSERT INTO time_records (username, clock_in, clock_out) VALUES (?, ?, ?)');
                    if ($stmt) {
                        $stmt->bind_param('sss', $employee, $clock_in, $clock_out);
                        if ($stmt->execute()) {
                            $success = 'Time record added successfully';
                            custom_log('Step 4.4: Time record added to time_db');
                            header('Location: time_record_editor.php?employee=' . urlencode($employee) . '&success=' . urlencode($success));
                            ob_end_flush();
                            exit;
                        } else {
                            $error = 'Failed to add time record: ' . $stmt->error;
                            custom_log('Step 4.4: Failed to add time record to time_db: ' . $stmt->error);
                        }
                    } else {
                        $error = 'Database error: Unable to add record: ' . $time_db->error;
                        custom_log('Step 4.4: Failed to prepare statement for time_db: ' . $time_db->error);
                    }
                } else {
                    $error = 'Missing required fields for time record';
                    custom_log('Step 4.3: Missing required fields for time record');
                }
                break;
            case 'edit_time_record':
                custom_log('Step 4.4: Edit time record action triggered');
                if (isset($_POST['time_record_id'], $_POST['employee'])) {
                    $time_record_id = $_POST['time_record_id'];
                    $employee = $_POST['employee'];
                    custom_log('Step 4.5: Editing time record ID ' . $time_record_id . ' for employee ' . $employee);
                    // Redirect to an edit page or handle edit logic here
                    header('Location: edit_time_record.php?time_record_id=' . urlencode($time_record_id) . '&employee=' . urlencode($employee));
                    ob_end_flush();
                    exit;
                } else {
                    $error = 'Missing required fields for editing time record';
                    custom_log('Step 4.5: Missing required fields for editing time record');
                }
                break;
            case 'add_break_form':
                custom_log('Step 4.6: Add Break form action triggered');
                if (isset($_POST['time_record_id'], $_POST['employee'])) {
                    $time_record_id = $_POST['time_record_id'];
                    $employee = $_POST['employee'];
                    custom_log('Step 4.7: Redirecting to add break page for time record ID ' . $time_record_id . ' for employee ' . $employee);
                    header('Location: add_break.php?time_record_id=' . urlencode($time_record_id) . '&employee=' . urlencode($employee));
                    ob_end_flush();
                    exit;
                } else {
                    $error = 'Missing required fields for adding break';
                    custom_log('Step 4.7: Missing required fields for adding break');
                }
                break;
            case 'add_break':
                if (isset($_POST['time_record_id'], $_POST['break_date'], $_POST['break_start'])) {
                    $time_record_id = $_POST['time_record_id'];
                    $break_date = $_POST['break_date'];
                    $break_start = $_POST['break_start'];
                    $break_in = $break_date . ' ' . $break_start;
                    $break_out = isset($_POST['break_end']) && !empty($_POST['break_end']) ? $break_date . ' ' . $_POST['break_end'] : null;
                    $db_source = isset($_POST['db_source']) ? $_POST['db_source'] : 'time_db';
                    custom_log('Step 4.1: Adding break for time record ' . $time_record_id);
                    $primary_db = ($db_source === 'grok_db') ? $grok_db : $time_db;
                    $stmt = $primary_db->prepare('INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)');
                    if ($stmt) {
                        $stmt->bind_param('iss', $time_record_id, $break_in, $break_out);
                        if ($stmt->execute()) {
                            $success = 'Break added successfully';
                            custom_log('Step 4.2: Break added to primary DB (' . $db_source . ')');
                            $secondary_db = ($db_source === 'grok_db') ? $time_db : $grok_db;
                            $stmt = $secondary_db->prepare('INSERT INTO breaks (time_record_id, break_in, break_out) VALUES (?, ?, ?)');
                            if ($stmt) {
                                $stmt->bind_param('iss', $time_record_id, $break_in, $break_out);
                                $stmt->execute();
                                custom_log('Step 4.3: Break synced to secondary DB');
                            }
                        } else {
                            $error = 'Failed to add break';
                            custom_log('Step 4.2: Failed to add break to primary DB');
                        }
                    } else {
                        $error = 'Database error: Unable to add break';
                        custom_log('Step 4.2: Failed to prepare statement for primary DB');
                    }
                    header('Location: time_record_editor.php?employee=' . urlencode($_POST['employee']) . '&success=' . urlencode($success));
                    ob_end_flush();
                    exit;
                } else {
                    $error = 'Missing required fields for break';
                    custom_log('Step 4.1: Missing required fields for break');
                }
                break;
        }
    }
}

custom_log('Step 5: Rendering HTML output');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Record Editor - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .back-button, .edit-btn { display: inline-block; padding: 10px 15px; background-color: #333; color: white; text-decoration: none; border-radius: 4px; margin: 5px 0; }
        .back-button:hover, .edit-btn:hover { background-color: #444; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: inline-block; width: 120px; }
        .form-group input, .form-group select { padding: 5px; width: 200px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .error-message { color: red; }
        .success-message { color: green; }
    </style>
    <script>
        function logButtonClick(action, recordId) {
            console.log('Button clicked: ' + action + ' for record ID: ' + recordId);
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Time Record Editor</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        
        <?php if (!$show_records): ?>
            <div class="admin-section">
                <h2>Select Employee</h2>
                <form method="POST" action="time_record_editor.php">
                    <div class="form-group">
                        <label for="employee">Employee:</label>
                        <select name="employee" id="employee" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="edit-btn">View Records</button>
                </form>
            </div>
            <div class="admin-section">
                <h2>Add New Time Record</h2>
                <form method="POST" action="admin_clock_actions.php" class="button-group">
                    <input type="hidden" name="action" value="create_time_record">
                    <input type="hidden" name="redirect_back" value="true">
                    <div class="form-group">
                        <label for="new_employee">Employee:</label>
                        <select name="employee" id="new_employee" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="record_date">Date:</label>
                        <input type="date" name="record_date" id="record_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time:</label>
                        <input type="time" name="start_time" id="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="out_time">End Time:</label>
                        <input type="time" name="out_time" id="out_time">
                    </div>
                    <button type="submit" class="edit-btn">Add Record</button>
                </form>
            </div>
        <?php else: ?>
            <a href="time_record_editor.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Employee Selection</a>
            <div class="admin-section">
                <h2>Time Records for <?php echo htmlspecialchars($selected_employee); ?></h2>
                <?php if (!empty($time_records)): ?>
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
                                    
                                    // Fetch breaks for this time record
                                    $break_stmt = $time_db->prepare("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = ?");
                                    if ($break_stmt === false) {
                                        custom_log('Error preparing statement for breaks fetch: ' . $time_db->error);
                                    }
                                    $break_stmt->bind_param("i", $record['id']);
                                    $break_stmt->execute();
                                    $break_result = $break_stmt->get_result();
                                    $breaks = [];
                                    while ($break_row = $break_result->fetch_assoc()) {
                                        $breaks[] = $break_row;
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
                                                        <form action="admin_clock_actions.php" method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_break">
                                                            <input type="hidden" name="break_id" value="<?php echo $break['id']; ?>">
                                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                                            <input type="hidden" name="redirect_back" value="true">
                                                            <button type="submit" class="edit-btn" style="padding: 2px 5px; font-size: 0.8em;">Delete</button>
                                                        </form>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <form action="admin_clock_actions.php" method="POST" style="margin-top: 5px;">
                                            <input type="hidden" name="action" value="add_break">
                                            <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                            <input type="hidden" name="break_date" value="<?php echo $clock_in->format('Y-m-d'); ?>">
                                            <input type="hidden" name="redirect_back" value="true">
                                            <div style="display: inline-block;">
                                                <input type="time" name="break_start" required style="width: 100px;">
                                                <input type="time" name="break_end" style="width: 100px;">
                                                <button type="submit" class="edit-btn" style="padding: 2px 5px; font-size: 0.8em;">Add Break</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <form action="admin_clock_actions.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_time_record">
                                            <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
                                            <input type="hidden" name="redirect_back" value="true">
                                            <button type="submit" class="edit-btn" style="padding: 2px 5px; font-size: 0.8em;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No time records found for this employee in the current week.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    $time_db->close();
    $grok_db->close();
    custom_log('Step 6: Script execution completed');
    ob_end_flush();
    ?>
</body>
</html> 