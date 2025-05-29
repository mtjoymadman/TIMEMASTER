<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['username'] ?? '';
    if (!empty($username)) {
        if (breakIn($username)) {
            echo json_encode(['success' => true, 'message' => 'Break started successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to start break']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
    }
}

function breakIn($username) {
    global $db;
    if (isSuspended($username)) return false;
    if (!hasRole($username, 'employee') && !hasRole($username, 'admin')) return false;
    
    $stmt = $db->prepare("SELECT id FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $time_record_id = $result['id'];
        $now = getEdtTime()->format('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO break_records (time_record_id, break_in, auto_added) VALUES (?, ?, 0)");
        $stmt->bind_param("is", $time_record_id, $now);
        $stmt->execute();
        
        sendNotification($username, "started break");
        return true;
    }
    return false;
}
?> 