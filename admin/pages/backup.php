<?php
// Admin Backup Management Page
$page_title = 'BACKUP & RESTORE';

// Handle download requests BEFORE any output
if (isset($_GET['download']) && isset($_GET['type'])) {
    require_once '../../includes/database.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/functions.php';
    
    // Check if user is logged in and is admin
    $db = new Database();
    $auth = new Auth($db);
    $auth->requireLogin();
    $auth->requireAdmin();
    
    $filename = basename($_GET['download']); // Prevent directory traversal
    $type = $_GET['type'];
    
    $backupPath = dirname(dirname(__DIR__)) . '/backups/';
    if ($type === 'sales') {
        $backupPath .= 'sales/' . $filename;
    } else if ($type === 'database') {
        $backupPath .= 'database/' . $filename;
    } else {
        die('Invalid backup type');
    }
    
    if (file_exists($backupPath)) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        // Read and output file
        readfile($backupPath);
        exit;
    } else {
        die('Backup file not found: ' . htmlspecialchars($filename));
    }
}

include '../components/main-layout.php';

require_once '../../includes/BackupManager.php';

// Initialize backup manager
$backupManager = new BackupManager($db);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'backup_database':
                $config = [
                    'host' => 'localhost',
                    'dbname' => '9bar_pos',
                    'username' => 'root',
                    'password' => ''
                ];
                $result = $backupManager->backupDatabase($config);
                if ($result['success']) {
                    $message = "Database backup created successfully! ({$result['filename']})";
                    $messageType = 'success';
                } else {
                    $message = "Backup failed: " . $result['error'];
                    $messageType = 'error';
                }
                break;
                
            case 'backup_today_sales':
                $result = $backupManager->dailyAutoBackup();
                if ($result['success']) {
                    $message = "Today's sales backed up successfully! ({$result['total_sales']} transactions)";
                    $messageType = 'success';
                } else {
                    $message = "Backup failed: " . $result['error'];
                    $messageType = 'error';
                }
                break;
                
            case 'backup_date_range':
                $startDate = sanitizeInput($_POST['start_date']);
                $endDate = sanitizeInput($_POST['end_date']);
                $result = $backupManager->backupSalesByDateRange($startDate, $endDate);
                if ($result['success']) {
                    $message = "Sales backed up successfully! ({$result['total_sales']} transactions from {$startDate} to {$endDate})";
                    $messageType = 'success';
                } else {
                    $message = "Backup failed: " . $result['error'];
                    $messageType = 'error';
                }
                break;
                
            case 'restore_sales':
                if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                    $tmpFile = $_FILES['backup_file']['tmp_name'];
                    $result = $backupManager->restoreSalesFromBackup($tmpFile);
                    if ($result['success']) {
                        $message = "Restore successful! {$result['restored']} sales restored, {$result['skipped']} skipped (already exist)";
                        $messageType = 'success';
                    } else {
                        $message = "Restore failed: " . $result['error'];
                        $messageType = 'error';
                    }
                } else {
                    $message = "Please select a backup file to restore";
                    $messageType = 'error';
                }
                break;
                
            case 'clean_old_backups':
                $daysToKeep = intval($_POST['days_to_keep'] ?? 30);
                $deleted = $backupManager->cleanOldBackups($daysToKeep);
                $message = "Cleaned up {$deleted} old backup files (kept last {$daysToKeep} days)";
                $messageType = 'success';
                break;
                
            case 'download_backup':
                // This case is handled via GET parameter below
                break;
        }
    }
}

// Get backup lists
$salesBackups = $backupManager->getBackupList('sales');
$dbBackups = $backupManager->getBackupList('database');
?>

<style>
.backup-container {
    max-width: 1400px;
    margin: 20px auto;
}

.backup-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.backup-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.backup-card h3 {
    color: #3E363F;
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.backup-card p {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.backup-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.2s;
}

.backup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(62, 54, 63, 0.3);
}

