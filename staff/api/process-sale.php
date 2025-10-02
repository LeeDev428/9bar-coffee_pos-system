<?php
// Minimal API endpoint to process a sale from POS
header('Content-Type: application/json');
require_once '../../includes/database.php';
require_once '../../includes/ProductManager.php';
require_once '../../includes/SalesManager.php';

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

    // Insert into sales (if table exists). Adjust columns if your schema differs.
    $stmt = $pdo->prepare("INSERT INTO sales (user_id, total_amount, payment_method, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $subtotal, $method]);
    $saleId = $pdo->lastInsertId();

    // Insert sale items and decrement stock for products (skip addons without product_id)
    $productManager = new ProductManager($pdo);

    $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        $pid = $item['id'] ?? null;
        $name = $item['name'] ?? '';
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);

        $itemStmt->execute([$saleId, $pid, $name, $price, $qty]);

        // decrement stock only if product id looks like a numeric product (not addon id)
        if ($pid && preg_match('/^\d+$/', $pid)) {
            // Attempt to decrement via ProductManager if available
            if (method_exists($productManager, 'decreaseStock')) {
                $productManager->decreaseStock((int)$pid, $qty);
            } else {
                // fallback: update inventory table's current_stock
                $upd = $pdo->prepare("UPDATE inventory SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ?");
                $upd->execute([$qty, $pid]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Sale recorded', 'sale_id' => $saleId]);
    exit;
} catch (Exception $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $ex->getMessage()]);
    exit;
}
