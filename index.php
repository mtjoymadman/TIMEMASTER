<?php
// CRITICAL: Exit immediately if this is a diagnostic or test request
// This prevents any redirects or session checks
$script_name = basename($_SERVER['PHP_SELF'] ?? '');
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$allowed_files = ['diagnose_email_issue.php', 'test_diagnostic_simple.php'];

foreach ($allowed_files as $file) {
    if ($script_name === $file || strpos($request_uri, $file) !== false) {
        http_response_code(404); // Return 404 to prevent any processing
        exit(); // Let the diagnostic file handle itself
    }
}

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user role
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Redirect based on role
if ($role === 'admin') {
    header("Location: admin/index.php");
    exit();
} else {
    header("Location: employee_portal.php");
    exit();
}

// If we get here, something went wrong
error_log("Unexpected role in index.php: " . $role);
header("Location: login.php");
exit();

// Get current week's time records
$week_start = date('Y-m-d', strtotime('sunday this week'));
$week_end = date('Y-m-d', strtotime('saturday this week'));

$stmt = $time_db->prepare("
    SELECT t.*, b.break_start, b.break_end, b.break_time,
           CASE 
               WHEN b.break_time IS NOT NULL THEN b.break_time
               WHEN b.break_start IS NOT NULL AND b.break_end IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, b.break_start, b.break_end)
               ELSE NULL 
           END as calculated_break_time
    FROM time_records t 
    LEFT JOIN breaks b ON t.id = b.time_record_id 
    WHERE t.username = ? 
    AND DATE(t.clock_in) BETWEEN ? AND ?
    ORDER BY t.clock_in DESC
");
$stmt->bind_param("sss", $_SESSION['username'], $week_start, $week_end);
$stmt->execute();
$week_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total hours for the week
$total_minutes = 0;
foreach ($week_records as $record) {
    if ($record['clock_out']) {
        $clock_in = strtotime($record['clock_in']);
        $clock_out = strtotime($record['clock_out']);
        $break_time = $record['calculated_break_time'] ?? 0;
        $total_minutes += ($clock_out - $clock_in) / 60 - $break_time;
    }
}
$total_hours = round($total_minutes / 60, 2);

?>
<!-- Add this before the logout button -->
<div class="week-summary">
    <h2>This Week's Time Records</h2>
    <div class="total-hours">Total Hours: <?php echo $total_hours; ?></div>
    <table class="time-records-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Break Duration</th>
                <th>Total Hours</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($week_records as $record): ?>
                <tr>
                    <td><?php echo date('m/d/Y', strtotime($record['clock_in'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                    <td><?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : 'Active'; ?></td>
                    <td>
                        <?php 
                        if ($record['calculated_break_time']) {
                            $break_hours = floor($record['calculated_break_time'] / 60);
                            $break_minutes = $record['calculated_break_time'] % 60;
                            echo sprintf('%02d:%02d', $break_hours, $break_minutes);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($record['clock_out']) {
                            $clock_in = strtotime($record['clock_in']);
                            $clock_out = strtotime($record['clock_out']);
                            $break_time = $record['calculated_break_time'] ?? 0;
                            $hours = ($clock_out - $clock_in) / 3600 - ($break_time / 60);
                            echo number_format($hours, 2);
                        } else {
                            echo 'Active';
                        }
                        ?>
                    </td>
                    <td><?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : '-'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.week-summary {
    background-color: #2d2d2d;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.week-summary h2 {
    color: #ffffff;
    margin-top: 0;
    margin-bottom: 15px;
}

.total-hours {
    font-size: 1.2em;
    color: #3498db;
    margin-bottom: 15px;
    font-weight: bold;
}

.time-records-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.time-records-table th,
.time-records-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #444;
}

.time-records-table th {
    background-color: #333;
    color: #fff;
    font-weight: bold;
}

.time-records-table tr:hover {
    background-color: #3a3a3a;
}

.time-records-table td {
    color: #ddd;
}
</style>