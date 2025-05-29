<?php
// Test script to capture and log form submission data
error_log('Test Form Submission - POST data: ' . print_r($_POST, true));
error_log('Test Form Submission - REQUEST data: ' . print_r($_REQUEST, true));
error_log('Test Form Submission - Raw Input: ' . file_get_contents('php://input'));
error_log('Test Form Submission - Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Test Form Submission - Content Type: ' . (isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'not set'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form Submission</title>
</head>
<body>
    <h2>Test Form Submission</h2>
    <form method="post" action="test_form_submission.php" enctype="application/x-www-form-urlencoded">
        <div style="margin-bottom: 15px;">
            <label for="employee" style="display: inline-block; width: 120px;">Employee:</label>
            <input type="text" name="employee" id="employee" required>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="record_date" style="display: inline-block; width: 120px;">Date:</label>
            <input type="date" name="record_date" id="record_date" required>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="start_time" style="display: inline-block; width: 120px;">Start Time:</label>
            <input type="time" name="start_time" id="start_time" required>
        </div>
        <button type="submit">Submit Test</button>
    </form>
    <p>Check the server error log after submission to see if data was received.</p>
</body>
</html> 