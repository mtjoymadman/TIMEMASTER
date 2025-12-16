<?php
/**
 * IP Access Control / Whitelist System
 * 
 * Manages IP address whitelisting for GROK and TIMEMASTER systems.
 * Features:
 * - Automatic tracking of new IP addresses
 * - Email notifications for new IP attempts
 * - Admin interface to approve/ban IPs
 * - Manual IP entry (from log files, etc.)
 * - IP range support (CIDR notation)
 * - Same rule applies to entire IP range
 */

// Prevent double inclusion
if (defined('IP_ACCESS_CONTROL_LOADED')) {
    return;
}
define('IP_ACCESS_CONTROL_LOADED', true);

/**
 * Get client IP address (handles proxies and load balancers)
 * 
 * @return string Client IP address
 */
function getClientIp() {
    // Check for IP in various headers (for proxy/load balancer scenarios)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if an IP address is in a CIDR range
 * 
 * @param string $ip IP address to check
 * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
 * @return bool True if IP is in range
 */
function ipInRange($ip, $cidr) {
    if (strpos($cidr, '/') === false) {
        // Not CIDR notation, do exact match
        return $ip === $cidr;
    }
    
    list($subnet, $mask) = explode('/', $cidr);
    
    // Convert IPs to long integers
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    
    if ($ip_long === false || $subnet_long === false) {
        return false;
    }
    
    // Calculate network mask
    $mask_long = -1 << (32 - (int)$mask);
    
    // Check if IP is in subnet
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

/**
 * Get IP range (CIDR) from an IP address
 * Automatically determines appropriate subnet mask
 * 
 * @param string $ip IP address
 * @param int $mask_bits Number of mask bits (default: 24 for /24 subnet)
 * @return string CIDR notation
 */
function getIpRange($ip, $mask_bits = 24) {
    $ip_long = ip2long($ip);
    if ($ip_long === false) {
        return $ip; // Return original IP if invalid
    }
    
    $mask_long = -1 << (32 - $mask_bits);
    $network_long = $ip_long & $mask_long;
    $network_ip = long2ip($network_long);
    
    return $network_ip . '/' . $mask_bits;
}

/**
 * Track IP address and check if it's allowed
 * 
 * @param mysqli $db Database connection
 * @param string $system System name ('grok' or 'timemaster')
 * @param string $username Username attempting access (optional)
 * @return array ['allowed' => bool, 'status' => string, 'ip_record' => array|null]
 */
function checkIpAccess($db, $system = 'timemaster', $username = '') {
    $clientIp = getClientIp();
    
    // First, check if IP is explicitly allowed or banned
    $stmt = $db->prepare("
        SELECT * FROM ip_access_control 
        WHERE ip_address = ? 
        AND (system = ? OR system = 'both')
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log("IP Access Control: Failed to prepare query: " . $db->error);
        return ['allowed' => false, 'status' => 'error', 'ip_record' => null];
    }
    
    $stmt->bind_param("ss", $clientIp, $system);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ipRecord = $result->fetch_assoc();
        
        // Update last_seen and attempt_count
        $updateStmt = $db->prepare("
            UPDATE ip_access_control 
            SET last_seen = NOW(), 
                attempt_count = attempt_count + 1 
            WHERE id = ?
        ");
        $updateStmt->bind_param("i", $ipRecord['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Check status
        if ($ipRecord['status'] === 'allowed') {
            return ['allowed' => true, 'status' => 'allowed', 'ip_record' => $ipRecord];
        } elseif ($ipRecord['status'] === 'banned') {
            return ['allowed' => false, 'status' => 'banned', 'ip_record' => $ipRecord];
        } else {
            // Status is 'pending' - send notification if this is first attempt
            if ($ipRecord['attempt_count'] == 1) {
                sendNewIpNotification($clientIp, $system, $username, $ipRecord);
            }
            return ['allowed' => false, 'status' => 'pending', 'ip_record' => $ipRecord];
        }
    }
    
    // IP not found - check if it matches any IP range
    $rangeStmt = $db->prepare("
        SELECT * FROM ip_access_control 
        WHERE ip_range IS NOT NULL 
        AND (system = ? OR system = 'both')
        AND status = 'allowed'
    ");
    $rangeStmt->bind_param("s", $system);
    $rangeStmt->execute();
    $rangeResult = $rangeStmt->get_result();
    
    while ($rangeRecord = $rangeResult->fetch_assoc()) {
        if (ipInRange($clientIp, $rangeRecord['ip_range'])) {
            // IP is in an allowed range
            return ['allowed' => true, 'status' => 'allowed_range', 'ip_record' => $rangeRecord];
        }
    }
    $rangeStmt->close();
    
    // New IP - create pending record and send notification
    $insertStmt = $db->prepare("
        INSERT INTO ip_access_control 
        (ip_address, ip_range, status, system, first_seen, last_seen, attempt_count) 
        VALUES (?, ?, 'pending', ?, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE 
            last_seen = NOW(), 
            attempt_count = attempt_count + 1
    ");
    
    $ipRange = getIpRange($clientIp);
    $insertStmt->bind_param("sss", $clientIp, $ipRange, $system);
    $insertStmt->execute();
    $insertStmt->close();
    
    // Get the created record
    $newStmt = $db->prepare("SELECT * FROM ip_access_control WHERE ip_address = ?");
    $newStmt->bind_param("s", $clientIp);
    $newStmt->execute();
    $newResult = $newStmt->get_result();
    $newRecord = $newResult->fetch_assoc();
    $newStmt->close();
    
    // Send notification for new IP
    sendNewIpNotification($clientIp, $system, $username, $newRecord);
    
    return ['allowed' => false, 'status' => 'pending', 'ip_record' => $newRecord];
}

/**
 * Send email notification for new IP address attempt
 * 
 * @param string $ip IP address
 * @param string $system System name
 * @param string $username Username (if available)
 * @param array $ipRecord IP record from database
 */
function sendNewIpNotification($ip, $system, $username, $ipRecord) {
    // Load TIMEMASTER email system
    if (file_exists(__DIR__ . '/../lib/smtp_config.php')) {
        require_once __DIR__ . '/../lib/smtp_config.php';
    }
    
    // Get notification email from config
    $notifyEmail = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : 'mtjoymadman@gmail.com, ifree2bmenow@yahoo.com, margie@redlionsalvage.net';
    
    // Split multiple emails
    $emails = array_map('trim', explode(',', $notifyEmail));
    
    $subject = "New IP Address Attempt - " . strtoupper($system) . " System";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #d32f2f; color: white; padding: 15px; text-align: center; }
            .content { background-color: #f5f5f5; padding: 20px; }
            .info-row { margin: 10px 0; }
            .label { font-weight: bold; }
            .button { display: inline-block; padding: 10px 20px; margin: 10px 5px; text-decoration: none; border-radius: 5px; }
            .approve { background-color: #4caf50; color: white; }
            .ban { background-color: #f44336; color: white; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New IP Address Access Attempt</h2>
            </div>
            <div class='content'>
                <div class='info-row'>
                    <span class='label'>IP Address:</span> {$ip}
                </div>
                <div class='info-row'>
                    <span class='label'>IP Range:</span> " . ($ipRecord['ip_range'] ?? getIpRange($ip)) . "
                </div>
                <div class='info-row'>
                    <span class='label'>System:</span> " . strtoupper($system) . "
                </div>
                <div class='info-row'>
                    <span class='label'>Username:</span> " . ($username ?: 'Not provided') . "
                </div>
                <div class='info-row'>
                    <span class='label'>Time:</span> " . date('Y-m-d H:i:s') . "
                </div>
                <div class='info-row'>
                    <span class='label'>Status:</span> <strong>PENDING APPROVAL</strong>
                </div>
                <hr>
                <p><strong>Action Required:</strong></p>
                <p>Review this IP address and either approve or ban it. The same rule will apply to the entire IP range.</p>
                <p>
                    <a href='https://grok.redlionsalvage.net/admin/ip_management.php?action=approve&ip=" . urlencode($ip) . "' class='button approve'>Approve IP</a>
                    <a href='https://grok.redlionsalvage.net/admin/ip_management.php?action=ban&ip=" . urlencode($ip) . "' class='button ban'>Ban IP</a>
                </p>
                <p><small>Note: Approving or banning this IP will apply to the entire IP range: " . ($ipRecord['ip_range'] ?? getIpRange($ip)) . "</small></p>
            </div>
            <div class='footer'>
                <p>This is an automated notification from the Red Lion Salvage IP Access Control System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    foreach ($emails as $email) {
        if (function_exists('sendEmail')) {
            sendEmail($email, $subject, $body);
        }
    }
}

/**
 * Manually add IP address to whitelist
 * 
 * @param mysqli $db Database connection
 * @param string $ip IP address
 * @param string $system System name ('grok', 'timemaster', or 'both')
 * @param string $allowedBy Username who added it
 * @param string $notes Optional notes
 * @param string $status Status ('allowed' or 'banned')
 * @return bool Success
 */
function manuallyAddIp($db, $ip, $system = 'both', $allowedBy = '', $notes = '', $status = 'allowed') {
    $ipRange = getIpRange($ip);
    
    $stmt = $db->prepare("
        INSERT INTO ip_access_control 
        (ip_address, ip_range, status, system, allowed_by, notes, first_seen, last_seen) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            ip_range = VALUES(ip_range),
            system = VALUES(system),
            allowed_by = VALUES(allowed_by),
            notes = VALUES(notes),
            updated_at = NOW()
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare manual IP add query: " . $db->error);
        return false;
    }
    
    $stmt->bind_param("ssssss", $ip, $ipRange, $status, $system, $allowedBy, $notes);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Approve IP address (whitelist it)
 * 
 * @param mysqli $db Database connection
 * @param string $ip IP address
 * @param string $allowedBy Username who approved it
 * @param string $notes Optional notes
 * @return bool Success
 */
function approveIp($db, $ip, $allowedBy = '', $notes = '') {
    $stmt = $db->prepare("
        UPDATE ip_access_control 
        SET status = 'allowed', 
            allowed_by = ?, 
            notes = ?,
            updated_at = NOW()
        WHERE ip_address = ?
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sss", $allowedBy, $notes, $ip);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Ban IP address
 * 
 * @param mysqli $db Database connection
 * @param string $ip IP address
 * @param string $bannedBy Username who banned it
 * @param string $notes Optional notes
 * @return bool Success
 */
function banIp($db, $ip, $bannedBy = '', $notes = '') {
    $stmt = $db->prepare("
        UPDATE ip_access_control 
        SET status = 'banned', 
            allowed_by = ?, 
            notes = ?,
            updated_at = NOW()
        WHERE ip_address = ?
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sss", $bannedBy, $notes, $ip);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Require IP access - redirect if not allowed
 * 
 * @param mysqli $db Database connection
 * @param string $system System name
 * @param string $username Username (optional)
 * @param string $errorUrl URL to redirect to if blocked
 */
function requireIpAccess($db, $system = 'timemaster', $username = '', $errorUrl = null) {
    $check = checkIpAccess($db, $system, $username);
    
    if (!$check['allowed']) {
        if ($errorUrl === null) {
            $errorUrl = '/login.php?error=ip_blocked';
        }
        
        // Log the blocked attempt
        error_log("IP Access Denied: " . getClientIp() . " (Status: " . $check['status'] . ", System: $system)");
        
        header("Location: $errorUrl");
        exit();
    }
}

/**
 * Parse IP addresses from log file
 * 
 * @param string $logFilePath Path to log file
 * @return array Array of unique IP addresses found
 */
function parseIpsFromLogFile($logFilePath) {
    $ips = [];
    
    if (!file_exists($logFilePath)) {
        return $ips;
    }
    
    $handle = fopen($logFilePath, 'r');
    if (!$handle) {
        return $ips;
    }
    
    // Common log patterns
    $patterns = [
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',  // IPv4
        '/\b(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}\b/',  // IPv6
    ];
    
    while (($line = fgets($handle)) !== false) {
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches)) {
                foreach ($matches[0] as $ip) {
                    // Validate IP
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[$ip] = true; // Use as key to ensure uniqueness
                    }
                }
            }
        }
    }
    
    fclose($handle);
    
    return array_keys($ips);
}

