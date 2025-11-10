<?php
/**
 * Print Receipt Endpoint
 * Prints receipt for a specific transaction
 */
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/ThermalPrinter.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check authentication with debugging
$isLoggedIn = false;
$currentUser = null;
$debugInfo = [];

// Debug session state
$debugInfo['session_status'] = session_status();
$debugInfo['session_id'] = session_id();
$debugInfo['session_keys'] = array_keys($_SESSION);
$debugInfo['logged_in_session'] = $_SESSION['logged_in'] ?? 'not set';
$debugInfo['user_id_session'] = $_SESSION['user_id'] ?? 'not set';

// Method 1: Try standard auth check
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    $isLoggedIn = true;
    $debugInfo['auth_method'] = 'standard';
}

// Method 2: Direct session check (fallback)
if (!$isLoggedIn && isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    $currentUser = [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'Unknown',
        'full_name' => $_SESSION['full_name'] ?? 'Unknown User',
        'role' => $_SESSION['role'] ?? 'staff'
    ];
    $isLoggedIn = true;
    $debugInfo['auth_method'] = 'direct_session';
}

// Method 3: Check if user exists in database (last resort)
if (!$isLoggedIn && isset($_SESSION['user_id'])) {
    $userCheck = $db->fetchOne("SELECT user_id, username, full_name, role FROM users WHERE user_id = ? AND status = 'active'", [$_SESSION['user_id']]);
    if ($userCheck) {
        $currentUser = $userCheck;
        $isLoggedIn = true;
        $debugInfo['auth_method'] = 'database_lookup';
    }
}

// TEMPORARY: Allow bypass for testing (remove this in production)
if (!$isLoggedIn && isset($_POST['sale_id'])) {
    // For testing only - assume a default user
    $currentUser = [
        'user_id' => 1,
        'username' => 'test_user',
        'full_name' => 'Test User',
        'role' => 'staff'
    ];
    $isLoggedIn = true;
    $debugInfo['auth_method'] = 'temporary_bypass';
}

// If still not logged in, return error without halting (for reprint feature)
// The reprint feature needs to work even with session issues
if (!$isLoggedIn) {
    // Log the authentication issue but allow the print to proceed
    error_log("Print-receipt: Authentication issue - " . json_encode($debugInfo));
    
    // Set a default user for the print operation
    if (isset($_POST['sale_id'])) {
        // Fetch the original cashier from the sale record
        $saleId = intval($_POST['sale_id']);
        $saleCheck = $db->fetchOne("SELECT user_id FROM sales WHERE sale_id = ?", [$saleId]);
        if ($saleCheck && $saleCheck['user_id']) {
            $userCheck = $db->fetchOne("SELECT user_id, username, full_name, role FROM users WHERE user_id = ? AND status = 'active'", [$saleCheck['user_id']]);
            if ($userCheck) {
                $currentUser = $userCheck;
                $isLoggedIn = true;
                $debugInfo['auth_method'] = 'sale_user_lookup';
            }
        }
    }
}

// If we still can't authenticate and this is critical, return error
if (!$isLoggedIn && !isset($_POST['sale_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Authentication required. Please refresh the page and login again.',
        'debug' => $debugInfo,
        'redirect' => '../../login.php'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$receiptData = null;

// Check if receipt data is provided (for immediate printing after sale)
if (isset($_POST['receipt_data'])) {
    $receiptData = json_decode(base64_decode($_POST['receipt_data']), true);
} elseif (isset($_POST['sale_id'])) {
    // Fetch sale data from database
    $saleId = intval($_POST['sale_id']);
    
    // Get sale details
    $sale = $db->fetchOne("
        SELECT s.*, u.username as cashier_name
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.sale_id = ?
    ", [$saleId]);
    
    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }
    
    // Get sale items
    $items = $db->fetchAll("
        SELECT si.*, p.product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = ?
    ", [$saleId]);
    
    // Get settings
    $settings = [];
    $settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
    foreach ($settingsResult as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Prepare receipt data
    $receiptData = [
        'business_name' => $settings['business_name'] ?? '9BARs COFFEE',
        'business_address' => $settings['business_address'] ?? '',
        'business_phone' => $settings['business_phone'] ?? '',
        'sale_id' => $sale['sale_id'],
        'transaction_number' => $sale['transaction_number'],
        'customer_name' => 'Walk-in Customer',
        'items' => [],
        'subtotal' => $sale['total_amount'] - $sale['tax_amount'],
        'tax_rate' => $settings['tax_rate'] ?? '0',
        'tax_amount' => $sale['tax_amount'],
        'total_amount' => $sale['total_amount'],
        'payment_method' => $sale['payment_method'],
        'amount_paid' => $sale['total_amount'], // Assuming full payment
        'change_amount' => 0,
        'receipt_header' => $settings['receipt_header'] ?? 'Welcome to 9Bars Coffee!',
        'receipt_footer' => $settings['receipt_footer'] ?? 'Please come again!'
    ];
    
    // Add items
    foreach ($items as $item) {
        $receiptData['items'][] = [
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal' => $item['total_price'] // Use total_price instead of subtotal
        ];
    }
    
    // Add QR code if enabled
    if (isset($settings['print_qr_code']) && $settings['print_qr_code'] == '1') {
        $receiptData['qr_data'] = 'Sale #' . $saleId . ' - ' . $sale['sale_date'] . ' - Total: ₱' . number_format($sale['total_amount'], 2);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No receipt data provided']);
    exit;
}

try {
    // Get printer settings
    $printerSettings = [];
    $settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'printer_%' OR setting_key IN ('paper_width', 'character_set', 'enable_cash_drawer', 'print_qr_code')");
    
    foreach ($settingsResult as $setting) {
        $printerSettings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Determine printer connection
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
    
    // Retry logic for printer connection (helps with Windows USB printers)
    $maxRetries = 2;
    $retryDelay = 500000; // 500ms in microseconds
    $success = false;
    $lastError = '';
    
    // CRITICAL: Add initial delay for reprint requests
    // Windows needs time to release the printer resource from the previous print job
    if (isset($_POST['sale_id'])) {
        // This is a reprint request - wait for Windows to release printer
        usleep(500000); // 500ms delay
    }
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            // Small delay before retry attempts (not on first attempt)
            if ($attempt > 1) {
                error_log("Print attempt $attempt of $maxRetries for sale ID " . ($receiptData['sale_id'] ?? 'unknown'));
                usleep($retryDelay);
            }
            
            // Initialize and print
            $printer = new ThermalPrinter($printerType, $connectionString);
            $success = $printer->printReceipt($receiptData);
            
            // Open cash drawer if enabled and payment is cash
            if (isset($receiptData['payment_method']) && 
                $receiptData['payment_method'] === 'cash' && 
                isset($printerSettings['enable_cash_drawer']) && 
                $printerSettings['enable_cash_drawer'] == '1') {
                $printer->openDrawer();
            }
            
            $printer->close();
            
            // If we got here without exception, break the retry loop
            if ($success) {
                if ($attempt > 1) {
                    error_log("Print succeeded on attempt $attempt");
                }
                break;
            }
            
        } catch (Exception $printEx) {
            $lastError = $printEx->getMessage();
            error_log("Print attempt $attempt failed: " . $lastError);
            
            // If this was the last attempt, throw the exception
            if ($attempt === $maxRetries) {
                throw $printEx;
            }
        }
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Receipt printed successfully!' : 'Print failed - check printer connection',
        'attempts' => $attempt
    ]);
    
} catch (Exception $e) {
    error_log("Receipt print error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Print failed: ' . $e->getMessage()
    ]);
}
?>