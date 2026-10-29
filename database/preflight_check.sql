-- =============================================================================
-- Preflight Check: run this BEFORE integrated_poultry_upgrade.sql
-- Purpose: catch data that would be silently corrupted by ENUM narrowing,
--          or that would break new UNIQUE constraints.
-- This script only reads data. It does not modify anything.
-- =============================================================================

-- 1. Check `batches`.status values that are NOT in the new allowed set
--    New set: ('active','harvested','closed')
SELECT 'batches.status - values outside new ENUM' AS check_name, status, COUNT(*) AS row_count
FROM batches
WHERE status NOT IN ('active','harvested','closed')
GROUP BY status;

-- 2. Check `inventory`.category values that are NOT in the new allowed set
--    New set: ('feed','medicine','supply','limestone')
SELECT 'inventory.category - values outside new ENUM' AS check_name, category, COUNT(*) AS row_count
FROM inventory
WHERE category NOT IN ('feed','medicine','supply','limestone')
GROUP BY category;

-- 3. Check `users`.role values that are NOT admin/worker
--    (If anything other than admin/worker shows up, decide what to do with it
--     before the migration runs, since the ENUM will only allow admin/worker.)
SELECT 'users.role - values outside admin/worker' AS check_name, role, COUNT(*) AS row_count
FROM users
WHERE role NOT IN ('admin','worker')
GROUP BY role;

-- 4. Check for duplicate (item_name, category) pairs in `inventory`
--    This would break the new UNIQUE KEY uk_inventory_item_category
SELECT 'inventory - duplicate item_name+category (blocks unique index)' AS check_name,
       item_name, category, COUNT(*) AS row_count
FROM inventory
GROUP BY item_name, category
HAVING COUNT(*) > 1;

-- 5. Check `expenses`.category values that are NOT in the new allowed set
--    New set: ('feed','medicine','supply','limestone','waste','labor','utilities','other')
SELECT 'expenses.category - values outside new ENUM' AS check_name, category, COUNT(*) AS row_count
FROM expenses
WHERE category NOT IN ('feed','medicine','supply','limestone','waste','labor','utilities','other')
GROUP BY category;

-- 6. Sanity check: confirm expected pre-existing columns the migration relies on
--    (these are referenced with "AFTER `column_name`" in ALTER statements)
SELECT 'batches.created_at exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'created_at';

SELECT 'inventory.previous_quantity exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'previous_quantity';

SELECT 'feed_consumption.date exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feed_consumption' AND COLUMN_NAME = 'date';

SELECT 'health_records.batch_id exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records' AND COLUMN_NAME = 'batch_id';

SELECT 'health_records.notes exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records' AND COLUMN_NAME = 'notes';

SELECT 'mortality_records.reason exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mortality_records' AND COLUMN_NAME = 'reason';

SELECT 'users.full_name exists?' AS check_name,
       COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'full_name';

-- =============================================================================
-- HOW TO READ THE RESULTS
-- =============================================================================
-- Checks 1-5: if ANY of these return rows, STOP. Do not run the migration yet.
--   - For ENUM mismatches (1,2,3,5): either update those rows to a valid value
--     first, or add the missing value into the migration's target ENUM list.
--   - For duplicates (4): rename/merge/deactivate one of the duplicate rows
--     before the migration runs, or the UNIQUE index creation will fail.
--
-- Checks 6 (the "exists?" ones): each should return found = 1.
--   If any return found = 0, the corresponding ALTER TABLE ... AFTER `column`
--   in the migration will fail because that column doesn't exist in your
--   actual database. Tell me which ones return 0 and I'll adjust the
--   migration to match your real schema.
-- =============================================================================
