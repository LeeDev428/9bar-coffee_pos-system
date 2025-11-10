<?php
$page_title = 'DASHBOARD';
include '../components/main-layout.php';

// Initialize dashboard
$dashboard = new Dashboard($db);
$stats = $dashboard->getDashboardStats();
?>

<!-- Dashboard Stats (Top 4 Cards) -->
<div class="stats-grid">
    <div class="stat-card" style="background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);">
        <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <h3 class="stat-title">Daily Sales</h3>
        <div class="stat-value"><?php echo formatCurrency($stats['daily_sales'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);">
        <div class="stat-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <h3 class="stat-title">Items Sold Today</h3>
        <div class="stat-value"><?php echo number_format($stats['quantity_sold_today'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
        <div class="stat-icon">
            <i class="fas fa-cube"></i>
        </div>
        <h3 class="stat-title">Total Products</h3>
        <div class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #16a085 0%, #138d75 100%);">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="stat-title">Low Stock Items</h3>
        <div class="stat-value" style="color: #fff;">
            <?php echo number_format($stats['critical_items'] ?? 0); ?>
        </div>
    </div>
</div>

<!-- Cash vs GCash Stats (Below the 4 boxes) -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; margin-top: 20px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
            <i class="fas fa-money-bill-wave" style="color: white;"></i>
        </div>
        <h3 class="stat-title" style="color: white;">💵 Cash Sales</h3>
        <div class="stat-value" style="color: white;"><?php echo formatCurrency($stats['cash_sales'] ?? 0); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
            <i class="fas fa-mobile-alt" style="color: white;"></i>
        </div>
        <h3 class="stat-title" style="color: white;">📱 GCash Sales</h3>
        <div class="stat-value" style="color: white;"><?php echo formatCurrency($stats['cashless_sales'] ?? 0); ?></div>
    </div>
</div>

<!-- Welcome Section -->
<div style="background: white; padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 25px;">
    <h2 style="color: #3E363F; margin-bottom: 10px;">Hi <?php echo htmlspecialchars($currentUser['full_name']); ?>!</h2>
    <p style="color: #7f8c8d; font-size: 16px;">Welcome back to your staff dashboard. Ready to serve some great coffee?</p>
</div>

<!-- Quick Actions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: white; padding: 25px; border-radius: 8px; text-align: center;">
        <div style="background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
            <i class="fas fa-cash-register"></i>
        </div>
        <h4 style="color: #3E363F; margin-bottom: 10px;">Start Selling</h4>
        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">Begin processing customer orders</p>
        <a href="pos.php" style="background: #3E363F; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Go to POS</a>
    </div>
    
    <div style="background: white; padding: 25px; border-radius: 8px; text-align: center;">
        <div style="background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
            <i class="fas fa-warehouse"></i>
        </div>
        <h4 style="color: #3E363F; margin-bottom: 10px;">Check Inventory</h4>
        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">View current stock levels</p>
        <a href="inventory.php" style="background: #3E363F; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">View Inventory</a>
    </div>
</div>

<!-- Daily Sold Products -->
<?php 
$dailyProducts = $dashboard->getDailySoldProducts();
?>
<div style="background: white; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #3E363F;">
        <h3 style="color: #3E363F; margin: 0; font-size: 20px;">
            <i class="fas fa-chart-line" style="margin-right: 10px;"></i>Today's Sold Products
        </h3>
        <span style="background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%); color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600;">
            <?php echo count($dailyProducts); ?> Products
        </span>
    </div>
    
    <?php if (empty($dailyProducts)): ?>
        <div style="text-align: center; padding: 40px 20px; color: #7f8c8d;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
            <p style="margin: 0; font-size: 16px;">No products sold today yet. Start selling to see them here!</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
            <?php foreach ($dailyProducts as $product): 
                $imagePath = !empty($product['image_path']) ? '../../assets/img/products/' . htmlspecialchars($product['image_path']) : '';
            ?>
                <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px; border-radius: 8px; border: 2px solid #e0e0e0; transition: all 0.2s;" onmouseover="this.style.borderColor='#3E363F'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e0e0e0'; this.style.transform='translateY(0)'">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 50px; height: 50px; border-radius: 8px; background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                            <?php if ($imagePath && file_exists($imagePath)): ?>
                                <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                                <i class="fas fa-coffee" style="color: white; font-size: 24px;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h4 style="color: #3E363F; margin: 0 0 3px 0; font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                            <p style="color: #7f8c8d; margin: 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1);">
                        <div>
                            <p style="color: #7f8c8d; margin: 0 0 3px 0; font-size: 11px; text-transform: uppercase;">Quantity</p>
                            <p style="color: #3E363F; margin: 0; font-size: 18px; font-weight: 700;">
                                <i class="fas fa-shopping-cart" style="font-size: 12px; margin-right: 3px; color: #e67e22;"></i><?php echo number_format($product['total_quantity']); ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <p style="color: #7f8c8d; margin: 0 0 3px 0; font-size: 11px; text-transform: uppercase;">Revenue</p>
                            <p style="color: #27ae60; margin: 0; font-size: 18px; font-weight: 700;"><?php echo formatCurrency($product['total_revenue']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../components/layout-end.php'; ?>