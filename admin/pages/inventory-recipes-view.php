<?php
// Fetch Recipe Details for View Modal
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['recipe_id'])) {
    echo json_encode(['error' => 'Recipe ID required']);
    exit;
}

$recipeId = intval($_GET['recipe_id']);
$db = new Database();

try {
    // Get recipe basic info
    $recipe = $db->fetchOne("
        SELECT 
            r.recipe_id,
            r.recipe_name,
            r.serving_size,
            r.preparation_time,
            r.notes,
            p.product_name,
            p.price
        FROM recipes r
        JOIN products p ON r.product_id = p.product_id
        WHERE r.recipe_id = ?
    ", [$recipeId]);
    
    if (!$recipe) {
        echo json_encode(['error' => 'Recipe not found']);
        exit;
    }
    
    // Get recipe ingredients
    $ingredients = $db->fetchAll("
        SELECT 
            ri.quantity,
            ri.unit,
            ri.ingredient_cost,
            i.ingredient_name,
            i.current_stock
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
        WHERE ri.recipe_id = ?
        ORDER BY i.ingredient_name
    ", [$recipeId]);
    
    // Build response
    $response = [
        'recipe_id' => $recipe['recipe_id'],
        'recipe_name' => $recipe['recipe_name'],
        'product_name' => $recipe['product_name'],
        'serving_size' => $recipe['serving_size'],
        'preparation_time' => $recipe['preparation_time'],
        'notes' => $recipe['notes'],
        'product_price' => $recipe['price'],
        'ingredients' => []
    ];
    
    // Add ingredients with stock info
    foreach ($ingredients as $ing) {
        $response['ingredients'][] = [
            'ingredient_name' => $ing['ingredient_name'],
            'quantity' => floatval($ing['quantity']),
            'unit' => $ing['unit'],
            'ingredient_cost' => floatval($ing['ingredient_cost']),
            'current_stock' => floatval($ing['current_stock'])
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
