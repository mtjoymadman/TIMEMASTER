<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Output initial debug message
// echo '<p>Starting live_time_editor.php...</p>';

// Use centralized session configuration
try {
    // echo '<p>Loading session configuration...</p>';
    // Temporarily bypass session config for debugging
    // echo '<p>Skipping session configuration load for debugging.</p>';
    // require_once __DIR__ . '/../FLEETMASTER/GROK/grok.redlionsalvage.net/includes/session_config.php';
    // echo '<p>Session configuration loaded successfully.</p>';
} catch (Exception $e) {
    // echo '<p>Error loading session configuration: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die('Test stopped due to session configuration error.');
}

// Load required files
try {
    // echo '<p>Loading config.php...</p>';
    require_once '../config.php';
    // echo '<p>config.php loaded successfully.</p>';
    
    // echo '<p>Loading functions.php...</p>';
    require_once '../functions.php';
    // echo '<p>functions.php loaded successfully.</p>';
} catch (Exception $e) {
    // echo '<p>Error loading required files: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die('Test stopped due to required files error.');
}

// Set timezone
date_default_timezone_set('America/New_York');

// echo '<p>Timezone set to America/New_York.</p>';

// Output session data
// echo '<p>Session data:</p>';
// echo '<pre>' . print_r($_SESSION, true) . '</pre>';

// echo '<p>Checking if user is logged in...</p>';
// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // echo '<p>No session exists, would redirect to ../login.php</p>';
    // header('Location: ../login.php');
    // exit;
} else {
    // echo '<p>User session found: ' . htmlspecialchars($_SESSION['username']) . '</p>';
}

