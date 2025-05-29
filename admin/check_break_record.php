<?php
// Script to check break record association
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

// Check break record ID 125
$stmt = $time_db->prepare("SELECT time_record_id FROM breaks WHERE id = 125");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo 'Break record ID 125 is associated with time record ID: ' . $row['time_record_id'];
} else {
    echo 'Break record ID 125 not found in the database.';
}

$time_db->close();
?> 