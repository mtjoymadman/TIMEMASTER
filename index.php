<?php
session_start();
require_once 'functions.php';

// Redirect to login if not authenticated or not an employee/admin
if (!isset($_SESSION['username']) || (!hasRole($_SESSION['username'], 'employee') && !hasRole($_SESSION['username'], 'admin'))) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['switch_mode']) && $_POST['switch_mode'] === 'admin') {
            header("Location: admin/index.php");
            exit;
        }
        switch ($action) {
            case 'clock_in': 
                if (!clockIn($username)) {
                    $error = "You are already clocked in.";
                }
                break;
            case 'clock_out': 
                if (!clockOut($username)) {
                    $error = "You must end your break before clocking out.";
                }
                break;
            case 'break_in': 
                if (!breakIn($username)) {
                    $error = "You must be clocked in to start a break.";
                }
                break;
            case 'break_out': 
                if (!breakOut($username)) {
                    $error = "You are not currently on break.";
                }
                break;
            case 'logout': 
                session_destroy();
                header("Location: login.php");
                exit;
                break;
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again.";
        error_log($e->getMessage());
    }
}

$records = getTimeRecords($username, $_POST['date'] ?? null);
$is_suspended = isSuspended($username);
$status = getCurrentStatus($username);
$hours_worked = getHoursWorked($username);
$current_date = '';

if ($status !== "Clocked Out") {
    $stmt = $db->prepare("SELECT clock_in FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $current_date = getEdtTime($result['clock_in'])->format('Y-m-d');
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
            <button type="submit" name="action" value="logout" class="logout-button">Logout</button>
        </form>
        <h1>TIMEMASTER</h1>
        
        <?php if ($is_suspended) { ?>
            <p style="color: #ff4444; text-align: center;">See your administrator for access, you are currently suspended.</p>
        <?php } else { ?>
            <?php if (isset($error)) { ?>
                <p style="color: #ff4444; text-align: center;"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            
            <form method="post" class="button-group" style="display: flex; justify-content: center;">
                <div>
                    <button type="submit" name="action" value="clock_in" class="<?php echo $status === 'Clocked In' ? 'green-button' : ''; ?>">Clock In</button>
                    <button type="submit" name="action" value="break_in" class="<?php echo $status === 'On Break' ? 'green-button' : ''; ?>">Start Break</button>
                    <button type="submit" name="action" value="break_out" class="<?php echo $status === 'On Break' ? 'green-button' : ''; ?>">End Break</button>
                    <button type="submit" name="action" value="clock_out" class="<?php echo $status === 'Clocked Out' ? 'green-button' : ''; ?>">Clock Out</button>
                </div>
            </form>
            
            <p style="text-align: center; font-size: 1.2em; margin: 20px 0;">Hi, <strong><?php echo htmlspecialchars($username); ?></strong>!</p>
            <p>Current Status: <strong><?php echo $status; ?></strong></p>
            <?php if ($status !== "Clocked Out") { ?>
                <p>Date: <strong><?php echo formatDate($current_date); ?></strong></p>
                <p>Hours Worked: <strong><?php echo number_format($hours_worked, 2); ?> hours</strong></p>
            <?php } ?>
            <p>Current Time: <strong><?php echo getEdtTime()->format('h:i A'); ?></strong></p>
            
            <form method="post" class="search-form">
                <input type="date" name="date">
                <button type="submit">Search Records</button>
            </form>
        <?php } ?>

        <?php if ($records && !$is_suspended) { ?>
            <h2>Your Records</h2>
            <table>
                <tr>
                    <th>Day</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Total Break (min)</th>
                </tr>
                <?php 
                $current_id = null;
                foreach ($records as $record) { 
                    if ($current_id !== $record['id']) { 
                        $current_id = $record['id'];
                        // Calculate total break minutes for this record
                        $total_break_minutes = array_sum(array_map(function($r) {
                            return $r['break_time'] ?? 0;
                        }, array_filter($records, fn($r) => $r['id'] === $current_id)));
                ?>
                    <tr>
                        <td><?php echo formatDate($record['clock_in']); ?></td>
                        <td><?php echo formatTime($record['clock_in']); ?></td>
                        <td><?php echo formatTime($record['clock_out']); ?></td>
                        <td><?php echo $total_break_minutes; ?></td>
                    </tr>
                <?php } } ?>
            </table>
        <?php } ?>

        <?php if (hasRole($username, 'admin')) { ?>
            <div class="admin-section">
                <form method="post" class="button-group">
                    <button type="submit" name="switch_mode" value="admin" class="mode-switch-btn">Switch to Admin Mode</button>
                </form>
            </div>
        <?php } ?>
    </div>
</body>
</html>