<?php
// Staff Inventory View Page
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Require login (staff or admin)
$auth->requireLogin();
$user = $auth->getCurrentUser();

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

$products = $db->fetchAll($sql);

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Count stock levels
$totalProducts = count($products);
$lowStockCount = count(array_filter($products, function($p) { return $p['stock_status'] === 'LOW'; }));
$reorderCount = count(array_filter($products, function($p) { return $p['stock_status'] === 'REORDER'; }));

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: ../../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Staff - 9BARS COFFEE POS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .inventory-container {
            display: flex;
            min-height: 100vh;
            background-color: #ecf0f1;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }

        .user-info h4 {
            margin: 0;
            font-size: 1rem;
        }

        .user-info p {
            margin: 0;
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .nav-item:hover,
        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-right: 3px solid #e67e22;
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: white;
            padding: 20px 30px;
            border-bottom: 1px solid #bdc3c7;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 0;
        }

        .header-subtitle {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 5px 0 0 0;
        }

        .content {
            flex: 1;
            padding: 30px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .low-stock .stat-value {
            color: #e74c3c;
        }

        .reorder .stat-value {
            color: #f39c12;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: flex;
            gap: 15px;
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
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            background: white;
        }

        .search-input {
            padding: 8px 12px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            width: 250px;
        }

        .inventory-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-header {
            background: #34495e;
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
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

        .stock-bar {
            width: 100px;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .stock-fill {
            height: 100%;
            border-radius: 4px;
        }

        .stock-ok .stock-fill {
            background: #27ae60;
        }

        .stock-reorder .stock-fill {
            background: #f39c12;
        }

        .stock-low .stock-fill {
            background: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="inventory-container">
        <!-- Sidebar -->
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
                <a href="../../dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>DASHBOARD</span>
                </a>
                
                <a href="pos.php" class="nav-item">
                    <i class="fas fa-cash-register"></i>
                    <span>POINT OF SALE</span>
                </a>
                
                <a href="inventory.php" class="nav-item active">
                    <i class="fas fa-warehouse"></i>
                    <span>INVENTORY</span>
                </a>
            </nav>
            
            <div style="padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
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
                <h1 class="header-title">INVENTORY</h1>
                <p class="header-subtitle">
                    <?php echo date('l, M d, Y h:i:s A'); ?> | Stock Levels & Product Information
                </p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <!-- Stats Row -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalProducts; ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card reorder">
                        <div class="stat-value"><?php echo $reorderCount; ?></div>
                        <div class="stat-label">Need Reorder</div>
                    </div>
                    <div class="stat-card low-stock">
                        <div class="stat-value"><?php echo $lowStockCount; ?></div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Category:</label>
                            <select class="filter-select" id="categoryFilter" onchange="filterTable()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Stock Status:</label>
                            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                                <option value="">All Status</option>
                                <option value="LOW">Low Stock</option>
                                <option value="REORDER">Need Reorder</option>
                                <option value="OK">Normal</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" class="search-input" id="searchInput" placeholder="Search products..." onkeyup="filterTable()">
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Table -->
                <div class="inventory-table">
                    <div class="table-header">
                        Product Inventory List
                    </div>
                    
                    <table id="inventoryTable">
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
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr data-category="<?php echo htmlspecialchars($product['category_name']); ?>" 
                                data-status="<?php echo $product['stock_status']; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <strong style="font-size: 1.1rem;">
                                        <?php echo number_format($product['current_stock']); ?>
                                    </strong>
                                </td>
                                <td><?php echo number_format($product['minimum_stock']); ?></td>
                                <td>
                                    <div class="stock-bar stock-<?php echo strtolower($product['stock_status']); ?>">
                                        <?php 
                                        $percentage = min(100, ($product['current_stock'] / $product['maximum_stock']) * 100);
                                        ?>
                                        <div class="stock-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                    <small><?php echo number_format($percentage, 1); ?>%</small>
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
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            
            rows.forEach(row => {
                const category = row.dataset.category.toLowerCase();
                const status = row.dataset.status;
                const name = row.dataset.name;
                
                let showRow = true;
                
                // Category filter
                if (categoryFilter && !category.includes(categoryFilter)) {
                    showRow = false;
                }
                
                // Status filter
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }
                
                // Search filter
                if (searchInput && !name.includes(searchInput)) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }

        // Auto-refresh every 30 seconds for real-time updates
        setInterval(() => {
            // In a real implementation, this would use AJAX to refresh data
            console.log('Auto-refresh inventory data');
        }, 30000);
    </script>
</body>
</html>