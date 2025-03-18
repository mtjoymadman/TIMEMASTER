<?php
session_start();
date_default_timezone_set('America/New_York'); // Force EDT (UTC-4)
require_once '../functions.php';

// Redirect to login if not authenticated or not authorized
$logged_in_user = $_SESSION['username'] ?? '';
if (!canManageEmployees($logged_in_user)) {
    header("Location: ../login.php");
    exit;
}

$admin_action = $_POST['admin_action'] ?? '';
$admin_employee = $_POST['admin_employee'] ?? '';
$selected_employee = $_POST['selected_employee'] ?? '';

if ($admin_action && $admin_employee) {
    switch ($admin_action) {
        case 'clock_in': clockIn($admin_employee); break;
        case 'clock_out': clockOut($admin_employee); break;
        case 'break_in': breakIn($admin_employee); break;
        case 'break_out': breakOut($admin_employee); break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) && canManageEmployees($logged_in_user)) {
        $roles = isset($_POST['roles']) ? $_POST['roles'] : ['employee'];
        addEmployee($_POST['username'], $_POST['flag_auto_break'] ?? 0, $roles);
    } elseif (isset($_POST['modify']) && canManageEmployees($logged_in_user)) {
        $roles = isset($_POST['roles']) ? $_POST['roles'] : ['employee'];
        modifyEmployee($_POST['old_username'], $_POST['new_username'], $_POST['flag_auto_break'] ?? 0, $roles, $_POST['suspended'] ?? 0);
    } elseif (isset($_POST['delete']) && canManageEmployees($logged_in_user)) {
        deleteEmployee($_POST['username']);
    } elseif (isset($_POST['save_changes']) && hasRole($logged_in_user, 'admin')) {
        $id = $_POST['id'];
        $current_year = date('Y');
        $clock_in = "$current_year-{$_POST['clock_in_date']} {$_POST['clock_in_time']}";
        $clock_out = $_POST['clock_out_date'] && $_POST['clock_out_time'] ? "$current_year-{$_POST['clock_out_date']} {$_POST['clock_out_time']}" : null;
        $breaks = [];
        foreach ($_POST['breaks'] as $break) {
            $breaks[] = [
                'break_id' => $break['break_id'] ?? null,
                'break_in' => $break['break_in_date'] && $break['break_in_time'] ? "$current_year-{$break['break_in_date']} {$break['break_in_time']}" : null,
                'break_out' => $break['break_out_date'] && $break['break_out_time'] ? "$current_year-{$break['break_out_date']} {$break['break_out_time']}" : null,
                'break_time' => $break['break_time'] ?? 0
            ];
        }
        modifyTimeRecord($id, $clock_in, $clock_out, $breaks);
    } elseif (isset($_POST['suspend']) && canManageEmployees($logged_in_user)) {
        suspendEmployee($_POST['username']);
    } elseif (isset($_POST['add_holiday']) && hasRole($logged_in_user, 'admin')) {
        addHolidayPay($_POST['holiday_username'], $_POST['holiday_date']);
    }
}
$employees = $db->query("SELECT * FROM employees")->fetch_all(MYSQLI_ASSOC);
$on_clock = getEmployeesOnClock();
$all_employees = getAllEmployees();
$selected_username = $_POST['selected_username'] ?? '';
$selected_records = $selected_username ? getTimeRecords($selected_username) : [];
$weekly_records = $selected_employee ? getEmployeeWeeklyRecords($selected_employee) : [];
$weekly_summary = getWeeklySummary();

// Process weekly summary for display
$summary_by_employee = [];
foreach ($weekly_summary as $entry) {
    $username = $entry['username'];
    $date = $entry['work_date'];
    if (!isset($summary_by_employee[$username])) {
        $summary_by_employee[$username] = ['days' => [], 'total_hours' => 0];
    }
    $summary_by_employee[$username]['days'][$date] = [
        'hours' => $entry['hours_worked'] ?? 0,
        'breaks' => $entry['breaks_taken'] ?? 0,
        'break_time' => $entry['total_break_time'] ?? 0
    ];
    $summary_by_employee[$username]['total_hours'] += $entry['hours_worked'] ?? 0;
}

