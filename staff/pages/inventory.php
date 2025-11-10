<?php
// Staff Inventory View Page
$page_title = 'INVENTORY';
include '../components/main-layout.php';

// Get current user from component system
$user = $currentUser;

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
        ORDER BY p.product_name";

$products = $db->fetchAll($sql);

// Count statistics
$totalProducts = count($products);
$needReorder = count(array_filter($products, function($p) { return $p['stock_status'] == 'REORDER'; }));
$lowStock = count(array_filter($products, function($p) { return $p['stock_status'] == 'LOW'; }));

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");
?>

<style>
.inventory-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    border-left: 4px solid #3E363F;
}

.stat-box.reorder {
    border-left-color: #f39c12;
}

.stat-box.low-stock {
    border-left-color: #e74c3c;
}

.stat-number {
    font-size: 2.5em;
    font-weight: 700;
    color: #3E363F;
    margin: 0;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.search-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    width: 250px;
}

.inventory-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table-header {
    background: #3E363F;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
    font-size: 16px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #3E363F;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
}

.table tr:hover {
    background-color: #f8f9fa;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-ok {
    background: #d4edda;
    color: #155724;
}

.status-reorder {
    background: #fff3cd;
    color: #856404;
}

.status-low {
    background: #f8d7da;
    color: #721c24;
}

.stock-level {
    font-weight: 600;
}

.stock-level.low {
    color: #e74c3c;
}

.stock-level.reorder {
    color: #f39c12;
}

.stock-level.ok {
    color: #27ae60;
}
</style>

<!-- Inventory Statistics -->
<div class="inventory-stats">
    <div class="stat-box">
        <div class="stat-number"><?php echo $totalProducts; ?></div>
        <div class="stat-label">Total Products</div>
    </div>
    <div class="stat-box reorder">
        <div class="stat-number" style="color: #f39c12;"><?php echo $needReorder; ?></div>
        <div class="stat-label">Need Reorder</div>
    </div>
    <div class="stat-box low-stock">
        <div class="stat-number" style="color: #e74c3c;"><?php echo $lowStock; ?></div>
        <div class="stat-label">Low Stock</div>
    </div>
</div>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Category:</label>
        <select class="filter-select" id="categoryFilter">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['category_id']; ?>">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Stock Status:</label>
        <select class="filter-select" id="statusFilter">
            <option value="">All Status</option>
            <option value="OK">OK</option>
            <option value="REORDER">Need Reorder</option>
            <option value="LOW">Low Stock</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Search:</label>
        <input type="text" class="search-input" placeholder="Search products..." id="searchInput">
    </div>
</div>

<!-- Inventory Table -->
<div class="inventory-table">
    <div class="table-header">
        Product Inventory List
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Barcode</th>
                <th>Price</th>
                <th>Current Stock</th>
                <th>Min Stock</th>
                <th>Stock Level</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="inventoryTableBody">
            <?php foreach ($products as $product): ?>
                <tr data-category="<?php echo $product['category_name']; ?>" 
                    data-status="<?php echo $product['stock_status']; ?>"
                    data-product="<?php echo strtolower($product['product_name']); ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                    <td><?php echo htmlspecialchars($product['barcode'] ?: 'N/A'); ?></td>
                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                    <td>
                        <span class="stock-level <?php echo strtolower($product['stock_status']); ?>">
                            <?php echo $product['current_stock']; ?>
                        </span>
                    </td>
                    <td><?php echo $product['minimum_stock']; ?></td>
                    <td>
                        <?php if ($product['stock_status'] == 'LOW'): ?>
                            <span style="color: #e74c3c; font-weight: 600;">
                                <?php echo $product['minimum_stock'] - $product['current_stock']; ?> short
                            </span>
                        <?php elseif ($product['stock_status'] == 'REORDER'): ?>
                            <span style="color: #f39c12; font-weight: 600;">
                                Reorder Soon
                            </span>
                        <?php else: ?>
                            <span style="color: #27ae60; font-weight: 600;">
                                Good
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($product['stock_status']); ?>">
                            <?php echo $product['stock_status']; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('inventoryTableBody');
    const rows = tableBody.querySelectorAll('tr');

    function filterTable() {
        const categoryValue = categoryFilter.value;
        const statusValue = statusFilter.value;
        const searchValue = searchInput.value.toLowerCase();

        rows.forEach(row => {
            const category = row.dataset.category;
            const status = row.dataset.status;
            const product = row.dataset.product;

            const categoryMatch = !categoryValue || row.querySelector('td:nth-child(2)').textContent === category;
            const statusMatch = !statusValue || status === statusValue;
            const searchMatch = !searchValue || product.includes(searchValue);

            if (categoryMatch && statusMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    categoryFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);
    searchInput.addEventListener('input', filterTable);
});
</script>

<?php include '../components/layout-end.php'; ?>