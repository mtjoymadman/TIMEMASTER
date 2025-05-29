<?php
// Fix column names in break_records table if needed
require_once 'config.php';

echo "Checking database structure...\n";

$tables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

echo "Tables found: " . implode(", ", $tables) . "\n\n";

// Check if break_records exists
$break_records_exists = in_array('break_records', $tables);
if ($break_records_exists) {
    echo "break_records table found.\n";
    
    // Get column structure
    $columns = [];
    $result = $db->query("DESCRIBE break_records");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
    
    echo "Current columns in break_records: " . implode(", ", array_keys($columns)) . "\n";
    
    // Check for column name mismatches
    $mismatches = [];
    
    // Check if record_id exists but time_record_id is used in code
    if (isset($columns['record_id']) && !isset($columns['time_record_id'])) {
        $mismatches[] = ['old' => 'record_id', 'new' => 'time_record_id'];
    }
    
    // Check if break_start exists but break_in is used in code
    if (isset($columns['break_start']) && !isset($columns['break_in'])) {
        $mismatches[] = ['old' => 'break_start', 'new' => 'break_in'];
    }
    
    // Check if break_end exists but break_out is used in code
    if (isset($columns['break_end']) && !isset($columns['break_out'])) {
        $mismatches[] = ['old' => 'break_end', 'new' => 'break_out'];
    }
    
    if (count($mismatches) > 0) {
        echo "\nColumn name mismatches found:\n";
        foreach ($mismatches as $mismatch) {
            echo "'{$mismatch['old']}' in database, '{$mismatch['new']}' in code\n";
        }
        
        echo "\nWould you like to fix these mismatches? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        
        if (strtolower($line) === 'y') {
            echo "\nFixing column names...\n";
            
            // Start transaction
            $db->begin_transaction();
            
            try {
                foreach ($mismatches as $mismatch) {
                    $sql = "ALTER TABLE break_records CHANGE {$mismatch['old']} {$mismatch['new']} {$columns[$mismatch['old']]}";
                    echo "Executing: $sql\n";
                    $db->query($sql);
                }
                
                // Commit the transaction
                $db->commit();
                echo "Column names fixed successfully.\n";
            } catch (Exception $e) {
                // Rollback on error
                $db->rollback();
                echo "Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "No changes made.\n";
        }
    } else {
        echo "No column name mismatches found.\n";
    }
} else if (in_array('breaks', $tables)) {
    echo "break_records table not found, but 'breaks' table exists.\n";
    echo "The application is likely using the 'breaks' table instead.\n";
    
    // Check if the breaks table has the expected structure
    $columns = [];
    $result = $db->query("DESCRIBE breaks");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
    
    echo "Current columns in breaks: " . implode(", ", array_keys($columns)) . "\n";
    
    // Check if auto_added column exists
    if (!isset($columns['auto_added'])) {
        echo "\nThe 'auto_added' column is missing from the breaks table.\n";
        echo "Would you like to add it? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        
        if (strtolower($line) === 'y') {
            try {
                $db->query("ALTER TABLE breaks ADD COLUMN auto_added TINYINT(1) DEFAULT 0");
                echo "Added 'auto_added' column to breaks table.\n";
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "No changes made.\n";
        }
    } else {
        echo "The 'auto_added' column already exists in the breaks table.\n";
    }
} else {
    echo "Neither break_records nor breaks table found. Database structure may be incorrect.\n";
}

echo "\nDone.\n";
?> 