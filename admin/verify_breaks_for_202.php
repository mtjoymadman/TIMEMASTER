<?php
// Script to verify break records for time record ID 202
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

// Query for break records associated with time record ID 202
echo '<h2>Break Records for Time Record ID 202</h2>';
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
    echo '<p>No break records found for time record ID 202 in the time database.</p>';
}

// Also check grok database for completeness
$grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
if ($grok_db->connect_error) {
    die('Grok database connection failed: ' . $grok_db->connect_error);
}

echo '<h2>Break Records for Time Record ID 202 in Grok Database</h2>';
$stmt = $grok_db->prepare("SELECT id, break_in, break_out FROM breaks WHERE time_record_id = 202");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo '<table border="1"><tr><th>ID</th><th>Break In</th><th>Break Out</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr><td>' . $row['id'] . '</td><td>' . $row['break_in'] . '</td><td>' . ($row['break_out'] ? $row['break_out'] : 'N/A') . '</td></tr>';
    }
    echo '</table>';
} else {
    echo '<p>No break records found for time record ID 202 in the grok database.</p>';
}

$time_db->close();
$grok_db->close();
?> 