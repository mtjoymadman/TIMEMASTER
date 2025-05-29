<?php
session_start();

// Show all submitted form data
echo "<h1>Form Debug Information</h1>";

echo "<h2>POST Data</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>FILES Data</h2>";
echo "<pre>";
print_r($_FILES);
echo "</pre>";

echo "<h2>SESSION Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Simple test form
?>

<h2>Test Email Form</h2>
<form method="post" action="generate_report.php">
    <input type="hidden" name="report_type" value="test">
    
    <label for="direct_email">Direct Email Input:</label>
    <input type="text" name="email_addresses" id="direct_email" value="test@example.com">
    
    <button type="submit">Test Submit</button>
</form>

<p><a href="reports.php">Back to Reports</a></p> 