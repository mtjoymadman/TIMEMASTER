<?php
// Script to fix all break_records table column references in the TIMEMASTER application
// This script updates all PHP files to ensure they use the correct column names:
// - break_start instead of break_in
// - break_end instead of break_out
// - record_id instead of time_record_id

// Include configuration
require_once '../config.php';

// Set Eastern Time
date_default_timezone_set('America/New_York');

echo "<h1>Break Records Table Fix</h1>";
echo "<pre>";

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// First check if the breaks table exists and break_records doesn't
$result = $conn->query("SHOW TABLES LIKE 'breaks'");
$breaks_exists = $result->num_rows > 0;

$result = $conn->query("SHOW TABLES LIKE 'break_records'");
$break_records_exists = $result->num_rows > 0;

echo "Current database state:\n";
echo "- 'breaks' table exists: " . ($breaks_exists ? "Yes" : "No") . "\n";
echo "- 'break_records' table exists: " . ($break_records_exists ? "Yes" : "No") . "\n\n";

if ($breaks_exists && !$break_records_exists) {
    echo "Creating break_records table from breaks table...\n";
    
    // Create the break_records table with the new column names
    $result = $conn->query("CREATE TABLE break_records LIKE breaks");
    
    if (!$result) {
        die("Failed to create break_records table: " . $conn->error);
    }
    
    // Rename the columns in the new table
    $conn->query("ALTER TABLE break_records 
                 CHANGE COLUMN time_record_id record_id INT(11) NOT NULL,
                 CHANGE COLUMN break_in break_start DATETIME NOT NULL,
                 CHANGE COLUMN break_out break_end DATETIME NULL DEFAULT NULL");
    
    // Copy data from breaks to break_records
    $conn->query("INSERT INTO break_records 
                 SELECT id, time_record_id as record_id, break_in as break_start, 
                        break_out as break_end, break_time, auto_added 
                 FROM breaks");
    
    echo "Created break_records table and copied data.\n";
} elseif ($break_records_exists) {
    echo "The break_records table already exists.\n";
}

// Define directories to scan
$dirs = [
    '../admin',
    '../'
];

// Define patterns to search for and their replacements
$patterns = [
    // Database table references
    '/FROM\s+breaks\b/i' => 'FROM break_records',
    '/JOIN\s+breaks\b/i' => 'JOIN break_records',
    '/INTO\s+breaks\b/i' => 'INTO break_records',
    '/UPDATE\s+breaks\b/i' => 'UPDATE break_records',
    '/DELETE\s+FROM\s+breaks\b/i' => 'DELETE FROM break_records',
    
    // Column references
    '/\btime_record_id\b/' => 'record_id',
    '/\bbreak_in\b/' => 'break_start',
    '/\bbreak_out\b/' => 'break_end'
];

// Initialize counters
$files_processed = 0;
$files_modified = 0;

// Scan directories and fix files
foreach ($dirs as $dir) {
    echo "Scanning directory: $dir\n";
    
    $dir_path = realpath($dir);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir_path),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        // Skip directories and non-PHP files
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }
        
        $filepath = $file->getRealPath();
        $files_processed++;
        
        // Read file contents
        $content = file_get_contents($filepath);
        $original_content = $content;
        
        // Apply all pattern replacements
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // If content was modified, save it back
        if ($content !== $original_content) {
            if (file_put_contents($filepath, $content)) {
                echo "  - Modified: " . basename($filepath) . "\n";
                $files_modified++;
            } else {
                echo "  - Failed to write: " . basename($filepath) . "\n";
            }
        }
    }
}

echo "\nProcess completed.\n";
echo "Files processed: $files_processed\n";
echo "Files modified: $files_modified\n";

// Check for broken references after modification
$problem_files = [];

// Function to check if a file has potential problems
function checkFileForIssues($filepath, $conn) {
    $content = file_get_contents($filepath);
    
    // Look for potential issues
    $issues = [];
    
    // Check for "breaks" table references that might have been missed
    if (preg_match('/\bbreaks\b/i', $content) && !preg_match('/\'breaks\'|"breaks"|break_records/i', $content)) {
        $issues[] = "Possible missed 'breaks' table reference";
    }
    
    // Check for break_in/break_out references that might have been missed
    if (preg_match('/\bbreak_in\b/i', $content) && !preg_match('/\'break_in\'|"break_in"|break_start/i', $content)) {
        $issues[] = "Possible missed 'break_in' column reference";
    }
    
    if (preg_match('/\bbreak_out\b/i', $content) && !preg_match('/\'break_out\'|"break_out"|break_end/i', $content)) {
        $issues[] = "Possible missed 'break_out' column reference";
    }
    
    // Check for time_record_id references that might have been missed
    if (preg_match('/\btime_record_id\b/i', $content) && !preg_match('/\'time_record_id\'|"time_record_id"|record_id/i', $content)) {
        $issues[] = "Possible missed 'time_record_id' column reference";
    }
    
    return $issues;
}

echo "\nChecking for potential issues after modifications...\n";

foreach ($dirs as $dir) {
    $dir_path = realpath($dir);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir_path),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }
        
        $filepath = $file->getRealPath();
        $issues = checkFileForIssues($filepath, $conn);
        
        if (!empty($issues)) {
            $problem_files[$filepath] = $issues;
            echo "  - " . basename($filepath) . " has potential issues:\n";
            foreach ($issues as $issue) {
                echo "      * $issue\n";
            }
        }
    }
}

if (empty($problem_files)) {
    echo "No potential issues found.\n";
} else {
    echo "\nFiles with potential issues: " . count($problem_files) . "\n";
    echo "Please review these files manually.\n";
}

echo "</pre>";
echo "<p><a href='index.php'>Return to Dashboard</a></p>";

$conn->close();
?> 