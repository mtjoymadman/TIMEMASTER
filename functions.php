<?php
require_once 'config.php';

function clockIn($username) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    $stmt = $db->prepare("INSERT INTO time_records (username, clock_in) VALUES (?, NOW())");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    sendNotification($username, "clocked in");
    return true;
}

function clockOut($username) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    $stmt = $db->prepare("UPDATE time_records SET clock_out = NOW() WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    checkAutoBreak($username);
    sendNotification($username, "clocked out");
    return true;
}

function breakIn($username) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    $stmt = $db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $time_record_id = $result['id'];
        $stmt = $db->prepare("INSERT INTO breaks (time_record_id, break_in) VALUES (?, NOW())");
        $stmt->bind_param("i", $time_record_id);
        $stmt->execute();
        sendNotification($username, "started break");
        return true;
    }
    return false;
}

function breakOut($username) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    $stmt = $db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $time_record_id = $result['id'];
        $stmt = $db->prepare("UPDATE breaks SET break_out = NOW(), break_time = TIMESTAMPDIFF(MINUTE, break_in, NOW()) WHERE time_record_id = ? AND break_out IS NULL");
        $stmt->bind_param("i", $time_record_id);
        $stmt->execute();
        sendNotification($username, "ended break");
        return true;
    }
    return false;
}

function checkAutoBreak($username) {
    global $db;
    $stmt = $db->prepare("SELECT flag_auto_break, clock_in FROM employees e JOIN time_records t ON e.username = t.username WHERE t.username = ? AND t.clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['flag_auto_break']) { // Fix: Added null check
        $hours = (time() - strtotime($result['clock_in'])) / 3600;
        if ($hours > 8) {
            $time_record_id = $db->query("SELECT id FROM time_records WHERE username = '$username' AND clock_out = NOW()")->fetch_assoc()['id'];
            $db->query("INSERT INTO breaks (time_record_id, break_in, break_out, break_time) VALUES ($time_record_id, DATE_SUB(NOW(), INTERVAL 30 MINUTE), NOW(), 30)");
        }
    }
}

function getShiftDuration($username) {
    global $db;
    $stmt = $db->prepare("SELECT clock_in FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        return (time() - strtotime($result['clock_in'])) / 3600;
    }
    return 0;
}

function getTimeRecords($username, $date = null) {
    global $db;
    $query = "SELECT t.*, b.id AS break_id, b.break_in, b.break_out, b.break_time 
              FROM time_records t 
              LEFT JOIN breaks b ON t.id = b.time_record_id 
              WHERE t.username = ?";
    if ($date) {
        $query .= " AND DATE(t.clock_in) = '$date'";
    }
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        $stmt->execute();
    }
}

function modifyEmployee($old_username, $new_username, $flag_auto_break, $roles, $suspended) {
    global $db;
    $valid_roles = ['employee', 'admin', 'baby admin', 'driver', 'yardman', 'office'];
    $roles = array_intersect((array)$roles, $valid_roles);
    if (!in_array('employee', $roles)) $roles[] = 'employee';
    $role_string = implode(',', array_unique($roles));
    $stmt = $db->prepare("UPDATE employees SET username = ?, flag_auto_break = ?, role = ?, suspended = ? WHERE username = ?");
    $stmt->bind_param("sisis", $new_username, $flag_auto_break, $role_string, $suspended, $old_username);
    $stmt->execute();
}

function deleteEmployee($username) {
    global $db;
    $stmt = $db->prepare("DELETE FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}

function suspendEmployee($username) {
    global $db;
    $stmt = $db->prepare("UPDATE employees SET suspended = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}

function isSuspended($username) {
    global $db;
    $stmt = $db->prepare("SELECT suspended FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['suspended'] == 1 : false; // Fix: Null check
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

function getAllEmployees() {
    global $db;
    $query = "SELECT username FROM employees";
    $result = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    return $result ? array_column($result, 'username') : []; // Fix: Null check
}

function sendNotification($username, $action) {
    global $db;
    $stmt = $db->prepare("SELECT flag_auto_break FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['flag_auto_break']) { // Fix: Null check
        $message = "$username has $action at " . date('Y-m-d H:i:s');
        exec("python python/send_notification.py " . escapeshellarg($message) . " " . escapeshellarg(NOTIFY_EMAIL));
    }
}

function getWeeklyRecords() {
    global $db;
    $start_of_week = date('Y-m-d 00:00:00', strtotime('sunday this week'));
    $query = "SELECT t.*, e.username, e.role, b.id AS break_id, b.break_in, b.break_out, b.break_time 
              FROM time_records t 
              JOIN employees e ON t.username = e.username 
              LEFT JOIN breaks b ON t.id = b.time_record_id 
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
              LEFT JOIN breaks b ON t.id = b.time_record_id 
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
              LEFT JOIN breaks b ON t.id = b.time_record_id
              WHERE t.clock_in >= ? AND t.clock_in <= ?
              GROUP BY t.username, DATE(t.clock_in)
              ORDER BY t.username, work_date";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $start_of_week, $end_of_week);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// New functions added below

function addExternalTimeRecord($username, $start_time, $end_time, $reason) {
    global $db;
    if (isSuspended($username) || !hasRole($username, 'employee')) return false;
    $stmt = $db->prepare("INSERT INTO external_time_records (username, start_time, end_time, reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $start_time, $end_time, $reason);
    $stmt->execute();
    return true;
}

function getExternalTimeRecords($username, $date = null) {
    global $db;
    $query = "SELECT * FROM external_time_records WHERE username = ?";
    if ($date) {
        $query .= " AND DATE(start_time) = ?";
    }
    $query .= " ORDER BY start_time DESC";
    $stmt = $db->prepare($query);
    if ($date) {
        $stmt->bind_param("ss", $username, $date);
    } else {
        $stmt->bind_param("s", $username);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function hasRole($username, $role) {
    global $db;
    $stmt = $db->prepare("SELECT role FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if (!$result) return false; // Fix: Null check
    $roles = explode(',', $result['role']);
    return in_array($role, $roles);
}

function canManageEmployees($username) {
    return hasRole($username, 'admin') || hasRole($username, 'baby admin');
}
?>