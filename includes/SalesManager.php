<?php
/**
 * Sales Manager Class
 * Handles sales transactions and related operations
 */

// Include guard to prevent multiple declarations
if (!class_exists('SalesManager')) {
    
class SalesManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create a new sale transaction
     */
    public function createSale($saleData, $items) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Insert main sale record
            $stmt = $this->db->query("
                INSERT INTO sales (transaction_number, user_id, customer_name, total_amount, tax_amount, discount_amount, payment_method, sale_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $saleData['transaction_number'],
                $saleData['user_id'],
                $saleData['customer_name'],
                $saleData['total_amount'],
                $saleData['tax_amount'],
                $saleData['discount_amount'] ?? 0,
                $saleData['payment_method']
            ]);
            
            $saleId = $this->db->lastInsertId();
            
            // Insert sale items and update inventory
            foreach ($items as $item) {
                // Insert sale item
                $this->db->query("
                    INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal, discount_per_item) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [
                    $saleId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount_per_item'] ?? 0
                ]);
                
                // Update inventory
                $this->db->query("
                    UPDATE inventory 
                    SET current_stock = current_stock - ?, last_updated = NOW() 
                    WHERE product_id = ?
                ", [
                    $item['quantity'],
                    $item['product_id']
                ]);
            }
            
            $this->db->getConnection()->commit();
            return $saleId;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            throw new Exception("Sale creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get sale by ID
     */
    public function getSale($saleId) {
        return $this->db->fetchOne("
            SELECT s.*, u.username as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.sale_id = ?
        ", [$saleId]);
    }
    
    /**
     * Get sale items
     */
    public function getSaleItems($saleId) {
        return $this->db->fetchAll("
            SELECT si.*, p.product_name
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.product_id
            WHERE si.sale_id = ?
        ", [$saleId]);
    }
    
    /**
     * Get sales by date range
     */
    public function getSalesByDateRange($startDate, $endDate, $userId = null) {
        $sql = "
            SELECT s.*, u.username as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE DATE(s.sale_date) BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $sql .= " AND s.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY s.sale_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get daily sales summary
     */
    public function getDailySales($date = null) {
        $date = $date ?: date('Y-m-d');
        
        return $this->db->fetchOne("
            SELECT 
                DATE(sale_date) as sale_date,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales,
                SUM(tax_amount) as total_tax,
                AVG(total_amount) as average_sale
            FROM sales 
            WHERE DATE(sale_date) = ?
            GROUP BY DATE(sale_date)
        ", [$date]);
    }
    
    /**
     * Get sales statistics
     */
    public function getSalesStats($period = 'today') {
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(sale_date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $dateCondition = "DATE(sale_date) = CURDATE()";
        }
        
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(AVG(total_amount), 0) as average_transaction,
                COUNT(DISTINCT user_id) as active_cashiers
            FROM sales 
            WHERE $dateCondition
        ");
    }
    
    /**
     * Get top selling products
     */
    public function getTopProducts($limit = 10, $period = 'month') {
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(s.sale_date) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $dateCondition = "s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
        
        return $this->db->fetchAll("
            SELECT 
                p.product_name,
                SUM(si.quantity) as total_quantity,
                SUM(si.subtotal) as total_revenue,
                COUNT(DISTINCT s.sale_id) as transaction_count,
                AVG(si.unit_price) as average_price
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE $dateCondition
            GROUP BY si.product_id, p.product_name
            ORDER BY total_revenue DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Void/Cancel a sale
     */
    public function voidSale($saleId, $reason, $userId) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Get original sale items to restore inventory
            $items = $this->getSaleItems($saleId);
            
            // Restore inventory
            foreach ($items as $item) {
                $this->db->query("
                    UPDATE inventory 
                    SET current_stock = current_stock + ?, last_updated = NOW() 
                    WHERE product_id = ?
                ", [
                    $item['quantity'],
                    $item['product_id']
                ]);
            }
            
            // Mark sale as voided
            $this->db->query("
                UPDATE sales 
                SET status = 'voided', void_reason = ?, voided_by = ?, voided_at = NOW()
                WHERE sale_id = ?
            ", [$reason, $userId, $saleId]);
            
            $this->db->getConnection()->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            throw new Exception("Sale void failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique transaction number
     */
    public static function generateTransactionNumber() {
        return 'TXN-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

} // End of include guard
?>