<?php
// test_endpoint.php - Simple test script to debug 500 error

// Set headers as early as possible
header('Content-Type: text/plain');

// Output initial debug message
echo 'Step 1: Script started\n';

// Start session if needed
session_start();
echo 'Step 2: Session started, ID: ' . session_id() . '\n';

// Output session data
echo 'Step 3: Session data: ' . print_r($_SESSION, true) . '\n';

// Output request data
echo 'Step 4: POST data: ' . print_r($_POST, true) . '\n';

// Return a simple success response
echo 'Step 5: Test endpoint reached successfully\n';

// Finalize with JSON response if possible
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Test endpoint reached successfully']);
?> 