<?php
// Script to update break record for time record ID 202
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['username']) || !hasRole($_SESSION['username'], 'admin')) {
    header('Location: ../login.php?redirect_reason=unauthorized');
    exit;
}

// Connect to time database
$time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($time_db->connect_error) {
    die('Time database connection failed: ' . $time_db->connect_error);
}

echo '<h2>Updating Break Record for Time Record ID 202</h2>';
// Update break ID 125 to change date to 2025-05-27
$stmt = $time_db->prepare("UPDATE breaks SET break_in = '2025-05-27 11:00:00', break_out = '2025-05-27 11:30:00' WHERE id = 125 AND time_record_id = 202");
if ($stmt->execute()) {
    echo '<p>Successfully updated break ID 125 for time record ID 202 to date 2025-05-27.</p>';
} else {
    echo '<p>Failed to update break ID 125: ' . $stmt->error . '</p>';
}

// Delete break ID 126 to ensure only one break
$stmt = $time_db->prepare("DELETE FROM breaks WHERE id = 126 AND time_record_id = 202");
if ($stmt->execute()) {
    echo '<p>Successfully deleted break ID 126 for time record ID 202.</p>';
    echo '<p>Affected rows: ' . $stmt->affected_rows . '</p>';
} else {
    echo '<p>Failed to delete break ID 126: ' . $stmt->error . '</p>';
}

// Verify remaining breaks for time record ID 202
echo '<h3>Remaining Break Records for Time Record ID 202</h3>';
$stmt = $time_db->prepare("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = 202");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo '<table border="1"><tr><th>ID</th><th>Break In</th><th>Break Out</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr><td>' . $row['id'] . '</td><td>' . $row['break_in'] . '</td><td>' . ($row['break_out'] ? $row['break_out'] : 'N/A') . '</td></tr>';
    }
    echo '</table>';
} else {
    echo '<p>No break records found for time record ID 202 after update.</p>';
}

$time_db->close();
?> 