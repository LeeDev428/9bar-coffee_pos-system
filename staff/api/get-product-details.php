<?php
// Get Product Details with Ingredients and Packaging Connections
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
    // Get product basic info
    $product = $db->fetchOne("
        SELECT 
            p.product_id,
            p.product_name,
            p.requires_ice,
            p.main_ingredient_id,
            i.ingredient_name as main_ingredient_name,
            i.current_stock as main_ingredient_stock,
            i.unit as main_ingredient_unit
        FROM products p
        LEFT JOIN ingredients i ON p.main_ingredient_id = i.ingredient_id
        WHERE p.product_id = ? AND p.status = 'active'
    ", [$productId]);
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Get product ingredients
    $productIngredients = $db->fetchAll("
        SELECT 
            pi.quantity_per_unit as quantity,
            pi.unit,
            i.ingredient_id,
            i.ingredient_name as name,
            i.current_stock as stock
        FROM product_ingredients pi
        JOIN ingredients i ON pi.ingredient_id = i.ingredient_id
        WHERE pi.product_id = ? AND i.status = 'active'
        ORDER BY i.ingredient_name
    ", [$productId]);
    
    // Get product packaging
    $productPackaging = $db->fetchAll("
        SELECT 
            pp.quantity_per_unit,
            ps.supply_id,
            ps.item_name as name,
            ps.current_stock as stock,
            ps.unit
        FROM product_packaging pp
        JOIN packaging_supplies ps ON pp.supply_id = ps.supply_id
        WHERE pp.product_id = ? AND ps.status = 'active'
        ORDER BY ps.item_name
    ", [$productId]);
    
    // Build response
    $response = [
        'product_id' => $product['product_id'],
        'product_name' => $product['product_name'],
        'requires_ice' => (bool) $product['requires_ice'],
        'main_ingredient' => null,
        'ingredients' => [],
        'packaging' => []
    ];
    
    // Add main ingredient if exists
    if ($product['main_ingredient_id']) {
        $response['main_ingredient'] = [
            'name' => $product['main_ingredient_name'],
            'stock' => floatval($product['main_ingredient_stock']),
            'unit' => $product['main_ingredient_unit']
        ];
    }
    
    // Add product ingredients
    foreach ($productIngredients as $ing) {
        $response['ingredients'][] = [
            'name' => $ing['name'],
            'quantity' => floatval($ing['quantity']),
            'unit' => $ing['unit'],
            'stock' => floatval($ing['stock'])
        ];
    }
    
    // Add product packaging
    foreach ($productPackaging as $pkg) {
        $response['packaging'][] = [
            'name' => $pkg['name'],
            'quantity' => floatval($pkg['quantity_per_unit']),
            'unit' => $pkg['unit'],
            'stock' => floatval($pkg['stock'])
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
