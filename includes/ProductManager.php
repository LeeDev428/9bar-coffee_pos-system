<?php
/**
 * Product Manager Class
 * Handles product CRUD operations
 */

// Include guard to prevent multiple declarations
if (!class_exists('ProductManager')) {
    
class ProductManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Add a new product with inventory
     */
    public function addProduct($data) {
        try {
            $this->db->beginTransaction();
            
            // Insert product
            // support optional image_path
            $imagePath = $data['image_path'] ?? null;
            if ($imagePath) {
                $this->db->query("INSERT INTO products (product_name, category_id, description, price, cost_price, barcode, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')", [
                    $data['product_name'],
                    $data['category_id'],
                    $data['description'],
                    $data['price'],
                    $data['cost_price'],
                    $data['barcode'],
                    $imagePath
                ]);
            } else {
                $this->db->query("INSERT INTO products (product_name, category_id, description, price, cost_price, barcode, status) VALUES (?, ?, ?, ?, ?, ?, 'active')", [
                    $data['product_name'],
                    $data['category_id'],
                    $data['description'],
                    $data['price'],
                    $data['cost_price'],
                    $data['barcode']
                ]);
            }
            $productId = $this->db->lastInsertId();
            
            // Insert inventory record
            $this->db->query("
                INSERT INTO inventory (product_id, current_stock, minimum_stock, maximum_stock, reorder_level) 
                VALUES (?, ?, ?, ?, ?)
            ", [
                $productId,
                $data['current_stock'],
                $data['minimum_stock'],
                $data['maximum_stock'],
                $data['reorder_level']
            ]);
            
            $this->db->commit();
            return $productId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update existing product
     */
    public function updateProduct($productId, $data) {
        // If image_path is provided, update it as well
        if (isset($data['image_path'])) {
            return $this->db->query(
                "UPDATE products SET product_name = ?, category_id = ?, description = ?, price = ?, cost_price = ?, barcode = ?, image_path = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?",
                [
                    $data['product_name'],
                    $data['category_id'],
                    $data['description'],
                    $data['price'],
                    $data['cost_price'],
                    $data['barcode'],
                    $data['image_path'],
                    $productId
                ]
            );
        }

        return $this->db->query(
            "UPDATE products SET product_name = ?, category_id = ?, description = ?, price = ?, cost_price = ?, barcode = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?",
            [
                $data['product_name'],
                $data['category_id'],
                $data['description'],
                $data['price'],
                $data['cost_price'],
                $data['barcode'],
                $productId
            ]
        );
    }
    
    /**
     * Delete product (soft delete)
     */
    public function deleteProduct($productId) {
        return $this->db->query("UPDATE products SET status = 'inactive' WHERE product_id = ?", [$productId]);
    }
    
    /**
     * Get product by ID
     */
    public function getProduct($productId) {
        return $this->db->fetchRow("
            SELECT p.*, i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level
            FROM products p
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE p.product_id = ?
        ", [$productId]);
    }
    
    /**
     * Get all active products
     */
    public function getAllProducts() {
        return $this->db->fetchAll("
            SELECT p.*, c.category_name, i.current_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE p.status = 'active'
            ORDER BY c.category_name, p.product_name
        ");
    }
    
    /**
     * Search products
     */
    public function searchProducts($searchTerm) {
        return $this->db->fetchAll("
            SELECT p.*, c.category_name, i.current_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE p.status = 'active' 
            AND (p.product_name LIKE ? OR p.barcode LIKE ?)
            ORDER BY p.product_name
        ", ["%$searchTerm%", "%$searchTerm%"]);
    }
    
    /**
     * Get products by category
     */
    public function getProductsByCategory($categoryId) {
        return $this->db->fetchAll("
            SELECT p.*, i.current_stock
            FROM products p
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE p.category_id = ? AND p.status = 'active'
            ORDER BY p.product_name
        ", [$categoryId]);
    }
    
    /**
     * Update product stock
     */
    public function updateStock($productId, $newStock) {
        return $this->db->query("
            UPDATE inventory 
            SET current_stock = ?, last_updated = NOW() 
            WHERE product_id = ?
        ", [$newStock, $productId]);
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts() {
        return $this->db->fetchAll("
            SELECT p.*, i.current_stock, i.minimum_stock
            FROM products p
            JOIN inventory i ON p.product_id = i.product_id
            WHERE p.status = 'active' AND i.current_stock <= i.minimum_stock
            ORDER BY i.current_stock ASC
        ");
    }
}

} // End of include guard
?>