<?php
// Admin Dashboard Page
$page_title = 'DASHBOARD';
include '../components/main-layout.php';

// Ensure content is offset from sidebar and charts are responsive
?>
<style>
     /* Adjust main content so it doesn't overlap the sidebar (match sidebar width)
         Use left padding so the inner container can remain centered in the viewport */
    /* smaller padding-left keeps content closer to sidebar while remaining centered */
    /* Align content flowing right after the sidebar (sidebar width: 180px) */
     /* Remove extra page-level padding so content sits next to the sidebar
         The layout's `.main-content` already reserves space for the fixed sidebar */
     .main-content-wrapper { padding-left: 0; padding-top: 20px; padding-bottom: 20px; }
     .main-inner { max-width: none; margin: 0; padding-right: 24px; }
    /* Chart card sizing */
    .chart-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); min-height: 360px; }
    .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: flex-start; width: 100%; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 18px; }
    .stat-card { padding: 18px; border-radius: 8px; color: #fff; }
    .stats-grid { align-items: center; }
    .chart-card canvas { width: 100% !important; height: 340px !important; }
    /* Specific chart sizes */
    #dailySalesChart, #topProductsChart { height: 320px !important; }
    /* Ensure tables and cards expand to container width */
    .card, .table-responsive { width: 100%; }
    /* Give space above tables so they don't collide with charts */
    .table-responsive { margin-top: 18px; }
    @media(max-width: 1200px) { .main-content-wrapper { padding-left: 60px; } }
    @media(max-width: 992px) { .charts-grid { grid-template-columns: 1fr; } .main-content-wrapper { padding-left: 0; } }
</style>

<div class="main-content-wrapper">
    <div class="main-inner">
<?php

// Initialize managers
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get dashboard statistics
$todaysSales = $db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = CURDATE()");
$todaysQuantity = $db->fetchValue("SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id WHERE DATE(s.sale_date) = CURDATE()");
$totalProducts = $db->fetchValue("SELECT COUNT(*) FROM products WHERE status = 'active'");
$lowStockCount = $db->fetchValue("SELECT COUNT(*) FROM inventory WHERE current_stock <= minimum_stock");

// Sample data for charts (you can replace with real data queries)
$chartLabels = ['ICED HAZELNUT', 'MATCHA', 'DOUBLE SHOT', 'CHOCOLATE', 'CARAMEL'];
$chartData = [23, 14, 11, 10, 8];
?>

<!-- Dashboard Stats Cards -->
<div class="stats-grid">
    <div class="stat-card" style="background:#2E8B57;color:#fff;">
        <div class="stat-icon" style="color:rgba(255,255,255,0.9);">
            <i class="bi bi-currency-dollar"></i>
        </div>
        <div class="stat-title">Daily Sales</div>
        <div class="stat-value">₱<?php echo number_format($todaysSales, 2); ?></div>
    </div>

    <div class="stat-card" style="background:#FF8C00;color:#fff;">
        <div class="stat-icon" style="color:rgba(255,255,255,0.95);">
            <i class="bi bi-box-seam"></i>
        </div>
        <div class="stat-title">Quantity Sold Today</div>
        <div class="stat-value"><?php echo number_format($todaysQuantity); ?></div>
    </div>

    <div class="stat-card" style="background:#6A5ACD;color:#fff;">
        <div class="stat-icon" style="color:rgba(255,255,255,0.95);">
            <i class="bi bi-grid-3x3-gap"></i>
        </div>
        <div class="stat-title">Total Product</div>
        <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
    </div>

    <div class="stat-card" style="background:#20B2AA;color:#fff;">
        <div class="stat-icon" style="color:rgba(255,255,255,0.95);">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-title">Critical Items</div>
        <div class="stat-value"><?php echo number_format($lowStockCount); ?></div>
    </div>
</div>

<!-- Charts Section -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-title">Sales Overview</div>
        <canvas id="salesChart"></canvas>
    </div>
    
    <div class="chart-card">
        <div class="chart-title">Product Performance</div>
        <canvas id="categoryChart"></canvas>
    </div>
</div>

<script>
// Bar Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Quantity Sold',
            data: <?php echo json_encode($chartData); ?>,
            backgroundColor: [
                '#20B2AA', /* teal */
                '#FF8C00', /* orange */
                '#6A5ACD', /* purple */
                '#FFD700', /* gold */
                '#5DADE2'  /* sky blue */
            ],
            borderColor: ['#1F8F85','#CC7000','#593FB8','#B68900','#4B9FD6'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 25,
                ticks: {
                    stepSize: 5
                }
            },
            x: {
                ticks: {
                    maxRotation: 0,
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});

// Pie Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: ['Iced Coffee', 'Hot Coffee', 'Fruit Tea', 'Milktea'],
        datasets: [{
            data: [35, 25, 25, 15],
            backgroundColor: [
                '#20B2AA', /* Iced Coffee - teal */
                '#FF8C00', /* Hot Coffee - orange */
                '#6A5ACD', /* Fruit Tea - purple */
                '#58D68D'  /* Milktea - light green */
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 8,
                    usePointStyle: true,
                    boxWidth: 12,
                    font: { size: 11 }
                }
            }
        }
    }
});
</script>

