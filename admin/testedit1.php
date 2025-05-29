<?php
// Disable all error display and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/testedit1_errors.log');

// Include required files with error handling
try {
    require_once '../config.php';
    require_once '../functions.php';
} catch (Exception $e) {
    error_log("Error including required files: " . $e->getMessage());
    die("Error loading required files. Please check the error log for details.");
}

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('America/New_York');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log file for debugging
function log_error($message) {
    error_log("TIMEMASTER: " . $message);
}

// Function to send JSON response
function send_json_response($success, $message = '', $error = '', $data = null) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    
    // Clean any HTML tags from messages
    $message = trim(strip_tags($message));
    $error = trim(strip_tags($error));
    
    // Create response array
    $response_array = [
        'success' => $success,
        'message' => $message,
        'error' => $error
    ];
    
    if ($data !== null) {
        $response_array['data'] = $data;
    }
    
    // Send JSON response
    echo json_encode($response_array);
    exit;
}

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    // Handle AJAX requests
    try {
        // Check if user is logged in
        if (!isset($_SESSION['username'])) {
            send_json_response(false, '', 'Not logged in');
        }

        // Get logged in user
        $admin_username = $_SESSION['username'];
        
        // Check if user has admin role using grok database
        if (!hasRole($admin_username, 'admin')) {
            send_json_response(false, '', 'Unauthorized');
        }

        // Handle specific AJAX actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_session':
                    // Get form data
                    $username = $_POST['username'] ?? '';
                    $time_record_id = intval($_POST['time_record_id'] ?? 0);
                    $clock_in = $_POST['clock_in'] ?? '';
                    $break_in = $_POST['break_in'] ?? '';
                    $break_out = $_POST['break_out'] ?? '';
                    $clock_out = $_POST['clock_out'] ?? '';
                    
                    // Log received data for debugging
                    error_log("Received data - Username: $username, Record ID: $time_record_id");
                    error_log("Times - Clock in: $clock_in, Break in: $break_in, Break out: $break_out, Clock out: $clock_out");
                    
                    // Validate required fields
                    if (empty($username) || $time_record_id <= 0) {
                        send_json_response(false, '', 'Missing required fields');
                    }
                    
                    try {
                        // Convert times to UTC for database storage
                        if (!empty($clock_in)) {
                            $dt = new DateTime($clock_in, new DateTimeZone('America/New_York'));
                            $dt->setTimezone(new DateTimeZone('UTC'));
                            $clock_in = $dt->format('Y-m-d H:i:s');
                        }
                        
                        if (!empty($break_in)) {
                            $dt = new DateTime($break_in, new DateTimeZone('America/New_York'));
                            $dt->setTimezone(new DateTimeZone('UTC'));
                            $break_in = $dt->format('Y-m-d H:i:s');
                        }
                        
                        if (!empty($break_out)) {
                            $dt = new DateTime($break_out, new DateTimeZone('America/New_York'));
                            $dt->setTimezone(new DateTimeZone('UTC'));
                            $break_out = $dt->format('Y-m-d H:i:s');
                        }
                        
                        if (!empty($clock_out)) {
                            $dt = new DateTime($clock_out, new DateTimeZone('America/New_York'));
                            $dt->setTimezone(new DateTimeZone('UTC'));
                            $clock_out = $dt->format('Y-m-d H:i:s');
                        }
                        
                        // Log converted times for debugging
                        error_log("Converted times - Clock in: $clock_in, Break in: $break_in, Break out: $break_out, Clock out: $clock_out");
                        
                        // Begin transaction
                        $time_db->begin_transaction();
                        
                        // Update the time record
                        $stmt = $time_db->prepare("UPDATE time_records SET clock_in = ?, clock_out = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $clock_in, $clock_out, $time_record_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update time record: " . $stmt->error);
                        }
                        
                        // Update break records
                        if (!empty($break_in)) {
                            // Check if break record exists
                            $stmt = $time_db->prepare("SELECT id FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
                            $stmt->bind_param("i", $time_record_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                // Update existing break
                                $break_record = $result->fetch_assoc();
                                $stmt = $time_db->prepare("UPDATE break_records SET break_in = ?, break_out = ? WHERE id = ?");
                                $stmt->bind_param("ssi", $break_in, $break_out, $break_record['id']);
                            } else {
                                // Insert new break
                                $stmt = $time_db->prepare("INSERT INTO break_records (time_record_id, break_in, break_out) VALUES (?, ?, ?)");
                                $stmt->bind_param("iss", $time_record_id, $break_in, $break_out);
                            }
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Failed to update break record: " . $stmt->error);
                            }
                        }
                        
                        // Commit transaction
                        $time_db->commit();
                        
                        send_json_response(true, 'Session updated successfully');
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $time_db->rollback();
                        error_log("Error in save_session: " . $e->getMessage());
                        send_json_response(false, '', $e->getMessage());
                    }
                    break;
                    
                case 'delete_record':
                    // Handle delete record logic here
                    send_json_response(true, 'Record deleted successfully');
                    break;
                    
                default:
                    send_json_response(false, '', 'Invalid action');
            }
        }
    } catch (Exception $e) {
        error_log("Unexpected error in AJAX handler: " . $e->getMessage());
        send_json_response(false, '', $e->getMessage());
    }
    exit;
}

