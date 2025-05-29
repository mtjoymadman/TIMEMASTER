<?php
// Start session and include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../FLEETMASTER/GROK/grok.redlionsalvage.net/includes/session_config.php';

// Function to check if user is already authenticated in Yardmaster
function checkYardmasterAuth() {
    global $grok_db;
    
    try {
        // Debug session data
        error_log("TimeMaster auto_auth: Full session data: " . print_r($_SESSION, true));
        error_log("TimeMaster auto_auth: Session ID: " . session_id());
        
        // First check if we already have a TimeMaster session
        if (isset($_SESSION['fleet_user_id']) && !empty($_SESSION['fleet_user_id'])) {
            error_log("TimeMaster auto_auth: Found existing TimeMaster session");
            header('Location: index.php');
            exit;
        }
        
        // Check if we have a username in session (from login.php)
        if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
            $username = $_SESSION['username'];
            error_log("TimeMaster auto_auth: Found username in session: " . $username);
            
            $employee = getEmployee($username);
            if ($employee) {
                error_log("TimeMaster auto_auth: Found employee record");
                
                // Set TimeMaster session variables
                $_SESSION['fleet_user_id'] = $employee['id'];
                $_SESSION['fleet_role'] = $employee['role'];
                $_SESSION['role'] = $employee['role'];
                
                error_log("TimeMaster auto_auth: Set session variables - fleet_user_id: " . $_SESSION['fleet_user_id'] . ", role: " . $_SESSION['role']);
                
                // Check if user is suspended
                if (isSuspended($username)) {
                    error_log("TimeMaster auto_auth: User is suspended");
                    header('Location: login.php?error=suspended');
                    exit;
                }
                
                // Redirect based on role
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    error_log("TimeMaster auto_auth: Redirecting admin to live_time_editor.php");
                    header('Location: admin/live_time_editor.php');
                } else {
                    error_log("TimeMaster auto_auth: Redirecting user to index.php");
                    header('Location: index.php');
                }
                exit;
            }
        }
        
        // If no valid session or employee not found, redirect to login
        error_log("TimeMaster auto_auth: No valid session found, redirecting to login");
        header('Location: login.php');
        exit;
        
    } catch (Exception $e) {
        error_log("TimeMaster auto_auth error: " . $e->getMessage());
        error_log("TimeMaster auto_auth stack trace: " . $e->getTraceAsString());
        header('Location: login.php');
        exit;
    }
}

// Execute the authentication check
checkYardmasterAuth();
?> 