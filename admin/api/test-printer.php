<?php
/**
 * Printer Test Endpoint
 * Tests thermal printer connectivity and prints test receipt
 */
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/ThermalPrinter.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Require admin login
if (!$auth->requireLogin() || !$auth->requireAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get printer settings
    $printerSettings = [];
    $settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'printer_%' OR setting_key IN ('paper_width', 'character_set', 'enable_cash_drawer', 'print_qr_code')");
    
    foreach ($settingsResult as $setting) {
        $printerSettings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Get business settings for test receipt
    $businessSettings = [];
    $businessResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'business_%'");
    
    foreach ($businessResult as $setting) {
        $businessSettings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Determine printer connection parameters
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
    
    // Initialize thermal printer
    $printer = new ThermalPrinter($printerType, $connectionString);
    
    // Create test receipt data
    $testReceiptData = [
        'business_name' => $businessSettings['business_name'] ?? '9BAR COFFEE',
        'business_address' => $businessSettings['business_address'] ?? 'Balamban, Cebu, Philippines',
        'business_phone' => $businessSettings['business_phone'] ?? '(032) 123-4567',
        'sale_id' => 'TEST-' . date('YmdHis'),
        'cashier' => $auth->getCurrentUser()['username'] ?? 'Test User',
        'customer_name' => 'Test Customer',
        'items' => [
            [
                'product_name' => 'Americano (Hot)',
                'quantity' => 1,
                'unit_price' => 85.00,
                'subtotal' => 85.00
            ],
            [
                'product_name' => 'Chocolate Croissant',
                'quantity' => 2,
                'unit_price' => 45.00,
                'subtotal' => 90.00
            ]
        ],
        'subtotal' => 175.00,
        'tax_rate' => '12',
        'tax_amount' => 21.00,
        'total_amount' => 196.00,
        'payment_method' => 'cash',
        'amount_paid' => 200.00,
        'change_amount' => 4.00,
        'receipt_footer' => 'This is a test print\nThank you for testing!',
        'qr_data' => isset($printerSettings['print_qr_code']) && $printerSettings['print_qr_code'] == '1' ? 
                     'https://9barcoffee.com/receipt/TEST-' . date('YmdHis') : null
    ];
    
    // Print test receipt
    $success = $printer->printReceipt($testReceiptData);
    
    if ($success) {
        // Also open cash drawer if enabled
        if (isset($printerSettings['enable_cash_drawer']) && $printerSettings['enable_cash_drawer'] == '1') {
            $printer->openDrawer();
        }
    }
    
    $printer->close();
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Test print completed successfully!' : 'Print test failed - check printer connection'
    ]);
    
} catch (Exception $e) {
    error_log("Printer test error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Printer test failed: ' . $e->getMessage()
    ]);
}
?>