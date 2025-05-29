<?php
/**
 * PHPMailer Setup Script for TIMEMASTER
 * 
 * This script downloads and installs PHPMailer for the TIMEMASTER application.
 */

// Enable error reporting for setup
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>TIMEMASTER - PHPMailer Setup</h1>";

// Create the lib/PHPMailer directory if it doesn't exist
$phpmailer_dir = __DIR__ . '/lib/PHPMailer';
if (!file_exists($phpmailer_dir)) {
    echo "<p>Creating PHPMailer directory...</p>";
    if (!is_dir(__DIR__ . '/lib')) {
        if (!mkdir(__DIR__ . '/lib', 0755, true)) {
            die("<p>Error: Failed to create lib directory. Please check permissions.</p>");
        }
    }
    
    if (!mkdir($phpmailer_dir, 0755, true)) {
        die("<p>Error: Failed to create PHPMailer directory. Please check permissions.</p>");
    }
    echo "<p>Directory created successfully.</p>";
} else {
    echo "<p>PHPMailer directory already exists.</p>";
}

// Function to download and extract PHPMailer
function downloadAndExtractPHPMailer($phpmailer_dir) {
    $zipFile = __DIR__ . '/phpmailer.zip';
    
    echo "<p>Downloading PHPMailer...</p>";
    // GitHub URL for the latest PHPMailer release
    $url = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.1.zip';
    
    // Download the file
    $fileContent = file_get_contents($url);
    if ($fileContent === false) {
        die("<p>Error: Failed to download PHPMailer. Please check your internet connection.</p>");
    }
    
    // Save the file
    if (file_put_contents($zipFile, $fileContent) === false) {
        die("<p>Error: Failed to save PHPMailer zip file. Please check permissions.</p>");
    }
    
    echo "<p>Extracting PHPMailer...</p>";
    
    // Extract the zip file
    $zip = new ZipArchive;
    $res = $zip->open($zipFile);
    if ($res !== true) {
        die("<p>Error: Failed to open PHPMailer zip file. Error code: $res</p>");
    }
    
    // Extract to a temporary directory
    $tempDir = __DIR__ . '/phpmailer_temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $zip->extractTo($tempDir);
    $zip->close();
    
    // Move the src directory to the final location
    $srcDir = glob($tempDir . '/PHPMailer-*/src')[0];
    if (!file_exists($srcDir)) {
        die("<p>Error: PHPMailer src directory not found in the extracted files.</p>");
    }
    
    // Create the PHPMailer/src directory
    if (!is_dir($phpmailer_dir . '/src')) {
        mkdir($phpmailer_dir . '/src', 0755, true);
    }
    
    // Copy all files from the src directory
    $files = glob($srcDir . '/*');
    foreach ($files as $file) {
        $fileName = basename($file);
        copy($file, $phpmailer_dir . '/src/' . $fileName);
    }
    
    // Clean up
    unlink($zipFile);
    deleteDirectory($tempDir);
    
    echo "<p>PHPMailer has been installed successfully!</p>";
    return true;
}

// Helper function to delete a directory and its contents
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            if (is_dir($dir . "/" . $object)) {
                deleteDirectory($dir . "/" . $object);
            } else {
                unlink($dir . "/" . $object);
            }
        }
    }
    rmdir($dir);
}

// Check if PHPMailer is already installed
if (!file_exists($phpmailer_dir . '/src/PHPMailer.php')) {
    // Download and install PHPMailer
    if (downloadAndExtractPHPMailer($phpmailer_dir)) {
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px 0; border-radius: 4px;'>";
        echo "<strong>Success!</strong> PHPMailer has been installed successfully.";
        echo "</div>";
    }
} else {
    echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px 0; border-radius: 4px;'>";
    echo "<strong>Info:</strong> PHPMailer is already installed.";
    echo "</div>";
}

// Final instructions
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Go to the <a href='admin/smtp_settings.php'>SMTP Settings page</a> to configure your email server.</li>";
echo "<li>Configure your SMTP settings with the information provided by your email service provider.</li>";
echo "<li>Send a test email to verify that everything is working correctly.</li>";
echo "</ol>";

echo "<p><a href='admin/index.php'>Return to Admin Dashboard</a></p>";
?> 