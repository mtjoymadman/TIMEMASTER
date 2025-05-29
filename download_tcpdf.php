<?php
/**
 * TCPDF Download and Setup Script
 * 
 * This script downloads and extracts the TCPDF library to the appropriate location.
 * It handles error checking and reporting.
 */

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define constants
define('TCPDF_VERSION', '6.6.2');
define('TCPDF_URL', 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/' . TCPDF_VERSION . '.zip');
define('DOWNLOAD_FILE', 'tcpdf_' . TCPDF_VERSION . '.zip');
define('EXTRACT_PATH', 'lib/');
define('TARGET_PATH', 'lib/tcpdf/');

// Create directory if it doesn't exist
if (!file_exists(EXTRACT_PATH)) {
    if (!mkdir(EXTRACT_PATH, 0777, true)) {
        die("Error: Failed to create the extract directory at " . EXTRACT_PATH);
    }
    echo "Created directory: " . EXTRACT_PATH . "<br>";
}

// Download TCPDF
echo "Downloading TCPDF version " . TCPDF_VERSION . "...<br>";
if (!file_put_contents(DOWNLOAD_FILE, file_get_contents(TCPDF_URL))) {
    die("Error: Failed to download TCPDF. Check your internet connection and try again.");
}
echo "Download complete.<br>";

// Extract files
echo "Extracting files...<br>";
$zip = new ZipArchive;
if ($zip->open(DOWNLOAD_FILE) !== TRUE) {
    die("Error: Could not open the downloaded zip file.");
}

// Create target directory if it doesn't exist
if (!file_exists(TARGET_PATH)) {
    if (!mkdir(TARGET_PATH, 0777, true)) {
        die("Error: Failed to create the target directory at " . TARGET_PATH);
    }
    echo "Created directory: " . TARGET_PATH . "<br>";
}

// Extract the files to the target directory
$zip->extractTo(EXTRACT_PATH);
$zip->close();
echo "Files extracted.<br>";

// Move files from extract directory to target directory
$extracted_dir = EXTRACT_PATH . 'TCPDF-' . TCPDF_VERSION;
if (!file_exists($extracted_dir)) {
    die("Error: Extracted directory not found at " . $extracted_dir);
}

// Copy the core files (we don't need examples and other extra files)
$core_folders = ['config', 'fonts', 'include', 'tcpdf.php', 'LICENSE.TXT'];
foreach ($core_folders as $item) {
    $source = $extracted_dir . '/' . $item;
    $dest = TARGET_PATH . ($item == 'tcpdf.php' || $item == 'LICENSE.TXT' ? '' : ($item . '/'));
    
    if (!file_exists($source)) {
        echo "Warning: Source '" . $source . "' not found, skipping.<br>";
        continue;
    }
    
    if (is_dir($source)) {
        // Create target directory if it doesn't exist
        if (!file_exists($dest) && !mkdir($dest, 0777, true)) {
            die("Error: Failed to create directory " . $dest);
        }
        
        // Copy directory contents
        copy_directory($source, $dest);
        echo "Copied directory: " . $item . "<br>";
    } else {
        // Copy file
        if (!copy($source, $dest . basename($source))) {
            die("Error: Failed to copy " . $source . " to " . $dest);
        }
        echo "Copied file: " . $item . "<br>";
    }
}

// Clean up
echo "Cleaning up...<br>";
unlink(DOWNLOAD_FILE);
delete_directory($extracted_dir);
echo "Cleanup complete.<br>";

echo "<h2>TCPDF Setup Complete!</h2>";
echo "<p>The TCPDF library has been successfully installed to " . TARGET_PATH . "</p>";
echo "<p>You can now generate PDF reports from the reports section in the admin interface.</p>";
echo "<p><a href='index.php'>Return to TimeMaster</a></p>";

/**
 * Helper function to copy directory recursively
 */
function copy_directory($source, $dest) {
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        $src = $source . '/' . $file;
        $dst = $dest . '/' . $file;
        
        if (is_dir($src)) {
            if (!file_exists($dst)) {
                mkdir($dst, 0777, true);
            }
            copy_directory($src, $dst);
        } else {
            copy($src, $dst);
        }
    }
    closedir($dir);
}

/**
 * Helper function to delete directory recursively
 */
function delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}
?> 