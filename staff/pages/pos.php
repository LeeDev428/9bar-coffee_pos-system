<?php
// Staff POS (Point of Sale) System
$page_title = 'POINT OF SALE';
include '../components/main-layout.php';

// Initialize managers
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get all active products for POS
$products = $productManager->getAllProducts();

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name") ?? [];

// Handle AJAX requests
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    // Capture any stray output (notices/warnings) so the client receives valid JSON
    ob_start();

    // Helper to send JSON while including any buffered output (so notices/warnings don't break JSON)
    $send_json = function($data) {
        $buffer = '';
        if (ob_get_length() !== false) {
            $buffer = ob_get_clean();
        }
        if (!empty($buffer)) $data['output'] = $buffer;
        echo json_encode($data);
        exit;
    };

    try {
        switch ($_POST['action']) {
            case 'add_to_cart':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity'] ?? 1);

                $product = $productManager->getProduct($productId);
                if ($product) {
                    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['quantity'] += $quantity;
                    } else {
                        $_SESSION['cart'][$productId] = [
                            'product_id' => $productId,
                            'product_name' => $product['product_name'],
                            'price' => $product['price'],
                            'quantity' => $quantity
                        ];
                    }

                    $send_json(['success' => true, 'message' => 'Item added to cart', 'cart' => $_SESSION['cart']]);
                } else {
                    $send_json(['success' => false, 'message' => 'Product not found']);
                }
                break;

            case 'remove_from_cart':
                $productId = intval($_POST['product_id']);
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                    $send_json(['success' => true, 'message' => 'Item removed from cart', 'cart' => $_SESSION['cart']]);
                } else {
                    $send_json(['success' => false, 'message' => 'Item not found in cart']);
                }
                break;

            case 'update_cart':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);

                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                }

                $send_json(['success' => true, 'message' => 'Cart updated', 'cart' => $_SESSION['cart']]);
                break;

            case 'process_sale':
                $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'cash');
                $receivedAmount = floatval($_POST['received_amount'] ?? 0);

                // Determine cart source: prefer posted cart JSON from client (localStorage), fallback to session cart
                $postedCartRaw = $_POST['cart'] ?? null;
                $cartSource = [];

                if ($postedCartRaw) {
                    $decoded = json_decode($postedCartRaw, true);
                    if (!is_array($decoded) || count($decoded) === 0) {
                        $send_json(['success' => false, 'message' => 'Invalid cart data']);
                    }
                    $cartSource = $decoded;
                } else {
                    if (empty($_SESSION['cart'])) {
                        $send_json(['success' => false, 'message' => 'Cart is empty']);
                    }
                    $cartSource = $_SESSION['cart'];
                }

                // Build authoritative items list using server-side product prices
                $subtotal = 0;
                $items = [];

                foreach ($cartSource as $rawItem) {
                    $productId = intval($rawItem['product_id'] ?? $rawItem['product_id']);
                    $quantity = intval($rawItem['quantity'] ?? $rawItem['quantity']);
                    if ($quantity <= 0) continue;

                    $product = $productManager->getProduct($productId);
                    if (!$product) {
                        $send_json(['success' => false, 'message' => "Product ID {$productId} not found"]);
                    }

                    $price = floatval($product['price']);
                    $itemTotal = $price * $quantity;
                    $subtotal += $itemTotal;

                    $items[] = [
                        'product_id' => $productId,
                        'product_name' => $product['product_name'],
                        'price' => $price,
                        'quantity' => $quantity,
                        'total_price' => $itemTotal
                    ];
                }

                if (empty($items)) {
                    $send_json(['success' => false, 'message' => 'Cart is empty or contains invalid items']);
                }

                $tax = $subtotal * 0.12; // 12% VAT
                $total = $subtotal + $tax;

                if ($paymentMethod === 'cash' && $receivedAmount < $total) {
                    $send_json(['success' => false, 'message' => 'Insufficient payment amount']);
                }

                // Process the sale
                try {
                    // Build sale data expected by SalesManager
                    $saleData = [
                        'transaction_number' => SalesManager::generateTransactionNumber(),
                        'user_id' => $currentUser['user_id'],
                        'customer_name' => 'Walk-in Customer',
                        'total_amount' => $total,
                        'tax_amount' => $tax,
                        'discount_amount' => 0,
                        'payment_method' => $paymentMethod
                    ];

                    // Normalize items shape to what SalesManager expects (unit_price, total_price)
                    $saleItemsForManager = [];
                    foreach ($items as $it) {
                        $saleItemsForManager[] = [
                            'product_id' => $it['product_id'],
                            'quantity' => $it['quantity'],
                            'unit_price' => $it['price'] ?? $it['unit_price'] ?? 0,
                            'total_price' => $it['total_price'] ?? ($it['price'] * $it['quantity']),
                            'discount_per_item' => $it['discount_per_item'] ?? 0
                        ];
                    }

                    $saleId = $salesManager->createSale($saleData, $saleItemsForManager);

                    if ($saleId) {
                        // If session cart was used, clear it. Client is expected to clear localStorage.
                        if (empty($postedCartRaw)) {
                            $_SESSION['cart'] = [];
                        }

                        $send_json([
                            'success' => true,
                            'message' => 'Sale processed successfully',
                            'sale_id' => $saleId,
                            'change' => $paymentMethod === 'cash' ? $receivedAmount - $total : 0
                        ]);
                    } else {
                        $send_json(['success' => false, 'message' => 'Failed to process sale']);
                    }

                } catch (Exception $e) {
                    $send_json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
                break;

            case 'clear_cart':
                $_SESSION['cart'] = [];
                $send_json(['success' => true, 'message' => 'Cart cleared', 'cart' => $_SESSION['cart']]);
                break;
        }
    } catch (Throwable $e) {
        $buffer = ob_get_clean();
        $resp = [
            'success' => false,
            'message' => 'Server error',
            'error' => $e->getMessage(),
        ];
        if (!empty($buffer)) $resp['output'] = $buffer;
        echo json_encode($resp);
        exit;
    }

    // If we reach here, flush any buffer leftover
    if (ob_get_length() > 0) ob_end_clean();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>

