-- ==========================================================
-- Migration: v4_order_returns_and_restoration.sql
-- Purpose: Support order cancellation, order returns, and atomic inventory stock restoration
-- ==========================================================

-- 1. Extend orders status enum to include 'returned'
ALTER TABLE `orders` 
MODIFY COLUMN `status` ENUM('draft','pending','confirmed','processing','partially_delivered','delivered','cancelled','returned') NOT NULL DEFAULT 'pending';

-- 2. Extend egg_inventory_movements enum to include 'order_return' and 'order_cancellation'
ALTER TABLE `egg_inventory_movements`
MODIFY COLUMN `movement_type` ENUM('production_in','order_alloc','order_out','order_return','order_cancellation','reservation_hold','reservation_release','distribution_out','waste_out','adjustment_in','adjustment_out') NOT NULL,
MODIFY COLUMN `reference_type` ENUM('production','order','reservation','distribution','waste','adjustment') NULL;

-- 3. Create order_returns table for return tracking and audit
CREATE TABLE IF NOT EXISTS `order_returns` (
    `return_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `return_number` VARCHAR(30) NOT NULL UNIQUE,
    `return_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reason` VARCHAR(255) NOT NULL,
    `returned_by` INT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`returned_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_return_items` (
    `return_item_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT NOT NULL,
    `order_item_id` INT NULL,
    `grade_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `condition_status` ENUM('good','damaged','expired') NOT NULL DEFAULT 'good',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`return_id`) REFERENCES `order_returns`(`return_id`) ON DELETE CASCADE,
    FOREIGN KEY (`grade_id`) REFERENCES `egg_grades`(`grade_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Update v_egg_inventory_balance view to include order_return and order_cancellation
CREATE OR REPLACE VIEW `v_egg_inventory_balance` AS
SELECT 
    g.grade_id,
    g.grade_code,
    g.grade_name,
    COALESCE(SUM(
        CASE eim.movement_type
            WHEN 'production_in' THEN eim.quantity
            WHEN 'adjustment_in' THEN eim.quantity
            WHEN 'order_return' THEN eim.quantity
            WHEN 'order_cancellation' THEN eim.quantity
            WHEN 'reservation_release' THEN eim.quantity
            WHEN 'order_alloc' THEN -eim.quantity
            WHEN 'order_out' THEN -eim.quantity
            WHEN 'reservation_hold' THEN -eim.quantity
            WHEN 'distribution_out' THEN -eim.quantity
            WHEN 'waste_out' THEN -eim.quantity
            WHEN 'adjustment_out' THEN -eim.quantity
            ELSE 0
        END
    ), 0) AS on_hand_qty
FROM egg_grades g
LEFT JOIN egg_inventory_movements eim ON eim.grade_id = g.grade_id
WHERE g.is_active = 1
GROUP BY g.grade_id, g.grade_code, g.grade_name;
