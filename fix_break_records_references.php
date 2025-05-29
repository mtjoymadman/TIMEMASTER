<?php
// Script to fix all references to break_records table columns
require_once 'config.php';

// Define the directory to search
$rootDir = __DIR__;

// Define the search patterns and replacements
$replacements = [
    // Fix column name references in SQL queries
    '/break_records WHERE time_record_id/' => 'break_records WHERE record_id',
    '/break_records.*?time_record_id =/' => 'break_records WHERE record_id =',
    '/break_records.*?time_record_id=/' => 'break_records WHERE record_id=',
    '/from break_records.*?time_record_id/' => 'from break_records b WHERE record_id',
    '/FROM break_records.*?time_record_id/' => 'FROM break_records b WHERE record_id',
    '/JOIN break_records.*?time_record_id/' => 'JOIN break_records b ON t.id = b.record_id',
    '/join break_records.*?time_record_id/' => 'join break_records b ON t.id = b.record_id',
    '/break_records.*?break_in/' => 'break_records b WHERE break_start',
    '/break_records.*?break_out/' => 'break_records b WHERE break_end',
    '/\bbreak_in\b/' => 'break_start',
    '/\bbreak_out\b/' => 'break_end',
    '/INSERT INTO break_records \(time_record_id/' => 'INSERT INTO break_records (record_id',
    '/UPDATE break_records SET break_in/' => 'UPDATE break_records SET break_start',
    '/UPDATE break_records SET break_out/' => 'UPDATE break_records SET break_end',
    // Add more patterns as needed
];

// Initialize counters
$processedFiles = 0;
$modifiedFiles = 0;
$errors = [];

// Function to scan and fix a directory
function scanAndFixDirectory($dir, &$processedFiles, &$modifiedFiles, &$errors, $replacements) {
    $entries = scandir($dir);
    
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.git') {
            continue;
        }
        
        $path = $dir . '/' . $entry;
        
        if (is_dir($path)) {
            scanAndFixDirectory($path, $processedFiles, $modifiedFiles, $errors, $replacements);
        } else if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            fixFile($path, $processedFiles, $modifiedFiles, $errors, $replacements);
        }
    }
}

// Function to fix a single file
function fixFile($file, &$processedFiles, &$modifiedFiles, &$errors, $replacements) {
    try {
        $content = file_get_contents($file);
        $originalContent = $content;
        $processedFiles++;
        
        $content = preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
        
        if ($content !== $originalContent) {
            file_put_contents($file, $content);
            $modifiedFiles++;
            echo "Updated: $file\n";
        }
    } catch (Exception $e) {
        $errors[] = "Error processing $file: " . $e->getMessage();
    }
}

// Run the scan and fix
echo "Starting to scan and fix break_records references...\n";
scanAndFixDirectory($rootDir, $processedFiles, $modifiedFiles, $errors, $replacements);

// Report results
echo "\nProcess completed.\n";
echo "Files processed: $processedFiles\n";
echo "Files modified: $modifiedFiles\n";

if (count($errors) > 0) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
} else {
    echo "\nNo errors encountered.\n";
}

// Create SQL script to check and update auto_added column
echo "\nCreating SQL script to update auto_added column...\n";

$sqlFile = $rootDir . '/fix_breaks_table.sql';
$sql = <<<SQL
-- Check tables and columns
SHOW TABLES LIKE 'breaks';
SHOW TABLES LIKE 'break_records';

-- Check if auto_added column exists in breaks table
SHOW COLUMNS FROM breaks LIKE 'auto_added';

-- Add auto_added column to breaks table if it doesn't exist (uncomment if needed)
-- ALTER TABLE breaks ADD COLUMN auto_added TINYINT(1) DEFAULT 0;

-- Mark automatic breaks
UPDATE breaks b
JOIN time_records t ON b.time_record_id = t.id
SET b.auto_added = 1
WHERE b.break_time = 30 
AND b.break_out IS NOT NULL
AND TIMESTAMPDIFF(HOUR, t.clock_in, t.clock_out) >= 8
AND (b.auto_added = 0 OR b.auto_added IS NULL);

SQL;

file_put_contents($sqlFile, $sql);
echo "SQL script created at: $sqlFile\n";
?> 