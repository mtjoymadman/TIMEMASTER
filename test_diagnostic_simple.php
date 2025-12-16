<?php
// MINIMAL TEST - Output immediately, no PHP processing
?>
<!DOCTYPE html>
<html>
<head><title>TEST - Diagnostic Simple</title></head>
<body>
<h1>TEST PAGE - If you see this, the file is being accessed directly</h1>
<p>This is a minimal test to see if files can be accessed without redirects.</p>
<p>Current Script: <?php echo basename($_SERVER['PHP_SELF'] ?? 'unknown'); ?></p>
<p>REQUEST_URI: <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown'); ?></p>
<p>SCRIPT_NAME: <?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'unknown'); ?></p>
</body>
</html>

