<?php
// Admin Inventory - Recipes / Bill of Materials
$page_title = 'INVENTORY - RECIPES/BOM';
include '../components/main-layout.php';

// Handle delete request
if (isset($_GET['delete_recipe'])) {
    try {
        $recipeId = intval($_GET['delete_recipe']);
        $db->query("DELETE FROM recipe_ingredients WHERE recipe_id = ?", [$recipeId]);
        $db->query("DELETE FROM recipes WHERE recipe_id = ?", [$recipeId]);
        showAlert('Recipe deleted successfully!', 'success');
        header('Location: inventory-recipes.php');
        exit;
    } catch (Exception $e) {
        showAlert('Error deleting recipe: ' . $e->getMessage(), 'error');
    }
}

// Get all products for dropdown
$products = $db->fetchAll("SELECT product_id, product_name FROM products WHERE status = 'active' ORDER BY product_name");

// Get all ingredients for dropdown
$ingredients = $db->fetchAll("SELECT ingredient_id, ingredient_name, unit FROM ingredients WHERE status = 'active' ORDER BY ingredient_name");

// Get all recipes with product info and total cost
$recipes = $db->fetchAll("
    SELECT r.*, p.product_name, p.price,
           COALESCE(SUM(ri.ingredient_cost * ri.quantity), 0) as total_cost
    FROM recipes r
    JOIN products p ON r.product_id = p.product_id
    LEFT JOIN recipe_ingredients ri ON r.recipe_id = ri.recipe_id
    GROUP BY r.recipe_id
    ORDER BY p.product_name
");
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

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 2% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 900px;
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
    max-height: 70vh;
    overflow-y: auto;
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

.ingredients-section {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.ingredient-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    margin-bottom: 10px;
    align-items: end;
}

.recipe-details {
    margin-top: 10px;
    padding: 10px;
    background: #e3f2fd;
    border-radius: 4px;
    font-size: 12px;
}
</style>

<div class="inventory-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Inventory Management - Recipes / BOM</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Manage product recipes and bill of materials</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Recipe
        </button>
        <button class="btn btn-danger" onclick="openManageModal()">
            <i class="fas fa-cog"></i> Manage
        </button>
    </div>
</div>

<!-- Recipes Table -->
<div class="content-card">
    <div class="card-header">
        <h3 style="margin: 0;">Recipes & Bill of Materials</h3>
        <input type="text" id="searchInput" placeholder="Search recipes..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" onkeyup="filterTable()">
    </div>
    <table id="recipesTable">
        <thead>
            <tr>
                <th>DRINK NAME</th>
                <th>INGREDIENTS</th>
                <th>QUANTITY</th>
                <th>UNIT</th>
                <th>COST PER UNIT</th>
                <th>INGREDIENT COST</th>
                <th>TOTAL COST</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recipes as $recipe): 
                $recipeIngredients = $db->fetchAll("
                    SELECT ri.*, i.ingredient_name, i.unit
                    FROM recipe_ingredients ri
                    JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                    WHERE ri.recipe_id = ?
                ", [$recipe['recipe_id']]);
                
                $ingredientCount = count($recipeIngredients);
            ?>
                <?php if ($ingredientCount > 0): ?>
                    <?php foreach ($recipeIngredients as $index => $ing): ?>
                    <tr>
                        <?php if ($index === 0): ?>
                        <td rowspan="<?php echo $ingredientCount; ?>">
                            <strong><?php echo htmlspecialchars($recipe['product_name']); ?></strong>
                            <?php if ($recipe['serving_size']): ?>
                                <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($recipe['serving_size']); ?></small>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($ing['ingredient_name']); ?></td>
                        <td><?php echo number_format($ing['quantity'], 2); ?></td>
                        <td><?php echo htmlspecialchars($ing['unit']); ?></td>
                        <td>₱<?php echo number_format($ing['ingredient_cost'], 2); ?></td>
                        <td>₱<?php echo number_format($ing['quantity'] * $ing['ingredient_cost'], 2); ?></td>
                        <?php if ($index === 0): ?>
                        <td rowspan="<?php echo $ingredientCount; ?>">
                            <strong>₱<?php echo number_format($recipe['total_cost'], 2); ?></strong>
                        </td>
                        <td rowspan="<?php echo $ingredientCount; ?>">
                            <button class="btn btn-primary btn-sm" onclick="viewRecipe(<?php echo $recipe['recipe_id']; ?>, '<?php echo htmlspecialchars($recipe['product_name'], ENT_QUOTES); ?>')" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($recipe['product_name']); ?></strong></td>
                    <td colspan="5" style="color: #7f8c8d;">No ingredients defined</td>
                    <td>₱0.00</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="viewRecipe(<?php echo $recipe['recipe_id']; ?>, '<?php echo htmlspecialchars($recipe['product_name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (empty($recipes)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                    No recipes found. Click "Add Recipe" to create one.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Recipe Modal -->
<div id="recipeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Recipe / Bill of Materials</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="inventory-recipes-save.php">
                <input type="hidden" name="action" value="add_recipe">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Select Product *</label>
                        <select name="product_id" id="productId" class="form-control" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Recipe Name *</label>
                        <input type="text" name="recipe_name" class="form-control" placeholder="e.g., Standard Cappuccino" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Serving Size</label>
                        <input type="text" name="serving_size" class="form-control" placeholder="e.g., 12 oz, 250ml">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Prep Time (minutes)</label>
                        <input type="number" name="preparation_time" class="form-control" min="1" placeholder="e.g., 5">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions, tips, etc."></textarea>
                    </div>
                </div>
                
                <div class="ingredients-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0;">Ingredients</h4>
                        <button type="button" class="btn btn-success btn-sm" onclick="addIngredientRow()">
                            <i class="fas fa-plus"></i> Add Ingredient
                        </button>
                    </div>
                    
                    <div id="ingredientsContainer">
                        <div class="ingredient-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Ingredient</label>
                                <select name="ingredients[0][ingredient_id]" class="form-control ingredient-select" onchange="updateUnit(this, 0)" required>
                                    <option value="">-- Select Ingredient --</option>
                                    <?php foreach ($ingredients as $ingredient): ?>
                                    <option value="<?php echo $ingredient['ingredient_id']; ?>" data-unit="<?php echo $ingredient['unit']; ?>">
                                        <?php echo htmlspecialchars($ingredient['ingredient_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="ingredients[0][quantity]" class="form-control" step="0.01" min="0.01" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Unit</label>
                                <input type="text" name="ingredients[0][unit]" id="unit_0" class="form-control" readonly required>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow(this)" style="margin-top: 27px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Recipe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Recipe Modal -->
<div id="viewRecipeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="viewRecipeName">Recipe Details</h3>
            <span class="close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewRecipeContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
let ingredientRowCount = 1;

function showRecipeOptions(recipeId, recipeName, productName) {
    if (confirm(`What would you like to do with "${productName}"?\n\nClick OK to VIEW\nClick Cancel to DELETE`)) {
        // User clicked OK - View
        viewRecipe(recipeId, productName);
    } else {
        // User clicked Cancel - Delete
        if (confirm(`Are you sure you want to DELETE recipe "${recipeName}"? This action cannot be undone.`)) {
            window.location.href = '?delete_recipe=' + recipeId;
        }
    }
}

function openAddModal() {
    document.getElementById('recipeModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('recipeModal').style.display = 'none';
}

function closeViewModal() {
    document.getElementById('viewRecipeModal').style.display = 'none';
}

function addIngredientRow() {
    const container = document.getElementById('ingredientsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'ingredient-row';
    newRow.innerHTML = `
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Ingredient</label>
            <select name="ingredients[${ingredientRowCount}][ingredient_id]" class="form-control ingredient-select" onchange="updateUnit(this, ${ingredientRowCount})" required>
                <option value="">-- Select Ingredient --</option>
                <?php foreach ($ingredients as $ingredient): ?>
                <option value="<?php echo $ingredient['ingredient_id']; ?>" data-unit="<?php echo $ingredient['unit']; ?>">
                    <?php echo htmlspecialchars($ingredient['ingredient_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Quantity</label>
            <input type="number" name="ingredients[${ingredientRowCount}][quantity]" class="form-control" step="0.01" min="0.01" required>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Unit</label>
            <input type="text" name="ingredients[${ingredientRowCount}][unit]" id="unit_${ingredientRowCount}" class="form-control" readonly required>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow(this)" style="margin-top: 27px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newRow);
    ingredientRowCount++;
}

function removeIngredientRow(button) {
    const row = button.closest('.ingredient-row');
    if (document.querySelectorAll('.ingredient-row').length > 1) {
        row.remove();
    } else {
        alert('At least one ingredient is required.');
    }
}

function updateUnit(select, index) {
    const selectedOption = select.options[select.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit');
    document.getElementById(`unit_${index}`).value = unit || '';
}

function viewRecipe(recipeId, productName) {
    document.getElementById('viewRecipeName').textContent = productName + ' - Recipe Details';
    
    // Fetch recipe details via AJAX
    fetch(`inventory-recipes-view.php?recipe_id=${recipeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('viewRecipeContent').innerHTML = `
                    <div style="padding:20px;text-align:center;color:#e74c3c;">
                        <i class="fas fa-exclamation-circle" style="font-size:48px;margin-bottom:10px;"></i>
                        <p>${data.error}</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div style="margin-bottom:20px;">
                    <h4 style="color:#2c3e50;margin-bottom:10px;"><i class="fas fa-info-circle"></i> Recipe Information</h4>
                    <div style="background:#f8f9fa;padding:15px;border-radius:6px;">
                        <p><strong>Recipe Name:</strong> ${data.recipe_name || 'N/A'}</p>
                        <p><strong>Serving Size:</strong> ${data.serving_size || 'N/A'}</p>
                        <p><strong>Preparation Time:</strong> ${data.preparation_time ? data.preparation_time + ' minutes' : 'Not specified'}</p>
                        ${data.notes ? '<p><strong>Notes:</strong> ' + data.notes + '</p>' : ''}
                    </div>
                </div>
                
                <h4 style="color:#2c3e50;margin-bottom:10px;"><i class="fas fa-flask"></i> Ingredients Breakdown</h4>
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                    <thead style="background:#34495e;color:white;">
                        <tr>
                            <th style="padding:10px;text-align:left;">Ingredient</th>
                            <th style="padding:10px;text-align:center;">Quantity</th>
                            <th style="padding:10px;text-align:center;">Unit</th>
                            <th style="padding:10px;text-align:right;">Cost/Unit</th>
                            <th style="padding:10px;text-align:right;">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            let totalCost = 0;
            
            if (data.ingredients && data.ingredients.length > 0) {
                data.ingredients.forEach((ing, index) => {
                    const ingredientTotal = parseFloat(ing.quantity) * parseFloat(ing.ingredient_cost);
                    totalCost += ingredientTotal;
                    
                    html += `
                        <tr style="border-bottom:1px solid #ecf0f1;${index % 2 === 0 ? 'background:#f8f9fa;' : ''}">
                            <td style="padding:10px;">${ing.ingredient_name}</td>
                            <td style="padding:10px;text-align:center;">${parseFloat(ing.quantity).toFixed(2)}</td>
                            <td style="padding:10px;text-align:center;">${ing.unit}</td>
                            <td style="padding:10px;text-align:right;">₱${parseFloat(ing.ingredient_cost).toFixed(2)}</td>
                            <td style="padding:10px;text-align:right;">₱${ingredientTotal.toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="5" style="padding:20px;text-align:center;color:#7f8c8d;">
                            No ingredients defined for this recipe
                        </td>
                    </tr>
                `;
            }
            
            html += `
                    </tbody>
                    <tfoot style="background:#e8f5e9;font-weight:bold;">
                        <tr>
                            <td colspan="4" style="padding:12px;text-align:right;">TOTAL RECIPE COST:</td>
                            <td style="padding:12px;text-align:right;color:#27ae60;font-size:16px;">₱${totalCost.toFixed(2)}</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="background:#fff3cd;padding:15px;border-radius:6px;border-left:4px solid #f39c12;">
                    <p style="margin:0;font-size:13px;"><i class="fas fa-info-circle"></i> <strong>Note:</strong> This is a reference recipe for cost calculation. Actual inventory deductions are managed in the Products page where you assign specific ingredients and packaging supplies.</p>
                </div>
                
                <div style="margin-top:20px;text-align:right;">
                    <button class="btn btn-primary" onclick="closeViewModal()">Close</button>
                </div>
            `;
            
            document.getElementById('viewRecipeContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching recipe:', error);
            document.getElementById('viewRecipeContent').innerHTML = `
                <div style="padding:20px;text-align:center;color:#e74c3c;">
                    <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:10px;"></i>
                    <p>Failed to load recipe details. Please try again.</p>
                </div>
            `;
        });
    
    document.getElementById('viewRecipeModal').style.display = 'block';
}

function deleteRecipe(recipeId) {
    if (confirm('Are you sure you want to delete this recipe? This cannot be undone.')) {
        window.location.href = 'inventory-recipes-delete.php?id=' + recipeId;
    }
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#recipesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const recipeModal = document.getElementById('recipeModal');
    const viewModal = document.getElementById('viewRecipeModal');
    const manageModal = document.getElementById('manageModal');
    
    if (event.target === recipeModal) recipeModal.style.display = 'none';
    if (event.target === viewModal) viewModal.style.display = 'none';
    if (event.target === manageModal) manageModal.style.display = 'none';
}
</script>

<?php include '../components/layout-end.php'; ?>
