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
$productManager = new ProductManager($db);
$salesManager = new SalesManager($db);

// Get all active products for POS
$products = $productManager->getAllProducts();

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_to_cart':
            $productId = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            // Get product details
            $product = $productManager->getProduct($productId);
            if ($product) {
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
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
                
                echo json_encode(['success' => true, 'message' => 'Item added to cart']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
            exit;
            
        case 'remove_from_cart':
            $productId = intval($_POST['product_id']);
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
                echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
            }
            exit;
            
        case 'update_cart':
            $productId = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId]['quantity'] = $quantity;
            }
            
            echo json_encode(['success' => true, 'message' => 'Cart updated']);
            exit;
            
        case 'process_sale':
            $customerName = sanitizeInput($_POST['customer_name'] ?? '');
            $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'cash');
            $receivedAmount = floatval($_POST['received_amount'] ?? 0);
            
            if (empty($_SESSION['cart'])) {
                echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                exit;
            }
            
            // Calculate totals
            $subtotal = 0;
            $items = [];
            
            foreach ($_SESSION['cart'] as $item) {
                $itemTotal = $item['price'] * $item['quantity'];
                $subtotal += $itemTotal;
                
                $items[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $itemTotal,
                    'discount_per_item' => 0
                ];
            }
            
            $taxAmount = $subtotal * 0.12; // 12% VAT
            $total = $subtotal + $taxAmount;
            
            if ($receivedAmount < $total && $paymentMethod === 'cash') {
                echo json_encode(['success' => false, 'message' => 'Insufficient payment amount']);
                exit;
            }
            
            try {
                // Create sale record
                $saleData = [
                    'transaction_number' => generateTransactionNumber(),
                    'user_id' => $user['user_id'],
                    'customer_name' => $customerName,
                    'total_amount' => $total,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'payment_method' => $paymentMethod
                ];
                
                $saleId = $salesManager->createSale($saleData, $items);
                
                $change = $receivedAmount - $total;
                
                // Get printer settings for receipt printing
                $autoPrint = false;
                $printerSettings = [];
                $businessSettings = [];
                
                try {
                    $settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
                    foreach ($settingsResult as $setting) {
                        if (strpos($setting['setting_key'], 'printer_') === 0 || 
                            in_array($setting['setting_key'], ['paper_width', 'character_set', 'enable_cash_drawer', 'print_qr_code', 'auto_print_receipt'])) {
                            $printerSettings[$setting['setting_key']] = $setting['setting_value'];
                        }
                        if (strpos($setting['setting_key'], 'business_') === 0 || 
                            in_array($setting['setting_key'], ['receipt_header', 'receipt_footer', 'tax_rate'])) {
                            $businessSettings[$setting['setting_key']] = $setting['setting_value'];
                        }
                    }
                    
                    $autoPrint = ($printerSettings['auto_print_receipt'] ?? '0') == '1';
                } catch (Exception $e) {
                    error_log("Error getting printer settings: " . $e->getMessage());
                }
                
                // Prepare receipt data
                $receiptData = [
                    'business_name' => $businessSettings['business_name'] ?? '9BAR COFFEE',
                    'business_address' => $businessSettings['business_address'] ?? '',
                    'business_phone' => $businessSettings['business_phone'] ?? '',
                    'sale_id' => $saleId,
                    'transaction_number' => $saleData['transaction_number'],
                    'cashier' => $user['username'],
                    'customer_name' => $customerName,
                    'items' => [],
                    'subtotal' => $subtotal,
                    'tax_rate' => $businessSettings['tax_rate'] ?? '12',
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'amount_paid' => $receivedAmount,
                    'change_amount' => $change,
                    'receipt_header' => $businessSettings['receipt_header'] ?? 'Welcome to 9Bar Coffee!',
                    'receipt_footer' => $businessSettings['receipt_footer'] ?? 'Thank you for your business!'
                ];
                
                // Add items to receipt data
                foreach ($_SESSION['cart'] as $item) {
                    $receiptData['items'][] = [
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['price'] * $item['quantity']
                    ];
                }
                
                // Add QR code if enabled
                if (isset($printerSettings['print_qr_code']) && $printerSettings['print_qr_code'] == '1') {
                    $receiptData['qr_data'] = 'Sale #' . $saleId . ' - ' . date('Y-m-d H:i:s') . ' - Total: P' . number_format($total, 2);
                }
                
                // Auto print if enabled
                $printSuccess = false;
                if ($autoPrint) {
                    try {
                        $printerType = $printerSettings['printer_type'] ?? 'windows';
                        $connectionString = '';
                        
                        switch ($printerType) {
                            case 'network':
                                $ip = $printerSettings['network_printer_ip'] ?? '';
                                $port = $printerSettings['network_printer_port'] ?? '9100';
                                $connectionString = $ip . ':' . $port;
                                break;
                            case 'usb':
                                $connectionString = $printerSettings['usb_printer_path'] ?? 'COM1';
                                break;
                            case 'windows':
                            default:
                                $connectionString = $printerSettings['windows_printer_name'] ?? '';
                                break;
                        }
                        
                        $printer = new ThermalPrinter($printerType, $connectionString);
                        $printSuccess = $printer->printReceipt($receiptData);
                        
                        // Open cash drawer if enabled and payment is cash
                        if ($paymentMethod === 'cash' && 
                            isset($printerSettings['enable_cash_drawer']) && 
                            $printerSettings['enable_cash_drawer'] == '1') {
                            $printer->openDrawer();
                        }
                        
                        $printer->close();
                    } catch (Exception $e) {
                        error_log("Auto print error: " . $e->getMessage());
                        $printSuccess = false;
                    }
                }
                
                // Clear cart
                unset($_SESSION['cart']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Transaction completed successfully',
                    'transaction_number' => $saleData['transaction_number'],
                    'sale_id' => $saleId,
                    'total' => $total,
                    'received' => $receivedAmount,
                    'change' => $change,
                    'auto_printed' => $autoPrint,
                    'print_success' => $printSuccess,
                    'receipt_data' => base64_encode(json_encode($receiptData))
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
            }
            exit;
            
        case 'clear_cart':
            unset($_SESSION['cart']);
            echo json_encode(['success' => true, 'message' => 'Cart cleared']);
            exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: ../../login.php');
    exit();
}

