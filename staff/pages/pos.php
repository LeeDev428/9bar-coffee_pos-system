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
            <?php foreach ($products as $product):
                // use relative path from staff/pages to assets folder so images load correctly
                $imgSrc = !empty($product['image']) ? ('../../assets/img/products/' . htmlspecialchars($product['image'])) : '';
                $productData = htmlspecialchars(json_encode([
                    'id' => $product['product_id'],
                    'name' => $product['product_name'],
                    'price' => $product['price'],
                    'image' => $imgSrc,
                    'description' => $product['description'] ?? '',
                ]));
            ?>
                <div class="product-card" data-product='<?php echo $productData; ?>' onclick="showProductDetails(this)">
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

<script>

let cart = [];
let lastModalProduct = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCartFromStorage();
    updateCartDisplay();
});

function showProductDetails(cardElem) {
    const product = JSON.parse(cardElem.getAttribute('data-product'));
    localStorage.setItem('selectedProduct', JSON.stringify(product));
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
    const existing = cart.find(i => i.id == lastModalProduct.id);
    if (existing) existing.quantity += 1;
    else cart.push({ id: lastModalProduct.id, name: lastModalProduct.name, price: Number(lastModalProduct.price), image: lastModalProduct.image || '', quantity: 1 });
    saveCartToStorage();
    updateCartDisplay();
    closeProductDetailsModal();
}

function addToCart(productId) {
    const cards = document.querySelectorAll('.product-card');
    for (const card of cards) {
        const product = JSON.parse(card.getAttribute('data-product'));
        if (product.id == productId) { showProductDetails(card); break; }
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
        document.getElementById('vat').textContent = '₱0.00';
        document.getElementById('total').textContent = '₱0.00';
        return;
    }
    let subtotal = 0;
    cart.forEach((item, idx) => {
        const row = document.createElement('div'); row.className = 'cart-item';
        const left = document.createElement('div'); left.style.display='flex'; left.style.alignItems='center'; left.style.gap='10px';
        const imgWrap = document.createElement('div'); imgWrap.style.width='48px'; imgWrap.style.height='48px'; imgWrap.style.flex='none';
        imgWrap.innerHTML = item.image ? `<img src="${item.image}" alt="${escapeHtml(item.name)}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee;" />` : '<i class="fas fa-coffee" style="font-size:20px;color:#c79a6e;"></i>';
        const info = document.createElement('div'); info.innerHTML = `<div style="font-size:13px;font-weight:600;color:#fff;">${escapeHtml(item.name)}</div><div style="font-size:12px;color:rgba(255,255,255,0.8)">₱${Number(item.price).toFixed(2)}</div>`;
        left.appendChild(imgWrap); left.appendChild(info);
        const right = document.createElement('div'); right.style.display='flex'; right.style.alignItems='center'; right.style.gap='8px';
        const qty = document.createElement('div'); qty.innerHTML = `<button class="btn" style="padding:4px 8px;" onclick="changeQuantity(${idx}, -1)">-</button> <span style="min-width:24px;display:inline-block;text-align:center;">${item.quantity}</span> <button class="btn" style="padding:4px 8px;" onclick="changeQuantity(${idx}, 1)">+</button>`;
        const subtotalText = document.createElement('div'); subtotalText.style.fontWeight='700'; subtotalText.textContent = '₱' + (item.price * item.quantity).toFixed(2);
        const removeBtn = document.createElement('button'); removeBtn.className='btn btn-danger'; removeBtn.style.padding='6px 8px'; removeBtn.textContent='Remove'; removeBtn.onclick = function(){ removeFromCart(idx); };
        right.appendChild(qty); right.appendChild(subtotalText); right.appendChild(removeBtn);
        row.appendChild(left); row.appendChild(right); container.appendChild(row);
        subtotal += item.price * item.quantity;
    });
    const vat = subtotal * 0.12; const grand = subtotal + vat;
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('vat').textContent = '₱' + vat.toFixed(2);
    document.getElementById('total').textContent = '₱' + grand.toFixed(2);
}

function saveCartToStorage() { try { localStorage.setItem('posCart', JSON.stringify(cart)); } catch(e){ console.warn('Could not save cart', e); } }
function loadCartFromStorage() { try { const raw = localStorage.getItem('posCart'); cart = raw ? JSON.parse(raw) : []; } catch(e){ cart = []; } }

function filterProducts(categoryId) { /* implement if needed */ }

function processPayment() { alert('Process payment - implement server-side logic'); }

function processPayment() {
    if (!cart.length) { alert('Cart is empty'); return; }
    const method = document.getElementById('paymentMethod').value;
    const amount = parseFloat(document.getElementById('receivedAmount').value || '0');
    const subtotalText = document.getElementById('subtotal').textContent.replace(/[₱,]/g,'');
    const total = parseFloat(subtotalText) + parseFloat(document.getElementById('vat').textContent.replace(/[₱,]/g,''));
    if (!amount || amount < total) {
        alert('Insufficient amount received');
        return;
    }

    // Minimal confirmation flow - replace with AJAX to server to record sale
    if (confirm(`Confirm payment of ₱${total.toFixed(2)} via ${method.toUpperCase()}?`)) {
        // TODO: send cart and payment info to server
        alert('Payment recorded (demo). Change to AJAX to call backend.');
        clearCart();
        document.getElementById('receivedAmount').value = '';
    }
}

function clearCart() { cart = []; saveCartToStorage(); updateCartDisplay(); }

function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>\"]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]); }); }
</script>

<?php include '../components/layout-end.php'; ?>