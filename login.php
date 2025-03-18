<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (authenticateUser($username, $password)) {
        $_SESSION['username'] = $username;
        // Redirect based on role
        if (hasRole($username, 'admin')) {
            header("Location: admin/index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Invalid username or password.";
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