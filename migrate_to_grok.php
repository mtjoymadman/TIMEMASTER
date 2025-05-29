<?php
// Connect to both databases
$time_db = new mysqli('localhost', 'salvageyard_time', '7361dead', 'salvageyard_time');
$grok_db = new mysqli('localhost', 'salvageyard_grok', '7361dead', 'salvageyard_grok');

if ($time_db->connect_error || $grok_db->connect_error) {
    die("Connection failed: " . $time_db->connect_error . " or " . $grok_db->connect_error);
}

// Set timezone
date_default_timezone_set('America/New_York');

// 1. Migrate employees
$result = $time_db->query("SELECT username, role, flag_auto_break, suspended FROM employees");
while ($row = $result->fetch_assoc()) {
    // Check if employee exists in grok
    $stmt = $grok_db->prepare("SELECT id FROM employees WHERE username = ?");
    $stmt->bind_param("s", $row['username']);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    
    if (!$exists) {
        // Insert new employee
        $stmt = $grok_db->prepare("INSERT INTO employees (username, role) VALUES (?, ?)");
        $stmt->bind_param("ss", $row['username'], $row['role']);
        $stmt->execute();
    }
}

// 2. Migrate time records
$result = $time_db->query("SELECT * FROM time_records");
while ($row = $result->fetch_assoc()) {
    // Get employee ID from grok
    $stmt = $grok_db->prepare("SELECT id FROM employees WHERE username = ?");
    $stmt->bind_param("s", $row['username']);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if ($employee) {
        // Insert time record
        $stmt = $grok_db->prepare("INSERT INTO timeclock (employee_id, clock_in, clock_out) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $employee['id'], $row['clock_in'], $row['clock_out']);
        $stmt->execute();
        $timeclock_id = $grok_db->insert_id;
        
        // 3. Migrate breaks
        $breaks = $time_db->query("SELECT * FROM break_records WHERE time_record_id = " . $row['id']);
        while ($break = $breaks->fetch_assoc()) {
            $stmt = $grok_db->prepare("UPDATE timeclock SET break_in = ?, break_out = ? WHERE id = ?");
            $stmt->bind_param("ssi", $break['break_in'], $break['break_out'], $timeclock_id);
            $stmt->execute();
        }
    }
}

echo "Migration completed successfully!";
?> 