<!-- Daily Sales & Top Products Grid -->
<div class="charts-grid" style="margin-top: 24px;">
    <div class="chart-card">
        <div class="chart-title">Daily Sales</div>
        <canvas id="dailySalesChart"></canvas>
    </div>

    <div class="chart-card">
        <div class="chart-title">Top Products</div>
        <canvas id="topProductsChart"></canvas>
    </div>
</div>

<!-- Recent Sales -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Sales</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Transaction #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Recent sales: some schemas may not have `customer_name`. Select safely.
                            $recentSales = $db->fetchAll("
                                SELECT transaction_number, NULL AS customer_name, total_amount, sale_date, payment_status 
                                FROM sales 
                                ORDER BY sale_date DESC 
                                LIMIT 10
                            ");
                            
                            foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($sale['transaction_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                <td class="text-success fw-bold">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($sale['sale_date'])); ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo ucfirst($sale['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart.js initialization
document.addEventListener('DOMContentLoaded', function() {
    // Daily Sales Chart
    const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
    <?php
    // Get last 7 days sales data
    $salesData = $db->fetchAll("
        SELECT DATE(sale_date) as date, SUM(total_amount) as total
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(sale_date)
        ORDER BY date
    ");
    
    $dates = [];
    $amounts = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = date('M j', strtotime($date));
        
        $found = false;
        foreach ($salesData as $data) {
            if ($data['date'] == $date) {
                $amounts[] = floatval($data['total']);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $amounts[] = 0;
        }
    }
    ?>
    
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Daily Sales (₱)',
                data: <?php echo json_encode($amounts); ?>,
                borderColor: '#8B4513',
                backgroundColor: 'rgba(139, 69, 19, 0.08)',
                tension: 0.15,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#8B4513'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            elements: { line: { fill: true } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Top Products Chart
    const productsCtx = document.getElementById('topProductsChart').getContext('2d');
    <?php
    $topProducts = $db->fetchAll("
        SELECT p.product_name, SUM(si.quantity) as total_sold
        FROM products p
        JOIN sale_items si ON p.product_id = si.product_id
        JOIN sales s ON si.sale_id = s.sale_id
        WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY p.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    
    $productNames = array_column($topProducts, 'product_name');
    $productSales = array_map('intval', array_column($topProducts, 'total_sold'));
    ?>
    
    new Chart(productsCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($productNames); ?>,
            datasets: [{
                data: <?php echo json_encode($productSales); ?>,
                backgroundColor: [
                    '#8B4513',
                    '#D2B48C',
                    '#F5E6D3',
                    '#5D2F0A',
                    '#A0522D'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

    </div>
</div>
<?php include '../components/layout-end.php'; ?>