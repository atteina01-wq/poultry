-- ==========================================================
-- Migration: v1_security_and_roles.sql
-- Purpose: Standardize 2-Role system (admin, staff) and ensure schema consistency
-- ==========================================================

-- 1. Ensure egg_production has status column
ALTER TABLE `egg_production` 
ADD COLUMN IF NOT EXISTS `status` ENUM('active','cancelled') NOT NULL DEFAULT 'active' AFTER `remarks`;

-- 2. Modify role column to temporarily accept 'worker' during migration
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin','staff','worker','manager','sales','viewer') NOT NULL DEFAULT 'staff';

-- 3. Safely migrate legacy roles to 'staff'
UPDATE `users` SET `role` = 'staff' WHERE `role` IN ('worker','manager','sales','viewer');

-- 4. Enforce strict 2-role enum on users table
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff';
