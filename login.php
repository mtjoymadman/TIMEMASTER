<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Load required files
require_once 'config.php';
require_once 'functions.php';

// Check IP access before proceeding (only if IP access control is available and table exists)
if (file_exists(__DIR__ . '/includes/ip_access_control.php')) {
    require_once __DIR__ . '/includes/ip_access_control.php';
    // Only check IP if the table exists (to avoid errors during initial setup)
    try {
        global $time_db;
        $table_check = $time_db->query("SHOW TABLES LIKE 'ip_access_control'");
        if ($table_check && $table_check->num_rows > 0) {
            requireIpAccess($time_db, 'timemaster', '', '/login.php?error=ip_blocked');
        }
    } catch (Exception $e) {
        // If IP table doesn't exist yet, skip IP check (for initial setup)
        error_log("IP access control table not found, skipping IP check: " . $e->getMessage());
    }
}

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
// This application only uses America/New_York timezone
date_default_timezone_set('America/New_York');

// Clear any existing output
if (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering
ob_start();

// Log session state for debugging
error_log("=== LOGIN.PHP DEBUG START ===");
error_log("Session state at start of login.php: " . print_r($_SESSION, true));
error_log("Session cookie parameters: " . print_r(session_get_cookie_params(), true));
error_log("Server name: " . $_SERVER['SERVER_NAME']);
error_log("HTTPS: " . (isset($_SERVER['HTTPS']) ? 'on' : 'off'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Script filename: " . $_SERVER['SCRIPT_FILENAME']);
error_log("Session ID: " . session_id());

$error = '';
$success = '';

// Handle cache busting (without destroying session)
if (isset($_GET['clean']) && $_GET['clean'] == '1' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Set cache busting headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Login attempt - POST received");
    
    // Get username from form
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    error_log("Username submitted: " . $username);
    
    if (empty($username)) {
        $error = "Please enter your username";
    } else {
        // Query database for employee
        $stmt = $time_db->prepare("SELECT username, role FROM employees WHERE username = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $time_db->error);
            $error = "System error. Please try again.";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            error_log("Query executed - rows found: " . $result->num_rows);
            
            if ($result->num_rows === 1) {
                $employee = $result->fetch_assoc();
                error_log("Employee found: " . print_r($employee, true));
                
                // Set session variables directly (simple approach)
                $_SESSION['username'] = $employee['username'];
                $_SESSION['role'] = $employee['role'];
                $_SESSION['last_activity'] = time();
                
                error_log("Session data after setting: " . print_r($_SESSION, true));
                error_log("User " . $employee['username'] . " logged in successfully");
                
                // Check if user is admin (simple string check)
                $is_admin = (strpos($employee['role'], 'admin') !== false);
                error_log("Role check for " . $employee['username'] . ": role=" . $employee['role'] . ", is_admin=" . ($is_admin ? 'true' : 'false'));
                error_log("Is admin: " . ($is_admin ? 'yes' : 'no'));
                
                if ($is_admin) {
                    error_log("Admin user - redirecting to admin dashboard");
                    error_log("Attempting redirect to: admin/index.php");
                    header("Location: admin/index.php");
                    exit();
                } else {
                    error_log("Regular user - redirecting to employee portal");
                    error_log("Attempting redirect to: employee_portal.php");
                    header("Location: employee_portal.php");
                    exit();
                }
            } else {
                $error = "Invalid username";
            }
            $stmt->close();
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    error_log("User already logged in as: " . $username);
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("Cookie data: " . print_r($_COOKIE, true));
    
    // Check if user is admin (simple string check)
    $is_admin = (strpos($_SESSION['role'], 'admin') !== false);
    error_log("Role check for " . $username . ": role=" . $_SESSION['role'] . ", is_admin=" . ($is_admin ? 'true' : 'false'));
    error_log("Is admin: " . ($is_admin ? 'yes' : 'no'));
    
    if ($is_admin) {
        error_log("Admin user - redirecting to admin dashboard");
        error_log("Attempting redirect to: admin/index.php");
        header("Location: admin/index.php");
    } else {
        error_log("Regular user - redirecting to employee portal");
        error_log("Attempting redirect to: employee_portal.php");
        header("Location: employee_portal.php");
    }
    exit();
}

error_log("=== LOGIN.PHP DEBUG END ===");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TIMEMASTER - Login</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/session_cleanup.js"></script>
</head>
<body>
    <div class="login-container">
        <h1>TIMEMASTER Login</h1>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>
</body>
</html>
<?php
// Close database connection
$time_db->close();
?>