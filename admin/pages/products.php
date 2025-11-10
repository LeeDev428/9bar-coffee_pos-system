<?php
// Admin Products Management Page
$page_title = 'MANAGE PRODUCTS';
include '../components/main-layout.php';

// Initialize managers
$productManager = new ProductManager($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                try {
                    $data = [
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'category_id' => intval($_POST['category_id']),
                        'description' => sanitizeInput($_POST['description']),
                        'price' => floatval($_POST['price']),
                        'cost_price' => floatval($_POST['cost_price']),
                        'current_stock' => intval($_POST['current_stock']),
                        'minimum_stock' => intval($_POST['minimum_stock']),
                        'maximum_stock' => intval($_POST['maximum_stock']),
                        'reorder_level' => intval($_POST['reorder_level']),
                        'requires_ice' => isset($_POST['requires_ice']) ? 1 : 0,
                        'main_ingredient_id' => !empty($_POST['main_ingredient_id']) ? intval($_POST['main_ingredient_id']) : null
                    ];

                    // Handle uploaded product image (optional)
                    if (!empty($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/../../assets/img/products/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $tmpName = $_FILES['product_image']['tmp_name'];
                        $origName = basename($_FILES['product_image']['name']);
                        $ext = pathinfo($origName, PATHINFO_EXTENSION);
                        $allowed = ['jpg','jpeg','png','gif','webp'];
                        if (!in_array(strtolower($ext), $allowed)) {
                            throw new Exception('Unsupported image type. Allowed: jpg, png, gif, webp');
                        }

                        $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                        $newName = $safeName . '_' . time() . '.' . $ext;
                        $dest = $uploadDir . $newName;

                        if (!move_uploaded_file($tmpName, $dest)) {
                            throw new Exception('Failed to move uploaded image');
                        }

                        // Store relative filename for DB
                        $data['image'] = $newName;
                    }

                    $productId = $productManager->addProduct($data);
                    
                    // Save product ingredients
                    if (!empty($_POST['ingredients']) && is_array($_POST['ingredients'])) {
                        foreach ($_POST['ingredients'] as $ing) {
                            if (!empty($ing['ingredient_id']) && !empty($ing['quantity'])) {
                                $db->query("INSERT INTO product_ingredients (product_id, ingredient_id, quantity_per_unit, unit) VALUES (?, ?, ?, ?)", [
                                    $productId,
                                    intval($ing['ingredient_id']),
                                    floatval($ing['quantity']),
                                    sanitizeInput($ing['unit'] ?? '')
                                ]);
                            }
                        }
                    }
                    
                    // Save product packaging
                    if (!empty($_POST['packaging']) && is_array($_POST['packaging'])) {
                        foreach ($_POST['packaging'] as $pkg) {
                            if (!empty($pkg['supply_id']) && !empty($pkg['quantity'])) {
                                $db->query("INSERT INTO product_packaging (product_id, supply_id, quantity_per_unit) VALUES (?, ?, ?)", [
                                    $productId,
                                    intval($pkg['supply_id']),
                                    floatval($pkg['quantity'])
                                ]);
                            }
                        }
                    }
                    
                    showAlert('Product added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding product: ' . $e->getMessage(), 'error');
                }
                break;
            case 'add_category':
                try {
                    $categoryName = sanitizeInput($_POST['category_name']);
                    $description = sanitizeInput($_POST['category_description'] ?? '');
                    if (empty($categoryName)) {
                        throw new Exception('Category name is required');
                    }
                    $db->query("INSERT INTO categories (category_name, description) VALUES (?, ?)", [$categoryName, $description]);
                    showAlert('Category added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding category: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'delete_category':
                try {
                    $categoryId = intval($_POST['category_id']);
                    
                    // Check if category has products
                    $productCount = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'", [$categoryId]);
                    
                    if ($productCount['count'] > 0) {
                        throw new Exception('Cannot delete category. It has ' . $productCount['count'] . ' active product(s). Please reassign or delete the products first.');
                    }
                    
                    // Delete the category
                    $db->query("DELETE FROM categories WHERE category_id = ?", [$categoryId]);
                    showAlert('Category deleted successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error deleting category: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'edit_product':
                try {
                    $productId = intval($_POST['product_id']);
                    $data = [
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'category_id' => intval($_POST['category_id']),
                        'description' => sanitizeInput($_POST['description']),
                        'price' => floatval($_POST['price']),
                        'cost_price' => floatval($_POST['cost_price'])
                    ];
                    
                    $productManager->updateProduct($productId, $data);
                    
                    // Update stock levels (separate from product data) - with defaults
                    $current_stock = isset($_POST['current_stock']) && $_POST['current_stock'] !== '' ? intval($_POST['current_stock']) : 0;
                    $minimum_stock = isset($_POST['minimum_stock']) && $_POST['minimum_stock'] !== '' ? intval($_POST['minimum_stock']) : 5;
                    $maximum_stock = isset($_POST['maximum_stock']) && $_POST['maximum_stock'] !== '' ? intval($_POST['maximum_stock']) : 100;
                    $reorder_level = isset($_POST['reorder_level']) && $_POST['reorder_level'] !== '' ? intval($_POST['reorder_level']) : 10;
                    
                    $db->query("UPDATE inventory SET current_stock = ?, minimum_stock = ?, maximum_stock = ?, reorder_level = ? WHERE product_id = ?", [
                        $current_stock,
                        $minimum_stock,
                        $maximum_stock,
                        $reorder_level,
                        $productId
                    ]);
                    
                    // Update product ingredients
                    // First, delete existing connections
                    $db->query("DELETE FROM product_ingredients WHERE product_id = ?", [$productId]);
                    if (!empty($_POST['edit_ingredients']) && is_array($_POST['edit_ingredients'])) {
                        foreach ($_POST['edit_ingredients'] as $ing) {
                            if (!empty($ing['ingredient_id']) && !empty($ing['quantity'])) {
                                $db->query("INSERT INTO product_ingredients (product_id, ingredient_id, quantity_per_unit, unit) VALUES (?, ?, ?, ?)", [
                                    $productId,
                                    intval($ing['ingredient_id']),
                                    floatval($ing['quantity']),
                                    sanitizeInput($ing['unit'] ?? '')
                                ]);
                            }
                        }
                    }
                    
                    // Update product packaging
                    // First, delete existing connections
                    $db->query("DELETE FROM product_packaging WHERE product_id = ?", [$productId]);
                    if (!empty($_POST['edit_packaging']) && is_array($_POST['edit_packaging'])) {
                        foreach ($_POST['edit_packaging'] as $pkg) {
                            if (!empty($pkg['supply_id']) && !empty($pkg['quantity'])) {
                                $db->query("INSERT INTO product_packaging (product_id, supply_id, quantity_per_unit) VALUES (?, ?, ?)", [
                                    $productId,
                                    intval($pkg['supply_id']),
                                    floatval($pkg['quantity'])
                                ]);
                            }
                        }
                    }
                    
                    showAlert('Product updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating product: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'delete_product':
                try {
                    $productId = intval($_POST['product_id']);
                    $productManager->deleteProduct($productId);
                    showAlert('Product deleted successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error deleting product: ' . $e->getMessage(), 'error');
                }
                break;
        }
    }
}

// Get all products with inventory
$products = $db->fetchAll("
    SELECT p.*, p.image_path AS image, c.category_name, i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.status = 'active'
    ORDER BY c.category_name, p.product_name
");

// Get categories for dropdowns
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Get ingredients for main ingredient dropdown
$ingredients = $db->fetchAll("SELECT ingredient_id, ingredient_name, unit, current_stock FROM ingredients WHERE status = 'active' ORDER BY ingredient_name");

// Get all packaging supplies
$packagingSupplies = $db->fetchAll("SELECT supply_id, item_name, unit, current_stock FROM packaging_supplies WHERE status = 'active' ORDER BY item_name");
?>

<style>
.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    background: #219a52;
}

.btn-warning {
    background: #f39c12;
    color: white;
}

.btn-warning:hover {
    background: #e67e22;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.products-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table-responsive {
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
}

tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.stock-low {
    background: #f8d7da;
    color: #721c24;
}

.stock-normal {
    background: #d4edda;
    color: #155724;
}

.stock-reorder {
    background: #fff3cd;
    color: #856404;
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
}

.modal-content {
    background: white;
    margin: 5% auto;
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
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    opacity: 0.7;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.search-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<div class="products-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">Products Management</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Manage your product catalog and inventory</p>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Category</h3>
                <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                        <button type="button" class="btn" onclick="closeModal('addCategoryModal')" style="margin-right: 10px;">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <button class="btn btn-secondary" onclick="openManageCategoriesModal()" style="background:#6c757d;color:white;">
            <i class="fas fa-folder"></i> Manage Categories
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add New Product
        </button>
    </div>
</div>

<!-- Manage Categories Modal -->
<div id="manageCategoriesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Manage Categories</h3>
            <span class="close" onclick="closeModal('manageCategoriesModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 20px;">
                <button class="btn btn-success" onclick="openAddCategoryModal()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
            
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd;">Category Name</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd; text-align: center;">Products</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ddd; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <?php 
                        $productCount = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'", [$category['category_id']]);
                    ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                            <?php echo $productCount['count']; ?> products
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>', <?php echo $productCount['count']; ?>)">
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

<!-- Search and Filters -->
<div class="search-filters">
    <div class="filters-row">
        <div class="filter-group">
            <label>Search:</label>
            <input type="text" class="form-control" id="searchInput" placeholder="Search products..." 
                   onkeyup="filterProducts()" style="width: 250px;">
        </div>
        <div class="filter-group">
            <label>Category:</label>
            <select class="form-control" id="categoryFilter" onchange="filterProducts()" style="width: 200px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['category_id']; ?>">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Cups Stock Status:</label>
            <select class="form-control" id="stockFilter" onchange="filterProducts()" style="width: 150px;">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="reorder">Need Reorder</option>
                <option value="normal">Normal</option>
            </select>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="products-table">
    <div class="table-responsive">
        <table id="productsTable">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Cost</th>
                    <th>Cups Stock</th>
                    <th>Min Cups Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <?php 
                $stockStatus = 'normal';
                $stockClass = 'stock-normal';
                if ($product['current_stock'] <= $product['minimum_stock']) {
                    $stockStatus = 'low';
                    $stockClass = 'stock-low';
                } elseif ($product['current_stock'] <= $product['reorder_level']) {
                    $stockStatus = 'reorder';
                    $stockClass = 'stock-reorder';
                }
                ?>
                <tr data-category="<?php echo $product['category_id']; ?>" 
                    data-stock="<?php echo $stockStatus; ?>"
                    data-name="<?php echo strtolower($product['product_name']); ?>">
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php $imgFile = $product['image'] ?? $product['image_path'] ?? ''; ?>
                            <?php if (!empty($imgFile)): ?>
                                <img src="<?php echo '../../assets/img/products/' . htmlspecialchars($imgFile); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #eee;">
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($product['description']); ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                    <td>₱<?php echo number_format($product['cost_price'], 2); ?></td>
                    <td>
                        <strong style="font-size: 16px;"><?php echo $product['current_stock']; ?></strong>
                    </td>
                    <td><?php echo $product['minimum_stock']; ?></td>
                    <td>
                        <span class="status-badge <?php echo $stockClass; ?>">
                            <?php echo strtoupper($stockStatus); ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Product</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="product_image" id="productImageInput" accept="image/*" class="form-control">
                    <div style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                        <img id="productImagePreview" src="" alt="Preview" style="max-width:100px; max-height:100px; display:none; border:1px solid #eee; padding:4px; border-radius:4px; object-fit:cover;" />
                        <button type="button" class="btn" id="removeImageBtn" style="display:none;" onclick="removeImagePreview()">Remove</button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Selling Price *</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price *</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Initial Cups Stock *</label>
                        <input type="number" name="current_stock" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Cups Stock *</label>
                        <input type="number" name="minimum_stock" class="form-control" value="5" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Maximum Cups Stock</label>
                        <input type="number" name="maximum_stock" class="form-control" value="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="10">
                    </div>
                </div>
                
                <!-- Ingredients Assignment -->
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label class="form-label" style="margin-bottom: 0;">
                            <i class="fas fa-leaf" style="color: #27ae60;"></i> Ingredients Used
                        </label>
                        <button type="button" class="btn btn-success btn-sm" onclick="addIngredientRow()">
                            <i class="fas fa-plus"></i> Add Ingredient
                        </button>
                    </div>
                    <div id="ingredientsContainer"></div>
                </div>
                
                <!-- Packaging Supplies Assignment -->
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label class="form-label" style="margin-bottom: 0;">
                            <i class="fas fa-box" style="color: #e67e22;"></i> Packaging & Supplies
                        </label>
                        <button type="button" class="btn btn-success btn-sm" onclick="addPackagingRow()">
                            <i class="fas fa-plus"></i> Add Supply
                        </button>
                    </div>
                    <div id="packagingContainer"></div>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('addModal')" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Product</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category_id" id="edit_category_id" class="form-control" required>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Selling Price *</label>
                        <input type="number" name="price" id="edit_price" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price *</label>
                        <input type="number" name="cost_price" id="edit_cost_price" class="form-control" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Current Cups Stock</label>
                        <input type="number" name="current_stock" id="edit_current_stock" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Cups Stock</label>
                        <input type="number" name="minimum_stock" id="edit_minimum_stock" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="product_image_edit" id="editProductImageInput" accept="image/*" class="form-control">
                    <input type="hidden" name="remove_image" id="edit_remove_image" value="0">
                    <div style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                        <img id="editProductImagePreview" src="" alt="Preview" style="max-width:100px; max-height:100px; display:none; border:1px solid #eee; padding:4px; border-radius:4px; object-fit:cover;" />
                        <button type="button" class="btn" id="editRemoveImageBtn" style="display:none;" onclick="removeEditImagePreview()">Remove</button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Maximum Cups Stock</label>
                        <input type="number" name="maximum_stock" id="edit_maximum_stock" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" id="edit_reorder_level" class="form-control">
                    </div>
                </div>
                
                <!-- Ingredients Assignment -->
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label class="form-label" style="margin-bottom: 0;">
                            <i class="fas fa-leaf" style="color: #27ae60;"></i> Ingredients Used
                        </label>
                        <button type="button" class="btn btn-success btn-sm" onclick="addEditIngredientRow()">
                            <i class="fas fa-plus"></i> Add Ingredient
                        </button>
                    </div>
                    <div id="editIngredientsContainer"></div>
                </div>
                
                <!-- Packaging Supplies Assignment -->
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label class="form-label" style="margin-bottom: 0;">
                            <i class="fas fa-box" style="color: #e67e22;"></i> Packaging & Supplies
                        </label>
                        <button type="button" class="btn btn-success btn-sm" onclick="addEditPackagingRow()">
                            <i class="fas fa-plus"></i> Add Supply
                        </button>
                    </div>
                    <div id="editPackagingContainer"></div>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('editModal')" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'block';
}

function openManageCategoriesModal() {
    document.getElementById('manageCategoriesModal').style.display = 'block';
}

function openAddCategoryModal() {
    // Close manage categories modal if open
    closeModal('manageCategoriesModal');
    document.getElementById('addCategoryModal').style.display = 'block';
}

function deleteCategory(categoryId, categoryName, productCount) {
    if (productCount > 0) {
        alert(`Cannot delete "${categoryName}" because it has ${productCount} active product(s).\n\nPlease reassign or delete the products first.`);
        return;
    }
    
    if (confirm(`Are you sure you want to delete the category "${categoryName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="${categoryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openEditModal(product) {
    document.getElementById('edit_product_id').value = product.product_id;
    document.getElementById('edit_product_name').value = product.product_name;
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_cost_price').value = product.cost_price;
    document.getElementById('edit_current_stock').value = product.current_stock || 0;
    document.getElementById('edit_minimum_stock').value = product.minimum_stock || 5;
    document.getElementById('edit_maximum_stock').value = product.maximum_stock || 100;
    document.getElementById('edit_reorder_level').value = product.reorder_level || 10;
    
    // set existing image preview if any
    const preview = document.getElementById('editProductImagePreview');
    const removeBtn = document.getElementById('editRemoveImageBtn');
    const removeFlag = document.getElementById('edit_remove_image');
    const imgFile = product.image || product.image_path || '';
    if (imgFile) {
        const imgPath = '../../assets/img/products/' + imgFile;
        preview.src = imgPath;
        preview.style.display = 'inline-block';
        removeBtn.style.display = 'inline-block';
        removeFlag.value = '0';
    } else {
        preview.src = '';
        preview.style.display = 'none';
        removeBtn.style.display = 'none';
        removeFlag.value = '0';
    }
    
    // Load existing ingredients and packaging
    loadProductConnections(product.product_id);

    document.getElementById('editModal').style.display = 'block';
}

function loadProductConnections(productId) {
    // Clear existing rows
    document.getElementById('editIngredientsContainer').innerHTML = '';
    document.getElementById('editPackagingContainer').innerHTML = '';
    editIngredientRowCount = 0;
    editPackagingRowCount = 0;
    
    // Fetch product connections
    fetch(`../api/get-product-connections.php?product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            // Load ingredients
            if (data.ingredients && data.ingredients.length > 0) {
                data.ingredients.forEach(ing => {
                    addEditIngredientRow(ing.ingredient_id, ing.quantity_per_unit, ing.unit);
                });
            }
            
            // Load packaging
            if (data.packaging && data.packaging.length > 0) {
                data.packaging.forEach(pkg => {
                    addEditPackagingRow(pkg.supply_id, pkg.quantity_per_unit);
                });
            }
        })
        .catch(err => console.error('Failed to load product connections:', err));
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function deleteProduct(productId, productName) {
    if (confirm(`Are you sure you want to delete "${productName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" value="${productId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function filterProducts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const stockFilter = document.getElementById('stockFilter').value;
    
    const rows = document.querySelectorAll('#productsTable tbody tr');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const category = row.dataset.category;
        const stock = row.dataset.stock;
        
        let show = true;
        
        if (searchTerm && !name.includes(searchTerm)) {
            show = false;
        }
        
        if (categoryFilter && category !== categoryFilter) {
            show = false;
        }
        
        if (stockFilter && stock !== stockFilter) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const addCategoryModal = document.getElementById('addCategoryModal');
    const manageCategoriesModal = document.getElementById('manageCategoriesModal');
    
    if (event.target === addModal) {
        addModal.style.display = 'none';
    }
    if (event.target === editModal) {
        editModal.style.display = 'none';
    }
    if (addCategoryModal && event.target === addCategoryModal) {
        addCategoryModal.style.display = 'none';
    }
    if (manageCategoriesModal && event.target === manageCategoriesModal) {
        manageCategoriesModal.style.display = 'none';
    }
}
</script>

<script>
// Image preview for Add Product modal
const productImageInput = document.getElementById('productImageInput');
const productImagePreview = document.getElementById('productImagePreview');
const removeImageBtn = document.getElementById('removeImageBtn');

if (productImageInput) {
    productImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            productImagePreview.src = ev.target.result;
            productImagePreview.style.display = 'inline-block';
            removeImageBtn.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    });
}

function removeImagePreview() {
    if (!productImageInput) return;
    productImageInput.value = '';
    productImagePreview.src = '';
    productImagePreview.style.display = 'none';
    removeImageBtn.style.display = 'none';
}

// Image preview for Edit Product modal
const editProductImageInput = document.getElementById('editProductImageInput');
const editProductImagePreview = document.getElementById('editProductImagePreview');
const editRemoveImageBtn = document.getElementById('editRemoveImageBtn');

if (editProductImageInput) {
    editProductImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            editProductImagePreview.src = ev.target.result;
            editProductImagePreview.style.display = 'inline-block';
            editRemoveImageBtn.style.display = 'inline-block';
            document.getElementById('edit_remove_image').value = '0';
        };
        reader.readAsDataURL(file);
    });
}

function removeEditImagePreview() {
    if (!editProductImageInput) return;
    editProductImageInput.value = '';
    editProductImagePreview.src = '';
    editProductImagePreview.style.display = 'none';
    editRemoveImageBtn.style.display = 'none';
    document.getElementById('edit_remove_image').value = '1';
}

// Ingredient and Packaging Management
const ingredientsData = <?php echo json_encode($ingredients); ?>;
const packagingData = <?php echo json_encode($packagingSupplies); ?>;

let ingredientRowCount = 0;
let packagingRowCount = 0;
let editIngredientRowCount = 0;
let editPackagingRowCount = 0;

function addIngredientRow() {
    const container = document.getElementById('ingredientsContainer');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    row.innerHTML = `
        <div>
            <label class="form-label">Ingredient</label>
            <select name="ingredients[${ingredientRowCount}][ingredient_id]" class="form-control" required>
                <option value="">-- Select --</option>
                ${ingredientsData.map(ing => `<option value="${ing.ingredient_id}" data-unit="${ing.unit}">${ing.ingredient_name} (Stock: ${ing.current_stock} ${ing.unit})</option>`).join('')}
            </select>
        </div>
        <div>
            <label class="form-label">Quantity</label>
            <input type="number" name="ingredients[${ingredientRowCount}][quantity]" class="form-control" step="0.01" min="0.01" required>
        </div>
        <div>
            <label class="form-label">Unit</label>
            <input type="text" name="ingredients[${ingredientRowCount}][unit]" class="form-control" readonly>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
    
    // Auto-fill unit when ingredient is selected
    const select = row.querySelector('select');
    const unitInput = row.querySelector('input[type="text"]');
    select.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        unitInput.value = option.dataset.unit || '';
    });
    
    ingredientRowCount++;
}

function addPackagingRow() {
    const container = document.getElementById('packagingContainer');
    const row = document.createElement('div');
    row.className = 'packaging-row';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    row.innerHTML = `
        <div>
            <label class="form-label">Supply Item</label>
            <select name="packaging[${packagingRowCount}][supply_id]" class="form-control" required>
                <option value="">-- Select --</option>
                ${packagingData.map(pkg => `<option value="${pkg.supply_id}">${pkg.item_name} (Stock: ${pkg.current_stock} ${pkg.unit})</option>`).join('')}
            </select>
        </div>
        <div>
            <label class="form-label">Quantity</label>
            <input type="number" name="packaging[${packagingRowCount}][quantity]" class="form-control" step="0.01" min="0.01" value="1" required>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
    packagingRowCount++;
}

function addEditIngredientRow(ingredientId = '', quantity = '', unit = '') {
    const container = document.getElementById('editIngredientsContainer');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    row.innerHTML = `
        <div>
            <select name="edit_ingredients[${editIngredientRowCount}][ingredient_id]" class="form-control" required>
                <option value="">-- Select --</option>
                ${ingredientsData.map(ing => `<option value="${ing.ingredient_id}" data-unit="${ing.unit}" ${ing.ingredient_id == ingredientId ? 'selected' : ''}>${ing.ingredient_name} (Stock: ${ing.current_stock} ${ing.unit})</option>`).join('')}
            </select>
        </div>
        <div>
            <input type="number" name="edit_ingredients[${editIngredientRowCount}][quantity]" class="form-control" step="0.01" min="0.01" value="${quantity}" required>
        </div>
        <div>
            <input type="text" name="edit_ingredients[${editIngredientRowCount}][unit]" class="form-control" value="${unit}" readonly>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
    
    // Auto-fill unit
    const select = row.querySelector('select');
    const unitInput = row.querySelector('input[type="text"]');
    select.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        unitInput.value = option.dataset.unit || '';
    });
    
    editIngredientRowCount++;
}

function addEditPackagingRow(supplyId = '', quantity = '') {
    const container = document.getElementById('editPackagingContainer');
    const row = document.createElement('div');
    row.className = 'packaging-row';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    row.innerHTML = `
        <div>
            <select name="edit_packaging[${editPackagingRowCount}][supply_id]" class="form-control" required>
                <option value="">-- Select --</option>
                ${packagingData.map(pkg => `<option value="${pkg.supply_id}" ${pkg.supply_id == supplyId ? 'selected' : ''}>${pkg.item_name} (Stock: ${pkg.current_stock} ${pkg.unit})</option>`).join('')}
            </select>
        </div>
        <div>
            <input type="number" name="edit_packaging[${editPackagingRowCount}][quantity]" class="form-control" step="0.01" min="0.01" value="${quantity}" required>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
    editPackagingRowCount++;
}
</script>

<?php include '../components/layout-end.php'; ?>