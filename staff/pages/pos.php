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

.pos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #3b2f2b;
}

.cashier-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cashier-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3b2f2b;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.search-bar {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 25px;
    font-size: 14px;
}

.categories {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.category-btn {
    padding: 8px 16px;
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 20px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.category-btn.active,
.category-btn:hover {
    background: #3b2f2b;
    color: white;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.product-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.2s;
    border: 2px solid transparent;
}

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
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
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
</style>

<div class="pos-container">
    <!-- Products Section -->
    <div class="products-section">
        <div class="pos-header">
            <div class="cashier-info">
                <div class="cashier-avatar">
                    <i class="fas fa-user"></i>
                </div>
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
            <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                    <div class="product-image">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                </div>
            <?php endforeach; ?>
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
            <div class="total-row">
                <span>VAT (12%):</span>
                <span id="vat">₱0.00</span>
            </div>
            <div class="total-row grand">
                <span>Total:</span>
                <span id="total">₱0.00</span>
            </div>
        </div>

        <div class="payment-section">
            <input type="number" class="amount-input" placeholder="Received Amount" id="receivedAmount">
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="processPayment()">
                    <i class="fas fa-credit-card"></i> Pay
                </button>
                <button class="btn btn-danger" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Clear
                </button>
            </div>
        </div>

        <div class="low-stock-alert">
            <i class="fas fa-exclamation-triangle"></i><br>
            <strong>LOW STOCK ALERT</strong><br>
            <div style="margin: 10px 0; font-size: 11px;">
                Check inventory<br>
                Please notify the admin!
            </div>
        </div>
    </div>
</div>

<script>
// Cart stored in localStorage under 'pos_cart'
let cart = JSON.parse(localStorage.getItem('pos_cart') || '[]');

// Read product data from the DOM based on productId
function getProductData(productId) {
    const card = document.querySelector('.product-card[data-id="' + productId + '"]');
    if (!card) return null;
    const name = card.querySelector('.product-name').innerText.trim();
    const priceText = card.querySelector('.product-price').innerText.replace(/[^0-9.]/g, '');
    const price = parseFloat(priceText) || 0;
    return { product_id: productId, product_name: name, price };
}

function saveCart() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

function addToCart(productId) {
    const pid = String(productId);
    const product = getProductData(pid);
    if (!product) return;

    // If product exists in cart, increment quantity
    const existing = cart.find(i => String(i.product_id) === pid);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ ...product, quantity: 1 });
    }

    saveCart();
    updateCartDisplay();
}

function removeFromCart(index) {
    if (index < 0 || index >= cart.length) return;
    cart.splice(index, 1);
    saveCart();
    updateCartDisplay();
}

function changeQuantity(index, delta) {
    if (index < 0 || index >= cart.length) return;
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    saveCart();
    updateCartDisplay();
}

function updateCartDisplay() {
    const container = document.getElementById('cartItems');
    container.innerHTML = '';

    if (!cart.length) {
        container.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 40px 0;">No items in cart</div>';
        document.getElementById('subtotal').innerText = '₱0.00';
        document.getElementById('vat').innerText = '₱0.00';
        document.getElementById('total').innerText = '₱0.00';
        return;
    }

    let subtotal = 0;
    cart.forEach((item, idx) => {
        const row = document.createElement('div');
        row.className = 'cart-item';
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;

        row.innerHTML = `
            <div style="flex:1">
                <div style="font-weight:600">${escapeHtml(item.product_name)}</div>
                <div style="font-size:12px; opacity:0.8">₱${numberFormat(item.price)} x ${item.quantity} = ₱${numberFormat(itemTotal)}</div>
            </div>
            <div style="display:flex; gap:8px; align-items:center">
                <button class="btn" onclick="changeQuantity(${idx}, -1)">-</button>
                <button class="btn" onclick="changeQuantity(${idx}, 1)">+</button>
                <button class="btn btn-danger" onclick="removeFromCart(${idx})">Remove</button>
            </div>
        `;

        container.appendChild(row);
    });

    const vat = subtotal * 0.12;
    const total = subtotal + vat;

    document.getElementById('subtotal').innerText = '₱' + numberFormat(subtotal);
    document.getElementById('vat').innerText = '₱' + numberFormat(vat);
    document.getElementById('total').innerText = '₱' + numberFormat(total);
}

function numberFormat(value) {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"'`]/g, function (s) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' })[s];
    });
}

function filterProducts(categoryId) {
    const cards = document.querySelectorAll('.product-card');
    if (categoryId === 'all') {
        cards.forEach(c => c.style.display = 'block');
        return;
    }
    cards.forEach(c => {
        if (c.dataset.category === String(categoryId)) c.style.display = 'block'; else c.style.display = 'none';
    });
}

function processPayment() {
    // Placeholder - actual implementation should POST to server to create sale
    if (!cart.length) { alert('Cart is empty'); return; }
    const receivedText = document.getElementById('receivedAmount').value || '0';
    const received = parseFloat(receivedText) || 0;
    const totalText = document.getElementById('total').innerText.replace(/[^0-9.]/g, '');
    const total = parseFloat(totalText) || 0;
    if (received < total) { alert('Received amount is less than total'); return; }

    // For now just clear cart and show success
    alert('Payment processed. Change: ₱' + numberFormat(received - total));
    clearCart();
}

function clearCart() {
    cart = [];
    saveCart();
    updateCartDisplay();
}

// Attach data-id and data-category attributes to product cards for easier lookup
document.querySelectorAll('.product-card').forEach(function(card){
    const idMatch = card.getAttribute('onclick') && card.getAttribute('onclick').match(/addToCart\((\d+)\)/);
    if (idMatch) card.dataset.id = idMatch[1];
    // If product had category info in dataset, set it; otherwise default to empty
    // Optionally you can add data-category to product-card server-side for better filtering
});

// Initialize UI
updateCartDisplay();
</script>

<?php include '../components/layout-end.php'; ?>