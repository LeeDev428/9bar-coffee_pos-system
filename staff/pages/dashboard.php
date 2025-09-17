<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Require login
$auth->requireLogin();

// Get user and redirect based on role
$user = $auth->getCurrentUser();

// Role-based dashboard routing
if ($user['role'] === 'admin') {
    // Admin gets full dashboard
    $dashboard = new Dashboard($db);
    $stats = $dashboard->getDashboardStats();
    $bestSelling = $dashboard->getBestSellingProducts(5);
    $criticalItems = $dashboard->getCriticalItems(5);
    $salesChart = $dashboard->getSalesChart(7);
    $productChart = $dashboard->getProductQuantityChart();
} else {
    // Staff gets limited dashboard
    $dashboard = new Dashboard($db);
    $stats = $dashboard->getDashboardStats();
    // Limited data for staff
    $bestSelling = [];
    $criticalItems = [];
    $salesChart = [];
    $productChart = [];
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    redirectTo('login.php');
}
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