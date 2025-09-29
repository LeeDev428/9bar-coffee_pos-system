<?php
$page_title = 'DASHBOARD';
include '../components/main-layout.php';

// Initialize dashboard
$dashboard = new Dashboard($db);
$stats = $dashboard->getDashboardStats();
$bestSelling = $dashboard->getBestSellingProducts(5);
$criticalItems = $dashboard->getCriticalItems(5);
$salesChart = $dashboard->getSalesChart(7);
$productChart = $dashboard->getProductQuantityChart();
?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="stat-card stat-card--green">
        <div class="stat-header">
            <h3 class="stat-title">Daily Sales</h3>
            <div class="stat-icon">
                <span class="currency-sign">₱</span>
            </div>
        </div>
        <p class="stat-value"><?php echo formatCurrency($stats['daily_sales'] ?? 0); ?></p>
    </div>
    
    <div class="stat-card stat-card--orange">
        <div class="stat-header">
            <h3 class="stat-title">Quantity Sold Today</h3>
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
        </div>
        <p class="stat-value"><?php echo number_format($stats['quantity_sold_today'] ?? 0); ?></p>
    </div>
    
    <div class="stat-card stat-card--purple">
        <div class="stat-header">
            <h3 class="stat-title">Total Product</h3>
            <div class="stat-icon">
                <i class="fas fa-cube"></i>
            </div>
        </div>
        <p class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></p>
    </div>
    
    <div class="stat-card stat-card--teal">
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
            <div class="best-selling-list">
            <?php if (!empty($bestSelling)): ?>
                <?php foreach (array_slice($bestSelling, 0, 5) as $index => $product): ?>
                    <?php $count = (int)($product['quantity_sold'] ?? 0); $pct = min(100, $count * 5); ?>
                    <div class="best-item">
                        <div class="item-row">
                            <div class="item-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="item-count"><?php echo number_format($count); ?></div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $pct; ?>%;"></div>
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
    </div>
    
    <!-- Product Category Pie Chart -->
    <div class="chart-container">
        <h3 class="chart-title">Categories</h3>
        <div style="height: 250px; position: relative;">
            <canvas id="categoryChart"></canvas>
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
                    backgroundColor: ['#2d9cdb', '#f39c12', '#27ae60', '#9b59b6'],
                    borderRadius: 8,
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
                backgroundColor: ['#2d9cdb', '#f39c12', '#27ae60', '#e74c3c', '#9b59b6'],
                borderRadius: 8
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
                backgroundColor: ['#2d9cdb', '#f39c12', '#27ae60', '#9b59b6'],
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

<?php include '../components/layout-end.php'; ?>