<?php
$page_title = 'DASHBOARD';
include '../components/main-layout.php';

// Initialize dashboard
$dashboard = new Dashboard($db);
$stats = $dashboard->getDashboardStats();
?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <h3 class="stat-title">Daily Sales</h3>
        <div class="stat-value"><?php echo formatCurrency($stats['daily_sales'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <h3 class="stat-title">Items Sold Today</h3>
        <div class="stat-value"><?php echo number_format($stats['quantity_sold_today'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-cube"></i>
        </div>
        <h3 class="stat-title">Total Products</h3>
        <div class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="stat-title">Low Stock Items</h3>
        <div class="stat-value" style="color: #e74c3c;">
            <?php echo number_format($stats['critical_items'] ?? 0); ?>
        </div>
    </div>
</div>

<!-- Welcome Section -->
<div style="background: white; padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 25px;">
    <h2 style="color: #3b2f2b; margin-bottom: 10px;">Hi <?php echo htmlspecialchars($currentUser['full_name']); ?>!</h2>
    <p style="color: #7f8c8d; font-size: 16px;">Welcome back to your staff dashboard. Ready to serve some great coffee?</p>
</div>

<!-- Quick Actions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
    <div style="background: white; padding: 25px; border-radius: 8px; text-align: center;">
        <div style="background: linear-gradient(135deg,#5a3f36 0%,#3b2f2b 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
            <i class="fas fa-cash-register"></i>
        </div>
        <h4 style="color: #3b2f2b; margin-bottom: 10px;">Start Selling</h4>
        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">Begin processing customer orders</p>
        <a href="pos.php" style="background: #5a3f36; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Go to POS</a>
    </div>
    
    <div style="background: white; padding: 25px; border-radius: 8px; text-align: center;">
        <div style="background: linear-gradient(135deg,#5a3f36 0%,#3b2f2b 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
            <i class="fas fa-warehouse"></i>
        </div>
        <h4 style="color: #3b2f2b; margin-bottom: 10px;">Check Inventory</h4>
        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">View current stock levels</p>
        <a href="inventory.php" style="background: #5a3f36; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">View Inventory</a>
    </div>
</div>

<?php include '../components/layout-end.php'; ?>