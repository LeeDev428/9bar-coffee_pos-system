<?php
/**
 * Backup Manager Class
 * Handles automatic backups of sales transactions and database
 */

class BackupManager {
    private $db;
    private $backupDir;
    private $salesBackupDir;
    private $dbBackupDir;
    
    public function __construct($database) {
        $this->db = $database;
        
        // Create backup directories
        $baseDir = dirname(__DIR__) . '/backups';
        $this->backupDir = $baseDir;
        $this->salesBackupDir = $baseDir . '/sales';
        $this->dbBackupDir = $baseDir . '/database';
        
        // Create directories if they don't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!is_dir($this->salesBackupDir)) {
            mkdir($this->salesBackupDir, 0755, true);
        }
        if (!is_dir($this->dbBackupDir)) {
            mkdir($this->dbBackupDir, 0755, true);
        }
        
        // Create .htaccess to protect backup files
        $this->createHtaccess();
    }
    
    /**
     * Create .htaccess file to protect backup directory
     */
    private function createHtaccess() {
        $htaccessFile = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $content = "# Deny access to backup files\n";
            $content .= "Order Deny,Allow\n";
            $content .= "Deny from all\n";
            file_put_contents($htaccessFile, $content);
        }
    }
    
    /**
     * Backup sales transaction to JSON file
     * Called immediately after a sale is completed
     */
    public function backupSaleTransaction($saleId) {
        try {
            // Get sale details
            $sql = "SELECT s.*, u.username, u.full_name 
                    FROM sales s 
                    LEFT JOIN users u ON s.user_id = u.user_id 
                    WHERE s.sale_id = ?";
            $sale = $this->db->fetchOne($sql, [$saleId]);
            
            if (!$sale) {
                return false;
            }
            
            // Get sale items
            $itemsSql = "SELECT si.*, p.product_name, p.price as product_price 
                         FROM sale_items si 
                         LEFT JOIN products p ON si.product_id = p.product_id 
                         WHERE si.sale_id = ?";
            $items = $this->db->fetchAll($itemsSql, [$saleId]);
            
            // Prepare backup data
            $backupData = [
                'backup_date' => date('Y-m-d H:i:s'),
                'backup_type' => 'sale_transaction',
                'sale' => $sale,
                'items' => $items
            ];
            
            // Create filename with date and sale ID
            $date = date('Y-m-d');
            $filename = "sale_{$saleId}_{$date}_" . time() . ".json";
            $filepath = $this->salesBackupDir . '/' . $filename;
            
            // Save to JSON file
            $json = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($filepath, $json);
            
            // Also append to daily log file
            $this->appendToDailyLog($backupData);
            
            return true;
        } catch (Exception $e) {
            error_log("Backup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Append sale to daily consolidated log
     */
    private function appendToDailyLog($backupData) {
        $date = date('Y-m-d');
        $logFile = $this->salesBackupDir . "/daily_log_{$date}.json";
        
        $dailyData = [];
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $dailyData = json_decode($content, true);
            if (!is_array($dailyData)) {
                $dailyData = [];
            }
        }
        
        $dailyData[] = $backupData;
        
        file_put_contents($logFile, json_encode($dailyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Backup all sales for a specific date range
     */
    public function backupSalesByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT s.*, u.username, u.full_name 
                    FROM sales s 
                    LEFT JOIN users u ON s.user_id = u.user_id 
                    WHERE DATE(s.sale_date) BETWEEN ? AND ?
                    ORDER BY s.sale_date ASC";
            $sales = $this->db->fetchAll($sql, [$startDate, $endDate]);
            
            $backupData = [
                'backup_date' => date('Y-m-d H:i:s'),
                'backup_type' => 'date_range',
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'total_sales' => count($sales),
                'sales' => []
            ];
            
            foreach ($sales as $sale) {
                $itemsSql = "SELECT si.*, p.product_name 
                             FROM sale_items si 
                             LEFT JOIN products p ON si.product_id = p.product_id 
                             WHERE si.sale_id = ?";
                $items = $this->db->fetchAll($itemsSql, [$sale['sale_id']]);
                
                $backupData['sales'][] = [
                    'sale' => $sale,
                    'items' => $items
                ];
            }
            
            $filename = "sales_backup_{$startDate}_to_{$endDate}_" . time() . ".json";
            $filepath = $this->salesBackupDir . '/' . $filename;
            
            file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return [
                'success' => true,
                'filename' => $filename,
                'total_sales' => count($sales),
                'filepath' => $filepath
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup entire database
     */
    public function backupDatabase($config = []) {
        try {
            $host = $config['host'] ?? 'localhost';
            $dbname = $config['dbname'] ?? '9bar_pos';
            $username = $config['username'] ?? 'root';
            $password = $config['password'] ?? '';
            
            $filename = "database_backup_" . date('Y-m-d_His') . ".sql";
            $filepath = $this->dbBackupDir . '/' . $filename;
            
            // Use mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --databases %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );
            
            // If password is empty, remove the password parameter
            if (empty($password)) {
                $command = sprintf(
                    'mysqldump --host=%s --user=%s --databases %s > %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($username),
                    escapeshellarg($dbname),
                    escapeshellarg($filepath)
                );
            }
            
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                // Compress the backup
                $this->compressFile($filepath);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => filesize($filepath)
                ];
            } else {
                // Fallback to PHP-based backup
                return $this->phpDatabaseBackup($dbname, $filepath);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PHP-based database backup (fallback method)
     */
    private function phpDatabaseBackup($dbname, $filepath) {
        try {
            $pdo = $this->db->getConnection();
            
            $backup = "-- 9Bar POS Database Backup\n";
            $backup .= "-- Backup Date: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: {$dbname}\n\n";
            $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Get table structure
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
                $backup .= "-- Table: {$table}\n";
                $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
                
                // createTable[0] = table name, createTable[1] = CREATE TABLE statement
                if (isset($createTable[1])) {
                    $backup .= $createTable[1] . ";\n\n";
                } else {
                    // Fallback if fetch fails
                    continue;
                }
                
                // Get table data
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($pdo) {
                            return $value === null ? 'NULL' : $pdo->quote($value);
                        }, array_values($row));
                        
                        $backup .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $backup);
            
            // Compress the backup
            $this->compressFile($filepath);
            
            return [
                'success' => true,
                'filename' => basename($filepath),
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'method' => 'php_backup'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Compress backup file using gzip
     */
    private function compressFile($filepath) {
        if (function_exists('gzopen') && file_exists($filepath)) {
            $gzFilepath = $filepath . '.gz';
            
            $fp = fopen($filepath, 'rb');
            $gzfp = gzopen($gzFilepath, 'wb9');
            
            while (!feof($fp)) {
                gzwrite($gzfp, fread($fp, 1024 * 512));
            }
            
            fclose($fp);
            gzclose($gzfp);
            
            // Keep both compressed and uncompressed versions
        }
    }
    
    /**
     * Auto-backup: Daily sales backup
     */
    public function dailyAutoBackup() {
        $today = date('Y-m-d');
        return $this->backupSalesByDateRange($today, $today);
    }
    
    /**
     * Restore sales from backup file
     */
    public function restoreSalesFromBackup($backupFile) {
        try {
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found");
            }
            
            $content = file_get_contents($backupFile);
            $data = json_decode($content, true);
            
            if (!$data) {
                throw new Exception("Invalid backup file format");
            }
            
            $pdo = $this->db->getConnection();
            $pdo->beginTransaction();
            
            $restored = 0;
            $skipped = 0;
            
            // Handle different backup formats
            $salesToRestore = [];
            if (isset($data['sales']) && is_array($data['sales'])) {
                // Date range backup format
                $salesToRestore = $data['sales'];
            } elseif (isset($data['sale'])) {
                // Single sale backup format
                $salesToRestore = [$data];
            } elseif (is_array($data) && isset($data[0]['sale'])) {
                // Daily log format
                $salesToRestore = $data;
            }
            
            foreach ($salesToRestore as $saleData) {
                $sale = $saleData['sale'];
                $items = $saleData['items'];
                
                // Check if sale already exists
                $existing = $this->db->fetchOne(
                    "SELECT sale_id FROM sales WHERE transaction_number = ?",
                    [$sale['transaction_number']]
                );
                
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                // Insert sale
                $stmt = $pdo->prepare(
                    "INSERT INTO sales (sale_id, user_id, total_amount, payment_method, payment_status, ref_no, sale_date, transaction_number, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                
                $stmt->execute([
                    $sale['sale_id'],
                    $sale['user_id'],
                    $sale['total_amount'],
                    $sale['payment_method'],
                    $sale['payment_status'],
                    $sale['ref_no'] ?? null,
                    $sale['sale_date'],
                    $sale['transaction_number'],
                    $sale['created_at'] ?? $sale['sale_date']
                ]);
                
                // Insert sale items
                foreach ($items as $item) {
                    $itemStmt = $pdo->prepare(
                        "INSERT INTO sale_items (sale_id, product_id, unit_price, quantity, total_price, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    
                    $itemStmt->execute([
                        $sale['sale_id'],
                        $item['product_id'],
                        $item['unit_price'],
                        $item['quantity'],
                        $item['total_price'],
                        $item['subtotal'] ?? $item['total_price']
                    ]);
                }
                
                $restored++;
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'restored' => $restored,
                'skipped' => $skipped,
                'total' => $restored + $skipped
            ];
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of available backups
     */
    public function getBackupList($type = 'sales') {
        $dir = $type === 'sales' ? $this->salesBackupDir : $this->dbBackupDir;
        
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = scandir($dir, SCANDIR_SORT_DESCENDING);
        $backups = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess') {
                continue;
            }
            
            $filepath = $dir . '/' . $file;
            if (is_file($filepath)) {
                $backups[] = [
                    'filename' => $file,
                    'filepath' => $filepath,
                    'size' => filesize($filepath),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath)),
                    'readable_size' => $this->formatBytes(filesize($filepath))
                ];
            }
        }
        
        return $backups;
    }
    
    /**
     * Delete old backups (keep last N days)
     */
    public function cleanOldBackups($daysToKeep = 30) {
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $deleted = 0;
        
        $dirs = [$this->salesBackupDir, $this->dbBackupDir];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess') {
                    continue;
                }
                
                $filepath = $dir . '/' . $file;
                if (is_file($filepath) && filemtime($filepath) < $cutoffTime) {
                    unlink($filepath);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Format bytes to human readable size
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>
