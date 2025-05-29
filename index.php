<?php
// Enable error reporting for debugging - commented out to prevent display
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

// Output initial debug message commented out
// echo '<p>Starting index.php...</p>';

// Use centralized session configuration
try {
    // echo '<p>Loading session configuration...</p>';
    // Temporarily bypass session config for debugging
    // echo '<p>Skipping session configuration load for debugging.</p>';
    // require_once __DIR__ . '/../FLEETMASTER/GROK/grok.redlionsalvage.net/includes/session_config.php';
    // echo '<p>Session configuration loaded successfully.</p>';
} catch (Exception $e) {
    // echo '<p>Error loading session configuration: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die('Test stopped due to session configuration error.');
}

// FORCED TIMEZONE: Always use America/New_York - No other timezone is allowed
date_default_timezone_set('America/New_York');

// echo '<p>Timezone set to America/New_York.</p>';

// Load required files
try {
    // echo '<p>Loading config.php...</p>';
    require_once __DIR__ . '/config.php';
    // echo '<p>config.php loaded successfully.</p>';
    
    // echo '<p>Loading functions.php...</p>';
    require_once __DIR__ . '/functions.php';
    // echo '<p>functions.php loaded successfully.</p>';
} catch (Exception $e) {
    // echo '<p>Error loading required files: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die('Test stopped due to required files error.');
}

// Output session data commented out
// echo '<p>Session data:</p>';
// echo '<pre>' . print_r($_SESSION, true) . '</pre>';

// Check if user is already logged in via Yardmaster
if (!isset($_SESSION['username'])) {
    // No session exists, redirect to auto_auth which will handle login or redirect
    header('Location: auto_auth.php');
    exit;
}

// Verify the session is still valid in GROK database
$username = $_SESSION['username'];
$employee = getEmployee($username);
if (!$employee) {
    // Invalid session, clear it and redirect to auto_auth
    session_destroy();
    header('Location: auto_auth.php');
    exit;
}

// If user is an admin, redirect them to the admin interface
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin/live_time_editor.php');
    exit;
}

// Removed debug output for admin role check and temporary stop

