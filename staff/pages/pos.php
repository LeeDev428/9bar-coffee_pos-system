<?php
// Staff POS (Point of Sale) System
$page_title = 'POINT OF SALE';
include '../components/main-layout.php';
require_once '../../includes/ThermalPrinter.php';

// Initialize managers
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get current user from component system
$user = $currentUser;

// Get all active products for POS
$products = $productManager->getAllProducts();

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Get all active add-ons
$addons = $db->fetchAll("SELECT * FROM addons WHERE status = 'active' ORDER BY addon_name");

// Get packaging supplies for automatic stock deduction
$packagingItems = $db->fetchAll("SELECT * FROM packaging_supplies WHERE status = 'active'");

// Get size options (you can customize these)
$sizeOptions = [
    ['name' => '8oz (Small)', 'value' => '8oz', 'multiplier' => 0.8],
    ['name' => '12oz (Regular)', 'value' => '12oz', 'multiplier' => 1.0],
    ['name' => '16oz (Large)', 'value' => '16oz', 'multiplier' => 1.3],
    ['name' => '22oz (Extra Large)', 'value' => '22oz', 'multiplier' => 1.6]
];

// Handle AJAX requests for POS operations
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_to_cart':
            // Add item to cart logic here
            break;
        case 'process_sale':
            // Process sale logic here
            break;
        case 'print_receipt':
            // Print receipt logic here
            break;
    }
    exit;
}

// Calculate cart totals
$cartTotal = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartTotal += $item['price'] * $item['quantity'];
    }
}

$taxAmount = $cartTotal * 0.12;
$grandTotal = $cartTotal + $taxAmount;
?>

<style>
body {
    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
}

.pos-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 20px;
    height: calc(100vh - 150px);
}

.products-section {
    background: #fffdfa;
    border-radius: 12px;
    padding: 20px;
    overflow-y: auto;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

/* Header, search and category filter styles */
.pos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #3E363F;
}

