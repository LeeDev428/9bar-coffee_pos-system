<?php
// Admin Inventory Management Page
$page_title = 'INVENTORY MANAGEMENT';
// Items panel removed; no need for $openItemsPanel flag
include '../components/main-layout.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // Items management removed: add_item handling has been removed since the Items UI was deleted
            // update_item_stock handler removed (Stocks now shown as status only)
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
<?php
// Fetch items list (inventory_items) safely
try {
    $itemsList = $db->fetchAll("SELECT ii.*, u.username as added_by_name FROM inventory_items ii LEFT JOIN users u ON ii.added_by = u.user_id ORDER BY ii.date_added DESC, ii.time_added DESC");
} catch (Exception $e) {
    $itemsList = [];
}

// Items stats
$totalItems = count($itemsList);
$lowItemCount = 0;
foreach ($itemsList as $it) {
    if (isset($it['quantity']) && intval($it['quantity']) <= 0) $lowItemCount++;
}
?>

<style>
.inventory-header {
    display: block; /* ensure header spans full width */
    width: 100%;
    box-sizing: border-box;
    margin-bottom: 18px;
    padding: 0 8px; /* small horizontal padding to align with page content */
}

.inventory-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)); /* evenly distribute 4 columns */
    gap: 18px;
    width: 100%;
    margin: 0;
}

@media (max-width: 900px) {
    .inventory-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .inventory-stats { grid-template-columns: 1fr; }
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    text-align: center;
    min-height: 96px; /* make cards more prominent */
    display: flex;
    flex-direction: column;
    justify-content: center;
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
/* Ensure small buttons have enough space to show labels */
.btn-sm { padding: 6px 10px; font-size: 12px; min-width: 64px; white-space: nowrap; }

.btn .btn-text { margin-left: 6px; display: inline-block; }

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

/* inline-stock-form removed: Stocks is now a read-only Status + Quantity column */

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

</style>

<div class="inventory-header">
    <div class="inventory-stats">
    <div class="stat-card">
        <div class="stat-value stat-info"><?php echo $totalProducts; ?></div>
        <div class="stat-label">Total Products</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-low"><?php echo $lowStockCount; ?></div>
        <div class="stat-label">Low Stock Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-warning"><?php echo $reorderCount; ?></div>
        <div class="stat-label">Need Reorder</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-success">₱<?php echo number_format($totalValue, 2); ?></div>
        <div class="stat-label">Total Inventory Value</div>
    </div>
    </div>
</div>

<!-- Items Stats (hidden by default, moved to right column for alignment) -->
<!-- moved into .inventory-tools below to align with product Low Stock card -->

<div class="inventory-content">
    <!-- Main Inventory Table -->
    <div class="main-inventory">
        <div class="filters-bar">
            <input type="text" class="form-control" id="searchInput" placeholder="Search products..." onkeyup="filterInventory()">
            
            <select class="form-control" id="categoryFilter" onchange="filterInventory()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['category_id']; ?>">
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
                            <button class="btn btn-primary btn-sm" title="Adjust Stock" onclick="openStockAdjustment(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                <i class="fas fa-edit"></i>
                                <span class="btn-text">Adjust</span>
                            </button>
                            <button class="btn btn-warning btn-sm" title="Update Levels" onclick="openStockLevels(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                <i class="fas fa-cog"></i>
                                <span class="btn-text">Levels</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
        <!-- Items panel removed per request -->
    <!-- Tools Panel -->
    <div class="inventory-tools">
        <!-- Quick Actions removed per request; only Items Summary info card will remain below -->
        
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
        
        <!-- Items summary removed -->
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
    const categoryFilter = document.getElementById('categoryFilter').value;
    const statusFilter = document.getElementById('stockStatusFilter').value;
    
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const category = row.dataset.category;
        const status = row.dataset.status;
        
        let show = true;
        
        if (searchTerm && !name.includes(searchTerm) && !category.toLowerCase().includes(searchTerm)) {
            show = false;
        }
        
        if (categoryFilter && !category.includes(categoryFilter)) {
            show = false;
        }
        
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function showBulkReorderModal() {
    // Show products that need reordering
    const lowStockItems = document.querySelectorAll('tr[data-status="low"], tr[data-status="reorder"]');
    
    if (lowStockItems.length === 0) {
        alert('No items need reordering at this time.');
        return;
    }
    
    alert(`Found ${lowStockItems.length} items that need reordering. This feature will be implemented in the next update.`);
}

function exportInventory() {
    // Export the inventoryTable
    const table = document.getElementById('inventoryTable');
    if (!table) {
        alert('No table found to export.');
        return;
    }

    // Build headers dynamically from the table's thead
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => headers.push(th.textContent.trim()));

    const data = [headers];

    // Collect visible rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.classList.contains('empty-row')) return;
        if (row.style.display === 'none') return;
        const cells = row.querySelectorAll('td');
        const rowData = [];
        cells.forEach(cell => rowData.push(cell.textContent.trim()));
        data.push(rowData);
    });

    if (data.length <= 1) {
        alert('No rows to export.');
        return;
    }

    // Proper CSV quoting for fields containing commas/newlines/quotes
    const csv = data.map(r => r.map(f => '"' + String(f).replace(/"/g, '""') + '"').join(',')).join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const baseName = 'inventory_report_';
    a.download = baseName + new Date().toISOString().split('T')[0] + '.csv';
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
    const modals = ['stockAdjustmentModal', 'stockLevelsModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Ensure the page finishes with the standard layout end include
<?php include '../components/layout-end.php'; ?>