// echo '<p>Continuing with index.php logic...</p>';

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['switch_mode']) && $_POST['switch_mode'] === 'admin') {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['admin_mode'] = true;
                header("Location: admin/index.php");
                exit;
            } else {
                $error = "You do not have admin privileges.";
            }
        }
        
        // Handle action submissions from forms
        if (!empty($action)) {
            switch ($action) {
                case 'clock_in':
                    if (clockIn($username)) {
                        sendNotification($username, "clocked in");
                    }
                    break;
                case 'clock_out':
                    if (clockOut($username)) {
                        sendNotification($username, "clocked out");
                    }
                    break;
                case 'break_in':
                    if (breakIn($username)) {
                        sendNotification($username, "started break");
                    }
                    break;
                case 'break_out':
                    if (breakOut($username)) {
                        sendNotification($username, "ended break");
                    }
                    break;
                case 'logout': 
                    session_destroy();
                    header("Location: login.php");
                    exit;
                    break;
            }
        } 
        elseif (isset($_POST['add_external_time'])) {
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $reason = $_POST['reason'];
            
            if (addExternalTimeRecord($username, $start_time, $end_time, $reason)) {
                $success = "External time record added successfully.";
            } else {
                $error = "Failed to add external time record.";
                error_log("Failed to add external time record for user $username: Start=$start_time, End=$end_time, Reason=$reason");
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again.";
        error_log("Exception in TimeMaster index.php for user $username: " . $e->getMessage());
    }

    // Redirect to prevent form resubmission and ensure status update
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get current time record
$current_record = getCurrentTimeRecord($username);

// Get today's time records
$today = date('Y-m-d');
$records = getTimeRecords($username, $today, $today);

// Get latest status after any form submissions
$is_suspended = isSuspended($username);
$status = getCurrentStatus($username);
$hours_worked = getHoursWorked($username);

// Always set current_date to today's date in Eastern Time
$eastern_today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
$current_date = $eastern_today;

if ($status !== "Clocked Out") {
    $stmt = $time_db->prepare("SELECT clock_in FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        // Get the clock-in date but keep today's date as current_date
        $clock_in_date = getEdtTime($result['clock_in'])->format('Y-m-d');
    }
}

$external_records = getExternalTimeRecords($username);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30"> <!-- Auto-refresh every 30 seconds -->
    <title>TIMEMASTER - Employee Portal</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #2a2a2a;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .status {
            font-size: 1.5em;
            margin-bottom: 30px;
            color: #ffffff;
            background-color: #34495e;
            padding: 15px;
            border-radius: 5px;
        }
        .status span {
            font-weight: bold;
            color: #e74c3c;
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #c0392b;
        }
        .view-records, .dashboard-btn {
            background-color: #3498db;
        }
        .view-records:hover, .dashboard-btn:hover {
            background-color: #2980b9;
        }
        .error {
            background-color: #c0392b;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .greeting {
            font-size: 1.2em;
            margin-bottom: 20px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dashboard-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .dashboard-card h2 {
            margin-top: 0;
            color: #e74c3c;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .dashboard-card p {
            margin-bottom: 20px;
            color: #aaa;
        }
        
        .status-section {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .status-section h2 {
            color: #e74c3c;
            margin-top: 0;
        }
        
        .status-box {
            background-color: #34495e;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .status-box p {
            color: red;
            margin: 5px 0;
        }
        
        .action-section {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .action-section h2 {
            color: #e74c3c;
            margin-top: 0;
        }
        
        .records-section {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .records-section h2 {
            color: #e74c3c;
            margin-top: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            color: #ecf0f1;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #34495e;
        }
        
        th {
            background-color: #34495e;
            color: white;
        }
        
        tr:hover {
            background-color: #34495e;
        }
        
        .mode-switch {
            margin: 20px 0;
            text-align: center;
        }
        
        .mode-switch button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .mode-switch button:hover {
            background-color: #c0392b;
        }
        
        .blue-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .blue-button:hover {
            background-color: #2980b9;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-menu .username {
            color: red;
            margin-right: 15px;
            font-weight: bold;
        }
        
        .user-menu .logout-btn {
            color: #e74c3c;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #e74c3c;
            border-radius: 4px;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .user-menu .logout-btn:hover {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-logo">
            <a href="index.php">TIMEMASTER</a>
        </div>
        <?php if (function_exists('yardmaster_link')) echo yardmaster_link(); ?>
        <nav>
            <ul class="main-nav">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="time_records.php">Time Records</a></li>
                <li><a href="external_time.php">External Time</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="admin/index.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="user-menu">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="logout-btn" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">Logout</a>
        </div>
    </header>
    <main class="container">
        <h1>TIMEMASTER - Employee Portal</h1>
        <?php if ($is_suspended): ?>
            <div class="error">Your account is suspended. Please contact your administrator.</div>
        <?php else: ?>
            <div class="greeting">Hi <?php echo htmlspecialchars($username); ?>, you are currently <span><?php echo $status; ?></span></div>
            <div class="status">Current Status: <span><?php echo $status; ?></span></div>
            <?php if ($status !== "Clocked Out"): ?>
                <div>Hours Worked: <span id="currentDuration">0.00 hours</span></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="button-group">
                <?php if (!$current_record): ?>
                    <button type="submit" name="action" value="clock_in">Clock In</button>
                <?php else: ?>
                    <?php
                    // Check if user is on break
                    $stmt = $time_db->prepare("SELECT br.id FROM break_records br JOIN time_records tr ON br.time_record_id = tr.id WHERE tr.username = ? AND tr.clock_out IS NULL AND br.break_out IS NULL");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $is_on_break = $stmt->get_result()->num_rows > 0;
                    ?>
                    <?php if ($is_on_break): ?>
                        <button type="submit" name="action" value="break_out">End Break</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="break_in">Start Break</button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="clock_out">Clock Out</button>
                <?php endif; ?>
            </form>
            <div class="button-group">
                <form action="time_records.php" method="get" style="display: inline;">
                    <button type="submit" class="view-records">View Saved Records</button>
                </form>
                <form action="https://grok.redlionsalvage.net/employee/index.php" method="get" style="display: inline;">
                    <button type="submit">Return to Dashboard</button>
                </form>
                <button type="button" onclick="document.getElementById('logoutForm').submit();">Logout</button>
            </div>
            <form id="logoutForm" method="post" action="logout.php" style="display: none;">
                <input type="hidden" name="action" value="logout">
            </form>
        <?php endif; ?>
    </main>

    <script>
        // JavaScript variables for Eastern Time clock calculation
        <?php if ($status !== "Clocked Out") { ?>
        const clockInTime = <?php 
            $stmt = $time_db->prepare("SELECT clock_in FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) {
                // Create DateTime object with Eastern Time zone
                $clockInDt = new DateTime($result['clock_in'], new DateTimeZone('America/New_York'));
                
                // Output the date in ISO format and include timezone offset
                echo "new Date('" . $clockInDt->format('Y-m-d\TH:i:s') . "')";
                
                // Store the time zone offset for calculation correction
                echo ";\n        const timeZoneOffset = " . $clockInDt->getOffset() / 3600 . ";";
                echo "\n        // Current time for debugging: " . (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s');
            } else {
                echo "null;\n        const timeZoneOffset = -4;"; // EDT offset
            }
        ?>
        
        // Update hours worked in real-time, ensuring Eastern Time is used
        function updateHoursWorked() {
            if (clockInTime) {
                const now = new Date();
                
                // Calculate the difference in milliseconds and convert to hours
                // Both dates are in local browser time, so no timezone conversion needed
                const durationHours = (now.getTime() - clockInTime.getTime()) / 3600000;
                
                const currentDurationElement = document.getElementById('currentDuration');
                if (currentDurationElement) {
                    currentDurationElement.innerText = durationHours.toFixed(2) + ' hours';
                }
            }
        }
        
        // Update every second
        setInterval(updateHoursWorked, 1000);
        <?php } ?>

        function openExternalTimeModal() {
            document.getElementById('externalTimeModal').style.display = 'block';
        }

        function closeExternalTimeModal() {
            document.getElementById('externalTimeModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('externalTimeModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>