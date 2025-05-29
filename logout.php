<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Store the user role before destroying the session
$username = $_SESSION['username'] ?? '';
$is_admin = $username ? hasRole($username, 'admin') : false;

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - TIMEMASTER</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #2c3e50;
            color: #ecf0f1;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .logout-container {
            background-color: #34495e;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 1.2em;
            margin-bottom: 30px;
            color: #2ecc71;
        }
        
        .button {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .button:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <h1>TIMEMASTER</h1>
        <div class="message">
            You have been successfully logged out.
        </div>
        <a href="https://grok.redlionsalvage.net/<?php echo $is_admin ? 'admin/index.php' : 'employee/index.php'; ?>" class="button">
            Return to <?php echo $is_admin ? 'Admin' : 'Employee'; ?> Dashboard
        </a>
    </div>
</body>
</html> 