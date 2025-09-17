<?php
// Admin Dashboard Page
$page_title = 'ADMIN DASHBOARD';
include '../components/main-layout.php';

// Initialize managers
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get dashboard statistics
$todaysSales = $db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = CURDATE()");
$totalProducts = $db->fetchValue("SELECT COUNT(*) FROM products WHERE status = 'active'");
$lowStockCount = $db->fetchValue("SELECT COUNT(*) FROM inventory WHERE current_stock <= minimum_stock");
$totalCustomers = $db->fetchValue("SELECT COUNT(DISTINCT customer_name) FROM sales WHERE customer_name IS NOT NULL");
?>

<!-- Dashboard Content -->
<div class="row">
    <!-- Stats Cards -->
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Today's Sales</h6>
                        <h3>₱<?php echo number_format($todaysSales, 2); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Products</h6>
                        <h3><?php echo $totalProducts; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box-seam fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Low Stock Items</h6>
                        <h3><?php echo $lowStockCount; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Customers</h6>
                        <h3><?php echo $totalCustomers; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Welcome Section -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body text-center py-5">
                <h2 class="text-primary mb-3">Welcome back, <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Administrator'); ?>!</h2>
                <p class="lead text-muted">Here's what's happening with your coffee shop today.</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Daily Sales</h5>
            </div>
            <div class="card-body">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Top Products</h5>
            </div>
            <div class="card-body">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
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
                            $recentSales = $db->fetchAll("
                                SELECT transaction_number, customer_name, total_amount, sale_date, status 
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
                                        <?php echo ucfirst($sale['status']); ?>
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
                backgroundColor: 'rgba(139, 69, 19, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
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

<?php include '../components/layout-end.php'; ?>