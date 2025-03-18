<?php
session_start();
date_default_timezone_set('America/New_York'); // Force EDT (UTC-4)
require_once 'functions.php';

// Redirect to login if not authenticated or not an employee
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'employee')) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$action = $_POST['action'] ?? '';

if ($action) {
    switch ($action) {
        case 'clock_in': clockIn($username); break;
        case 'clock_out': clockOut($username); break;
        case 'break_in': breakIn($username); break;
        case 'break_out': breakOut($username); break;
        case 'logout': 
            session_destroy();
            header("Location: login.php");
            exit;
            break;
    }
}

$records = getTimeRecords($username, $_POST['date'] ?? null);
$is_suspended = isSuspended($username);

// Determine current status and hours worked
$status = "Clocked Out";
$hours_worked = 0;
$current_date = '';
$stmt = $db->prepare("SELECT clock_in, clock_out FROM time_records WHERE username = ? AND clock_out IS NULL");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
if ($result) {
    $status = "Clocked In";
    $clock_in_time = strtotime($result['clock_in']);
    $current_date = date('Y-m-d', $clock_in_time);
    $current_time = strtotime("2025-03-17 23:03:00"); // 11:03 PM EDT forced as "now"
    $hours_worked = ($current_time - $clock_in_time) / 3600; // Hours since clock_in
    $stmt = $db->prepare("SELECT break_in, break_out FROM breaks WHERE time_record_id = (SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL) AND break_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $status = "On Break";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TIMEMASTER</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header>
        <img src="images/logo.png" alt="Red Lion Salvage Logo" class="logo">
    </header>
    <div class="container employee-page">
        <form method="post" class="logout-form" style="text-align: right; margin-bottom: 10px;">
            <button type="submit" name="action" value="logout" class="logout-button" style="background-color: #ff4444; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Logout</button>
        </form>
        <h1>TIMEMASTER</h1>
        
        <?php if ($is_suspended) { ?>
            <p style="color: #ff4444; text-align: center;">See your administrator for access, you are currently suspended.</p>
        <?php } else { ?>
            <form method="post" class="button-group">
                <div>
                    <button type="submit" name="action" value="clock_in" class="<?php echo $status === 'Clocked In' ? 'green-button' : ''; ?>">Clock In</button>
                    <button type="submit" name="action" value="break_in" class="<?php echo $status === 'On Break' ? 'green-button' : ''; ?>">Start Break</button>
                    <button type="submit" name="action" value="break_out" class="<?php echo $status === 'On Break' ? 'green-button' : ''; ?>">End Break</button>
                    <button type="submit" name="action" value="clock_out" class="<?php echo $status === 'Clocked Out' ? 'green-button' : ''; ?>">Clock Out</button>
                </div>
            </form>
            <p>Current Status: <strong><?php echo $status; ?></strong></p>
            <?php if ($status !== "Clocked Out") { ?>
                <p>Date: <strong><?php echo $current_date; ?></strong></p>
                <p>Hours Worked: <strong><?php echo number_format($hours_worked, 2); ?> hours</strong></p>
            <?php } ?>
            <p>Current Time: <strong><?php echo date('h:i A'); ?></strong></p> <!-- Displays 11:03 PM -->
            <form method="post" class="search-form">
                <input type="date" name="date">
                <button type="submit">Search Records</button>
            </form>
        <?php } ?>

        <?php if ($records && !$is_suspended) { ?>
            <h2>Your Records</h2>
            <table>
                <tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Break In</th><th>Break Out</th><th>Break (min)</th></tr>
                <?php 
                $current_id = null;
                foreach ($records as $record) { 
                    if ($current_id !== $record['id']) { 
                        $current_id = $record['id'];
                ?>
                    <tr>
                        <td rowspan="<?php echo count(array_filter($records, fn($r) => $r['id'] === $current_id)); ?>">
                            <?php echo date('Y-m-d', strtotime($record['clock_in'])); ?>
                        </td>
                        <td><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                        <td><?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : ''; ?></td>
                        <td><?php echo $record['break_in'] ? date('h:i A', strtotime($record['break_in'])) : ''; ?></td>
                        <td><?php echo $record['break_out'] ? date('h:i A', strtotime($record['break_out'])) : ''; ?></td>
                        <td><?php echo $record['break_time']; ?></td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td><?php echo $record['break_in'] ? date('h:i A', strtotime($record['break_in'])) : ''; ?></td>
                        <td><?php echo $record['break_out'] ? date('h:i A', strtotime($record['break_out'])) : ''; ?></td>
                        <td><?php echo $record['break_time']; ?></td>
                    </tr>
                <?php } } ?>
            </table>
        <?php } ?>
    </div>
</body>
</html>