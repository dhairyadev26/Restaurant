-- Migration: Add stock management and inventory tracking
-- Date: 2024-10-09
-- Description: Add stock management tables and enhance food items with inventory tracking

-- Add stock quantity to food table
ALTER TABLE `food` 
ADD COLUMN `stock_quantity` int(11) DEFAULT 100 AFTER `allergens`,
ADD COLUMN `min_stock_level` int(11) DEFAULT 10 AFTER `stock_quantity`,
ADD COLUMN `unit` varchar(20) DEFAULT 'pieces' AFTER `min_stock_level`;

-- Create inventory transactions table
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `food_id` int(11) NOT NULL,
  `transaction_type` enum('in','out','adjustment','waste') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'Order ID or other reference',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'order, waste, adjustment, etc.',
  `performed_by` int(11) DEFAULT NULL COMMENT 'User ID who performed the transaction',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_food_id` (`food_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_inventory_food` FOREIGN KEY (`food_id`) REFERENCES `food`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create purchase orders table
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_date` (`order_date`),
  CONSTRAINT `fk_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create purchase order items table
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `food_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_order_id` (`purchase_order_id`),
  KEY `idx_food_id` (`food_id`),
  CONSTRAINT `fk_po_items_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_food` FOREIGN KEY (`food_id`) REFERENCES `food`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample suppliers
INSERT INTO `suppliers` (`name`, `contact_person`, `email`, `phone`) VALUES
('Fresh Foods Co.', 'John Smith', 'john@freshfoods.com', '+1234567890'),
('Quality Meats Ltd.', 'Sarah Johnson', 'sarah@qualitymeats.com', '+1234567891'),
('Organic Produce Inc.', 'Mike Wilson', 'mike@organicproduce.com', '+1234567892');

-- Update existing food items with stock quantities
UPDATE `food` SET 
  `stock_quantity` = FLOOR(RAND() * 50) + 20,
  `min_stock_level` = 10,
  `unit` = CASE 
    WHEN `name` LIKE '%juice%' THEN 'bottles'
    WHEN `name` LIKE '%cake%' THEN 'slices'
    ELSE 'pieces'
  END;

-- Create view for low stock items
CREATE OR REPLACE VIEW `low_stock_items` AS
SELECT 
  f.id,
  f.name,
  f.stock_quantity,
  f.min_stock_level,
  f.unit,
  c.name as category_name
FROM food f
LEFT JOIN menu_categories c ON f.category_id = c.id
WHERE f.stock_quantity <= f.min_stock_level
AND f.is_active = 1
ORDER BY f.stock_quantity ASC;

-- Add indexes for better performance
CREATE INDEX `idx_food_stock` ON `food` (`stock_quantity`, `is_active`);
CREATE INDEX `idx_inventory_food_date` ON `inventory_transactions` (`food_id`, `created_at`);
CREATE INDEX `idx_purchase_supplier_status` ON `purchase_orders` (`supplier_id`, `status`);

-- Add table comments
ALTER TABLE `inventory_transactions` 
COMMENT = 'Track all inventory movements and adjustments';

ALTER TABLE `suppliers` 
COMMENT = 'Food and ingredient suppliers';

ALTER TABLE `purchase_orders` 
COMMENT = 'Purchase orders for inventory replenishment';

ALTER TABLE `purchase_order_items` 
COMMENT = 'Individual items in purchase orders';
