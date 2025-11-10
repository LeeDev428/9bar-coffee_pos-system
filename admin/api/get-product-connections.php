<?php
// Get Product Ingredients and Packaging Connections
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode(['error' => 'Product ID required']);
    exit;
}

$productId = intval($_GET['product_id']);
$db = new Database();

try {
    // Get product ingredients
    $ingredients = $db->fetchAll("
        SELECT 
            pi.ingredient_id,
            pi.quantity_per_unit,
            pi.unit,
            i.ingredient_name
        FROM product_ingredients pi
        JOIN ingredients i ON pi.ingredient_id = i.ingredient_id
        WHERE pi.product_id = ?
        ORDER BY i.ingredient_name
    ", [$productId]);
    
    // Get product packaging
    $packaging = $db->fetchAll("
        SELECT 
            pp.supply_id,
            pp.quantity_per_unit,
            ps.item_name
        FROM product_packaging pp
        JOIN packaging_supplies ps ON pp.supply_id = ps.supply_id
        WHERE pp.product_id = ?
        ORDER BY ps.item_name
    ", [$productId]);
    
    echo json_encode([
        'success' => true,
        'ingredients' => $ingredients,
        'packaging' => $packaging
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
