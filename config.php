<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'salvageyard_time'); // Adjust if needed based on your MySQL setup
define('DB_PASS', '7361dead');
define('DB_NAME', 'salvageyard_time');
define('SMTP_HOST', 'mail.supremecenter.com');
define('SMTP_USER', 'time@time.redlionsalvage.net');
define('SMTP_PASS', '736-Dead');
define('NOTIFY_EMAIL', 'MTJOYMADMAN@GMAIL.COM'); // Replace with your admin email for notifications

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
?>