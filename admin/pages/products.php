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
                    // Accept empty price/cost_price (store NULL) for products like Coffee where price is set per-cup later
                    $price = (isset($_POST['price']) && trim($_POST['price']) !== '') ? floatval($_POST['price']) : null;
                    $costPrice = (isset($_POST['cost_price']) && trim($_POST['cost_price']) !== '') ? floatval($_POST['cost_price']) : null;

                    $data = [
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'category_id' => intval($_POST['category_id']),
                        'description' => sanitizeInput($_POST['description']),
                        'price' => $price,
                        'cost_price' => $costPrice,
                        'barcode' => sanitizeInput($_POST['barcode']),
                        'current_stock' => intval($_POST['current_stock']),
                        'minimum_stock' => intval($_POST['minimum_stock']),
                        'maximum_stock' => intval($_POST['maximum_stock']),
                        'reorder_level' => intval($_POST['reorder_level'])
                    ];
                    
                    // Handle uploaded image if any
                    if (isset($_FILES['product_image'])) {
                        $fileErr = $_FILES['product_image']['error'];
                        if ($fileErr !== UPLOAD_ERR_NO_FILE) {
                            if ($fileErr !== UPLOAD_ERR_OK) {
                                // Map common upload errors to readable messages
                                $errMsg = 'Image upload error (code ' . $fileErr . ')';
                                switch ($fileErr) {
                                    case UPLOAD_ERR_INI_SIZE:
                                    case UPLOAD_ERR_FORM_SIZE:
                                        $errMsg = 'Uploaded image exceeds the maximum allowed size.';
                                        break;
                                    case UPLOAD_ERR_PARTIAL:
                                        $errMsg = 'Image was only partially uploaded.';
                                        break;
                                    case UPLOAD_ERR_NO_TMP_DIR:
                                        $errMsg = 'Missing temporary folder on server.';
                                        break;
                                    case UPLOAD_ERR_CANT_WRITE:
                                        $errMsg = 'Failed to write uploaded file to disk.';
                                        break;
                                }
                                showAlert('Product image upload failed: ' . $errMsg, 'error');
                            } else {
                                $uploadDir = __DIR__ . '/../../assets/img/products/';
                                if (!is_dir($uploadDir)) {
                                    if (!mkdir($uploadDir, 0755, true)) {
                                        showAlert('Failed to create upload directory for product images.', 'error');
                                    }
                                }

                                $tmpName = $_FILES['product_image']['tmp_name'];
                                $origName = basename($_FILES['product_image']['name']);
                                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                                $allowed = ['jpg','jpeg','png','gif'];
                                if (in_array(strtolower($ext), $allowed)) {
                                    $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/','', pathinfo($origName, PATHINFO_FILENAME));
                                    $newName = $safeName . '-' . time() . '.' . $ext;
                                    $destPath = $uploadDir . $newName;
                                    if (is_uploaded_file($tmpName) && move_uploaded_file($tmpName, $destPath)) {
                                        // store relative path for DB
                                        $data['image_path'] = 'assets/img/products/' . $newName;
                                    } else {
                                        showAlert('Failed to move uploaded image to destination folder.', 'error');
                                    }
                                } else {
                                    showAlert('Unsupported image type. Allowed: jpg, jpeg, png, gif.', 'error');
                                }
                            }
                        }
                    }

                    $productId = $productManager->addProduct($data);
                    showAlert('Product added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding product: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'edit_product':
                try {
                    $productId = intval($_POST['product_id']);
                    // For edits, accept empty price/cost_price as NULL
                    $editPrice = (isset($_POST['price']) && trim($_POST['price']) !== '') ? floatval($_POST['price']) : null;
                    $editCost = (isset($_POST['cost_price']) && trim($_POST['cost_price']) !== '') ? floatval($_POST['cost_price']) : null;

                    $data = [
                        'product_name' => sanitizeInput($_POST['product_name']),
                        'category_id' => intval($_POST['category_id']),
                        'description' => sanitizeInput($_POST['description']),
                        'price' => $editPrice,
                        'cost_price' => $editCost,
                        'barcode' => sanitizeInput($_POST['barcode'])
                    ];
                    
                    $productManager->updateProduct($productId, $data);

                    // Handle image upload for edit (similar reporting as add)
                    if (isset($_FILES['product_image'])) {
                        $fileErr = $_FILES['product_image']['error'];
                        if ($fileErr !== UPLOAD_ERR_NO_FILE) {
                            if ($fileErr !== UPLOAD_ERR_OK) {
                                $errMsg = 'Image upload error (code ' . $fileErr . ')';
                                switch ($fileErr) {
                                    case UPLOAD_ERR_INI_SIZE:
                                    case UPLOAD_ERR_FORM_SIZE:
                                        $errMsg = 'Uploaded image exceeds the maximum allowed size.';
                                        break;
                                    case UPLOAD_ERR_PARTIAL:
                                        $errMsg = 'Image was only partially uploaded.';
                                        break;
                                    case UPLOAD_ERR_NO_TMP_DIR:
                                        $errMsg = 'Missing temporary folder on server.';
                                        break;
                                    case UPLOAD_ERR_CANT_WRITE:
                                        $errMsg = 'Failed to write uploaded file to disk.';
                                        break;
                                }
                                showAlert('Product image upload failed: ' . $errMsg, 'error');
                            } else {
                                $uploadDir = __DIR__ . '/../../assets/img/products/';
                                if (!is_dir($uploadDir)) {
                                    if (!mkdir($uploadDir, 0755, true)) {
                                        showAlert('Failed to create upload directory for product images.', 'error');
                                    }
                                }

                                $tmpName = $_FILES['product_image']['tmp_name'];
                                $origName = basename($_FILES['product_image']['name']);
                                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                                $allowed = ['jpg','jpeg','png','gif'];
                                if (in_array(strtolower($ext), $allowed)) {
                                    $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/','', pathinfo($origName, PATHINFO_FILENAME));
                                    $newName = $safeName . '-' . time() . '.' . $ext;
                                    $destPath = $uploadDir . $newName;
                                    if (is_uploaded_file($tmpName) && move_uploaded_file($tmpName, $destPath)) {
                                        $data['image_path'] = 'assets/img/products/' . $newName;
                                        // update product with image
                                        $productManager->updateProduct($productId, $data);
                                    } else {
                                        showAlert('Failed to move uploaded image to destination folder.', 'error');
                                    }
                                } else {
                                    showAlert('Unsupported image type. Allowed: jpg, jpeg, png, gif.', 'error');
                                }
                            }
                        }
                    }
                    
                    // Update inventory if provided
                    if (isset($_POST['current_stock'])) {
                        $inventoryData = [
                            'current_stock' => intval($_POST['current_stock']),
                            'minimum_stock' => intval($_POST['minimum_stock']),
                            'maximum_stock' => intval($_POST['maximum_stock']),
                            'reorder_level' => intval($_POST['reorder_level'])
                        ];
                        
                        $db->query("UPDATE inventory SET current_stock = ?, minimum_stock = ?, maximum_stock = ?, reorder_level = ? WHERE product_id = ?", [
                            $inventoryData['current_stock'],
                            $inventoryData['minimum_stock'],
                            $inventoryData['maximum_stock'],
                            $inventoryData['reorder_level'],
                            $productId
                        ]);
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
    SELECT p.*, c.category_name, i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.status = 'active'
    ORDER BY c.category_name, p.product_name
");

// Get categories for dropdowns
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");
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

/* Ensure action button text is visible */
.btn-warning, .btn-danger, .btn-success {
    color: white !important;
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

/* Coffee themed button */
.btn-coffee {
    background: #6f4e37; /* coffee brown */
    color: #ffffff;
    border: none;
}
.btn-coffee:hover {
    background: #573826; /* darker on hover */
}

.btn-outline {
    background: transparent;
    border: 1px solid #bdc3c7;
    color: #34495e;
}
.btn-outline.active {
    background: #34495e;
    color: #fff;
    border-color: #34495e;
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
    <div style="display:flex; gap:10px; align-items:center;">
        <div style="display:flex; gap:8px; align-items:center;">
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Product
            </button>
            <!-- Three matching view buttons placed next to Add New Product -->
            <button type="button" class="btn btn-sm btn-outline" id="viewSuppliesBtn" onclick="showSuppliesView()">View Supplies</button>
            <button type="button" class="btn btn-sm btn-outline" id="viewCoffeeBtn" onclick="showCoffeeView()">View Coffee</button>
            <button type="button" class="btn btn-sm btn-outline" id="viewFoodBtn" onclick="showFoodView()">View Food</button>
        </div>
    </div>
</div>

<?php
// Try to fetch supplies (table may not exist yet). We'll catch errors and default to empty.
$supplies = [];
try {
    $supplies = $db->fetchAll("SELECT * FROM supplies ORDER BY date_added DESC LIMIT 500");
} catch (Exception $e) {
    // table likely doesn't exist or query failed — keep $supplies empty
}
// Do NOT fall back to products — supplies should represent supplies only.
?>



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
            <label>Stock Status:</label>
            <select class="form-control" id="stockFilter" onchange="filterProducts()" style="width: 150px;">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="reorder">Need Reorder</option>
                <option value="normal">Normal</option>
            </select>
        </div>
    </div>
</div>

<!-- View Supplies Modal -->
<div id="viewSuppliesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Supplies</h3>
            <span class="close" onclick="closeModal('viewSuppliesModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-responsive">
                <table class="products-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($supplies)): ?>
                            <?php foreach ($supplies as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['product_name'] ?? $s['item_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['size'] ?? $s['variant'] ?? ''); ?></td>
                                    <td><?php echo isset($s['price']) ? '₱' . number_format($s['price'],2) : (isset($s['cost']) ? '₱' . number_format($s['cost'],2) : ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['quantity'] ?? $s['stock'] ?? $s['current_stock'] ?? ''); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning">Edit</button>
                                        <button class="btn btn-sm btn-danger">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;color:#666;padding:20px;">No supplies found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align:right; margin-top:12px;"><button class="btn" onclick="closeModal('viewSuppliesModal')">Close</button></div>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="products-table">
    <div class="table-responsive">
        <table id="productsTable">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Barcode</th>
                    <th>Price</th>
                    <th>Cost</th>
                    <th>Stock</th>
                    <th>Min Stock</th>
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
                        <?php if (!empty($product['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars('../../' . $product['image_path']); ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                        <div style="width:50px;height:50px;background:#f0f0f0;border-radius:4px;display:inline-block;"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                        <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($product['description']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                    <td><?php echo htmlspecialchars($product['barcode'] ?: 'N/A'); ?></td>
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
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                            <i class="fas fa-trash"></i> Delete
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
            <form method="POST" enctype="multipart/form-data" id="addProductForm">
                <input type="hidden" name="action" value="add_product">
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <button type="button" class="btn btn-sm btn-coffee" id="addCoffeeBtn">Add Coffee</button>
                    <button type="button" class="btn btn-sm btn-success" id="addFoodBtn">Add Food</button>
                    <button type="button" class="btn btn-sm btn-outline" id="addSuppliesBtn">Add Supplies</button>
                </div>
                
                
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_id" id="add_category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control" id="add_barcode">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" id="add_description"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Selling Price *</label>
                            <input type="number" name="price" class="form-control" step="0.01" required id="add_price">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price *</label>
                            <input type="number" name="cost_price" class="form-control" step="0.01" required id="add_cost_price">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Initial Stock *</label>
                            <input type="number" name="current_stock" class="form-control" required id="add_current_stock">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Stock *</label>
                            <input type="number" name="minimum_stock" class="form-control" value="5" required id="add_minimum_stock">
                    </div>
                </div>
                
                <!-- Maximum Stock and Reorder Level removed from UI; keep hidden inputs so server still receives values -->
                <input type="hidden" name="maximum_stock" id="add_maximum_stock" value="100">
                <input type="hidden" name="reorder_level" id="add_reorder_level" value="10">

                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="product_image" class="form-control" accept="image/*">
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
                
                <div class="form-row">
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
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="edit_barcode" class="form-control">
                    </div>
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
                        <label class="form-label">Current Stock</label>
                        <input type="number" name="current_stock" id="edit_current_stock" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Stock</label>
                        <input type="number" name="minimum_stock" id="edit_minimum_stock" class="form-control">
                    </div>
                </div>
                
                <!-- Maximum Stock and Reorder Level removed from UI; preserve hidden inputs for server compatibility -->
                <input type="hidden" name="maximum_stock" id="edit_maximum_stock" value="100">
                <input type="hidden" name="reorder_level" id="edit_reorder_level" value="10">

                <div class="form-group">
                    <label class="form-label">Product Image (leave blank to keep existing)</label>
                    <input type="file" name="product_image" class="form-control" accept="image/*">
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

function openViewSupplies(mode = '') {
    // mode: 'coffee', 'food', or '' for all
    const modal = document.getElementById('viewSuppliesModal');
    modal.style.display = 'block';

    // Normalize mode
    mode = String(mode || '').toLowerCase();

    // Define allowed category names for coffee/food matching
    const coffeeCats = ['hot coffee','iced coffee','tea'];
    const foodCats = ['pastries','sandwiches'];

    // Filter rows in the supplies modal table
    const rows = modal.querySelectorAll('tbody tr');
    rows.forEach(row => {
        // skip placeholder 'No supplies found.' row
        if (row.querySelector('td') && row.querySelector('td').colSpan == 9 && row.innerText.trim().toLowerCase().includes('no supplies')) return;
        const catCell = row.cells[1];
        const cat = catCell ? (catCell.innerText || '').trim().toLowerCase() : '';

        let show = true;
        if (mode === 'coffee') show = coffeeCats.includes(cat);
        else if (mode === 'food') show = foodCats.includes(cat);

        row.style.display = show ? '' : 'none';
    });

    // highlight supplies button only (coffee/food control the main grid)
    const vSupplies = document.getElementById('viewSuppliesBtn');
    if (vSupplies) vSupplies.classList.add('active');
}

function openEditModal(product) {
    document.getElementById('edit_product_id').value = product.product_id;
    document.getElementById('edit_product_name').value = product.product_name;
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('edit_barcode').value = product.barcode || '';
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_cost_price').value = product.cost_price;
    document.getElementById('edit_current_stock').value = product.current_stock || 0;
    document.getElementById('edit_minimum_stock').value = product.minimum_stock || 5;
    // set hidden fields for compatibility with server-side inventory updates
    if (document.getElementById('edit_maximum_stock')) document.getElementById('edit_maximum_stock').value = product.maximum_stock ?? 100;
    if (document.getElementById('edit_reorder_level')) document.getElementById('edit_reorder_level').value = product.reorder_level ?? 10;
    
    // If the page is currently in Coffee View, show a minimal edit form (name, category, barcode, image)
    try {
        const viewCoffeeBtn = document.getElementById('viewCoffeeBtn');
        const isCoffeeView = viewCoffeeBtn && viewCoffeeBtn.classList.contains('active');
        const editHideIds = ['edit_price','edit_cost_price','edit_current_stock','edit_minimum_stock','edit_description'];

        if (isCoffeeView) {
            editHideIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const fg = el.closest('.form-group');
                if (fg) fg.style.display = 'none';
                el.required = false;
            });
            // ensure barcode and name and category remain required for coffee edits
            const eb = document.getElementById('edit_barcode'); if (eb) eb.required = true;
            const en = document.getElementById('edit_product_name'); if (en) en.required = true;
            const ec = document.getElementById('edit_category_id'); if (ec) ec.required = true;
        } else {
            // restore full form for Food view
            editHideIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const fg = el.closest('.form-group');
                if (fg) fg.style.display = '';
                // revert required where appropriate
                if (id === 'edit_price' || id === 'edit_cost_price') el.required = true;
            });
            const eb = document.getElementById('edit_barcode'); if (eb) eb.required = false;
        }
    } catch (e) {
        // silent fallback if DOM structure differs
    }

    document.getElementById('editModal').style.display = 'block';
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) el.style.display = 'none';

    // if closing the supplies modal, clear only the supplies button active state
    if (modalId === 'viewSuppliesModal') {
        const vSupplies = document.getElementById('viewSuppliesBtn');
        if (vSupplies) vSupplies.classList.remove('active');
    }
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
    const viewModal = document.getElementById('viewSuppliesModal');
    
    if (event.target === addModal) {
        addModal.style.display = 'none';
    }
    if (event.target === editModal) {
        editModal.style.display = 'none';
    }
    if (event.target === viewModal) {
        viewModal.style.display = 'none';
        // clear active states
        const vSupplies = document.getElementById('viewSuppliesBtn');
        const vCoffee = document.getElementById('viewCoffeeBtn');
        const vFood = document.getElementById('viewFoodBtn');
        if (vSupplies) vSupplies.classList.remove('active');
        if (vCoffee) vCoffee.classList.remove('active');
        if (vFood) vFood.classList.remove('active');
    }
}
</script>
 

<script>
// Add Product modal mode toggles: Coffee (minimal) vs Food (full)
document.addEventListener('DOMContentLoaded', function(){
    const addCoffeeBtn = document.getElementById('addCoffeeBtn');
    const addFoodBtn = document.getElementById('addFoodBtn');
    const form = document.getElementById('addProductForm');

    // category select and saved original options for filtering
    const addCategory = document.getElementById('add_category');
    const originalCategoryOptions = addCategory ? Array.from(addCategory.options).map(o => ({ value: o.value, text: o.text })) : [];

    // allowed category names (lowercase) for each mode
    const coffeeCategoryNames = ['hot coffee', 'iced coffee', 'tea'];
    const foodCategoryNames = ['pastries', 'sandwiches'];

    function filterCategories(allowedNames) {
        if (!addCategory) return;
        // rebuild options: keep a default placeholder
        addCategory.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.text = 'Select Category';
        addCategory.appendChild(defaultOpt);

        originalCategoryOptions.forEach(opt => {
            if (allowedNames.includes(opt.text.trim().toLowerCase())) {
                const o = document.createElement('option');
                o.value = opt.value;
                o.text = opt.text;
                addCategory.appendChild(o);
            }
        });
    }

    // fields to hide for coffee
    const coffeeHide = ['add_price','add_cost_price','add_current_stock','add_minimum_stock','add_description'];

    // fields to hide for supplies (we will show only name, category, price, cost, initial stock, minimum stock)
    const suppliesHide = ['add_barcode','add_description','add_maximum_stock','add_reorder_level'];

    function setModeCoffee() {
        coffeeHide.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.closest('.form-group').style.display = 'none';
            el.required = false;
        });
        // ensure barcode and product name and category and image are required
        document.getElementById('add_barcode').required = true;
        form.querySelector('[name="product_name"]').required = true;
        if (addCategory) addCategory.required = true;
        // show only coffee-related categories
        filterCategories(coffeeCategoryNames);
    }

    function setModeFood() {
        coffeeHide.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.closest('.form-group').style.display = '';
            // revert required to original for these fields
            if (id === 'add_price' || id === 'add_cost_price' || id === 'add_current_stock' || id === 'add_minimum_stock') {
                el.required = true;
            }
        });
        if (addCategory) addCategory.required = true;
        document.getElementById('add_barcode').required = false;
        // show only food-related categories
        filterCategories(foodCategoryNames);
    }

    function setModeSupplies() {
        // first, make sure common required fields are set properly
        // show product name
        const nameField = form.querySelector('[name="product_name"]');
        if (nameField) { nameField.closest('.form-group').style.display = ''; nameField.required = true; }

        // category select must be visible and required
        if (addCategory) { addCategory.closest('.form-group') && (addCategory.closest('.form-group').style.display = ''); addCategory.required = true; }

        // show price, cost, initial stock, minimum stock
        ['add_price','add_cost_price','add_current_stock','add_minimum_stock'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            const fg = el.closest('.form-group'); if (fg) fg.style.display = '';
            el.required = true;
        });

        // hide fields not relevant to supplies: barcode, description, image (image input stays optional)
        ['add_barcode','add_description'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            const fg = el.closest('.form-group'); if (fg) fg.style.display = 'none';
            el.required = false;
        });

        // keep hidden inputs values for server compatibility
        // restrict categories to a conservative supplies set — try to match common supplies category names
        const suppliesCategoryNames = ['supplies','ingredients','inventory','dry goods','paper','condiments'];
        filterCategories(suppliesCategoryNames);
    }

    if (addCoffeeBtn) addCoffeeBtn.addEventListener('click', setModeCoffee);
    if (addFoodBtn) addFoodBtn.addEventListener('click', setModeFood);
    const addSuppliesBtn = document.getElementById('addSuppliesBtn');
    if (addSuppliesBtn) addSuppliesBtn.addEventListener('click', setModeSupplies);

    // default: Food (full form)
    setModeFood();
});
</script>

