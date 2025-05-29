<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    // Redirect to employee interface
    header('Location: ../index.php');
    exit;
}

// Get logged in user
$logged_in_user = $_SESSION['username'];

// Process form submission
$error = '';
$success = '';

// Connect to database
$db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get list of employees
$employees = [];
$result = $grok_db->query("SELECT username FROM employees ORDER BY username");
while ($row = $result->fetch_assoc()) {
    $employees[] = $row['username'];
}

// Handle time record selection
$selected_username = '';
$time_records = [];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

if (isset($_POST['selected_username']) || isset($_GET['username'])) {
    $selected_username = isset($_POST['selected_username']) ? $_POST['selected_username'] : $_GET['username'];
    
    // Get total count for pagination
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM time_records WHERE username = ?");
    $count_stmt->bind_param("s", $selected_username);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Get time records for selected employee
    $stmt = $db->prepare("SELECT id, username, clock_in, clock_out, admin_notes FROM time_records WHERE username = ? ORDER BY clock_in DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $selected_username, $per_page, $offset);
    $stmt->execute();
    $time_records_result = $stmt->get_result();
    
    while ($record = $time_records_result->fetch_assoc()) {
        // Format the dates for display
        $clock_in = new DateTime($record['clock_in'], new DateTimeZone('America/New_York'));
        $record['clock_in'] = $clock_in->format('Y-m-d H:i:s');
        
        if ($record['clock_out']) {
            $clock_out = new DateTime($record['clock_out'], new DateTimeZone('America/New_York'));
            $record['clock_out'] = $clock_out->format('Y-m-d H:i:s');
        }
        
        // Get all breaks for this time record
        $breaks = [];
        $break_stmt = $db->prepare("SELECT id, break_in, break_out, notes FROM break_records WHERE time_record_id = ? ORDER BY break_in");
        $break_stmt->bind_param("i", $record['id']);
        $break_stmt->execute();
        $break_result = $break_stmt->get_result();
        
        while ($break = $break_result->fetch_assoc()) {
            // Format break times
            if ($break['break_in']) {
                $break_in = new DateTime($break['break_in'], new DateTimeZone('America/New_York'));
                $break['break_in'] = $break_in->format('Y-m-d H:i:s');
            }
            if ($break['break_out']) {
                $break_out = new DateTime($break['break_out'], new DateTimeZone('America/New_York'));
                $break['break_out'] = $break_out->format('Y-m-d H:i:s');
            }
            $breaks[] = $break;
        }
        
        $record['breaks'] = $breaks;
        $time_records[] = $record;
    }
}

// Close database connection
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Records - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        
        .time-record {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .time-record-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
        }
        
        .time-record-dates {
            flex: 1;
        }
        
        .time-record-duration {
            margin-left: 20px;
            font-weight: bold;
        }
        
        .break-records {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #444;
        }
        
        .break-record {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 5px;
            background-color: #333;
            border-radius: 4px;
        }
        
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 20px 0;
            justify-content: center;
        }
        
        .pagination li {
            margin: 0 5px;
        }
        
        .pagination a {
            display: block;
            padding: 5px 10px;
            background-color: #333;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a.active {
            background-color: #e74c3c;
        }
        
        .pagination a:hover {
            background-color: #444;
        }
        
        .no-records {
            text-align: center;
            padding: 20px;
            background-color: #2a2a2a;
            border-radius: 8px;
        }
        
        .clock-out-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .clock-out-btn:hover {
            background-color: #c0392b;
        }
        
        .active-shift {
            border-left: 4px solid #e74c3c;
        }
        
        .clock-out-section {
            background-color: #2a2a2a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .clock-out-section h3 {
            color: #e74c3c;
            margin-top: 0;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #2a2a2a;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            color: white;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: white;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ddd;
        }
        
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: white;
        }
        
        .break-records-container {
            margin: 20px 0;
            padding: 15px;
            background-color: #333;
            border-radius: 4px;
        }
        
        .break-record {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #2a2a2a;
            border-radius: 4px;
        }
        
        .break-time-inputs {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        
        .remove-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .add-btn {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-btn {
            background-color: #666;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .edit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Time Records</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>View Employee Records</h2>
            <form method="post" class="button-group">
                <select name="selected_username">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp) { ?>
                        <option value="<?php echo htmlspecialchars($emp); ?>" <?php echo $selected_username === $emp ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp); ?>
                        </option>
                    <?php } ?>
                </select>
                <button type="submit" class="edit-btn">View Time Records</button>
            </form>
        </div>
        
        <?php if ($selected_username) { ?>
            <div class="admin-section">
                <h2>Time Records for <?php echo htmlspecialchars($selected_username); ?></h2>
                
                <?php if (count($time_records) > 0) { ?>
                    <?php foreach ($time_records as $record) { 
                        $clock_in_time = strtotime($record['clock_in']);
                        $clock_out_time = $record['clock_out'] ? strtotime($record['clock_out']) : null;
                        
                        // Calculate duration
                        $duration = '';
                        if ($clock_out_time) {
                            $diff = $clock_out_time - $clock_in_time;
                            $hours = floor($diff / 3600);
                            $minutes = floor(($diff % 3600) / 60);
                            $duration = $hours . 'h ' . $minutes . 'm';
                        } else {
                            $duration = 'Still clocked in';
                        }
                    ?>
                        <div class="time-record">
                            <div class="time-record-header">
                                <div class="time-record-dates">
                                    <div><strong>Clock In:</strong> <?php echo date('m/d/Y g:i A', $clock_in_time); ?></div>
                                    <div><strong>Clock Out:</strong> <?php echo $clock_out_time ? date('m/d/Y g:i A', $clock_out_time) : 'Still clocked in'; ?></div>
                                </div>
                                <div class="time-record-duration">
                                    <div>Duration: <?php echo $duration; ?></div>
                                    <div>ID: <?php echo $record['id']; ?></div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <form action="edit_time_record.php" method="GET" style="display: inline;">
                                        <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                        <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_username); ?>">
                                        <button type="submit" class="edit-btn" style="background-color: #4CAF50; color: white;">Edit Times</button>
                                    </form>
                                    <form action="add_break.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="add_break">
                                        <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                        <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_username); ?>">
                                        <input type="hidden" name="db_source" value="time_db">
                                        <button type="submit" class="edit-btn" style="background-color: #2196F3; color: white;">Add Break</button>
                                    </form>
                                    <form action="admin_clock_actions.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_time_record">
                                        <input type="hidden" name="time_record_id" value="<?php echo $record['id']; ?>">
                                        <input type="hidden" name="redirect_back" value="true">
                                        <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_username); ?>">
                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this time record?');" class="edit-btn" style="background-color: #ff4d4d; color: white; margin-top: 5px;">Delete</button>
                                    </form>
                                </div>
                            </div>
                            
                            <?php if (count($record['breaks']) > 0) { ?>
                                <div class="break-records">
                                    <h4>Break Records (<?php echo count($record['breaks']); ?>)</h4>
                                    <?php foreach ($record['breaks'] as $break) { 
                                        $break_in_time = strtotime($break['break_in']);
                                        $break_out_time = $break['break_out'] ? strtotime($break['break_out']) : null;
                                        
                                        // Calculate break duration
                                        $break_duration = '';
                                        if ($break_out_time) {
                                            $diff = $break_out_time - $break_in_time;
                                            $minutes = floor($diff / 60);
                                            $break_duration = $minutes . ' min';
                                        } else {
                                            $break_duration = 'Still on break';
                                        }
                                    ?>
                                        <div class="break-record">
                                            <div><strong>Break Start:</strong> <?php echo date('g:i A', $break_in_time); ?></div>
                                            <div><strong>Break End:</strong> <?php echo $break_out_time ? date('g:i A', $break_out_time) : 'Still on break'; ?></div>
                                            <div><strong>Duration:</strong> <?php echo $break_duration; ?></div>
                                            <div style="margin-left: auto;">
                                                <form action="admin_clock_actions.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_break">
                                                    <input type="hidden" name="break_id" value="<?php echo $break['id']; ?>">
                                                    <input type="hidden" name="redirect_back" value="true">
                                                    <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_username); ?>">
                                                    <button type="submit" onclick="return confirm('Are you sure you want to delete this break record?');" class="edit-btn" style="background-color: #ff4d4d; color: white;">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="break-records">
                                    <p>No breaks recorded for this shift.</p>
                                </div>
                            <?php } ?>
                            
                            <div style="text-align: right; margin-top: 10px;">
                                <?php if (!$clock_out_time) { ?>
                                    <button type="button" class="clock-out-btn" onclick="openTimeEditorModal('<?php echo htmlspecialchars($selected_username); ?>', <?php echo $record['id']; ?>, true)">Clock Out</button>
                                <?php } ?>
                                <button type="button" class="edit-btn" onclick="openTimeEditorModal('<?php echo htmlspecialchars($selected_username); ?>', <?php echo $record['id']; ?>, false)">Edit Times</button>
                            </div>
                        </div>
                    <?php } ?>
                    
                    <?php if (isset($total_pages) && $total_pages > 1) { ?>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                                <li>
                                    <a href="?username=<?php echo urlencode($selected_username); ?>&page=<?php echo $i; ?>" <?php echo $page == $i ? 'class="active"' : ''; ?>>
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                <?php } else { ?>
                    <div class="no-records">
                        <p>No time records found for this employee.</p>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        
        <!-- Time Editor Modal -->
        <div id="timeEditorModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="modalTitle">Edit Time Record</h2>
                <p id="timeEditorEmployee"></p>
                
                <form method="post" id="timeEditorForm" action="process_time_edit.php">
                    <input type="hidden" name="action" value="edit_times">
                    <input type="hidden" id="editEmployeeUsername" name="edit_username">
                    <input type="hidden" id="editTimeRecordId" name="edit_time_record_id">
                    
                    <div class="form-group">
                        <label for="editDate">Date:</label>
                        <input type="date" id="editDate" name="edit_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editClockIn">Clock In Time:</label>
                        <input type="time" id="editClockIn" name="edit_clock_in" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editClockOut">Clock Out Time:</label>
                        <input type="time" id="editClockOut" name="edit_clock_out">
                    </div>
                    
                    <div class="break-records-container">
                        <h3>Break Records</h3>
                        <div id="breakRecordsContainer">
                            <!-- Break records will be loaded dynamically -->
                        </div>
                        
                        <button type="button" class="add-btn" id="addBreakBtn">Add Break</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="editNotes">Admin Notes:</label>
                        <textarea id="editNotes" name="edit_notes" placeholder="Reason for time adjustment"></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="cancel-btn" onclick="closeTimeEditorModal()">Cancel</button>
                        <button type="submit" class="edit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Time Editor Modal functionality
        const timeEditorModal = document.getElementById('timeEditorModal');
        const timeEditorSpan = timeEditorModal.querySelector('.close');
        const breakRecordsContainer = document.getElementById('breakRecordsContainer');
        const addBreakBtn = document.getElementById('addBreakBtn');
        
        function openTimeEditorModal(username, timeRecordId, isClockOut) {
            timeEditorModal.style.display = 'block';
            document.getElementById('timeEditorEmployee').textContent = 'Editing time record for: ' + username;
            document.getElementById('editEmployeeUsername').value = username;
            document.getElementById('editTimeRecordId').value = timeRecordId;
            
            // Clear previous form data
            document.getElementById('editNotes').value = '';
            breakRecordsContainer.innerHTML = '';
            
            // Fetch the time record data
            fetch('get_time_record.php?id=' + timeRecordId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const record = data.timeRecord;
                        
                        // Set date and times
                        const clockInDate = new Date(record.clock_in);
                        document.getElementById('editDate').value = clockInDate.toISOString().split('T')[0];
                        document.getElementById('editClockIn').value = clockInDate.toTimeString().substring(0, 5);
                        
                        if (record.clock_out) {
                            const clockOutDate = new Date(record.clock_out);
                            document.getElementById('editClockOut').value = clockOutDate.toTimeString().substring(0, 5);
                        } else {
                            document.getElementById('editClockOut').value = '';
                        }
                        
                        // Load break records
                        loadBreakRecords(record.breaks);
                    } else {
                        throw new Error(data.message || 'Failed to load time record');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading time record: ' + error.message);
                });
        }
        
        function loadBreakRecords(breaks) {
            breakRecordsContainer.innerHTML = '';
            
            if (breaks.length === 0) {
                breakRecordsContainer.innerHTML = '<div class="no-breaks">No breaks recorded for this shift.</div>';
                return;
            }
            
            breaks.forEach((breakRecord, index) => {
                const breakDiv = document.createElement('div');
                breakDiv.className = 'break-record';
                breakDiv.innerHTML = `
                    <div class="break-time-inputs">
                        <div class="form-group">
                            <label>Break Start</label>
                            <input type="time" name="break_in[]" value="${formatTime(breakRecord.break_in)}" required>
                        </div>
                        <div class="form-group">
                            <label>Break End</label>
                            <input type="time" name="break_out[]" value="${formatTime(breakRecord.break_out)}">
                        </div>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeBreakRecord(this)">Remove</button>
                `;
                breakRecordsContainer.appendChild(breakDiv);
            });
        }
        
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            return date.toTimeString().substring(0, 5);
        }
        
        function addBreakRecord() {
            const breakDiv = document.createElement('div');
            breakDiv.className = 'break-record';
            breakDiv.innerHTML = `
                <div class="break-time-inputs">
                    <div class="form-group">
                        <label>Break Start</label>
                        <input type="time" name="break_in[]" required>
                    </div>
                    <div class="form-group">
                        <label>Break End</label>
                        <input type="time" name="break_out[]">
                    </div>
                </div>
                <button type="button" class="remove-btn" onclick="removeBreakRecord(this)">Remove</button>
            `;
            breakRecordsContainer.appendChild(breakDiv);
        }
        
        function removeBreakRecord(button) {
            button.parentElement.remove();
        }
        
        function closeTimeEditorModal() {
            timeEditorModal.style.display = 'none';
        }
        
        // Event listeners
        timeEditorSpan.onclick = closeTimeEditorModal;
        addBreakBtn.onclick = addBreakRecord;
        
        window.onclick = function(event) {
            if (event.target == timeEditorModal) {
                closeTimeEditorModal();
            }
        };
    </script>
</body>
</html> 