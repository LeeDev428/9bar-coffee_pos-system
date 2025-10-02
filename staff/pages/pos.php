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
.pos-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 20px;
    height: calc(100vh - 150px);
}

.products-section {
    background: #fffdfa;
    border-radius: 8px;
    padding: 20px;
    overflow-y: auto;
}

/* Header, search and category filter styles */
.pos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #3b2f2b;
}

.search-bar {
    width: 100%;
    max-width: 420px;
    padding: 10px 14px;
    border: 2px solid #ddd;
    border-radius: 25px;
    font-size: 14px;
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
    border: 1px solid #ddd;
    border-radius: 20px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.15s;
}

.category-btn:hover,
.category-btn.active {
    background: #3b2f2b;
    color: white;
    border-color: #3b2f2b;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.product-card {
    background: #fff;
    border-radius: 8px;
    padding: 14px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    border: 1px solid rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.product-card .product-name { margin-top: 8px; }
.product-card .product-price { margin-top: 6px; font-weight:700; }


.product-card:hover {
    transform: translateY(-2px);
    border-color: #3b2f2b;
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #c79a6e;
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
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
    background: rgba(0,0,0,0.4);
    align-items: center;
    justify-content: center;
}

.product-modal {
    background: #3b2f2b;
    color: white;
    padding: 20px 18px;
    border-radius: 10px;
    max-width: 360px;
    width: 92vw;
    position: relative;
}

.product-modal .modal-image {
    text-align: center;
    margin-bottom: 12px;
}

.product-modal h2 { color: #fff; margin-bottom:6px; }
.product-modal .price { color: #c79a6e; font-weight:700; margin-bottom:8px; }
.product-modal .desc { color: rgba(255,255,255,0.85); margin-bottom:14px; }

/* Compact cart item rows to match original design */
.cart-item { padding: 8px 0; }
.cart-item .btn { padding: 6px 8px; font-size: 13px; }
.cart-item img { border-radius: 6px; }

.product-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #3b2f2b;
}

.product-price {
    font-size: 14px;
    font-weight: 700;
    color: #3b2f2b;
}

.transaction-panel {
    background: #3b2f2b;
    border-radius: 8px;
    padding: 20px;
    color: white;
    display: flex;
    flex-direction: column;
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
    background: rgba(199,154,110,0.6);
    border-radius: 3px;
}

.cart-items::-webkit-scrollbar-thumb:hover {
    background: rgba(199,154,110,0.8);
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
    border: none;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 16px;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn {
    padding: 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-primary {
    background: #c79a6e;
    color: white;
}

.process-btn {
    background: #4aa76b;
    color: white;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.low-stock-alert {
    background: #e74c3c;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
    text-align: center;
    font-size: 12px;
}

.addon-btn:hover {
    background: #c79a6e !important;
    color: white !important;
    transform: translateY(-1px);
}
</style>

<div class="pos-container">
    <!-- Products Section -->
    <div class="products-section">
        <div class="pos-header">
            <div class="cashier-info">
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div style="font-size: 12px; color: #7f8c8d;">Cashier</div>
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
                ?>
                <div class="product-card" data-id="<?php echo $dataId; ?>" data-name="<?php echo $dataName; ?>" data-price="<?php echo $dataPrice; ?>" data-image="<?php echo $dataImage; ?>" data-description="<?php echo $dataDesc; ?>" onclick="showProductDetails(this)">
                    <div class="product-image">
                        <?php if ($imgSrc): ?>
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:50%;border:1px solid #eee;" />
                        <?php else: ?>
                            <i class="fas fa-coffee"></i>
                        <?php endif; ?>
                    </div>
                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                </div>
            <?php endforeach; ?>
<!-- Product Details Modal -->
<div id="productDetailsModal" style="display:none;position:fixed;z-index:2000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
    <div style="background:white;padding:30px 20px 20px 20px;border-radius:10px;max-width:350px;width:90vw;position:relative;box-shadow:0 4px 24px rgba(0,0,0,0.15);">
        <span onclick="closeProductDetailsModal()" style="position:absolute;top:10px;right:18px;font-size:28px;cursor:pointer;">&times;</span>
        <div id="modalProductImage" style="text-align:center;margin-bottom:15px;"></div>
        <h2 id="modalProductName" style="margin:0 0 10px 0;font-size:22px;color:#3b2f2b;"></h2>
        <div id="modalProductPrice" style="font-size:18px;font-weight:600;color:#c79a6e;margin-bottom:10px;"></div>
        <div id="modalProductDesc" style="font-size:14px;color:#555;margin-bottom:18px;"></div>
        <button class="btn btn-primary" style="width:100%;" onclick="addModalProductToCart()">Add to Cart</button>
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

        <!-- Add-ons Section -->
        <div class="addons-section" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: rgba(255,255,255,0.9);">Add-ons</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <button class="addon-btn" onclick="addAddon('Espresso Shot', 35)" style="padding: 4px 8px; background: rgba(199,154,110,0.2); color: #c79a6e; border: 1px solid #c79a6e; border-radius: 12px; font-size: 10px; cursor: pointer; transition: all 0.2s;">
                    Espresso Shot +₱35
                </button>
                <button class="addon-btn" onclick="addAddon('Whipped Cream', 25)" style="padding: 4px 8px; background: rgba(199,154,110,0.2); color: #c79a6e; border: 1px solid #c79a6e; border-radius: 12px; font-size: 10px; cursor: pointer; transition: all 0.2s;">
                    Whipped Cream +₱25
                </button>
                <button class="addon-btn" onclick="addAddon('Flavored Syrup', 20)" style="padding: 4px 8px; background: rgba(199,154,110,0.2); color: #c79a6e; border: 1px solid #c79a6e; border-radius: 12px; font-size: 10px; cursor: pointer; transition: all 0.2s;">
                    Flavored Syrup +₱20
                </button>
                <button class="addon-btn" onclick="addAddon('Coffee Jelly', 20)" style="padding: 4px 8px; background: rgba(199,154,110,0.2); color: #c79a6e; border: 1px solid #c79a6e; border-radius: 12px; font-size: 10px; cursor: pointer; transition: all 0.2s;">
                    Coffee Jelly +₱30
                </button>
                <button class="addon-btn" onclick="addAddon('Nata de Coco', 15)" style="padding: 4px 8px; background: rgba(199,154,110,0.2); color: #c79a6e; border: 1px solid #c79a6e; border-radius: 12px; font-size: 10px; cursor: pointer; transition: all 0.2s;">
                    Nata de Coco +₱15
                </button>
            </div>
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
                <select id="paymentMethod" class="search-bar" style="max-width:180px;padding:8px 12px;">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                </select>

                <input type="number" class="amount-input" placeholder="Amount Received" id="receivedAmount" style="flex:1;max-width:260px;">
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
<div id="receiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 20px; max-width: 400px; max-height: 90vh; overflow-y: auto; position: relative;">
        <button onclick="closeReceiptModal()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        
        <div id="receiptContent" style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; color: #333;">
            <!-- Receipt content will be populated here -->
        </div>
        
        <div style="margin-top: 20px; text-align: center; display: flex; gap: 10px; justify-content: center;">
            <button onclick="printReceiptAgain()" style="background: #3b2f2b; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-print"></i> Print Again
            </button>
            <button onclick="closeReceiptModal()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Close
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
        description: cardElem.dataset.description
    };
    try { localStorage.setItem('selectedProduct', JSON.stringify(product)); } catch(e){}
    lastModalProduct = product;
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductPrice').textContent = '₱' + Number(product.price).toFixed(2);
    document.getElementById('modalProductDesc').textContent = product.description || '';
    if (product.image) {
        document.getElementById('modalProductImage').innerHTML = `<img src="${product.image}" alt="${escapeHtml(product.name)}" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #eee;" />`;
    } else {
        document.getElementById('modalProductImage').innerHTML = '<i class="fas fa-coffee" style="font-size:60px;color:#c79a6e;"></i>';
    }
    document.getElementById('productDetailsModal').style.display = 'flex';
}

function closeProductDetailsModal() {
    document.getElementById('productDetailsModal').style.display = 'none';
}

function addModalProductToCart() {
    if (!lastModalProduct) return;
    // ensure image is present; try lastModalProduct.image then lookup from product cards
    const imageSrc = lastModalProduct.image || getProductImageById(lastModalProduct.id) || '';
    const existing = cart.find(i => i.id == lastModalProduct.id && !i.isAddon);
    if (existing) existing.quantity += 1;
    else cart.push({ id: lastModalProduct.id, name: lastModalProduct.name, price: Number(lastModalProduct.price), image: imageSrc, quantity: 1 });
    saveCartToStorage();
    updateCartDisplay();
    closeProductDetailsModal();
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

function addAddon(addonName, addonPrice) {
    // Add addon as a separate cart item
    const existing = cart.find(i => i.name === addonName && i.isAddon === true);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            id: 'addon_' + Date.now(),
            name: addonName,
            price: Number(addonPrice),
            image: '',
            quantity: 1,
            isAddon: true
        });
    }
    saveCartToStorage();
    updateCartDisplay();
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
        // reset payment summary
        updatePaymentSummary();
        return;
    }
    let subtotal = 0;
    cart.forEach((item, idx) => {
        const row = document.createElement('div'); row.className = 'cart-item';
        const left = document.createElement('div'); left.style.display='flex'; left.style.alignItems='center'; left.style.gap='8px'; left.style.flex='1'; left.style.minWidth='0';
        const imgWrap = document.createElement('div'); imgWrap.style.width='36px'; imgWrap.style.height='36px'; imgWrap.style.flexShrink='0';
        
        // Different styling for add-ons
        if (item.isAddon) {
            imgWrap.innerHTML = '<div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(199,154,110,0.3);border-radius:4px;border:1px solid #c79a6e;"><i class="fas fa-plus" style="font-size:12px;color:#c79a6e;"></i></div>';
        } else {
            if (item.image) {
                // Create image element via DOM to avoid nested-quote escaping issues
                const imgEl = document.createElement('img');
                imgEl.src = item.image;
                imgEl.alt = escapeHtml(item.name);
                imgEl.style.width = '36px';
                imgEl.style.height = '36px';
                imgEl.style.objectFit = 'cover';
                imgEl.style.borderRadius = '4px';
                imgEl.style.border = '1px solid #eee';
                imgEl.onerror = function() {
                    try {
                        this.onerror = null;
                        this.style.display = 'none';
                        imgWrap.innerHTML = '<div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(199,154,110,0.2);border-radius:4px;"><i class="fas fa-coffee" style="font-size:16px;color:#c79a6e;"></i></div>';
                    } catch (e) { imgWrap.innerHTML = '' }
                };
                imgWrap.appendChild(imgEl);
            } else {
                imgWrap.innerHTML = '<div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(199,154,110,0.2);border-radius:4px;"><i class="fas fa-coffee" style="font-size:16px;color:#c79a6e;"></i></div>';
            }
        }
        
        const info = document.createElement('div'); info.style.flex='1'; info.style.minWidth='0';
        
        // Different text styling for add-ons
        if (item.isAddon) {
            info.innerHTML = `<div style="font-size:12px;font-weight:600;color:#c79a6e;word-wrap:break-word;line-height:1.1;font-style:italic;">+ ${escapeHtml(item.name)}</div><div style="font-size:11px;color:rgba(199,154,110,0.8);margin-top:1px;">₱${Number(item.price).toFixed(2)}</div>`;
        } else {
            info.innerHTML = `<div style="font-size:12px;font-weight:600;color:#fff;word-wrap:break-word;line-height:1.1;">${escapeHtml(item.name)}</div><div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:1px;">₱${Number(item.price).toFixed(2)}</div>`;
        }
        left.appendChild(imgWrap); left.appendChild(info);
        const right = document.createElement('div'); right.style.display='flex'; right.style.alignItems='center'; right.style.gap='6px'; right.style.flexShrink='0';
        const qty = document.createElement('div'); qty.style.display='flex'; qty.style.alignItems='center'; qty.style.gap='2px'; qty.style.minWidth='80px'; qty.innerHTML = `<button class="btn" style="padding:4px 6px;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:#c79a6e;color:white;border:none;border-radius:3px;font-size:12px;font-weight:600;" onclick="changeQuantity(${idx}, -1)">-</button> <span style="min-width:24px;display:inline-flex;align-items:center;justify-content:center;height:24px;background:rgba(255,255,255,0.1);border-radius:3px;font-weight:600;font-size:12px;">${item.quantity}</span> <button class="btn" style="padding:4px 6px;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:#c79a6e;color:white;border:none;border-radius:3px;font-size:12px;font-weight:600;" onclick="changeQuantity(${idx}, 1)">+</button>`;
        const subtotalText = document.createElement('div'); subtotalText.style.fontWeight='600'; subtotalText.style.minWidth='60px'; subtotalText.style.textAlign='right'; subtotalText.style.fontSize='12px'; subtotalText.textContent = '₱' + (item.price * item.quantity).toFixed(2);
        const removeBtn = document.createElement('button'); removeBtn.className='btn btn-danger'; removeBtn.style.padding='4px 6px'; removeBtn.style.minWidth='55px'; removeBtn.style.height='24px'; removeBtn.style.fontSize='11px'; removeBtn.textContent='Remove'; removeBtn.onclick = function(){ removeFromCart(idx); };
        right.appendChild(qty); right.appendChild(subtotalText); right.appendChild(removeBtn);
        row.appendChild(left); row.appendChild(right); container.appendChild(row);
        subtotal += item.price * item.quantity;
    });
    // No VAT applied here per request. Total is just the subtotal.
    const grand = subtotal;
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    // Clear vat display if it exists
    const vatEl = document.getElementById('vat'); if (vatEl) vatEl.textContent = '';
    document.getElementById('total').textContent = '₱' + grand.toFixed(2);

    // Update payment summary (received and change)
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

function filterProducts(categoryId) { /* implement if needed */ }

function processPayment() { alert('Process payment - implement server-side logic'); }

function processPayment() {
    if (!cart.length) { alert('Cart is empty'); return; }
    const method = document.getElementById('paymentMethod').value;
    const amount = parseFloat(document.getElementById('receivedAmount').value || '0');
    const subtotalText = document.getElementById('subtotal').textContent.replace(/[₱,]/g,'');
    const total = parseFloat(subtotalText) || 0;
    if (!amount || amount < total) {
        alert('Insufficient amount received');
        return;
    }

    const change = Math.max(0, amount - total);

    // Confirm and send to server to record sale and decrement stock
    if (!confirm(`Confirm payment of ₱${total.toFixed(2)} via ${method.toUpperCase()}?\nChange: ₱${change.toFixed(2)}`)) return;

    const payload = {
        cart: cart,
        payment: { amount: amount },
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
            statusMessage = '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;"><i class="fas fa-check-circle"></i> Receipt printed successfully!</div>';
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
            <h2 style="margin: 0; font-size: 18px; font-weight: bold;">9BAR COFFEE</h2>
            <div style="font-size: 11px; margin: 5px 0;">Balamban, Cebu, Philippines</div>
            <div style="font-size: 11px;">(032) 123-4567</div>
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
        const itemTotal = (item.price || 0) * (item.quantity || 1);
        html += `
            <div style="margin-bottom: 8px;">
                <div style="font-weight: bold; font-size: 12px;">${escapeHtml(item.name || 'Unknown Item')}</div>
                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                    <span>${item.quantity || 1} x ₱${(item.price || 0).toFixed(2)}</span>
                    <span>₱${itemTotal.toFixed(2)}</span>
                </div>
            </div>
        `;
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
            <div>Thank you for your business!</div>
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
    
    const formData = new FormData();
    formData.append('sale_id', saleId);
    
    fetch('../api/print-receipt.php', {
        method: 'POST',
        credentials: 'same-origin', // Include cookies/session
        body: formData
    }).then(r => {
        console.log('Print response status:', r.status);
        return r.json();
    }).then(data => {
        console.log('Print response data:', data);
        if (data && data.success) {
            alert('✅ Receipt printed successfully!');
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
    }).catch(err => {
        console.error('Print error', err);
        alert('❌ Print request failed. Please check printer connection and try again.');
    });
}

function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>\"]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]); }); }
</script>

<?php include '../components/layout-end.php'; ?>