<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../functions.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Set up error logging
$log_file = __DIR__ . '/employee_management_errors.log';
ini_set('error_log', $log_file);
error_log("Starting employee management script...");

// Get database connections from global scope
global $time_db, $grok_db;

// Check if database connections are initialized
if (!isset($time_db) || !isset($grok_db)) {
    error_log("Database connections not initialized, attempting to initialize...");
    
    // Initialize grok database connection
    error_log("Connecting to grok database...");
    $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
    if ($grok_db->connect_error) {
        $error_msg = "Grok database connection failed: " . $grok_db->connect_error;
        error_log($error_msg);
        die("Database connection error: " . $error_msg);
    }
    error_log("Grok database connection successful");
    
    // Initialize time database connection
    error_log("Connecting to time database...");
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        $error_msg = "Time database connection failed: " . $time_db->connect_error;
        error_log($error_msg);
        die("Database connection error: " . $error_msg);
    }
    error_log("Time database connection successful");
    
    // Set timezone for both databases
    error_log("Setting timezone for database connections...");
    $timezone = 'America/New_York';
    $grok_db->query("SET time_zone = '$timezone'");
    $time_db->query("SET time_zone = '$timezone'");
    error_log("Timezone set successfully for both databases");
    
    error_log("Database initialization completed successfully");
}

// Verify database connections are active
if (!$grok_db->ping()) {
    $error_msg = "Grok database connection is not active: " . $grok_db->error;
    error_log($error_msg);
    die("Database connection error: " . $error_msg);
}

error_log("Database connections verified and ready");

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

