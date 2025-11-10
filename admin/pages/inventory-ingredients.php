<?php
// Admin Inventory - Ingredients Management
$page_title = 'INVENTORY - INGREDIENTS';
include '../components/main-layout.php';

// Handle delete request
if (isset($_GET['delete_ingredient'])) {
    try {
        $ingredientId = intval($_GET['delete_ingredient']);
        $db->query("DELETE FROM ingredients WHERE ingredient_id = ?", [$ingredientId]);
        showAlert('Ingredient deleted successfully!', 'success');
        header('Location: inventory-ingredients.php');
        exit;
    } catch (Exception $e) {
        showAlert('Error deleting ingredient: ' . $e->getMessage(), 'error');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_ingredient':
                try {
                    $ingredientName = sanitizeInput($_POST['ingredient_name']);
                    $unit = sanitizeInput($_POST['unit']);
                    $currentStock = floatval($_POST['current_stock']);
                    $reorderLevel = floatval($_POST['reorder_level']);
                    $costPerUnit = floatval($_POST['cost_per_unit']);
                    $supplier = sanitizeInput($_POST['supplier']);
                    $expiryDate = $_POST['expiry_date'] ?: null;
                    
                    $db->query("INSERT INTO ingredients (ingredient_name, unit, current_stock, reorder_level, cost_per_unit, supplier, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')", [
                        $ingredientName, $unit, $currentStock, $reorderLevel, $costPerUnit, $supplier, $expiryDate
                    ]);
                    
                    showAlert('Ingredient added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding ingredient: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_ingredient':
                try {
                    $ingredientId = intval($_POST['ingredient_id']);
                    $ingredientName = sanitizeInput($_POST['ingredient_name']);
                    $unit = sanitizeInput($_POST['unit']);
                    $currentStock = floatval($_POST['current_stock']);
                    $reorderLevel = floatval($_POST['reorder_level']);
                    $costPerUnit = floatval($_POST['cost_per_unit']);
                    $supplier = sanitizeInput($_POST['supplier']);
                    $expiryDate = $_POST['expiry_date'] ?: null;
                    $status = $_POST['status'];
                    
                    $db->query("UPDATE ingredients SET ingredient_name = ?, unit = ?, current_stock = ?, reorder_level = ?, cost_per_unit = ?, supplier = ?, expiry_date = ?, status = ? WHERE ingredient_id = ?", [
                        $ingredientName, $unit, $currentStock, $reorderLevel, $costPerUnit, $supplier, $expiryDate, $status, $ingredientId
                    ]);
                    
                    showAlert('Ingredient updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating ingredient: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'adjust_stock':
                try {
                    $ingredientId = intval($_POST['ingredient_id']);
                    $adjustmentType = $_POST['adjustment_type'];
                    $quantity = floatval($_POST['quantity']);
                    $reason = sanitizeInput($_POST['reason']);
                    
                    $currentStock = $db->fetchValue("SELECT current_stock FROM ingredients WHERE ingredient_id = ?", [$ingredientId]);
                    
                    if ($adjustmentType === 'add') {
                        $newStock = $currentStock + $quantity;
                    } elseif ($adjustmentType === 'subtract') {
                        $newStock = max(0, $currentStock - $quantity);
                    } else {
                        $newStock = $quantity;
                    }
                    
                    $db->query("UPDATE ingredients SET current_stock = ?, last_updated = NOW() WHERE ingredient_id = ?", [$newStock, $ingredientId]);
                    
                    showAlert('Stock adjusted successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adjusting stock: ' . $e->getMessage(), 'error');
                }
                break;
        }
    }
}

// Get ingredients data
$ingredients = $db->fetchAll("SELECT * FROM ingredients ORDER BY ingredient_name");

// Calculate stats
$totalIngredients = count($ingredients);
$lowStockCount = count(array_filter($ingredients, function($item) { return $item['current_stock'] <= $item['reorder_level']; }));
$totalValue = array_sum(array_map(function($item) { return $item['current_stock'] * $item['cost_per_unit']; }, $ingredients));
$expiringSoon = count(array_filter($ingredients, function($item) { 
    if (!$item['expiry_date']) return false;
    $daysUntilExpiry = (strtotime($item['expiry_date']) - time()) / 86400;
    return $daysUntilExpiry > 0 && $daysUntilExpiry <= 30;
}));
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

.stats-grid {
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

.content-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.btn:hover { opacity: 0.9; transform: translateY(-1px); }

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #34495e;
    color: white;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
    font-size: 13px;
}

tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-low { background: #fff3cd; color: #856404; }
.status-expired { background: #f8d7da; color: #721c24; }

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
    margin: 3% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
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

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
    font-size: 13px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}
</style>

<div class="inventory-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Inventory Management - Ingredients</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Manage raw ingredients and materials</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Ingredient
        </button>
        <button class="btn btn-danger" onclick="openManageModal()">
            <i class="fas fa-cog"></i> Manage
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: #3498db;"><?php echo $totalIngredients; ?></div>
        <div class="stat-label">Total Ingredients</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #e74c3c;"><?php echo $lowStockCount; ?></div>
        <div class="stat-label">Low Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #f39c12;"><?php echo $expiringSoon; ?></div>
        <div class="stat-label">Expiring Soon (30 days)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #27ae60;">₱<?php echo number_format($totalValue, 2); ?></div>
        <div class="stat-label">Total Value</div>
    </div>
</div>

<!-- Ingredients Table -->
<div class="content-card">
    <div class="card-header">
        <h3 style="margin: 0;">Ingredients List</h3>
        <input type="text" id="searchInput" placeholder="Search ingredients..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" onkeyup="filterTable()">
    </div>
    <div class="table-container">
        <table id="ingredientsTable">
            <thead>
                <tr>
                    <th>INGREDIENT NAME</th>
                    <th>UNIT</th>
                    <th>CURRENT STOCK</th>
                    <th>REORDER LEVEL</th>
                    <th>EXPIRY DATE</th>
                    <th>COST PER UNIT</th>
                    <th>SUPPLIER</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingredients as $ingredient): 
                    $isLowStock = $ingredient['current_stock'] <= $ingredient['reorder_level'];
                    $isExpired = $ingredient['expiry_date'] && strtotime($ingredient['expiry_date']) < time();
                    $isExpiringSoon = $ingredient['expiry_date'] && (strtotime($ingredient['expiry_date']) - time()) / 86400 <= 30 && !$isExpired;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                    <td>
                        <strong style="color: <?php echo $isLowStock ? '#e74c3c' : '#27ae60'; ?>">
                            <?php echo number_format($ingredient['current_stock'], 2); ?>
                        </strong>
                    </td>
                    <td><?php echo number_format($ingredient['reorder_level'], 2); ?></td>
                    <td>
                        <?php if ($ingredient['expiry_date']): ?>
                            <span style="color: <?php echo $isExpired ? '#e74c3c' : ($isExpiringSoon ? '#f39c12' : '#27ae60'); ?>">
                                <?php echo date('M d, Y', strtotime($ingredient['expiry_date'])); ?>
                                <?php if ($isExpired): ?>
                                    <br><small>(EXPIRED)</small>
                                <?php elseif ($isExpiringSoon): ?>
                                    <br><small>(<?php echo ceil((strtotime($ingredient['expiry_date']) - time()) / 86400); ?> days left)</small>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #7f8c8d;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>₱<?php echo number_format($ingredient['cost_per_unit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($ingredient['supplier'] ?: 'N/A'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $ingredient['status']; ?>">
                            <?php echo strtoupper($ingredient['status']); ?>
                        </span>
                        <?php if ($isLowStock): ?>
                            <span class="status-badge status-low">LOW STOCK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick='editIngredient(<?php echo json_encode($ingredient); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" onclick='adjustStock(<?php echo json_encode($ingredient); ?>)' title="Adjust Stock">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Ingredient Modal -->
<div id="ingredientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Ingredient</h3>
            <span class="close" onclick="closeModal('ingredientModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="ingredientForm">
                <input type="hidden" name="action" id="formAction" value="add_ingredient">
                <input type="hidden" name="ingredient_id" id="ingredientId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Ingredient Name *</label>
                        <input type="text" name="ingredient_name" id="ingredientName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unit *</label>
                        <select name="unit" id="unit" class="form-control" required>
                            <option value="kg">Kilogram (kg)</option>
                            <option value="g">Gram (g)</option>
                            <option value="L">Liter (L)</option>
                            <option value="mL">Milliliter (mL)</option>
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="oz">Ounce (oz)</option>
                            <option value="lb">Pound (lb)</option>
                            <option value="cup">Cup</option>
                            <option value="tbsp">Tablespoon</option>
                            <option value="tsp">Teaspoon</option>
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
                    
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiryDate" class="form-control">
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
                    <button type="button" class="btn" onclick="closeModal('ingredientModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div id="adjustStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Adjust Stock</h3>
            <span class="close" onclick="closeModal('adjustStockModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="ingredient_id" id="adjustIngredientId">
                
                <div class="form-group">
                    <div class="form-label">Ingredient: <span id="adjustIngredientName" style="font-weight: bold;"></span></div>
                    <div style="color: #7f8c8d; font-size: 12px;">Current Stock: <span id="adjustCurrentStock"></span> <span id="adjustUnit"></span></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Adjustment Type</label>
                    <select name="adjustment_type" class="form-control" required>
                        <option value="add">Add Stock</option>
                        <option value="subtract">Subtract Stock</option>
                        <option value="set">Set Stock Level</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Reason for adjustment..." required></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('adjustStockModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Ingredients Modal -->
<div id="manageModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Manage Ingredients</h3>
            <span class="close" onclick="closeManageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="text" id="manageSearchInput" placeholder="Search ingredients..." style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;" onkeyup="filterManageTable()">
            <div style="max-height: 500px; overflow-y: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Ingredient Name</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="manageTableBody">
                        <?php foreach ($ingredients as $ingredient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                            <td><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                            <td><?php echo number_format($ingredient['current_stock'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $ingredient['status']; ?>">
                                    <?php echo strtoupper($ingredient['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button class="btn btn-danger btn-sm" onclick="deleteIngredient(<?php echo $ingredient['ingredient_id']; ?>, '<?php echo addslashes($ingredient['ingredient_name']); ?>')">
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

function deleteIngredient(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        window.location.href = '?delete_ingredient=' + id;
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Ingredient';
    document.getElementById('formAction').value = 'add_ingredient';
    document.getElementById('ingredientForm').reset();
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('ingredientModal').style.display = 'block';
}

function editIngredient(ingredient) {
    document.getElementById('modalTitle').textContent = 'Edit Ingredient';
    document.getElementById('formAction').value = 'update_ingredient';
    document.getElementById('ingredientId').value = ingredient.ingredient_id;
    document.getElementById('ingredientName').value = ingredient.ingredient_name;
    document.getElementById('unit').value = ingredient.unit;
    document.getElementById('currentStock').value = ingredient.current_stock;
    document.getElementById('reorderLevel').value = ingredient.reorder_level;
    document.getElementById('costPerUnit').value = ingredient.cost_per_unit;
    document.getElementById('expiryDate').value = ingredient.expiry_date || '';
    document.getElementById('supplier').value = ingredient.supplier || '';
    document.getElementById('status').value = ingredient.status;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('ingredientModal').style.display = 'block';
}

function adjustStock(ingredient) {
    document.getElementById('adjustIngredientId').value = ingredient.ingredient_id;
    document.getElementById('adjustIngredientName').textContent = ingredient.ingredient_name;
    document.getElementById('adjustCurrentStock').textContent = parseFloat(ingredient.current_stock).toFixed(2);
    document.getElementById('adjustUnit').textContent = ingredient.unit;
    document.getElementById('adjustStockModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#ingredientsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

function exportData() {
    const data = [];
    const rows = document.querySelectorAll('#ingredientsTable tbody tr');
    
    data.push(['Ingredient Name', 'Unit', 'Current Stock', 'Reorder Level', 'Expiry Date', 'Cost Per Unit', 'Supplier', 'Status']);
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            data.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim().split('\n')[0],
                cells[5].textContent.trim(),
                cells[6].textContent.trim(),
                cells[7].textContent.trim()
            ]);
        }
    });
    
    const csv = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ingredients_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const ingredientModal = document.getElementById('ingredientModal');
    const adjustModal = document.getElementById('adjustStockModal');
    const manageModal = document.getElementById('manageModal');
    
    if (event.target === ingredientModal) ingredientModal.style.display = 'none';
    if (event.target === adjustModal) adjustModal.style.display = 'none';
    if (event.target === manageModal) manageModal.style.display = 'none';
}
</script>

<?php include '../components/layout-end.php'; ?>
