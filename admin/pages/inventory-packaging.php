<?php
// Admin Inventory - Packaging & Supplies Management
$page_title = 'INVENTORY - PACKAGING & SUPPLIES';
include '../components/main-layout.php';

// Handle delete request
if (isset($_GET['delete_supply'])) {
    try {
        $supplyId = intval($_GET['delete_supply']);
        $db->query("DELETE FROM packaging_supplies WHERE supply_id = ?", [$supplyId]);
        showAlert('Item deleted successfully!', 'success');
        header('Location: inventory-packaging.php');
        exit;
    } catch (Exception $e) {
        showAlert('Error deleting item: ' . $e->getMessage(), 'error');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                try {
                    $itemName = sanitizeInput($_POST['item_name']);
                    $unit = sanitizeInput($_POST['unit']);
                    $currentStock = floatval($_POST['current_stock']);
                    $reorderLevel = floatval($_POST['reorder_level']);
                    $costPerUnit = floatval($_POST['cost_per_unit']);
                    $supplier = sanitizeInput($_POST['supplier']);
                    
                    $db->query("INSERT INTO packaging_supplies (item_name, unit, current_stock, reorder_level, cost_per_unit, supplier, status) VALUES (?, ?, ?, ?, ?, ?, 'active')", [
                        $itemName, $unit, $currentStock, $reorderLevel, $costPerUnit, $supplier
                    ]);
                    
                    showAlert('Item added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding item: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_item':
                try {
                    $supplyId = intval($_POST['supply_id']);
                    $itemName = sanitizeInput($_POST['item_name']);
                    $unit = sanitizeInput($_POST['unit']);
                    $currentStock = floatval($_POST['current_stock']);
                    $reorderLevel = floatval($_POST['reorder_level']);
                    $costPerUnit = floatval($_POST['cost_per_unit']);
                    $supplier = sanitizeInput($_POST['supplier']);
                    $status = $_POST['status'];
                    
                    $db->query("UPDATE packaging_supplies SET item_name = ?, unit = ?, current_stock = ?, reorder_level = ?, cost_per_unit = ?, supplier = ?, status = ? WHERE supply_id = ?", [
                        $itemName, $unit, $currentStock, $reorderLevel, $costPerUnit, $supplier, $status, $supplyId
                    ]);
                    
                    showAlert('Item updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating item: ' . $e->getMessage(), 'error');
                }
                break;
        }
    }
}

// Get packaging & supplies data
$supplies = $db->fetchAll("SELECT * FROM packaging_supplies ORDER BY item_name");

// Calculate stats
$totalItems = count($supplies);
$lowStockCount = count(array_filter($supplies, function($item) { return $item['current_stock'] <= $item['reorder_level']; }));
$totalValue = array_sum(array_map(function($item) { return $item['current_stock'] * $item['cost_per_unit']; }, $supplies));
?>

<!-- Same styling as ingredients page -->
<style>
.inventory-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.inventory-tabs { display: flex; gap: 5px; margin-bottom: 30px; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.tab-item { flex: 1; padding: 12px 20px; text-align: center; text-decoration: none; color: #6c757d; border-radius: 6px; font-weight: 500; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; border: 2px solid transparent; }
.tab-item:hover { background: #f8f9fa; color: #495057; }
.tab-item.active { background: #3498db; color: white; border-color: #2980b9; }
.tab-item i { font-size: 16px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
.stat-label { color: #7f8c8d; font-size: 14px; }
.content-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.card-header { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.3s; }
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn:hover { opacity: 0.9; transform: translateY(-1px); }
table { width: 100%; border-collapse: collapse; }
thead { background: #34495e; color: white; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; font-size: 13px; }
tbody tr:hover { background: #f8f9fa; }
.status-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-low { background: #fff3cd; color: #856404; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 3% auto; padding: 0; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { background: #34495e; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 20px; }
.close { color: white; font-size: 24px; font-weight: bold; cursor: pointer; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group.full-width { grid-column: 1 / -1; }
.form-label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c3e50; font-size: 13px; }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid #bdc3c7; border-radius: 4px; font-size: 14px; }
.form-control:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2); }
</style>

<div class="inventory-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Inventory Management - Packaging & Supplies</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Manage packaging materials and supplies</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Item
        </button>
        <button class="btn btn-danger" onclick="openManageModal()">
            <i class="fas fa-cog"></i> Manage
        </button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: #3498db;"><?php echo $totalItems; ?></div>
        <div class="stat-label">Total Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #e74c3c;"><?php echo $lowStockCount; ?></div>
        <div class="stat-label">Low Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #27ae60;">₱<?php echo number_format($totalValue, 2); ?></div>
        <div class="stat-label">Total Value</div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3 style="margin: 0;">Packaging & Supplies List</h3>
        <input type="text" id="searchInput" placeholder="Search items..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" onkeyup="filterTable()">
    </div>
    <div class="table-container">
        <table id="suppliesTable">
            <thead>
                <tr>
                    <th>ITEM NAME</th>
                    <th>UNIT</th>
                    <th>CURRENT STOCK</th>
                    <th>REORDER LEVEL</th>
                    <th>COST PER UNIT</th>
                    <th>SUPPLIER</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplies as $supply): 
                    $isLowStock = $supply['current_stock'] <= $supply['reorder_level'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($supply['item_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($supply['unit']); ?></td>
                    <td>
                        <strong style="color: <?php echo $isLowStock ? '#e74c3c' : '#27ae60'; ?>">
                            <?php echo number_format($supply['current_stock'], 2); ?>
                        </strong>
                    </td>
                    <td><?php echo number_format($supply['reorder_level'], 2); ?></td>
                    <td>₱<?php echo number_format($supply['cost_per_unit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($supply['supplier'] ?: 'N/A'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $supply['status']; ?>">
                            <?php echo strtoupper($supply['status']); ?>
                        </span>
                        <?php if ($isLowStock): ?>
                            <span class="status-badge status-low">LOW STOCK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick='editItem(<?php echo json_encode($supply); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Item</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add_item">
                <input type="hidden" name="supply_id" id="supplyId">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="item_name" id="itemName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unit *</label>
                        <select name="unit" id="unit" class="form-control" required>
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="pack">Pack</option>
                            <option value="box">Box</option>
                            <option value="roll">Roll</option>
                            <option value="set">Set</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Current Stock *</label>
                        <input type="number" name="current_stock" id="currentStock" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reorder Level *</label>
                        <input type="number" name="reorder_level" id="reorderLevel" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cost Per Unit (₱) *</label>
                        <input type="number" name="cost_per_unit" id="costPerUnit" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" id="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                    
                    <div class="form-group" id="statusGroup" style="display: none;">
                        <label class="form-label">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Items Modal -->
<div id="manageModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Manage Packaging & Supplies</h3>
            <span class="close" onclick="closeManageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="text" id="manageSearchInput" placeholder="Search items..." style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;" onkeyup="filterManageTable()">
            <div style="max-height: 500px; overflow-y: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="manageTableBody">
                        <?php foreach ($supplies as $supply): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supply['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($supply['unit']); ?></td>
                            <td><?php echo number_format($supply['current_stock'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $supply['status']; ?>">
                                    <?php echo strtoupper($supply['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button class="btn btn-danger btn-sm" onclick="deleteSupply(<?php echo $supply['supply_id']; ?>, '<?php echo addslashes($supply['item_name']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
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
function openManageModal() {
    document.getElementById('manageModal').style.display = 'block';
}

function closeManageModal() {
    document.getElementById('manageModal').style.display = 'none';
}

function filterManageTable() {
    const searchTerm = document.getElementById('manageSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#manageTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

function deleteSupply(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        window.location.href = '?delete_supply=' + id;
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Item';
    document.getElementById('formAction').value = 'add_item';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('itemModal').style.display = 'block';
}

function editItem(item) {
    document.getElementById('modalTitle').textContent = 'Edit Item';
    document.getElementById('formAction').value = 'update_item';
    document.getElementById('supplyId').value = item.supply_id;
    document.getElementById('itemName').value = item.item_name;
    document.getElementById('unit').value = item.unit;
    document.getElementById('currentStock').value = item.current_stock;
    document.getElementById('reorderLevel').value = item.reorder_level;
    document.getElementById('costPerUnit').value = item.cost_per_unit;
    document.getElementById('supplier').value = item.supplier || '';
    document.getElementById('status').value = item.status;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('itemModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('itemModal').style.display = 'none';
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#suppliesTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
}

function exportData() {
    alert('Export functionality will be implemented soon.');
}

window.onclick = function(event) {
    const itemModal = document.getElementById('itemModal');
    const manageModal = document.getElementById('manageModal');
    
    if (event.target === itemModal) itemModal.style.display = 'none';
    if (event.target === manageModal) manageModal.style.display = 'none';
}
</script>

<?php include '../components/layout-end.php'; ?>
