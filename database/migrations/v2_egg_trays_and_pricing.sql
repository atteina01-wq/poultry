-- ==========================================================
-- Migration: v2_egg_trays_and_pricing.sql
-- Purpose: Add tray capacity (30 eggs/tray standard) and base pricing to egg_grades
-- ==========================================================

ALTER TABLE `egg_grades` 
ADD COLUMN IF NOT EXISTS `price_per_piece` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `grade_name`,
ADD COLUMN IF NOT EXISTS `pieces_per_tray` INT NOT NULL DEFAULT 30 AFTER `price_per_piece`;

-- Update standard base prices & tray capacities
UPDATE `egg_grades` SET `price_per_piece` = 6.50, `pieces_per_tray` = 30 WHERE `grade_code` = 'S';
UPDATE `egg_grades` SET `price_per_piece` = 7.50, `pieces_per_tray` = 30 WHERE `grade_code` = 'M';
UPDATE `egg_grades` SET `price_per_piece` = 8.50, `pieces_per_tray` = 30 WHERE `grade_code` = 'L';
UPDATE `egg_grades` SET `price_per_piece` = 9.50, `pieces_per_tray` = 30 WHERE `grade_code` = 'XL';
UPDATE `egg_grades` SET `price_per_piece` = 8.00, `pieces_per_tray` = 30 WHERE `grade_code` = 'A';
UPDATE `egg_grades` SET `price_per_piece` = 7.00, `pieces_per_tray` = 30 WHERE `grade_code` = 'B';
UPDATE `egg_grades` SET `price_per_piece` = 6.00, `pieces_per_tray` = 30 WHERE `grade_code` = 'C';