<div class="row">
    <!-- Product Grid -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Products</h5>
                <div class="d-flex gap-2">
                    <select id="categoryFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="searchProduct" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
                </div>
            </div>
            <div class="card-body">
                <div class="product-grid">
                    <?php if (empty($products)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <div class="mt-2">No products available</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="product-card" data-category="<?php echo $product['category_id']; ?>">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars('../../' . $product['image_path']); ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:6px;margin-bottom:10px;">
                            <?php else: ?>
                                <div style="width:100%;height:120px;background:#f5f5f5;border-radius:6px;margin-bottom:10px;display:flex;align-items:center;justify-content:center;color:#999;">
                                    <i class="bi bi-image" style="font-size:2rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <h6 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></small>
                            </div>
                            <button class="btn btn-primary btn-sm add-to-cart-btn" 
                                    data-product-id="<?php echo $product['product_id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-price="<?php echo $product['price']; ?>">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cart -->
    <div class="col-lg-4">
        <div class="cart-container">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Current Order</h5>
                    <button id="clearCartBtn" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Clear
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="cartItems" class="cart-items">
                        <!-- Cart items will be populated here -->
                    </div>
                </div>
                <div class="card-footer">
                    <div class="cart-totals">
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span id="subtotalAmount">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tax (12%):</span>
                            <span id="taxAmount">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold border-top pt-2">
                            <span>Total:</span>
                            <span id="totalAmount">₱0.00</span>
                        </div>
                    </div>
                    
                    <!-- Payment Section -->
                    <div class="payment-section mt-3">
                        <div class="row mb-2">
                            <div class="col-6">
                                <select id="paymentMethod" class="form-select form-select-sm">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="number" id="receivedAmount" class="form-control form-control-sm" 
                                       placeholder="Amount Received" step="0.01">
                            </div>
                        </div>
                        
                        <div id="changeAmount" class="mb-2" style="display: none;">
                            <div class="alert alert-info mb-2 p-2">
                                Change: <span id="changeValue">₱0.00</span>
                            </div>
                        </div>
                        
                        <button id="processPaymentBtn" class="btn btn-success w-100" disabled>
                            <i class="bi bi-credit-card"></i> Process Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for POS -->
<style>
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.product-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s;
    background: white;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #2c5282;
}

.product-name {
    margin-bottom: 8px;
    font-weight: 600;
}

.product-price {
    font-size: 1.1rem;
    font-weight: bold;
    color: #28a745;
    margin-bottom: 8px;
}

.cart-container {
    position: sticky;
    top: 20px;
}

.cart-items {
    max-height: 300px;
    overflow-y: auto;
}

