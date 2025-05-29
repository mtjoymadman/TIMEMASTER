<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../lib/smtp_config.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
if (!hasRole($_SESSION['username'], 'admin')) {
    // Redirect to employee interface
    header('Location: ../index.php');
    exit;
}

// Get logged in user
$logged_in_user = $_SESSION['username'];

// Function to get SMTP config settings from the database or config file
function getSMTPConfig() {
    // Default values from constants
    $config = [
        'host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
        'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
        'username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : 'rlstimeclock@gmail.com',
        'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'rlstimeclock@gmail.com',
        'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'TIMEMASTER',
        'secure' => defined('SMTP_SECURE') ? SMTP_SECURE : 'tls',
        'auth' => defined('SMTP_AUTH') ? SMTP_AUTH : true,
        'debug' => defined('SMTP_DEBUG') ? SMTP_DEBUG : 0
    ];
    
    return $config;
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_smtp') {
    // Get form data
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? '';
    $smtp_user = $_POST['smtp_user'] ?? '';
    $smtp_pass = $_POST['smtp_pass'] ?? '';
    $smtp_from = $_POST['smtp_from'] ?? '';
    $smtp_from_name = $_POST['smtp_from_name'] ?? '';
    $smtp_secure = $_POST['smtp_secure'] ?? '';
    $notify_email = $_POST['notify_email'] ?? '';

    // Validate form data
    if (empty($smtp_host) || empty($smtp_port) || empty($smtp_user) || empty($smtp_from) || empty($smtp_from_name) || empty($smtp_secure)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Only update SMTP password if one was provided
        $smtp_config = array(
            'SMTP_HOST' => $smtp_host,
            'SMTP_PORT' => $smtp_port,
            'SMTP_USER' => $smtp_user,
            'SMTP_FROM' => $smtp_from,
            'SMTP_FROM_NAME' => $smtp_from_name,
            'SMTP_SECURE' => $smtp_secure,
            'NOTIFY_EMAIL' => $notify_email
        );
        
        if (!empty($smtp_pass)) {
            $smtp_config['SMTP_PASS'] = $smtp_pass;
        }
        
        // Save the SMTP configuration
        if (updateSMTPConfig($smtp_config)) {
            $success = 'SMTP configuration updated successfully!';
            
            // Send a test email if requested
            if (isset($_POST['send_test_email']) && $_POST['send_test_email'] === 'yes') {
                $test_result = sendTestEmail();
                if ($test_result === true) {
                    $success .= ' Test email sent successfully.';
                } else {
                    $error = 'SMTP configuration updated, but test email failed: ' . $test_result;
                }
            }
        } else {
            $error = 'Failed to update SMTP configuration. Please check file permissions.';
        }
    }
}

