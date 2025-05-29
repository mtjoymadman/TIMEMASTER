<?php
/**
 * SMTP Configuration for TIMEMASTER
 */

// Prevent direct access
if (!defined('INCLUDED_FROM_APP')) {
    define('INCLUDED_FROM_APP', true);
}

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// SMTP settings with Gmail defaults - using TLS connection that worked before
@define('SMTP_HOST', 'smtp.gmail.com');
@define('SMTP_PORT', 587);
@define('SMTP_SECURE', 'tls');
@define('SMTP_AUTH', true);
@define('SMTP_USERNAME', 'rlstimeclock@gmail.com'); // Your Gmail address
@define('SMTP_PASSWORD', 'wsilgdzeouzremou'); // New Gmail App Password (generated 3/19/2025)
@define('SMTP_FROM_EMAIL', 'rlstimeclock@gmail.com'); // Must match your Gmail address
@define('SMTP_FROM_NAME', 'TIMEMASTER');
@define('SMTP_DEBUG', 0); // Set debug level to 0 for production
@define('SEND_METHOD', 'smtp'); // Use SMTP exclusively

/**
 * Send email using PHPMailer and Gmail SMTP
 * 
 * @param string|array $to Recipient email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $attachments Optional array of attachments
 * @return array ['success' => bool, 'message' => string]
 */
function send_smtp_email($to, $subject, $body, $attachments = []) {
    // We're using Gmail SMTP exclusively
    
    $mail = null;
    try {
        // Check if we should use PHP's mail() function instead of SMTP
        if (defined('SEND_METHOD') && SEND_METHOD === 'mail') {
            return send_php_mail($to, $subject, $body);
        }
        
        // Log the type of recipient for debugging
        error_log("RECIPIENT TYPE: " . (is_array($to) ? 'Array of ' . count($to) . ' email(s)' : 'Single string') . 
                 ", Value: " . (is_array($to) ? implode(', ', $to) : $to));
        
        // Start output buffering to prevent script hanging
        if (ob_get_level() == 0) ob_start();
        echo "<!-- Starting email process... -->";
        if (ob_get_level() > 0) {
            ob_flush();
            flush();
        }
        
        // Check if PHPMailer is available
        if (!file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
            error_log('PHPMailer not installed');
            return ['success' => false, 'message' => 'PHPMailer is not installed'];
        }
        
        // Include PHPMailer files (always include them for consistency)
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        
        // Enhanced debug output function
        $debug_function = function($str, $level) {
            // Always log SMTP debug info regardless of debug level setting
            error_log("PHPMAILER SMTP DEBUG ($level): " . $str);
            
            // Track authentication steps more clearly
            if (stripos($str, 'auth') !== false) {
                error_log("AUTH STEP: " . $str);
            }
            
            // Output to browser if debugging is enabled
            echo "<!-- SMTP Debug: " . htmlspecialchars($str) . " -->\n";
            if (ob_get_level() > 0) {
                ob_flush();
                flush();
            }
        };
        
        // Create new PHPMailer instance with error exceptions enabled
        $mail = new PHPMailer(true);
        
        // Server settings with extra debugging
        error_log("Configuring PHPMailer with: Host=" . SMTP_HOST . ", Port=" . SMTP_PORT . ", Auth=" . (SMTP_AUTH ? 'Yes' : 'No'));
        
        // Set shorter PHP timeouts so script doesn't hang indefinitely
        set_time_limit(20); // 20 seconds timeout to prevent server hanging
        ini_set('max_execution_time', 20);
        ini_set('default_socket_timeout', 10);
        
        // Set reasonable debug level 
        $mail->SMTPDebug = SMTP_DEBUG;
        error_log("Using SMTP debug level: " . SMTP_DEBUG);
        
        $mail->Debugoutput = $debug_function;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->SMTPAuth = SMTP_AUTH;
        if (SMTP_AUTH) {
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
        }
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10; // Reduce to 10 seconds - fail faster if connection issues
        $mail->SMTPKeepAlive = false; // Don't keep connection alive
        $mail->SMTPAutoTLS = false; // Disable automatic TLS negotiation since we're explicitly specifying TLS
        
        // Add Gmail-specific options with more lenient SSL settings for compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set who the message is from
        // For Gmail, sender should match authorized address
        error_log("Setting from address: " . SMTP_FROM_EMAIL);
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Add recipients - ensure proper type handling
        if (is_array($to)) {
            foreach ($to as $recipient) {
                if (is_string($recipient) && !empty(trim($recipient))) {
                    $mail->addAddress(trim($recipient));
                }
            }
        } else if (is_string($to) && !empty(trim($to))) {
            $mail->addAddress(trim($to));
        }
        
        // Add attachments if any
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $filename = isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $filename);
                }
            }
        }
        
        // Set email format to HTML
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Attempt SMTP send
        error_log("Attempting to send email via SMTP");
        echo "<!-- Attempting SMTP connection... -->";
        if (ob_get_level() > 0) {
            ob_flush();
            flush();
        }
        
        // Send the email
        $result = $mail->send();
        error_log("Email send result: " . ($result ? "Success" : "Failed"));

        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        
        // Log more detailed error information
        if ($mail && $mail->SMTPDebug > 0) {
            error_log('SMTP Host: ' . SMTP_HOST);
            error_log('SMTP Port: ' . SMTP_PORT);
            error_log('SMTP Username: ' . SMTP_USERNAME);
            error_log('SMTP Auth: ' . (SMTP_AUTH ? 'Yes' : 'No'));
            error_log('SMTP Secure: ' . SMTP_SECURE);
        }
        
        // No fallbacks - we're using Gmail SMTP exclusively
        error_log('Gmail SMTP failed: ' . $e->getMessage());
        
        // Include debug output in the returned error message if available
        $error_message = $e->getMessage();
        $debug_info = '';
        
        if (strpos($error_message, 'SMTP Error: Could not authenticate') !== false) {
            $debug_info = 'Gmail authentication failed. Check that:
            1. You\'re using an App Password (not regular Gmail password)
            2. 2-Step Verification is enabled on your Google account
            3. App Password is correctly formatted (16 characters)
            4. Your From email matches the Gmail address used for login';
        } elseif (strpos($error_message, 'SMTP connect() failed') !== false) {
            $debug_info = 'Could not connect to Gmail SMTP server. Your hosting may be blocking outbound SMTP connections.';
        }
        
        return [
            'success' => false, 
            'message' => $error_message, 
            'debug' => $debug_info
        ];
    }
}

