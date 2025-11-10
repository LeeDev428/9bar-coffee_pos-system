<?php
/**
 * Database Setup Script
 * Imports the complete SQL structure
 */

// Read the SQL file
$sqlFile = __DIR__ . '/9bar_pos_complete.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at $sqlFile\n");
}

echo "Reading SQL file...\n";
$sql = file_get_contents($sqlFile);

// Connect to database
require_once 'includes/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Connected to database successfully.\n";
    echo "Importing tables...\n\n";
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "✓ Database import completed successfully!\n\n";
    
    // Verify tables
    $tables = $db->fetchAll("SHOW TABLES");
    echo "=== TABLES IN DATABASE ===\n";
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "✓ $tableName\n";
    }
    
    echo "\n✓ All tables imported successfully!\n";
    echo "You can now use the POS system.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
