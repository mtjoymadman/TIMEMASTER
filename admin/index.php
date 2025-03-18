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
$error = '';
$success = '';

if ($admin_action && $admin_employee) {
    switch ($admin_action) {
        case 'clock_in': clockIn($admin_employee); break;
        case 'clock_out': clockOut($admin_employee); break;
        case 'break_in': breakIn($admin_employee); break;
        case 'break_out': breakOut($admin_employee); break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['switch_mode']) && $_POST['switch_mode'] === 'employee') {
            header("Location: ../index.php");
            exit;
        }
        if (isset($_POST['clock_out_all']) && hasRole($logged_in_user, 'admin')) {
            $count = clockOutAllEmployees($logged_in_user);
            if ($count !== false) {
                $success = "Successfully clocked out $count employees.";
            } else {
                $error = "Failed to clock out employees.";
            }
        }
        if (isset($_POST['add']) && canManageEmployees($logged_in_user)) {
            $roles = isset($_POST['roles']) ? $_POST['roles'] : ['employee'];
            if (addEmployee($_POST['username'], $_POST['flag_auto_break'] ?? 0, $roles)) {
                $success = "Employee added successfully.";
            } else {
                $error = "Failed to add employee.";
            }
        } elseif (isset($_POST['modify']) && canManageEmployees($logged_in_user)) {
            // Debug output
            error_log("Modify employee form submitted");
            error_log("Old username: " . $_POST['old_username']);
            error_log("New username: " . $_POST['new_username']);
            error_log("Flag auto break: " . ($_POST['flag_auto_break'] ?? 0));
            error_log("Roles: " . print_r($_POST['roles'] ?? [], true));
            error_log("Suspended: " . ($_POST['suspended'] ?? 0));

            // Ensure roles array is properly set
            $roles = isset($_POST['roles']) ? $_POST['roles'] : ['employee'];
            if (!is_array($roles)) {
                $roles = ['employee'];
            }

            // Convert checkbox values to proper boolean
            $flag_auto_break = isset($_POST['flag_auto_break']) ? 1 : 0;
            $suspended = isset($_POST['suspended']) ? 1 : 0;

            // Validate inputs
            if (empty($_POST['old_username']) || empty($_POST['new_username'])) {
                $error = "Username cannot be empty.";
            } else {
                if (modifyEmployee(
                    $_POST['old_username'],
                    $_POST['new_username'],
                    $flag_auto_break,
                    $roles,
                    $suspended
                )) {
                    $success = "Employee modified successfully.";
                } else {
                    $error = "Failed to modify employee. Check server logs for details.";
                }
            }
        } elseif (isset($_POST['delete']) && canManageEmployees($logged_in_user)) {
            if (deleteEmployee($_POST['username'])) {
                $success = "Employee deleted successfully.";
            } else {
                $error = "Failed to delete employee.";
            }
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
            if (modifyTimeRecord($id, $clock_in, $clock_out, $breaks)) {
                $success = "Time record modified successfully.";
            } else {
                $error = "Failed to modify time record.";
            }
        } elseif (isset($_POST['suspend']) && canManageEmployees($logged_in_user)) {
            if (suspendEmployee($_POST['username'])) {
                $success = "Employee status updated successfully.";
            } else {
                $error = "Failed to update employee status.";
            }
        } elseif (isset($_POST['add_holiday']) && hasRole($logged_in_user, 'admin')) {
            if (addHolidayPay($_POST['holiday_username'], $_POST['holiday_date'])) {
                $success = "Holiday pay added successfully.";
            } else {
                $error = "Failed to add holiday pay.";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// Get all employee data
$employees = getAllEmployees();
$on_clock = getEmployeesOnClock();
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
        <p>Current Time: <strong><?php echo date('h:i A'); ?></strong></p>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Clock Management</h2>
            <form method="post" class="button-group">
                <select name="admin_employee" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp['username']); ?>" <?php echo $admin_employee === $emp['username'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['username']); ?>
                        </option>
                    <?php } ?>
                </select>
                <button type="submit" name="admin_action" value="clock_in">Clock In</button>
                <button type="submit" name="admin_action" value="clock_out">Clock Out</button>
                <button type="submit" name="admin_action" value="break_in">Start Break</button>
                <button type="submit" name="admin_action" value="break_out">End Break</button>
            </form>
            <?php if (hasRole($logged_in_user, 'admin')) { ?>
                <form method="post" class="button-group" style="margin-top: 10px;">
                    <button type="submit" name="clock_out_all" class="clock-out-all-btn">Clock Out All Employees</button>
                </form>
            <?php } ?>
        </div>
        
        <div class="admin-section">
            <h2>Employees Currently Clocked In</h2>
            <table>
                <tr><th>Username</th><th>Status</th><th>Duration</th></tr>
                <?php foreach ($on_clock as $emp) { 
                    // Get current time record
                    $stmt = $db->prepare("SELECT id, clock_in FROM time_records WHERE username = ? AND clock_out IS NULL");
                    $stmt->bind_param("s", $emp);
                    $stmt->execute();
                    $time_result = $stmt->get_result()->fetch_assoc();
                    
                    if ($time_result) {
                        // Get current break if any
                        $stmt = $db->prepare("SELECT break_in FROM breaks WHERE time_record_id = ? AND break_out IS NULL");
                        $stmt->bind_param("i", $time_result['id']);
                        $stmt->execute();
                        $break_result = $stmt->get_result()->fetch_assoc();
                        
                        if ($break_result) {
                            // Employee is on break
                            $status_class = 'status-break';
                            $status_text = 'On Break';
                            $break_duration = time() - strtotime($break_result['break_in']);
                            $minutes = floor($break_duration / 60);
                            $duration = $minutes . ' min';
                        } else {
                            // Employee is clocked in
                            $status_class = 'status-active';
                            $status_text = 'Clocked In';
                            $shift_duration = time() - strtotime($time_result['clock_in']);
                            $hours = floor($shift_duration / 3600);
                            $minutes = floor(($shift_duration % 3600) / 60);
                            $duration = $hours . 'h ' . $minutes . 'm';
                        }
                    } else {
                        // Employee is not clocked in
                        $status_class = 'status-inactive';
                        $status_text = 'Not Clocked In';
                        $duration = '';
                    }
                ?>
                    <tr>
                        <td>
                            <form method="post" class="button-group">
                                <input type="hidden" name="selected_employee" value="<?php echo htmlspecialchars($emp); ?>">
                                <a href="#" onclick="this.closest('form').submit(); return false;" class="employee-link <?php echo $status_class; ?>">
                                    <strong><?php echo htmlspecialchars($emp); ?></strong>
                                </a>
                            </form>
                        </td>
                        <td><?php echo $status_text; ?></td>
                        <td><?php echo $duration; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        
        <div class="admin-section">
            <h2>View Employee Records</h2>
            <form method="post" class="button-group">
                <select name="selected_username">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp['username']); ?>" <?php echo $selected_username === $emp['username'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['username']); ?>
                        </option>
                    <?php } ?>
                </select>
                <button type="submit">View/Modify Records</button>
            </form>
        </div>
        
        <div class="admin-section">
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
        </div>
        
        <div class="time-records-section">
            <h2>Time Records</h2>
            <form method="post" class="search-form">
                <input type="date" name="date">
                <button type="submit">Search Records</button>
            </form>
            <?php if ($selected_records) { ?>
                <table>
                    <tr>
                        <th>Day</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                        <th>Break (min)</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                    <?php 
                    $current_id = null;
                    foreach ($selected_records as $record) { 
                        if ($current_id !== $record['id']) { 
                            $current_id = $record['id'];
                            // Calculate total break minutes for this record
                            $total_break_minutes = array_sum(array_map(function($r) {
                                return $r['break_time'] ?? 0;
                            }, array_filter($selected_records, fn($r) => $r['id'] === $current_id)));
                            
                            // Calculate duration
                            $duration = strtotime($record['clock_out']) - strtotime($record['clock_in']);
                            $hours = floor($duration / 3600);
                            $minutes = floor(($duration % 3600) / 60);
                            $duration_str = $hours . 'h ' . $minutes . 'm';
                    ?>
                        <tr>
                            <td><?php echo formatDate($record['clock_in']); ?></td>
                            <td><?php echo formatTime($record['clock_in']); ?></td>
                            <td><?php echo formatTime($record['clock_out']); ?></td>
                            <td><?php echo $duration_str; ?></td>
                            <td><?php echo $total_break_minutes > 0 ? $total_break_minutes : '-'; ?></td>
                            <td><?php echo isset($record['is_external']) ? htmlspecialchars($record['reason']) : '-'; ?></td>
                            <td>
                                <button class="edit-btn" onclick="openEditTimeRecordModal(
                                    <?php echo $record['id']; ?>,
                                    '<?php echo date('Y-m-d\TH:i', strtotime($record['clock_in'])); ?>',
                                    '<?php echo $record['clock_out'] ? date('Y-m-d\TH:i', strtotime($record['clock_out'])) : ''; ?>',
                                    <?php echo json_encode(array_filter($selected_records, fn($r) => $r['id'] === $record['id'] && isset($r['break_in']))); ?>
                                )">Edit</button>
                            </td>
                        </tr>
                    <?php } } ?>
                </table>
            <?php } ?>
        </div>

        <!-- Edit Time Record Modal -->
        <div id="editTimeRecordModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditTimeRecordModal()">&times;</span>
                <h2>Edit Time Record</h2>
                <form method="post" class="edit-time-form">
                    <input type="hidden" name="action" value="save_record">
                    <input type="hidden" name="record_id" id="edit_record_id">
                    <div class="form-group">
                        <label for="edit_clock_in">Clock In:</label>
                        <input type="datetime-local" id="edit_clock_in" name="clock_in" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_clock_out">Clock Out:</label>
                        <input type="datetime-local" id="edit_clock_out" name="clock_out" required>
                    </div>
                    <div id="breaks-container">
                        <!-- Breaks will be added here dynamically -->
                    </div>
                    <button type="button" onclick="addBreak()" class="blue-button">Add Break</button>
                    <button type="submit" class="green-button">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="admin-section">
            <h2>Add Holiday Pay</h2>
            <form method="post" class="button-group">
                <select name="holiday_username">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp['username']); ?>"><?php echo htmlspecialchars($emp['username']); ?></option>
                    <?php } ?>
                </select>
                <input type="date" name="holiday_date" required>
                <button type="submit" name="add_holiday">Add 8 Hours Holiday Pay</button>
            </form>
        </div>

        <div class="admin-section">
            <h2>Manage Employees</h2>
            <table>
                <tr><th>Username</th><th>Flagged</th><th>Roles</th><th>Suspended</th><th>Actions</th></tr>
                <?php foreach ($employees as $emp) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['username']); ?></td>
                        <td><?php echo $emp['flag_auto_break'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($emp['role']); ?></td>
                        <td><?php echo $emp['suspended'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <button class="edit-btn" onclick="openEmployeeModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">Edit</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($emp['username']); ?>">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" name="suspend" class="<?php echo $emp['suspended'] ? 'unsuspend-btn' : 'suspend-btn'; ?>">
                                    <?php echo $emp['suspended'] ? 'Unsuspend' : 'Suspend'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <!-- Employee Modal -->
        <div id="employeeModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Employee</h2>
                <form method="post">
                    <input type="hidden" name="old_username" id="old_username">
                    <div>
                        <label for="new_username">Username:</label>
                        <input type="text" id="new_username" name="new_username" required>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" id="flag_auto_break" name="flag_auto_break" value="1">
                            Flagged for Auto Break
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" id="suspended" name="suspended" value="1">
                            Suspended
                        </label>
                    </div>
                    <div>
                        <h3>Roles:</h3>
                        <label><input type="checkbox" id="role_employee" name="roles[]" value="employee" checked disabled> Employee (required)</label>
                        <label><input type="checkbox" id="role_admin" name="roles[]" value="admin"> Admin</label>
                        <label><input type="checkbox" id="role_baby_admin" name="roles[]" value="baby admin"> Baby Admin</label>
                        <label><input type="checkbox" id="role_driver" name="roles[]" value="driver"> Driver</label>
                        <label><input type="checkbox" id="role_yardman" name="roles[]" value="yardman"> Yardman</label>
                        <label><input type="checkbox" id="role_office" name="roles[]" value="office"> Office</label>
                    </div>
                    <button type="submit" name="modify">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="admin-section">
            <form method="post" class="button-group">
                <button type="submit" name="switch_mode" value="employee" class="mode-switch-btn">Switch to Employee Mode</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('employeeModal');
        const span = document.getElementsByClassName('close')[0];

        function openEmployeeModal(employee) {
            document.getElementById('old_username').value = employee.username;
            document.getElementById('new_username').value = employee.username;
            document.getElementById('flag_auto_break').checked = employee.flag_auto_break == 1;
            document.getElementById('suspended').checked = employee.suspended == 1;
            
            // Reset all role checkboxes
            document.querySelectorAll('[id^="role_"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Set roles
            const roles = employee.role.split(',');
            roles.forEach(role => {
                const checkbox = document.getElementById(`role_${role.replace(' ', '_')}`);
                if (checkbox) checkbox.checked = true;
            });
            
            // Disable admin checkbox if user is not admin
            document.getElementById('role_admin').disabled = !<?php echo hasRole($logged_in_user, 'admin') ? 'true' : 'false'; ?>;
            
            modal.style.display = 'block';
        }

        span.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        function openEditTimeRecordModal(recordId, clockIn, clockOut, breaks) {
            document.getElementById('edit_record_id').value = recordId;
            document.getElementById('edit_clock_in').value = clockIn;
            document.getElementById('edit_clock_out').value = clockOut;
            
            const breaksContainer = document.getElementById('breaks-container');
            breaksContainer.innerHTML = '';
            
            if (breaks && breaks.length > 0) {
                breaks.forEach((break_, index) => {
                    addBreak(break_.break_in, break_.break_out, break_.break_time, index);
                });
            }
            
            document.getElementById('editTimeRecordModal').style.display = 'block';
        }

        function closeEditTimeRecordModal() {
            document.getElementById('editTimeRecordModal').style.display = 'none';
        }

        function addBreak(breakIn = '', breakOut = '', breakTime = 0, index = null) {
            const breaksContainer = document.getElementById('breaks-container');
            const breakIndex = index !== null ? index : breaksContainer.children.length;
            
            const breakDiv = document.createElement('div');
            breakDiv.className = 'break-entry';
            breakDiv.innerHTML = `
                <h3>Break ${breakIndex + 1}</h3>
                <div class="form-group">
                    <label>Break In:</label>
                    <input type="datetime-local" name="breaks[${breakIndex}][break_in]" value="${breakIn}" required>
                </div>
                <div class="form-group">
                    <label>Break Out:</label>
                    <input type="datetime-local" name="breaks[${breakIndex}][break_out]" value="${breakOut}" required>
                </div>
                <div class="form-group">
                    <label>Break Time (minutes):</label>
                    <input type="number" name="breaks[${breakIndex}][break_time]" value="${breakTime}" required>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="red-button">Remove Break</button>
            `;
            
            breaksContainer.appendChild(breakDiv);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('editTimeRecordModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>