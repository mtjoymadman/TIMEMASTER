<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session directly like GROK/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/', '.redlionsalvage.net', true, true);
    session_start();
}

// Output a debug message to confirm script execution
echo '<p>Script started. Session initialized directly.</p>';
echo '<pre>Debug Session Data: ' . print_r($_SESSION, true) . '</pre>';

// Load required files with error checking
try {
    echo '<p>Loading config.php...</p>';
    require_once __DIR__ . '/config.php';
    echo '<p>config.php loaded successfully.</p>';
    
    echo '<p>Loading functions.php...</p>';
    require_once __DIR__ . '/functions.php';
    echo '<p>functions.php loaded successfully.</p>';
} catch (Exception $e) {
    echo '<p>Error loading required files: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die('Test stopped due to error in loading required files.');
}

try {
    // Test database connection
    echo '<p>Testing database connection...</p>';
    $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
    if ($time_db->connect_error) {
        echo '<p>Time database connection failed: ' . htmlspecialchars($time_db->connect_error) . '</p>';
        die('Test stopped due to database connection failure.');
    } else {
        echo '<p>Time database connection successful.</p>';
        $time_db->close();
    }

    // Test a function call if available
    if (function_exists('getEmployee') && isset($_SESSION['username'])) {
        echo '<p>Testing getEmployee function...</p>';
        $employee = getEmployee($_SESSION['username']);
        if ($employee) {
            echo '<p>getEmployee function call successful. Employee data retrieved.</p>';
        } else {
            echo '<p>getEmployee function call failed. No employee data found.</p>';
        }
    } else {
        echo '<p>getEmployee function not available or no username in session. Skipping test.</p>';
    }

    // Check if user is already logged in via TimeMaster session
    if (isset($_SESSION['fleet_user_id']) && !empty($_SESSION['fleet_user_id'])) {
        error_log('TimeMaster login.php - Existing TimeMaster session found');
        if (isset($_SESSION['fleet_role']) && $_SESSION['fleet_role'] === 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }

    // Check for GROK authentication variables
    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        error_log('TimeMaster login.php - GROK authenticated user found: ' . $_SESSION['username']);
        $_SESSION['fleet_user_id'] = $_SESSION['username'];
        $_SESSION['fleet_role'] = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
        if ($_SESSION['fleet_role'] === 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }

    echo '<p>No authenticated session found. Displaying login form...</p>';

    $error = '';
    if (isset($_GET['error']) && $_GET['error'] === 'suspended') {
        $error = 'Your account has been suspended. Please contact your administrator.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        
        if (empty($username)) {
            $error = 'Please enter your username';
        } else {
            if (authenticateUser($username, '')) {
                $_SESSION['username'] = $username;
                $_SESSION['fleet_user_id'] = $username;
                // Get the user's role
                $employee = getEmployee($username);
                if ($employee) {
                    if (isSuspended($username)) {
                        $error = 'Your account has been suspended. Please contact your administrator.';
                        session_destroy();
                    } else {
                        $_SESSION['role'] = $employee['role'];
                        $_SESSION['fleet_role'] = $employee['role'];
                        error_log('TimeMaster login.php - User authenticated: ' . $username);
                        if ($employee['role'] === 'admin') {
                            header('Location: admin/index.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Employee record not found';
                }
            } else {
                $error = 'Invalid username';
            }
        }
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    error_log("Login error stack trace: " . $e->getTraceAsString());
    echo '<p>Error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>Stack Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    $error = 'An error occurred during login. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TIMEMASTER</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h1>TIMEMASTER Login</h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>