// echo '<p>Checking for admin role...</p>';
// Check if user has admin role
if (!(isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    // echo '<p>No admin role, would redirect to ../index.php</p>';
    // Redirect to employee interface
    // header('Location: ../index.php');
    // exit;
} else {
    // echo '<p>Admin role confirmed.</p>';
}

// echo '<p>Getting logged in user...</p>';
// Get logged in user
$logged_in_user = $_SESSION['username'];
// echo '<p>Logged in user: ' . htmlspecialchars($logged_in_user) . '</p>';

// echo '<p>Initializing error and success variables...</p>';
// Process form submission
$error = '';
$success = '';

// echo '<p>Checking for session messages...</p>';
// Check for success or error messages from other pages
if (isset($_SESSION['success']) && isset($_SESSION['message'])) {
    if ($_SESSION['success']) {
        $success = $_SESSION['message'];
    } else {
        $error = $_SESSION['message'];
    }
    unset($_SESSION['success']);
    unset($_SESSION['message']);
    // echo '<p>Session messages processed.</p>';
} else {
    // echo '<p>No session messages found.</p>';
}

// echo '<p>Attempting database connections...</p>';
// Connect to databases
$time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($time_db->connect_error) {
    // echo '<p>Time database connection failed: ' . htmlspecialchars($time_db->connect_error) . '</p>';
    die('Time database connection failed: ' . htmlspecialchars($time_db->connect_error));
} else {
    // echo '<p>Time database connection successful.</p>';
}

$grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
if ($grok_db->connect_error) {
    // echo '<p>Grok database connection failed: ' . htmlspecialchars($grok_db->connect_error) . '</p>';
    die('Grok database connection failed: ' . htmlspecialchars($grok_db->connect_error));
} else {
    // echo '<p>Grok database connection successful.</p>';
}
// echo '<p>Continuing with live_time_editor.php logic...</p>';

// Get employees currently on the clock from time database
$on_clock = [];
$result = $time_db->query("SELECT DISTINCT username FROM time_records WHERE clock_out IS NULL");
while ($row = $result->fetch_assoc()) {
    // Get employee details from grok database
    $stmt = $grok_db->prepare("SELECT username FROM employees WHERE username = ?");
    $stmt->bind_param("s", $row['username']);
    $stmt->execute();
    $empResult = $stmt->get_result();
    if ($empResult->num_rows > 0) {
        $empData = $empResult->fetch_assoc();
        $on_clock[] = [
            'username' => $empData['username'],
            'name' => $empData['username'] // Use username as display name
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Time Editor - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script>
        // Function to load break records into the form
        function loadLiveBreakRecords(breaks) {
            const container = document.getElementById('liveBreakRecordsContainer');
            container.innerHTML = ''; // Clear loading message
            
            if (!breaks || breaks.length === 0) {
                container.innerHTML = '<div class="info-message">No breaks recorded for this shift.</div>';
                return;
            }
            
            let breakRecordsHtml = '';
            
            breaks.forEach((breakRecord, index) => {
                // Use the time directly - server returns Eastern Time
                // Extract time component (HH:MM) from the break_in timestamp
                let breakInTime = breakRecord.break_in;
                if (breakInTime.includes(' ')) {
                    breakInTime = breakInTime.split(' ')[1].substring(0, 5);
                }
                
                let breakOutHtml = '';
                let breakOutTime = '';
                if (breakRecord.break_out) {
                    // Extract time component from break_out timestamp
                    breakOutTime = breakRecord.break_out;
                    if (breakOutTime.includes(' ')) {
                        breakOutTime = breakOutTime.split(' ')[1].substring(0, 5);
                    }
                    
                    breakOutHtml = `
                        <div class="form-group">
                            <label>Break End:</label>
                            <input type="time" id="liveBreakOut_${index}" name="break_out[]" value="${breakOutTime}">
                        </div>
                    `;
                } else {
                    breakOutHtml = `
                        <div class="form-group">
                            <label>Break End:</label>
                            <input type="time" id="liveBreakOut_${index}" name="break_out[]">
                        </div>
                    `;
                }
                
                // Add location field for external breaks
                let locationField = '';
                if (breakRecord.is_external) {
                    locationField = `
                        <div class="form-group">
                            <label>Location:</label>
                            <input type="text" id="liveBreakLocation_${index}" name="break_location[]" value="${breakRecord.location || ''}" placeholder="Enter work location">
                        </div>
                    `;
                }
                
                breakRecordsHtml += `
                    <div class="break-record ${breakRecord.is_external ? 'external-break' : ''}">
                        <input type="hidden" name="break_id[]" value="${breakRecord.id}">
                        <input type="hidden" name="break_is_external[]" value="${breakRecord.is_external ? '1' : '0'}">
                        <div class="break-time-inputs">
                            <div class="form-group">
                                <label>Break Start:</label>
                                <input type="time" id="liveBreakIn_${index}" name="break_in[]" value="${breakInTime}" required>
                            </div>
                            ${breakOutHtml}
                            ${locationField}
                        </div>
                        <div class="break-actions">
                            <button type="button" class="toggle-external-btn" onclick="toggleBreakType(this)">
                                ${breakRecord.is_external ? 'On-Site Break' : 'External Break'}
                            </button>
                            <button type="button" class="remove-btn" onclick="removeLiveBreakRecord(this)">Remove</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = breakRecordsHtml;
        }

        // Function to toggle between regular and external breaks
        function toggleBreakType(button) {
            const breakRecord = button.closest('.break-record');
            const isExternal = breakRecord.classList.contains('external-break');
            
            if (isExternal) {
                // Switching to regular break
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
                // Switching to external break
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

        // Function to remove a break record from the form
        function removeLiveBreakRecord(button) {
            const breakRecord = button.closest('.break-record');
            breakRecord.remove();
            
            const remainingBreakRecords = document.querySelectorAll('.break-record');
            if (remainingBreakRecords.length === 0) {
                document.getElementById('liveBreakRecordsContainer').innerHTML = '<div class="info-message">No breaks recorded for this shift.</div>';
            }
        }

        // Move deleteTimeRecord function outside of DOMContentLoaded to make it globally accessible
        function deleteTimeRecord(username, timeRecordId) {
            console.log('Attempting to delete record for', username, 'with ID', timeRecordId); // Debug log
            if (confirm(`Are you sure you want to delete the time record for ${username}? This action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('time_record_id', timeRecordId);
                formData.append('username', username);
                formData.append('action', 'delete');
                
                console.log('Sending delete request to ./delete_time_record.php'); // Debug log
                fetch('./delete_time_record.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response received:', response); // Debug log
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data); // Debug log
                    if (data.success) {
                        alert('Time record deleted successfully');
                        // Refresh the page to show updated status
                        window.location.reload();
                    } else {
                        throw new Error(data.error || 'Failed to delete record');
                    }
                })
                .catch(error => {
                    console.error('Error deleting record:', error); // Debug log
                    alert('Error deleting record: ' + error.message);
                });
            }
        }
    </script>
    <script src="openLiveTimeEditorModal.js"></script>
    <style>
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            background-color: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .back-button:hover {
            background-color: #444;
        }
        
        .back-button i {
            margin-right: 5px;
        }
        
        .toggle-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .toggle-btn:hover {
            background-color: #45a049;
        }
        
        .toggle-btn i {
            transition: transform 0.3s;
        }
        
        .toggle-btn.active i {
            transform: rotate(90deg);
        }
        
        .collapsible-content {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: color 0.3s;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .test-edit-container {
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .edit-btn:hover {
            background-color: #d63031;
        }
        
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .break-records-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .break-records-table th,
        .break-records-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .break-records-table th {
            background-color: #f5f5f5;
        }
        
        .add-break-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .delete-break-btn {
            background-color: #f44336;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            background-color: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .back-button:hover {
            background-color: #444;
        }
        
        .break-record {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
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
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
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
    </style>
</head>
<body>
    <header>
        <div class="home-btn">
            <a href="../index.php"><i class="fas fa-home"></i> Home</a>
        </div>
        <div class="logo">
            <a href="index.php">TIMEMASTER</a>
        </div>
        <div class="logout-btn">
            <a href="../logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>
    <div class="container">
        <div class="header">
            <h1>TIMEMASTER Admin - Time Edit</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
            <button class="btn btn-secondary" onclick="window.location.href='<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? '//time.redlionsalvage.net/admin/index.php' : '//time.redlionsalvage.net/employee.php'; ?>'"><?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'Back to Time Dashboard' : 'Back to Yardmaster Dashboard'; ?></button>
        </div>
        
        <?php
        // Determine the appropriate dashboard based on role
        $dashboard_url = '/index.php'; // Default to employee dashboard
        if (hasRole($logged_in_user, 'admin')) {
            $dashboard_url = '//grok.redlionsalvage.net/admin/index.php';
        }
        ?>
        <a href="<?php echo $dashboard_url; ?>" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Live Time Editor</h2>
            <p>Edit time records for employees currently on the clock</p>
            <form method="post" class="button-group">
                <select id="liveTimeEditorSelect" name="live_edit_employee">
                    <option value="">Select Employee</option>
                    <?php foreach ($on_clock as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp['username']); ?>">
                            <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['username']); ?>)
                        </option>
                    <?php } ?>
                </select>
                <button type="button" id="openLiveTimeEditor" class="edit-btn">Edit Time Record</button>
            </form>
        </div>
        
        <!-- Test Edit Section -->
        <div class="admin-section">
            <button type="button" id="toggleTestEdit" class="toggle-btn">
                <i class="fas fa-chevron-right"></i> Show Test Edit Interface
            </button>
            <div id="testEditContent" class="collapsible-content" style="display: none;">
                <div class="section-header">
                    <h2>Test Edit Interface</h2>
                    <button type="button" class="close-btn" id="closeTestEdit">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="test-edit-container">
                    <iframe src="testedit1.php" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            </div>
        </div>
        
        <div class="admin-section">
            <h2>Employees Currently On the Clock</h2>
            <table class="time-editor-table">
                <tr>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Clock In Time</th>
                    <th>Current Duration</th>
                    <th>Break Status</th>
                    <th>Actions</th>
                </tr>
                <?php
                // Get all employees currently on the clock
                $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
                $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
                foreach ($on_clock as $employee) {
                    // Get current time record
                    $stmt = $time_db->prepare("SELECT id, clock_in FROM time_records WHERE username = ? AND clock_out IS NULL");
                    $stmt->bind_param("s", $employee['username']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $timeRecord = $result->fetch_assoc();
                        $timeRecordId = $timeRecord['id'];
                        
                        // Create clock in time using Eastern Time
                        $clockInTime = new DateTime($timeRecord['clock_in'], new DateTimeZone('America/New_York'));
                        $displayClockIn = $clockInTime->format('h:i A') . ' ET';
                        
                        $onBreak = false;
                        $breakTime = "";
                        
                        // Calculate shift duration in EDT
                        $now = new DateTime('now', new DateTimeZone('America/New_York'));
                        $shiftDuration = $now->getTimestamp() - $clockInTime->getTimestamp();
                        $hours = floor($shiftDuration / 3600);
                        $minutes = floor(($shiftDuration % 3600) / 60);
                        $durationText = $hours . "h " . $minutes . "m";
                        
                        // Check if employee is on break
                        $stmt = $time_db->prepare("SELECT break_in FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
                        $stmt->bind_param("i", $timeRecordId);
                        $stmt->execute();
                        $breakResult = $stmt->get_result();
                        if ($breakResult->num_rows > 0) {
                            $breakRecord = $breakResult->fetch_assoc();
                            // Use Eastern Time for break calculations
                            $breakStartTime = new DateTime($breakRecord['break_in'], new DateTimeZone('America/New_York'));
                            $breakDuration = $now->getTimestamp() - $breakStartTime->getTimestamp();
                            $breakMinutes = floor($breakDuration / 60);
                            $breakTime = $breakMinutes . " min";
                            $onBreak = true;
                            $breakStatus = "Currently on break";
                        } else {
                            // Get total breaks for this time record
                            $stmt = $time_db->prepare("SELECT COUNT(*) as break_count FROM break_records WHERE time_record_id = ?");
                            $stmt->bind_param("i", $timeRecordId);
                            $stmt->execute();
                            $totalBreaksResult = $stmt->get_result()->fetch_assoc();
                            $totalBreaks = $totalBreaksResult['break_count'];
                            $breakStatus = $totalBreaks > 0 ? $totalBreaks . " break(s) taken" : "No breaks";
                        }
                        
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($employee['name']) . "</td>";
                        echo "<td>" . ($onBreak ? "<span class='status-break'>On Break</span>" : "<span class='status-active'>Clocked In</span>") . "</td>";
                        echo "<td>" . $displayClockIn . "</td>";
                        echo "<td>" . $durationText . "</td>";
                        echo "<td>" . $breakStatus . ($breakTime ? " (" . $breakTime . ")" : "") . "</td>";
                        echo "<td>
                            <button type='button' class='edit-btn' onclick='openLiveTimeEditorModal(\"" . htmlspecialchars($employee['username']) . "\")'>Edit Times</button>
                            <button type='button' class='delete-btn' onclick='deleteTimeRecord(\"" . htmlspecialchars($employee['username']) . "\", " . $timeRecordId . ")'>Delete Record</button>
                        </td>";
                        echo "</tr>";
                    }
                }
                // Remove the early database closing statements
                
                if (count($on_clock) === 0) {
                    echo "<tr><td colspan='6' style='text-align: center;'>No employees currently clocked in</td></tr>";
                }
                ?>
            </table>
        </div>
        
        <!-- Live Time Editor Modal -->
        <div id="liveTimeEditorModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Live Time Editor</h2>
                <p id="liveTimeEditorEmployee"></p>
                
                <form method="post" id="liveTimeEditorForm" action="process_live_time_edit.php">
                    <input type="hidden" name="action" value="edit_live_time">
                    <input type="hidden" id="liveEditEmployeeUsername" name="live_edit_username">
                    <input type="hidden" id="liveEditTimeRecordId" name="live_edit_time_record_id">
                    
                    <div class="form-group">
                        <label for="liveEditClockIn">Clock In Time:</label>
                        <input type="time" id="liveEditClockIn" name="live_edit_clock_in">
                    </div>
                    
                    <div class="break-records-container">
                        <h3>Break Records</h3>
                        <div id="liveBreakRecordsContainer">
                            <!-- Break records will be loaded dynamically -->
                            <div class="loading-message">Loading break records...</div>
                        </div>
                        
                        <button type="button" class="add-btn" id="liveAddBreakBtn">Add New Break</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="liveEditNotes">Admin Notes:</label>
                        <textarea id="liveEditNotes" name="live_edit_notes" placeholder="Reason for time adjustment"></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="cancel-btn" id="liveCancelBtn">Cancel</button>
                        <button type="submit" class="edit-btn">Save Changes</button>
                        <button type="button" class="delete-btn" id="liveDeleteBtn">Delete Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Test Edit Toggle functionality
            const toggleBtn = document.getElementById('toggleTestEdit');
            const testEditContent = document.getElementById('testEditContent');
            const closeBtn = document.getElementById('closeTestEdit');
            
            if (toggleBtn && testEditContent && closeBtn) {
                toggleBtn.addEventListener('click', function() {
                    testEditContent.style.display = testEditContent.style.display === 'none' ? 'block' : 'none';
                    toggleBtn.classList.toggle('active');
                });
                
                closeBtn.addEventListener('click', function() {
                    testEditContent.style.display = 'none';
                    toggleBtn.classList.remove('active');
                });
            }
            
            // Existing Live Time Editor Modal functionality
            const liveTimeEditorModal = document.getElementById('liveTimeEditorModal');
            const liveTimeEditorSpan = liveTimeEditorModal.querySelector('.close');
            const liveBreakRecordsContainer = document.getElementById('liveBreakRecordsContainer');
            const liveAddBreakBtn = document.getElementById('liveAddBreakBtn');
            const liveCancelBtn = document.getElementById('liveCancelBtn');
            const openLiveTimeEditorBtn = document.getElementById('openLiveTimeEditor');
            const liveTimeEditorSelect = document.getElementById('liveTimeEditorSelect');
            
            // Open the Live Time Editor modal
            openLiveTimeEditorBtn.addEventListener('click', function() {
                const selectedEmployee = liveTimeEditorSelect.value;
                if (!selectedEmployee) {
                    alert('Please select an employee');
                    return;
                }
                
                openLiveTimeEditorModal(selectedEmployee);
            });
            
            // Function to open the Live Time Editor modal
            function openLiveTimeEditorModal(username) {
                // Show the modal
                liveTimeEditorModal.style.display = 'block';
                
                // Set the employee name
                document.getElementById('liveTimeEditorEmployee').textContent = 'Editing time record for: ' + username;
                document.getElementById('liveEditEmployeeUsername').value = username;
                
                // Clear previous form data
                document.getElementById('liveEditNotes').value = '';
                document.getElementById('liveEditTimeRecordId').value = ''; // Clear ID first
                liveBreakRecordsContainer.innerHTML = '<div class="loading-message">Loading break records...</div>';
                
                // Fetch the current time record for the employee
                fetch('get_live_time_record.php?username=' + encodeURIComponent(username))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Server returned status ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (!data.timeRecord || !data.timeRecord.id) {
                                throw new Error('No active time record found for this employee');
                            }
                            
                            // Set time record ID
                            document.getElementById('liveEditTimeRecordId').value = data.timeRecord.id;
                            console.log("Setting time record ID to:", data.timeRecord.id);
                            
                            // Use the time directly - server returns Eastern Time
                            // The clock_in should already be in Eastern Time from the server
                            let clockInTime = data.timeRecord.clock_in;
                            if (clockInTime && clockInTime.includes(' ')) {
                                // If the time includes date information, extract just the time portion
                                clockInTime = clockInTime.split(' ')[1].substring(0, 5);
                            }
                            document.getElementById('liveEditClockIn').value = clockInTime || '';
                            
                            // Load break records using the external function
                            if (typeof loadLiveBreakRecordsExternal === 'function') {
                                loadLiveBreakRecordsExternal(data.timeRecord.breaks || []);
                            } else {
                                console.error('loadLiveBreakRecordsExternal function not found');
                            }
                        } else {
                            throw new Error(data.message || 'Could not load time record');
                        }
                    })
                    .catch(error => {
                        liveBreakRecordsContainer.innerHTML = '<div class="error-message">Error: ' + error.message + '</div>';
                        alert('Error: ' + error.message + '\nPlease make sure the employee has an active clock-in session.');
                        setTimeout(() => {
                            liveTimeEditorModal.style.display = 'none'; // Close the modal after showing error
                        }, 100);
                    });
            }
            
            // Function to add a new break record to the form
            liveAddBreakBtn.addEventListener('click', function() {
                const newBreakRecord = document.createElement('div');
                newBreakRecord.className = 'break-record';
                
                newBreakRecord.innerHTML = `
                    <input type="hidden" name="break_id[]" value="new">
                    <div class="break-time-inputs">
                        <div class="form-group">
                            <label>Break Start:</label>
                            <input type="time" name="break_in[]" required>
                        </div>
                        <div class="form-group">
                            <label>Break End:</label>
                            <input type="time" name="break_out[]">
                        </div>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeLiveBreakRecord(this)">Remove</button>
                `;
                
                liveBreakRecordsContainer.appendChild(newBreakRecord);
            });
            
            // Function to load break records into the form
            function loadLiveBreakRecordsExternal(breaks) {
                if (breaks.length === 0) {
                    liveBreakRecordsContainer.innerHTML = '<div class="info-message">No breaks recorded for this shift.</div>';
                    return;
                }
                
                let breakRecordsHtml = '';
                
                breaks.forEach((breakRecord, index) => {
                    // Use the time directly - server returns Eastern Time
                    // Extract time component (HH:MM) from the break_in timestamp
                    let breakInTime = breakRecord.break_in;
                    if (breakInTime.includes(' ')) {
                        breakInTime = breakInTime.split(' ')[1].substring(0, 5);
                    }
                    
                    let breakOutHtml = '';
                    let breakOutTime = '';
                    if (breakRecord.break_out) {
                        // Extract time component from break_out timestamp
                        breakOutTime = breakRecord.break_out;
                        if (breakOutTime.includes(' ')) {
                            breakOutTime = breakOutTime.split(' ')[1].substring(0, 5);
                        }
                        
                        breakOutHtml = `
                            <div class="form-group">
                                <label>Break End:</label>
                                <input type="time" id="liveBreakOut_${index}" name="break_out[]" value="${breakOutTime}">
                            </div>
                        `;
                    } else {
                        breakOutHtml = `
                            <div class="form-group">
                                <label>Break End:</label>
                                <input type="time" id="liveBreakOut_${index}" name="break_out[]">
                            </div>
                        `;
                    }
                    
                    // Add location field for external breaks
                    let locationField = '';
                    if (breakRecord.is_external) {
                        locationField = `
                            <div class="form-group">
                                <label>Location:</label>
                                <input type="text" id="liveBreakLocation_${index}" name="break_location[]" value="${breakRecord.location || ''}" placeholder="Enter work location">
                            </div>
                        `;
                    }
                    
                    breakRecordsHtml += `
                        <div class="break-record ${breakRecord.is_external ? 'external-break' : ''}">
                            <input type="hidden" name="break_id[]" value="${breakRecord.id}">
                            <input type="hidden" name="break_is_external[]" value="${breakRecord.is_external ? '1' : '0'}">
                            <div class="break-time-inputs">
                                <div class="form-group">
                                    <label>Break Start:</label>
                                    <input type="time" id="liveBreakIn_${index}" name="break_in[]" value="${breakInTime}" required>
                                </div>
                                ${breakOutHtml}
                                ${locationField}
                            </div>
                            <div class="break-actions">
                                <button type="button" class="toggle-external-btn" onclick="toggleBreakType(this)">
                                    ${breakRecord.is_external ? 'On-Site Break' : 'External Break'}
                                </button>
                                <button type="button" class="remove-btn" onclick="removeLiveBreakRecord(this)">Remove</button>
                            </div>
                        </div>
                    `;
                });
                
                liveBreakRecordsContainer.innerHTML = breakRecordsHtml;
            }
            
            // Close the live time editor modal when clicking X
            liveTimeEditorSpan.onclick = function() {
                liveTimeEditorModal.style.display = 'none';
            }
            
            // Close the live time editor modal when clicking cancel
            liveCancelBtn.onclick = function() {
                liveTimeEditorModal.style.display = 'none';
            }
            
            // Close the live time editor modal when clicking outside it
            window.addEventListener('click', function(event) {
                if (event.target == liveTimeEditorModal) {
                    liveTimeEditorModal.style.display = 'none';
                }
            });
            
            // Helper function to format date as time only (HH:MM) for time inputs
            function formatTimeOnly(date) {
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                
                return `${hours}:${minutes}`;
            }

            // Add delete functionality
            const liveDeleteBtn = document.getElementById('liveDeleteBtn');
            liveDeleteBtn.addEventListener('click', function() {
                const username = document.getElementById('liveEditEmployeeUsername').value;
                const timeRecordId = document.getElementById('liveEditTimeRecordId').value;
                
                if (!timeRecordId) {
                    alert('No time record selected');
                    return;
                }
                
                if (confirm(`Are you sure you want to delete the time record for ${username}? This action cannot be undone.`)) {
                    const formData = new FormData();
                    formData.append('time_record_id', timeRecordId);
                    formData.append('username', username);
                    formData.append('action', 'delete');
                    
                    console.log('Sending delete request to ./delete_time_record.php'); // Debug log
                    fetch('./delete_time_record.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response received:', response); // Debug log
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data); // Debug log
                        if (data.success) {
                            alert('Time record deleted successfully');
                            liveTimeEditorModal.style.display = 'none';
                            // Refresh the page to show updated status
                            window.location.reload();
                        } else {
                            throw new Error(data.error || 'Failed to delete record');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting record:', error); // Debug log
                        alert('Error deleting record: ' + error.message);
                    });
                }
            });

            // Add form submission handling
            const liveTimeEditorForm = document.getElementById('liveTimeEditorForm');
            liveTimeEditorForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('process_live_time_edit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Time record updated successfully');
                        liveTimeEditorModal.style.display = 'none';
                        // Refresh the page to show updated status
                        window.location.reload();
                    } else {
                        throw new Error(data.error || 'Failed to update record');
                    }
                })
                .catch(error => {
                    alert('Error updating record: ' + error.message);
                });
            });
        });
    </script>
</body>
</html>
?> 