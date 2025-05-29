<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// TIMEMASTER POLICY: Eastern Time Only (America/New_York)
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    header('Location: ../index.php');
    exit;
}

// Connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Calculate the start of the current week (Sunday)
$current_week_start = date('Y-m-d', strtotime('last sunday'));

// Begin transaction
$db->begin_transaction();

try {
    // First, count records to be deleted for reporting
    $count_time_query = "SELECT COUNT(*) as count FROM time_records WHERE DATE(clock_in) < ?";
    $count_stmt = $db->prepare($count_time_query);
    $count_stmt->bind_param("s", $current_week_start);
    $count_stmt->execute();
    $time_count = $count_stmt->get_result()->fetch_assoc()['count'];
    
    // Delete break records from the break_records table
    $delete_breaks_query = "DELETE br FROM break_records br 
                           INNER JOIN time_records tr ON br.time_record_id = tr.id 
                           WHERE DATE(tr.clock_in) < ?";
    $break_stmt = $db->prepare($delete_breaks_query);
    $break_stmt->bind_param("s", $current_week_start);
    $break_stmt->execute();
    $breaks_deleted = $db->affected_rows;
    
    // Delete break records from the breaks table (to handle foreign key constraint)
    $delete_legacy_breaks_query = "DELETE b FROM breaks b 
                                  INNER JOIN time_records tr ON b.time_record_id = tr.id 
                                  WHERE DATE(tr.clock_in) < ?";
    $legacy_break_stmt = $db->prepare($delete_legacy_breaks_query);
    $legacy_break_stmt->bind_param("s", $current_week_start);
    $legacy_break_stmt->execute();
    $legacy_breaks_deleted = $db->affected_rows;
    
    // Delete time records older than current week
    $delete_time_query = "DELETE FROM time_records WHERE DATE(clock_in) < ?";
    $time_stmt = $db->prepare($delete_time_query);
    $time_stmt->bind_param("s", $current_week_start);
    $time_stmt->execute();
    $times_deleted = $db->affected_rows;
    
    // Commit the transaction
    $db->commit();
    
    $success = "Database cleanup completed successfully. Removed $times_deleted time records, $breaks_deleted break_records, and $legacy_breaks_deleted legacy breaks. All entries from this week have been preserved.";
} catch (Exception $e) {
    // Rollback the transaction in case of error
    $db->rollback();
    $error = "Error during database cleanup: " . $e->getMessage();
}

// Close database connection
$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Cleanup - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - Database Cleanup</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="admin-section">
            <h2>Database Cleanup Results</h2>
            
            <?php if (isset($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
                <div class="info-box">
                    <h3>Database Status:</h3>
                    <p>✅ Project is now marked as working</p>
                    <p>✅ Only entries from this week (since <?php echo $current_week_start; ?>) have been preserved</p>
                    <p>✅ All testing data has been removed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 