// Get current SMTP configuration
$config = getSMTPConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings - TIMEMASTER</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            background-color: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .back-button:hover {
            background-color: #444;
        }
        
        .back-button i {
            margin-right: 5px;
        }
        
        .settings-container {
            background-color: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .settings-header {
            color: #e74c3c;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            background-color: #333;
            border: 1px solid #444;
            border-radius: 4px;
            color: white;
        }
        
        .checkbox-group {
            margin-top: 20px;
        }
        
        .button-group {
            margin-top: 20px;
            text-align: right;
        }
        
        .help-text {
            font-size: 0.9em;
            color: #999;
            margin-top: 5px;
        }
        
        .smtp-status {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .status-configured {
            background-color: rgba(39, 174, 96, 0.2);
            border: 1px solid #27ae60;
        }
        
        .status-not-configured {
            background-color: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
        }
        
        .toggle-password {
            cursor: pointer;
            color: #999;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER - SMTP Settings</h1>
            <p>Welcome, <?php echo htmlspecialchars($logged_in_user); ?></p>
        </header>
        
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($error) { ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        
        <?php if ($success) { ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>
        
        <?php if (isset($config['SMTP_HOST']) && !empty($config['SMTP_HOST'])) { ?>
            <div class="smtp-status status-configured">
                <p><i class="fas fa-check-circle"></i> SMTP is currently configured. Email functionality is available.</p>
            </div>
        <?php } else { ?>
            <div class="smtp-status status-not-configured">
                <p><i class="fas fa-exclamation-circle"></i> SMTP is not configured. Email functionality will not work until you configure SMTP settings.</p>
            </div>
        <?php } ?>
        
        <div class="settings-container">
            <h2 class="settings-header">
                <span><i class="fas fa-envelope"></i> Email Configuration</span>
                <?php if (isset($config['SMTP_HOST']) && !empty($config['SMTP_HOST'])) { ?>
                    <button type="button" class="edit-btn" id="testEmailBtn">Test Email</button>
                <?php } ?>
            </h2>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="update_smtp">
                <input type="hidden" name="send_test_email" id="send_test_email" value="no">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host:</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($config['SMTP_HOST'] ?? ''); ?>" required>
                        <p class="help-text">e.g., smtp.gmail.com</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_port">SMTP Port:</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($config['SMTP_PORT'] ?? '587'); ?>" required>
                        <p class="help-text">Common ports: 587 (TLS), 465 (SSL)</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_secure">Security Protocol:</label>
                        <select id="smtp_secure" name="smtp_secure" required>
                            <option value="tls" <?php echo (isset($config['SMTP_SECURE']) && $config['SMTP_SECURE'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo (isset($config['SMTP_SECURE']) && $config['SMTP_SECURE'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_user">SMTP Username:</label>
                        <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($config['SMTP_USER'] ?? ''); ?>" required>
                        <p class="help-text">Usually your email address</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_pass">SMTP Password:</label>
                        <div style="display: flex; align-items: center;">
                            <input type="password" id="smtp_pass" name="smtp_pass" value="" placeholder="<?php echo !empty($config['SMTP_PASS']) ? '••••••••••••••••' : ''; ?>">
                            <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
                        </div>
                        <p class="help-text">Leave empty to keep current password</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_from">From Email:</label>
                        <input type="email" id="smtp_from" name="smtp_from" value="<?php echo htmlspecialchars($config['SMTP_FROM'] ?? ''); ?>" required>
                        <p class="help-text">The email address that will appear as sender</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_from_name">From Name:</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($config['SMTP_FROM_NAME'] ?? 'TIMEMASTER System'); ?>" required>
                        <p class="help-text">The name that will appear as sender</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="notify_email">Notification Email:</label>
                        <input type="email" id="notify_email" name="notify_email" value="<?php echo htmlspecialchars($config['NOTIFY_EMAIL'] ?? ''); ?>">
                        <p class="help-text">Email address for status change notifications</p>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="edit-btn">Save Settings</button>
                </div>
            </form>
        </div>
        
        <div class="settings-container">
            <h2 class="settings-header"><i class="fas fa-info-circle"></i> Gmail SMTP Setup Guide</h2>
            
            <div class="smtp-guide">
                <p>If you're using Gmail as your SMTP provider, follow these steps:</p>
                
                <ol>
                    <li>Make sure you have enabled "Less secure app access" or use App Password if you have 2-factor authentication enabled.</li>
                    <li>Use the following settings:</li>
                    <ul>
                        <li><strong>SMTP Host:</strong> smtp.gmail.com</li>
                        <li><strong>SMTP Port:</strong> 587</li>
                        <li><strong>Security Protocol:</strong> TLS</li>
                        <li><strong>SMTP Username:</strong> your-gmail-address@gmail.com</li>
                        <li><strong>SMTP Password:</strong> your Gmail password or App Password</li>
                        <li><strong>From Email:</strong> your-gmail-address@gmail.com</li>
                    </ul>
                </ol>
                
                <p class="help-text">Note: If you're having issues with Gmail, please check Google's documentation as their security policies may change.</p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('smtp_pass');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Test Email button functionality
        document.addEventListener('DOMContentLoaded', function() {
            const testEmailBtn = document.getElementById('testEmailBtn');
            
            if (testEmailBtn) {
                testEmailBtn.addEventListener('click', function() {
                    if (confirm('Send a test email to verify your SMTP configuration?')) {
                        document.getElementById('send_test_email').value = 'yes';
                        document.querySelector('form').submit();
                    }
                });
            }
        });
    </script>
</body>
</html> 