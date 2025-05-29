<?php
require_once __DIR__ . '/../config.php';

// Check if tables exist
$tables = ['employees', 'time_records', 'breaks'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        echo "Table '$table' does not exist. Creating...\n";
        // Read and execute schema.sql
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $queries = explode(';', $schema);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!$db->query($query)) {
                    echo "Error executing query: " . $db->error . "\n";
                }
            }
        }
    } else {
        echo "Table '$table' exists.\n";
    }
}

echo "Database check complete.\n";
?> 