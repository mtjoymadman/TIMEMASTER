<?php
// Script to clean up break records for time record ID 202
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

echo '<h2>Cleaning Up Break Records for Time Record ID 202</h2>';
// Delete break records with IDs 103 to 124 for time record ID 202
$stmt = $time_db->prepare("DELETE FROM breaks WHERE time_record_id = 202 AND id BETWEEN 103 AND 124");
if ($stmt->execute()) {
    echo '<p>Successfully deleted break records with IDs 103 to 124 for time record ID 202.</p>';
    echo '<p>Affected rows: ' . $stmt->affected_rows . '</p>';
} else {
    echo '<p>Failed to delete break records: ' . $stmt->error . '</p>';
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
    echo '<p>No break records found for time record ID 202 after cleanup.</p>';
}

$time_db->close();
?> 