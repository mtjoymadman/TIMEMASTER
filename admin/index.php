<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Use centralized session configuration
try {
    // Temporarily bypass session config for debugging
    // require_once __DIR__ . '/../../FLEETMASTER/GROK/grok.redlionsalvage.net/includes/session_config.php';
} catch (Exception $e) {
    die('Test stopped due to session configuration error.');
}

// Load required files
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
date_default_timezone_set('America/New_York');

// Initialize database connections
$grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
if ($grok_db->connect_error) {
    die("Grok database connection failed: " . $grok_db->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!(isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    header('Location: ../index.php');
    exit;
}

// Set admin mode in session
$_SESSION['admin_mode'] = true;

// Get logged in user
$logged_in_user = $_SESSION['username'];

// Process form submission
$error = '';
$success = '';

// Handle mode switching
if (isset($_POST['switch_mode']) && $_POST['switch_mode'] === 'employee') {
    unset($_SESSION['admin_mode']);
    header('Location: ../index.php');
    exit;
}

// Connect to time database
$time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
if ($time_db->connect_error) {
    die("Time database connection failed: " . $time_db->connect_error);
}

// Get employees with their current time status
$employees_with_status = [];

// First get all employees
$employees = [];
$result = $grok_db->query("SELECT username, role, suspended FROM employees ORDER BY username");
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// Then get time records and break status
foreach ($employees as &$employee) {
    // Get time record
    $stmt = $time_db->prepare("SELECT id, clock_in, clock_out FROM time_records WHERE username = ? AND clock_out IS NULL");
    $stmt->bind_param("s", $employee['username']);
    $stmt->execute();
    $timeResult = $stmt->get_result();
    
    if ($timeResult->num_rows > 0) {
        $timeRecord = $timeResult->fetch_assoc();
        $employee['time_record_id'] = $timeRecord['id'];
        $employee['raw_clock_in'] = $timeRecord['clock_in'];
        $employee['formatted_clock_in'] = date('h:i A', strtotime($timeRecord['clock_in']));
        
        // Get active breaks
        $stmt = $time_db->prepare("SELECT COUNT(*) as active_breaks FROM break_records WHERE time_record_id = ? AND break_out IS NULL");
        $stmt->bind_param("i", $timeRecord['id']);
        $stmt->execute();
        $breakResult = $stmt->get_result();
        $breakData = $breakResult->fetch_assoc();
        $employee['active_breaks'] = $breakData['active_breaks'];
    } else {
        $employee['time_record_id'] = null;
        $employee['raw_clock_in'] = null;
        $employee['formatted_clock_in'] = null;
        $employee['active_breaks'] = 0;
    }
    
    $employees_with_status[] = $employee;
}

// Get employees currently on the clock
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

// Get break records from time database
$break_records = [];
$result = $time_db->query("SELECT * FROM break_records ORDER BY break_in DESC");
while ($row = $result->fetch_assoc()) {
    $break_records[] = $row;
}

// Check for success or error messages from other pages
if (isset($_SESSION['success']) && isset($_SESSION['message'])) {
    if ($_SESSION['success']) {
        $success = $_SESSION['message'];
    } else {
        $error = $_SESSION['message'];
    }
    unset($_SESSION['success']);
    unset($_SESSION['message']);
}

// Count total time records
$total_time_records = 0;
$result = $time_db->query("SELECT COUNT(*) as count FROM time_records");
if ($result) {
    $total_time_records = $result->fetch_assoc()['count'];
}

// Get recent time records
$recent_records = [];
$result = $time_db->query("SELECT tr.*, e.username 
                          FROM time_records tr 
                          LEFT JOIN employees e ON tr.username = e.username 
                          ORDER BY tr.clock_in DESC 
                          LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dashboard-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .dashboard-card h2 {
            margin-top: 0;
            color: #e74c3c;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .dashboard-card p {
            margin-bottom: 20px;
            color: #aaa;
        }
        
        .dashboard-link {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .dashboard-link:hover {
            background-color: #c0392b;
        }
        
        .mode-switch {
            margin: 20px 0;
            text-align: center;
        }
        
        .mode-switch button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .mode-switch button:hover {
            background-color: #c0392b;
        }
        
        .dashboard-card i {
            display: block;
            font-size: 2rem;
            margin-bottom: 15px;
            color: #e74c3c;
        }
        
        .status-count {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: white;
        }

        /* Test Edit Interface Styles */
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .employee-card {
            background: white;
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
            color: #e74c3c;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .status-clocked-in {
            background-color: #27ae60;
            color: white;
        }

        .status-break {
            background-color: #f39c12;
            color: white;
        }

        .status-clocked-out {
            background-color: #ecf0f1;
            color: #2c3e50;
        }

        .time-info {
            font-size: 0.9em;
            color: #2c3e50;
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
            background-color: white;
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
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
            color: #e74c3c;
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
            background-color: #34495e;
            color: white;
        }

        .btn-secondary {
            background-color: #ecf0f1;
            color: #2c3e50;
        }

        .btn-danger {
            background-color: #c0392b;
            color: white;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .section-header {
            margin-top: 40px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Admin Dashboard</h1>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
                <a href="../index.php" class="btn" style="background-color: #e74c3c; color: white; text-decoration: none; padding: 8px 12px; border-radius: 4px; display: inline-flex; align-items: center;">
                    <i class="fas fa-home" style="margin-right: 5px;"></i> Home
                </a>
            </div>
            <div class="mode-switch">
                <form method="post">
                    <button type="submit" name="switch_mode" value="employee">Switch to Employee Mode</button>
                </form>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </header>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <i class="fas fa-clock"></i>
                <h2>Clock Management</h2>
                <p>Manage employee clock ins/outs and breaks</p>
                <div class="status-count"><?php echo count($on_clock); ?></div>
                <p>Employees currently on the clock</p>
                <a href="live_time_editor.php" class="dashboard-link">Open Clock Management</a>
            </div>
            
            <div class="dashboard-card">
                <i class="fas fa-edit"></i>
                <h2>Live Time Editor</h2>
                <p>Edit time records for employees currently on the clock</p>
                <div class="status-count"><?php echo count($on_clock); ?></div>
                <p>Active employees</p>
                <a href="live_time_editor.php" class="dashboard-link">Open Live Time Editor</a>
            </div>
            
            <div class="dashboard-card">
                <i class="fas fa-users"></i>
                <h2>Employee Management</h2>
                <p>Add, edit, or remove employees</p>
                <div class="status-count"><?php echo count($employees); ?></div>
                <p>Total employees</p>
                <a href="employee_management.php" class="dashboard-link">Manage Employees</a>
            </div>
            
            <div class="dashboard-card">
                <i class="fas fa-history"></i>
                <h2>Time Records</h2>
                <p>View and manage employee time records</p>
                <div class="status-count"><?php echo $total_time_records; ?></div>
                <p>Total time records</p>
                <a href="time_records.php" class="dashboard-link">View Time Records</a>
            </div>
            
            <div class="dashboard-card">
                <i class="fas fa-file-alt"></i>
                <h2>Reports</h2>
                <p>Generate and email reports</p>
                <a href="reports.php" class="dashboard-link">Generate Reports</a>
            </div>
            
            <div class="dashboard-card">
                <i class="fas fa-cog"></i>
                <h2>System Settings</h2>
                <p>Configure email and system settings</p>
                <a href="smtp_settings.php" class="dashboard-link">System Settings</a>
            </div>

            <!-- Add a Time Record -->
            <div class="dashboard-card">
                <i class="fas fa-clock"></i>
                <h2>Add a Time Record</h2>
                <p>Create or edit time records for employees.</p>
                <a href="clock_management.php" class="dashboard-link">Add Time Record</a>
            </div>

            <div class="dashboard-card">
                <i class="fas fa-calendar-alt"></i>
                <h2>Weekly Time Records</h2>
                <p>View the current week's time records for all employees</p>
                <a href="weekly_records.php" class="dashboard-link">View Weekly Records</a>
            </div>
        </div>

        <!-- Test Edit Interface Section -->
        <div class="section-header">
            <h2>Quick Time Edit Interface</h2>
            <p>Click on an employee to edit their time records</p>
        </div>

        <div class="employee-grid">
            <?php foreach ($employees_with_status as $employee): ?>
                <div class="employee-card" onclick="showEditOptions('<?php echo htmlspecialchars($employee['username']); ?>', <?php echo $employee['time_record_id'] ? 'true' : 'false'; ?>)">
                    <div class="employee-name"><?php echo htmlspecialchars($employee['username']); ?></div>
                    <?php if ($employee['time_record_id']): ?>
                        <?php if ($employee['active_breaks'] > 0): ?>
                            <div class="status-badge status-break">On Break</div>
                        <?php else: ?>
                            <div class="status-badge status-clocked-in">Clocked In</div>
                        <?php endif; ?>
                        <div class="time-info">
                            Clocked in: <?php 
                                // Just use the SQL-formatted time directly
                                if (isset($employee['formatted_clock_in'])) {
                                    echo htmlspecialchars($employee['formatted_clock_in']) . ' ET';
                                } else {
                                    echo 'Unknown time';
                                }
                                
                                // Debug info - only show for admin user
                                if ($logged_in_user === 'admin') {
                                    echo "<br><small style='color:#999;'>Raw: " . htmlspecialchars($employee['raw_clock_in']) . "</small>";
                                }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="status-badge status-clocked-out">Clocked Out</div>
                    <?php endif; ?>
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
            <h2>Edit Current Session</h2>
            <form id="currentSessionForm" class="edit-form">
                <div class="form-group">
                    <label>Clock In Time:</label>
                    <input type="time" id="clockInTime">
                </div>
                <div class="form-group">
                    <label>Current Break Time (if any):</label>
                    <input type="time" id="breakTime">
                </div>
                <div class="form-group">
                    <label>Break Out Time (if on break):</label>
                    <input type="time" id="breakOutTime">
                </div>
                <div class="form-group">
                    <label>Clock Out Time:</label>
                    <input type="time" id="clockOutTime">
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-primary" id="saveChangesBtn">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('currentSessionModal')">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deleteCurrentSession()">Delete Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // General utility functions and event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin dashboard loaded');
            
            // Add event listener for save changes button
            document.getElementById('saveChangesBtn').addEventListener('click', function() {
                saveCurrentSession();
            });
        });

        // Test Edit Interface Scripts
        let selectedUsername = '';
        let hasActiveSession = false;
        let currentTimeRecordId = null;

        function showEditOptions(username, hasSession) {
            selectedUsername = username;
            hasActiveSession = hasSession;
            document.getElementById('selectedEmployee').textContent = username;
            
            // Show/hide buttons based on whether the employee is clocked in
            document.getElementById('editCurrentBtn').style.display = hasSession ? 'inline-block' : 'none';
            document.getElementById('deleteRecordBtn').style.display = hasSession ? 'inline-block' : 'none';
            document.getElementById('clockInBtn').style.display = hasSession ? 'none' : 'inline-block';
            
            document.getElementById('editOptionsModal').style.display = 'block';
            
            // Get the time record ID if there's an active session
            if (hasSession) {
                fetch(`get_current_session.php?username=${encodeURIComponent(username)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentTimeRecordId = data.time_record_id;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching time record ID:', error);
                    });
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        async function editCurrentSession() {
            closeModal('editOptionsModal');
            document.getElementById('currentSessionModal').style.display = 'block';
            
            if (hasActiveSession) {
                try {
                    const response = await fetch(`get_current_session.php?username=${encodeURIComponent(selectedUsername)}`);
                    if (!response.ok) {
                        throw new Error('Failed to fetch current session');
                    }
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load session data');
                    }
                    
                    console.log('Raw session data:', data); // Log raw data for debugging
                    
                    // Store time record ID for saving
                    currentTimeRecordId = data.time_record_id;
                    
                    // Set current times in form - handle potential missing data
                    if (data.clock_in) {
                        document.getElementById('clockInTime').value = data.clock_in;
                    }
                    
                    // Debug output for admin
                    if ('<?php echo $logged_in_user; ?>' === 'admin') {
                        console.log('Clock in value set to:', data.clock_in || 'not set');
                        console.log('Raw data:', data.raw_data || {});
                    }
                    
                    if (data.current_break) {
                        document.getElementById('breakTime').value = data.current_break;
                    } else {
                        document.getElementById('breakTime').value = '';
                    }
                    
                    if (data.break_out) {
                        document.getElementById('breakOutTime').value = data.break_out;
                    } else {
                        document.getElementById('breakOutTime').value = '';
                    }
                    
                    if (data.clock_out) {
                        document.getElementById('clockOutTime').value = data.clock_out;
                    } else {
                        document.getElementById('clockOutTime').value = '';
                    }
                } catch (error) {
                    alert('Error loading current session: ' + error.message);
                    closeModal('currentSessionModal');
                }
            }
        }

        function editSavedRecords() {
            window.location.href = `time_records.php?username=${encodeURIComponent(selectedUsername)}`;
        }

        async function saveCurrentSession() {
            const clockInTime = document.getElementById('clockInTime').value;
            const breakTime = document.getElementById('breakTime').value;
            const breakOutTime = document.getElementById('breakOutTime').value;
            const clockOutTime = document.getElementById('clockOutTime').value;
            
            try {
                const formData = new FormData();
                formData.append('username', selectedUsername);
                formData.append('time_record_id', currentTimeRecordId);
                formData.append('timezone', 'America/New_York'); // Explicitly specify Eastern Time
                
                // Only append fields that have values
                if (clockInTime) {
                    formData.append('clock_in_time', clockInTime);
                }
                if (breakTime) {
                    formData.append('break_time', breakTime);
                }
                if (breakOutTime) {
                    formData.append('break_out_time', breakOutTime);
                }
                if (clockOutTime) {
                    formData.append('clock_out_time', clockOutTime);
                }
                
                const response = await fetch('save_current_session.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Failed to save changes');
                }
                
                const data = await response.json();
                if (data.success) {
                    alert('Changes saved successfully');
                    closeModal('currentSessionModal');
                    // Refresh the page to show updated times
                    window.location.reload();
                } else {
                    throw new Error(data.error || 'Failed to save changes');
                }
            } catch (error) {
                alert('Error saving changes: ' + error.message);
            }
        }

        function promptDeleteRecord() {
            if (!hasActiveSession || !currentTimeRecordId) {
                alert('No active time record to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete the time record for ${selectedUsername}? This action cannot be undone.`)) {
                deleteRecord();
            }
        }

        function deleteRecord() {
            const formData = new FormData();
            formData.append('time_record_id', currentTimeRecordId);
            formData.append('username', selectedUsername);
            formData.append('action', 'delete');
            
            fetch('delete_time_record.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Time record deleted successfully');
                    closeModal('editOptionsModal');
                    // Refresh the page to show updated status
                    window.location.reload();
                } else {
                    throw new Error(data.error || 'Failed to delete record');
                }
            })
            .catch(error => {
                alert('Error deleting record: ' + error.message);
            });
        }

        async function deleteCurrentSession() {
            if (!currentTimeRecordId) {
                alert('No time record selected');
                return;
            }

            if (confirm(`Are you sure you want to delete the time record for ${selectedUsername}? This action cannot be undone.`)) {
                try {
                    const formData = new FormData();
                    formData.append('time_record_id', currentTimeRecordId);
                    formData.append('username', selectedUsername);
                    formData.append('action', 'delete');
                    
                    const response = await fetch('delete_time_record.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error('Failed to delete record');
                    }
                    
                    const data = await response.json();
                    if (data.success) {
                        alert('Time record deleted successfully');
                        closeModal('currentSessionModal');
                        // Refresh the page to show updated status
                        window.location.reload();
                    } else {
                        throw new Error(data.error || 'Failed to delete record');
                    }
                } catch (error) {
                    alert('Error deleting record: ' + error.message);
                }
            }
        }

        function clockInEmployee() {
            if (confirm(`Are you sure you want to clock in ${selectedUsername}?`)) {
                const formData = new FormData();
                formData.append('username', selectedUsername);
                formData.append('action', 'admin_clock_in');
                
                fetch('admin_clock_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`${selectedUsername} has been clocked in successfully`);
                        closeModal('editOptionsModal');
                        // Refresh the page to show updated status
                        window.location.reload();
                    } else {
                        throw new Error(data.error || 'Failed to clock in employee');
                    }
                })
                .catch(error => {
                    alert('Error clocking in employee: ' + error.message);
                });
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
// Close database connections at the very end
$grok_db->close();
$time_db->close();
?>