// Handle regular page load
// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    header('Location: ../index.php');
    exit;
}

// Get all employees from the database
try {
    $query = "SELECT username FROM employees ORDER BY username";
    $result = $grok_db->query($query);
    if (!$result) {
        throw new Exception("Failed to fetch employees: " . $grok_db->error);
    }
    $employees = $result->fetch_all(MYSQLI_ASSOC);

    // Get current time sessions for each employee
    foreach ($employees as &$employee) {
        $username = $employee['username'];
        
        // Get the most recent time record for this employee
        $stmt = $time_db->prepare("SELECT * FROM time_records WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_session = $result->fetch_assoc();
        
        $employee['has_session'] = ($current_session !== null);
        if ($employee['has_session']) {
            $dt = new DateTime($current_session['clock_in'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/New_York'));
            $employee['formatted_clock_in'] = $dt->format('g:i A');
        }
    }
    unset($employee); // Break the reference
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching employee data. Please check the error log for details.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TIMEMASTER Admin - Time Edit</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #34495e;
            --success-color: #27ae60;
            --danger-color: #c0392b;
            --light-gray: #ecf0f1;
            --dark-gray: #2c3e50;
            --text-color: #ffffff;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #1a1a1a;
            color: #ffffff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            color: var(--primary-color);
        }

        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .employee-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .employee-card:hover {
            transform: translateY(-2px);
        }

        .employee-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            color: red;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-bottom: 10px;
            color: red;
        }

        .status-clocked-in {
            background-color: var(--success-color);
            color: white;
        }

        .status-break {
            background-color: var(--warning-color);
            color: white;
        }

        .status-clocked-out {
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }

        .time-info {
            font-size: 0.9em;
            color: var(--dark-gray);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #2a2a2a;
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            color: #ffffff;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }

        .edit-form {
            display: grid;
            gap: 15px;
        }

        .form-group {
            display: grid;
            gap: 5px;
        }

        .form-group label {
            font-weight: bold;
            color: var(--primary-color);
        }

        .form-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .break-records-container {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .break-record {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            position: relative;
        }

        .break-record.external-break {
            border-left: 4px solid #4CAF50;
            background-color: #f8fff8;
        }

        .break-time-inputs {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .break-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .toggle-external-btn {
            background-color: #2196F3;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .toggle-external-btn:hover {
            background-color: #1976D2;
        }

        .remove-btn {
            background-color: #f44336;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .remove-btn:hover {
            background-color: #d32f2f;
        }

        .info-message {
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            text-align: center;
            color: #666;
        }

        .card-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .employee-card {
            position: relative;
        }
        
        .clock-out-time {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            text-align: center;
            color: #ffffff;
        }
        
        .clock-out-time h3 {
            margin: 0 0 15px 0;
            color: var(--primary-color);
        }
        
        .clock-out-time .time-display {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .clock-out-time .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .action-buttons button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .clock-in-btn {
            background-color: #4CAF50;
            color: white;
        }

        .clock-out-btn {
            background-color: #f44336;
            color: white;
        }

        .break-start-btn {
            background-color: #2196F3;
            color: white;
        }

        .break-end-btn {
            background-color: #FF9800;
            color: white;
        }

        .action-buttons button:hover {
            opacity: 0.9;
        }
    </style>
    <script>
        // Test Edit Interface Scripts
        let selectedUsername = '';
        let hasActiveSession = false;
        let currentTimeRecordId = null;

        function showEditOptions(username, hasSession) {
            console.log('showEditOptions called with:', username, hasSession); // Debug log
            selectedUsername = username;
            hasActiveSession = hasSession;
            
            // Update the selected employee name in the modal
            const selectedEmployeeSpan = document.getElementById('selectedEmployee');
            if (selectedEmployeeSpan) {
                selectedEmployeeSpan.textContent = username;
            }
            
            // Get the modal buttons
            const editCurrentBtn = document.getElementById('editCurrentBtn');
            const deleteRecordBtn = document.getElementById('deleteRecordBtn');
            const clockInBtn = document.getElementById('clockInBtn');
            
            // Show/hide buttons based on session status
            if (editCurrentBtn && deleteRecordBtn && clockInBtn) {
                if (hasSession) {
                    editCurrentBtn.style.display = 'inline-block';
                    deleteRecordBtn.style.display = 'inline-block';
                    clockInBtn.style.display = 'none';
                } else {
                    editCurrentBtn.style.display = 'none';
                    deleteRecordBtn.style.display = 'none';
                    clockInBtn.style.display = 'inline-block';
                }
            }
            
            // Show the modal
            const modal = document.getElementById('editOptionsModal');
            if (modal) {
                modal.style.display = 'block';
            }
            
            // Get the time record ID if there's an active session
            if (hasSession) {
                fetch(`get_current_session.php?username=${encodeURIComponent(username)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentTimeRecordId = data.data.time_record_id;
                            console.log('Current session data:', data); // Debug log
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching time record ID:', error);
                    });
            }
        }

        function closeModal(modalId) {
            console.log('Closing modal:', modalId); // Debug log
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function editCurrentSession() {
            const username = document.getElementById('selectedEmployee').textContent;
            console.log('Editing current session for:', username);
            
            // Fetch current session data
            fetch(`get_current_session.php?username=${encodeURIComponent(username)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load session data');
                    }
                    
                    console.log('Session data:', data);
                    
                    // Handle clock in time
                    const clockInTime = document.getElementById('clockInTime');
                    if (data.data.clock_in) {
                        try {
                            const clockInDate = new Date(data.data.clock_in);
                            if (!isNaN(clockInDate.getTime())) {
                                clockInTime.value = clockInDate.toISOString().slice(0, 16);
                            } else {
                                console.error('Invalid clock in time:', data.data.clock_in);
                                clockInTime.value = '';
                            }
                        } catch (e) {
                            console.error('Error parsing clock in time:', e);
                            clockInTime.value = '';
                        }
                    } else {
                        clockInTime.value = '';
                    }
                    
                    // Handle clock out time
                    const clockOutTime = document.getElementById('clockOutTime');
                    if (data.data.clock_out) {
                        try {
                            const clockOutDate = new Date(data.data.clock_out);
                            if (!isNaN(clockOutDate.getTime())) {
                                clockOutTime.value = clockOutDate.toISOString().slice(0, 16);
                            } else {
                                console.error('Invalid clock out time:', data.data.clock_out);
                                clockOutTime.value = '';
                            }
                        } catch (e) {
                            console.error('Error parsing clock out time:', e);
                            clockOutTime.value = '';
                        }
                    } else {
                        clockOutTime.value = '';
                    }
                    
                    // Handle breaks
                    const breaksContainer = document.getElementById('breaksContainer');
                    breaksContainer.innerHTML = ''; // Clear existing breaks
                    
                    if (data.data.breaks && data.data.breaks.length > 0) {
                        data.data.breaks.forEach((breakRecord, index) => {
                            const breakDiv = document.createElement('div');
                            breakDiv.className = 'break-record';
                            
                            // Format break in time
                            let breakInValue = '';
                            if (breakRecord.break_in) {
                                try {
                                    const breakInDate = new Date(breakRecord.break_in);
                                    if (!isNaN(breakInDate.getTime())) {
                                        breakInValue = breakInDate.toISOString().slice(0, 16);
                                    }
                                } catch (e) {
                                    console.error('Error parsing break in time:', e);
                                }
                            }
                            
                            // Format break out time
                            let breakOutValue = '';
                            if (breakRecord.break_out) {
                                try {
                                    const breakOutDate = new Date(breakRecord.break_out);
                                    if (!isNaN(breakOutDate.getTime())) {
                                        breakOutValue = breakOutDate.toISOString().slice(0, 16);
                                    }
                                } catch (e) {
                                    console.error('Error parsing break out time:', e);
                                }
                            }
                            
                            breakDiv.innerHTML = `
                                <div class="break-header">
                                    <h4>Break ${index + 1}</h4>
                                    <button type="button" class="remove-break" onclick="removeBreak(this)">Remove</button>
                                </div>
                                <div class="form-group">
                                    <label>Start Time:</label>
                                    <input type="datetime-local" name="break_in[]" value="${breakInValue}" required>
                                </div>
                                <div class="form-group">
                                    <label>End Time:</label>
                                    <input type="datetime-local" name="break_out[]" value="${breakOutValue}" required>
                                </div>
                            `;
                            breaksContainer.appendChild(breakDiv);
                        });
                    }
                    
                    // Show the edit modal
                    document.getElementById('editCurrentModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error loading session data:', error);
                    alert('Failed to load session data: ' + error.message);
                });
        }

        function addNewBreak() {
            const breakContainer = document.getElementById('breakRecordsContainer');
            if (!breakContainer) return;

            const breakDiv = document.createElement('div');
            breakDiv.className = 'break-record';
            
            breakDiv.innerHTML = `
                <input type="hidden" name="break_id[]" value="new">
                <input type="hidden" name="break_is_external[]" value="0">
                <div class="break-time-inputs">
                    <div class="form-group">
                        <label>Break Start:</label>
                        <input type="time" name="break_in[]" class="time-input" required>
                        <button type="button" class="btn btn-secondary" onclick="setCurrentTime(this.previousElementSibling)">Now</button>
                    </div>
                    <div class="form-group">
                        <label>Break End:</label>
                        <input type="time" name="break_out[]" class="time-input">
                        <button type="button" class="btn btn-secondary" onclick="setCurrentTime(this.previousElementSibling)">Now</button>
                    </div>
                </div>
                <div class="break-actions">
                    <button type="button" class="toggle-external-btn" onclick="toggleBreakType(this)">External Break</button>
                    <button type="button" class="remove-btn" onclick="removeBreakRecord(this)">Remove</button>
                </div>
            `;
            
            breakContainer.appendChild(breakDiv);
        }

        function setCurrentTime(inputElement) {
            if (!inputElement) return;
            
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            inputElement.value = `${hours}:${minutes}`;
        }

        function toggleBreakType(button) {
            const breakRecord = button.closest('.break-record');
            const isExternal = breakRecord.classList.contains('external-break');
            
            if (isExternal) {
                breakRecord.classList.remove('external-break');
                button.textContent = 'External Break';
                
                // Remove location field if it exists
                const locationField = breakRecord.querySelector('input[name="break_location[]"]');
                if (locationField) {
                    locationField.closest('.form-group').remove();
                }
                
                // Update hidden input
                const hiddenInput = breakRecord.querySelector('input[name="break_is_external[]"]');
                hiddenInput.value = '0';
            } else {
                breakRecord.classList.add('external-break');
                button.textContent = 'On-Site Break';
                
                // Add location field
                const breakOutField = breakRecord.querySelector('.form-group:last-child');
                const locationHtml = `
                    <div class="form-group">
                        <label>Location:</label>
                        <input type="text" name="break_location[]" placeholder="Enter work location">
                    </div>
                `;
                breakOutField.insertAdjacentHTML('afterend', locationHtml);
                
                // Update hidden input
                const hiddenInput = breakRecord.querySelector('input[name="break_is_external[]"]');
                hiddenInput.value = '1';
            }
        }

        function removeBreakRecord(button) {
            const breakRecord = button.closest('.break-record');
            if (breakRecord) {
                breakRecord.remove();
            }
        }

        function clockOutEmployee(username) {
            fetch('admin_clock_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&action=admin_clock_out`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to clock out employee');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to clock out employee');
            });
        }

        function clockInEmployee(username) {
            fetch('admin_clock_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&action=admin_clock_in`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to clock in employee');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to clock in employee');
            });
        }

        function startBreak(username) {
            fetch('admin_clock_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&action=admin_break_in`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to start break');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to start break');
            });
        }

        function endBreak(username) {
            fetch('admin_clock_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&action=admin_break_out`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to end break');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to end break');
            });
        }

        // Initialize event listeners when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded'); // Debug log
            
            // Add event listener for save changes button
            const saveChangesBtn = document.getElementById('saveChangesBtn');
            if (saveChangesBtn) {
                saveChangesBtn.addEventListener('click', function() {
                    saveCurrentSession();
                });
            }
            
            // Add event listener for clock out button
            const clockOutBtn = document.getElementById('clockOutBtn');
            if (clockOutBtn) {
                clockOutBtn.addEventListener('click', function() {
                    const clockOutTime = document.getElementById('clockOutTime');
                    if (clockOutTime) {
                        setCurrentTime(clockOutTime);
                    }
                });
            }
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TIMEMASTER Admin - Time Edit</h1>
            <button class="btn btn-secondary" onclick="window.location.href='../index.php'">Back to Dashboard</button>
        </div>

        <div class="employee-grid">
            <?php foreach ($employees as $employee): ?>
                <div class="employee-card" onclick="showEditOptions('<?php echo htmlspecialchars($employee['username']); ?>', <?php echo $employee['has_session'] ? 'true' : 'false'; ?>)">
                    <div class="employee-info">
                        <h3><?php echo htmlspecialchars($employee['username']); ?></h3>
                        <?php if ($employee['has_session']): ?>
                            <?php
                            // Check if employee is on break
                            $stmt = $time_db->prepare("SELECT br.id FROM break_records br JOIN time_records tr ON br.time_record_id = tr.id WHERE tr.username = ? AND tr.clock_out IS NULL AND br.break_out IS NULL");
                            $stmt->bind_param("s", $employee['username']);
                            $stmt->execute();
                            $is_on_break = $stmt->get_result()->num_rows > 0;
                            ?>
                            <div class="status clocked-in">Clocked In: <?php echo htmlspecialchars($employee['formatted_clock_in']); ?></div>
                            <?php if ($is_on_break): ?>
                                <div class="status break">On Break</div>
                                <div class="action-buttons">
                                    <button class="break-end-btn" onclick="event.stopPropagation(); endBreak('<?php echo htmlspecialchars($employee['username']); ?>')">End Break</button>
                                    <button class="clock-out-btn" onclick="event.stopPropagation(); clockOutEmployee('<?php echo htmlspecialchars($employee['username']); ?>')">Clock Out</button>
                                </div>
                            <?php else: ?>
                                <div class="action-buttons">
                                    <button class="break-start-btn" onclick="event.stopPropagation(); startBreak('<?php echo htmlspecialchars($employee['username']); ?>')">Start Break</button>
                                    <button class="clock-out-btn" onclick="event.stopPropagation(); clockOutEmployee('<?php echo htmlspecialchars($employee['username']); ?>')">Clock Out</button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="status clocked-out">Clocked Out</div>
                            <div class="action-buttons">
                                <button class="clock-in-btn" onclick="event.stopPropagation(); clockInEmployee('<?php echo htmlspecialchars($employee['username']); ?>')">Clock In</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Edit Options Modal -->
    <div id="editOptionsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editOptionsModal')">&times;</span>
            <h2>Edit Time for <span id="selectedEmployee"></span></h2>
            <div class="button-group">
                <button class="btn btn-primary" onclick="editCurrentSession()" id="editCurrentBtn">Edit Current Session</button>
                <button class="btn btn-secondary" onclick="editSavedRecords()">Edit Saved Records</button>
                <button class="btn btn-danger" onclick="promptDeleteRecord()" id="deleteRecordBtn">Delete Time Record</button>
                <button class="btn btn-success" onclick="clockInEmployee()" id="clockInBtn" style="display: none;">Clock In Employee</button>
            </div>
        </div>
    </div>

    <!-- Current Session Edit Modal -->
    <div id="currentSessionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('currentSessionModal')">&times;</span>
            <h2 id="modalTitle">Edit Current Session</h2>
            <form id="currentSessionForm" class="edit-form">
                <div class="form-group">
                    <label>Clock In Time:</label>
                    <input type="time" id="clockInTime" class="time-input">
                </div>
                
                <div class="break-records-container">
                    <h3>Break Records</h3>
                    <div id="breakRecordsContainer">
                        <!-- Break records will be loaded dynamically -->
                    </div>
                    <button type="button" class="btn btn-primary" id="addBreakBtn" onclick="addNewBreak()">Add New Break</button>
                </div>
                
                <div class="form-group">
                    <label>Clock Out Time:</label>
                    <input type="time" id="clockOutTime" class="time-input">
                    <button type="button" class="btn btn-danger" id="clockOutBtn" onclick="setCurrentTime('clockOutTime')">Set Current Time</button>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-primary" id="saveChangesBtn">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('currentSessionModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>