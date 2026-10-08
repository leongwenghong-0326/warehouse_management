-- Warehouse Management System Database Schema
-- Compatible with MySQL 8.0+

CREATE DATABASE IF NOT EXISTS `warehouse_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `warehouse_management`;

-- 1. Table: users
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: items
CREATE TABLE `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(50) NOT NULL UNIQUE,
    `item_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `unit` VARCHAR(30) NOT NULL,
    `reorder_level` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_item_quantity` CHECK (`quantity` >= 0),
    CONSTRAINT `chk_item_reorder` CHECK (`reorder_level` >= 0),
    INDEX `idx_items_code` (`item_code`),
    INDEX `idx_items_category` (`category`),
    INDEX `idx_items_location` (`location`),
    INDEX `idx_items_quantity` (`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: stock_movements
CREATE TABLE `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `movement_type` ENUM('IN', 'OUT') NOT NULL,
    `quantity` INT NOT NULL,
    `movement_date` DATE NOT NULL,
    `reference_note` VARCHAR(255) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_movement_quantity` CHECK (`quantity` > 0),
    CONSTRAINT `fk_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_movements_item` (`item_id`),
    INDEX `idx_movements_type` (`movement_type`),
    INDEX `idx_movements_date` (`movement_date`),
    INDEX `idx_movements_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Users
-- Default Admin: admin / admin123
-- Default User: user / user123
INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `is_active`) VALUES
(1, 'System Administrator', 'admin', '$2y$10$86mkLp5zkcYeyojnbVYDT.UaIz3a4v4VVK0f9TBx/7B/pxnmTjC32', 'admin', 1),
(2, 'Warehouse Operator', 'user', '$2y$10$hyios28CFcrLKNM/P8fGEO6KSLRTcga0ExcAxYiY/uimaoocoi/Fe', 'user', 1);

-- Seed Sample Inventory Items
INSERT INTO `items` (`id`, `item_code`, `item_name`, `category`, `location`, `quantity`, `unit`, `reorder_level`) VALUES
(1, 'ITM-001', 'Wireless Optical Mouse', 'Electronics', 'Aisle A-01', 75, 'PCS', 20),
(2, 'ITM-002', 'Mechanical Gaming Keyboard', 'Electronics', 'Aisle A-02', 12, 'PCS', 15),
(3, 'ITM-003', 'Cat6 Ethernet Cable (50m)', 'Cables & Wiring', 'Aisle B-01', 8, 'ROLL', 10),
(4, 'ITM-004', 'Heavy Duty Cardboard Box', 'Packaging', 'Zone P-03', 250, 'PCS', 50),
(5, 'ITM-005', 'Bubble Wrap Roll 100m', 'Packaging', 'Zone P-01', 4, 'ROLL', 5),
(6, 'ITM-006', 'Industrial Packing Tape', 'Supplies', 'Zone P-02', 45, 'ROLL', 30);

-- Seed Initial Stock Movements corresponding to items
INSERT INTO `stock_movements` (`item_id`, `movement_type`, `quantity`, `movement_date`, `reference_note`, `created_by`) VALUES
(1, 'IN', 100, CURDATE(), 'Initial opening balance batch', 1),
(1, 'OUT', 25, CURDATE(), 'Dispatched for Sales Order #1002', 1),
(2, 'IN', 20, CURDATE(), 'Supplier delivery PO-4401', 1),
(2, 'OUT', 8, CURDATE(), 'Internal transfer to Tech department', 1),
(3, 'IN', 15, CURDATE(), 'Batch intake from cabling vendor', 1),
(3, 'OUT', 7, CURDATE(), 'Field installation project', 1),
(4, 'IN', 300, CURDATE(), 'Wholesale delivery from Packaging Ltd', 1),
(4, 'OUT', 50, CURDATE(), 'Daily shipping fulfillment usage', 1),
(5, 'IN', 10, CURDATE(), 'Warehouse supplies restocking', 1),
(5, 'OUT', 6, CURDATE(), 'Packing line consumption', 1),
(6, 'IN', 60, CURDATE(), 'Restock inventory purchase', 1),
(6, 'OUT', 15, CURDATE(), 'Fulfillment station allocation', 1);