.backup-btn.secondary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.backup-btn.danger {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.backup-table {
    width: 100%;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.backup-table h3 {
    color: #3E363F;
    margin-bottom: 20px;
    font-size: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #3E363F;
    border-bottom: 2px solid #dee2e6;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #3E363F;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.icon {
    width: 24px;
    height: 24px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.download-link {
    color: #667eea;
    text-decoration: none;
    cursor: pointer;
}

.download-link:hover {
    text-decoration: underline;
}
</style>

<div class="backup-container">
    <h1 style="color: #3E363F; margin-bottom: 10px;">🔒 Backup & Restore</h1>
    <p style="color: #666; margin-bottom: 30px;">Protect your sales data with automatic backups. All transactions are backed up immediately after completion.</p>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Backup Actions -->
    <div class="backup-grid">
        <!-- Database Backup -->
        <div class="backup-card">
            <h3>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                </svg>
                Full Database Backup
            </h3>
            <p>Backup entire database including all tables, products, and settings</p>
            <form method="POST">
                <input type="hidden" name="action" value="backup_database">
                <button type="submit" class="backup-btn">Create Database Backup</button>
            </form>
        </div>
        
        <!-- Today's Sales Backup -->
        <div class="backup-card">
            <h3>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Today's Sales Backup
            </h3>
            <p>Backup all sales transactions from today</p>
            <form method="POST">
                <input type="hidden" name="action" value="backup_today_sales">
                <button type="submit" class="backup-btn secondary">Backup Today's Sales</button>
            </form>
        </div>
        
        <!-- Date Range Backup -->
        <div class="backup-card">
            <h3>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Date Range Backup
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="backup_date_range">
                <div class="form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>End Date:</label>
                    <input type="date" name="end_date" required>
                </div>
                <button type="submit" class="backup-btn secondary">Backup Date Range</button>
            </form>
        </div>
    </div>
    
    <!-- Restore Section -->
    <div class="backup-grid">
        <!-- Restore from File -->
        <div class="backup-card">
            <h3>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Restore Sales
            </h3>
            <p>Upload a backup file to restore sales transactions</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="restore_sales">
                <div class="form-group">
                    <input type="file" name="backup_file" accept=".json" required>
                </div>
                <button type="submit" class="backup-btn secondary">Restore from File</button>
            </form>
        </div>
        
        <!-- Clean Old Backups -->
        <div class="backup-card">
            <h3>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Clean Old Backups
            </h3>
            <p>Delete old backup files to save disk space</p>
            <form method="POST">
                <input type="hidden" name="action" value="clean_old_backups">
                <div class="form-group">
                    <label>Keep Last (days):</label>
                    <input type="number" name="days_to_keep" value="30" min="7" max="365" required>
                </div>
                <button type="submit" class="backup-btn danger" onclick="return confirm('Are you sure? This will delete old backup files.')">Clean Old Backups</button>
            </form>
        </div>
    </div>
    
    <!-- Sales Backups List -->
    <div class="backup-table">
        <h3>📦 Sales Transaction Backups</h3>
        <?php if (count($salesBackups) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Date Created</th>
                        <th>Size</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesBackups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                            <td><?php echo $backup['date']; ?></td>
                            <td><span class="badge badge-info"><?php echo $backup['readable_size']; ?></span></td>
                            <td>
                                <a href="backup.php?download=<?php echo urlencode($backup['filename']); ?>&type=sales" class="download-link">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No sales backups available yet. Backups are created automatically with each sale.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Database Backups List -->
    <div class="backup-table">
        <h3>🗄️ Full Database Backups</h3>
        <?php if (count($dbBackups) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Date Created</th>
                        <th>Size</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbBackups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                            <td><?php echo $backup['date']; ?></td>
                            <td><span class="badge badge-success"><?php echo $backup['readable_size']; ?></span></td>
                            <td>
                                <a href="backup.php?download=<?php echo urlencode($backup['filename']); ?>&type=database" class="download-link">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No database backups available yet. Create your first backup above.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Info Box -->
    <div class="backup-card" style="background: #f8f9fa; border: 2px solid #dee2e6;">
        <h3>ℹ️ Automatic Backup Information</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #666;">
            <li>✅ Every sale is automatically backed up immediately after completion</li>
            <li>✅ Backups are stored as JSON files in the <code>backups/</code> directory</li>
            <li>✅ Both individual sales and daily logs are maintained</li>
            <li>✅ Database backups include all tables and data</li>
            <li>✅ Backups are protected and not accessible via web browser</li>
            <li>✅ You can restore sales even if the database is deleted</li>
            <li>⚠️ Store backups externally (USB drive, cloud) for extra safety</li>
        </ul>
    </div>
</div>

<?php include '../components/layout-end.php'; ?>
