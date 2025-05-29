<?php
// Diagnostics script for time records
require_once 'config.php';
require_once 'functions.php';

echo "<html><body><pre>";
echo "========= TIMEZONE DIAGNOSTIC SCRIPT =========\n\n";

// Get current server timezone
echo "Default/Server timezone: " . date_default_timezone_get() . "\n";

// Set timezone to America/New_York (Eastern Time)
date_default_timezone_set('America/New_York');
echo "Set timezone to America/New_York\n";
echo "Current timezone: " . date_default_timezone_get() . "\n\n";

// Current date and time using date()
echo "Current date/time using date(): " . date('Y-m-d H:i:s') . "\n";

// Using DateTime class with Eastern Time
$now = new DateTime('now', new DateTimeZone('America/New_York'));
echo "Current date/time using DateTime (Eastern): " . $now->format('Y-m-d H:i:s') . "\n";
echo "Eastern timezone offset: " . $now->format('P') . " hours\n\n";

// Using DateTime with UTC
$utc_now = new DateTime('now', new DateTimeZone('UTC'));
echo "Current date/time in UTC: " . $utc_now->format('Y-m-d H:i:s') . "\n\n";

// Time difference checks between date() and DateTime
echo "Timestamp using time(): " . time() . "\n";
echo "Timestamp using DateTime: " . $now->getTimestamp() . "\n";

// Difference between UTC and Eastern Time
$diff_hours = ($now->getOffset() - $utc_now->getOffset()) / 3600;
echo "Hours difference between EDT and UTC: " . $diff_hours . "\n\n";

// Test problem date
$original_in = isset($_GET['date']) ? $_GET['date'] : '2024-03-26 08:00:00';
echo "Testing with sample date: " . $original_in . "\n";

// Create DateTime objects for testing
$date_original = new DateTime($original_in);
echo "Sample date parsed with system timezone: " . $date_original->format('Y-m-d H:i:s P') . "\n";

$date_eastern = new DateTime($original_in, new DateTimeZone('America/New_York'));
echo "Sample date parsed with Eastern timezone: " . $date_eastern->format('Y-m-d H:i:s P') . "\n";

// Test formatDate function
require_once 'functions.php';

echo "\nTesting formatDate() function:\n";
$formatted_date = formatDate($original_in);
echo "formatDate() result: " . $formatted_date . "\n";
echo "Today's date using formatDate(): " . formatDate(date('Y-m-d')) . "\n";

// Create today's date in Eastern Time
$today_eastern = new DateTime('today', new DateTimeZone('America/New_York'));
echo "Today using DateTime('today', ET): " . $today_eastern->format('Y-m-d') . "\n";

// Yesterday's date in Eastern Time
$yesterday_eastern = new DateTime('yesterday', new DateTimeZone('America/New_York'));
echo "Yesterday using DateTime('yesterday', ET): " . $yesterday_eastern->format('Y-m-d') . "\n\n";

// Test various other date strings
echo "Testing other date string formats:\n";
$test_dates = [
    'now',
    'today',
    'yesterday',
    '+1 day',
    '-1 day',
    'first day of this month',
    'last day of this month'
];

foreach ($test_dates as $test_date) {
    $dt = new DateTime($test_date, new DateTimeZone('America/New_York'));
    echo str_pad($test_date, 25) . ": " . $dt->format('Y-m-d H:i:s P') . "\n";
}

// Browser Time vs Server Time
echo "\nBrowser/JavaScript Time Detection:\n";
?>
<script>
const now = new Date();
document.write("Browser date (local): " + now.toLocaleDateString() + "<br>");
document.write("Browser time (local): " + now.toLocaleTimeString() + "<br>");
document.write("Browser timezone offset: " + (now.getTimezoneOffset() / -60) + " hours<br>");
document.write("Browser date (ISO): " + now.toISOString() + "<br>");
</script>
<?php

// Test problematic time record
echo "\n\nProblematic Time Record Check:\n";
if (isset($db) && $db instanceof mysqli) {
    try {
        $result = $db->query("SELECT * FROM time_records WHERE DATE(clock_in) = CURDATE() - INTERVAL 1 DAY LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $problematic_record = $result->fetch_assoc();
            echo "Found problematic record with ID: " . $problematic_record['id'] . "\n";
            echo "Username: " . $problematic_record['username'] . "\n";
            echo "Clock In (raw): " . $problematic_record['clock_in'] . "\n";
            
            // Parse with different methods
            $clock_in_dt = new DateTime($problematic_record['clock_in']);
            echo "Clock In parsed with system timezone: " . $clock_in_dt->format('Y-m-d H:i:s P') . "\n";
            
            $clock_in_et = new DateTime($problematic_record['clock_in'], new DateTimeZone('America/New_York'));
            echo "Clock In parsed with Eastern Time: " . $clock_in_et->format('Y-m-d H:i:s P') . "\n";
            
            echo "formatDate() result: " . formatDate($problematic_record['clock_in']) . "\n";
        } else {
            echo "No records from yesterday found.\n";
        }
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No database connection available.\n";
}

// Test getEdtTime function
echo "\nTesting getEdtTime() function:\n";
$edt_now = getEdtTime();
echo "getEdtTime('now'): " . $edt_now->format('Y-m-d H:i:s P') . "\n";

if (isset($problematic_record) && isset($problematic_record['clock_in'])) {
    $edt_record = getEdtTime($problematic_record['clock_in']);
    echo "getEdtTime(problematic record): " . $edt_record->format('Y-m-d H:i:s P') . "\n";
}

echo "\n========= END OF DIAGNOSTIC SCRIPT =========\n";
echo "</pre></body></html>";
?> 