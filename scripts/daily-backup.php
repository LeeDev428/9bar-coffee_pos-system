<?php
/**
 * Automatic Daily Backup Cron Job
 * 
 * This script should be run daily via Windows Task Scheduler or cron
 * 
 * Windows Task Scheduler Setup:
 * 1. Open Task Scheduler
 * 2. Create Basic Task
 * 3. Name: "9Bar POS Daily Backup"
 * 4. Trigger: Daily at 11:59 PM
 * 5. Action: Start a program
 * 6. Program: C:\laragon\bin\php\php-8.x.x-Win32\php.exe
 * 7. Arguments: C:\laragon\www\9bar-coffee_pos-system\scripts\daily-backup.php
 * 
 * Or run manually:
 * php C:\laragon\www\9bar-coffee_pos-system\scripts\daily-backup.php
 */

// Change to project root directory
chdir(dirname(__DIR__));

require_once 'includes/database.php';
require_once 'includes/BackupManager.php';

echo "========================================\n";
echo "9Bar POS - Daily Automatic Backup\n";
echo "========================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Initialize database and backup manager
    $db = new Database();
    $backupManager = new BackupManager($db);
    
    echo "1. Creating daily sales backup...\n";
    $salesBackup = $backupManager->dailyAutoBackup();
    
    if ($salesBackup['success']) {
        echo "   ✓ Sales backup created: {$salesBackup['filename']}\n";
        echo "   ✓ Total sales backed up: {$salesBackup['total_sales']}\n";
    } else {
        echo "   ✗ Sales backup failed: {$salesBackup['error']}\n";
    }
    
    echo "\n2. Creating full database backup...\n";
    $dbConfig = [
        'host' => 'localhost',
        'dbname' => '9bar_pos',
        'username' => 'root',
        'password' => ''
    ];
    
    $dbBackup = $backupManager->backupDatabase($dbConfig);
    
    if ($dbBackup['success']) {
        echo "   ✓ Database backup created: {$dbBackup['filename']}\n";
        echo "   ✓ Size: " . round($dbBackup['size'] / 1024, 2) . " KB\n";
    } else {
        echo "   ✗ Database backup failed: {$dbBackup['error']}\n";
    }
    
    echo "\n3. Cleaning old backups (keeping last 30 days)...\n";
    $deleted = $backupManager->cleanOldBackups(30);
    echo "   ✓ Deleted {$deleted} old backup files\n";
    
    echo "\n========================================\n";
    echo "Backup completed successfully!\n";
    echo "========================================\n";
    
    // Log success
    $logFile = dirname(__DIR__) . '/backups/backup.log';
    $logEntry = date('Y-m-d H:i:s') . " - Daily backup completed. Sales: {$salesBackup['total_sales']}, DB Size: " . round($dbBackup['size'] / 1024, 2) . " KB, Cleaned: {$deleted} files\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    
    // Log error
    $logFile = dirname(__DIR__) . '/backups/backup.log';
    $logEntry = date('Y-m-d H:i:s') . " - Backup FAILED: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    exit(1);
}
?>
