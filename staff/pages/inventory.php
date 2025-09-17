<?php
// Staff Inventory View Page
$page_title = 'INVENTORY';
include '../components/main-layout.php';

// Get all products with inventory
$sql = "SELECT p.product_id, p.product_name, p.price, p.barcode, p.status,
               c.category_name,
               COALESCE(i.current_stock, 0) as current_stock,
               COALESCE(i.minimum_stock, 5) as minimum_stock,
               COALESCE(i.maximum_stock, 100) as maximum_stock,
               COALESCE(i.reorder_level, 10) as reorder_level,
               CASE 
                   WHEN COALESCE(i.current_stock, 0) <= COALESCE(i.minimum_stock, 5) THEN 'LOW'
                   WHEN COALESCE(i.current_stock, 0) <= COALESCE(i.reorder_level, 10) THEN 'REORDER'
                   ELSE 'OK'
               END as stock_status
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE p.status = 'active'
        ORDER BY c.category_name, p.product_name";

$products = $db->fetchAll($sql) ?? [];

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name") ?? [];

// Count stock levels
$totalProducts = count($products);
$lowStockCount = count(array_filter($products, function($p) { return $p['stock_status'] === 'LOW'; }));
$reorderCount = count(array_filter($products, function($p) { return $p['stock_status'] === 'REORDER'; }));
?>

<!-- Inventory Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-boxes"></i>
        </div>
        <div class="stat-title">Total Products</div>
        <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon text-warning">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-title">Reorder Soon</div>
        <div class="stat-value"><?php echo number_format($reorderCount); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon text-danger">
            <i class="bi bi-exclamation-circle"></i>
        </div>
        <div class="stat-title">Low Stock</div>
        <div class="stat-value"><?php echo number_format($lowStockCount); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon text-success">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-title">Categories</div>
        <div class="stat-value"><?php echo number_format(count($categories)); ?></div>
    </div>
</div>

<!-- Filters and Search -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="searchProduct" class="form-control" placeholder="Search products...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="categoryFilter" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select id="stockFilter" class="form-select">
            <option value="">All Stock Levels</option>
            <option value="OK">Good Stock</option>
            <option value="REORDER">Reorder Soon</option>
            <option value="LOW">Low Stock</option>
        </select>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Product Inventory</h5>
        <span class="badge bg-primary"><?php echo number_format($totalProducts); ?> Products</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                        <th>Barcode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <div class="mt-2">No products found in inventory</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr data-category="<?php echo htmlspecialchars($product['category_name']); ?>" 
                            data-stock-status="<?php echo $product['stock_status']; ?>">
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            </td>
                            <td class="fw-bold text-success">₱<?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <span class="fw-bold <?php 
                                    echo $product['stock_status'] === 'LOW' ? 'text-danger' : 
                                        ($product['stock_status'] === 'REORDER' ? 'text-warning' : 'text-success'); 
                                ?>">
                                    <?php echo number_format($product['current_stock']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($product['minimum_stock']); ?></td>
                            <td>
                                <?php if ($product['stock_status'] === 'LOW'): ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-exclamation-circle"></i> Low Stock
                                    </span>
                                <?php elseif ($product['stock_status'] === 'REORDER'): ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Reorder
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Good
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($product['barcode'])): ?>
                                    <code><?php echo htmlspecialchars($product['barcode']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Back to Dashboard -->
<div class="row mt-4">
    <div class="col-12">
        <div class="text-center">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Filtering JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchProduct');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const tableRows = document.querySelectorAll('#inventoryTable tbody tr[data-category]');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
        const selectedStock = stockFilter.value;

        tableRows.forEach(row => {
            const productName = row.cells[0].textContent.toLowerCase();
            const category = row.getAttribute('data-category');
            const stockStatus = row.getAttribute('data-stock-status');

            const matchesSearch = productName.includes(searchTerm);
            const matchesCategory = !selectedCategory || category === selectedCategory;
            const matchesStock = !selectedStock || stockStatus === selectedStock;

            if (matchesSearch && matchesCategory && matchesStock) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Update visible count
        const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
        document.querySelector('.card-header .badge').textContent = visibleRows.length + ' Products';
    }

    searchInput.addEventListener('keyup', filterTable);
    categoryFilter.addEventListener('change', filterTable);
    stockFilter.addEventListener('change', filterTable);
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</div> <!-- End content -->
</div> <!-- End main-content -->
</body>
</html>