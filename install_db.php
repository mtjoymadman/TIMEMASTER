<?php
require_once 'config.php';

// Set time limit to 5 minutes for large databases
set_time_limit(300);

// Display header
echo "=================================================\n";
echo "TIMEMASTER Database Installation Script\n";
echo "=================================================\n\n";

// Connect to the database
try {
    echo "Connecting to database...\n";
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    
    echo "Connected successfully!\n\n";
    
    // Read the SQL file
    echo "Reading database schema...\n";
    $sql = file_get_contents('database_schema.sql');
    
    if ($sql === false) {
        throw new Exception("Error reading database_schema.sql");
    }
    
    echo "Schema loaded successfully!\n\n";
    
    // Execute multi query
    echo "Creating database tables...\n";
    
    // Split the SQL file into separate statements
    $queries = explode(';', $sql);
    $success = true;
    
    // Begin transaction
    $db->begin_transaction();
    
    try {
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;
            
            if (!$db->query($query)) {
                throw new Exception("Error executing query: " . $db->error . "\nQuery: " . $query);
            }
            
            // Output progress for each query
            echo ".";
        }
        
        // Commit transaction
        $db->commit();
        echo "\n\nDatabase setup completed successfully!\n";
        
        // Check if tables were created
        echo "\nVerifying tables...\n";
        $tables = ['employees', 'time_records', 'breaks', 'activity_log'];
        
        foreach ($tables as $table) {
            $result = $db->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "✓ Table '$table' created successfully.\n";
            } else {
                echo "✗ Table '$table' not found.\n";
                $success = false;
            }
        }
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        echo "\n\nError: " . $e->getMessage() . "\n";
        $success = false;
    }
    
    // Close database connection
    $db->close();
    
    if ($success) {
        echo "\n=================================================\n";
        echo "Installation completed successfully!\n";
        echo "You can now use the TIMEMASTER system.\n";
        echo "=================================================\n";
    } else {
        echo "\n=================================================\n";
        echo "Installation failed. Please check the error messages.\n";
        echo "=================================================\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "\n=================================================\n";
    echo "Installation failed. Please check your database configuration.\n";
    echo "=================================================\n";
}
?> 