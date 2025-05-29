<?php
// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
date_default_timezone_set('America/New_York');

// Security check - Only admins and baby admins can access
$logged_in_user = $_SESSION['username'] ?? '';
if (!hasRole($logged_in_user, 'admin') && !hasRole($logged_in_user, 'baby admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get username from the query string
$username = isset($_GET['username']) ? $_GET['username'] : $_SESSION['username'];

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

try {
    global $db;
    
    // Get all time records for the specified user
    $query = "
        SELECT t.id, t.clock_in, t.clock_out, t.notes,
               b.id as break_id, b.break_in, b.break_out
        FROM time_records t
        LEFT JOIN break_records b ON t.id = b.time_record_id
        WHERE t.username = ?
        ORDER BY t.clock_in DESC, b.break_in ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    $currentRecordId = null;
    $currentRecord = null;

    while ($row = $result->fetch_assoc()) {
        // If this is a new time record, start a new entry
        if ($currentRecordId !== $row['id']) {
            // Add the previous record to our array if it exists
            if ($currentRecord !== null) {
                $records[] = $currentRecord;
            }
            
            // Format times in Eastern Time
            $clockIn = new DateTime($row['clock_in'], new DateTimeZone('America/New_York'));
            $formattedClockIn = $clockIn->format('Y-m-d H:i:s');
            
            $formattedClockOut = null;
            if ($row['clock_out']) {
                $clockOut = new DateTime($row['clock_out'], new DateTimeZone('America/New_York'));
                $formattedClockOut = $clockOut->format('Y-m-d H:i:s');
            }
            
            // Start a new record
            $currentRecordId = $row['id'];
            $currentRecord = [
                'id' => $row['id'],
                'clock_in' => $formattedClockIn,
                'clock_out' => $formattedClockOut,
                'notes' => $row['notes'],
                'breaks' => []
            ];
        }
        
        // If this record has break information, add it to the current record
        if ($row['break_id']) {
            // Format break times in Eastern Time
            $breakIn = null;
            if ($row['break_in']) {
                $breakInDt = new DateTime($row['break_in'], new DateTimeZone('America/New_York'));
                $breakIn = $breakInDt->format('Y-m-d H:i:s');
            }
            
            $breakOut = null;
            if ($row['break_out']) {
                $breakOutDt = new DateTime($row['break_out'], new DateTimeZone('America/New_York'));
                $breakOut = $breakOutDt->format('Y-m-d H:i:s');
            }
            
            $currentRecord['breaks'][] = [
                'id' => $row['break_id'],
                'break_in' => $breakIn,
                'break_out' => $breakOut
            ];
        }
    }

    // Add the last record if it exists
    if ($currentRecord !== null) {
        $records[] = $currentRecord;
    }
    
    // Return the time records
    echo json_encode([
        'success' => true,
        'username' => $username,
        'records' => $records
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching employee time records: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 