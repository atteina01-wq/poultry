-- Database Indexing and Normalization Optimization Migration
-- Ensures high performance across high volume of records ("for example a lot of records dapat naka index siya")

-- 1. Reservations Table Indexing
ALTER TABLE reservations 
    ADD INDEX IF NOT EXISTS idx_reservations_workflow_stage (workflow_stage),
    ADD INDEX IF NOT EXISTS idx_reservations_batch_id (batch_id),
    ADD INDEX IF NOT EXISTS idx_reservations_status_workflow (status, workflow_stage),
    ADD INDEX IF NOT EXISTS idx_reservations_created_at (created_at);

-- 2. Batches Table Indexing
ALTER TABLE batches 
    ADD INDEX IF NOT EXISTS idx_batches_house_pen (house_pen),
    ADD INDEX IF NOT EXISTS idx_batches_arrival_date (arrival_date),
    ADD INDEX IF NOT EXISTS idx_batches_harvest_date (expected_harvest_date),
    ADD INDEX IF NOT EXISTS idx_batches_created_at (created_at);

-- 3. Houses Table Indexing
ALTER TABLE houses 
    ADD INDEX IF NOT EXISTS idx_houses_current_batch (current_batch_id);

-- 4. Cleaning Records Indexing
ALTER TABLE cleaning_records 
    ADD INDEX IF NOT EXISTS idx_cleaning_records_date (date);

-- 5. Tasks Table Indexing
ALTER TABLE tasks 
    ADD INDEX IF NOT EXISTS idx_tasks_status_due (status, due_date),
    ADD INDEX IF NOT EXISTS idx_tasks_created_at (created_at);

-- 6. Notifications Table Indexing
ALTER TABLE notifications 
    ADD INDEX IF NOT EXISTS idx_notif_user_status_created (user_id, status, created_at);

-- 7. Audit Logs Table Indexing
ALTER TABLE audit_logs 
    ADD INDEX IF NOT EXISTS idx_audit_logs_table_created (table_name, created_at);

-- 8. Health Records Table Indexing
ALTER TABLE health_records 
    ADD INDEX IF NOT EXISTS idx_health_records_date (date),
    ADD INDEX IF NOT EXISTS idx_health_records_status (status);

-- 9. Mortality Records Table Indexing
ALTER TABLE mortality_records 
    ADD INDEX IF NOT EXISTS idx_mortality_records_date (date);

-- 10. Feed Consumption Table Indexing
ALTER TABLE feed_consumption 
    ADD INDEX IF NOT EXISTS idx_feed_consumption_date (date);

-- 11. Expenses Table Indexing
ALTER TABLE expenses 
    ADD INDEX IF NOT EXISTS idx_expenses_date (date);
