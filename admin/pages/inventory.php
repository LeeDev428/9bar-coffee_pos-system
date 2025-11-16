<?php
// Admin Inventory Management Page
$page_title = 'INVENTORY MANAGEMENT';
include '../components/main-layout.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'stock_adjustment':
                try {
                    $productId = intval($_POST['product_id']);
                    $adjustmentType = $_POST['adjustment_type'];
                    $quantity = intval($_POST['quantity']);
                    $reason = sanitizeInput($_POST['reason']);
                    
                    // Get current stock
                    $currentStock = $db->fetchValue("SELECT current_stock FROM inventory WHERE product_id = ?", [$productId]);
                    
                    if ($adjustmentType === 'add') {
                        $newStock = $currentStock + $quantity;
                    } elseif ($adjustmentType === 'subtract') {
                        $newStock = max(0, $currentStock - $quantity);
                    } else {
                        $newStock = $quantity; // Set absolute value
                    }
                    
                    // Update inventory
                    $db->query("UPDATE inventory SET current_stock = ?, last_updated = NOW() WHERE product_id = ?", [$newStock, $productId]);
                    
                    // Log adjustment
                    $db->query("INSERT INTO stock_adjustments (product_id, adjustment_type, quantity_before, quantity_after, adjustment_quantity, reason, adjusted_by, adjustment_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", [
                        $productId, $adjustmentType, $currentStock, $newStock, $quantity, $reason, $_SESSION['user_id']
                    ]);
                    
                    showAlert("Stock adjustment completed successfully!", 'success');
                } catch (Exception $e) {
                    showAlert('Error adjusting stock: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'bulk_reorder':
                try {
                    $selectedProducts = $_POST['selected_products'] ?? [];
                    $reorderQuantities = $_POST['reorder_quantities'] ?? [];
                    
                    foreach ($selectedProducts as $productId) {
                        $productId = intval($productId);
                        $reorderQty = intval($reorderQuantities[$productId] ?? 0);
                        
                        if ($reorderQty > 0) {
                            // Get current stock
                            $currentStock = $db->fetchValue("SELECT current_stock FROM inventory WHERE product_id = ?", [$productId]);
                            $newStock = $currentStock + $reorderQty;
                            
                            // Update inventory
                            $db->query("UPDATE inventory SET current_stock = ?, last_updated = NOW() WHERE product_id = ?", [$newStock, $productId]);
                            
                            // Log reorder
                            $db->query("INSERT INTO stock_adjustments (product_id, adjustment_type, quantity_before, quantity_after, adjustment_quantity, reason, adjusted_by, adjustment_date) VALUES (?, 'add', ?, ?, ?, 'Bulk Reorder', ?, NOW())", [
                                $productId, $currentStock, $newStock, $reorderQty, $_SESSION['user_id']
                            ]);
                        }
                    }
                    
                    showAlert("Bulk reorder completed successfully!", 'success');
                } catch (Exception $e) {
                    showAlert('Error processing bulk reorder: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_stock_levels':
                try {
                    $productId = intval($_POST['product_id']);
                    $minStock = intval($_POST['minimum_stock']);
                    $maxStock = intval($_POST['maximum_stock']);
                    $reorderLevel = intval($_POST['reorder_level']);
                    
                    $db->query("UPDATE inventory SET minimum_stock = ?, maximum_stock = ?, reorder_level = ? WHERE product_id = ?", [
                        $minStock, $maxStock, $reorderLevel, $productId
                    ]);
                    
                    showAlert("Stock levels updated successfully!", 'success');
                } catch (Exception $e) {
                    showAlert('Error updating stock levels: ' . $e->getMessage(), 'error');
                }
                break;
        }
    }
}

// Get inventory data
$inventoryData = $db->fetchAll("
    SELECT p.product_id, p.product_name, c.category_name, p.price, p.cost_price,
           i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level, i.last_updated,
           CASE 
               WHEN i.current_stock <= i.minimum_stock THEN 'low'
               WHEN i.current_stock <= i.reorder_level THEN 'reorder' 
               ELSE 'normal'
           END as stock_status
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN inventory i ON p.product_id = i.product_id
    WHERE p.status = 'active'
    ORDER BY 
        CASE 
            WHEN i.current_stock <= i.minimum_stock THEN 1
            WHEN i.current_stock <= i.reorder_level THEN 2 
            ELSE 3
        END,
        c.category_name, p.product_name
");

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Get recent stock adjustments
$recentAdjustments = $db->fetchAll("
    SELECT sa.*, p.product_name, u.username
    FROM stock_adjustments sa
    JOIN products p ON sa.product_id = p.product_id
    JOIN users u ON sa.adjusted_by = u.user_id
    ORDER BY sa.adjustment_date DESC
    LIMIT 10
");

// Calculate inventory stats
$totalProducts = count($inventoryData);
$lowStockCount = count(array_filter($inventoryData, function($item) { return $item['stock_status'] === 'low'; }));
$reorderCount = count(array_filter($inventoryData, function($item) { return $item['stock_status'] === 'reorder'; }));
$totalValue = array_sum(array_map(function($item) { return $item['current_stock'] * $item['cost_price']; }, $inventoryData));
?>

<style>
.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.inventory-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 30px;
    background: white;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tab-item {
    flex: 1;
    padding: 12px 20px;
    text-align: center;
    text-decoration: none;
    color: #6c757d;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 2px solid transparent;
}

.tab-item:hover {
    background: #f8f9fa;
    color: #495057;
}

.tab-item.active {
    background: #3498db;
    color: white;
    border-color: #2980b9;
}

.tab-item i {
    font-size: 16px;
}

.inventory-stats {
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
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
}

.stat-low { color: #e74c3c; }
.stat-warning { color: #f39c12; }
.stat-success { color: #27ae60; }
.stat-info { color: #3498db; }

.inventory-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.main-inventory {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.inventory-tools {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    height: fit-content;
}

.tools-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ecf0f1;
}

.tools-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.section-title {
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 16px;
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
    font-size: 13px;
    transition: all 0.3s;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-info { background: #17a2b8; color: white; }

.btn:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-sm { padding: 5px 10px; font-size: 11px; }

.filters-bar {
    display: flex;
    gap: 15px;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.form-control {
    padding: 6px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
}

.table-container {
    max-height: 600px;
    overflow-y: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #34495e;
    color: white;
    position: sticky;
    top: 0;
    z-index: 1;
}

th, td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
    font-size: 13px;
}

tbody tr:hover {
    background: #f8f9fa;
}

.stock-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-low { background: #f8d7da; color: #721c24; }
.status-reorder { background: #fff3cd; color: #856404; }
.status-normal { background: #d4edda; color: #155724; }

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    background: #34495e;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.close {
    color: white;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
    font-size: 13px;
}

.adjustment-options {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.adjustment-option {
    flex: 1;
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 4px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.adjustment-option.active {
    border-color: #3498db;
    background: #e3f2fd;
}

.recent-activities {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 12px;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-date {
    color: #7f8c8d;
    font-size: 11px;
}
</style>

<div class="inventory-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Inventory Management</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Monitor and manage your stock levels</p>
    </div>
</div>

<!-- Inventory Statistics -->
<div class="inventory-stats">
    <div class="stat-card stat-card--info">
        <div class="stat-value"><?php echo $totalProducts; ?></div>
        <div class="stat-label">Total Products</div>
    </div>
    <div class="stat-card stat-card--low">
        <div class="stat-value"><?php echo $lowStockCount; ?></div>
        <div class="stat-label">Low Stock Items</div>
    </div>
    <div class="stat-card stat-card--warning">
        <div class="stat-value"><?php echo $reorderCount; ?></div>
        <div class="stat-label">Need Reorder</div>
    </div>
    <div class="stat-card stat-card--success">
        <div class="stat-value">₱<?php echo number_format($totalValue, 2); ?></div>
        <div class="stat-label">Total Inventory Value</div>
    </div>
</div>

<div class="inventory-content">
    <!-- Main Inventory Table -->
    <div class="main-inventory">
        <div class="filters-bar">
            <input type="text" class="form-control" id="searchInput" placeholder="Search products..." onkeyup="filterInventory()">
            
            <select class="form-control" id="categoryFilter" onchange="filterInventory()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="form-control" id="stockStatusFilter" onchange="filterInventory()">
                <option value="">All Status</option>
                <option value="low">Low Stock</option>
                <option value="reorder">Need Reorder</option>
                <option value="normal">Normal</option>
            </select>
        </div>
        
        <div class="table-container">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min/Max</th>
                        <th>Reorder Level</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryData as $item): ?>
                    <tr data-product-id="<?php echo $item['product_id']; ?>"
                        data-category="<?php echo $item['category_name']; ?>"
                        data-status="<?php echo $item['stock_status']; ?>"
                        data-name="<?php echo strtolower($item['product_name']); ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td>
                            <strong style="font-size: 16px;"><?php echo $item['current_stock']; ?></strong>
                        </td>
                        <td>
                            <?php echo $item['minimum_stock']; ?> / <?php echo $item['maximum_stock']; ?>
                        </td>
                        <td><?php echo $item['reorder_level']; ?></td>
                        <td>₱<?php echo number_format($item['current_stock'] * $item['cost_price'], 2); ?></td>
                        <td>
                            <span class="stock-status status-<?php echo $item['stock_status']; ?>">
                                <?php echo strtoupper($item['stock_status']); ?>
                            </span>
                        </td>
                        <td><?php echo $item['last_updated'] ? date('M j, Y', strtotime($item['last_updated'])) : 'N/A'; ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="openStockAdjustment(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="openStockLevels(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                <i class="fas fa-cog"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tools Panel -->
    <div class="inventory-tools">
        <div class="tools-section">
            <div class="section-title">Quick Actions</div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-success" onclick="showBulkReorderModal()">
                    <i class="fas fa-shopping-cart"></i> Bulk Reorder Low Stock
                </button>
                <button class="btn btn-info" onclick="exportInventory()">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <button class="btn btn-warning" onclick="showStockAlerts()">
                    <i class="fas fa-exclamation-triangle"></i> Stock Alerts
                </button>
            </div>
        </div>
        
        <div class="tools-section">
            <div class="section-title">Recent Activities</div>
            <div class="recent-activities">
                <?php foreach ($recentAdjustments as $adjustment): ?>
                <div class="activity-item">
                    <div><strong><?php echo htmlspecialchars($adjustment['product_name']); ?></strong></div>
                    <div>
                        <?php echo ucfirst($adjustment['adjustment_type']); ?> 
                        <?php echo $adjustment['adjustment_quantity']; ?> units
                    </div>
                    <div class="activity-date">
                        by <?php echo htmlspecialchars($adjustment['username']); ?> - 
                        <?php echo date('M j, H:i', strtotime($adjustment['adjustment_date'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stockAdjustmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Stock Adjustment</h3>
            <span class="close" onclick="closeModal('stockAdjustmentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="stock_adjustment">
                <input type="hidden" name="product_id" id="adjust_product_id">
                
                <div class="form-group">
                    <div class="form-label">Product: <span id="adjust_product_name" style="font-weight: bold;"></span></div>
                    <div style="color: #7f8c8d; font-size: 12px;">Current Stock: <span id="adjust_current_stock"></span></div>
                </div>
                
                <div class="form-group">
                    <div class="form-label">Adjustment Type</div>
                    <div class="adjustment-options">
                        <div class="adjustment-option" onclick="selectAdjustmentType('add')" data-type="add">
                            <i class="fas fa-plus-circle"></i><br>Add Stock
                        </div>
                        <div class="adjustment-option" onclick="selectAdjustmentType('subtract')" data-type="subtract">
                            <i class="fas fa-minus-circle"></i><br>Remove Stock
                        </div>
                        <div class="adjustment-option" onclick="selectAdjustmentType('set')" data-type="set">
                            <i class="fas fa-edit"></i><br>Set Stock
                        </div>
                    </div>
                    <input type="hidden" name="adjustment_type" id="adjustment_type" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Reason for adjustment..." required></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('stockAdjustmentModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Levels Modal -->
<div id="stockLevelsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Stock Levels</h3>
            <span class="close" onclick="closeModal('stockLevelsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_stock_levels">
                <input type="hidden" name="product_id" id="levels_product_id">
                
                <div class="form-group">
                    <div class="form-label">Product: <span id="levels_product_name" style="font-weight: bold;"></span></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Minimum Stock Level</label>
                    <input type="number" name="minimum_stock" id="levels_minimum_stock" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Maximum Stock Level</label>
                    <input type="number" name="maximum_stock" id="levels_maximum_stock" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reorder Level</label>
                    <input type="number" name="reorder_level" id="levels_reorder_level" class="form-control" min="0" required>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('stockLevelsModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Levels
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reorder Modal -->
<div id="bulkReorderModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Bulk Reorder - Low Stock Items</h3>
            <span class="close" onclick="closeModal('bulkReorderModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="bulkReorderForm">
                <input type="hidden" name="action" value="bulk_reorder">
                
                <div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #f39c12;">
                    <strong>Note:</strong> Select items to reorder and enter quantities. Quantities will be added to current stock.
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 1;">
                            <tr>
                                <th style="padding: 10px; border-bottom: 2px solid #dee2e6;">
                                    <input type="checkbox" id="selectAll" onchange="toggleAllProducts(this)">
                                </th>
                                <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: left;">Product</th>
                                <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: center;">Current</th>
                                <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: center;">Min</th>
                                <th style="padding: 10px; border-bottom: 2px solid #dee2e6; text-align: center;">Reorder Qty</th>
                            </tr>
                        </thead>
                        <tbody id="bulkReorderTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #7f8c8d; font-size: 13px;">
                        Selected: <strong id="selectedCount">0</strong> items
                    </div>
                    <div>
                        <button type="button" class="btn" onclick="closeModal('bulkReorderModal')">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-shopping-cart"></i> Process Reorder
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openStockAdjustment(product) {
    document.getElementById('adjust_product_id').value = product.product_id;
    document.getElementById('adjust_product_name').textContent = product.product_name;
    document.getElementById('adjust_current_stock').textContent = product.current_stock;
    
    // Reset adjustment type selection
    document.querySelectorAll('.adjustment-option').forEach(opt => opt.classList.remove('active'));
    document.getElementById('adjustment_type').value = '';
    
    document.getElementById('stockAdjustmentModal').style.display = 'block';
}

function openStockLevels(product) {
    document.getElementById('levels_product_id').value = product.product_id;
    document.getElementById('levels_product_name').textContent = product.product_name;
    document.getElementById('levels_minimum_stock').value = product.minimum_stock;
    document.getElementById('levels_maximum_stock').value = product.maximum_stock;
    document.getElementById('levels_reorder_level').value = product.reorder_level;
    
    document.getElementById('stockLevelsModal').style.display = 'block';
}

function selectAdjustmentType(type) {
    document.querySelectorAll('.adjustment-option').forEach(opt => opt.classList.remove('active'));
    document.querySelector(`[data-type="${type}"]`).classList.add('active');
    document.getElementById('adjustment_type').value = type;
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function filterInventory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
    const statusFilter = document.getElementById('stockStatusFilter').value;
    
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const category = row.dataset.category.toLowerCase();
        const status = row.dataset.status;
        
        let show = true;
        
        if (searchTerm && !name.includes(searchTerm) && !category.includes(searchTerm)) {
            show = false;
        }
        
        if (categoryFilter && category !== categoryFilter) {
            show = false;
        }
        
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function showBulkReorderModal() {
    // Get products that need reordering from the table
    const lowStockItems = document.querySelectorAll('tr[data-status="low"], tr[data-status="reorder"]');
    
    if (lowStockItems.length === 0) {
        alert('No items need reordering at this time.');
        return;
    }
    
    // Populate the bulk reorder table
    const tbody = document.getElementById('bulkReorderTableBody');
    tbody.innerHTML = '';
    
    lowStockItems.forEach(row => {
        const productId = row.dataset.productId;
        const productName = row.querySelector('td:nth-child(1)').textContent.trim();
        const currentStock = row.querySelector('td:nth-child(3)').textContent.trim();
        const minMaxText = row.querySelector('td:nth-child(4)').textContent.trim();
        const minStock = minMaxText.split('/')[0].trim();
        const status = row.dataset.status;
        
        // Calculate suggested reorder quantity
        const suggestedQty = Math.max(parseInt(minStock) - parseInt(currentStock), 5);
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                <input type="checkbox" name="selected_products[]" value="${productId}" 
                       class="product-checkbox" onchange="updateSelectedCount()">
            </td>
            <td style="padding: 8px; border-bottom: 1px solid #f0f0f0;">
                <strong>${productName}</strong>
                <span class="stock-status status-${status}" style="margin-left: 8px; font-size: 10px;">
                    ${status.toUpperCase()}
                </span>
            </td>
            <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                <strong>${currentStock}</strong>
            </td>
            <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                ${minStock}
            </td>
            <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                <input type="number" name="reorder_quantities[${productId}]" 
                       value="${suggestedQty}" min="1" 
                       style="width: 80px; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Reset select all checkbox
    document.getElementById('selectAll').checked = false;
    updateSelectedCount();
    
    // Show modal
    document.getElementById('bulkReorderModal').style.display = 'block';
}

function toggleAllProducts(checkbox) {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.product-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

function exportInventory() {
    // Create CSV export
    const data = [];
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    // Add headers
    data.push(['Product', 'Category', 'Current Stock', 'Min Stock', 'Max Stock', 'Reorder Level', 'Status']);
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            data.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim(),
                cells[6].textContent.trim()
            ]);
        }
    });
    
    // Convert to CSV
    const csv = data.map(row => row.join(',')).join('\n');
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inventory_report_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function showStockAlerts() {
    const lowStock = document.querySelectorAll('tr[data-status="low"]').length;
    const needReorder = document.querySelectorAll('tr[data-status="reorder"]').length;
    
    alert(`Stock Alerts:\n• ${lowStock} items with low stock\n• ${needReorder} items need reordering`);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['stockAdjustmentModal', 'stockLevelsModal', 'bulkReorderModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Validate bulk reorder form
document.getElementById('bulkReorderForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.product-checkbox:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('Please select at least one product to reorder.');
        return false;
    }
});
</script>

<?php include '../components/layout-end.php'; ?>