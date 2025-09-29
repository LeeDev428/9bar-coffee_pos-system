-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for 9bar_pos
CREATE DATABASE IF NOT EXISTS `9bar_pos` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `9bar_pos`;

-- Dumping structure for table 9bar_pos.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  KEY `idx_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for view 9bar_pos.daily_sales_summary
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `daily_sales_summary` (
	`sale_date` DATE NULL,
	`total_transactions` BIGINT NOT NULL,
	`daily_revenue` DECIMAL(32,2) NULL,
	`cash_sales` DECIMAL(32,2) NULL,
	`card_sales` DECIMAL(32,2) NULL,
	`digital_sales` DECIMAL(32,2) NULL
) ENGINE=MyISAM;

-- Dumping structure for procedure 9bar_pos.GetDashboardStats
DELIMITER //
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
END//
DELIMITER ;

-- Dumping structure for table 9bar_pos.inventory
CREATE TABLE IF NOT EXISTS `inventory` (
  `inventory_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `current_stock` int NOT NULL DEFAULT '0',
  `minimum_stock` int NOT NULL DEFAULT '5',
  `maximum_stock` int NOT NULL DEFAULT '100',
  `reorder_level` int NOT NULL DEFAULT '10',
  `unit_of_measure` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pcs',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `unique_product_inventory` (`product_id`),
  KEY `idx_current_stock` (`current_stock`),
  KEY `idx_minimum_stock` (`minimum_stock`),
  KEY `idx_inventory_stock_levels` (`current_stock`,`minimum_stock`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.inventory_items
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `measurement` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `date_added` date DEFAULT NULL,
  `time_added` time DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for view 9bar_pos.low_stock_items
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `low_stock_items` (
	`product_id` INT NOT NULL,
	`product_name` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`category_name` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`current_stock` INT NOT NULL,
	`minimum_stock` INT NOT NULL,
	`reorder_level` INT NOT NULL,
	`shortage_quantity` BIGINT NOT NULL
) ENGINE=MyISAM;

-- Dumping structure for table 9bar_pos.products
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_products_category_status` (`category_id`,`status`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for view 9bar_pos.product_sales_performance
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `product_sales_performance` (
	`product_id` INT NOT NULL,
	`product_name` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`category_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`total_quantity_sold` DECIMAL(32,0) NULL,
	`total_revenue` DECIMAL(32,2) NULL,
	`avg_selling_price` DECIMAL(14,6) NULL,
	`number_of_orders` BIGINT NOT NULL
) ENGINE=MyISAM;

-- Dumping structure for table 9bar_pos.sales
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` int NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('cash','card','digital_wallet','credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','pending','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `sale_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  UNIQUE KEY `unique_transaction` (`transaction_number`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_sales_date_user` (`sale_date`,`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
  `sale_item_id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_per_item` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`sale_item_id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_type` enum('string','number','boolean','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  UNIQUE KEY `unique_setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.stock_adjustments
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `adjustment_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `adjustment_type` enum('add','subtract','set') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_before` int NOT NULL DEFAULT '0',
  `quantity_after` int NOT NULL DEFAULT '0',
  `adjustment_quantity` int NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `adjusted_by` int NOT NULL,
  `adjustment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  KEY `adjusted_by` (`adjusted_by`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_adjustment_date` (`adjustment_date`),
  CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.stock_movements
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `movement_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('in','out','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('sale','purchase','adjustment','return') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` int NOT NULL,
  `movement_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_product_movement` (`product_id`,`movement_date`),
  KEY `idx_movement_type` (`movement_type`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table 9bar_pos.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for trigger 9bar_pos.update_inventory_on_sale
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `update_inventory_on_sale` AFTER INSERT ON `sale_items` FOR EACH ROW BEGIN
    -- Update inventory
    UPDATE inventory 
    SET current_stock = current_stock - NEW.quantity,
        last_updated = CURRENT_TIMESTAMP
    WHERE product_id = NEW.product_id;
    
    -- Log stock movement
    INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, reference_id, user_id)
    SELECT NEW.product_id, 'out', NEW.quantity, 'sale', NEW.sale_id, s.user_id
    FROM sales s WHERE s.sale_id = NEW.sale_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `daily_sales_summary`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `daily_sales_summary` AS select cast(`sales`.`sale_date` as date) AS `sale_date`,count(0) AS `total_transactions`,sum(`sales`.`total_amount`) AS `daily_revenue`,sum((case when (`sales`.`payment_method` = 'cash') then `sales`.`total_amount` else 0 end)) AS `cash_sales`,sum((case when (`sales`.`payment_method` = 'card') then `sales`.`total_amount` else 0 end)) AS `card_sales`,sum((case when (`sales`.`payment_method` = 'digital_wallet') then `sales`.`total_amount` else 0 end)) AS `digital_sales` from `sales` where (`sales`.`payment_status` = 'paid') group by cast(`sales`.`sale_date` as date) order by `sale_date` desc;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `low_stock_items`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `low_stock_items` AS select `p`.`product_id` AS `product_id`,`p`.`product_name` AS `product_name`,`c`.`category_name` AS `category_name`,`i`.`current_stock` AS `current_stock`,`i`.`minimum_stock` AS `minimum_stock`,`i`.`reorder_level` AS `reorder_level`,(`i`.`minimum_stock` - `i`.`current_stock`) AS `shortage_quantity` from ((`inventory` `i` join `products` `p` on((`i`.`product_id` = `p`.`product_id`))) join `categories` `c` on((`p`.`category_id` = `c`.`category_id`))) where (`i`.`current_stock` <= `i`.`minimum_stock`) order by (`i`.`minimum_stock` - `i`.`current_stock`) desc;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `product_sales_performance`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `product_sales_performance` AS select `p`.`product_id` AS `product_id`,`p`.`product_name` AS `product_name`,`c`.`category_name` AS `category_name`,sum(`si`.`quantity`) AS `total_quantity_sold`,sum(`si`.`total_price`) AS `total_revenue`,avg(`si`.`unit_price`) AS `avg_selling_price`,count(distinct `si`.`sale_id`) AS `number_of_orders` from ((`products` `p` left join `sale_items` `si` on((`p`.`product_id` = `si`.`product_id`))) left join `categories` `c` on((`p`.`category_id` = `c`.`category_id`))) group by `p`.`product_id`,`p`.`product_name`,`c`.`category_name` order by `total_quantity_sold` desc;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
