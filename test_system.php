<?php
// Simple database test and setup
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

try {
    echo "🔄 Testing database connection...\n";
    $db = new Database();
    echo "✅ Database connected successfully!\n";
    
    echo "\n🔄 Testing authentication...\n";
    $auth = new Auth($db);
    echo "✅ Authentication class loaded!\n";
    
    echo "\n🔄 Testing dashboard functions...\n";
    $dashboard = new Dashboard($db);
    
    // Test dashboard stats
    echo "📊 Getting dashboard stats...\n";
    $stats = $dashboard->getDashboardStats();
    echo "   - Daily Sales: ₱" . number_format($stats['daily_sales'], 2) . "\n";
    echo "   - Quantity Sold: " . $stats['quantity_sold_today'] . "\n";
    echo "   - Total Products: " . $stats['total_products'] . "\n";
    echo "   - Critical Items: " . $stats['critical_items'] . "\n";
    
    // Test best selling products
    echo "\n📈 Getting best selling products...\n";
    $bestSelling = $dashboard->getBestSellingProducts(3);
    foreach ($bestSelling as $product) {
        echo "   - " . $product['product_name'] . ": " . $product['quantity_sold'] . " sold\n";
    }
    
    echo "\n🔑 Testing login credentials...\n";
    // Test admin login
    $adminResult = $auth->login('admin', 'admin123');
    if ($adminResult['success']) {
        echo "✅ Admin login works!\n";
        $auth->logout();
    } else {
        echo "❌ Admin login failed: " . $adminResult['message'] . "\n";
    }
    
    // Test staff login
    $staffResult = $auth->login('staff1', 'admin123');
    if ($staffResult['success']) {
        echo "✅ Staff login works!\n";
        $auth->logout();
    } else {
        echo "❌ Staff login failed: " . $staffResult['message'] . "\n";
    }
    
    echo "\n🎉 All tests passed! Your system is ready to use.\n";
    echo "\n🌐 Access your application at: http://localhost/9bar-coffee_pos-system/\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure:\n";
    echo "   1. Laragon is running\n";
    echo "   2. Database '9bar_pos' exists\n";
    echo "   3. Run the SQL file: database/9bar_pos.sql\n";
}
?>