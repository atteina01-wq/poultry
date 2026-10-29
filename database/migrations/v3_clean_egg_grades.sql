-- ==========================================================
-- Migration: v3_clean_egg_grades.sql
-- Purpose: Deactivate legacy A, B, C egg grades and standardize S, M, L, XL as the only active grades
-- ==========================================================

UPDATE `egg_grades` SET `is_active` = 0 WHERE `grade_code` IN ('A', 'B', 'C');
UPDATE `egg_grades` SET `sort_order` = 1, `is_active` = 1 WHERE `grade_code` = 'S';
UPDATE `egg_grades` SET `sort_order` = 2, `is_active` = 1 WHERE `grade_code` = 'M';
UPDATE `egg_grades` SET `sort_order` = 3, `is_active` = 1 WHERE `grade_code` = 'L';
UPDATE `egg_grades` SET `sort_order` = 4, `is_active` = 1 WHERE `grade_code` = 'XL';
