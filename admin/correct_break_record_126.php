<?php
// Script to correct break record association for ID 126
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

// Update break record ID 126 to associate with time record ID 202
$stmt = $time_db->prepare("UPDATE breaks SET time_record_id = 202 WHERE id = 126");
if ($stmt->execute()) {
    echo 'Break record ID 126 successfully updated to associate with time record ID 202.';
} else {
    echo 'Failed to update break record: ' . $stmt->error;
}

$time_db->close();
?> 