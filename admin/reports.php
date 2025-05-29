<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Set timezone
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
$result = $db->query("SELECT username FROM employees ORDER BY username");
while ($row = $result->fetch_assoc()) {
    $employees[] = $row['username'];
}

// Close database connection
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - TIMEMASTER</title>
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
        
        .reports-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            flex: 1;
            min-width: 300px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .report-card h3 {
            color: #e74c3c;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }
        
        .report-card p {
            margin-bottom: 20px;
            color: #ccc;
        }
        
        .report-card-actions {
            text-align: right;
        }
        
        .form-section {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            color: #e74c3c;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select,
        .form-group input[type="date"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 8px 10px;
            background-color: #333;
            border: 1px solid #444;
            border-radius: 4px;
            color: white;
        }
        
        .email-addresses {
            background-color: #333;
            border: 1px solid #444;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .email-address {
            display: inline-block;
            background-color: #444;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            margin: 2px;
        }
        
        .email-address .remove {
            margin-left: 5px;
            cursor: pointer;
            color: #e74c3c;
        }
        
        .button-group {
            margin-top: 20px;
            text-align: right;
        }
        
        /* Checkbox group styling */
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .checkbox-item label {
            margin: 0;
        }
        
        /* Form controls */
        .form-controls {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Direct email input */
        input[type="text"][name="email_addresses"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .field-note {
            margin-top: 5px;
            font-size: 0.8em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Reports</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <h2>Available Reports</h2>
        
        <div class="reports-container">
            <div class="report-card">
                <h3><i class="fas fa-user-clock"></i> Employee Hours Report</h3>
                <p>Generate a report showing hours worked for specific employees across a chosen date range.</p>
                <div class="report-card-actions">
                    <button type="button" class="edit-btn" onclick="showReportForm('employee-hours')">Generate Report</button>
                </div>
            </div>
            
            <div class="report-card">
                <h3><i class="fas fa-calendar-alt"></i> Weekly Timesheet Report</h3>
                <p>Create a weekly timesheet report for all active employees or selected individuals.</p>
                <div class="report-card-actions">
                    <button type="button" class="edit-btn" onclick="showReportForm('weekly-timesheet')">Generate Report</button>
                </div>
            </div>
            
            <div class="report-card">
                <h3><i class="fas fa-hourglass-half"></i> Break Time Analysis</h3>
                <p>Analyze break patterns and duration for employees over a selected period.</p>
                <div class="report-card-actions">
                    <button type="button" class="edit-btn" onclick="showReportForm('break-analysis')">Generate Report</button>
                </div>
            </div>
        </div>
        
        <!-- Employee Hours Report Form -->
        <div id="employee-hours-form" class="form-section" style="display: none;">
            <h3>Employee Hours Report</h3>
            <form action="generate_report.php" method="post">
                <input type="hidden" name="report_type" value="employee-hours">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_select">Select Employees:</label>
                        <select id="employee_select" name="employees[]" multiple size="6">
                            <option value="all">All Employees</option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php } ?>
                        </select>
                        <small>Hold Ctrl to select multiple employees</small>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="direct_email">Email Report To:</label>
                    <input type="text" id="direct_email" name="email_addresses" 
                           placeholder="Enter comma-separated email addresses">
                    <small>Enter multiple emails separated by commas</small>
                </div>
                
                <div class="form-group">
                    <label for="report_notes">Additional Notes:</label>
                    <textarea id="report_notes" name="report_notes" rows="3" placeholder="Enter any additional notes for the report"></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="hideReportForm('employee-hours')">Cancel</button>
                    <button type="submit" class="edit-btn">Generate & Send Report</button>
                </div>
            </form>
        </div>
        
        <!-- Weekly Timesheet Report Form -->
        <div id="weekly-timesheet-form" class="form-section" style="display: none;">
            <h3>Weekly Timesheet Report</h3>
            <form action="generate_report.php" method="post">
                <input type="hidden" name="report_type" value="weekly-timesheet">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="week_select">Select Week:</label>
                        <select id="week_select" name="week" required>
                            <option value="">Select a week...</option>
                            <?php
                            // Generate options for the last 4 weeks
                            $current_date = new DateTime();
                            for ($i = 0; $i < 4; $i++) {
                                $start_of_week = clone $current_date;
                                $start_of_week->modify('monday this week');
                                $end_of_week = clone $start_of_week;
                                $end_of_week->modify('+6 days');
                                
                                $week_string = $start_of_week->format('Y-m-d') . ' to ' . $end_of_week->format('Y-m-d');
                                $week_value = $start_of_week->format('Y-m-d');
                                
                                echo '<option value="' . htmlspecialchars($week_value) . '">' . htmlspecialchars($week_string) . '</option>';
                                
                                $current_date->modify('-1 week');
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employee_select_weekly">Select Employees:</label>
                        <select id="employee_select_weekly" name="employees[]" multiple size="6">
                            <option value="all">All Employees</option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php } ?>
                        </select>
                        <small>Hold Ctrl to select multiple employees</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="direct_email_weekly">Email Report To:</label>
                    <input type="text" id="direct_email_weekly" name="email_addresses" 
                           placeholder="Enter comma-separated email addresses">
                    <small>Enter multiple emails separated by commas</small>
                </div>
                
                <div class="form-group">
                    <label for="report_notes_weekly">Additional Notes:</label>
                    <textarea id="report_notes_weekly" name="report_notes" rows="3" placeholder="Enter any additional notes for the report"></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="hideReportForm('weekly-timesheet')">Cancel</button>
                    <button type="submit" class="edit-btn">Generate & Send Report</button>
                </div>
            </form>
        </div>
        
        <!-- Break Analysis Report Form -->
        <div id="break-analysis-form" class="form-section" style="display: none;">
            <h3>Break Time Analysis Report</h3>
            <form action="generate_report.php" method="post">
                <input type="hidden" name="report_type" value="break-analysis">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_select_break">Select Employees:</label>
                        <select id="employee_select_break" name="employees[]" multiple size="6">
                            <option value="all">All Employees</option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo htmlspecialchars($emp); ?>"><?php echo htmlspecialchars($emp); ?></option>
                            <?php } ?>
                        </select>
                        <small>Hold Ctrl to select multiple employees</small>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="start_date_break">Start Date:</label>
                            <input type="date" id="start_date_break" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date_break">End Date:</label>
                            <input type="date" id="end_date_break" name="end_date" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="direct_email_break">Email Report To:</label>
                    <input type="text" id="direct_email_break" name="email_addresses" 
                           placeholder="Enter comma-separated email addresses">
                    <small>Enter multiple emails separated by commas</small>
                </div>
                
                <div class="form-group">
                    <label for="report_notes_break">Additional Notes:</label>
                    <textarea id="report_notes_break" name="report_notes" rows="3" placeholder="Enter any additional notes for the report"></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="hideReportForm('break-analysis')">Cancel</button>
                    <button type="submit" class="edit-btn">Generate & Send Report</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Initialize date fields with current date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
            const oneWeekAgoFormatted = oneWeekAgo.toISOString().split('T')[0];
            
            // Set default dates for employee hours report
            document.getElementById('start_date').value = oneWeekAgoFormatted;
            document.getElementById('end_date').value = today;
            
            // Set default dates for break analysis report
            document.getElementById('start_date_break').value = oneWeekAgoFormatted;
            document.getElementById('end_date_break').value = today;
            
            // Set default week
            if (document.getElementById('week_select')) {
                const selectElement = document.getElementById('week_select');
                if (selectElement.options.length > 0) {
                    selectElement.selectedIndex = 0;
                }
            }
        });
        
        // Show report form
        function showReportForm(formType) {
            // Hide all forms first
            document.getElementById('employee-hours-form').style.display = 'none';
            document.getElementById('weekly-timesheet-form').style.display = 'none';
            document.getElementById('break-analysis-form').style.display = 'none';
            
            // Show the selected form
            document.getElementById(formType + '-form').style.display = 'block';
            
            // Scroll to the form
            document.getElementById(formType + '-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Hide report form
        function hideReportForm(formType) {
            document.getElementById(formType + '-form').style.display = 'none';
        }
    </script>
</body>
</html> 