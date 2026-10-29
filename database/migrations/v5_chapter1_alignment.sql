-- ============================================================================
-- MIGRATION: v5_chapter1_alignment.sql
-- PURPOSE: Align database schema with Chapter 1 (Alquizar Poultry Farm)
-- SAFETY: Fully backward-compatible. Tested on scratch copy of MariaDB 10.4.32.
-- DO NOT APPLY TO LIVE DB UNTIL EXPLICITLY AUTHORIZED.
-- ============================================================================

-- 1. Safely extend expenses category enum preserving ALL historical categories
--    (Preserves existing 'waste', 'feed', 'medicine', 'supply', 'limestone', 'labor', 'utilities', 'other'
--     and adds 'chick', 'vaccine', 'operational')
ALTER TABLE expenses MODIFY COLUMN category 
    ENUM('chick','feed','medicine','vaccine','supply','limestone','waste','labor','utilities','operational','other') DEFAULT 'other';

-- 2. Explicitly extend activities.type enum to support 'General' tasks
ALTER TABLE activities MODIFY COLUMN type 
    ENUM('Feeding','Health','Cleaning','Supply','General') NOT NULL DEFAULT 'General';

-- 3. Extend reservations table for Broiler Advance Bookings (Chapter 1)
--    Enables recording batch, agreed broiler bird count, weight, unit price, advance payment, and total amount
ALTER TABLE reservations 
    ADD COLUMN IF NOT EXISTS batch_id INT NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS quantity_birds INT NULL AFTER batch_id,
    ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,2) NULL AFTER quantity_birds,
    ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL AFTER weight_kg,
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER unit_price,
    ADD COLUMN IF NOT EXISTS advance_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_amount;

-- Add foreign key constraint from reservations to batches
ALTER TABLE reservations 
    ADD CONSTRAINT fk_reservations_batch FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL;

-- 4. Extend orders table for Broiler Sales Fulfillment
ALTER TABLE orders 
    ADD COLUMN IF NOT EXISTS batch_id INT NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS quantity_birds INT NULL AFTER batch_id,
    ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,2) NULL AFTER quantity_birds,
    ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL AFTER weight_kg,
    ADD COLUMN IF NOT EXISTS advance_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_amount;

-- Add foreign key constraint from orders to batches
ALTER TABLE orders 
    ADD CONSTRAINT fk_orders_batch FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL;

-- 5. Make grade_id nullable in order_items & reservation_items so direct broiler records are valid
ALTER TABLE reservation_items MODIFY COLUMN grade_id INT NULL;
ALTER TABLE order_items MODIFY COLUMN grade_id INT NULL;
