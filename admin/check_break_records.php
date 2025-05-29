<?php
// Script to check the break_records table schema
require_once '../config.php';

// Set timezone
date_default_timezone_set('America/New_York');

echo "<h1>Break Records Table Check</h1>";
echo "<pre>";

// Connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if break_records table exists
$result = $db->query("SHOW TABLES LIKE 'break_records'");
if ($result->num_rows > 0) {
    echo "break_records table exists.\n\n";

    // Show table structure
    echo "Table structure:\n";
    $result = $db->query("DESCRIBE break_records");
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")" . 
             ($row['Null'] === 'NO' ? ' NOT NULL' : '') . 
             ($row['Key'] === 'PRI' ? ' PRIMARY KEY' : '') . "\n";
    }
    
    // Show sample data
    echo "\nSample data (first 5 records):\n";
    $result = $db->query("SELECT * FROM break_records LIMIT 5");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "  Record ID: " . $row['id'] . "\n";
            foreach ($row as $key => $value) {
                if ($key !== 'id') {
                    echo "    - " . $key . ": " . ($value ?: 'NULL') . "\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "  No records found.\n";
    }
    
} else {
    echo "break_records table does not exist.\n";
    
    // Check if breaks table exists instead
    $result = $db->query("SHOW TABLES LIKE 'breaks'");
    if ($result->num_rows > 0) {
        echo "\nbreaks table exists instead.\n\n";
        
        // Show table structure
        echo "Table structure:\n";
        $result = $db->query("DESCRIBE breaks");
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")" . 
                 ($row['Null'] === 'NO' ? ' NOT NULL' : '') . 
                 ($row['Key'] === 'PRI' ? ' PRIMARY KEY' : '') . "\n";
        }
        
        // Show sample data
        echo "\nSample data (first 5 records):\n";
        $result = $db->query("SELECT * FROM breaks LIMIT 5");
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "  Record ID: " . $row['id'] . "\n";
                foreach ($row as $key => $value) {
                    if ($key !== 'id') {
                        echo "    - " . $key . ": " . ($value ?: 'NULL') . "\n";
                    }
                }
                echo "\n";
            }
        } else {
            echo "  No records found.\n";
        }
    } else {
        echo "\nbreaks table also does not exist.\n";
    }
}

$db->close();
echo "</pre>";
?> 