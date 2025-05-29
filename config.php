<?php
// Define constant to prevent double inclusion
define('TIMEMASTER_CONFIG_LOADED', true);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin/employee_management_errors.log');

// Start session with cross-subdomain settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_domain' => '.redlionsalvage.net',
        'cookie_path' => '/',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// TIMEZONE CONFIGURATION - EASTERN TIME ONLY
// This application is hardcoded to only use Eastern Time (America/New_York)
// No other timezone is supported or allowed
date_default_timezone_set('America/New_York');

// Database configuration for time records
define('TIME_DB_HOST', 'localhost');
define('TIME_DB_USER', 'salvageyard_time');
define('TIME_DB_PASS', '7361dead');
define('TIME_DB_NAME', 'salvageyard_time');

// Database configuration for employee data (grok)
define('GROK_DB_HOST', 'localhost');
define('GROK_DB_USER', 'salvageyard_grok');
define('GROK_DB_PASS', '7361dead');
define('GROK_DB_NAME', 'salvageyard_grok');

// Email configuration
define('SMTP_HOST', 'mail.supremecenter.com');
define('SMTP_USER', 'time@time.redlionsalvage.net');
define('SMTP_PASS', '7361-Dead');
define('NOTIFY_EMAIL', 'mtjoymadman@gmail.com');

// Initialize global database connections with proper error handling
function initializeDatabases() {
    global $time_db, $grok_db;
    
    try {
        error_log("Attempting to initialize database connections...");
        
        // Initialize time database connection
        error_log("Connecting to time database...");
        $time_db = new mysqli(TIME_DB_HOST, TIME_DB_USER, TIME_DB_PASS, TIME_DB_NAME);
        if ($time_db->connect_error) {
            throw new Exception("Time database connection failed: " . $time_db->connect_error);
        }
        error_log("Time database connection successful");
        
        // Initialize grok database connection
        error_log("Connecting to grok database...");
        $grok_db = new mysqli(GROK_DB_HOST, GROK_DB_USER, GROK_DB_PASS, GROK_DB_NAME);
        if ($grok_db->connect_error) {
            throw new Exception("Grok database connection failed: " . $grok_db->connect_error);
        }
        error_log("Grok database connection successful");
        
        // Set timezone for both database connections
        error_log("Setting timezone for database connections...");
        if (!$time_db->query("SET time_zone = '-04:00'")) {
            throw new Exception("Failed to set timezone for time database: " . $time_db->error);
        }
        if (!$grok_db->query("SET time_zone = '-04:00'")) {
            throw new Exception("Failed to set timezone for grok database: " . $grok_db->error);
        }
        error_log("Timezone set successfully for both databases");
        
        // Make connections available globally
        $GLOBALS['time_db'] = $time_db;
        $GLOBALS['grok_db'] = $grok_db;
        
        error_log("Database initialization completed successfully");
        return true;
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        return false;
    }
}

// Helper function to ensure consistent Eastern Time handling
function formatInEasternTime($datetime, $format = 'h:i A') {
    if (empty($datetime)) return '';
    
    // Explicitly create in Eastern Time
    $dt = new DateTime($datetime, new DateTimeZone('America/New_York'));
    return $dt->format($format) . ' ET';
}

// Initialize database connections
if (!initializeDatabases()) {
    die("Failed to initialize database connections. Please check the error log for details.");
}

// For debugging in error_log
if (basename($_SERVER['PHP_SELF']) == 'auto_auth.php' || basename($_SERVER['PHP_SELF']) == 'login.php') {
    error_log("TimeMaster Session data in " . basename($_SERVER['PHP_SELF']) . ": " . print_r($_SESSION, true));
}
?>