.search-bar {
    width: 100%;
    max-width: 420px;
    padding: 10px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 14px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.search-bar:focus {
    outline: none;
    border-color: #3E363F;
    box-shadow: 0 0 0 3px rgba(62, 54, 63, 0.15);
}

.categories {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.category-btn {
    padding: 8px 14px;
    background: #f8f8f8;
    border: 2px solid #e0e0e0;
    border-radius: 20px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s;
}

.category-btn:hover,
.category-btn.active {
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    color: white;
    border-color: #3E363F;
    box-shadow: 0 4px 12px rgba(62, 54, 63, 0.2);
    transform: translateY(-1px);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.product-card {
    background: #fff;
    border-radius: 12px;
    padding: 14px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    border: 2px solid rgba(45, 122, 31, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.product-card .product-name { margin-top: 8px; }
.product-card .product-price { margin-top: 6px; font-weight:700; }


.product-card:hover {
    transform: translateY(-3px);
    border-color: #3E363F;
    box-shadow: 0 8px 20px rgba(62, 54, 63, 0.25);
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 4px 12px rgba(62, 54, 63, 0.25);
}

.product-card {
    box-shadow: 0 6px 18px rgba(0,0,0,0.04);
}

.product-image img {
    display: block;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}

/* Modal styled to match transaction panel (dark) */
.product-modal-backdrop {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.6);
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.product-modal {
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    color: white;
    padding: 20px 18px;
    border-radius: 12px;
    max-width: 360px;
    width: 92vw;
    position: relative;
    box-shadow: 0 12px 40px rgba(0,0,0,0.3);
}

.product-modal .modal-image {
    text-align: center;
    margin-bottom: 12px;
}

.product-modal h2 { color: #fff; margin-bottom:6px; }
.product-modal .price { color: #fff; font-weight:700; margin-bottom:8px; }
.product-modal .desc { color: rgba(255,255,255,0.85); margin-bottom:14px; }

/* Compact cart item rows to match original design */
.cart-item { padding: 8px 0; }
.cart-item .btn { padding: 6px 8px; font-size: 13px; }
.cart-item img { border-radius: 6px; }

.product-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #3E363F;
}

.product-price {
    font-size: 14px;
    font-weight: 700;
    color: #3E363F;
}

.transaction-panel {
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.transaction-header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.transaction-number {
    font-size: 12px;
    opacity: 0.8;
}

.cart-items {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 20px;
    max-height: 280px;
    min-height: 120px;
    padding-right: 5px;
}

.cart-items::-webkit-scrollbar {
    width: 6px;
}

.cart-items::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.cart-items::-webkit-scrollbar-thumb {
    background: rgba(62, 54, 63, 0.6);
    border-radius: 3px;
}

.cart-items::-webkit-scrollbar-thumb:hover {
    background: rgba(62, 54, 63, 0.8);
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    min-height: 48px;
}

.totals {
    margin-bottom: 20px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.total-row.grand {
    font-size: 18px;
    font-weight: 700;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.payment-section {
    margin-top: 20px;
}

.amount-input {
    width: 100%;
    padding: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    margin-bottom: 10px;
    font-size: 16px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transition: border-color 0.3s, background 0.3s;
}

.amount-input:focus {
    outline: none;
    border-color: #3E363F;
    background: rgba(255, 255, 255, 0.2);
}

.amount-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn {
    padding: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(62, 54, 63, 0.3);
    transition: all 0.2s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(62, 54, 63, 0.4);
}

.process-btn {
    background: linear-gradient(135deg, #4aa76b 0%, #3d8f5a 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(74, 167, 107, 0.3);
    transition: all 0.2s;
}

.process-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(74, 167, 107, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    transition: all 0.2s;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
}

.low-stock-alert {
    background: #e74c3c;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
    text-align: center;
    font-size: 12px;
}
</style>

<div class="pos-container">
    <!-- Products Section -->
    <div class="products-section">
        <div class="pos-header">
            <div class="cashier-info" style="background: linear-gradient(135deg, #3E363F 0%, #2d2830 100%); padding: 12px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(62, 54, 63, 0.2);">
                <div>
                    <div style="font-weight: 600; color: white; font-size: 14px;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div style="font-size: 11px; color: rgba(255, 255, 255, 0.7);">Cashier</div>
                </div>
            </div>
            <input type="text" class="search-bar" placeholder="Search products..." id="productSearch">
        </div>

        <div class="categories">
            <button class="category-btn active" onclick="filterProducts('all')">All</button>
            <?php foreach ($categories as $category): ?>
                <button class="category-btn" onclick="filterProducts(<?php echo $category['category_id']; ?>)">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product):
                // use relative path from staff/pages to assets folder so images load correctly
                $imgSrc = !empty($product['image']) ? ('../../assets/img/products/' . htmlspecialchars($product['image'])) : '';
                // Emit individual data-* attributes to avoid JSON-in-attribute parsing issues
                $dataId = htmlspecialchars($product['product_id']);
                $dataName = htmlspecialchars($product['product_name']);
                $dataPrice = htmlspecialchars($product['price']);
                $dataImage = htmlspecialchars($imgSrc);
                $dataDesc = htmlspecialchars($product['description'] ?? '');
                $dataCategoryId = htmlspecialchars($product['category_id'] ?? '');
                $dataStock = htmlspecialchars($product['current_stock'] ?? 0);
                ?>
                <div class="product-card" data-id="<?php echo $dataId; ?>" data-name="<?php echo $dataName; ?>" data-price="<?php echo $dataPrice; ?>" data-image="<?php echo $dataImage; ?>" data-description="<?php echo $dataDesc; ?>" data-category-id="<?php echo $dataCategoryId; ?>" data-stock="<?php echo $dataStock; ?>" onclick="showProductDetails(this)">
                    <div class="product-image">
                        <?php if ($imgSrc): ?>
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:50%;border:1px solid #eee;" />
                        <?php else: ?>
                            <i class="fas fa-coffee"></i>
                        <?php endif; ?>
                    </div>
                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                    <div style="margin-top: 5px; font-size: 11px; color: <?php echo ($product['current_stock'] ?? 0) <= 10 ? '#e74c3c' : '#3f9b28'; ?>; font-weight: 600;">
                        <i class="fas fa-box"></i> <?php echo number_format($product['current_stock'] ?? 0); ?> cups
                    </div>
                </div>
            <?php endforeach; ?>
<!-- Product Details Modal -->
<div id="productDetailsModal" style="display:none;position:fixed;z-index:2000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:white;padding:30px 20px 20px 20px;border-radius:12px;max-width:450px;width:90vw;position:relative;box-shadow:0 12px 40px rgba(0,0,0,0.3);max-height:85vh;overflow-y:auto;">
        <span onclick="closeProductDetailsModal()" style="position:absolute;top:10px;right:18px;font-size:28px;cursor:pointer;color:#999;transition:color 0.2s;" onmouseover="this.style.color='#2d7a1f'" onmouseout="this.style.color='#999'">&times;</span>
        <div id="modalProductImage" style="text-align:center;margin-bottom:15px;"></div>
        <h2 id="modalProductName" style="margin:0 0 10px 0;font-size:22px;color:#2d7a1f;font-weight:600;"></h2>
        <div id="modalProductPrice" style="font-size:18px;font-weight:700;color:#3f9b28;margin-bottom:10px;"></div>
        <div id="modalProductStock" style="font-size:13px;font-weight:600;margin-bottom:10px;padding:6px 10px;background:#f8f9fa;border-radius:6px;display:inline-block;"></div>
        <div id="modalProductDesc" style="font-size:14px;color:#555;margin-bottom:15px;line-height:1.5;"></div>
        
        <!-- Ingredient & Packaging Information -->
        <div id="modalProductConnections" style="background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:15px;font-size:12px;">
            <div style="margin-bottom:10px;">
                <strong style="color:#2c3e50;display:flex;align-items:center;gap:5px;margin-bottom:6px;">
                    <i class="fas fa-leaf" style="color:#27ae60;"></i> Ingredients Used:
                </strong>
                <div id="modalIngredientsList" style="color:#7f8c8d;margin-left:20px;line-height:1.6;">Loading...</div>
            </div>
            <div>
                <strong style="color:#2c3e50;display:flex;align-items:center;gap:5px;margin-bottom:6px;">
                    <i class="fas fa-box" style="color:#e67e22;"></i> Packaging & Supplies:
                </strong>
                <div id="modalPackagingList" style="color:#7f8c8d;margin-left:20px;line-height:1.6;">Loading...</div>
            </div>
        </div>
        
        <!-- Quantity selector removed: modal will add 1 item per click -->
        
        <button class="btn btn-primary" style="width:100%;padding:12px;border-radius:8px;font-size:15px;border:none;cursor:pointer;" onclick="addModalProductToCart()">Add to Cart</button>
    </div>
</div>
        </div>
    </div>

    <!-- Transaction Panel -->
    <div class="transaction-panel">
        <div class="transaction-header">
            <h3 style="margin: 0;">Transaction</h3>
            <div class="transaction-number">No. <?php echo date('Ymd') . sprintf('%04d', rand(1, 9999)); ?></div>
        </div>

        <div class="cart-items" id="cartItems">
            <div style="text-align: center; opacity: 0.6; padding: 40px 0;">
                No items in cart
            </div>
        </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span id="subtotal">₱0.00</span>
            </div>
            <!-- Removed VAT row per request -->
            <div class="total-row">
                <span>Received:</span>
                <span id="receivedDisplay">₱0.00</span>
            </div>
            <div class="total-row grand">
                <span>Total:</span>
                <span id="total">₱0.00</span>
            </div>
            <div class="total-row">
                <span>Change:</span>
                <span id="changeDisplay">₱0.00</span>
            </div>
        </div>

        <div class="payment-section">
            <div style="display:flex;gap:10px;margin-bottom:10px;align-items:center;">
                <select id="paymentMethod" class="search-bar" style="max-width:180px;padding:8px 12px;" onchange="toggleGcashReference()">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                </select>

                <input type="number" class="amount-input" placeholder="Amount Received" id="receivedAmount" style="flex:1;max-width:260px;">
            </div>

            <!-- GCash Reference Number (shows only when GCash is selected) -->
            <div id="gcashReferenceSection" style="display:none;margin-bottom:10px;">
                <input type="text" id="gcashReference" class="form-control" placeholder="GCash Reference Number (required for GCash)" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:5px;background:rgba(255,255,255,0.15);color:white;">
            </div>

            <button id="processPaymentBtn" class="btn" style="background:#4aa76b;color:white;padding:12px;border-radius:6px;width:100%;display:flex;align-items:center;gap:10px;justify-content:center;" onclick="processPayment()">
                <i class="fas fa-credit-card"></i> Process Payment
            </button>

            <button id="clearCartBtn" class="btn btn-danger" style="margin-top:8px; width:100%; display:none;" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear
            </button>
        </div>
    </div>
</div>

<!-- Receipt Display Modal -->
<div id="receiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 20px; max-width: 400px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 12px 40px rgba(0,0,0,0.3);">
        <button onclick="closeReceiptModal()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 28px; cursor: pointer; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#2d7a1f'" onmouseout="this.style.color='#999'">&times;</button>
        
        <div id="receiptContent" style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; color: #333;">
            <!-- Receipt content will be populated here -->
        </div>
        
        <div style="margin-top: 20px; text-align: center; display: flex; gap: 10px; justify-content: center;">
            <button onclick="closeReceiptModal()" style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(108, 117, 125, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(108, 117, 125, 0.3)'">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Add-ons Selection Modal -->
<div id="addonsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 25px; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; position: relative; box-shadow: 0 12px 40px rgba(0,0,0,0.3);">
        <button onclick="closeAddonsModal()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 28px; cursor: pointer; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#999'">&times;</button>
        
        <h3 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 20px;">Customize Your Order</h3>
        <p style="margin: 0 0 20px 0; color: #7f8c8d; font-size: 14px;">Selected: <strong id="selectedProductName"></strong></p>
        
        <!-- Add-ons Selection -->
        <div style="margin-bottom: 20px;">
            <h4 style="margin: 0 0 12px 0; color: #34495e; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus-circle" style="color: #3498db;"></i> Add-ons (Optional)
            </h4>
            <div id="addonsList" style="display: grid; gap: 10px;">
                <?php foreach ($addons as $addon): ?>
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3498db'; this.style.background='#e3f2fd'" onmouseout="if(!this.querySelector('input').checked){ this.style.borderColor='#e9ecef'; this.style.background='#f8f9fa' }">
                    <input type="checkbox" class="addon-checkbox" data-id="<?php echo $addon['addon_id']; ?>" data-name="<?php echo htmlspecialchars($addon['addon_name']); ?>" data-price="<?php echo isset($addon['cost_per_unit']) ? $addon['cost_per_unit'] : 0; ?>" data-stock="<?php echo $addon['current_stock']; ?>" style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleAddonQuantity(this)">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 2px;"><?php echo htmlspecialchars($addon['addon_name']); ?></div>
                        <div style="font-size: 12px; color: #7f8c8d;">₱<?php echo number_format(isset($addon['cost_per_unit']) ? $addon['cost_per_unit'] : 0, 2); ?> • Stock: <?php echo $addon['current_stock']; ?> <?php echo $addon['unit']; ?></div>
                    </div>
                    <input type="number" class="addon-quantity" min="1" max="<?php echo $addon['current_stock']; ?>" value="1" style="width: 60px; padding: 6px; border: 1px solid #bdc3c7; border-radius: 4px; text-align: center; display: none;" onclick="event.stopPropagation()">
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Special Notes -->
        <div style="margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0; color: #34495e; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sticky-note" style="color: #f39c12;"></i> Special Instructions
            </h4>
            <textarea id="specialNotes" rows="3" placeholder="Example: No sugar, less ice, extra hot, etc." style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; resize: vertical; font-family: inherit; font-size: 14px;"></textarea>
        </div>
        
        <!-- Quantity Selection -->
        <div style="margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0; color: #34495e; font-size: 16px;">Quantity</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button onclick="adjustModalQuantity(-1)" style="width: 40px; height: 40px; background: #ecf0f1; border: none; border-radius: 8px; cursor: pointer; font-size: 18px; font-weight: bold; transition: all 0.2s;" onmouseover="this.style.background='#bdc3c7'" onmouseout="this.style.background='#ecf0f1'">-</button>
                <input type="number" id="modalQuantity" value="1" min="1" style="width: 80px; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; text-align: center; font-size: 16px; font-weight: 600;">
                <button onclick="adjustModalQuantity(1)" style="width: 40px; height: 40px; background: #ecf0f1; border: none; border-radius: 8px; cursor: pointer; font-size: 18px; font-weight: bold; transition: all 0.2s;" onmouseover="this.style.background='#bdc3c7'" onmouseout="this.style.background='#ecf0f1'">+</button>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 10px;">
            <button onclick="closeAddonsModal()" style="flex: 1; background: #95a5a6; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.2s;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">
                Cancel
            </button>
            <button onclick="confirmAddToCart()" style="flex: 2; background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(39, 174, 96, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(39, 174, 96, 0.3)'">
                <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>
        </div>
    </div>
</div>

<script>

let cart = [];
let lastModalProduct = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCartFromStorage();
    updateCartDisplay();
});

function showProductDetails(cardElem) {
    // read product fields from data-* attributes to avoid JSON parsing issues
    const product = {
        id: cardElem.dataset.id,
        name: cardElem.dataset.name,
        price: cardElem.dataset.price,
        image: cardElem.dataset.image,
        description: cardElem.dataset.description,
        stock: cardElem.dataset.stock || 0
    };
    try { localStorage.setItem('selectedProduct', JSON.stringify(product)); } catch(e){}
    lastModalProduct = product;
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductPrice').textContent = '₱' + Number(product.price).toFixed(2);
    document.getElementById('modalProductDesc').textContent = product.description || '';
    
    // Display stock information
    const stockQty = parseInt(product.stock) || 0;
    const stockColor = stockQty <= 10 ? '#e74c3c' : '#3f9b28';
    document.getElementById('modalProductStock').innerHTML = `<i class="fas fa-box"></i> Available: <strong>${stockQty} cups</strong>`;
    document.getElementById('modalProductStock').style.color = stockColor;
    
    if (product.image) {
        document.getElementById('modalProductImage').innerHTML = `<img src="${product.image}" alt="${escapeHtml(product.name)}" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #eee;" />`;
    } else {
        document.getElementById('modalProductImage').innerHTML = '<i class="fas fa-coffee" style="font-size:60px;color:#3f9b28;"></i>';
    }
    
    // Fetch ingredient and packaging connections
    fetchProductConnections(product.id);
    
    document.getElementById('productDetailsModal').style.display = 'flex';
}

function fetchProductConnections(productId) {
    // Set loading state
    document.getElementById('modalIngredientsList').textContent = 'Loading...';
    document.getElementById('modalPackagingList').textContent = 'Loading...';
    
    fetch(`../api/get-product-details.php?product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            // Display Ingredients
            if (data.ingredients && data.ingredients.length > 0) {
                const ingredientList = data.ingredients.map(ing => {
                    const stockColor = ing.stock < 50 ? '#e74c3c' : '#27ae60';
                    return `<div style="margin-bottom:4px;">
                        • ${ing.name} <span style="color:#3498db;font-weight:600;">${ing.quantity} ${ing.unit}</span> 
                        - <span style="color:${stockColor};font-weight:600;">${ing.stock} ${ing.unit} in stock</span>
                    </div>`;
                }).join('');
                document.getElementById('modalIngredientsList').innerHTML = ingredientList;
            } else {
                document.getElementById('modalIngredientsList').innerHTML = '<span style="color:#95a5a6;font-style:italic;">No ingredients assigned</span>';
            }
            
            // Display Packaging
            if (data.packaging && data.packaging.length > 0) {
                const packagingList = data.packaging.map(pkg => {
                    const stockColor = pkg.stock < 50 ? '#e74c3c' : '#e67e22';
                    return `<div style="margin-bottom:4px;">
                        • ${pkg.name} <span style="color:#3498db;font-weight:600;">${pkg.quantity} ${pkg.unit}</span> 
                        - <span style="color:${stockColor};font-weight:600;">${pkg.stock} ${pkg.unit} in stock</span>
                    </div>`;
                }).join('');
                document.getElementById('modalPackagingList').innerHTML = packagingList;
            } else {
                document.getElementById('modalPackagingList').innerHTML = '<span style="color:#95a5a6;font-style:italic;">No packaging assigned</span>';
            }
        })
        .catch(err => {
            console.error('Failed to fetch product connections:', err);
            document.getElementById('modalIngredientsList').innerHTML = '<span style="color:#e74c3c;">Failed to load</span>';
            document.getElementById('modalPackagingList').innerHTML = '<span style="color:#e74c3c;">Failed to load</span>';
        });
}

function closeProductDetailsModal() {
    document.getElementById('productDetailsModal').style.display = 'none';
    // Modal quantity removed; nothing to reset
}



function addModalProductToCart() {
    if (!lastModalProduct) return;
    closeProductDetailsModal();
    // Open addons modal instead
    openAddonsModal(lastModalProduct);
}

function openAddonsModal(product) {
    lastModalProduct = product;
    document.getElementById('selectedProductName').textContent = product.name;
    document.getElementById('modalQuantity').value = 1;
    document.getElementById('specialNotes').value = '';
    
    // Reset all addon checkboxes and quantities
    document.querySelectorAll('.addon-checkbox').forEach(cb => {
        cb.checked = false;
        const qtyInput = cb.parentElement.querySelector('.addon-quantity');
        if (qtyInput) {
            qtyInput.style.display = 'none';
            qtyInput.value = 1;
        }
        cb.parentElement.style.borderColor = '#e9ecef';
        cb.parentElement.style.background = '#f8f9fa';
    });
    
    document.getElementById('addonsModal').style.display = 'flex';
}

function closeAddonsModal() {
    document.getElementById('addonsModal').style.display = 'none';
}

function toggleAddonQuantity(checkbox) {
    const qtyInput = checkbox.parentElement.querySelector('.addon-quantity');
    const label = checkbox.parentElement;
    if (checkbox.checked) {
        qtyInput.style.display = 'block';
        label.style.borderColor = '#3498db';
        label.style.background = '#e3f2fd';
    } else {
        qtyInput.style.display = 'none';
        label.style.borderColor = '#e9ecef';
        label.style.background = '#f8f9fa';
    }
}

function adjustModalQuantity(delta) {
    const input = document.getElementById('modalQuantity');
    let val = parseInt(input.value) || 1;
    val += delta;
    if (val < 1) val = 1;
    input.value = val;
}

function confirmAddToCart() {
    if (!lastModalProduct) return;
    
    const quantity = parseInt(document.getElementById('modalQuantity').value) || 1;
    const notes = document.getElementById('specialNotes').value.trim();
    
    // Collect selected addons
    const selectedAddons = [];
    document.querySelectorAll('.addon-checkbox:checked').forEach(cb => {
        const qtyInput = cb.parentElement.querySelector('.addon-quantity');
        const addonQty = parseInt(qtyInput.value) || 1;
        const stock = parseInt(cb.dataset.stock) || 0;
        
        if (addonQty > stock) {
            alert(`Insufficient stock for ${cb.dataset.name}. Available: ${stock}`);
            return;
        }
        
        selectedAddons.push({
            id: cb.dataset.id,
            name: cb.dataset.name,
            price: parseFloat(cb.dataset.price),
            quantity: addonQty
        });
    });
    
    // Get product image
    const imageSrc = lastModalProduct.image || getProductImageById(lastModalProduct.id) || '';
    
    // Add main product with addons and notes
    cart.push({ 
        id: lastModalProduct.id,
        name: lastModalProduct.name,
        price: Number(lastModalProduct.price),
        image: imageSrc,
        quantity: quantity,
        addons: selectedAddons,
        notes: notes,
        cartItemId: Date.now() // Unique ID for this cart item
    });
    
    saveCartToStorage();
    updateCartDisplay();
    closeAddonsModal();
}

// Try to find product image from the product-card elements by product id
function getProductImageById(productId) {
    const cards = document.querySelectorAll('.product-card');
    for (const card of cards) {
        const id = card.dataset.id;
        const img = card.dataset.image;
        if (id && id == productId && img) return img;
    }
    return '';
}

function addToCart(productId) {
    const cards = document.querySelectorAll('.product-card');
    for (const card of cards) {
        const id = card.dataset.id;
        if (id == productId) { showProductDetails(card); break; }
    }
}



function removeFromCart(index) {
    if (index < 0 || index >= cart.length) return;
    cart.splice(index, 1);
    saveCartToStorage();
    updateCartDisplay();
}

function changeQuantity(index, delta) {
    if (index < 0 || index >= cart.length) return;
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    saveCartToStorage();
    updateCartDisplay();
}

function updateCartDisplay() {
    const container = document.getElementById('cartItems');
    container.innerHTML = '';
    if (!cart.length) {
        container.innerHTML = '<div style="text-align:center;opacity:0.6;padding:40px 0;">No items in cart</div>';
        document.getElementById('subtotal').textContent = '₱0.00';
        document.getElementById('total').textContent = '₱0.00';
        updatePaymentSummary();
        return;
    }
    
    let subtotal = 0;
    cart.forEach((item, idx) => {
        // Main product row
        const row = document.createElement('div');
        row.className = 'cart-item';
        row.style.paddingBottom = '4px';
        row.style.borderBottom = 'none';
        
        const left = document.createElement('div');
        left.style.display='flex';
        left.style.alignItems='center';
        left.style.gap='8px';
        left.style.flex='1';
        left.style.minWidth='0';
        
        const imgWrap = document.createElement('div');
        imgWrap.style.width='36px';
        imgWrap.style.height='36px';
        imgWrap.style.flexShrink='0';
        
        if (item.image) {
            const imgEl = document.createElement('img');
            imgEl.src = item.image;
            imgEl.alt = escapeHtml(item.name);
            imgEl.style.width = '36px';
            imgEl.style.height = '36px';
            imgEl.style.objectFit = 'cover';
            imgEl.style.borderRadius = '4px';
            imgEl.style.border = '1px solid #eee';
            imgEl.onerror = function() {
                this.onerror = null;
                this.style.display = 'none';
                imgWrap.innerHTML = '<div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(63,155,40,0.2);border-radius:4px;"><i class="fas fa-coffee" style="font-size:16px;color:#3f9b28;"></i></div>';
            };
            imgWrap.appendChild(imgEl);
        } else {
            imgWrap.innerHTML = '<div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(63,155,40,0.2);border-radius:4px;"><i class="fas fa-coffee" style="font-size:16px;color:#3f9b28;"></i></div>';
        }
        
        const info = document.createElement('div');
        info.style.flex='1';
        info.style.minWidth='0';
        
        // Build info HTML
        let infoHTML = `<div style="font-size:12px;font-weight:600;color:#fff;word-wrap:break-word;line-height:1.1;">${escapeHtml(item.name)}</div>`;
        infoHTML += `<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:1px;">₱${Number(item.price).toFixed(2)}</div>`;
        info.innerHTML = infoHTML;
        
        left.appendChild(imgWrap);
        left.appendChild(info);
        
        const right = document.createElement('div');
        right.style.display='flex';
        right.style.alignItems='center';
        right.style.gap='6px';
        right.style.flexShrink='0';
        
        const qty = document.createElement('div');
        qty.style.display='flex';
        qty.style.alignItems='center';
        qty.style.gap='2px';
        qty.style.minWidth='80px';
        qty.innerHTML = `<button class="btn" style="padding:4px 6px;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:#3f9b28;color:white;border:none;border-radius:3px;font-size:12px;font-weight:600;" onclick="changeQuantity(${idx}, -1)">-</button> <span style="min-width:24px;display:inline-flex;align-items:center;justify-content:center;height:24px;background:rgba(255,255,255,0.1);border-radius:3px;font-weight:600;font-size:12px;">${item.quantity}</span> <button class="btn" style="padding:4px 6px;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:#3f9b28;color:white;border:none;border-radius:3px;font-size:12px;font-weight:600;" onclick="changeQuantity(${idx}, 1)">+</button>`;
        
        let itemTotal = item.price * item.quantity;
        
        // Add addon prices to item total
        if (item.addons && item.addons.length > 0) {
            item.addons.forEach(addon => {
                itemTotal += addon.price * addon.quantity * item.quantity;
            });
        }
        
        const subtotalText = document.createElement('div');
        subtotalText.style.fontWeight='600';
        subtotalText.style.minWidth='60px';
        subtotalText.style.textAlign='right';
        subtotalText.style.fontSize='12px';
        subtotalText.textContent = '₱' + itemTotal.toFixed(2);
        
        const removeBtn = document.createElement('button');
        removeBtn.className='btn btn-danger';
        removeBtn.style.padding='4px 6px';
        removeBtn.style.minWidth='55px';
        removeBtn.style.height='24px';
        removeBtn.style.fontSize='11px';
        removeBtn.textContent='Remove';
        removeBtn.onclick = function(){ removeFromCart(idx); };
        
        right.appendChild(qty);
        right.appendChild(subtotalText);
        right.appendChild(removeBtn);
        
        row.appendChild(left);
        row.appendChild(right);
        container.appendChild(row);
        
        subtotal += itemTotal;
        
        // Show add-ons below the main product
        if (item.addons && item.addons.length > 0) {
            item.addons.forEach(addon => {
                const addonRow = document.createElement('div');
                addonRow.style.padding = '4px 8px 4px 50px';
                addonRow.style.display = 'flex';
                addonRow.style.alignItems = 'center';
                addonRow.style.justifyContent = 'space-between';
                addonRow.style.borderBottom = 'none';
                addonRow.innerHTML = `
                    <div style="flex: 1; font-size: 11px; color: rgba(255,255,255,0.8); font-style: italic;">
                        <i class="fas fa-plus" style="font-size: 9px; color: #3f9b28; margin-right: 4px;"></i>
                        ${escapeHtml(addon.name)} (x${addon.quantity})
                    </div>
                    <div style="font-size: 11px; color: rgba(255,255,255,0.7);">
                        +₱${(addon.price * addon.quantity * item.quantity).toFixed(2)}
                    </div>
                `;
                container.appendChild(addonRow);
            });
        }
        
        // Show notes below add-ons
        if (item.notes) {
            const notesRow = document.createElement('div');
            notesRow.style.padding = '4px 8px 8px 50px';
            notesRow.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
            notesRow.style.marginBottom = '8px';
            notesRow.innerHTML = `
                <div style="font-size: 10px; color: rgba(255,255,255,0.6); font-style: italic;">
                    <i class="fas fa-sticky-note" style="font-size: 9px; color: #f39c12; margin-right: 4px;"></i>
                    Note: ${escapeHtml(item.notes)}
                </div>
            `;
            container.appendChild(notesRow);
        } else if (item.addons && item.addons.length > 0) {
            // Add separator if there are addons but no notes
            const separator = document.createElement('div');
            separator.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
            separator.style.marginBottom = '8px';
            separator.style.marginTop = '4px';
            container.appendChild(separator);
        } else {
            // Add separator for regular items
            const separator = document.createElement('div');
            separator.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
            separator.style.marginBottom = '8px';
            separator.style.marginTop = '4px';
            container.appendChild(separator);
        }
    });
    
    const grand = subtotal;
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '₱' + grand.toFixed(2);
    updatePaymentSummary();
}

function saveCartToStorage() { try { localStorage.setItem('posCart', JSON.stringify(cart)); } catch(e){ console.warn('Could not save cart', e); } }
function loadCartFromStorage() { try { const raw = localStorage.getItem('posCart'); cart = raw ? JSON.parse(raw) : []; } catch(e){ cart = []; } }

// Update payment display (received amount and change)
function updatePaymentSummary() {
    const subtotalText = document.getElementById('subtotal').textContent.replace(/[₱,]/g,'') || '0';
    const total = parseFloat(subtotalText) || 0;
    const received = parseFloat(document.getElementById('receivedAmount').value || '0') || 0;
    const receivedDisplay = document.getElementById('receivedDisplay');
    const changeDisplay = document.getElementById('changeDisplay');
    if (receivedDisplay) receivedDisplay.textContent = '₱' + received.toFixed(2);
    if (changeDisplay) changeDisplay.textContent = '₱' + Math.max(0, (received - total)).toFixed(2);
}

// Listen for changes on the received amount to update the summary live
document.addEventListener('input', function(e){
    if (e.target && e.target.id === 'receivedAmount') updatePaymentSummary();
});

function filterProducts(categoryId) {
    const productCards = document.querySelectorAll('.product-card');
    const categoryBtns = document.querySelectorAll('.category-btn');
    
    // Update active category button
    categoryBtns.forEach(btn => btn.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Filter products
    productCards.forEach(card => {
        const cardCategoryId = card.getAttribute('data-category-id');
        
        if (categoryId === 'all' || String(cardCategoryId) === String(categoryId)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Search functionality
const searchBar = document.getElementById('productSearch');
if (searchBar) {
    searchBar.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach(card => {
            const productName = card.getAttribute('data-name').toLowerCase();
            if (productName.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// Toggle GCash reference number field visibility
function toggleGcashReference() {
    const method = document.getElementById('paymentMethod').value;
    const gcashSection = document.getElementById('gcashReferenceSection');
    const receivedAmountInput = document.getElementById('receivedAmount');
    const subtotalText = document.getElementById('subtotal').textContent.replace(/[₱,]/g,'');
    const total = parseFloat(subtotalText) || 0;
    
    if (method === 'gcash') {
        gcashSection.style.display = 'block';
        // Auto-fill the exact amount for GCash (no change needed)
        receivedAmountInput.value = total.toFixed(2);
        receivedAmountInput.readOnly = true;
        receivedAmountInput.style.backgroundColor = '#f0f0f0';
        updatePaymentSummary();
    } else {
        gcashSection.style.display = 'none';
        document.getElementById('gcashReference').value = ''; // Clear when hidden
        receivedAmountInput.value = '';
        receivedAmountInput.readOnly = false;
        receivedAmountInput.style.backgroundColor = '';
        updatePaymentSummary();
    }
}

function processPayment() {
    if (!cart.length) { alert('Cart is empty'); return; }
    const method = document.getElementById('paymentMethod').value;
    const subtotalText = document.getElementById('subtotal').textContent.replace(/[₱,]/g,'');
    const total = parseFloat(subtotalText) || 0;
    let amount = parseFloat(document.getElementById('receivedAmount').value || '0');
    
    // For GCash, automatically set amount to total (exact payment)
    if (method === 'gcash') {
        amount = total;
        const gcashRef = document.getElementById('gcashReference').value.trim();
        if (!gcashRef) {
            alert('Please enter GCash reference number');
            document.getElementById('gcashReference').focus();
            return;
        }
    } else {
        // For Cash, validate amount
        if (!amount || amount < total) {
            alert('Insufficient amount received');
            return;
        }
    }

    const change = Math.max(0, amount - total);

    // Confirm and send to server to record sale and decrement stock
    if (!confirm(`Confirm payment of ₱${total.toFixed(2)} via ${method.toUpperCase()}?\nChange: ₱${change.toFixed(2)}`)) return;

    const payload = {
        cart: cart,
        payment: { 
            amount: amount,
            gcash_reference: method === 'gcash' ? document.getElementById('gcashReference').value.trim() : null
        },
        method: method,
        user_id: <?php echo json_encode($user['user_id'] ?? null); ?>
    };

    // Show loading state
    const processBtn = document.getElementById('processPaymentBtn');
    const originalText = processBtn.innerHTML;
    processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    processBtn.disabled = true;

    fetch('../api/process-sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(r => {
        console.log('Response status:', r.status);
        console.log('Response headers:', r.headers);
        return r.text(); // Get as text first to see what we're getting
    }).then(responseText => {
        console.log('Raw response:', responseText);
        try {
            const data = JSON.parse(responseText);
            console.log('Parsed data:', data);
            
            // Reset button state
            processBtn.innerHTML = originalText;
            processBtn.disabled = false;
            
            if (data && data.success) {
                // Show receipt modal first
                showReceiptModal(payload, data, change);
                
                // Clear cart and reset form
                clearCart();
                document.getElementById('receivedAmount').value = '';
                updatePaymentSummary();
            } else {
                alert('Failed to record sale: ' + (data.message || 'unknown error'));
            }
        } catch (jsonErr) {
            processBtn.innerHTML = originalText;
            processBtn.disabled = false;
            console.error('JSON parse error:', jsonErr);
            console.error('Response was not valid JSON:', responseText);
            alert('Server returned invalid response. Check console for details.');
        }
    }).catch(err => {
        processBtn.innerHTML = originalText;
        processBtn.disabled = false;
        console.error('Sale error', err);
        alert('Server error while recording sale. Please try again.');
    });
}

// Receipt Modal Functions
let currentSaleId = null;

function showReceiptModal(saleData, serverResponse, change) {
    currentSaleId = serverResponse.sale_id;
    const modal = document.getElementById('receiptModal');
    const content = document.getElementById('receiptContent');
    
    // Generate receipt HTML that matches the thermal printer output
    const receiptHtml = generateReceiptHTML(saleData, serverResponse, change);
    content.innerHTML = receiptHtml;
    
    // Show modal with flex display
    modal.style.display = 'flex';
    
    // Show print status message
    let statusMessage = '';
    if (serverResponse.auto_print_enabled) {
        if (serverResponse.print_success) {
            statusMessage = '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;"><i class="fas fa-check-circle"></i> Invoice printed successfully!</div>';
        } else {
            statusMessage = '<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;"><i class="fas fa-exclamation-circle"></i> Print failed - ' + (serverResponse.print_message || 'check printer connection') + '</div>';
        }
    } else {
        statusMessage = '<div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;"><i class="fas fa-info-circle"></i> Auto-print is disabled. Enable it in Admin Settings.</div>';
    }
    
    content.innerHTML = statusMessage + receiptHtml;
}

function generateReceiptHTML(saleData, serverResponse, change) {
    const currentDate = new Date();
    const dateStr = currentDate.toLocaleDateString();
    const timeStr = currentDate.toLocaleTimeString();
    
    // Calculate totals
    let subtotal = 0;
    saleData.cart.forEach(item => {
        subtotal += (item.price || 0) * (item.quantity || 1);
    });
    
    let html = `
        <div style="text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 18px; font-weight: bold;">9BARs COFFEE</h2>
            <div style="font-size: 11px; margin: 5px 0;">99 F.C. Tuazon Street, Pateros, Philippines 1620</div>
            <div style="font-size: 11px;">+63-939-128-8505</div>
        </div>
        
        <div style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Sale #: ${serverResponse.sale_id}</span>
                <span>${dateStr} ${timeStr}</span>
            </div>
            <div style="font-size: 11px;">Cashier: <?php echo htmlspecialchars($user['full_name'] ?? 'Staff'); ?></div>
            <div style="font-size: 11px;">Customer: Walk-in</div>
        </div>
        
        <div style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px;">
    `;
    
    // Add items
    saleData.cart.forEach(item => {
        let itemTotal = (item.price || 0) * (item.quantity || 1);
        
        // Add addon prices to item total
        if (item.addons && item.addons.length > 0) {
            item.addons.forEach(addon => {
                itemTotal += addon.price * addon.quantity * item.quantity;
            });
        }
        
        html += `
            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; font-size: 12px;">${escapeHtml(item.name || 'Unknown Item')}</div>
                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                    <span>${item.quantity || 1} x ₱${(item.price || 0).toFixed(2)}</span>
                    <span>₱${itemTotal.toFixed(2)}</span>
                </div>
        `;
        
        // Show add-ons
        if (item.addons && item.addons.length > 0) {
            item.addons.forEach(addon => {
                html += `
                <div style="display: flex; justify-content: space-between; font-size: 10px; padding-left: 15px; color: #666; font-style: italic;">
                    <span>+ ${escapeHtml(addon.name)} (x${addon.quantity})</span>
                    <span>₱${(addon.price * addon.quantity * item.quantity).toFixed(2)}</span>
                </div>
                `;
            });
        }
        
        // Show notes
        if (item.notes) {
            html += `
                <div style="font-size: 10px; padding-left: 15px; color: #888; font-style: italic; margin-top: 2px;">
                    Note: ${escapeHtml(item.notes)}
                </div>
            `;
        }
        
        html += `</div>`;
    });
    
    html += `
        </div>
        
        <div style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Subtotal:</span>
                <span>₱${subtotal.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px;">
                <span>TOTAL:</span>
                <span>₱${subtotal.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                <span>Payment (${saleData.method.toUpperCase()}):</span>
                <span>₱${(saleData.payment.amount || 0).toFixed(2)}</span>
            </div>
    `;
    
    // Add GCash reference number if applicable
    if (saleData.method === 'gcash' && saleData.payment.gcash_reference) {
        html += `
            <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 11px; color: #555;">
                <span>GCash Ref #:</span>
                <span>${escapeHtml(saleData.payment.gcash_reference)}</span>
            </div>
        `;
    }
    
    if (change > 0) {
        html += `
            <div style="display: flex; justify-content: space-between;">
                <span>Change:</span>
                <span>₱${change.toFixed(2)}</span>
            </div>
        `;
    }
    
    html += `
        </div>
        
        <div style="text-align: center; font-size: 11px; margin-top: 15px;">
            <div>Thank you for visiting 9BARs Coffee! 
</div>
            <div>Please come again!</div>
        </div>
    `;
    
    return html;
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.style.display = 'none';
    currentSaleId = null;
}

function printReceiptAgain() {
    if (!currentSaleId) {
        alert('No sale ID available for printing');
        return;
    }
    printReceiptManually(currentSaleId);
}

function clearCart() { cart = []; saveCartToStorage(); updateCartDisplay(); }

// Manual receipt printing function
function printReceiptManually(saleId) {
    if (!saleId) {
        alert('No sale ID provided for printing');
        return;
    }
    
    // Show loading state
    const printBtn = event.target;
    const originalText = printBtn.innerHTML;
    printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
    printBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('sale_id', saleId);
    
    fetch('../api/print-receipt.php', {
        method: 'POST',
        credentials: 'same-origin', // Include cookies/session
        body: formData
    }).then(r => {
        console.log('Print response status:', r.status);
        console.log('Print response headers:', [...r.headers.entries()]);
        return r.text(); // Get as text first to handle non-JSON responses
    }).then(responseText => {
        console.log('Print raw response:', responseText);
        
        // Reset button state
        if (printBtn) {
            printBtn.innerHTML = originalText;
            printBtn.disabled = false;
        }
        
        try {
            const data = JSON.parse(responseText);
            console.log('Print parsed data:', data);
            
            if (data && data.success) {
                alert('✅ Invoice printed successfully!');
            } else {
                if (data.redirect) {
                    // Authentication issue - redirect to login
                    if (confirm('Session expired. Please login again. Redirect now?')) {
                        window.location.href = data.redirect;
                    }
                } else {
                    alert('❌ Print failed: ' + (data.error || data.message || 'Unknown error'));
                }
            }
        } catch (jsonErr) {
            console.error('JSON parse error:', jsonErr);
            console.error('Response was not valid JSON:', responseText);
            alert('❌ Server returned invalid response. Check console for details.');
        }
    }).catch(err => {
        // Reset button state
        if (printBtn) {
            printBtn.innerHTML = originalText;
            printBtn.disabled = false;
        }
        
        console.error('Print error', err);
        alert('❌ Print request failed. Please check printer connection and try again.');
    });
}

function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>\"]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]); }); }
</script>

<?php include '../components/layout-end.php'; ?>