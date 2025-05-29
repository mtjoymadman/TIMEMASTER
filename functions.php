<?php
date_default_timezone_set('America/New_York'); // Force EDT (UTC-4)

// Only include config.php if it hasn't been included already
if (!defined('TIMEMASTER_CONFIG_LOADED')) {
    require_once __DIR__ . '/config.php';
}

// Make database connections available globally
global $time_db, $grok_db;

function getEdtTime($time = null) {
    if ($time === null) {
        $dt = new DateTime('now', new DateTimeZone('America/New_York'));
        // Always force America/New_York timezone regardless of server settings
        return $dt;
    }
    $dt = new DateTime($time, new DateTimeZone('America/New_York'));
    // No timezone conversion, always use America/New_York
    return $dt;
}

function getEmployee($username) {
    global $grok_db;
    $stmt = $grok_db->prepare("SELECT * FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function hasRole($username, $role) {
    global $grok_db;
    if (!$grok_db) {
        error_log("Database connection not initialized in hasRole()");
        return false;
    }
    $employee = getEmployee($username);
    if (!$employee) return false;
    return $employee['role'] === $role || strpos($employee['role'], $role) !== false;
}

function clockIn($username) {
    global $time_db, $grok_db;
    
    // First verify employee exists in GROK
    $stmt = $grok_db->prepare("SELECT id FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) return false;

    // Check if already clocked in using TIME db
    $stmt = $time_db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return false;

    // Clock in using TIME db
    $stmt = $time_db->prepare("INSERT INTO time_records (username, clock_in) VALUES (?, NOW())");
    $stmt->bind_param("s", $username);
    $success = $stmt->execute();
    
    if ($success) {
        sendNotification("Employee $username clocked in");
    }
    
    return $success;
}

function clockOut($username) {
    global $time_db;
    
    // Get current time record from TIME db
    $stmt = $time_db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;

    $time_record = $result->fetch_assoc();

    // Clock out using TIME db
    $stmt = $time_db->prepare("UPDATE time_records SET clock_out = NOW() WHERE id = ?");
    $stmt->bind_param("i", $time_record['id']);
    $success = $stmt->execute();
    
    if ($success) {
        sendNotification("Employee $username clocked out");
    }
    
    return $success;
}

function breakIn($username) {
    global $time_db;
    
    // Get current time record from TIME db
    $stmt = $time_db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;

    $time_record = $result->fetch_assoc();

    // Check if already on break using TIME db
    $stmt = $time_db->prepare("SELECT id FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
    $stmt->bind_param("i", $time_record['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return false;

    // Start break using TIME db
    $stmt = $time_db->prepare("INSERT INTO break_records (time_record_id, break_in) VALUES (?, NOW())");
    $stmt->bind_param("i", $time_record['id']);
    $success = $stmt->execute();
    
    if ($success) {
        sendNotification("Employee $username started break");
    }
    
    return $success;
}

function breakOut($username) {
    global $time_db;
    
    // Get current time record from TIME db
    $stmt = $time_db->prepare("SELECT tr.id 
        FROM time_records tr 
        JOIN break_records br ON tr.id = br.time_record_id 
        WHERE tr.username = ? AND tr.clock_out IS NULL AND br.break_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;

    $time_record = $result->fetch_assoc();

    // End break using TIME db
    $stmt = $time_db->prepare("UPDATE break_records SET break_out = NOW() 
        WHERE time_record_id = ? AND break_out IS NULL");
    $stmt->bind_param("i", $time_record['id']);
    $success = $stmt->execute();
    
    if ($success) {
        sendNotification("Employee $username ended break");
    }
    
    return $success;
}

function getCurrentTimeRecord($username) {
    global $time_db;
    
    $stmt = $time_db->prepare("
        SELECT tr.*, 
               br.id as break_id,
               br.break_in,
               br.break_out
        FROM time_records tr 
        LEFT JOIN break_records br ON tr.id = br.time_record_id 
            AND br.break_out IS NULL
        WHERE tr.username = ? 
        AND tr.clock_out IS NULL 
        ORDER BY tr.clock_in DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getAllEmployees() {
    global $grok_db;
    $result = $grok_db->query("SELECT * FROM employees ORDER BY username");
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    return $employees;
}

function getTimeRecords($username, $start_date = null, $end_date = null) {
    global $time_db;
    
    $query = "SELECT * FROM time_records WHERE username = ?";
    $params = [$username];
    $types = "s";
    
    if ($start_date) {
        $query .= " AND DATE(clock_in) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if ($end_date) {
        $query .= " AND DATE(clock_in) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    $query .= " ORDER BY clock_in DESC";
    
    $stmt = $time_db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    return $records;
}

function calculateTotalHours($time_record) {
    if (!$time_record['clock_in']) return 0;
    
    $clock_in = new DateTime($time_record['clock_in']);
    $clock_out = $time_record['clock_out'] ? new DateTime($time_record['clock_out']) : new DateTime();
    
    $total_seconds = $clock_out->getTimestamp() - $clock_in->getTimestamp();
    
    // Subtract break time if any
    if ($time_record['break_in'] && $time_record['break_out']) {
        $break_in = new DateTime($time_record['break_in']);
        $break_out = new DateTime($time_record['break_out']);
        $break_seconds = $break_out->getTimestamp() - $break_in->getTimestamp();
        $total_seconds -= $break_seconds;
    } elseif ($time_record['break_in']) {
        // If break is in progress, subtract time since break started
        $break_in = new DateTime($time_record['break_in']);
        $break_seconds = (new DateTime())->getTimestamp() - $break_in->getTimestamp();
        $total_seconds -= $break_seconds;
    }
    
    return round($total_seconds / 3600, 2);
}

function addEmployee($username, $flag_auto_break = 0, $roles = ['employee'], $suspended = 0) {
    global $db;
    $valid_roles = ['employee', 'admin', 'baby admin', 'driver', 'yardman', 'office'];
    $roles = array_intersect((array)$roles, $valid_roles); // Filter invalid roles
    if (!in_array('employee', $roles)) $roles[] = 'employee'; // Enforce employee role
    $role_string = implode(',', array_unique($roles)); // Convert to string
    $stmt = $db->prepare("SELECT username FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $stmt = $db->prepare("INSERT INTO employees (username, flag_auto_break, role, suspended) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sisi", $username, $flag_auto_break, $role_string, $suspended);
        $result = $stmt->execute();
        // Send notification when employee is added
        sendNotification("ADMIN", "added employee $username with roles: $role_string");
        return $result;
    }
    return false; // Employee already exists
}

function modifyEmployee($old_username, $new_username, $flag_auto_break, $roles, $suspended) {
    global $db;
    try {
        // Validate inputs
        if (empty($old_username) || empty($new_username)) {
            error_log("modifyEmployee: Empty username provided");
            return false;
        }

        // Ensure roles is an array
        if (!is_array($roles)) {
            $roles = ['employee'];
        }

        // Validate roles
        $valid_roles = ['employee', 'admin', 'baby admin', 'driver', 'yardman', 'office'];
        $roles = array_values(array_intersect($roles, $valid_roles));
        if (!in_array('employee', $roles)) {
            $roles[] = 'employee';
        }
        $role_string = implode(',', array_unique($roles));

        // Check if new username already exists (excluding the old username)
        $stmt = $db->prepare("SELECT username FROM employees WHERE username = ? AND username != ?");
        if (!$stmt) {
            error_log("modifyEmployee: Prepare failed for username check: " . $db->error);
            return false;
        }
        $stmt->bind_param("ss", $new_username, $old_username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            error_log("modifyEmployee: Username already exists: $new_username");
            return false;
        }

        // Update the employee
        $stmt = $db->prepare("UPDATE employees SET username = ?, flag_auto_break = ?, role = ?, suspended = ? WHERE username = ?");
        if (!$stmt) {
            error_log("modifyEmployee: Prepare failed for update: " . $db->error);
            return false;
        }

        $stmt->bind_param("sisis", $new_username, $flag_auto_break, $role_string, $suspended, $old_username);
        if (!$stmt->execute()) {
            error_log("modifyEmployee: Execute failed: " . $stmt->error);
            return false;
        }

        if ($stmt->affected_rows === 0) {
            error_log("modifyEmployee: No rows affected for username: $old_username");
            return false;
        }

        // Send notification when employee is modified
        $changed_msg = $old_username !== $new_username ? "renamed from $old_username to $new_username" : "updated";
        sendNotification("ADMIN", "employee $new_username $changed_msg with roles: $role_string");
        
        return true;
    } catch (Exception $e) {
        error_log("modifyEmployee: Exception: " . $e->getMessage());
        return false;
    }
}

function deleteEmployee($username) {
    global $db;
    $stmt = $db->prepare("DELETE FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    // Send notification when employee is deleted
    sendNotification("ADMIN", "deleted employee $username");
}

function suspendEmployee($username) {
    global $db;
    try {
        // First get current suspension status
        $stmt = $db->prepare("SELECT suspended FROM employees WHERE username = ?");
        if (!$stmt) {
            error_log("suspendEmployee: Prepare failed for status check: " . $db->error);
            return false;
        }
        
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            error_log("suspendEmployee: Execute failed for status check: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) {
            error_log("suspendEmployee: Employee not found: $username");
            return false;
        }
        
        // Toggle the suspension status
        $new_status = $result['suspended'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE employees SET suspended = ? WHERE username = ?");
        if (!$stmt) {
            error_log("suspendEmployee: Prepare failed for update: " . $db->error);
            return false;
        }
        
        $stmt->bind_param("is", $new_status, $username);
        if (!$stmt->execute()) {
            error_log("suspendEmployee: Execute failed for update: " . $stmt->error);
            return false;
        }
        
        // Send notification when employee suspension status changes
        $status_msg = $new_status ? "suspended" : "unsuspended";
        sendNotification("ADMIN", "$status_msg employee $username");
        
        return true;
    } catch (Exception $e) {
        error_log("suspendEmployee: Exception: " . $e->getMessage());
        return false;
    }
}

function isSuspended($username) {
    global $grok_db;
    $stmt = $grok_db->prepare("SELECT suspended FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['suspended'] == 1;
}

function getEmployeesOnClock() {
    global $db;
    $query = "SELECT DISTINCT username FROM time_records WHERE clock_out IS NULL";
    $result = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    return $result ? array_column($result, 'username') : []; // Fix: Null check
}

function modifyTimeRecord($id, $clock_in, $clock_out, $breaks) {
    global $db;
    $stmt = $db->prepare("UPDATE time_records SET clock_in = ?, clock_out = ? WHERE id = ?");
    $stmt->bind_param("ssi", $clock_in, $clock_out, $id);
    $stmt->execute();

    foreach ($breaks as $break) {
        if ($break['break_id']) {
            $stmt = $db->prepare("UPDATE breaks SET break_in = ?, break_out = ?, break_time = ? WHERE id = ?");
            $stmt->bind_param("ssii", $break['break_in'], $break['break_out'], $break['break_time'], $break['break_id']);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT INTO breaks (time_record_id, break_in, break_out, break_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $id, $break['break_in'], $break['break_out'], $break['break_time']);
            $stmt->execute();
        }
    }
}

function getWeeklyRecords() {
    global $db;
    $start_of_week = date('Y-m-d 00:00:00', strtotime('sunday this week'));
    $query = "SELECT t.*, e.username, e.role, b.id AS break_id, b.break_in, b.break_out, b.break_time 
              FROM time_records t 
              JOIN employees e ON t.username = e.username 
              LEFT JOIN break_records b ON t.id = b.time_record_id 
              WHERE t.clock_in >= ? 
              ORDER BY t.clock_in DESC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $start_of_week);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getEmployeeWeeklyRecords($username) {
    global $db;
    $start_of_week = date('Y-m-d 00:00:00', strtotime('sunday this week'));
    $query = "SELECT t.*, e.username, e.role, b.id AS break_id, b.break_in, b.break_out, b.break_time 
              FROM time_records t 
              JOIN employees e ON t.username = e.username 
              LEFT JOIN break_records b ON t.id = b.time_record_id 
              WHERE t.username = ? AND t.clock_in >= ? 
              ORDER BY t.clock_in DESC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $username, $start_of_week);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function addHolidayPay($username, $date) {
    global $db;
    $clock_in = "$date 00:00:00";
    $clock_out = "$date 08:00:00";
    $stmt = $db->prepare("INSERT INTO time_records (username, clock_in, clock_out) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $clock_in, $clock_out);
    $stmt->execute();
}

function getWeeklySummary() {
    global $db;
    $start_of_week = date('Y-m-d 00:00:00', strtotime('sunday this week'));
    $end_of_week = date('Y-m-d 23:59:59', strtotime('saturday this week'));
    $query = "SELECT 
                t.username,
                DATE(t.clock_in) AS work_date,
                SUM(TIMESTAMPDIFF(HOUR, t.clock_in, t.clock_out)) AS hours_worked,
                COUNT(b.id) AS breaks_taken,
                SUM(b.break_time) AS total_break_time
              FROM time_records t
              LEFT JOIN break_records b ON t.id = b.time_record_id
              WHERE t.clock_in >= ? AND t.clock_in <= ?
              GROUP BY t.username, DATE(t.clock_in)
              ORDER BY t.username, work_date";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $start_of_week, $end_of_week);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function addExternalTimeRecord($username, $start_time, $end_time, $reason) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    
    // Ensure times are in Eastern Time format
    $start = new DateTime($start_time, new DateTimeZone('America/New_York'));
    $end = new DateTime($end_time, new DateTimeZone('America/New_York'));
    
    $formatted_start = $start->format('Y-m-d H:i:s');
    $formatted_end = $end->format('Y-m-d H:i:s');
    
    $stmt = $db->prepare("INSERT INTO external_time_records (username, start_time, end_time, reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $formatted_start, $formatted_end, $reason);
    $stmt->execute();
    return true;
}

function getExternalTimeRecords($username, $date = null) {
    global $time_db;
    
    // Check if database connection exists
    if (!isset($time_db) || !$time_db) {
        // Try to establish connection if it doesn't exist
        $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
        if ($time_db->connect_error) {
            error_log("Time database connection failed: " . $time_db->connect_error);
            return [];
        }
    }
    
    $query = "SELECT * FROM external_time_records WHERE username = ?";
    if ($date) {
        $query .= " AND DATE(start_time) = ?";
    }
    $query .= " ORDER BY start_time DESC";
    
    $stmt = $time_db->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $time_db->error);
        return [];
    }
    
    if ($date) {
        $stmt->bind_param("ss", $username, $date);
    } else {
        $stmt->bind_param("s", $username);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Get result failed: " . $stmt->error);
        return [];
    }
    
    $records = $result->fetch_all(MYSQLI_ASSOC);
    
    // Ensure all timestamps are interpreted as America/New_York
    foreach ($records as &$record) {
        // Always treat the database timestamps as being in Eastern Time
        $start_time = new DateTime($record['start_time'], new DateTimeZone('America/New_York'));
        $end_time = new DateTime($record['end_time'], new DateTimeZone('America/New_York'));
        
        // Store formatted times (always in Eastern Time)
        $record['formatted_date'] = $start_time->format('m/d/Y');
        $record['formatted_start'] = $start_time->format('h:i A') . ' ET';
        $record['formatted_end'] = $end_time->format('h:i A') . ' ET';
        
        // Calculate duration
        $duration = $end_time->getTimestamp() - $start_time->getTimestamp();
        $record['duration_hours'] = floor($duration / 3600);
        $record['duration_minutes'] = floor(($duration % 3600) / 60);
        $record['formatted_duration'] = $record['duration_hours'] . 'h ' . $record['duration_minutes'] . 'm';
    }
    
    return $records;
}

function canManageEmployees($username) {
    return hasRole($username, 'admin') || hasRole($username, 'baby admin');
}

function getCurrentStatus($username) {
    global $time_db;
    
    // Check if user is clocked in
    $stmt = $time_db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $time_record_id = $row['id'];
        
        // Check if currently on break
        $stmt = $time_db->prepare("
            SELECT br.id 
            FROM break_records br 
            WHERE br.time_record_id = ? 
            AND br.break_out IS NULL
        ");
        $stmt->bind_param("i", $time_record_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return "On Break";
        }
        return "Clocked In";
    }
    return "Clocked Out";
}

function getHoursWorked($username) {
    global $time_db;
    
    // Check if database connection exists
    if (!isset($time_db) || !$time_db) {
        // Try to establish connection if it doesn't exist
        $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
        if ($time_db->connect_error) {
            error_log("Time database connection failed: " . $time_db->connect_error);
            return 0;
        }
    }
    
    $stmt = $time_db->prepare("SELECT clock_in FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
    if (!$stmt) {
        error_log("Prepare failed: " . $time_db->error);
        return 0;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return 0;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Get result failed: " . $stmt->error);
        return 0;
    }
    
    $row = $result->fetch_assoc();
    if (!$row) {
        return 0;
    }
    
    $clock_in = getEdtTime($row['clock_in']);
    $now = getEdtTime();
    return ($now->getTimestamp() - $clock_in->getTimestamp()) / 3600;
}

function formatTime($time) {
    if (!$time) return '-';
    
    // Always create the DateTime object with the America/New_York timezone
    // This ensures consistent interpretation of timestamps
    $dt = new DateTime($time, new DateTimeZone('America/New_York'));
    
    // Format with Eastern Time and add the ET indicator
    return $dt->format('h:i A') . ' ET';
}

function formatDate($date) {
    if (!$date) return '-';
    
    // Always create the DateTime object with the America/New_York timezone
    // This ensures consistent interpretation of timestamps
    $dt = new DateTime($date, new DateTimeZone('America/New_York'));
    
    // Format with Eastern Time
    return $dt->format('m/d/Y');
}

function authenticateUser($username, $password) {
    global $grok_db;
    
    $stmt = $grok_db->prepare("SELECT username FROM employees WHERE username = ?");
    if (!$stmt) {
        error_log("Error preparing statement: " . $grok_db->error);
        return false;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function clockOutAllEmployees($admin_username) {
    global $db;
    if (!hasRole($admin_username, 'admin')) return false;
    
    // Get all active time records
    $stmt = $db->prepare("SELECT id, username FROM time_records WHERE clock_out IS NULL");
    $stmt->execute();
    $active_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $now = getEdtTime()->format('Y-m-d H:i:s');
    $clocked_out_count = 0;
    
    // Begin transaction
    $db->begin_transaction();
    
    try {
        // Update all active time records
        $stmt = $db->prepare("UPDATE time_records SET clock_out = ? WHERE clock_out IS NULL");
        $stmt->bind_param("s", $now);
        $stmt->execute();
        $clocked_out_count = $stmt->affected_rows;
        
        // Log the action
        foreach ($active_records as $record) {
            sendNotification($record['username'], "clocked out by admin");
        }
        
        $db->commit();
        return $clocked_out_count;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error clocking out all employees: " . $e->getMessage());
        return false;
    }
}

function getCurrentEasternTime() {
    // Always return Eastern Time, never allow conversion
    return new DateTime('now', new DateTimeZone('America/New_York'));
}

function formatEasternTime($time) {
    if (!$time) return '-';
    
    // Parse the timestamp
    $dt = new DateTime($time);
    
    // Check if this is before March 24, 2024
    $march24 = new DateTime('2024-03-24 00:00:00');
    
    if ($dt < $march24) {
        // For older timestamps, directly create a DateTime with Eastern Time
        // This assumes the DB timestamps for older records are already in Eastern Time
        $dt = new DateTime($time, new DateTimeZone('America/New_York'));
    } else {
        // For newer timestamps (March 24 and later), convert from UTC to Eastern
        $dt->setTimezone(new DateTimeZone('America/New_York'));
    }
    
    return $dt->format('h:i A');
}

function formatEasternDateTime($datetime) {
    if (!$datetime) return '-';
    
    // Parse the timestamp
    $dt = new DateTime($datetime);
    
    // Check if this is before March 24, 2024
    $march24 = new DateTime('2024-03-24 00:00:00');
    
    if ($dt < $march24) {
        // For older timestamps, directly create a DateTime with Eastern Time
        // This assumes the DB timestamps for older records are already in Eastern Time
        $dt = new DateTime($datetime, new DateTimeZone('America/New_York'));
    } else {
        // For newer timestamps (March 24 and later), convert from UTC to Eastern
        $dt->setTimezone(new DateTimeZone('America/New_York'));
    }
    
    return $dt->format('m/d/Y h:i A');
}

function formatEasternDate($date) {
    if (!$date) return '-';
    
    // Parse the timestamp
    $dt = new DateTime($date);
    
    // Check if this is before March 24, 2024
    $march24 = new DateTime('2024-03-24 00:00:00');
    
    if ($dt < $march24) {
        // For older timestamps, directly create a DateTime with Eastern Time
        // This assumes the DB timestamps for older records are already in Eastern Time
        $dt = new DateTime($date, new DateTimeZone('America/New_York'));
    } else {
        // For newer timestamps (March 24 and later), convert from UTC to Eastern
        $dt->setTimezone(new DateTimeZone('America/New_York'));
    }
    
    return $dt->format('m/d/Y');
}

function getEasternTimeForDB($time) {
    if (!$time) return null;
    
    // If time is a string, parse it with default timezone
    if (is_string($time)) {
        $dt = new DateTime($time);
        
        // Check if this is before March 24, 2024
        $march24 = new DateTime('2024-03-24 00:00:00');
        
        if ($dt < $march24) {
            // For older timestamps, directly create a DateTime with Eastern Time
            // This assumes the DB timestamps for older records are already in Eastern Time
            $dt = new DateTime($time, new DateTimeZone('America/New_York'));
        } else {
            // For newer timestamps (March 24 and later), convert from UTC to Eastern
            $dt->setTimezone(new DateTimeZone('America/New_York'));
        }
    } else {
        // If time is already a DateTime, just set the timezone
        $dt = $time;
        $dt->setTimezone(new DateTimeZone('America/New_York'));
    }
    
    return $dt->format('Y-m-d H:i:s');
}

function calculateDurationEastern($start, $end) {
    if (!$start || !$end) return 0;
    
    // Parse the start timestamp
    $start_dt = new DateTime($start);
    
    // Check if this is before March 24, 2024
    $march24 = new DateTime('2024-03-24 00:00:00');
    
    if ($start_dt < $march24) {
        // For older timestamps, directly create DateTimes with Eastern Time
        $start_dt = new DateTime($start, new DateTimeZone('America/New_York'));
        $end_dt = new DateTime($end, new DateTimeZone('America/New_York'));
    } else {
        // For newer timestamps (March 24 and later), convert from UTC to Eastern
        $start_dt->setTimezone(new DateTimeZone('America/New_York'));
        $end_dt = new DateTime($end);
        $end_dt->setTimezone(new DateTimeZone('America/New_York'));
    }
    
    $duration = $end_dt->getTimestamp() - $start_dt->getTimestamp();
    return $duration / 60; // Return minutes
}

function isEasternBusinessHours() {
    $now = getEdtTime();
    $hour = (int)$now->format('G');
    return $hour >= 8 && $hour < 17; // 8 AM to 5 PM Eastern Time
}

function sendNotification($message, $action = null) {
    // If we have both parameters, format the message (legacy support)
    if ($action !== null) {
        $message = "Employee $message $action";
    }
    
    error_log("NOTIFICATION: $message to " . NOTIFY_EMAIL);
    
    // Only proceed if NOTIFY_EMAIL is defined
    if (!defined('NOTIFY_EMAIL') || empty(NOTIFY_EMAIL)) {
        error_log("NOTIFICATION WARNING: NOTIFY_EMAIL is not defined or empty");
        return;
    }
    
    // Prepare HTML email body with proper formatting
    $body = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>TIMEMASTER Notification</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background-color: #c0392b; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; }
            .footer { font-size: 12px; color: #777; text-align: center; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>TIMEMASTER Notification</h1>
            </div>
            <div class="content">
                <p>' . $message . '</p>
            </div>
            <div class="footer">
                <p>This is an automated message from the TIMEMASTER system.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Debug log for all notifications for easier tracking
    error_log("NOTIFICATION ATTEMPT: Message: " . $message . ", Recipient: " . NOTIFY_EMAIL);
    
    try {
        // Make sure PHPMailer is available
        if (!file_exists(__DIR__ . '/lib/PHPMailer/src/PHPMailer.php')) {
            error_log("NOTIFICATION ERROR: PHPMailer not found at " . __DIR__ . '/lib/PHPMailer/src/PHPMailer.php');
            return;
        }
        
        // Include PHPMailer files
        require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rlstimeclock@gmail.com';
        $mail->Password = 'wsilgdzeouzremou';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Set from/to
        $mail->setFrom('rlstimeclock@gmail.com', 'TIMEMASTER');
        $mail->addAddress(NOTIFY_EMAIL);
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = "TIMEMASTER Alert: " . $message;
        $mail->Body = $body;
        
        // Send the email
        $result = $mail->send();
        
        if ($result) {
            error_log("NOTIFICATION SUCCESS: Email sent for message: " . $message);
        } else {
            error_log("NOTIFICATION FAILURE: Email send failed: " . $mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("NOTIFICATION EXCEPTION: " . $e->getMessage());
    }
}

?>