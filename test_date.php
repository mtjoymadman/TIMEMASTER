<?php
// Date diagnostic script
echo "<html><body><pre>";
echo "========= DATE/TIMEZONE DIAGNOSTIC SCRIPT =========\n\n";

// Check default timezone setting
echo "Default timezone: " . date_default_timezone_get() . "\n";

// Set timezone to America/New_York
date_default_timezone_set('America/New_York');
echo "Set timezone to America/New_York\n";
echo "Current timezone: " . date_default_timezone_get() . "\n\n";

// Current date and time
echo "Current date/time using date(): " . date('Y-m-d H:i:s') . "\n";

// Using DateTime
$now = new DateTime('now', new DateTimeZone('America/New_York'));
echo "Current date/time using DateTime: " . $now->format('Y-m-d H:i:s') . "\n";
echo "Timezone offset: " . $now->format('P') . " hours\n\n";

// MySQL date/time (if a database connection is available)
if (file_exists('config.php')) {
    require_once 'config.php';
    if (isset($db) && $db instanceof mysqli) {
        try {
            // Get MySQL server time
            $result = $db->query("SELECT NOW() as now, @@system_time_zone as system_tz, @@session_time_zone as session_tz");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "MySQL server time: " . $row['now'] . "\n";
                echo "MySQL system timezone: " . $row['system_tz'] . "\n";
                echo "MySQL session timezone: " . $row['session_tz'] . "\n\n";
            }
        } catch (Exception $e) {
            echo "Error accessing MySQL time: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No database connection available\n";
    }
} else {
    echo "config.php not found, skipping MySQL tests\n";
}

// Test with specific date
$testDate = '2024-03-27 10:00:00';
echo "Test date: $testDate\n";

$testDt = new DateTime($testDate, new DateTimeZone('America/New_York'));
echo "Test date parsed with DateTime: " . $testDt->format('Y-m-d H:i:s P') . "\n\n";

// Yesterday and today dates
$yesterday = new DateTime('yesterday', new DateTimeZone('America/New_York'));
$today = new DateTime('today', new DateTimeZone('America/New_York'));

echo "Yesterday date: " . $yesterday->format('Y-m-d') . "\n";
echo "Today date: " . $today->format('Y-m-d') . "\n\n";

// Check if a date is equal to today
function isToday($dateString) {
    $today = new DateTime('today', new DateTimeZone('America/New_York'));
    $date = new DateTime($dateString, new DateTimeZone('America/New_York'));
    
    return $today->format('Y-m-d') === $date->format('Y-m-d');
}

$testDateToday = date('Y-m-d');
echo "Is '$testDateToday' today? " . (isToday($testDateToday) ? 'Yes' : 'No') . "\n";

$testDateYesterday = date('Y-m-d', strtotime('yesterday'));
echo "Is '$testDateYesterday' today? " . (isToday($testDateYesterday) ? 'Yes' : 'No') . "\n";

// Test database connection with break_records table
if (isset($db) && $db instanceof mysqli) {
    echo "\n========= DATABASE TABLE TESTS =========\n\n";
    
    try {
        // Check if the break_records table exists
        $result = $db->query("SHOW TABLES LIKE 'break_records'");
        if ($result && $result->num_rows > 0) {
            echo "break_records table exists.\n";
            
            // Get the most recent time records
            $result = $db->query("SELECT id, username, clock_in, clock_out FROM time_records ORDER BY id DESC LIMIT 5");
            
            if ($result && $result->num_rows > 0) {
                echo "\nMost recent time records:\n";
                echo str_pad("ID", 5) . " | " . str_pad("Username", 15) . " | " . str_pad("Clock In", 25) . " | " . str_pad("Clock Out", 25) . "\n";
                echo str_repeat("-", 80) . "\n";
                
                while ($row = $result->fetch_assoc()) {
                    $clockInDate = new DateTime($row['clock_in'], new DateTimeZone('America/New_York'));
                    $clockOutDate = $row['clock_out'] ? new DateTime($row['clock_out'], new DateTimeZone('America/New_York')) : 'NULL';
                    $clockOutFormatted = $clockOutDate !== 'NULL' ? $clockOutDate->format('Y-m-d H:i:s') : 'NULL';
                    
                    echo str_pad($row['id'], 5) . " | " . 
                         str_pad($row['username'], 15) . " | " . 
                         str_pad($clockInDate->format('Y-m-d H:i:s'), 25) . " | " . 
                         str_pad($clockOutFormatted, 25) . "\n";
                }
            } else {
                echo "No time records found.\n";
            }
            
            // Get the most recent break records
            $result = $db->query("SELECT b.id, b.time_record_id, b.break_in, b.break_out, t.username 
                                  FROM break_records b 
                                  JOIN time_records t ON b.time_record_id = t.id 
                                  ORDER BY b.id DESC LIMIT 5");
            
            if ($result && $result->num_rows > 0) {
                echo "\nMost recent break records:\n";
                echo str_pad("ID", 5) . " | " . str_pad("TR ID", 5) . " | " . str_pad("Username", 15) . " | " . 
                     str_pad("Break In", 25) . " | " . str_pad("Break Out", 25) . "\n";
                echo str_repeat("-", 90) . "\n";
                
                while ($row = $result->fetch_assoc()) {
                    $breakInDate = new DateTime($row['break_in'], new DateTimeZone('America/New_York'));
                    $breakOutDate = $row['break_out'] ? new DateTime($row['break_out'], new DateTimeZone('America/New_York')) : 'NULL';
                    $breakOutFormatted = $breakOutDate !== 'NULL' ? $breakOutDate->format('Y-m-d H:i:s') : 'NULL';
                    
                    echo str_pad($row['id'], 5) . " | " . 
                         str_pad($row['time_record_id'], 5) . " | " . 
                         str_pad($row['username'], 15) . " | " . 
                         str_pad($breakInDate->format('Y-m-d H:i:s'), 25) . " | " . 
                         str_pad($breakOutFormatted, 25) . "\n";
                }
            } else {
                echo "No break records found.\n";
            }
        } else {
            echo "break_records table does not exist.\n";
            
            // Check if the breaks table exists
            $result = $db->query("SHOW TABLES LIKE 'breaks'");
            if ($result && $result->num_rows > 0) {
                echo "breaks table exists instead.\n";
            } else {
                echo "breaks table does not exist either.\n";
            }
        }
    } catch (Exception $e) {
        echo "Error checking tables: " . $e->getMessage() . "\n";
    }
}

echo "\n========= END OF DIAGNOSTIC SCRIPT =========\n";
echo "</pre></body></html>";
?> 