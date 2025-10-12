<?php
/**
 * Users Seeder Script
 * This script creates sample admin and staff users for testing
 * Created: October 11, 2025
 */

// Database configuration
$host = 'localhost';
$dbname = '9bar_pos';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=================================================\n";
    echo "       9BARS POS - Users Seeder Script\n";
    echo "=================================================\n\n";
    
    // Check if users already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email IN (?, ?)");
    $stmt->execute(['admin@gmail.com', 'staff@gmail.com']);
    $existingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existingCount > 0) {
        echo "⚠️  Warning: Some users already exist!\n";
        echo "Do you want to delete existing users and recreate them? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) == 'y' || trim($line) == 'Y') {
            $pdo->exec("DELETE FROM users WHERE email IN ('admin@gmail.com', 'staff@gmail.com')");
            echo "✓ Existing users deleted.\n\n";
        } else {
            echo "❌ Seeding cancelled.\n";
            exit(0);
        }
    }
    
    // Prepare insert statement
    $sql = "INSERT INTO users (username, password, full_name, email, role, status, created_at, updated_at) 
            VALUES (:username, :password, :full_name, :email, :role, :status, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    // Users data
    $users = [
        [
            'username' => 'admin',
            'password' => md5('admin123'), // You can change to password_hash('admin123', PASSWORD_BCRYPT) for better security
            'full_name' => 'System Administrator',
            'email' => 'admin@gmail.com',
            'role' => 'admin',
            'status' => 'active'
        ],
        [
            'username' => 'staff',
            'password' => md5('staff123'), // You can change to password_hash('staff123', PASSWORD_BCRYPT) for better security
            'full_name' => 'Staff Member',
            'email' => 'staff@gmail.com',
            'role' => 'staff',
            'status' => 'active'
        ]
    ];
    
    // Insert users
    $insertedCount = 0;
    foreach ($users as $user) {
        try {
            $stmt->execute($user);
            $insertedCount++;
            echo "✓ Created {$user['role']} user: {$user['email']}\n";
        } catch (PDOException $e) {
            echo "✗ Failed to create {$user['email']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=================================================\n";
    echo "   Seeding completed! {$insertedCount} users created.\n";
    echo "=================================================\n\n";
    
    // Display created users
    echo "Created Users:\n";
    echo "-------------------------------------------------\n";
    $stmt = $pdo->query("SELECT user_id, username, full_name, email, role, status, created_at 
                         FROM users 
                         WHERE email IN ('admin@gmail.com', 'staff@gmail.com')
                         ORDER BY user_id");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "\n";
        echo "ID: {$user['user_id']}\n";
        echo "Username: {$user['username']}\n";
        echo "Full Name: {$user['full_name']}\n";
        echo "Email: {$user['email']}\n";
        echo "Role: {$user['role']}\n";
        echo "Status: {$user['status']}\n";
        echo "Created: {$user['created_at']}\n";
        echo "-------------------------------------------------\n";
    }
    
    echo "\n";
    echo "=================================================\n";
    echo "           LOGIN CREDENTIALS\n";
    echo "=================================================\n";
    echo "\n📧 Admin Account:\n";
    echo "   Email: admin@gmail.com\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n";
    echo "\n📧 Staff Account:\n";
    echo "   Email: staff@gmail.com\n";
    echo "   Username: staff\n";
    echo "   Password: staff123\n";
    echo "\n=================================================\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
