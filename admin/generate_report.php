<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../lib/smtp_config.php'; // Explicitly include SMTP configuration

// Set timezone
date_default_timezone_set('America/New_York');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/report_errors.log');

// Create log file if it doesn't exist
$log_dir = dirname(__DIR__) . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Function to log report generation activity
function log_report($message) {
    error_log('[REPORT] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    // Redirect to employee interface
    header('Location: ../index.php');
    exit;
}

// Get logged in user
$admin_username = $_SESSION['username'];
log_report("Report generation started by: $admin_username");

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
    $report_type = $_POST['report_type'];
    $email_addresses = isset($_POST['email_addresses']) ? $_POST['email_addresses'] : '';
    $report_notes = isset($_POST['report_notes']) ? $_POST['report_notes'] : '';
    
    // Debug email addresses
    log_report("DEBUG: Initial email_addresses from form: '$email_addresses'");
    log_report("DEBUG: All POST data: " . print_r($_POST, true));
    
    // Special test case
    if ($report_type === 'test') {
        echo "<h1>Test Form Result</h1>";
        echo "<p>Received email address: " . htmlspecialchars($email_addresses) . "</p>";
        echo "<p>Empty check: " . (empty($email_addresses) ? 'TRUE (empty)' : 'FALSE (not empty)') . "</p>";
        echo "<p><a href='debug_form.php'>Back to Debug Form</a></p>";
        echo "<p><a href='reports.php'>Back to Reports</a></p>";
        exit;
    }
    
    // Check if this is a send confirmation after preview
    $is_send_confirmation = isset($_POST['send_report']) && $_POST['send_report'] === 'yes';
    
    // If this is a send confirmation, get the report details from session
    if ($is_send_confirmation) {
        log_report("Processing send confirmation after preview");
        $subject = $_SESSION['report_subject'];
        $report_content = $_SESSION['report_content'];
        $email_addresses_session = $_SESSION['report_email_addresses'] ?? '';
        $email_addresses_post = isset($_POST['email_addresses']) ? $_POST['email_addresses'] : '';
        
        log_report("DEBUG: email_addresses from session: '$email_addresses_session'");
        log_report("DEBUG: email_addresses from POST: '$email_addresses_post'");
        
        // Use POST value if present, otherwise use session value
        if (!empty($email_addresses_post)) {
            $email_addresses = $email_addresses_post;
            log_report("DEBUG: Using email_addresses from POST");
        } else if (!empty($email_addresses_session)) {
            $email_addresses = $email_addresses_session;
            log_report("DEBUG: Using email_addresses from session");
        }
        
        log_report("DEBUG: Final email_addresses value: '$email_addresses'");
    } else {
        log_report("Report type: $report_type, Email addresses: $email_addresses");
    }
    
    // Connect to database
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        log_report("Database connection failed: " . $db->connect_error);
        $_SESSION['success'] = false;
        $_SESSION['message'] = "Database connection failed: " . $db->connect_error;
        header('Location: reports.php');
        exit;
    }
    
    // Default values
    $subject = '';
    $report_content = '';
    $filename = '';
    
    try {
        // Generate report based on report type
        switch ($report_type) {
            case 'employee-hours':
                // Get form parameters
                $employees = $_POST['employees'] ?? [];
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                
                if (empty($start_date) || empty($end_date)) {
                    throw new Exception("Start date and end date are required");
                }
                
                log_report("Employee hours report: " . implode(',', $employees) . " from $start_date to $end_date");
                
                // Format dates for display
                $formatted_start = date('m/d/Y', strtotime($start_date));
                $formatted_end = date('m/d/Y', strtotime($end_date));
                
                // Set report title and filename
                $subject = "Employee Hours Report: $formatted_start to $formatted_end";
                $filename = "employee_hours_{$formatted_start}_{$formatted_end}.pdf";
                
                // Check if "all" is selected
                $all_employees = in_array('all', $employees);
                
                // Build query to get time records
                $query = "SELECT t.username, t.clock_in, t.clock_out, 
                         TIMESTAMPDIFF(HOUR, t.clock_in, IFNULL(t.clock_out, NOW())) as hours,
                         TIMESTAMPDIFF(MINUTE, t.clock_in, IFNULL(t.clock_out, NOW())) % 60 as minutes
                         FROM time_records t
                         JOIN employees e ON t.username = e.username
                         WHERE DATE(t.clock_in) >= ? AND DATE(t.clock_in) <= ?";
                
                if (!$all_employees && !empty($employees)) {
                    $query .= " AND t.username IN (" . str_repeat("?,", count($employees) - 1) . "?)";
                }
                
                $query .= " ORDER BY t.username, t.clock_in";
                
                $stmt = $db->prepare($query);
                
                if ($all_employees) {
                    $stmt->bind_param("ss", $start_date, $end_date);
                } else {
                    $types = "ss" . str_repeat("s", count($employees));
                    $params = array_merge([$types, $start_date, $end_date], $employees);
                    call_user_func_array([$stmt, 'bind_param'], $params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Generate report content
                $report_content = '<h2>Employee Hours Report</h2>';
                $report_content .= "<p>Period: $formatted_start to $formatted_end</p>";
                
                if ($report_notes) {
                    $report_content .= "<p>Notes: " . htmlspecialchars($report_notes) . "</p>";
                }
                
                $report_content .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $report_content .= '<tr style="background-color: #f2f2f2;">';
                $report_content .= '<th>Employee</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th>';
                $report_content .= '</tr>';
                
                $current_employee = '';
                $total_hours = 0;
                $employee_hours = 0;
                
                while ($row = $result->fetch_assoc()) {
                    // Handle employee change - add subtotal row
                    if ($current_employee != '' && $current_employee != $row['username']) {
                        // Add subtotal row for previous employee
                        $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                        $report_content .= '<td colspan="4" align="right">Subtotal for ' . htmlspecialchars($current_employee) . ':</td>';
                        $report_content .= '<td>' . number_format($employee_hours, 2) . '</td>';
                        $report_content .= '</tr>';
                        
                        // Reset employee hours
                        $employee_hours = 0;
                    }
                    
                    $current_employee = $row['username'];
                    
                    // Calculate hours for this record
                    $record_hours = $row['hours'] + ($row['minutes'] / 60);
                    $total_hours += $record_hours;
                    $employee_hours += $record_hours;
                    
                    // Format dates
                    $date = date('m/d/Y', strtotime($row['clock_in']));
                    $clock_in = date('h:i A', strtotime($row['clock_in']));
                    $clock_out = $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : 'Still Clocked In';
                    
                    // Add row to table
                    $report_content .= '<tr>';
                    $report_content .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                    $report_content .= '<td>' . $date . '</td>';
                    $report_content .= '<td>' . $clock_in . '</td>';
                    $report_content .= '<td>' . $clock_out . '</td>';
                    $report_content .= '<td>' . number_format($record_hours, 2) . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add final employee subtotal
                if ($current_employee != '') {
                    $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                    $report_content .= '<td colspan="4" align="right">Subtotal for ' . htmlspecialchars($current_employee) . ':</td>';
                    $report_content .= '<td>' . number_format($employee_hours, 2) . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add grand total
                $report_content .= '<tr style="font-weight: bold; background-color: #d9d9d9;">';
                $report_content .= '<td colspan="4" align="right">Grand Total:</td>';
                $report_content .= '<td>' . number_format($total_hours, 2) . '</td>';
                $report_content .= '</tr>';
                
                $report_content .= '</table>';
                
                break;
                
            case 'weekly-timesheet':
                // Get form parameters
                $employees = $_POST['employees'] ?? [];
                $week_start = $_POST['week'] ?? '';
                
                if (empty($week_start)) {
                    throw new Exception("Week start date is required");
                }
                
                // Calculate week end date (7 days from start)
                $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
                
                log_report("Weekly timesheet report: " . implode(',', $employees) . " for week starting $week_start");
                
                // Format dates for display
                $formatted_start = date('m/d/Y', strtotime($week_start));
                $formatted_end = date('m/d/Y', strtotime($week_end));
                
                // Set report title and filename
                $subject = "Weekly Timesheet Report: $formatted_start to $formatted_end";
                $filename = "weekly_timesheet_{$formatted_start}.pdf";
                
                // Check if "all" is selected
                $all_employees = in_array('all', $employees);
                
                // Build query to get time records for the week
                $query = "SELECT t.username, 
                         DATE(t.clock_in) as work_date,
                         MIN(t.clock_in) as first_clock_in,
                         MAX(IFNULL(t.clock_out, NOW())) as last_clock_out,
                         SUM(TIMESTAMPDIFF(MINUTE, t.clock_in, IFNULL(t.clock_out, NOW()))) as total_minutes,
                         COUNT(DISTINCT b.id) as break_count,
                         IFNULL(SUM(b.break_time), 0) as break_minutes
                         FROM time_records t
                         LEFT JOIN break_records b ON t.id = b.time_record_id
                         JOIN employees e ON t.username = e.username
                         WHERE DATE(t.clock_in) >= ? AND DATE(t.clock_in) <= ?";
                
                if (!$all_employees && !empty($employees)) {
                    $query .= " AND t.username IN (" . str_repeat("?,", count($employees) - 1) . "?)";
                }
                
                $query .= " GROUP BY t.username, DATE(t.clock_in)
                           ORDER BY t.username, work_date";
                
                $stmt = $db->prepare($query);
                
                if ($all_employees) {
                    $stmt->bind_param("ss", $week_start, $week_end);
                } else {
                    $types = "ss" . str_repeat("s", count($employees));
                    $params = array_merge([$types, $week_start, $week_end], $employees);
                    call_user_func_array([$stmt, 'bind_param'], $params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Generate report content
                $report_content = '<h2>Weekly Timesheet Report</h2>';
                $report_content .= "<p>Week: $formatted_start to $formatted_end</p>";
                
                if ($report_notes) {
                    $report_content .= "<p>Notes: " . htmlspecialchars($report_notes) . "</p>";
                }
                
                $report_content .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $report_content .= '<tr style="background-color: #f2f2f2;">';
                $report_content .= '<th>Employee</th><th>Date</th><th>First In</th><th>Last Out</th>';
                $report_content .= '<th>Breaks</th><th>Break Time</th><th>Work Hours</th>';
                $report_content .= '</tr>';
                
                $current_employee = '';
                $total_hours = 0;
                $employee_hours = 0;
                $weekly_data = [];
                
                while ($row = $result->fetch_assoc()) {
                    // Collect data for weekly summary
                    if (!isset($weekly_data[$row['username']])) {
                        $weekly_data[$row['username']] = [
                            'days' => 0,
                            'total_minutes' => 0,
                            'break_count' => 0,
                            'break_minutes' => 0
                        ];
                    }
                    
                    $weekly_data[$row['username']]['days']++;
                    $weekly_data[$row['username']]['total_minutes'] += $row['total_minutes'];
                    $weekly_data[$row['username']]['break_count'] += $row['break_count'];
                    $weekly_data[$row['username']]['break_minutes'] += $row['break_minutes'];
                    
                    // Handle employee change - add subtotal row
                    if ($current_employee != '' && $current_employee != $row['username']) {
                        // Add subtotal row for previous employee
                        $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                        $report_content .= '<td colspan="6" align="right">Subtotal for ' . htmlspecialchars($current_employee) . ':</td>';
                        $report_content .= '<td>' . number_format($employee_hours, 2) . '</td>';
                        $report_content .= '</tr>';
                        
                        // Reset employee hours
                        $employee_hours = 0;
                    }
                    
                    $current_employee = $row['username'];
                    
                    // Calculate work hours (total minus breaks)
                    $work_minutes = $row['total_minutes'] - $row['break_minutes'];
                    $work_hours = $work_minutes / 60;
                    
                    $total_hours += $work_hours;
                    $employee_hours += $work_hours;
                    
                    // Format dates and times
                    $date = date('D m/d', strtotime($row['work_date']));
                    $first_in = date('h:i A', strtotime($row['first_clock_in']));
                    $last_out = date('h:i A', strtotime($row['last_clock_out']));
                    $break_time = $row['break_minutes'] > 0 ? 
                                 floor($row['break_minutes'] / 60) . 'h ' . ($row['break_minutes'] % 60) . 'm' : 
                                 'None';
                    
                    // Add row to table
                    $report_content .= '<tr>';
                    $report_content .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                    $report_content .= '<td>' . $date . '</td>';
                    $report_content .= '<td>' . $first_in . '</td>';
                    $report_content .= '<td>' . $last_out . '</td>';
                    $report_content .= '<td>' . $row['break_count'] . '</td>';
                    $report_content .= '<td>' . $break_time . '</td>';
                    $report_content .= '<td>' . number_format($work_hours, 2) . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add final employee subtotal
                if ($current_employee != '') {
                    $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                    $report_content .= '<td colspan="6" align="right">Subtotal for ' . htmlspecialchars($current_employee) . ':</td>';
                    $report_content .= '<td>' . number_format($employee_hours, 2) . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add grand total
                $report_content .= '<tr style="font-weight: bold; background-color: #d9d9d9;">';
                $report_content .= '<td colspan="6" align="right">Grand Total:</td>';
                $report_content .= '<td>' . number_format($total_hours, 2) . '</td>';
                $report_content .= '</tr>';
                
                $report_content .= '</table>';
                
                // Add weekly summary section
                $report_content .= '<h3>Weekly Summary by Employee</h3>';
                $report_content .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $report_content .= '<tr style="background-color: #f2f2f2;">';
                $report_content .= '<th>Employee</th><th>Days Worked</th><th>Total Hours</th>';
                $report_content .= '<th>Break Count</th><th>Break Hours</th><th>Work Hours</th>';
                $report_content .= '</tr>';
                
                foreach ($weekly_data as $username => $data) {
                    $total_work_hours = ($data['total_minutes'] - $data['break_minutes']) / 60;
                    $break_hours = $data['break_minutes'] / 60;
                    
                    $report_content .= '<tr>';
                    $report_content .= '<td>' . htmlspecialchars($username) . '</td>';
                    $report_content .= '<td>' . $data['days'] . '</td>';
                    $report_content .= '<td>' . number_format($data['total_minutes'] / 60, 2) . '</td>';
                    $report_content .= '<td>' . $data['break_count'] . '</td>';
                    $report_content .= '<td>' . number_format($break_hours, 2) . '</td>';
                    $report_content .= '<td>' . number_format($total_work_hours, 2) . '</td>';
                    $report_content .= '</tr>';
                }
                
                $report_content .= '</table>';
                
                break;
                
            case 'break-analysis':
                // Get form parameters
                $employees = $_POST['employees'] ?? [];
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                
                if (empty($start_date) || empty($end_date)) {
                    throw new Exception("Start date and end date are required");
                }
                
                log_report("Break analysis report: " . implode(',', $employees) . " from $start_date to $end_date");
                
                // Format dates for display
                $formatted_start = date('m/d/Y', strtotime($start_date));
                $formatted_end = date('m/d/Y', strtotime($end_date));
                
                // Set report title and filename
                $subject = "Break Analysis Report: $formatted_start to $formatted_end";
                $filename = "break_analysis_{$formatted_start}_{$formatted_end}.pdf";
                
                // Check if "all" is selected
                $all_employees = in_array('all', $employees);
                
                // Build query to get break records
                $query = "SELECT t.username, DATE(b.break_in) as break_date, 
                         TIME(b.break_in) as break_start_time,
                         TIME(b.break_out) as break_end_time,
                         b.break_time,
                         TIMESTAMPDIFF(MINUTE, b.break_in, IFNULL(b.break_out, NOW())) as actual_break_minutes
                         FROM break_records b
                         JOIN time_records t ON b.time_record_id = t.id
                         JOIN employees e ON t.username = e.username
                         WHERE DATE(b.break_in) >= ? AND DATE(b.break_in) <= ?";
                
                if (!$all_employees && !empty($employees)) {
                    $query .= " AND t.username IN (" . str_repeat("?,", count($employees) - 1) . "?)";
                }
                
                $query .= " ORDER BY t.username, b.break_in";
                
                $stmt = $db->prepare($query);
                
                if ($all_employees) {
                    $stmt->bind_param("ss", $start_date, $end_date);
                } else {
                    $types = "ss" . str_repeat("s", count($employees));
                    $params = array_merge([$types, $start_date, $end_date], $employees);
                    call_user_func_array([$stmt, 'bind_param'], $params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Generate report content
                $report_content = '<h2>Break Analysis Report</h2>';
                $report_content .= "<p>Period: $formatted_start to $formatted_end</p>";
                
                if ($report_notes) {
                    $report_content .= "<p>Notes: " . htmlspecialchars($report_notes) . "</p>";
                }
                
                $report_content .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $report_content .= '<tr style="background-color: #f2f2f2;">';
                $report_content .= '<th>Employee</th><th>Date</th><th>Start Time</th><th>End Time</th><th>Duration (min)</th>';
                $report_content .= '</tr>';
                
                $current_employee = '';
                $total_break_minutes = 0;
                $employee_break_minutes = 0;
                $employee_break_count = 0;
                $employee_data = [];
                
                while ($row = $result->fetch_assoc()) {
                    // Collect employee data for summary
                    if (!isset($employee_data[$row['username']])) {
                        $employee_data[$row['username']] = [
                            'break_count' => 0,
                            'total_minutes' => 0,
                            'min_break' => PHP_INT_MAX,
                            'max_break' => 0,
                            'morning_breaks' => 0,
                            'afternoon_breaks' => 0
                        ];
                    }
                    
                    $break_minutes = $row['actual_break_minutes'] ?? 0;
                    $employee_data[$row['username']]['break_count']++;
                    $employee_data[$row['username']]['total_minutes'] += $break_minutes;
                    
                    if ($break_minutes > 0) {
                        $employee_data[$row['username']]['min_break'] = min($employee_data[$row['username']]['min_break'], $break_minutes);
                        $employee_data[$row['username']]['max_break'] = max($employee_data[$row['username']]['max_break'], $break_minutes);
                    }
                    
                    // Check if morning or afternoon break
                    $break_hour = (int)substr($row['break_start_time'], 0, 2);
                    if ($break_hour < 12) {
                        $employee_data[$row['username']]['morning_breaks']++;
                    } else {
                        $employee_data[$row['username']]['afternoon_breaks']++;
                    }
                    
                    // Handle employee change - add subtotal row
                    if ($current_employee != '' && $current_employee != $row['username']) {
                        // Add subtotal row for previous employee
                        $avg_break = $employee_break_count > 0 ? round($employee_break_minutes / $employee_break_count) : 0;
                        
                        $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                        $report_content .= '<td colspan="4" align="right">Subtotal for ' . htmlspecialchars($current_employee) . 
                                         ' (' . $employee_break_count . ' breaks, avg ' . $avg_break . ' min):</td>';
                        $report_content .= '<td>' . $employee_break_minutes . '</td>';
                        $report_content .= '</tr>';
                        
                        // Reset employee stats
                        $employee_break_minutes = 0;
                        $employee_break_count = 0;
                    }
                    
                    $current_employee = $row['username'];
                    
                    // Add break minutes to totals
                    $total_break_minutes += $break_minutes;
                    $employee_break_minutes += $break_minutes;
                    $employee_break_count++;
                    
                    // Format dates and times
                    $date = date('D m/d', strtotime($row['break_date']));
                    
                    // Add row to table
                    $report_content .= '<tr>';
                    $report_content .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                    $report_content .= '<td>' . $date . '</td>';
                    $report_content .= '<td>' . $row['break_start_time'] . '</td>';
                    $report_content .= '<td>' . ($row['break_end_time'] ?? 'Still on break') . '</td>';
                    $report_content .= '<td>' . $break_minutes . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add final employee subtotal
                if ($current_employee != '') {
                    $avg_break = $employee_break_count > 0 ? round($employee_break_minutes / $employee_break_count) : 0;
                    
                    $report_content .= '<tr style="font-weight: bold; background-color: #e9e9e9;">';
                    $report_content .= '<td colspan="4" align="right">Subtotal for ' . htmlspecialchars($current_employee) . 
                                     ' (' . $employee_break_count . ' breaks, avg ' . $avg_break . ' min):</td>';
                    $report_content .= '<td>' . $employee_break_minutes . '</td>';
                    $report_content .= '</tr>';
                }
                
                // Add grand total
                $total_breaks = array_sum(array_column($employee_data, 'break_count'));
                $avg_total = $total_breaks > 0 ? round($total_break_minutes / $total_breaks) : 0;
                
                $report_content .= '<tr style="font-weight: bold; background-color: #d9d9d9;">';
                $report_content .= '<td colspan="4" align="right">Grand Total (' . $total_breaks . ' breaks, avg ' . $avg_total . ' min):</td>';
                $report_content .= '<td>' . $total_break_minutes . '</td>';
                $report_content .= '</tr>';
                
                $report_content .= '</table>';
                
                // Add break analysis summary
                $report_content .= '<h3>Break Pattern Analysis</h3>';
                $report_content .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $report_content .= '<tr style="background-color: #f2f2f2;">';
                $report_content .= '<th>Employee</th><th>Break Count</th><th>Morning Breaks</th><th>Afternoon Breaks</th>';
                $report_content .= '<th>Min Duration</th><th>Max Duration</th><th>Avg Duration</th>';
                $report_content .= '</tr>';
                
                foreach ($employee_data as $username => $data) {
                    $min_break = $data['min_break'] === PHP_INT_MAX ? 0 : $data['min_break'];
                    $avg_break = $data['break_count'] > 0 ? round($data['total_minutes'] / $data['break_count']) : 0;
                    
                    $report_content .= '<tr>';
                    $report_content .= '<td>' . htmlspecialchars($username) . '</td>';
                    $report_content .= '<td>' . $data['break_count'] . '</td>';
                    $report_content .= '<td>' . $data['morning_breaks'] . '</td>';
                    $report_content .= '<td>' . $data['afternoon_breaks'] . '</td>';
                    $report_content .= '<td>' . $min_break . ' min</td>';
                    $report_content .= '<td>' . $data['max_break'] . ' min</td>';
                    $report_content .= '<td>' . $avg_break . ' min</td>';
                    $report_content .= '</tr>';
                }
                
                $report_content .= '</table>';
                
                break;
                
            default:
                throw new Exception("Invalid report type");
        }
        
        // If not a send confirmation, show preview
        if (!$is_send_confirmation) {
            // Store report details in session
            $_SESSION['report_subject'] = $subject;
            $_SESSION['report_content'] = $report_content;
            $_SESSION['report_email_addresses'] = $email_addresses;
            
            // Debug session storage
            log_report("DEBUG: Storing in session - email_addresses: '$email_addresses'");
            log_report("DEBUG: Session check immediately after storage: '" . ($_SESSION['report_email_addresses'] ?? 'NOT SET') . "'");
            
            // Ensure session is saved immediately
            session_write_close();
            // Note: Removed duplicate session_start() that was causing 'headers already sent' errors
            // session_start();
            
            // Verify session data after restarting
            log_report("DEBUG: Session check after restart: '" . ($_SESSION['report_email_addresses'] ?? 'NOT SET') . "'");
            
            // Show preview page
            outputPreviewPage($report_content, $subject, $report_type, $email_addresses);
            exit;
        }
        
        // Send email with report (only if this is a send confirmation)
        if ($is_send_confirmation && !empty($email_addresses)) {
            log_report("Preparing to send report to: $email_addresses");
            
            // Include PHPMailer
            require_once '../lib/PHPMailer/src/Exception.php';
            require_once '../lib/PHPMailer/src/PHPMailer.php';
            require_once '../lib/PHPMailer/src/SMTP.php';
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                // Log SMTP settings before attempting connection
                log_report("============ EMAIL DEBUG INFO ============");
                log_report("SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED'));
                log_report("SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED'));
                log_report("SMTP_PASSWORD: " . (defined('SMTP_PASSWORD') ? (SMTP_PASSWORD ? 'SET' : 'EMPTY') : 'NOT DEFINED'));
                log_report("SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'NOT DEFINED'));
                log_report("SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED'));
                log_report("SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT DEFINED'));
                log_report("SMTP_FROM_NAME: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NOT DEFINED'));
                log_report("==========================================");
                
                // Check if PHPMailer files exist
                $phpmailer_exception = '../lib/PHPMailer/src/Exception.php';
                $phpmailer_main = '../lib/PHPMailer/src/PHPMailer.php';
                $phpmailer_smtp = '../lib/PHPMailer/src/SMTP.php';
                
                if (!file_exists($phpmailer_exception)) {
                    throw new Exception("PHPMailer Exception file not found at: $phpmailer_exception");
                }
                if (!file_exists($phpmailer_main)) {
                    throw new Exception("PHPMailer main file not found at: $phpmailer_main");
                }
                if (!file_exists($phpmailer_smtp)) {
                    throw new Exception("PHPMailer SMTP file not found at: $phpmailer_smtp");
                }
                
                // Include PHPMailer files
                require_once $phpmailer_exception;
                require_once $phpmailer_main;
                require_once $phpmailer_smtp;
                
                // Create a new PHPMailer instance
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // CRITICAL: Temporarily enable error display for debugging
                ini_set('display_errors', 1);
                error_log("Starting email send process...");
                
                // Server settings - Explicitly for Gmail
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Force Gmail host
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = 'tls'; // Force TLS
                $mail->Port = 587; // Force Gmail port
                
                // Some servers need explicit auth type
                $mail->AuthType = 'LOGIN';
                
                // Add SSL/TLS verification bypass
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Enable debug output
                $mail->SMTPDebug = 3; // Most detailed debug output
                $mail->Debugoutput = function($str, $level) {
                    log_report("SMTP DEBUG ($level): $str");
                    // Also output to screen for immediate feedback
                    echo "SMTP DEBUG ($level): " . htmlspecialchars($str) . "<br>";
                };
                
                // Set longer timeout
                $mail->Timeout = 120; // 2 minutes timeout
                
                // Recipients
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                
                $email_list = explode(',', $email_addresses);
                $valid_recipients = false;
                
                foreach ($email_list as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addAddress($email);
                        $valid_recipients = true;
                        log_report("Added valid recipient: $email");
                        echo "Added valid recipient: $email<br>";
                    } else {
                        log_report("Invalid email address: $email");
                        echo "Invalid email address: $email<br>";
                    }
                }
                
                if (!$valid_recipients) {
                    throw new Exception("No valid email recipients were provided");
                }
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                
                // Prepare HTML email body with proper formatting
                $body = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . $subject . '</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 800px; margin: 0 auto; }
                        .header { background-color: #2c3e50; color: white; padding: 15px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 30px; }
                        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                        th { background-color: #f2f2f2; }
                        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>' . $subject . '</h1>
                        </div>
                        <div class="content">
                            ' . $report_content . '
                        </div>
                        <div class="footer">
                            <p>This report was generated by TIMEMASTER on ' . date('Y-m-d H:i:s') . '</p>
                            <p>Generated by: ' . htmlspecialchars($admin_username) . '</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                $mail->Body = $body;
                
                // Send the email
                echo "<div style='background-color: #f5f5f5; border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
                echo "<h3>Attempting to send email now...</h3>";
                $result = $mail->send();
                echo "<h3>Email send attempt complete</h3>";
                echo "Result: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
                echo "</div>";
                
                log_report("Email sent successfully");
                
                $_SESSION['success'] = true;
                $_SESSION['message'] = "Report generated and sent successfully to $email_addresses";
                
                // DEBUGGING: Don't redirect immediately so we can see output
                echo "<p>Debug complete. <a href='reports.php'>Click here to continue</a> if not automatically redirected.</p>";
                echo "<script>setTimeout(function() { window.location.href = 'reports.php'; }, 30000);</script>";
                exit;
            } catch (Exception $e) {
                log_report("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                
                // Detailed error display
                echo "<div style='background-color: #ffeeee; border: 1px solid #ff0000; padding: 10px; margin: 10px 0;'>";
                echo "<h3>Email Error</h3>";
                echo "<p>Error message: " . htmlspecialchars($e->getMessage()) . "</p>";
                if (isset($mail) && is_object($mail)) {
                    echo "<p>Mailer Error Info: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
                }
                echo "<p>Stack trace:<br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></p>";
                echo "</div>";
                
                throw new Exception("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            }
        } else {
            $_SESSION['success'] = true;
            $_SESSION['message'] = "Report generated successfully";
        }
        
    } catch (Exception $e) {
        log_report("Error generating report: " . $e->getMessage());
        $_SESSION['success'] = false;
        $_SESSION['message'] = "Error generating report: " . $e->getMessage();
    }
    
    // Close database connection
    $db->close();
    
    // Redirect back to reports page
    header('Location: reports.php');
    exit;
} else {
    // Invalid request
    log_report("Invalid request - no report type specified");
    $_SESSION['success'] = false;
    $_SESSION['message'] = "Invalid request";
    header('Location: reports.php');
    exit;
}

/**
 * Output the preview page with the report content and send button
 */
function outputPreviewPage($report_content, $subject, $report_type, $email_addresses) {
    global $base_path;
    ?>
    <div class="section-header">
        <h2><?php echo htmlspecialchars($subject); ?></h2>
    </div>
    <div class="report-container">
        <div class="debug-info small-text">
            Email addresses: <?php echo htmlspecialchars($email_addresses); ?>
        </div>
        <div class="report-preview">
            <?php echo $report_content; ?>
        </div>
        <div class="button-container">
            <div class="button-group">
                <a href="reports.php" class="cancel-btn">Cancel</a>
                <a href="test_email.php" class="edit-btn">Test Email System</a>
                <?php if (!empty($email_addresses)): ?>
                    <form method="post" action="send_report.php" style="display: inline-block;">
                        <input type="hidden" name="email_addresses" value="<?php echo htmlspecialchars($email_addresses); ?>">
                        <button type="submit" class="edit-btn">Send Report</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?> 