<script>
// Page-level view toggle: Coffee vs Food vs Supplies (controls the main products grid)
document.addEventListener('DOMContentLoaded', function(){
    const viewCoffeeBtn = document.getElementById('viewCoffeeBtn');
    const viewFoodBtn = document.getElementById('viewFoodBtn');
    const viewSuppliesBtn = document.getElementById('viewSuppliesBtn');
    const productsTable = document.getElementById('productsTable');
    const suppliesModal = document.getElementById('viewSuppliesModal');

    // Backup original products table header and body so we can restore them
    const originalProductsBody = productsTable.querySelector('tbody').innerHTML;
    const originalProductsHead = productsTable.querySelector('thead').innerHTML;

    // column indices visible in coffee view: Image(0), Name(1), Category(2), Barcode(3), Actions(9)
    const coffeeVisible = [0,1,2,3,9];

    function setActiveButton(activeBtn){
        [viewCoffeeBtn, viewFoodBtn, viewSuppliesBtn].forEach(b => b && b.classList.remove('active'));
        if (activeBtn) activeBtn.classList.add('active');
    }

    // Restore original products table body
    function restoreOriginalProducts() {
        productsTable.querySelector('tbody').innerHTML = originalProductsBody;
        // restore original header
        const thead = productsTable.querySelector('thead');
        if (thead) thead.innerHTML = originalProductsHead;
        // restore full categoryFilter visibility
        const catFilter = document.getElementById('categoryFilter');
        if (catFilter) {
            Array.from(catFilter.options).forEach(o => o.style.display = '');
        }
    }

    window.showCoffeeView = function(){
        // restore original table before applying coffee filter
        restoreOriginalProducts();
        setActiveButton(viewCoffeeBtn);
        const ths = productsTable.querySelectorAll('thead th');
        ths.forEach((th, idx) => th.style.display = coffeeVisible.includes(idx) ? '' : 'none');
        productsTable.querySelectorAll('tbody tr').forEach(row => {
            const cat = (row.cells[2] && row.cells[2].innerText.trim().toLowerCase()) || '';
            if (['hot coffee','iced coffee','tea'].includes(cat)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            Array.from(row.cells).forEach((cell, ci) => cell.style.display = coffeeVisible.includes(ci) ? '' : 'none');
        });
        // limit categoryFilter to coffee categories
        const catFilter = document.getElementById('categoryFilter');
        if (catFilter) {
            const opts = Array.from(catFilter.options);
            opts.forEach(o => {
                const txt = (o.text || '').trim().toLowerCase();
                if (o.value === '') { o.style.display = ''; return; }
                o.style.display = ['hot coffee','iced coffee','tea'].includes(txt) ? '' : 'none';
            });
            catFilter.value = '';
        }
    };

    window.showFoodView = function(){
        // restore original table before applying food filter
        restoreOriginalProducts();
        setActiveButton(viewFoodBtn);
        productsTable.querySelectorAll('thead th').forEach(th => th.style.display = '');
        productsTable.querySelectorAll('tbody tr').forEach(row => {
            const cat = (row.cells[2] && row.cells[2].innerText.trim().toLowerCase()) || '';
            if (['pastries','sandwiches'].includes(cat)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            Array.from(row.cells).forEach(cell => cell.style.display = '');
        });
        const catFilter = document.getElementById('categoryFilter');
        if (catFilter) {
            Array.from(catFilter.options).forEach(o => {
                const txt = (o.text || '').trim().toLowerCase();
                if (o.value === '') { o.style.display = ''; return; }
                o.style.display = ['pastries','sandwiches'].includes(txt) ? '' : 'none';
            });
            catFilter.value = '';
        }
    };

    // Show supplies in the main products grid (use the supplies modal rows as source)
    window.showSuppliesView = function(){
        // restore original table (we'll rebuild tbody to supplies layout)
        restoreOriginalProducts();
        setActiveButton(viewSuppliesBtn);

        // Replace table header to supplies columns: Name, Size, Price, Quantity, Actions
        const thead = productsTable.querySelector('thead');
        thead.innerHTML = '<tr><th>Name</th><th>Size</th><th>Price</th><th>Quantity</th><th>Actions</th></tr>';

        // build tbody from supplies modal rows
        const sRows = suppliesModal.querySelectorAll('tbody tr');
        const tbody = productsTable.querySelector('tbody');
        tbody.innerHTML = '';

        // collect categories present in supplies for filtering (if categoryFilter exists)
        const suppliesCategories = new Set();

        sRows.forEach(sRow => {
            // skip placeholder row
            const firstTd = sRow.querySelector('td');
            if (!firstTd) return;
            if (firstTd.colSpan && firstTd.colSpan >= 4 && firstTd.innerText.toLowerCase().includes('no supplies')) return;

            // Map modal columns (Name, Size, Price, Quantity, Actions) if present
            const cols = Array.from(sRow.children).map(td => td.innerText.trim());

            const tr = document.createElement('tr');
            const nameTd = document.createElement('td'); nameTd.innerText = cols[0] || ''; tr.appendChild(nameTd);
            const sizeTd = document.createElement('td'); sizeTd.innerText = cols[1] || ''; tr.appendChild(sizeTd);
            const priceTd = document.createElement('td'); priceTd.innerText = cols[2] || ''; tr.appendChild(priceTd);
            const qtyTd = document.createElement('td'); qtyTd.innerText = cols[3] || ''; tr.appendChild(qtyTd);
            const actTd = document.createElement('td'); actTd.innerHTML = sRow.children[sRow.children.length - 1].innerHTML || ''; tr.appendChild(actTd);

            // remember this category text if present in modal (for filter behavior later)
            const catCell = sRow.querySelector('td:nth-child(2)');
            if (catCell) suppliesCategories.add(catCell.innerText.trim());

            tbody.appendChild(tr);
        });

        // Restrict the top categoryFilter to only the supplies categories (if exists)
        const catFilter = document.getElementById('categoryFilter');
        if (catFilter) {
            Array.from(catFilter.options).forEach(o => {
                const txt = (o.text || '').trim();
                if (o.value === '') { o.style.display = ''; return; }
                o.style.display = suppliesCategories.has(o.text) ? '' : 'none';
            });
            catFilter.value = '';
        }
    };

    // default to Food view
    showFoodView();
});
// Supplies modal helpers: allow resetting filters and closing with Escape
document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('viewSuppliesModal');
    // Add a small 'Show All' control if missing
    const header = modal.querySelector('.modal-header');
    if (header && !modal.querySelector('.show-all-btn')) {
        const btn = document.createElement('button');
        btn.className = 'btn btn-sm show-all-btn';
        btn.style.marginLeft = '8px';
        btn.textContent = 'Show All';
        btn.onclick = function(){ openViewSupplies(''); };
        header.appendChild(btn);
    }

    // Close modal with Escape key
    window.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            if (modal && modal.style.display === 'block') modal.style.display = 'none';
        }
    });
});
</script>

<?php include '../components/layout-end.php'; ?>