.cart-item {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-totals {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.payment-section {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}

@media (max-width: 991px) {
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .cart-container {
        position: static;
        margin-top: 20px;
    }
}
</style>

<!-- POS JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Use localStorage for temporary cart storage on the client
    let cart = {};
    const LOCAL_CART_KEY = 'pos_cart';

    function loadCartFromStorage() {
        try {
            const raw = localStorage.getItem(LOCAL_CART_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') return parsed;
            }
        } catch (e) { console.warn('Failed to load cart from storage', e); }
        return {};
    }

    function saveCartToStorage() {
        try { localStorage.setItem(LOCAL_CART_KEY, JSON.stringify(cart)); } catch (e) { console.warn('Failed to save cart', e); }
    }

    // Initialize cart from localStorage
    cart = loadCartFromStorage();
    
    // DOM Elements
    const cartItemsContainer = document.getElementById('cartItems');
    const subtotalElement = document.getElementById('subtotalAmount');
    const taxElement = document.getElementById('taxAmount');
    const totalElement = document.getElementById('totalAmount');
    const processPaymentBtn = document.getElementById('processPaymentBtn');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const receivedAmountInput = document.getElementById('receivedAmount');
    const changeAmountDiv = document.getElementById('changeAmount');
    const changeValueSpan = document.getElementById('changeValue');
    
    // Add to cart functionality (use event delegation so dynamically filtered items still work)
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.add-to-cart-btn');
        if (!btn) return;
        e.preventDefault();

        const productId = btn.dataset.productId;
        const productName = btn.dataset.productName;
        const price = parseFloat(btn.dataset.price);

        addToCart(productId, productName, price, 1);
    });
    
    // Add to cart function (localStorage-first temporary cart)
    function addToCart(productId, productName, price, quantity) {
        productId = String(productId);
        quantity = parseInt(quantity) || 1;
        price = parseFloat(price) || 0;

        if (!cart[productId]) {
            cart[productId] = { product_id: productId, product_name: productName, price: price, quantity: 0 };
        }
        cart[productId].quantity += quantity;
        saveCartToStorage();
        updateCartDisplay();
        showNotification('Item added to temporary cart', 'success');
    }

    // POST helper that returns parsed JSON or throws an object with .text for debugging
    function postForm(body) {
        const postUrl = window.location.pathname + window.location.search;
        // Return a promise that resolves to a structured result object.
        return fetch(postUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body
        })
        .then(response => response.text().then(text => {
            if (!response.ok) {
                // HTTP error but include raw text for debugging
                return { httpError: true, status: response.status, text };
            }
            try {
                const data = JSON.parse(text || '{}');
                return { httpError: false, parseError: false, data };
            } catch (e) {
                // Return parseError with raw text instead of throwing
                return { httpError: false, parseError: true, text };
            }
        })).catch(err => {
            // Network or fetch-level error
            return { fetchError: true, error: err };
        });
    }
    
    // Update cart display
    function updateCartDisplay() {
        let cartHTML = '';
        let subtotal = 0;
        
        if (Object.keys(cart).length === 0) {
            cartHTML = '<div class="text-center text-muted p-3">Cart is empty</div>';
        } else {
            Object.values(cart).forEach(rawItem => {
                // Normalize item from server (may be associative array)
                const item = {
                    product_id: rawItem['product_id'] || rawItem.product_id,
                    product_name: rawItem['product_name'] || rawItem.product_name,
                    price: parseFloat(rawItem['price'] ?? rawItem.price) || 0,
                    quantity: parseInt(rawItem['quantity'] ?? rawItem.quantity) || 0
                };

                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;

                cartHTML += `
                    <div class="cart-item">
                        <div>
                            <div class="fw-bold">${item.product_name}</div>
                            <small class="text-muted">₱${item.price.toFixed(2)} x ${item.quantity}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold">₱${itemTotal.toFixed(2)}</span>
                            <button class="btn btn-outline-danger btn-sm remove-item" data-product-id="${item.product_id}">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        const tax = subtotal * 0.12;
        const total = subtotal + tax;
        
        cartItemsContainer.innerHTML = cartHTML;
        subtotalElement.textContent = `₱${subtotal.toFixed(2)}`;
        taxElement.textContent = `₱${tax.toFixed(2)}`;
        totalElement.textContent = `₱${total.toFixed(2)}`;
        
        // Update payment button state
        updatePaymentButton();
        
        // Add remove event listeners
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.productId;
                removeFromCart(productId);
            });
        });
    }
    
    // Remove from cart (localStorage)
    function removeFromCart(productId) {
        if (cart[productId]) {
            delete cart[productId];
            saveCartToStorage();
            updateCartDisplay();
            showNotification('Item removed from temporary cart', 'success');
        }
    }
    
    // Clear cart (localStorage)
    clearCartBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = {};
            saveCartToStorage();
            updateCartDisplay();
            showNotification('Temporary cart cleared', 'success');
        }
    });
    
    // Payment method change
    paymentMethodSelect.addEventListener('change', function() {
        updatePaymentButton();
        calculateChange();
    });
    
    receivedAmountInput.addEventListener('input', function() {
        updatePaymentButton();
        calculateChange();
    });
    
    // Update payment button state
    function updatePaymentButton() {
        const cartEmpty = Object.keys(cart).length === 0;
        const paymentMethod = paymentMethodSelect.value;
        const receivedAmount = parseFloat(receivedAmountInput.value) || 0;
        
        let canProcess = !cartEmpty;
        
        if (paymentMethod === 'cash') {
            const total = parseFloat(totalElement.textContent.replace('₱', ''));
            canProcess = canProcess && receivedAmount >= total;
        }
        
        processPaymentBtn.disabled = !canProcess;
    }
    
    // Calculate change
    function calculateChange() {
        const paymentMethod = paymentMethodSelect.value;
        const receivedAmount = parseFloat(receivedAmountInput.value) || 0;
        const total = parseFloat(totalElement.textContent.replace('₱', ''));
        
        if (paymentMethod === 'cash' && receivedAmount > 0) {
            const change = receivedAmount - total;
            changeValueSpan.textContent = `₱${change.toFixed(2)}`;
            changeAmountDiv.style.display = change >= 0 ? 'block' : 'none';
        } else {
            changeAmountDiv.style.display = 'none';
        }
    }
    
    // Process payment
    processPaymentBtn.addEventListener('click', function() {
        const paymentMethod = paymentMethodSelect.value;
        const receivedAmount = parseFloat(receivedAmountInput.value) || 0;

        if (Object.keys(cart).length === 0) {
            showNotification('Cart is empty', 'error');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="spinner-border spinner-border-sm me-1"></i> Processing...';

        // Send the client-side cart to server for processing
        const body = `action=process_sale&payment_method=${encodeURIComponent(paymentMethod)}&received_amount=${encodeURIComponent(receivedAmount)}&cart=${encodeURIComponent(JSON.stringify(cart))}`;
        postForm(body)
        .then(res => {
            if (res.fetchError) throw new Error('Network error while processing sale');
            if (res.httpError) throw new Error('Server error: ' + (res.text || res.status));
            if (res.parseError) throw new Error('Server returned unexpected response');
            const data = res.data || {};
            if (data && data.success) {
                cart = {};
                saveCartToStorage();
                updateCartDisplay();
                receivedAmountInput.value = '';
                changeAmountDiv.style.display = 'none';
                showNotification('Sale completed successfully!', 'success');
                if (data.change && data.change > 0) alert(`Change: ₱${data.change.toFixed(2)}`);
            } else {
                showNotification((data && data.message) || 'Failed to process sale', 'error');
            }
        })
        .catch(err => {
            console.error('Process sale error:', err);
            showNotification(err.message || 'Network error while processing sale', 'error');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-credit-card"></i> Process Payment';
        });
    });
    
    // Product filtering
    const categoryFilter = document.getElementById('categoryFilter');
    const searchInput = document.getElementById('searchProduct');
    
    function filterProducts() {
        const selectedCategory = categoryFilter.value;
        const searchTerm = searchInput.value.toLowerCase();
        
        document.querySelectorAll('.product-card').forEach(card => {
            const category = card.dataset.category;
            const productName = card.querySelector('.product-name').textContent.toLowerCase();
            
            const matchesCategory = !selectedCategory || category === selectedCategory;
            const matchesSearch = !searchTerm || productName.includes(searchTerm);
            
            card.style.display = matchesCategory && matchesSearch ? 'block' : 'none';
        });
    }
    
    categoryFilter.addEventListener('change', filterProducts);
    searchInput.addEventListener('keyup', filterProducts);
    
    // Show notification
    function showNotification(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
    
    // Initial cart display
    updateCartDisplay();
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</div> <!-- End content -->
</div> <!-- End main-content -->
</body>
</html>