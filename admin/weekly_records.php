<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    header('Location: ../index.php');
    exit;
}

// Get logged in user
$logged_in_user = $_SESSION['username'];

// Connect to time database
$time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($time_db->connect_error) {
    die("Time database connection failed: " . $time_db->connect_error);
}

// Get start and end of the current week
$now = new DateTime('now', new DateTimeZone('America/New_York'));
$day_of_week = $now->format('N'); // 1 (Monday) to 7 (Sunday)
$start_of_week = clone $now;
$start_of_week->modify('-' . ($day_of_week - 1) . ' days');
$start_of_week->setTime(0, 0, 0);
$end_of_week = clone $start_of_week;
$end_of_week->modify('+6 days');
$end_of_week->setTime(23, 59, 59);

$start_of_week_str = $start_of_week->format('Y-m-d H:i:s');
$end_of_week_str = $end_of_week->format('Y-m-d H:i:s');

// Fetch all employees' time records for the current week
$weekly_records = [];
$stmt = $time_db->prepare("SELECT tr.id, tr.username, tr.clock_in, tr.clock_out 
                          FROM time_records tr 
                          WHERE tr.clock_in >= ? AND tr.clock_in <= ? 
                          ORDER BY tr.username, tr.clock_in");
$stmt->bind_param("ss", $start_of_week_str, $end_of_week_str);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $username = $row['username'];
    if (!isset($weekly_records[$username])) {
        $weekly_records[$username] = [];
    }
    $weekly_records[$username]['time_records'][] = $row;
}

// Fetch break records for the current week
foreach ($weekly_records as $username => &$data) {
    $data['breaks'] = [];
    foreach ($data['time_records'] as $time_record) {
        $stmt = $time_db->prepare("SELECT break_in, break_out 
                                  FROM break_records 
                                  WHERE time_record_id = ?");
        $stmt->bind_param("i", $time_record['id']);
        $stmt->execute();
        $break_result = $stmt->get_result();
        while ($break_row = $break_result->fetch_assoc()) {
            $data['breaks'][] = $break_row;
        }
    }
}
unset($data); // Unset the reference after the loop

// Calculate total hours for each employee (excluding breaks)
$total_hours = [];
foreach ($weekly_records as $username => $data) {
    $total_work_seconds = 0;
    $total_break_seconds = 0;

    // Calculate total work time
    foreach ($data['time_records'] as $record) {
        if ($record['clock_out']) {
            $clock_in = new DateTime($record['clock_in'], new DateTimeZone('America/New_York'));
            $clock_out = new DateTime($record['clock_out'], new DateTimeZone('America/New_York'));
            $diff = $clock_out->getTimestamp() - $clock_in->getTimestamp();
            $total_work_seconds += $diff;
        }
    }

    // Calculate total break time
    foreach ($data['breaks'] as $break) {
        if ($break['break_in'] && $break['break_out']) {
            $break_in = new DateTime($break['break_in'], new DateTimeZone('America/New_York'));
            $break_out = new DateTime($break['break_out'], new DateTimeZone('America/New_York'));
            $break_diff = $break_out->getTimestamp() - $break_in->getTimestamp();
            $total_break_seconds += $break_diff;
        }
    }

    // Subtract break time from work time
    $net_work_seconds = $total_work_seconds - $total_break_seconds;
    $hours = floor($net_work_seconds / 3600);
    $minutes = floor(($net_work_seconds % 3600) / 60);
    $total_hours[$username] = sprintf("%dh %dm", $hours, $minutes);
}

// Close database connection
$time_db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Time Records - TIMEMASTER</title>
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
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .records-table th, .records-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .records-table th {
            background-color: #2a2a2a;
            color: #e74c3c;
        }
        
        .records-table tr:nth-child(even) {
            background-color: #333;
        }
        
        .records-table tr:hover {
            background-color: #444;
        }
        
        .employee-section {
            margin-bottom: 30px;
        }
        
        .employee-section h2 {
            color: #e74c3c;
            margin-bottom: 10px;
        }
        
        .no-records {
            color: #aaa;
            font-style: italic;
        }
        
        .break-table {
            width: 80%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-left: 20px;
        }
        
        .break-table th, .break-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        
        .break-table th {
            background-color: #3a3a3a;
            color: #e74c3c;
        }
        
        .break-table tr:nth-child(even) {
            background-color: #383838;
        }
        
        .break-table tr:hover {
            background-color: #484848;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Weekly Time Records</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <h2>Weekly Time Records (<?php echo $start_of_week->format('Y-m-d'); ?> to <?php echo $end_of_week->format('Y-m-d'); ?>)</h2>
        
        <?php if (empty($weekly_records)): ?>
            <p class="no-records">No time records found for this week.</p>
        <?php else: ?>
            <?php foreach ($weekly_records as $username => $data): ?>
                <div class="employee-section">
                    <h2><?php echo htmlspecialchars($username); ?> - Total Hours: <?php echo isset($total_hours[$username]) ? $total_hours[$username] : '0h 0m'; ?></h2>
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['time_records'] as $record): ?>
                                <?php
                                    $clock_in = new DateTime($record['clock_in'], new DateTimeZone('America/New_York'));
                                    $clock_out = $record['clock_out'] ? new DateTime($record['clock_out'], new DateTimeZone('America/New_York')) : null;
                                    $duration = '';
                                    if ($clock_out) {
                                        $diff = $clock_out->getTimestamp() - $clock_in->getTimestamp();
                                        $hours = floor($diff / 3600);
                                        $minutes = floor(($diff % 3600) / 60);
                                        $duration = $hours . 'h ' . $minutes . 'm';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $clock_in->format('Y-m-d'); ?></td>
                                    <td><?php echo $clock_in->format('H:i'); ?></td>
                                    <td><?php echo $clock_out ? $clock_out->format('H:i') : 'Not clocked out'; ?></td>
                                    <td><?php echo $duration ?: 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (!empty($data['breaks'])): ?>
                        <h3>Breaks</h3>
                        <table class="break-table">
                            <thead>
                                <tr>
                                    <th>Break In</th>
                                    <th>Break Out</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['breaks'] as $break): ?>
                                    <?php
                                        $break_in = $break['break_in'] ? new DateTime($break['break_in'], new DateTimeZone('America/New_York')) : null;
                                        $break_out = $break['break_out'] ? new DateTime($break['break_out'], new DateTimeZone('America/New_York')) : null;
                                        $break_duration = '';
                                        if ($break_in && $break_out) {
                                            $break_diff = $break_out->getTimestamp() - $break_in->getTimestamp();
                                            $break_hours = floor($break_diff / 3600);
                                            $break_minutes = floor(($break_diff % 3600) / 60);
                                            $break_duration = $break_hours . 'h ' . $break_minutes . 'm';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $break_in ? $break_in->format('Y-m-d H:i') : 'N/A'; ?></td>
                                        <td><?php echo $break_out ? $break_out->format('H:i') : 'Not ended'; ?></td>
                                        <td><?php echo $break_duration ?: 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-records">No breaks recorded for this week.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html> 