// Get cart items
$cartItems = $_SESSION['cart'] ?? [];
$cartTotal = 0;
$cartQuantity = 0;

foreach ($cartItems as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
    $cartQuantity += $item['quantity'];
}

$taxAmount = $cartTotal * 0.12;
$grandTotal = $cartTotal + $taxAmount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - 9Bar POS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pos-container {
            display: flex;
            height: 100vh;
            background: #2c3e50;
            font-family: Arial, sans-serif;
        }

        .pos-sidebar {
            width: 200px;
            background: #34495e;
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .cashier-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }

        .cashier-avatar {
            width: 60px;
            height: 60px;
            background: #95a5a6;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .pos-nav-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 5px 0;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            transition: background 0.3s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .pos-nav-item:hover, .pos-nav-item.active {
            background: rgba(255,255,255,0.1);
        }

        .pos-nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        .low-stock-alert {
            background: #e74c3c;
            margin: 10px 0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            font-size: 12px;
        }

        .pos-main {
            flex: 1;
            background: #2c3e50;
            padding: 20px;
            overflow-y: auto;
        }

        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: white;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 25px;
            padding: 8px 15px;
            margin-bottom: 20px;
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 5px;
            flex: 1;
        }

        .category-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #95a5a6;
            background: transparent;
            color: #95a5a6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .product-card {
            background: #34495e;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
            color: white;
            border: none;
        }

        .product-card:hover {
            transform: translateY(-2px);
            background: #3c4f66;
        }

        .product-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e67e22, #f39c12);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .product-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .product-price {
            font-size: 11px;
            color: #bdc3c7;
        }

        .pos-cart {
            width: 350px;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            background: #34495e;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .transaction-info {
            padding: 15px;
            background: #ecf0f1;
            border-bottom: 1px solid #bdc3c7;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            max-height: 300px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qty-btn {
            width: 25px;
            height: 25px;
            border: 1px solid #95a5a6;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-summary {
            padding: 15px;
            background: #f8f9fa;
            border-top: 2px solid #34495e;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .payment-section {
            padding: 15px;
            background: #e8f5e8;
        }

        .payment-input {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
        }

        .pay-btn {
            width: 100%;
            padding: 15px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }

        .pay-btn:hover {
            background: #229954;
        }

        .pos-actions {
            padding: 15px;
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #95a5a6;
            background: white;
            cursor: pointer;
            border-radius: 5px;
            font-size: 12px;
        }

        .clear-btn {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="pos-container">
        <!-- Left Sidebar -->
        <div class="pos-sidebar">
            <div class="cashier-info">
                <div class="cashier-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="cashier-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div style="font-size: 12px; opacity: 0.8;">Cashier</div>
            </div>

            <button class="pos-nav-item active" onclick="newTransaction()">
                <i class="fas fa-plus-circle"></i>
                <span>New Transaction</span>
            </button>

            <button class="pos-nav-item" onclick="settlePayment()">
                <i class="fas fa-credit-card"></i>
                <span>Settle Payment</span>
            </button>

            <div class="low-stock-alert">
                <i class="fas fa-exclamation-triangle"></i><br>
                <strong>LOW STOCK<br>ALERT</strong><br>
                <div style="margin: 10px 0; font-size: 11px;">
                    Check inventory<br>
                    Low item list<br>
                    Please notify the admin!
                </div>
                <div style="color: #ffeb3b; font-size: 10px;">
                    Choco Hazelnut Powder - Unit<br>
                    Coffee Jelly Powder - Unit<br>
                    Taro Powder - Unit<br>
                    Strawberry Powder - Unit<br>
                    Green Apple Powder - Unit
                </div>
            </div>

            <button class="pos-nav-item" onclick="window.location.href='?action=logout'" style="margin-top: auto; color: #e74c3c;">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </button>
        </div>

        <!-- Main Product Area -->
        <div class="pos-main">
            <div class="pos-header">
                <h2><i class="fas fa-cash-register"></i> POS</h2>
                <div style="font-size: 14px;">
                    <?php echo date('D, M d, Y h:i:s A'); ?>
                </div>
            </div>

            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search products..." onkeyup="searchProducts()">
            </div>

            <div class="category-filters">
                <button class="filter-btn active" onclick="filterProducts('all')">All</button>
                <?php foreach ($categories as $category): ?>
                <button class="filter-btn" onclick="filterProducts('<?php echo $category['category_id']; ?>')">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="products-grid" id="productsGrid">
                <?php foreach ($products as $product): ?>
                <button class="product-card" data-category="<?php echo $product['category_id']; ?>" 
                        onclick="addToCart(<?php echo $product['product_id']; ?>)">
                    <div class="product-image">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <div class="product-name"><?php echo strtoupper(htmlspecialchars($product['product_name'])); ?></div>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Cart Panel -->
        <div class="pos-cart">
            <div class="cart-header">
                <h3>Transaction</h3>
            </div>

            <div class="transaction-info">
                <div><strong>Transaction No.</strong></div>
                <div id="transactionNo"><?php echo date('Ymd') . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT); ?></div>
                <br>
                <div><strong>No of Item:</strong> <span id="itemCount"><?php echo $cartQuantity; ?></span></div>
                <div><strong>Total:</strong> PHP <span id="cartTotal"><?php echo number_format($grandTotal, 2); ?></span></div>
            </div>

            <div class="cart-items" id="cartItems">
                <?php foreach ($cartItems as $item): ?>
                <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                    <div>
                        <div style="font-weight: bold; font-size: 12px;">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </div>
                        <div style="font-size: 11px; color: #666;">
                            ₱<?php echo number_format($item['price'], 2); ?> each
                        </div>
                    </div>
                    <div class="item-controls">
                        <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">-</button>
                        <span style="margin: 0 8px;"><?php echo $item['quantity']; ?></span>
                        <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">+</button>
                        <button class="qty-btn" onclick="removeFromCart(<?php echo $item['product_id']; ?>)" style="color: red; margin-left: 5px;">×</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>PHP <span id="subtotal"><?php echo number_format($cartTotal, 2); ?></span></span>
                </div>
                <div class="summary-row">
                    <span>VAT (12%):</span>
                    <span>PHP <span id="taxAmount"><?php echo number_format($taxAmount, 2); ?></span></span>
                </div>
                <div class="summary-row" style="font-weight: bold; border-top: 1px solid #ccc; padding-top: 5px;">
                    <span>Total:</span>
                    <span>PHP <span id="grandTotal"><?php echo number_format($grandTotal, 2); ?></span></span>
                </div>
            </div>

            <div class="payment-section">
                <div><strong>Received Amount PHP</strong></div>
                <input type="number" class="payment-input" id="receivedAmount" placeholder="0.00" step="0.01">
                
                <div style="margin: 10px 0;"><strong>Balance Amount PHP</strong></div>
                <input type="text" class="payment-input" id="balanceAmount" readonly style="background: #f0f0f0;">
                
                <select class="payment-input" id="paymentMethod">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="digital_wallet">Digital Wallet</option>
                </select>

                <button class="pay-btn" onclick="processPayment()">
                    <i class="fas fa-check"></i> PROCESS PAYMENT
                </button>
            </div>

            <div class="pos-actions">
                <button class="action-btn" onclick="printReceipt()">
                    <i class="fas fa-print"></i><br>PRINT
                </button>
                <button class="action-btn clear-btn" onclick="clearCart()">
                    <i class="fas fa-trash"></i><br>CLEAR
                </button>
            </div>
        </div>
    </div>

    <script>
        // Calculate balance when received amount changes
        document.getElementById('receivedAmount').addEventListener('input', function() {
            const received = parseFloat(this.value) || 0;
            const total = parseFloat(document.getElementById('grandTotal').textContent.replace(',', ''));
            const balance = received - total;
            document.getElementById('balanceAmount').value = balance.toFixed(2);
        });

        function addToCart(productId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_to_cart&product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function removeFromCart(productId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=remove_from_cart&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                location.reload();
            });
        }

        function updateQuantity(productId, change) {
            const currentQty = parseInt(document.querySelector(`[data-product-id="${productId}"] span`).textContent);
            const newQty = Math.max(0, currentQty + change);
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_cart&product_id=${productId}&quantity=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                location.reload();
            });
        }

        function processPayment() {
            const receivedAmount = parseFloat(document.getElementById('receivedAmount').value) || 0;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const total = parseFloat(document.getElementById('grandTotal').textContent.replace(',', ''));

            if (receivedAmount < total && paymentMethod === 'cash') {
                alert('Insufficient payment amount!');
                return;
            }

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=process_sale&received_amount=${receivedAmount}&payment_method=${paymentMethod}&customer_name=`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = `Transaction completed!\nTransaction: ${data.transaction_number}\nTotal: ₱${data.total.toFixed(2)}\nReceived: ₱${data.received.toFixed(2)}\nChange: ₱${data.change.toFixed(2)}`;
                    
                    if (data.auto_printed && data.print_success) {
                        message += '\n\nReceipt printed automatically!';
                    } else if (data.auto_printed && !data.print_success) {
                        message += '\n\nAuto-print failed. Print manually?';
                    }
                    
                    alert(message);
                    
                    // Show print options if not auto-printed or auto-print failed
                    if (!data.auto_printed || !data.print_success) {
                        if (confirm('Would you like to print a receipt?')) {
                            printReceipt(data.receipt_data);
                        }
                    }
                    
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function clearCart() {
            if (confirm('Clear all items from cart?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=clear_cart'
                })
                .then(response => response.json())
                .then(data => {
                    location.reload();
                });
            }
        }
        
        function printReceipt(receiptData = null, saleId = null) {
            const printBtn = document.querySelector('.print-receipt-btn');
            if (printBtn) {
                const originalText = printBtn.innerHTML;
                printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
                printBtn.disabled = true;
            }
            
            let body = '';
            if (receiptData) {
                body = `receipt_data=${receiptData}`;
            } else if (saleId) {
                body = `sale_id=${saleId}`;
            } else {
                alert('No receipt data available');
                return;
            }
            
            fetch('../api/print-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (printBtn) {
                    printBtn.innerHTML = originalText;
                    printBtn.disabled = false;
                }
                
                if (data.success) {
                    alert('Receipt printed successfully!');
                } else {
                    alert('Print failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                if (printBtn) {
                    printBtn.innerHTML = originalText;
                    printBtn.disabled = false;
                }
                alert('Print error: ' + error.message);
            });
        }

        function newTransaction() {
            clearCart();
        }

        function settlePayment() {
            document.getElementById('receivedAmount').focus();
        }

        function printReceipt() {
            alert('Print functionality would be implemented here');
        }

        function searchProducts() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.querySelector('.product-name').textContent.toLowerCase();
                product.style.display = name.includes(search) ? 'block' : 'none';
            });
        }

        function filterProducts(categoryId) {
            const products = document.querySelectorAll('.product-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            products.forEach(product => {
                if (categoryId === 'all' || product.dataset.category === categoryId) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }
    </script>
<?php include '../components/layout-end.php'; ?>