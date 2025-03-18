<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    // Check if the username exists and has 'employee' role
    $stmt = $db->prepare("SELECT username, role FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && hasRole($username, 'employee')) {
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or you must be an employee to use TIMEMASTER.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TIMEMASTER - Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container employee-page">
        <img src="images/logo.png" alt="Red Lion Salvage Logo" class="logo">
        <h1>TIMEMASTER</h1>
        <h2>Employee Login</h2>
        <?php if (isset($error)) { ?>
            <p style="color: #ff4444; text-align: center;"><?php echo $error; ?></p>
        <?php } ?>
        <form method="post" class="button-group">
            <input type="text" name="username" placeholder="Username" required>
            <input type="text" name="employee_code" placeholder="Employee Code" disabled> <!-- Decorative only -->
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>