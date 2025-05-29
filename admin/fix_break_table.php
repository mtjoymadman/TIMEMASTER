<?php
require_once '../config.php';

// Connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if break_records table exists
$result = $db->query("SHOW TABLES LIKE 'break_records'");
$break_records_exists = $result->num_rows > 0;

// Check if breaks table exists
$result = $db->query("SHOW TABLES LIKE 'breaks'");
$breaks_exists = $result->num_rows > 0;

if ($break_records_exists) {
    // Check if break_records has the old column names
    $result = $db->query("SHOW COLUMNS FROM break_records LIKE 'break_start'");
    $has_break_start = $result->num_rows > 0;
    
    if ($has_break_start) {
        // Rename columns in break_records
        $db->query("ALTER TABLE break_records CHANGE COLUMN break_start break_in DATETIME NOT NULL");
        $db->query("ALTER TABLE break_records CHANGE COLUMN break_end break_out DATETIME DEFAULT NULL");
        $db->query("ALTER TABLE break_records CHANGE COLUMN record_id time_record_id INT NOT NULL");
        echo "Renamed columns in break_records table\n";
    }
} elseif (!$breaks_exists) {
    // Create breaks table if neither exists
    $db->query("CREATE TABLE breaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time_record_id INT NOT NULL,
        break_in DATETIME NOT NULL,
        break_out DATETIME DEFAULT NULL,
        break_time INT DEFAULT NULL,
        FOREIGN KEY (time_record_id) REFERENCES time_records(id) ON DELETE CASCADE
    )");
    echo "Created breaks table\n";
}

$db->close();
echo "Done\n";
?> 