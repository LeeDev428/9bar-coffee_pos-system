<?php
// Staff Dashboard Page
$page_title = 'DASHBOARD';
include '../components/main-layout.php';

// Initialize managers
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get dashboard statistics
$todaysSales = $db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = CURDATE()") ?? 0;
$todaysQuantity = $db->fetchValue("SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id WHERE DATE(s.sale_date) = CURDATE()") ?? 0;
$totalProducts = $db->fetchValue("SELECT COUNT(*) FROM products WHERE status = 'active'") ?? 0;
$lowStockCount = $db->fetchValue("SELECT COUNT(*) FROM inventory WHERE current_stock <= minimum_stock") ?? 0;

// Get recent sales for staff view (removed customer_name column)
$recentSales = $db->fetchAll("
    SELECT s.transaction_number, s.total_amount, s.sale_date, s.payment_method
    FROM sales s 
    WHERE s.user_id = ? OR ? = 'admin'
    ORDER BY s.sale_date DESC 
    LIMIT 10
", [$currentUser['user_id'], $currentUser['role']]) ?? [];

// Get low stock items
$lowStockItems = $db->fetchAll("
    SELECT p.product_name, i.current_stock, i.minimum_stock, c.category_name
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE i.current_stock <= i.minimum_stock
    ORDER BY i.current_stock ASC
    LIMIT 10
") ?? [];
?>

<!-- Staff Dashboard Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-currency-dollar"></i>
        </div>
        <div class="stat-title">Today's Sales</div>
        <div class="stat-value">₱<?php echo number_format($todaysSales, 2); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-box-seam"></i>
        </div>
        <div class="stat-title">Items Sold Today</div>
        <div class="stat-value"><?php echo number_format($todaysQuantity); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-grid-3x3-gap"></i>
        </div>
        <div class="stat-title">Available Products</div>
        <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-title">Low Stock Alert</div>
        <div class="stat-value"><?php echo number_format($lowStockCount); ?></div>
    </div>
</div>

<!-- Recent Activity and Alerts -->
<div class="row">
    <div class="col-md-8">
        <div class="table-responsive">
            <h5 class="mb-3">Recent Sales</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No recent sales found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($sale['transaction_number']); ?></code></td>
                            <td class="text-success fw-bold">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo ucfirst($sale['payment_method']); ?></span>
                            </td>
                            <td><?php echo date('M j, g:i A', strtotime($sale['sale_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="table-responsive">
            <h5 class="mb-3">Low Stock Alert</h5>
            <?php if (empty($lowStockItems)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> All items are well stocked!
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($lowStockItems as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                        </div>
                        <span class="badge bg-danger rounded-pill">
                            <?php echo $item['current_stock']; ?>/<?php echo $item['minimum_stock']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions for Staff -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Quick Actions</h5>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="pos.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-calculator"></i> Start New Sale
                    </a>
                    <a href="inventory.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-boxes"></i> Check Inventory
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</div> <!-- End content -->
</div> <!-- End main-content -->
</body>
</html>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BrewTopia POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-user">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p><?php echo ucfirst($user['role']); ?></p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>DASHBOARD</span>
                </a>
                
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin/pages/products.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>MANAGE PRODUCT</span>
                </a>
                
                <a href="admin/pages/inventory.php" class="nav-item">
                    <i class="fas fa-warehouse"></i>
                    <span>INVENTORY</span>
                </a>
                
                <a href="admin/pages/records.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>RECORD</span>
                </a>
                
                <a href="admin/pages/settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>SETTING</span>
                </a>
                <?php else: ?>
                <a href="staff/pages/pos.php" class="nav-item">
                    <i class="fas fa-cash-register"></i>
                    <span>POINT OF SALE</span>
                </a>
                
                <a href="staff/pages/inventory.php" class="nav-item">
                    <i class="fas fa-warehouse"></i>
                    <span>INVENTORY</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <a href="?action=logout" class="nav-item" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>LOG OUT</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 class="header-title">DASHBOARD</h1>
                <p class="header-subtitle">
                    <?php echo date('l, M d, Y h:i:s A'); ?>
                </p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <!-- Welcome Section -->
                <div style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <h2 style="color: #2c3e50; margin: 0; font-size: 1.5rem;">Hi <?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p style="color: #7f8c8d; margin: 5px 0 0 0; font-size: 1.1rem;">WELCOME BACK</p>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Daily Sales</h3>
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <p class="stat-value"><?php echo formatCurrency($stats['daily_sales'] ?? 0); ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Quantity Sold Today</h3>
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <p class="stat-value"><?php echo number_format($stats['quantity_sold_today'] ?? 0); ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Total Product</h3>
                            <div class="stat-icon">
                                <i class="fas fa-cube"></i>
                            </div>
                        </div>
                        <p class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Critical Items</h3>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <p class="stat-value" style="color: #e74c3c;">
                            <?php echo number_format($stats['critical_items'] ?? 0); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Analytics Grid -->
                <div class="analytics-grid">
                    <!-- Sales Chart -->
                    <div class="chart-container">
                        <h3 class="chart-title">Daily Sales</h3>
                        <div style="height: 300px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Product Quantity Chart (Horizontal Bar) -->
                    <div class="chart-container">
                        <h3 class="chart-title">Product Quantities</h3>
                        <div style="height: 300px;">
                            <canvas id="productChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Product Charts Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <!-- Best Selling Products Chart -->
                    <div class="chart-container">
                        <h3 class="chart-title">Best Selling</h3>
                        <div style="height: 250px; padding: 20px;">
                            <?php if (!empty($bestSelling)): ?>
                                <?php foreach (array_slice($bestSelling, 0, 5) as $index => $product): ?>
                                    <div style="margin-bottom: 15px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                            <span style="font-size: 0.9rem; color: #2c3e50;"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                            <span style="font-size: 0.8rem; color: #7f8c8d;"><?php echo number_format($product['quantity_sold'] ?? 0); ?></span>
                                        </div>
                                        <div style="height: 8px; background: #ecf0f1; border-radius: 4px;">
                                            <div style="height: 100%; background: #e67e22; border-radius: 4px; width: <?php echo min(100, ($product['quantity_sold'] ?? 0) * 5); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; color: #7f8c8d; padding: 40px;">
                                    <p>No sales data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Product Category Pie Chart -->
                    <div class="chart-container">
                        <h3 class="chart-title">Categories</h3>
                        <div style="height: 250px; position: relative;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Sales Chart (Vertical Bar Chart like in screenshot)
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Daily Sales',
                    data: [225, 175, 200, 250],
                    backgroundColor: '#95a5a6',
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        max: 300,
                        grid: { color: '#e0e0e0' },
                        ticks: { stepSize: 50 }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
        
        // Product Quantity Chart (Horizontal Bar Chart like in screenshot)
        const productCtx = document.getElementById('productChart').getContext('2d');
        const productChart = new Chart(productCtx, {
            type: 'bar',
            data: {
                labels: ['CHOCO HAZELNUT', 'MATCHA', 'DOUBLE DUTCH', 'CHOCOLATE', 'ORIGINAL'],
                datasets: [{
                    data: [40, 30, 25, 35, 30],
                    backgroundColor: '#95a5a6',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { 
                        beginAtZero: true,
                        max: 50,
                        grid: { color: '#e0e0e0' }
                    },
                    y: { 
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
        
        // Category Pie Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hot Coffee', 'Iced Coffee', 'Milkshake', 'Iced Coffee'],
                datasets: [{
                    data: [35, 25, 20, 20],
                    backgroundColor: ['#7f8c8d', '#95a5a6', '#bdc3c7', '#d5dbdb'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Add real-time updates (optional)
        function updateStats() {
            console.log('Stats updated at:', new Date().toLocaleString());
        }
        
        // Update stats every 5 minutes
        setInterval(updateStats, 300000);
    </script>
</body>
</html>