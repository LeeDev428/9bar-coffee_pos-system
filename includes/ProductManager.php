<?php
/**
 * ProductManager
 * Uses PDO transactions via Database::getConnection()
 */

if (!class_exists('ProductManager')) {

class ProductManager {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Add a new product with inventory (returns inserted product_id)
     */
    public function addProduct(array $data) {
        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare(
                "INSERT INTO products (product_name, category_id, description, price, cost_price, barcode, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $data['product_name'] ?? null,
                $data['category_id'] ?? null,
                $data['description'] ?? null,
                $data['price'] ?? 0,
                $data['cost_price'] ?? 0,
                $data['barcode'] ?? null,
                $data['image'] ?? null,
            ]);

            $productId = (int)$conn->lastInsertId();

            $stmt2 = $conn->prepare(
                "INSERT INTO inventory (product_id, current_stock, minimum_stock, maximum_stock, reorder_level) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt2->execute([
                $productId,
                $data['current_stock'] ?? 0,
                $data['minimum_stock'] ?? 0,
                $data['maximum_stock'] ?? 0,
                $data['reorder_level'] ?? 0,
            ]);

            $conn->commit();
            return $productId;
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    public function updateProduct($productId, array $data) {
        return $this->db->query(
            "UPDATE products SET product_name = ?, category_id = ?, description = ?, price = ?, cost_price = ?, barcode = ?, image_path = ? WHERE product_id = ?",
            [
                $data['product_name'] ?? null,
                $data['category_id'] ?? null,
                $data['description'] ?? null,
                $data['price'] ?? 0,
                $data['cost_price'] ?? 0,
                $data['barcode'] ?? null,
                $data['image'] ?? null,
                $productId,
            ]
        );
    }

    public function deleteProduct($productId) {
        return $this->db->query("UPDATE products SET status = 'inactive' WHERE product_id = ?", [$productId]);
    }

    public function getProduct($productId) {
        return $this->db->fetchOne(
            "SELECT p.*, p.image_path AS image, i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.product_id = ?",
            [$productId]
        );
    }

    public function getAllProducts() {
        return $this->db->fetchAll(
            "SELECT p.*, p.image_path AS image, c.category_name, i.current_stock FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.status = 'active' ORDER BY c.category_name, p.product_name"
        );
    }

    public function searchProducts($searchTerm) {
        $like = "%{$searchTerm}%";
        return $this->db->fetchAll(
            "SELECT p.*, p.image_path AS image, c.category_name, i.current_stock FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.status = 'active' AND (p.product_name LIKE ? OR p.barcode LIKE ?) ORDER BY p.product_name",
            [$like, $like]
        );
    }

    public function getProductsByCategory($categoryId) {
        return $this->db->fetchAll(
            "SELECT p.*, p.image_path AS image, i.current_stock FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.category_id = ? AND p.status = 'active' ORDER BY p.product_name",
            [$categoryId]
        );
    }

    public function updateStock($productId, $newStock) {
        return $this->db->query("UPDATE inventory SET current_stock = ?, last_updated = NOW() WHERE product_id = ?", [$newStock, $productId]);
    }

    public function getLowStockProducts() {
        return $this->db->fetchAll(
            "SELECT p.*, p.image_path AS image, i.current_stock, i.minimum_stock FROM products p JOIN inventory i ON p.product_id = i.product_id WHERE p.status = 'active' AND i.current_stock <= i.minimum_stock ORDER BY i.current_stock ASC"
        );
    }

}

} // end class_exists

?>
<?php
/**
 * Product Manager Class
 * Handles product CRUD operations
 */

// Prevent multiple inclusions
if (!class_exists('ProductManager')) {
    
class ProductManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Update existing product
     */
    public function updateProduct($productId, $data) {
        return $this->db->query("
            UPDATE products 
            SET product_name = ?, category_id = ?, description = ?, price = ?, cost_price = ?, barcode = ?
            WHERE product_id = ?
        ", [
            $data['product_name'],
            $data['category_id'],
            $data['description'],
            $data['price'],
            $data['cost_price'],
            $data['barcode'],
            $productId
        ]);
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
        // alias image_path as image for compatibility
        return $this->db->fetchOne(
            "SELECT p.*, p.image_path AS image, i.current_stock, i.minimum_stock, i.maximum_stock, i.reorder_level FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.product_id = ?",
            [$productId]
        );
    }
    
    /**
     * Get all active products
     */
    public function getAllProducts() {
        // alias image_path as image so UI keeps using `image`
        return $this->db->fetchAll(
            "SELECT p.*, p.image_path AS image, c.category_name, i.current_stock FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.status = 'active' ORDER BY c.category_name, p.product_name"
        );
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

} // End of class_exists check
?>