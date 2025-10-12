-- XP-58IIB Printer Setup SQL
-- Run this in phpMyAdmin to configure printer settings for automatic receipt printing

-- Create settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert or update printer settings for XP-58IIB USB thermal printer
INSERT INTO settings (setting_key, setting_value) VALUES 
-- Printer Configuration
('printer_type', 'windows'),
('windows_printer_name', 'XP-58IIB'),
('paper_width', '32'),
('character_set', 'CP437'),
('enable_cash_drawer', '0'),
('print_qr_code', '0'),
('auto_print_receipt', '1'),

-- Network printer settings (not used for USB, but kept for completeness)
('network_printer_ip', ''),
('network_printer_port', '9100'),

-- USB printer settings (fallback)
('usb_printer_path', 'COM1'),

-- Business Information
('business_name', '9BARS COFFEE'),
('business_address', 'Balamban, Cebu, Philippines'),
('business_phone', '(032) 123-4567'),
('business_email', 'info@9barcoffee.com'),

-- Receipt Settings
('receipt_header', 'Welcome to 9Bars Coffee!'),
('receipt_footer', 'Thank you for your business!\nPlease come again!'),

-- POS Settings
('allow_discounts', '1'),
('require_customer_name', '0'),
('low_stock_alert', '10'),

-- Tax Settings
('tax_rate', '0'),
('currency', 'PHP')

ON DUPLICATE KEY UPDATE 
  setting_value = VALUES(setting_value),
  updated_at = CURRENT_TIMESTAMP;

-- Verify settings were inserted
SELECT 
  setting_key, 
  setting_value,
  updated_at
FROM settings 
WHERE setting_key IN (
  'printer_type',
  'windows_printer_name',
  'auto_print_receipt',
  'business_name',
  'receipt_header'
)
ORDER BY setting_key;

-- Display success message
SELECT 
  'SUCCESS!' as status,
  'Printer settings configured for XP-58IIB USB thermal printer' as message,
  COUNT(*) as total_settings
FROM settings;
