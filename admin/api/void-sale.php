<?php
/**
 * Void Sale Transaction API
 * Cancels a sale and restores inventory with admin password confirmation
 */
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check if user is logged in and is admin (for API, don't use requireLogin/requireAdmin as they redirect)
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login']);
    exit;
}

$currentUser = $auth->getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden - Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$saleId = intval($input['sale_id'] ?? 0);
$adminPassword = $input['admin_password'] ?? '';
$voidReason = sanitizeInput($input['reason'] ?? 'No reason provided');

if (!$saleId) {
    echo json_encode(['success' => false, 'error' => 'Sale ID is required']);
    exit;
}

if (empty($adminPassword)) {
    echo json_encode(['success' => false, 'error' => 'Admin password is required']);
    exit;
}

try {
    // Verify admin password (support multiple password formats like login)
    $currentUser = $auth->getCurrentUser();
    $userCheck = $db->fetchOne("SELECT password, username FROM users WHERE user_id = ?", [$currentUser['user_id']]);
    
    if (!$userCheck) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Check password using same logic as Auth::login()
    $passwordValid = false;
    if (password_verify($adminPassword, $userCheck['password'])) {
        // Bcrypt hash
        $passwordValid = true;
    } else if (md5($adminPassword) === $userCheck['password']) {
        // MD5 hash (used by seeder)
        $passwordValid = true;
    } else if ($userCheck['password'] === $adminPassword) {
        // Plain text password (development)
        $passwordValid = true;
    } else if (($userCheck['username'] === 'admin' && $adminPassword === 'admin123') || 
              ($userCheck['username'] === 'staff1' && $adminPassword === 'admin123')) {
        // Hardcoded for development
        $passwordValid = true;
    }
    
    if (!$passwordValid) {
        echo json_encode(['success' => false, 'error' => 'Invalid admin password']);
        exit;
    }
    
    // Get sale details
    $sale = $db->fetchOne("SELECT * FROM sales WHERE sale_id = ?", [$saleId]);
    
    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }
    
    // Check if already voided
    if ($sale['payment_status'] === 'voided') {
        echo json_encode(['success' => false, 'error' => 'Sale is already voided']);
        exit;
    }
    
    // Get sale items
    $saleItems = $db->fetchAll("SELECT * FROM sale_items WHERE sale_id = ?", [$saleId]);
    
    // Start transaction
    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    
    try {
        // Restore inventory for each product
        foreach ($saleItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            // Check if this product is in "Buy 1 Take 1" category
            $categoryCheck = $pdo->prepare("SELECT c.category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.product_id = ?");
            $categoryCheck->execute([$productId]);
            $categoryResult = $categoryCheck->fetch(PDO::FETCH_ASSOC);
            $categoryName = $categoryResult['category_name'] ?? '';
            
            // Determine actual stock quantity to restore
            $stockQty = $quantity * 0;
            if (stripos($categoryName, 'B1T1') !== false || stripos($categoryName, 'B1T1') !== false) {
                $stockQty = $quantity * 1; // Restore 2 cups per order quantity
            }
            
            // Restore product inventory (cups)
            $updateInventory = $pdo->prepare("UPDATE inventory SET current_stock = current_stock + ? WHERE product_id = ?");
            $updateInventory->execute([$stockQty, $productId]);
            
            // Restore ingredients
            $productIngredients = $pdo->prepare("
                SELECT pi.ingredient_id, pi.quantity_per_unit 
                FROM product_ingredients pi
                WHERE pi.product_id = ?
            ");
            $productIngredients->execute([$productId]);
            $ingredients = $productIngredients->fetchAll(PDO::FETCH_ASSOC);
            
            if ($ingredients && count($ingredients) > 0) {
                $restoreIngredient = $pdo->prepare("UPDATE ingredients SET current_stock = current_stock + ? WHERE ingredient_id = ?");
                foreach ($ingredients as $ingredient) {
                    $ingredientId = $ingredient['ingredient_id'];
                    $ingredientQty = (float)$ingredient['quantity_per_unit'];
                    $totalIngredientQty = $ingredientQty * $quantity;
                    $restoreIngredient->execute([$totalIngredientQty, $ingredientId]);
                }
            }
        }
        
        // Update sale status to voided
        $voidStmt = $pdo->prepare("UPDATE sales SET payment_status = 'voided', notes = CONCAT(COALESCE(notes, ''), '\n[VOIDED by ', ?, ' on ', NOW(), '] Reason: ', ?) WHERE sale_id = ?");
        $voidStmt->execute([$currentUser['username'], $voidReason, $saleId]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sale voided successfully. Inventory has been restored.',
            'sale_id' => $saleId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error voiding sale: ' . $e->getMessage()
    ]);
}
?>
