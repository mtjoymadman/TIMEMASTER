<?php
require_once '../functions.php';

$all_employees = $db->query("SELECT username FROM employees WHERE role != 'admin'")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period = $_POST['period'] ?? 'all';
    $email = $_POST['email'] ?? '';
    $fields = $_POST['fields'] ?? [];
    $selected_employees = $_POST['employees'] ?? [];
    $action = $_POST['action'] ?? '';

    $fields_str = !empty($fields) ? implode(',', $fields) : 't.username,t.clock_in,t.clock_out,b.break_in,b.break_out,b.break_time';
    $query = "SELECT $fields_str 
              FROM time_records t 
              LEFT JOIN breaks b ON t.id = b.time_record_id";
    if (!empty($selected_employees) && !in_array('all', $selected_employees)) {
        $placeholders = implode(',', array_fill(0, count($selected_employees), '?'));
        $query .= " WHERE t.username IN ($placeholders)";
    }
    if ($period !== 'all') {
        $query .= (!empty($selected_employees) && !in_array('all', $selected_employees) ? " AND" : " WHERE") . " t.clock_in >= DATE_SUB(NOW(), INTERVAL 1 $period)";
    }
    $query .= " ORDER BY t.clock_in DESC";

    $stmt = $db->prepare($query);
    if (!empty($selected_employees) && !in_array('all', $selected_employees)) {
        $stmt->bind_param(str_repeat('s', count($selected_employees)), ...$selected_employees);
    }
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($action === 'email' && $email) {
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, array_keys($report_data[0]));
        foreach ($report_data as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $csv_content = stream_get_contents($csv);
        fclose($csv);
        file_put_contents('../python/report.csv', $csv_content);
        exec('python ../python/email_report.py ' . escapeshellarg($email));
        $message = "Report emailed to $email";
    } elseif ($action === 'save') {
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, array_keys($report_data[0]));
        foreach ($report_data as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="timemaster_report.csv"');
        fpassthru($csv);
        fclose($csv);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TIMEMASTER Report</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <a href="../index.php" class="home-btn">Home</a>
        <img src="../images/logo.png" alt="Red Lion Salvage Logo" class="logo">
    </header>
    <div class="container">
        <h1>Generate Report</h1>
        <form method="post" class="button-group">
            <label>Period:
                <select name="period">
                    <option value="DAY" <?php echo ($period ?? '') === 'DAY' ? 'selected' : ''; ?>>Daily</option>
                    <option value="WEEK" <?php echo ($period ?? '') === 'WEEK' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="MONTH" <?php echo ($period ?? '') === 'MONTH' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="all" <?php echo ($period ?? '') === 'all' ? 'selected' : ''; ?>>All Time</option>
                </select>
            </label>
            <label>Employees:
                <select name="employees[]" multiple size="5">
                    <option value="all" <?php echo in_array('all', $selected_employees ?? []) ? 'selected' : ''; ?>>All Employees</option>
                    <?php foreach ($all_employees as $emp) { ?>
                        <option value="<?php echo $emp['username']; ?>" <?php echo in_array($emp['username'], $selected_employees ?? []) ? 'selected' : ''; ?>>
                            <?php echo $emp['username']; ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
            <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>"></label>
            <label>Fields:</label>
            <label><input type="checkbox" name="fields[]" value="t.username" <?php echo in_array('t.username', $fields ?? ['t.username']) ? 'checked' : ''; ?>> Username</label>
            <label><input type="checkbox" name="fields[]" value="t.clock_in" <?php echo in_array('t.clock_in', $fields ?? ['t.clock_in']) ? 'checked' : ''; ?>> Clock In</label>
            <label><input type="checkbox" name="fields[]" value="t.clock_out" <?php echo in_array('t.clock_out', $fields ?? []) ? 'checked' : ''; ?>> Clock Out</label>
            <label><input type="checkbox" name="fields[]" value="b.break_in" <?php echo in_array('b.break_in', $fields ?? []) ? 'checked' : ''; ?>> Break In</label>
            <label><input type="checkbox" name="fields[]" value="b.break_out" <?php echo in_array('b.break_out', $fields ?? [])