/**
 * Send email using PHP's built-in mail() function
 * 
 * @param string|array $to Recipient email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @return array ['success' => bool, 'message' => string]
 */
function send_php_mail($to, $subject, $body) {
    try {
        // Prepare recipients
        $recipients = is_array($to) ? implode(', ', $to) : $to;
        
        // Get server hostname
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        
        // Set up headers - formatted more carefully for picky mail servers
        $from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@' . $server_name;
        $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'TIMEMASTER';
        
        // Detailed logging for debugging
        error_log("Email FROM: $from_name <$from_email>");
        error_log("Email TO: $recipients");
        error_log("Email SUBJECT: $subject");
        
        // Format "From" header properly
        $from_header = $from_name . ' <' . $from_email . '>';
        
        // Build headers - format is critical for some mail servers
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . $from_header . "\r\n";
        $headers .= 'Reply-To: ' . $from_email . "\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
        
        // Try to diagnose mail server configuration
        $mail_ini = ini_get('sendmail_path');
        error_log("Sendmail path: " . ($mail_ini ? $mail_ini : "Not configured"));
        
        // Send email with additional parameters (5th param can improve delivery on some hosts)
        $additional_params = "-f" . $from_email;
        $result = mail($recipients, $subject, $body, $headers, $additional_params);
        
        if ($result) {
            error_log("PHP mail() function reported success");
            return ['success' => true, 'message' => 'Email sent successfully via PHP mail()'];
        } else {
            // Get last PHP error for more debug info
            $error = error_get_last();
            error_log("PHP mail() function failed. Error: " . ($error ? json_encode($error) : "No error details"));
            return ['success' => false, 'message' => 'Failed to send email using PHP mail() function. Check server logs.'];
        }
    } catch (\Exception $e) {
        error_log('PHP mail() error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()];
    }
}

// Add a function to save emails to files for debugging
/**
 * Save email to a file instead of sending
 * This is a fallback when SMTP doesn't work in hosting environments
 * 
 * @param string|array $to Recipient email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @return array ['success' => bool, 'message' => string]
 */
function save_email_to_file($to, $subject, $body) {
    try {
        // Create a reports directory if it doesn't exist
        $dir = __DIR__ . '/../reports';
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Make sure reports directory is writable
        if (!is_writable($dir)) {
            error_log("Reports directory is not writable: $dir");
            return ['success' => false, 'message' => 'Reports directory is not writable'];
        }
        
        // Create a timestamped filename
        $timestamp = date('Y-m-d_H-i-s');
        $recipients = is_array($to) ? implode(',', $to) : $to;
        $safe_subject = preg_replace('/[^a-z0-9]/i', '_', $subject);
        $filename = $dir . '/report_' . $timestamp . '_' . substr($safe_subject, 0, 30) . '.html';
        
        // Create a header section with metadata
        $meta = "<!--- EMAIL METADATA --->\n";
        $meta .= "<!-- To: " . $recipients . " -->\n";
        $meta .= "<!-- Subject: " . $subject . " -->\n";
        $meta .= "<!-- Date: " . date('Y-m-d H:i:s') . " -->\n";
        $meta .= "<!-- From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . "> -->\n";
        $meta .= "<!--- END METADATA --->\n\n";
        
        // Write the email to file
        $content = $meta . $body;
        $result = file_put_contents($filename, $content);
        
        if ($result === false) {
            error_log("Failed to write email to file: $filename");
            return ['success' => false, 'message' => 'Failed to save email to file'];
        }
        
        error_log("Email saved to file: $filename");
        return [
            'success' => true, 
            'message' => 'Email saved to file instead of sending',
            'filename' => basename($filename),
            'path' => $filename
        ];
    } catch (\Exception $e) {
        error_log('Error saving email to file: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error saving email: ' . $e->getMessage()];
    }
} 