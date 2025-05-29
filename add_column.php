<?php
// Script to add auto_added column to breaks table
require_once 'config.php';

echo "Adding auto_added column to breaks table...\n";

try {
    // First check if the column already exists
    $result = $db->query("SHOW COLUMNS FROM breaks LIKE 'auto_added'");
    
    if ($result->num_rows === 0) {
        // The column doesn't exist, so add it
        $result = $db->query("ALTER TABLE breaks ADD COLUMN auto_added TINYINT(1) DEFAULT 0");
        
        if ($result) {
            echo "Success: 'auto_added' column has been added to the breaks table.\n";
            
            // Update existing records to mark likely auto-breaks
            $update_result = $db->query("UPDATE breaks b 
                           JOIN time_records t ON b.time_record_id = t.id
                           SET b.auto_added = 1
                           WHERE b.break_time = 30 
                           AND b.break_out IS NOT NULL
                           AND TIMESTAMPDIFF(HOUR, t.clock_in, t.clock_out) >= 8");
            
            echo "Updated " . $db->affected_rows . " existing break records as auto-added.\n";
        } else {
            echo "Error: Failed to add column. " . $db->error . "\n";
        }
    } else {
        echo "Note: 'auto_added' column already exists in the breaks table.\n";
    }
    
    echo "Process completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 