<?php
// Script to check table columns
require_once 'config.php';

echo "Checking table structures...\n\n";

// Check if breaks table exists and get its columns
$breaks_exists = $db->query("SHOW TABLES LIKE 'breaks'")->num_rows > 0;
if ($breaks_exists) {
    echo "BREAKS TABLE EXISTS\n";
    echo "==================\n";
    $result = $db->query("DESCRIBE breaks");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "breaks table does not exist\n";
}

echo "\n";

// Check if break_records table exists and get its columns
$break_records_exists = $db->query("SHOW TABLES LIKE 'break_records'")->num_rows > 0;
if ($break_records_exists) {
    echo "BREAK_RECORDS TABLE EXISTS\n";
    echo "=========================\n";
    $result = $db->query("DESCRIBE break_records");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "break_records table does not exist\n";
}

echo "\n";

// Check time_records table structure
echo "TIME_RECORDS TABLE STRUCTURE\n";
echo "===========================\n";
$result = $db->query("DESCRIBE time_records");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?> 