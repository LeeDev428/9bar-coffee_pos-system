-- BrewTopia POS System Database Schema
-- Created for Laragon/HeidiSQL
-- Date: September 17, 2025

-- Create Database
CREATE DATABASE IF NOT EXISTS `9bar_pos` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `9bar_pos`;

-- ===================================
-- USER MANAGEMENT TABLES
-- ===================================

-- Users Table (Admin and Staff)
CREATE TABLE `users` (
    `user_id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    PRIMARY KEY (`user_id`),
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- PRODUCT MANAGEMENT TABLES
-- ===================================

-- Categories Table
CREATE TABLE `categories` (
    `category_id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`),
    INDEX `idx_category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE `products` (
    `product_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_name` VARCHAR(100) NOT NULL,
    `category_id` INT(11) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `barcode` VARCHAR(50) UNIQUE,
    `image_path` VARCHAR(255),
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE CASCADE,
    INDEX `idx_product_name` (`product_name`),
    INDEX `idx_barcode` (`barcode`),
    INDEX `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- INVENTORY MANAGEMENT TABLES
-- ===================================

-- Inventory Table
CREATE TABLE `inventory` (
    `inventory_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `current_stock` INT(11) NOT NULL DEFAULT 0,
    `minimum_stock` INT(11) NOT NULL DEFAULT 5,
    `maximum_stock` INT(11) NOT NULL DEFAULT 100,
    `reorder_level` INT(11) NOT NULL DEFAULT 10,
    `unit_of_measure` VARCHAR(20) DEFAULT 'pcs',
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`inventory_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_product_inventory` (`product_id`),
    INDEX `idx_current_stock` (`current_stock`),
    INDEX `idx_minimum_stock` (`minimum_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock Movements Table (for tracking inventory changes)
CREATE TABLE `stock_movements` (
    `movement_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `movement_type` ENUM('in', 'out', 'adjustment') NOT NULL,
    `quantity` INT(11) NOT NULL,
    `reference_type` ENUM('sale', 'purchase', 'adjustment', 'return') NOT NULL,
    `reference_id` INT(11),
    `notes` TEXT,
    `user_id` INT(11) NOT NULL,
    `movement_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`movement_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
    INDEX `idx_product_movement` (`product_id`, `movement_date`),
    INDEX `idx_movement_type` (`movement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- SALES MANAGEMENT TABLES
-- ===================================

-- Sales Transactions Table
CREATE TABLE `sales` (
    `sale_id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT(11) NOT NULL,
    `customer_name` VARCHAR(100),
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'card', 'digital_wallet', 'credit') NOT NULL DEFAULT 'cash',
    `payment_status` ENUM('paid', 'pending', 'refunded') NOT NULL DEFAULT 'paid',
    `sale_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    PRIMARY KEY (`sale_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
    UNIQUE KEY `unique_transaction` (`transaction_number`),
    INDEX `idx_sale_date` (`sale_date`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Items Table
CREATE TABLE `sale_items` (
    `sale_item_id` INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_per_item` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`sale_item_id`),
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`sale_id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`),
    INDEX `idx_sale_id` (`sale_id`),
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock Adjustments Table (for inventory management)
CREATE TABLE `stock_adjustments` (
    `adjustment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `adjustment_type` ENUM('add', 'subtract', 'set') NOT NULL,
    `quantity_before` INT(11) NOT NULL DEFAULT 0,
    `quantity_after` INT(11) NOT NULL DEFAULT 0,
    `adjustment_quantity` INT(11) NOT NULL,
    `reason` TEXT,
    `adjusted_by` INT(11) NOT NULL,
    `adjustment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`adjustment_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`adjusted_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_adjustment_date` (`adjustment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- SYSTEM SETTINGS TABLE
-- ===================================

-- Settings Table
CREATE TABLE `settings` (
    `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `description` TEXT,
    `updated_by` INT(11),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_id`),
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`),
    UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- SAMPLE DATA INSERTS
-- ===================================

-- Insert Default Admin User (password: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `status`) VALUES
('admin', '$2y$10$nvGOjg.zXNz9trZOW3BALeHeTfKZgWelKJLsEPIr9QnjTxMsz20fu', 'Administrator', 'admin@9bar.com', 'admin', 'active'),
('staff1', '$2y$10$nvGOjg.zXNz9trZOW3BALeHeTfKZgWelKJLsEPIr9QnjTxMsz20fu', 'Staff Member', 'staff@9bar.com', 'staff', 'active');

-- Insert Sample Categories
INSERT INTO `categories` (`category_name`, `description`) VALUES
('Hot Coffee', 'Hot coffee beverages'),
('Iced Coffee', 'Cold and iced coffee drinks'),
('Tea', 'Hot and iced tea varieties'),
('Pastries', 'Baked goods and pastries'),
('Sandwiches', 'Fresh sandwiches and wraps');

-- Insert Sample Products
INSERT INTO `products` (`product_name`, `category_id`, `description`, `price`, `cost_price`, `barcode`) VALUES
('Americano', 1, 'Classic black coffee', 120.00, 50.00, 'BT001'),
('Cappuccino', 1, 'Espresso with steamed milk foam', 150.00, 60.00, 'BT002'),
('Latte', 1, 'Espresso with steamed milk', 160.00, 65.00, 'BT003'),
('Iced Americano', 2, 'Cold black coffee', 130.00, 55.00, 'BT004'),
('Iced Latte', 2, 'Cold espresso with milk', 170.00, 70.00, 'BT005'),
('Green Tea', 3, 'Fresh green tea', 100.00, 40.00, 'BT006'),
('Croissant', 4, 'Buttery pastry', 80.00, 35.00, 'BT007'),
('Chocolate Muffin', 4, 'Rich chocolate muffin', 95.00, 40.00, 'BT008'),
('Club Sandwich', 5, 'Triple-layer sandwich', 180.00, 80.00, 'BT009'),
('Tuna Wrap', 5, 'Fresh tuna wrap', 160.00, 75.00, 'BT010');

-- Insert Sample Inventory
INSERT INTO `inventory` (`product_id`, `current_stock`, `minimum_stock`, `maximum_stock`, `reorder_level`) VALUES
(1, 50, 5, 100, 10),
(2, 45, 5, 100, 10),
(3, 38, 5, 100, 10),
(4, 42, 5, 100, 10),
(5, 35, 5, 100, 10),
(6, 25, 5, 50, 8),
(7, 15, 3, 30, 5),
(8, 12, 3, 30, 5),
(9, 8, 2, 20, 5),
(10, 6, 2, 20, 5);

-- Insert Sample Sales
INSERT INTO `sales` (`transaction_number`, `user_id`, `total_amount`, `payment_method`, `sale_date`) VALUES
('TXN-20250917-001', 1,  320.00, 'cash', '2025-09-17 08:30:00'),
('TXN-20250917-002', 2,  250.00, 'card', '2025-09-17 09:15:00'),
('TXN-20250917-003', 1,  180.00, 'cash', '2025-09-17 10:00:00'),
('TXN-20250917-004', 2,  410.00, 'digital_wallet', '2025-09-17 11:30:00'),
('TXN-20250917-005', 1, 290.00, 'card', '2025-09-17 13:45:00');

-- Insert Sample Sale Items
INSERT INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `subtotal`) VALUES
-- Sale 1: TXN-20250917-001
(1, 2, 2, 150.00, 300.00, 300.00),  -- 2 Cappuccinos
(1, 7, 1, 80.00, 80.00, 80.00),    -- 1 Croissant

-- Sale 2: TXN-20250917-002  
(2, 3, 1, 160.00, 160.00, 160.00),  -- 1 Latte
(2, 8, 1, 95.00, 95.00, 95.00),    -- 1 Chocolate Muffin

-- Sale 3: TXN-20250917-003
(3, 9, 1, 180.00, 180.00, 180.00),  -- 1 Club Sandwich

-- Sale 4: TXN-20250917-004
(4, 5, 2, 170.00, 340.00, 340.00),  -- 2 Iced Lattes  
(4, 7, 1, 80.00, 80.00, 80.00),    -- 1 Croissant

-- Sale 5: TXN-20250917-005
(5, 1, 1, 120.00, 120.00, 120.00),  -- 1 Americano
(5, 10, 1, 160.00, 160.00, 160.00), -- 1 Tuna Wrap
(5, 8, 1, 95.00, 95.00, 95.00);    -- 1 Chocolate Muffin

-- Insert Default System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'BrewTopia', 'string', 'Company name displayed on receipts and reports'),
('tax_rate', '12.00', 'number', 'Default tax rate percentage'),
('currency', 'PHP', 'string', 'Default currency'),
('receipt_footer', 'Thank you for visiting BrewTopia!', 'string', 'Footer message on receipts'),
('low_stock_alert', '5', 'number', 'Minimum stock level for alerts'),
('backup_frequency', 'daily', 'string', 'Database backup frequency');

-- ===================================
-- USEFUL VIEWS FOR DASHBOARD
-- ===================================

-- View for Today's Sales Summary
CREATE VIEW `daily_sales_summary` AS
SELECT 
    DATE(sale_date) as sale_date,
    COUNT(*) as total_transactions,
    SUM(total_amount) as daily_revenue,
    SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
    SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) as card_sales,
    SUM(CASE WHEN payment_method = 'digital_wallet' THEN total_amount ELSE 0 END) as digital_sales
FROM sales 
WHERE payment_status = 'paid'
GROUP BY DATE(sale_date)
ORDER BY sale_date DESC;

-- View for Product Sales Performance
CREATE VIEW `product_sales_performance` AS
SELECT 
    p.product_id,
    p.product_name,
    c.category_name,
    SUM(si.quantity) as total_quantity_sold,
    SUM(si.total_price) as total_revenue,
    AVG(si.unit_price) as avg_selling_price,
    COUNT(DISTINCT si.sale_id) as number_of_orders
FROM products p
LEFT JOIN sale_items si ON p.product_id = si.product_id
LEFT JOIN categories c ON p.category_id = c.category_id
GROUP BY p.product_id, p.product_name, c.category_name
ORDER BY total_quantity_sold DESC;

-- View for Low Stock Items (Critical Items)
CREATE VIEW `low_stock_items` AS
SELECT 
    p.product_id,
    p.product_name,
    c.category_name,
    i.current_stock,
    i.minimum_stock,
    i.reorder_level,
    (i.minimum_stock - i.current_stock) as shortage_quantity
FROM inventory i
JOIN products p ON i.product_id = p.product_id
JOIN categories c ON p.category_id = c.category_id
WHERE i.current_stock <= i.minimum_stock
ORDER BY shortage_quantity DESC;

-- ===================================
-- TRIGGERS FOR AUTOMATIC STOCK UPDATES
-- ===================================

DELIMITER $$

-- Trigger to update inventory when sale is made
CREATE TRIGGER `update_inventory_on_sale` 
AFTER INSERT ON `sale_items`
FOR EACH ROW
BEGIN
    -- Update inventory
    UPDATE inventory 
    SET current_stock = current_stock - NEW.quantity,
        last_updated = CURRENT_TIMESTAMP
    WHERE product_id = NEW.product_id;
    
    -- Log stock movement
    INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, reference_id, user_id)
    SELECT NEW.product_id, 'out', NEW.quantity, 'sale', NEW.sale_id, s.user_id
    FROM sales s WHERE s.sale_id = NEW.sale_id;
END$$

DELIMITER ;

-- ===================================
-- INDEX OPTIMIZATION
-- ===================================

-- Additional indexes for better query performance
CREATE INDEX `idx_sales_date_user` ON `sales` (`sale_date`, `user_id`);
CREATE INDEX `idx_products_category_status` ON `products` (`category_id`, `status`);
CREATE INDEX `idx_inventory_stock_levels` ON `inventory` (`current_stock`, `minimum_stock`);

-- ===================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- ===================================

DELIMITER $$

-- Procedure to get dashboard statistics
CREATE PROCEDURE `GetDashboardStats`(IN target_date DATE)
BEGIN
    DECLARE today_sales DECIMAL(10,2) DEFAULT 0;
    DECLARE today_quantity INT DEFAULT 0;
    DECLARE total_products INT DEFAULT 0;
    DECLARE critical_items INT DEFAULT 0;
    
    -- Get today's sales
    SELECT COALESCE(SUM(total_amount), 0) INTO today_sales
    FROM sales 
    WHERE DATE(sale_date) = target_date AND payment_status = 'paid';
    
    -- Get today's quantity sold
    SELECT COALESCE(SUM(si.quantity), 0) INTO today_quantity
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE DATE(s.sale_date) = target_date AND s.payment_status = 'paid';
    
    -- Get total products
    SELECT COUNT(*) INTO total_products
    FROM products 
    WHERE status = 'active';
    
    -- Get critical items count
    SELECT COUNT(*) INTO critical_items
    FROM inventory 
    WHERE current_stock <= minimum_stock;
    
    -- Return results
    SELECT 
        today_sales as daily_sales,
        today_quantity as quantity_sold_today,
        total_products as total_products,
        critical_items as critical_items;
END$$

DELIMITER ;

-- Grant permissions (adjust as needed for your setup)
-- GRANT ALL PRIVILEGES ON 9bar_pos.* TO 'pos_user'@'localhost' IDENTIFIED BY 'pos_password';
-- FLUSH PRIVILEGES;
-- FLUSH PRIVILEGES;