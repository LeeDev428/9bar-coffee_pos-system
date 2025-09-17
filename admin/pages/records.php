<?php
// Admin Records & Reports Page
$page_title = 'RECORDS & REPORTS';
include '../components/main-layout.php';

// Get date filters from request
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'sales';

// Sales Analytics
$salesData = $db->fetchAll("
    SELECT DATE(s.sale_date) as sale_date,
           COUNT(*) as transaction_count,
           SUM(s.total_amount) as daily_total,
           AVG(s.total_amount) as avg_transaction
    FROM sales s
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY DATE(s.sale_date)
    ORDER BY DATE(s.sale_date)
", [$startDate, $endDate]);

// Top Products
$topProducts = $db->fetchAll("
    SELECT p.product_name, 
           SUM(si.quantity) as total_quantity,
           SUM(si.total_price) as total_revenue,
           COUNT(DISTINCT s.sale_id) as transaction_count
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY si.product_id, p.product_name
    ORDER BY total_revenue DESC
    LIMIT 10
", [$startDate, $endDate]);

// Category Performance
$categoryPerformance = $db->fetchAll("
    SELECT c.category_name,
           SUM(si.quantity) as total_quantity,
           SUM(si.total_price) as total_revenue,
           AVG(si.unit_price) as avg_price
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY c.category_id, c.category_name
    ORDER BY total_revenue DESC
", [$startDate, $endDate]);

// Payment Methods
$paymentMethods = $db->fetchAll("
    SELECT payment_method,
           COUNT(*) as transaction_count,
           SUM(total_amount) as total_amount
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total_amount DESC
", [$startDate, $endDate]);

// Staff Performance
$staffPerformance = $db->fetchAll("
    SELECT u.username,
           COUNT(s.sale_id) as transaction_count,
           SUM(s.total_amount) as total_sales,
           AVG(s.total_amount) as avg_transaction
    FROM sales s
    JOIN users u ON s.user_id = u.user_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY s.user_id, u.username
    ORDER BY total_sales DESC
", [$startDate, $endDate]);

// Recent Transactions
// Note: some database schemas may not have a `customer_name` column on `sales`.
// Use COALESCE to ensure query does not fail if customer_name is NULL, and
// select only known columns to avoid "Unknown column" errors.
$recentTransactions = $db->fetchAll("
    SELECT s.sale_id,
           s.sale_date,
           s.total_amount,
           s.payment_method,
           u.username,
           NULL AS customer_name,
           (
               SELECT COUNT(*) FROM sale_items si2 WHERE si2.sale_id = s.sale_id
           ) as item_count
    FROM sales s
    JOIN users u ON s.user_id = u.user_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ORDER BY s.sale_date DESC
    LIMIT 50
", [$startDate, $endDate]);

// Summary Statistics
$totalSales = array_sum(array_column($salesData, 'daily_total'));
$totalTransactions = array_sum(array_column($salesData, 'transaction_count'));
$avgTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
?>

<style>
.reports-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.date-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-control {
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 14px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-info { background: #17a2b8; color: white; }

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.summary-value {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.summary-label {
    color: #7f8c8d;
    font-size: 14px;
}

.summary-sales { color: #27ae60; }
.summary-transactions { color: #3498db; }
.summary-avg { color: #f39c12; }
.summary-items { color: #9b59b6; }

.reports-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.main-reports {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.report-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.report-title {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 20px;
}

.side-reports {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.side-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.side-title {
    font-size: 16px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
}

.table-simple {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.table-simple th,
.table-simple td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}

.table-simple th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.table-simple tr:hover {
    background: #f8f9fa;
}

.transactions-table {
    max-height: 400px;
    overflow-y: auto;
}

.transaction-item {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}

.transaction-item:hover {
    background: #f8f9fa;
}

.transaction-info {
    flex: 1;
}

.transaction-amount {
    font-weight: bold;
    color: #27ae60;
}

.transaction-date {
    color: #7f8c8d;
    font-size: 11px;
}

.report-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.tab-button {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s;
}

.tab-button.active {
    color: #3498db;
    border-bottom-color: #3498db;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<div class="reports-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Records & Reports</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Business analytics and transaction history</p>
    </div>
    <div>
        <button class="btn btn-success" onclick="exportReport('sales')">
            <i class="fas fa-download"></i> Export Sales
        </button>
        <button class="btn btn-info" onclick="exportReport('transactions')">
            <i class="fas fa-file-excel"></i> Export Transactions
        </button>
    </div>
</div>

<!-- Date Filters -->
<div class="date-filters">
    <div class="filter-group">
        <label><strong>From:</strong></label>
        <input type="date" id="startDate" class="form-control" value="<?php echo $startDate; ?>">
    </div>
    <div class="filter-group">
        <label><strong>To:</strong></label>
        <input type="date" id="endDate" class="form-control" value="<?php echo $endDate; ?>">
    </div>
    <button class="btn btn-primary" onclick="applyDateFilter()">
        <i class="fas fa-filter"></i> Apply Filter
    </button>
    <button class="btn btn-info" onclick="setQuickFilter('today')">Today</button>
    <button class="btn btn-info" onclick="setQuickFilter('week')">This Week</button>
    <button class="btn btn-info" onclick="setQuickFilter('month')">This Month</button>
</div>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-value summary-sales">₱<?php echo number_format($totalSales, 2); ?></div>
        <div class="summary-label">Total Sales</div>
    </div>
    <div class="summary-card">
        <div class="summary-value summary-transactions"><?php echo number_format($totalTransactions); ?></div>
        <div class="summary-label">Total Transactions</div>
    </div>
    <div class="summary-card">
        <div class="summary-value summary-avg">₱<?php echo number_format($avgTransaction, 2); ?></div>
        <div class="summary-label">Average Transaction</div>
    </div>
    <div class="summary-card">
        <div class="summary-value summary-items"><?php echo array_sum(array_column($topProducts, 'total_quantity')); ?></div>
        <div class="summary-label">Items Sold</div>
    </div>
</div>

<div class="reports-content">
    <div class="main-reports">
        <!-- Sales Chart -->
        <div class="report-card">
            <div class="report-title">Daily Sales Trend</div>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        
        <!-- Detailed Reports Tabs -->
        <div class="report-card">
            <div class="report-tabs">
                <button class="tab-button active" onclick="showTab('products')">Top Products</button>
                <button class="tab-button" onclick="showTab('categories')">Categories</button>
                <button class="tab-button" onclick="showTab('staff')">Staff Performance</button>
                <button class="tab-button" onclick="showTab('payments')">Payment Methods</button>
            </div>
            
            <div id="products-tab" class="tab-content active">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo number_format($product['total_quantity']); ?></td>
                            <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                            <td><?php echo number_format($product['transaction_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="categories-tab" class="tab-content">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Avg Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryPerformance as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo number_format($category['total_quantity']); ?></td>
                            <td>₱<?php echo number_format($category['total_revenue'], 2); ?></td>
                            <td>₱<?php echo number_format($category['avg_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="staff-tab" class="tab-content">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Transactions</th>
                            <th>Total Sales</th>
                            <th>Avg Transaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffPerformance as $staff): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($staff['username']); ?></td>
                            <td><?php echo number_format($staff['transaction_count']); ?></td>
                            <td>₱<?php echo number_format($staff['total_sales'], 2); ?></td>
                            <td>₱<?php echo number_format($staff['avg_transaction'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="payments-tab" class="tab-content">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalPaymentAmount = array_sum(array_column($paymentMethods, 'total_amount'));
                        foreach ($paymentMethods as $payment): 
                        $percentage = $totalPaymentAmount > 0 ? ($payment['total_amount'] / $totalPaymentAmount) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td><?php echo number_format($payment['transaction_count']); ?></td>
                            <td>₱<?php echo number_format($payment['total_amount'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="side-reports">
        <!-- Recent Transactions -->
        <div class="side-card">
            <div class="side-title">Recent Transactions</div>
            <div class="transactions-table">
                <?php foreach (array_slice($recentTransactions, 0, 15) as $transaction): ?>
                <div class="transaction-item">
                    <div class="transaction-info">
                        <div><strong>Sale #<?php echo $transaction['sale_id']; ?></strong></div>
                        <div><?php echo htmlspecialchars($transaction['customer_name'] ?: 'Walk-in'); ?></div>
                        <div class="transaction-date">
                            <?php echo date('M j, H:i', strtotime($transaction['sale_date'])); ?> by <?php echo htmlspecialchars($transaction['username']); ?>
                        </div>
                        <div style="font-size: 11px; color: #6c757d;">
                            <?php echo $transaction['item_count']; ?> items • <?php echo ucfirst($transaction['payment_method']); ?>
                        </div>
                    </div>
                    <div class="transaction-amount">
                        ₱<?php echo number_format($transaction['total_amount'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="side-card">
            <div class="side-title">Quick Statistics</div>
            <div style="font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Highest Sale:</span>
                    <strong>₱<?php echo number_format(max(array_column($recentTransactions, 'total_amount')), 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Lowest Sale:</span>
                    <strong>₱<?php echo number_format(min(array_column($recentTransactions, 'total_amount')), 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Most Popular Product:</span>
                    <strong><?php echo isset($topProducts[0]) ? htmlspecialchars($topProducts[0]['product_name']) : 'N/A'; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Top Category:</span>
                    <strong><?php echo isset($categoryPerformance[0]) ? htmlspecialchars($categoryPerformance[0]['category_name']) : 'N/A'; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($salesData, 'sale_date')); ?>,
        datasets: [{
            label: 'Daily Sales (₱)',
            data: <?php echo json_encode(array_column($salesData, 'daily_total')); ?>,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Transactions',
            data: <?php echo json_encode(array_column($salesData, 'transaction_count')); ?>,
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231, 76, 60, 0.1)',
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales Amount (₱)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Number of Transactions'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

function showTab(tabName) {
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to selected tab
    document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
    document.getElementById(`${tabName}-tab`).classList.add('active');
}

function applyDateFilter() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (startDate && endDate) {
        window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
    } else {
        alert('Please select both start and end dates.');
    }
}

function setQuickFilter(period) {
    const endDate = new Date().toISOString().split('T')[0];
    let startDate;
    
    switch(period) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            startDate = weekAgo.toISOString().split('T')[0];
            break;
        case 'month':
            const monthAgo = new Date();
            monthAgo.setMonth(monthAgo.getMonth() - 1);
            startDate = monthAgo.toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('startDate').value = startDate;
    document.getElementById('endDate').value = endDate;
    applyDateFilter();
}

function exportReport(type) {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (type === 'sales') {
        // Export sales summary
        const data = [
            ['Date', 'Transactions', 'Total Sales', 'Average Transaction']
        ];
        
        <?php foreach ($salesData as $sale): ?>
        data.push([
            '<?php echo $sale['sale_date']; ?>',
            '<?php echo $sale['transaction_count']; ?>',
            '<?php echo $sale['daily_total']; ?>',
            '<?php echo number_format($sale['avg_transaction'], 2); ?>'
        ]);
        <?php endforeach; ?>
        
        downloadCSV(data, `sales_report_${startDate}_to_${endDate}.csv`);
    } else if (type === 'transactions') {
        // Export transaction details
        const data = [
            ['Sale ID', 'Date', 'Staff', 'Customer', 'Items', 'Payment', 'Amount']
        ];
        
        <?php foreach ($recentTransactions as $transaction): ?>
        data.push([
            '<?php echo $transaction['sale_id']; ?>',
            '<?php echo date('Y-m-d H:i:s', strtotime($transaction['sale_date'])); ?>',
            '<?php echo htmlspecialchars($transaction['username']); ?>',
            '<?php echo htmlspecialchars($transaction['customer_name'] ?: 'Walk-in'); ?>',
            '<?php echo $transaction['item_count']; ?>',
            '<?php echo ucfirst($transaction['payment_method']); ?>',
            '<?php echo $transaction['total_amount']; ?>'
        ]);
        <?php endforeach; ?>
        
        downloadCSV(data, `transactions_report_${startDate}_to_${endDate}.csv`);
    }
}

function downloadCSV(data, filename) {
    const csv = data.map(row => 
        row.map(cell => `"${cell}"`).join(',')
    ).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include '../components/layout-end.php'; ?>