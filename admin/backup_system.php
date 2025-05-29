<?php
// Backup System for TIMEMASTER
// This script creates backups of critical files before changes are made

// Define backup directory
$backup_dir = __DIR__ . '/backups/';

// Ensure backup directory exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Function to create a backup of a file
function createBackup($file_path) {
    global $backup_dir;
    
    if (!file_exists($file_path)) {
        error_log("Backup failed: File $file_path does not exist.");
        return false;
    }
    
    $file_name = basename($file_path);
    $backup_path = $backup_dir . $file_name . '_' . date('Y-m-d_H-i-s') . '.bak';
    
    if (copy($file_path, $backup_path)) {
        error_log("Backup created for $file_path at $backup_path");
        return true;
    } else {
        error_log("Backup failed for $file_path");
        return false;
    }
}

// Function to list backups for a specific file
function listBackups($file_name) {
    global $backup_dir;
    $backups = [];
    $files = glob($backup_dir . $file_name . '_*.bak');
    foreach ($files as $file) {
        $backups[] = [
            'path' => $file,
            'timestamp' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    return $backups;
}

// Function to revert to a specific backup
function revertToBackup($backup_path, $original_file) {
    if (!file_exists($backup_path)) {
        error_log("Revert failed: Backup $backup_path does not exist.");
        return false;
    }
    
    if (copy($backup_path, $original_file)) {
        error_log("Reverted $original_file to backup $backup_path");
        return true;
    } else {
        error_log("Revert failed for $original_file from $backup_path");
        return false;
    }
}

// Handle requests to create or revert backups
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                if (isset($_POST['file_path'])) {
                    $result = createBackup($_POST['file_path']);
                    echo json_encode(['success' => $result, 'message' => $result ? 'Backup created successfully' : 'Backup creation failed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Missing file path']);
                }
                break;
            case 'list_backups':
                if (isset($_POST['file_name'])) {
                    $backups = listBackups($_POST['file_name']);
                    echo json_encode(['success' => true, 'backups' => $backups]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Missing file name']);
                }
                break;
            case 'revert_backup':
                if (isset($_POST['backup_path']) && isset($_POST['original_file'])) {
                    $result = revertToBackup($_POST['backup_path'], $_POST['original_file']);
                    echo json_encode(['success' => $result, 'message' => $result ? 'Revert successful' : 'Revert failed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Missing backup path or original file']);
                }
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
    }
    exit;
}

// HTML interface for backup management
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TIMEMASTER Backup System</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>TIMEMASTER Backup System</h1>
        </header>
        <div class="admin-section">
            <h2>Create Backup</h2>
            <form id="backupForm" method="post" action="backup_system.php">
                <input type="hidden" name="action" value="create_backup">
                <div style="margin-bottom: 15px;">
                    <label for="file_path" style="display: inline-block; width: 120px;">File Path:</label>
                    <input type="text" name="file_path" id="file_path" required style="padding: 5px; width: 300px;" placeholder="e.g., TIMEMASTER/admin/admin_clock_actions.php">
                </div>
                <button type="submit" class="edit-btn">Create Backup</button>
            </form>
        </div>
        <div class="admin-section">
            <h2>Revert to Backup</h2>
            <form id="listBackupsForm" method="post" action="backup_system.php">
                <input type="hidden" name="action" value="list_backups">
                <div style="margin-bottom: 15px;">
                    <label for="file_name" style="display: inline-block; width: 120px;">File Name:</label>
                    <input type="text" name="file_name" id="file_name" required style="padding: 5px; width: 300px;" placeholder="e.g., admin_clock_actions.php">
                </div>
                <button type="submit" class="edit-btn">List Backups</button>
            </form>
            <div id="backupsList" style="margin-top: 20px;">
                <!-- Backups will be listed here via JavaScript -->
            </div>
        </div>
    </div>
    <script>
        document.getElementById('backupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const response = await fetch('backup_system.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
        });

        document.getElementById('listBackupsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const response = await fetch('backup_system.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                let html = '<h3>Available Backups:</h3>';
                if (result.backups.length > 0) {
                    html += '<ul>';
                    result.backups.forEach(backup => {
                        html += `<li>${backup.timestamp} - <button onclick="revertToBackup('${backup.path}', '${document.getElementById('file_name').value}')">Revert</button></li>`;
                    });
                    html += '</ul>';
                } else {
                    html += '<p>No backups found for this file.</p>';
                }
                document.getElementById('backupsList').innerHTML = html;
            } else {
                alert(result.message);
            }
        });

        async function revertToBackup(backupPath, originalFile) {
            if (confirm('Are you sure you want to revert to this backup? This will overwrite the current file.')) {
                const formData = new FormData();
                formData.append('action', 'revert_backup');
                formData.append('backup_path', backupPath);
                formData.append('original_file', 'TIMEMASTER/admin/' + originalFile);
                const response = await fetch('backup_system.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                alert(result.message);
            }
        }
    </script>
</body>
</html> 