// At the top of the file, after session_start()
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Handle adding/modifying employees
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        error_log("POST request received. POST data: " . print_r($_POST, true));
        
        if (isset($_POST['add'])) {
            error_log("Starting new employee addition process in grok database...");
            error_log("Raw POST data: " . print_r($_POST, true));
            
            // Add new employee
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $flag_auto_break = isset($_POST['flag_auto_break']) ? 1 : 0;
            $suspended = isset($_POST['suspended']) ? 1 : 0;
            $roles = isset($_POST['roles']) ? $_POST['roles'] : ['employee'];
            
            error_log("Processing new employee with username: " . $username);
            error_log("Roles: " . print_r($roles, true));
            
            if (empty($username)) {
                error_log("Error: Username is empty");
                throw new Exception("Username cannot be empty");
            }
            
            // Check if username already exists in grok database
            error_log("Checking if username exists in grok database...");
            $stmt = $grok_db->prepare("SELECT username FROM employees WHERE username = ?");
            if (!$stmt) {
                error_log("Error preparing statement: " . $grok_db->error);
                throw new Exception("Failed to prepare statement: " . $grok_db->error);
            }
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                error_log("Error executing statement: " . $stmt->error);
                throw new Exception("Failed to check username: " . $stmt->error);
            }
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                error_log("Error: Username already exists in grok database");
                throw new Exception("Username already exists");
            }
            
            // Insert new employee into grok database
            error_log("Preparing to insert new employee into grok database...");
            $stmt = $grok_db->prepare("INSERT INTO employees (username, role, suspended, flag_auto_break) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Error preparing insert statement: " . $grok_db->error);
                throw new Exception("Failed to prepare insert statement: " . $grok_db->error);
            }
            
            // Ensure 'employee' role is always included and roles are unique
            if (!is_array($roles)) {
                $roles = ['employee'];
            }
            $roles = array_unique(array_merge(['employee'], $roles));
            $role_str = implode(',', $roles);
            
            error_log("Final roles string: " . $role_str);
            error_log("Inserting employee with data: username=" . $username . ", roles=" . $role_str . ", suspended=" . $suspended . ", flag_auto_break=" . $flag_auto_break);
            
            $stmt->bind_param("ssii", $username, $role_str, $suspended, $flag_auto_break);
            
            if (!$stmt->execute()) {
                error_log("Error executing insert statement: " . $stmt->error);
                throw new Exception("Failed to add employee: " . $stmt->error);
            }
            
            $success = "Successfully added employee: $username";
            error_log("Successfully added new employee to grok database: " . $username);
            
            // Redirect to show success message
            header("Location: employee_management.php?success=" . urlencode($success));
            exit;
            
        } elseif (isset($_POST['modify'])) {
            error_log("Starting employee modification process in grok database...");
            error_log("Raw POST data: " . print_r($_POST, true));
            
            $old_username = trim($_POST['old_username']);
            $new_username = trim($_POST['username']);
            $flag_auto_break = isset($_POST['flag_auto_break']) ? 1 : 0;
            $suspended = isset($_POST['suspended']) ? 1 : 0;
            
            // Process roles in grok database
            error_log("Processing roles from POST data for grok database...");
            $roles = [];
            
            if (isset($_POST['roles']) && is_array($_POST['roles'])) {
                $roles = $_POST['roles'];
                error_log("Found roles array: " . print_r($roles, true));
            } else {
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'roles[') === 0 || strpos($key, 'roles[]') === 0) {
                        $roles[] = $value;
                    }
                }
                error_log("Found individual role fields: " . print_r($roles, true));
            }
            
            // Clean up roles
            $roles = array_map('trim', $roles);
            $roles = array_filter($roles);
            
            // Always include 'employee' role
            if (!in_array('employee', $roles)) {
                $roles[] = 'employee';
            }
            
            // Remove duplicates and sort
            $roles = array_unique($roles);
            sort($roles);
            
            $role_str = implode(',', $roles);
            error_log("Final processed roles for grok database: " . print_r($roles, true));
            error_log("Final role string: " . $role_str);
            
            // Update employee in grok database
            $update_query = "UPDATE employees SET username = ?, role = ?, suspended = ?, flag_auto_break = ? WHERE username = ?";
            error_log("Update query for grok database: " . $update_query);
            error_log("Parameters: username=$new_username, role=$role_str, suspended=$suspended, flag_auto_break=$flag_auto_break, old_username=$old_username");
            
            $stmt = $grok_db->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement in grok database: " . $grok_db->error);
            }
            
            $stmt->bind_param("ssiis", $new_username, $role_str, $suspended, $flag_auto_break, $old_username);
            
            if (!$stmt->execute()) {
                error_log("Update failed in grok database: " . $stmt->error);
                throw new Exception("Failed to modify employee in grok database: " . $stmt->error);
            }
            
            $success = "Successfully modified employee in grok database: $old_username";
            error_log("Successfully modified employee in grok database: $old_username");
            
            // Redirect to refresh the page and show success message
            header("Location: employee_management.php?success=" . urlencode($success));
            exit;
            
        } elseif (isset($_POST['delete']) && isset($_POST['confirm_delete'])) {
            error_log("Starting employee deletion process in grok database...");
            error_log("Raw POST data: " . print_r($_POST, true));
            
            $username = isset($_POST['old_username']) ? trim($_POST['old_username']) : '';
            
            if (empty($username)) {
                error_log("Error: Username is empty for deletion");
                throw new Exception("Username cannot be empty");
            }
            
            // Delete employee from grok database
            error_log("Preparing to delete employee from grok database: " . $username);
            $stmt = $grok_db->prepare("DELETE FROM employees WHERE username = ?");
            if (!$stmt) {
                error_log("Error preparing delete statement: " . $grok_db->error);
                throw new Exception("Failed to prepare delete statement: " . $grok_db->error);
            }
            
            $stmt->bind_param("s", $username);
            
            if (!$stmt->execute()) {
                error_log("Error executing delete statement: " . $stmt->error);
                throw new Exception("Failed to delete employee: " . $stmt->error);
            }
            
            $success = "Successfully deleted employee: $username";
            error_log("Successfully deleted employee from grok database: " . $username);
            
            // Redirect to show success message
            header("Location: employee_management.php?success=" . urlencode($success));
            exit;
        }
    } catch (Exception $e) {
        error_log("Error in employee management: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Get list of employees from grok database
$employees = [];
try {
    error_log("Starting employee data fetch process from grok database...");
    
    // Check if employees table exists in grok database
    $table_check = $grok_db->query("SHOW TABLES LIKE 'employees'");
    if (!$table_check) {
        $error_msg = "Failed to check for employees table in grok database: " . $grok_db->error;
        error_log($error_msg);
        die("Database error: " . $error_msg);
    }
    
    if ($table_check->num_rows == 0) {
        $error_msg = "Employees table does not exist in the grok database";
        error_log($error_msg);
        die("Database error: " . $error_msg);
    }
    
    // Fetch employees from grok database
    $result = $grok_db->query("SELECT username, role, suspended, flag_auto_break FROM employees ORDER BY username");
    if (!$result) {
        $error_msg = "Failed to fetch employees from grok database: " . $grok_db->error;
        error_log($error_msg);
        die("Database error: " . $error_msg);
    }
    
    while ($row = $result->fetch_assoc()) {
        error_log("Processing employee from grok database: " . $row['username']);
        
        // Process roles
        $roles = array_filter(explode(',', $row['role']), 'trim');
        if (empty($roles)) {
            $roles = ['employee'];
        }
        $row['role'] = implode(',', $roles);
        $employees[] = $row;
    }
    error_log("Successfully processed " . count($employees) . " employee records from grok database");
} catch (Exception $e) {
    $error_msg = "Error fetching employees from grok database: " . $e->getMessage();
    error_log($error_msg);
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - TIMEMASTER</title>
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
        
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .employee-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .employee-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: white;
        }
        
        .employee-roles {
            margin-bottom: 10px;
            color: #aaa;
        }
        
        .employee-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        
        .status-active {
            background-color: #2ecc71;
            color: white;
        }
        
        .status-suspended {
            background-color: #e74c3c;
            color: white;
        }
        
        .employee-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .employee-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .auto-break-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #3498db;
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #2a2a2a;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
            color: white;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
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
            color: #aaa;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background-color: #333;
            color: white;
        }
        
        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .edit-btn, .cancel-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .edit-btn {
            background-color: #3498db;
            color: white;
        }
        
        .cancel-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .edit-btn:hover {
            background-color: #2980b9;
        }
        
        .cancel-btn:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Employee Management</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="<?php echo hasRole($logged_in_user, 'admin') ? '../admin/index.php' : '../employee.php'; ?>" class="back-button"><i class="fas fa-arrow-left"></i> <?php echo hasRole($logged_in_user, 'admin') ? 'Back to Time Dashboard' : 'Back to Yardmaster Dashboard'; ?></a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <div class="admin-section">
            <h2>Manage Employees</h2>
            <button id="addEmployeeButton" class="edit-btn">Add New Employee</button>
            
            <div class="employee-grid">
                <?php foreach ($employees as $employee) { 
                    // Split roles and ensure they're properly formatted
                    $roles = array_filter(explode(',', $employee['role']), 'trim');
                    if (empty($roles)) {
                        $roles = ['employee']; // Default to employee if no roles
                    }
                    $isSuspended = $employee['suspended'] == 1;
                    $hasAutoBreak = $employee['flag_auto_break'] == 1;
                    
                    error_log("Displaying employee: " . $employee['username'] . " with roles: " . implode(',', $roles));
                ?>
                <div class="employee-card">
                    <?php if ($hasAutoBreak) { ?>
                        <div class="auto-break-badge">Auto Break</div>
                    <?php } ?>
                    <div class="employee-name"><?php echo htmlspecialchars($employee['username']); ?></div>
                    <div class="employee-roles">
                        <strong>Roles:</strong> 
                        <?php 
                        // Display each role with proper formatting
                        $formattedRoles = array_map(function($role) {
                            return ucwords(trim($role));
                        }, $roles);
                        echo htmlspecialchars(implode(', ', $formattedRoles)); 
                        ?>
                    </div>
                    <div class="employee-status <?php echo $isSuspended ? 'status-suspended' : 'status-active'; ?>">
                        <?php echo $isSuspended ? 'Suspended' : 'Active'; ?>
                    </div>
                    <div class="employee-actions">
                        <button class="edit-btn" onclick="editEmployee('<?php echo htmlspecialchars($employee['username']); ?>', <?php echo $hasAutoBreak ? 'true' : 'false'; ?>, <?php echo $isSuspended ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars(json_encode($roles)); ?>')">Edit</button>
                        <button class="cancel-btn" onclick="confirmDelete('<?php echo htmlspecialchars($employee['username']); ?>')">Delete</button>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Employee Modal -->
        <div id="employeeModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="employeeModalTitle">Add New Employee</h2>
                
                <form method="post" id="employeeForm" onsubmit="return handleFormSubmit(event)">
                    <input type="hidden" id="old_username" name="old_username">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" id="flag_auto_break" name="flag_auto_break" value="1">
                            Flagged for Auto Break
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" id="suspended" name="suspended" value="1">
                            Suspended
                        </label>
                    </div>
                    <div>
                        <h3>Roles:</h3>
                        <div class="role-checkboxes">
                            <label><input type="checkbox" id="role_employee" name="roles[]" value="employee" checked disabled> Employee (required)</label>
                            <label><input type="checkbox" id="role_admin" name="roles[]" value="admin"> Admin</label>
                            <label><input type="checkbox" id="role_baby_admin" name="roles[]" value="baby admin"> Baby Admin</label>
                            <label><input type="checkbox" id="role_driver" name="roles[]" value="driver"> Driver</label>
                            <label><input type="checkbox" id="role_yardman" name="roles[]" value="yardman"> Yardman</label>
                            <label><input type="checkbox" id="role_office" name="roles[]" value="office"> Office</label>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                        <button type="submit" name="modify" id="saveEmployeeBtn" class="edit-btn">Save Changes</button>
                        <button type="submit" name="add" id="addEmployeeBtn" style="display: none;" class="edit-btn">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeDeleteModal()">&times;</span>
                <h2>Confirm Delete</h2>
                <p>Are you sure you want to delete <span id="deleteEmployeeName"></span>? This action cannot be undone.</p>
                <form method="post" id="deleteForm">
                    <input type="hidden" id="delete_username" name="old_username">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="confirm_delete" required>
                            I understand this action cannot be undone
                        </label>
                    </div>
                    <div class="button-group">
                        <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" name="delete" class="cancel-btn">Delete Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Add event listener for Add New Employee button
    document.getElementById('addEmployeeButton').addEventListener('click', function() {
        console.log('Opening Add New Employee modal');
        document.getElementById('employeeModalTitle').textContent = 'Add New Employee';
        document.getElementById('old_username').value = '';
        document.getElementById('username').value = '';
        document.getElementById('flag_auto_break').checked = false;
        document.getElementById('suspended').checked = false;
        
        // Reset all role checkboxes
        const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
        roleCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Set employee role as default
        document.getElementById('role_employee').checked = true;
        
        document.getElementById('addEmployeeBtn').style.display = 'inline-block';
        document.getElementById('saveEmployeeBtn').style.display = 'none';
        
        document.getElementById('employeeModal').style.display = 'block';
    });

    function handleFormSubmit(event) {
        event.preventDefault();
        console.log('Form submission started');
        
        // Get all role checkboxes
        const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
        const selectedRoles = [];
        
        // Collect selected roles
        roleCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedRoles.push(checkbox.value);
            }
        });
        
        console.log('Selected roles:', selectedRoles);
        
        // Ensure at least one role is selected
        if (selectedRoles.length === 0) {
            alert('Please select at least one role');
            return false;
        }
        
        // Create a new form element
        const newForm = document.createElement('form');
        newForm.method = 'post';
        newForm.action = '';
        
        // Add all form fields except roles
        const formData = new FormData(event.target);
        for (let [key, value] of formData.entries()) {
            if (key !== 'roles[]') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                newForm.appendChild(input);
            }
        }
        
        // Add roles
        selectedRoles.forEach(role => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'roles[]';
            input.value = role;
            newForm.appendChild(input);
        });
        
        // Add the appropriate action parameter
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = document.getElementById('addEmployeeBtn').style.display === 'none' ? 'modify' : 'add';
        actionInput.value = '1';
        newForm.appendChild(actionInput);
        
        // Add the form to the document and submit it
        document.body.appendChild(newForm);
        console.log('Submitting form with data:', {
            username: document.getElementById('username').value,
            old_username: document.getElementById('old_username').value,
            roles: selectedRoles,
            flag_auto_break: document.getElementById('flag_auto_break').checked,
            suspended: document.getElementById('suspended').checked,
            action: document.getElementById('addEmployeeBtn').style.display === 'none' ? 'modify' : 'add'
        });
        newForm.submit();
        return false;
    }

    // Open modal for editing employee
    function editEmployee(username, autoBreak, suspended, roles) {
        console.log('Editing employee:', username);
        console.log('Current roles:', roles);
        
        document.getElementById('employeeModalTitle').textContent = 'Edit Employee';
        document.getElementById('old_username').value = username;
        document.getElementById('username').value = username;
        document.getElementById('flag_auto_break').checked = autoBreak;
        document.getElementById('suspended').checked = suspended;
        
        // Reset all role checkboxes
        const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
        roleCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Set roles safely
        try {
            const roleArray = JSON.parse(roles);
            console.log('Parsed roles:', roleArray);
            if (Array.isArray(roleArray)) {
                roleArray.forEach(role => {
                    if (role && typeof role === 'string') {
                        const roleId = 'role_' + role.toLowerCase().replace(/\s+/g, '_');
                        const element = document.getElementById(roleId);
                        if (element) {
                            element.checked = true;
                            console.log('Setting role:', roleId, 'to checked');
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error parsing roles:', e);
        }
        
        document.getElementById('addEmployeeBtn').style.display = 'none';
        document.getElementById('saveEmployeeBtn').style.display = 'inline-block';
        
        document.getElementById('employeeModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('employeeModal').style.display = 'none';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    function confirmDelete(username) {
        console.log('Confirming delete for employee:', username);
        document.getElementById('deleteEmployeeName').textContent = username;
        document.getElementById('delete_username').value = username;
        document.getElementById('deleteModal').style.display = 'block';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('employeeModal')) {
            closeModal();
        }
        if (event.target == document.getElementById('deleteModal')) {
            closeDeleteModal();
        }
    };

    // Add event listener for delete form submission
    document.getElementById('deleteForm').addEventListener('submit', function(event) {
        event.preventDefault();
        console.log('Delete form submission started');
        
        if (!document.querySelector('input[name="confirm_delete"]').checked) {
            alert('Please confirm that you understand this action cannot be undone');
            return false;
        }
        
        // Create a new form element
        const newForm = document.createElement('form');
        newForm.method = 'post';
        newForm.action = '';
        
        // Add the username and delete parameters
        const usernameInput = document.createElement('input');
        usernameInput.type = 'hidden';
        usernameInput.name = 'old_username';
        usernameInput.value = document.getElementById('delete_username').value;
        newForm.appendChild(usernameInput);
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete';
        deleteInput.value = '1';
        newForm.appendChild(deleteInput);
        
        const confirmInput = document.createElement('input');
        confirmInput.type = 'hidden';
        confirmInput.name = 'confirm_delete';
        confirmInput.value = '1';
        newForm.appendChild(confirmInput);
        
        // Add the form to the document and submit it
        document.body.appendChild(newForm);
        console.log('Submitting delete form for employee:', document.getElementById('delete_username').value);
        newForm.submit();
        return false;
    });
    </script>
</body>
</html> 