foreach ($weekly_records as &$record) {
    if ($record['clock_out']) {
        $total_seconds = strtotime($record['clock_out']) - strtotime($record['clock_in']);
        $break_seconds = array_sum(array_map(fn($b) => $b['break_out'] && $b['break_in'] ? strtotime($b['break_out']) - strtotime($b['break_in']) : 0, array_filter($weekly_records, fn($r) => $r['id'] === $record['id'])));
        $total_seconds -= $break_seconds;
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $record['accumulated_time'] = "$hours h, $minutes m";
    } else {
        $record['accumulated_time'] = '-';
    }
}
unset($record);
?>
<!DOCTYPE html>
<html>
<head>
    <title>TIMEMASTER Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <a href="../index.php" class="home-btn">Home</a>
        <img src="../images/logo.png" alt="Red Lion Salvage Logo" class="logo">
    </header>
    <div class="container">
        <h1>TIMEMASTER Admin</h1>
        <p>Current Time: <strong><?php echo date('h:i A'); ?></strong></p> <!-- Displays 11:03 PM -->
        
        <form method="post" class="button-group" style="margin-top: 10px;">
            <select name="admin_employee" required>
                <option value="">Select Employee</option>
                <?php foreach ($all_employees as $emp) { ?>
                    <option value="<?php echo $emp['username']; ?>" <?php echo $admin_employee === $emp['username'] ? 'selected' : ''; ?>>
                        <?php echo $emp['username']; ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit" name="admin_action" value="clock_in">Clock In</button>
            <button type="submit" name="admin_action" value="clock_out">Clock Out</button>
            <button type="submit" name="admin_action" value="break_in">Start Break</button>
            <button type="submit" name="admin_action" value="break_out">End Break</button>
        </form>
        
        <h2>Employees Currently Clocked In</h2>
        <table>
            <tr><th>Username</th></tr>
            <?php foreach ($on_clock as $emp) { ?>
                <tr>
                    <td>
                        <form method="post" class="button-group">
                            <input type="hidden" name="selected_employee" value="<?php echo $emp['username']; ?>">
                            <button type="submit"><?php echo $emp['username']; ?></button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </table>
        
        <?php if ($selected_employee) { ?>
            <h2>Weekly Records for <?php echo $selected_employee; ?></h2>
            <form method="post" class="button-group">
                <input type="hidden" name="selected_employee" value="<?php echo $selected_employee; ?>">
                <table>
                    <tr>
                        <th>ID</th><th>User</th><th>Roles</th><th>Clock In Date</th><th>Clock In Time</th><th>Clock Out Date</th><th>Clock Out Time</th>
                        <th>Break In Date</th><th>Break In Time</th><th>Break Out Date</th><th>Break Out Time</th><th>Break (min)</th><th>Accumulated Time</th>
                    </tr>
                    <?php 
                    $current_id = null;
                    foreach ($weekly_records as $record) { 
                        if ($current_id !== $record['id']) { 
                            $current_id = $record['id'];
                    ?>
                        <tr>
                            <td rowspan="<?php echo count(array_filter($weekly_records, fn($r) => $r['id'] === $current_id)); ?>">
                                <input type="hidden" name="id" value="<?php echo $current_id; ?>">
                                <?php echo $current_id; ?>
                            </td>
                            <td><?php echo htmlspecialchars($record['username']); ?></td>
                            <td><?php echo htmlspecialchars($record['role']); ?></td>
                            <td><input type="text" name="clock_in_date" value="<?php echo date('m-d', strtotime($record['clock_in'])); ?>" class="date-input"></td>
                            <td><input type="time" name="clock_in_time" value="<?php echo date('H:i:s', strtotime($record['clock_in'])); ?>" step="1"></td>
                            <td><input type="text" name="clock_out_date" value="<?php echo $record['clock_out'] ? date('m-d', strtotime($record['clock_out'])) : ''; ?>" class="date-input"></td>
                            <td><input type="time" name="clock_out_time" value="<?php echo $record['clock_out'] ? date('H:i:s', strtotime($record['clock_out'])) : ''; ?>" step="1"></td>
                            <td><input type="text" name="breaks[<?php echo $record['break_id']; ?>][break_in_date]" value="<?php echo $record['break_in'] ? date('m-d', strtotime($record['break_in'])) : ''; ?>" class="date-input"></td>
                            <td><input type="time" name="breaks[<?php echo $record['break_id']; ?>][break_in_time]" value="<?php echo $record['break_in'] ? date('H:i:s', strtotime($record['break_in'])) : ''; ?>" step="1"></td>
                            <td><input type="text" name="breaks[<?php echo $record['break_id']; ?>][break_out_date]" value="<?php echo $record['break_out'] ? date('m-d', strtotime($record['break_out'])) : ''; ?>" class="date-input"></td>
                            <td><input type="time" name="breaks[<?php echo $record['break_id']; ?>][break_out_time]" value="<?php echo $record['break_out'] ? date('H:i:s', strtotime($record['break_out'])) : ''; ?>" step="1"></td>
                            <td><input type="number" name="breaks[<?php echo $record['break_id']; ?>][break_time]" value="<?php echo $record['break_time']; ?>" min="0" class="number-input">
                                <input type="hidden" name="breaks[<?php echo $record['break_id']; ?>][break_id]" value="<?php echo $record['break_id']; ?>">
                            </td>
                            <td><?php echo $record['accumulated_time']; ?></td>
                        </tr>
                    <?php } else { ?>
                        <tr>
                            <td><input type="text" name="breaks[<?php echo $record['break_id']; ?>][break_in_date]" value="<?php echo $record['break_in'] ? date('m-d', strtotime($record['break_in'])) : ''; ?>" class="date-input"></td>
                            <td><input type="time" name="breaks[<?php echo $record['break_id']; ?>][break_in_time]" value="<?php echo $record['break_in'] ? date('H:i:s', strtotime($record['break_in'])) : ''; ?>" step="1"></td>
                            <td><input type="text" name="breaks[<?php echo $record['break_id']; ?>][break_out_date]" value="<?php echo $record['break_out'] ? date('m-d', strtotime($record['break_out'])) : ''; ?>" class="date-input"></td>
                            <td><input type="time" name="breaks[<?php echo $record['break_id']; ?>][break_out_time]" value="<?php echo $record['break_out'] ? date('H:i:s', strtotime($record['break_out'])) : ''; ?>" step="1"></td>
                            <td><input type="number" name="breaks[<?php echo $record['break_id']; ?>][break_time]" value="<?php echo $record['break_time']; ?>" min="0" class="number-input">
                                <input type="hidden" name="breaks[<?php echo $record['break_id']; ?>][break_id]" value="<?php echo $record['break_id']; ?>">
                            </td>
                            <td></td>
                        </tr>
                    <?php } } ?>
                    <tr><td colspan="13"><button type="submit" name="save_changes">Save</button></td></tr>
                </table>
            </form>
        <?php } ?>
        
        <h2>All Employees</h2>
        <form method="post" class="button-group">
            <select name="selected_username">
                <option value="">Select Employee</option>
                <?php foreach ($all_employees as $emp) { ?>
                    <option value="<?php echo $emp['username']; ?>" <?php echo $selected_username === $emp['username'] ? 'selected' : ''; ?>>
                        <?php echo $emp['username']; ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit">View/Modify Records</button>
        </form>
        
        <?php if ($selected_username && hasRole($logged_in_user, 'admin')) { ?>
            <h2>Records for <?php echo $selected_username; ?></h2>
            <table>
                <tr><th>ID</th><th>Clock In</th><th>Clock Out</th><th>Break In</th><th>Break Out</th><th>Break (min)</th></tr>
                <?php 
                $current_id = null;
                foreach ($selected_records as $record) { 
                    if ($current_id !== $record['id']) { 
                        $current_id = $record['id'];
                ?>
                    <tr>
                        <td rowspan="<?php echo count(array_filter($selected_records, fn($r) => $r['id'] === $current_id)); ?>">
                            <?php echo $current_id; ?>
                        </td>
                        <td><?php echo date('Y-m-d h:i A', strtotime($record['clock_in'])); ?></td>
                        <td><?php echo $record['clock_out'] ? date('Y-m-d h:i A', strtotime($record['clock_out'])) : ''; ?></td>
                        <td><?php echo $record['break_in'] ? date('Y-m-d h:i A', strtotime($record['break_in'])) : ''; ?></td>
                        <td><?php echo $record['break_out'] ? date('Y-m-d h:i A', strtotime($record['break_out'])) : ''; ?></td>
                        <td><?php echo $record['break_time']; ?></td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td><?php echo $record['break_in'] ? date('Y-m-d h:i A', strtotime($record['break_in'])) : ''; ?></td>
                        <td><?php echo $record['break_out'] ? date('Y-m-d h:i A', strtotime($record['break_out'])) : ''; ?></td>
                        <td><?php echo $record['break_time']; ?></td>
                    </tr>
                <?php } } ?>
            </table>
        <?php } ?>
        
        <h2>Add Employee</h2>
        <form method="post" class="button-group">
            <input type="text" name="username" placeholder="Username" required>
            <label><input type="checkbox" name="flag_auto_break" value="1"> Flagged</label>
            <div>
                <label><input type="checkbox" name="roles[]" value="employee" checked disabled> Employee (required)</label>
                <label><input type="checkbox" name="roles[]" value="admin" <?php echo hasRole($logged_in_user, 'admin') ? '' : 'disabled'; ?>> Admin</label>
                <label><input type="checkbox" name="roles[]" value="baby admin"> Baby Admin</label>
                <label><input type="checkbox" name="roles[]" value="driver"> Driver</label>
                <label><input type="checkbox" name="roles[]" value="yardman"> Yardman</label>
                <label><input type="checkbox" name="roles[]" value="office"> Office</label>
            </div>
            <button type="submit" name="add">Add</button>
        </form>
        
        <h2>Manage Employees</h2>
        <table>
            <tr><th>Username</th><th>Flagged</th><th>Roles</th><th>Suspended</th><th>Actions</th></tr>
            <?php foreach ($employees as $emp) { ?>
                <tr>
                    <form method="post" class="button-group">
                        <td><input type="text" name="new_username" value="<?php echo $emp['username']; ?>"></td>
                        <td><input type="checkbox" name="flag_auto_break" value="1" <?php echo $emp['flag_auto_break'] ? 'checked' : ''; ?>></td>
                        <td>
                            <?php $emp_roles = explode(',', $emp['role']); ?>
                            <label><input type="checkbox" name="roles[]" value="employee" <?php echo in_array('employee', $emp_roles) ? 'checked' : ''; ?> disabled> Employee</label>
                            <label><input type="checkbox" name="roles[]" value="admin" <?php echo in_array('admin', $emp_roles) ? 'checked' : ''; ?> <?php echo hasRole($logged_in_user, 'admin') ? '' : 'disabled'; ?>> Admin</label>
                            <label><input type="checkbox" name="roles[]" value="baby admin" <?php echo in_array('baby admin', $emp_roles) ? 'checked' : ''; ?>></label>
                            <label><input type="checkbox" name="roles[]" value="driver" <?php echo in_array('driver', $emp_roles) ? 'checked' : ''; ?>></label>
                            <label><input type="checkbox" name="roles[]" value="yardman" <?php echo in_array('yardman', $emp_roles) ? 'checked' : ''; ?>></label>
                            <label><input type="checkbox" name="roles[]" value="office" <?php echo in_array('office', $emp_roles) ? 'checked' : ''; ?>></label>
                        </td>
                        <td><input type="checkbox" name="suspended" value="1" <?php echo $emp['suspended'] ? 'checked' : ''; ?> disabled></td>
                        <td>
                            <input type="hidden" name="old_username" value="<?php echo $emp['username']; ?>">
                            <button type="submit" name="modify">Modify</button>
                            <button type="submit" name="delete">Delete</button>
                            <button type="submit" name="suspend">Suspend</button>
                        </td>
                    </form>
                </tr>
            <?php } ?>
        </table>
        
        <?php if (hasRole($logged_in_user, 'admin')) { ?>
            <h2>Weekly Summary (Since Sunday)</h2>
            <table>
                <tr>
                    <th>Employee</th>
                    <th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th><th>Saturday</th>
                    <th>Total Hours</th>
                </tr>
                <?php foreach ($summary_by_employee as $username => $data) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($username); ?></td>
                        <?php 
                        $week_start = strtotime('sunday this week');
                        for ($i = 0; $i < 7; $i++) {
                            $date = date('Y-m-d', $week_start + ($i * 86400));
                            $day_data = $data['days'][$date] ?? ['hours' => 0, 'breaks' => 0, 'break_time' => 0];
                        ?>
                            <td>
                                <?php echo $day_data['hours']; ?>h<br>
                                <?php echo $day_data['breaks']; ?> breaks (<?php echo $day_data['break_time']; ?> min)
                            </td>
                        <?php } ?>
                        <td><?php echo $data['total_hours']; ?>h</td>
                    </tr>
                <?php } ?>
            </table>
            
            <h2>Add Holiday Pay</h2>
            <form method="post" class="button-group">
                <select name="holiday_username">
                    <option value="">Select Employee</option>
                    <?php foreach ($all_employees as $emp) { ?>
                        <option value="<?php echo $emp['username']; ?>"><?php echo $emp['username']; ?></option>
                    <?php } ?>
                </select>
                <input type="date" name="holiday_date" required>
                <button type="submit" name="add_holiday">Add 8 Hours Holiday Pay</button>
            </form>
        <?php } ?>
    </div>
</body>
</html>