<?php
// Minimal API endpoint to process a sale from POS
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/database.php';
require_once '../../includes/ProductManager.php';  
require_once '../../includes/SalesManager.php';
require_once '../../includes/ThermalPrinter.php';
require_once '../../includes/auth.php';

// read JSON payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$cart = $input['cart'] ?? null;
$payment = $input['payment'] ?? null;
$method = $input['method'] ?? 'cash';
$userId = $input['user_id'] ?? null;
$gcashReference = ($method === 'gcash' && isset($payment['gcash_reference'])) ? $payment['gcash_reference'] : null;

if (!is_array($cart) || count($cart) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

// basic total calc
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
}

// Insert sale record (best-effort, table names assumed)
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // Insert into sales table
    $stmt = $pdo->prepare("INSERT INTO sales (user_id, total_amount, payment_method, ref_no, sale_date, transaction_number) VALUES (?, ?, ?, ?, NOW(), ?)");
    $transactionNumber = 'TXN-' . date('YmdHis') . '-' . rand(100, 999);
    $stmt->execute([$userId, $subtotal, $method, $gcashReference, $transactionNumber]);
    $saleId = $pdo->lastInsertId();

    // Insert sale items and decrement stock for products and addons
    $productManager = new ProductManager($pdo);

    $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, unit_price, quantity, total_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($cart as $item) {
        $pid = $item['id'] ?? null;
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        $totalPrice = $price * $qty;

        // Only insert actual products with numeric IDs
        if ($pid && preg_match('/^\d+$/', $pid)) {
            // Check if this product is in "Buy 1 Take 1" category
            $categoryCheck = $pdo->prepare("SELECT c.category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.product_id = ?");
            $categoryCheck->execute([$pid]);
            $categoryResult = $categoryCheck->fetch(PDO::FETCH_ASSOC);
            $categoryName = $categoryResult['category_name'] ?? '';
            
            // Determine actual stock quantity to deduct
            // For "Buy 1 Take 1", we deduct 2 cups per quantity ordered
            $stockQty = $qty * 0;
            if (stripos($categoryName, 'B1T1') !== false || stripos($categoryName, 'B1T1') !== false) {
                $stockQty = $qty * 1; // Use 2 cups per order quantity
            }

            // Check current cup stock for this product before committing
            $stockCheck = $pdo->prepare("SELECT i.current_stock, p.product_name FROM inventory i JOIN products p ON i.product_id = p.product_id WHERE i.product_id = ?");
            $stockCheck->execute([$pid]);
            $stockRow = $stockCheck->fetch(PDO::FETCH_ASSOC);
            $currentStock = isset($stockRow['current_stock']) ? (int)$stockRow['current_stock'] : 0;
            $productNameForMsg = $stockRow['product_name'] ?? ('Product ID ' . $pid);

            if ($currentStock < $stockQty) {
                // Not enough cups to fulfill this line item - rollback and return error
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => sprintf('Insufficient cups for "%s". Available: %d cups, Required: %d cups', $productNameForMsg, $currentStock, $stockQty)
                ]);
                exit;
            }

            // Insert sale item with display quantity (what customer ordered)
            $itemStmt->execute([$saleId, $pid, $price, $qty, $totalPrice, $totalPrice]);

            // Manually decrement inventory with the actual stock quantity used
            $updateStock = $pdo->prepare("UPDATE inventory SET current_stock = current_stock - ? WHERE product_id = ?");
            $updateStock->execute([$stockQty, $pid]);
            
            // Decrement ingredients based on product_ingredients table
            $productIngredientsCheck = $pdo->prepare("
                SELECT pi.ingredient_id, pi.quantity_per_unit, pi.unit, i.ingredient_name, i.current_stock 
                FROM product_ingredients pi
                JOIN ingredients i ON pi.ingredient_id = i.ingredient_id
                WHERE pi.product_id = ?
            ");
            $productIngredientsCheck->execute([$pid]);
            $productIngredients = $productIngredientsCheck->fetchAll(PDO::FETCH_ASSOC);
            
            if ($productIngredients && count($productIngredients) > 0) {
                $ingredientStmt = $pdo->prepare("UPDATE ingredients SET current_stock = current_stock - ? WHERE ingredient_id = ?");
                foreach ($productIngredients as $ingredient) {
                    $ingredientId = $ingredient['ingredient_id'];
                    $ingredientQty = (float)$ingredient['quantity_per_unit'];
                    $ingredientName = $ingredient['ingredient_name'];
                    $ingredientStock = (float)$ingredient['current_stock'];
                    
                    // Multiply ingredient quantity by product quantity
                    $totalIngredientQty = $ingredientQty * $qty;
                    
                    if ($ingredientStock < $totalIngredientQty) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => sprintf('Insufficient stock for ingredient "%s". Available: %.2f, Required: %.2f', $ingredientName, $ingredientStock, $totalIngredientQty)
                        ]);
                        exit;
                    }
                    
                    $ingredientStmt->execute([$totalIngredientQty, $ingredientId]);
                }
            }
            
            // Decrement add-ons stock for this product
            if (isset($item['addons']) && is_array($item['addons']) && count($item['addons']) > 0) {
                $addonStmt = $pdo->prepare("UPDATE addons SET current_stock = current_stock - ? WHERE addon_id = ?");
                foreach ($item['addons'] as $addon) {
                    $addonId = $addon['id'] ?? null;
                    $addonQty = (int)($addon['quantity'] ?? 1);
                    // Multiply addon quantity by product quantity
                    $totalAddonQty = $addonQty * $qty;
                    
                    if ($addonId && $totalAddonQty > 0) {
                        // Check addon stock first
                        $addonCheck = $pdo->prepare("SELECT current_stock, addon_name FROM addons WHERE addon_id = ?");
                        $addonCheck->execute([$addonId]);
                        $addonRow = $addonCheck->fetch(PDO::FETCH_ASSOC);
                        $addonStock = isset($addonRow['current_stock']) ? (int)$addonRow['current_stock'] : 0;
                        $addonName = $addonRow['addon_name'] ?? 'Addon';
                        
                        if ($addonStock < $totalAddonQty) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            http_response_code(400);
                            echo json_encode([
                                'success' => false,
                                'message' => sprintf('Insufficient stock for "%s". Available: %d, Required: %d', $addonName, $addonStock, $totalAddonQty)
                            ]);
                            exit;
                        }
                        
                        $addonStmt->execute([$totalAddonQty, $addonId]);
                    }
                }
            }
            
            // Decrement packaging supplies based on product_packaging table
            $productPackagingCheck = $pdo->prepare("
                SELECT pp.supply_id, pp.quantity_per_unit, ps.item_name, ps.current_stock, ps.unit
                FROM product_packaging pp
                JOIN packaging_supplies ps ON pp.supply_id = ps.supply_id
                WHERE pp.product_id = ?
            ");
            $productPackagingCheck->execute([$pid]);
            $productPackaging = $productPackagingCheck->fetchAll(PDO::FETCH_ASSOC);
            
            if ($productPackaging && count($productPackaging) > 0) {
                $packagingUpdateStmt = $pdo->prepare("UPDATE packaging_supplies SET current_stock = current_stock - ? WHERE supply_id = ?");
                foreach ($productPackaging as $packaging) {
                    $supplyId = $packaging['supply_id'];
                    $packagingQty = (float)$packaging['quantity_per_unit'];
                    $packagingName = $packaging['item_name'];
                    $packagingStock = (float)$packaging['current_stock'];
                    
                    // Multiply packaging quantity by product quantity
                    $totalPackagingQty = $packagingQty * $qty;
                    
                    if ($packagingStock < $totalPackagingQty) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => sprintf('Insufficient stock for packaging "%s". Available: %.2f, Required: %.2f', $packagingName, $packagingStock, $totalPackagingQty)
                        ]);
                        exit;
                    }
                    
                    $packagingUpdateStmt->execute([$totalPackagingQty, $supplyId]);
                }
            }
        }
    }

    $pdo->commit();
    
    // Auto-print receipt if enabled
    $printSuccess = false;
    $printMessage = '';
    
    try {
        // Check if auto-print is enabled
        $autoPrintSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'auto_print_receipt'");
        $autoPrint = ($autoPrintSetting && $autoPrintSetting['setting_value'] == '1');
        
        if ($autoPrint) {
            // Get current user for cashier name
            $auth = new Auth($db);
            $currentUser = $auth->getCurrentUser();
            
            // Fallback if no current user in session
            if (!$currentUser && $userId) {
                $userLookup = $db->fetchOne("SELECT user_id, username, full_name, role FROM users WHERE user_id = ? AND status = 'active'", [$userId]);
                if ($userLookup) {
                    $currentUser = $userLookup;
                }
            }
            
            $cashierName = $currentUser ? ($currentUser['full_name'] ?? $currentUser['username'] ?? 'Staff') : 'Unknown';
            
            // Get business settings
            $businessSettings = [];
            $businessResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'business_%' OR setting_key LIKE 'receipt_%'");
            foreach ($businessResult as $setting) {
                $businessSettings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Get printer settings
            $printerSettings = [];
            $printerResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'printer_%' OR setting_key IN ('paper_width', 'character_set', 'enable_cash_drawer', 'print_qr_code', 'windows_printer_name', 'network_printer_ip', 'network_printer_port', 'usb_printer_path')");
            foreach ($printerResult as $setting) {
                $printerSettings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Prepare receipt data
            $receiptData = [
                'business_name' => $businessSettings['business_name'] ?? '9BARs COFFEE',
                'business_address' => $businessSettings['business_address'] ?? '99 F.C. Tuazon Street, Pateros, Philippines 1620',
                'business_phone' => $businessSettings['business_phone'] ?? '09391288505',
                'sale_id' => $saleId,
                'transaction_number' => 'TXN-' . date('Ymd') . '-' . str_pad($saleId, 4, '0', STR_PAD_LEFT),
                'customer_name' => 'Walk-in Customer',
                'items' => [],
                'subtotal' => $subtotal,
                'tax_rate' => '0', // No tax in your current system
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'payment_method' => $method,
                'gcash_reference' => $gcashReference, // GCash reference number
                'amount_paid' => $payment['amount'] ?? $subtotal,
                'change_amount' => max(0, ($payment['amount'] ?? $subtotal) - $subtotal),
                'receipt_header' => $businessSettings['receipt_header'] ?? 'Welcome to 9BARs Coffee!',
                'receipt_footer' => $businessSettings['receipt_footer'] ?? 'Thank you for visiting 9BARs Coffee! 
Please come again!'
            ];
            
            // Add items to receipt
            foreach ($cart as $item) {
                $receiptData['items'][] = [
                    'product_name' => $item['name'] ?? 'Unknown Item',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['price'] ?? 0,
                    'subtotal' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1)
                ];
            }
            
            // Add QR code if enabled
            if (isset($printerSettings['print_qr_code']) && $printerSettings['print_qr_code'] == '1') {
                $receiptData['qr_data'] = 'Sale #' . $saleId . ' - ' . date('Y-m-d H:i:s') . ' - Total: ₱' . number_format($subtotal, 2);
            }
            
            // Initialize printer and print
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
            
            // Note: Cash drawer opening disabled - user doesn't have a cash drawer
            // if ($method === 'cash' && 
            //     isset($printerSettings['enable_cash_drawer']) && 
            //     $printerSettings['enable_cash_drawer'] == '1') {
            //     $printer->openDrawer();
            // }
            
            $printer->close();
            $printMessage = $printSuccess ? 'Receipt printed successfully!' : 'Print failed - check printer connection';
        }
    } catch (Exception $printEx) {
        error_log("Auto-print error: " . $printEx->getMessage());
        $printMessage = 'Print error: ' . $printEx->getMessage();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sale recorded', 
        'sale_id' => $saleId,
        'print_success' => $printSuccess,
        'print_message' => $printMessage,
        'auto_print_enabled' => isset($autoPrint) ? $autoPrint : false
    ]);
    exit;
} catch (Exception $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $ex->getMessage()]);
    exit;
}
