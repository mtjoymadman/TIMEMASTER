<?php
require_once '../config.php';
global $grok_db;

// Update any existing records to have suspended = 0 by default
$update_query = "UPDATE employees SET suspended = 0 WHERE suspended IS NULL";
if ($grok_db->query($update_query)) {
    echo "Successfully updated existing records\n";
} else {
    echo "Error updating records: " . $grok_db->error . "\n";
}

echo "Done!\n";
?> 