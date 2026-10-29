-- ============================================================================
-- Migration: v4_safe_cleanup_old_demo_data.sql
-- Description: Safely remove old pre-August 18, 2026 demo/test data while 
--              strictly preserving all live records entered from 2026-08-18 onwards.
-- ============================================================================

-- 1. Remove legacy sales records (dated 2026-03-22)
DELETE FROM `sales` WHERE `date` < '2026-08-18';

-- 2. Remove old demo activity logs (dated 2026-03-22 to 2026-03-23)
DELETE FROM `activities` WHERE `date` < '2026-08-18';

-- 3. Remove old cleaning records (dated 2026-03-22)
DELETE FROM `cleaning_records` WHERE `date` < '2026-08-18';

-- 4. Remove old tasks (due_date / created_at < 2026-08-18)
DELETE FROM `tasks` WHERE `due_date` < '2026-08-18' OR `created_at` < '2026-08-18 00:00:00';

-- 5. Remove old health records (dated 2026-03-22)
DELETE FROM `health_records` WHERE `date` < '2026-08-18' OR `created_at` < '2026-08-18 00:00:00';

-- 6. Remove old feed consumption records (dated 2026-03-22 to 2026-04-02)
DELETE FROM `feed_consumption` WHERE `date` < '2026-08-18' OR `created_at` < '2026-08-18 00:00:00';

-- 7. Remove old mortality records (dated 2026-03-16 to 2026-04-07)
DELETE FROM `mortality_records` WHERE `date` < '2026-08-18' OR `created_at` < '2026-08-18 00:00:00';

-- 8. Remove legacy demo batches (Batch IDs 1, 2, 3 with arrival_date 2026-03-22)
-- Note: Batch ID 4 ('BATCH 1-NEW') has arrival_date '2026-08-18' and is strictly preserved!
DELETE FROM `batches` WHERE `arrival_date` < '2026-08-18' AND `batch_id` NOT IN (
    SELECT DISTINCT batch_id FROM `egg_production` WHERE `batch_id` IS NOT NULL
);
