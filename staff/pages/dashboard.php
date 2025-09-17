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