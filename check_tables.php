<?php
require_once 'config.php';

// Connect to the database
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get a list of all tables
$tables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

echo "Tables in database:\n";
foreach ($tables as $table) {
    echo "- $table\n";
}

// Check if the breaks table exists
$breaks_exists = in_array('breaks', $tables);
echo "\nBreaks table exists: " . ($breaks_exists ? 'Yes' : 'No') . "\n";

// Check if the break_records table exists
$break_records_exists = in_array('break_records', $tables);
echo "Break_records table exists: " . ($break_records_exists ? 'Yes' : 'No') . "\n";

// Display structure of breaks table if it exists
if ($breaks_exists) {
    echo "\nStructure of breaks table:\n";
    $result = $db->query("DESCRIBE breaks");
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

$db->close();
?> 