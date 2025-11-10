<?php
// Dashboard Functions
class Dashboard {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getDashboardStats($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $stats = [
            'daily_sales' => 0,
            'quantity_sold_today' => 0,
            'total_products' => 0,
            'critical_items' => 0,
            'cash_sales' => 0,
            'cashless_sales' => 0
        ];
        
        try {
            // Get today's sales - using CURDATE() instead of ? to ensure we get today's data
            $salesSql = "SELECT COALESCE(SUM(total_amount), 0) as daily_sales
                        FROM sales 
                        WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid'";
            $salesResult = $this->db->fetchOne($salesSql);
            $stats['daily_sales'] = $salesResult['daily_sales'] ?? 0;
            
            // Get cash sales (payment_method = 'cash')
            $cashSql = "SELECT COALESCE(SUM(total_amount), 0) as cash_sales
                       FROM sales 
                       WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid' AND payment_method = 'cash'";
            $cashResult = $this->db->fetchOne($cashSql);
            $stats['cash_sales'] = $cashResult['cash_sales'] ?? 0;
            
            // Get cashless sales (payment_method = 'gcash' only, no card)
            $cashlessSql = "SELECT COALESCE(SUM(total_amount), 0) as cashless_sales
                           FROM sales 
                           WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid' 
                           AND payment_method = 'gcash'";
            $cashlessResult = $this->db->fetchOne($cashlessSql);
            $stats['cashless_sales'] = $cashlessResult['cashless_sales'] ?? 0;
            
            // Get today's quantity sold
            $quantitySql = "SELECT COALESCE(SUM(si.quantity), 0) as quantity_sold
                           FROM sale_items si
                           JOIN sales s ON si.sale_id = s.sale_id
                           WHERE DATE(s.sale_date) = CURDATE() AND s.payment_status = 'paid'";
            $quantityResult = $this->db->fetchOne($quantitySql);
            $stats['quantity_sold_today'] = $quantityResult['quantity_sold'] ?? 0;
            
            // Get total products
            $productsSql = "SELECT COUNT(*) as total_products
                           FROM products 
                           WHERE status = 'active'";
            $productsResult = $this->db->fetchOne($productsSql);
            $stats['total_products'] = $productsResult['total_products'] ?? 0;
            
            // Get critical items count
            $criticalSql = "SELECT COUNT(*) as critical_items
                           FROM inventory i
                           JOIN products p ON i.product_id = p.product_id
                           WHERE i.current_stock <= i.minimum_stock 
                           AND p.status = 'active'";
            $criticalResult = $this->db->fetchOne($criticalSql);
            $stats['critical_items'] = $criticalResult['critical_items'] ?? 0;
            
        } catch (Exception $e) {
            // Return default stats if there's an error
            error_log("Dashboard stats error: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    public function getBestSellingProducts($limit = 5) {
        $sql = "SELECT p.product_name, c.category_name, 
                       COALESCE(SUM(si.quantity), 0) as quantity_sold
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN sale_items si ON p.product_id = si.product_id
                LEFT JOIN sales s ON si.sale_id = s.sale_id
                WHERE p.status = 'active' 
                AND (s.payment_status IS NULL OR s.payment_status = 'paid')
                GROUP BY p.product_id, p.product_name, c.category_name
                ORDER BY quantity_sold DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getCriticalItems($limit = 10) {
        $sql = "SELECT p.product_name, c.category_name,
                       i.current_stock, i.minimum_stock,
                       (i.minimum_stock - i.current_stock) as shortage_quantity
                FROM inventory i
                JOIN products p ON i.product_id = p.product_id
                JOIN categories c ON p.category_id = c.category_id
                WHERE i.current_stock <= i.minimum_stock
                AND p.status = 'active'
                ORDER BY shortage_quantity DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getSalesChart($days = 7) {
        $sql = "SELECT DATE(sale_date) as date, 
                       COUNT(*) as transactions,
                       COALESCE(SUM(total_amount), 0) as revenue
                FROM sales 
                WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND payment_status = 'paid'
                GROUP BY DATE(sale_date)
                ORDER BY date ASC";
        
        $result = $this->db->fetchAll($sql, [$days]);
        
        // If no data, return sample data
        if (empty($result)) {
            return [
                ['date' => date('Y-m-d', strtotime('-6 days')), 'revenue' => 1200],
                ['date' => date('Y-m-d', strtotime('-5 days')), 'revenue' => 1500],
                ['date' => date('Y-m-d', strtotime('-4 days')), 'revenue' => 1100],
                ['date' => date('Y-m-d', strtotime('-3 days')), 'revenue' => 1800],
                ['date' => date('Y-m-d', strtotime('-2 days')), 'revenue' => 1400],
                ['date' => date('Y-m-d', strtotime('-1 days')), 'revenue' => 1600],
                ['date' => date('Y-m-d'), 'revenue' => 900]
            ];
        }
        
        return $result;
    }
    
    public function getProductQuantityChart() {
        $sql = "SELECT p.product_name, 
                       COALESCE(SUM(si.quantity), 0) as total_sold
                FROM products p
                LEFT JOIN sale_items si ON p.product_id = si.product_id
                LEFT JOIN sales s ON si.sale_id = s.sale_id
                WHERE p.status = 'active' 
                AND (s.sale_date IS NULL OR s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
                AND (s.payment_status IS NULL OR s.payment_status = 'paid')
                GROUP BY p.product_id, p.product_name
                ORDER BY total_sold DESC
                LIMIT 10";
        
        $result = $this->db->fetchAll($sql);
        
        // If no data, return sample data
        if (empty($result)) {
            return [
                ['product_name' => 'Americano', 'total_sold' => 25],
                ['product_name' => 'Cappuccino', 'total_sold' => 20],
                ['product_name' => 'Latte', 'total_sold' => 18],
                ['product_name' => 'Iced Coffee', 'total_sold' => 15],
                ['product_name' => 'Mocha', 'total_sold' => 12]
            ];
        }
        
        return $result;
    }
    
    public function getRecentTransactions($limit = 10) {
        $sql = "SELECT s.transaction_number, s.customer_name, s.total_amount,
                       s.payment_method, s.sale_date, u.full_name as cashier
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.user_id
                WHERE s.payment_status = 'paid'
                ORDER BY s.sale_date DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getDailySoldProducts() {
        $sql = "SELECT p.product_name, c.category_name,
                       SUM(si.quantity) as total_quantity,
                       SUM(si.total_price) as total_revenue,
                       si.unit_price,
                       p.image_path
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.sale_id
                JOIN products p ON si.product_id = p.product_id
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE DATE(s.sale_date) = CURDATE() 
                AND s.payment_status = 'paid'
                GROUP BY p.product_id, p.product_name, c.category_name, si.unit_price, p.image_path
                ORDER BY total_quantity DESC";
        
        return $this->db->fetchAll($sql);
    }
}

// Sales Management Functions
class SalesManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function createSale($saleData, $items) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Insert sale record
            $sql = "INSERT INTO sales (transaction_number, user_id, customer_name, total_amount, 
                                     tax_amount, discount_amount, payment_method, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $saleData['transaction_number'],
                $saleData['user_id'],
                $saleData['customer_name'],
                $saleData['total_amount'],
                $saleData['tax_amount'],
                $saleData['discount_amount'],
                $saleData['payment_method'],
                'paid'
            ]);
            
            $saleId = $this->db->lastInsertId();
            
            // Insert sale items
            foreach ($items as $item) {
                $itemSql = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price, discount_per_item)
                           VALUES (?, ?, ?, ?, ?, ?)";
                
                $this->db->query($itemSql, [
                    $saleId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['discount_per_item'] ?? 0
                ]);
            }
            
            $this->db->getConnection()->commit();
            return $saleId;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            throw $e;
        }
    }
}
?>