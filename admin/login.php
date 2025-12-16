<?php
/**
 * Admin Login Page (if needed)
 * 
 * This file exists to handle any admin-specific login logic.
 * If admin/login.php is being accessed, redirect to main login.
 */

// Redirect to main login page
header("Location: ../login.php");
exit();

