<?php
// Save Recipe Handler
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = new Database();
    
    try {
        if ($_POST['action'] === 'add_recipe') {
            $productId = intval($_POST['product_id']);
            $recipeName = sanitizeInput($_POST['recipe_name']);
            $servingSize = sanitizeInput($_POST['serving_size']);
            $prepTime = $_POST['preparation_time'] ? intval($_POST['preparation_time']) : null;
            $notes = sanitizeInput($_POST['notes']);
            
            // Insert recipe
            $db->query("INSERT INTO recipes (product_id, recipe_name, serving_size, preparation_time, notes, status) VALUES (?, ?, ?, ?, ?, 'active')", [
                $productId, $recipeName, $servingSize, $prepTime, $notes
            ]);
            
            $recipeId = $db->lastInsertId();
            
            // Insert recipe ingredients
            if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
                foreach ($_POST['ingredients'] as $ingredient) {
                    $ingredientId = intval($ingredient['ingredient_id']);
                    $quantity = floatval($ingredient['quantity']);
                    $unit = sanitizeInput($ingredient['unit']);
                    
                    // Get ingredient cost
                    $costPerUnit = $db->fetchValue("SELECT cost_per_unit FROM ingredients WHERE ingredient_id = ?", [$ingredientId]);
                    
                    $db->query("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, ingredient_cost) VALUES (?, ?, ?, ?, ?)", [
                        $recipeId, $ingredientId, $quantity, $unit, $costPerUnit
                    ]);
                }
            }
            
            $_SESSION['alert_message'] = 'Recipe added successfully!';
            $_SESSION['alert_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['alert_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['alert_type'] = 'error';
    }
}

header('Location: inventory-recipes.php');
exit;
?>
