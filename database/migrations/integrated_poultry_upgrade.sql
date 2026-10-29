-- =============================================================================
-- Migration: integrated_poultry_upgrade.sql
-- Database:  poultry_db (MariaDB / MySQL 8+ compatible)
-- Purpose:   Extend existing broiler-focused schema into an integrated poultry
--            farm management database (egg production, commerce, expiry, waste).
--
-- Safety:
--   - No DROP TABLE on application tables
--   - No DELETE / TRUNCATE of existing data
--   - CREATE TABLE IF NOT EXISTS + conditional ALTER helpers
--   - Existing PKs and FKs preserved; new constraints added additively
--
-- Run:
--   mysql -u root poultry_db < database/migrations/integrated_poultry_upgrade.sql
--
-- Idempotency:
--   Safe to re-run. Helper procedures skip existing columns, indexes, and FKs.
--   ENUM MODIFY statements use the full target definition (re-applying is safe).
--   CREATE OR REPLACE VIEW refreshes view definitions.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------------
-- 0. Idempotent DDL helpers (dropped at end of migration)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS migration_add_column;
DROP PROCEDURE IF EXISTS migration_add_index;
DROP PROCEDURE IF EXISTS migration_add_fk;

DELIMITER $$

CREATE PROCEDURE migration_add_column(
    IN p_table   VARCHAR(64),
    IN p_column  VARCHAR(64),
    IN p_def     TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE migration_add_index(
    IN p_table   VARCHAR(64),
    IN p_index   VARCHAR(64),
    IN p_def     TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE migration_add_fk(
    IN p_table       VARCHAR(64),
    IN p_constraint  VARCHAR(64),
    IN p_def         TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND CONSTRAINT_NAME = p_constraint
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------------
-- 1. Migration tracking
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `migration_id`   INT(11)      NOT NULL AUTO_INCREMENT,
    `migration_name` VARCHAR(255) NOT NULL,
    `applied_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`migration_id`),
    UNIQUE KEY `uk_schema_migrations_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 2. Baseline: ensure tables present in dump but missing from db_check bootstrap
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `expense_id`  INT(11)       NOT NULL AUTO_INCREMENT,
    `batch_id`    INT(11)       DEFAULT NULL,
    `category`    ENUM('feed','medicine','other') DEFAULT NULL,
    `amount`      DECIMAL(14,2) DEFAULT NULL,
    `description` VARCHAR(255)  DEFAULT NULL,
    `date`        DATE          DEFAULT NULL,
    PRIMARY KEY (`expense_id`),
    KEY `batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT(11)       NOT NULL AUTO_INCREMENT,
    `message`         VARCHAR(255)  DEFAULT NULL,
    `type`            ENUM('low_stock','vaccination','general') DEFAULT NULL,
    `status`          ENUM('unread','read') DEFAULT 'unread',
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure baseline FK on expenses when table pre-existed without constraint
CALL migration_add_fk('expenses', 'expenses_ibfk_1',
    'FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- Widen money columns on existing sales table (non-destructive; fixes DECIMAL(10,2) overflow)
ALTER TABLE `sales`
    MODIFY COLUMN `price_per_kg`   DECIMAL(14,2) DEFAULT NULL,
    MODIFY COLUMN `total_revenue`  DECIMAL(14,2) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3. Extend batches (flock management)
-- ---------------------------------------------------------------------------
ALTER TABLE `batches`
    MODIFY COLUMN `status` ENUM('active','harvested','closed') DEFAULT 'active';

CALL migration_add_column('batches', 'flock_type',
    "ENUM('broiler','layer') NOT NULL DEFAULT 'broiler' AFTER `status`");
CALL migration_add_column('batches', 'breed',
    'VARCHAR(100) DEFAULT NULL AFTER `flock_type`');
CALL migration_add_column('batches', 'house_pen',
    'VARCHAR(100) DEFAULT NULL AFTER `breed`');
CALL migration_add_column('batches', 'expected_harvest_date',
    'DATE DEFAULT NULL AFTER `house_pen`');
CALL migration_add_column('batches', 'notes',
    'TEXT DEFAULT NULL AFTER `expected_harvest_date`');
CALL migration_add_column('batches', 'created_by',
    'INT(11) DEFAULT NULL AFTER `notes`');
CALL migration_add_column('batches', 'updated_at',
    'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

CALL migration_add_index('batches', 'idx_batches_status_type',
    'INDEX `idx_batches_status_type` (`status`, `flock_type`)');
CALL migration_add_index('batches', 'idx_batches_flock_type',
    'INDEX `idx_batches_flock_type` (`flock_type`)');
CALL migration_add_fk('batches', 'fk_batches_created_by',
    'FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- ---------------------------------------------------------------------------
-- 4. Extend inventory (supply + limestone + expiry support via lots)
-- ---------------------------------------------------------------------------
ALTER TABLE `inventory`
    MODIFY COLUMN `category` ENUM('feed','medicine','supply','limestone') NOT NULL;

CALL migration_add_column('inventory', 'reorder_level',
    'DECIMAL(10,2) DEFAULT NULL AFTER `previous_quantity`');
CALL migration_add_column('inventory', 'is_active',
    'TINYINT(1) NOT NULL DEFAULT 1 AFTER `reorder_level`');
CALL migration_add_column('inventory', 'sku',
    'VARCHAR(50) DEFAULT NULL AFTER `is_active`');
CALL migration_add_column('inventory', 'description',
    'TEXT DEFAULT NULL AFTER `sku`');
CALL migration_add_column('inventory', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `description`');
CALL migration_add_column('inventory', 'updated_at',
    'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

CALL migration_add_index('inventory', 'idx_inventory_category',
    'INDEX `idx_inventory_category` (`category`)');
CALL migration_add_index('inventory', 'idx_inventory_active_category',
    'INDEX `idx_inventory_active_category` (`is_active`, `category`)');
CALL migration_add_index('inventory', 'uk_inventory_item_category',
    'UNIQUE INDEX `uk_inventory_item_category` (`item_name`, `category`)');

CREATE TABLE IF NOT EXISTS `inventory_lots` (
    `lot_id`              INT(11)       NOT NULL AUTO_INCREMENT,
    `inventory_id`        INT(11)       NOT NULL,
    `lot_number`          VARCHAR(50)   NOT NULL,
    `quantity_received`   DECIMAL(10,2) NOT NULL,
    `quantity_remaining`  DECIMAL(10,2) NOT NULL,
    `unit_cost`           DECIMAL(14,2) DEFAULT NULL,
    `received_date`       DATE          NOT NULL,
    `expiry_date`         DATE          DEFAULT NULL,
    `supplier`            VARCHAR(100)  DEFAULT NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`lot_id`),
    UNIQUE KEY `uk_inventory_lots_inventory_lot` (`inventory_id`, `lot_number`),
    KEY `idx_inventory_lots_inventory` (`inventory_id`),
    KEY `idx_inventory_lots_expiry` (`expiry_date`, `quantity_remaining`),
    CONSTRAINT `fk_inventory_lots_inventory`
        FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill one legacy lot per inventory row (no-op when lots already exist)
INSERT INTO `inventory_lots` (
    `inventory_id`, `lot_number`, `quantity_received`, `quantity_remaining`,
    `unit_cost`, `received_date`, `supplier`
)
SELECT
    i.`inventory_id`,
    CONCAT('LEGACY-', i.`inventory_id`),
    i.`quantity`,
    i.`quantity`,
    i.`unit_cost`,
    COALESCE(DATE(i.`created_at`), CURDATE()),
    NULL
FROM `inventory` i
WHERE NOT EXISTS (
    SELECT 1
    FROM `inventory_lots` il
    WHERE il.`inventory_id` = i.`inventory_id`
      AND il.`lot_number`   = CONCAT('LEGACY-', i.`inventory_id`)
);

-- ---------------------------------------------------------------------------
-- 5. Extend feed_consumption (feed + limestone batch usage)
-- ---------------------------------------------------------------------------
CALL migration_add_column('feed_consumption', 'recorded_by',
    'INT(11) DEFAULT NULL AFTER `date`');
CALL migration_add_column('feed_consumption', 'remarks',
    'TEXT DEFAULT NULL AFTER `recorded_by`');
CALL migration_add_column('feed_consumption', 'unit_cost_snapshot',
    'DECIMAL(14,2) DEFAULT NULL AFTER `remarks`');
CALL migration_add_column('feed_consumption', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `unit_cost_snapshot`');

CALL migration_add_index('feed_consumption', 'idx_feed_consumption_batch_date',
    'INDEX `idx_feed_consumption_batch_date` (`batch_id`, `date`)');
CALL migration_add_fk('feed_consumption', 'fk_feed_consumption_recorded_by',
    'FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- ---------------------------------------------------------------------------
-- 6. Extend health_records (flock health + medicine inventory link)
-- ---------------------------------------------------------------------------
CALL migration_add_column('health_records', 'inventory_id',
    'INT(11) DEFAULT NULL AFTER `batch_id`');
CALL migration_add_column('health_records', 'recorded_by',
    'INT(11) DEFAULT NULL AFTER `notes`');
CALL migration_add_column('health_records', 'next_due_date',
    'DATE DEFAULT NULL AFTER `recorded_by`');
CALL migration_add_column('health_records', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `next_due_date`');

CALL migration_add_index('health_records', 'idx_health_records_batch_date',
    'INDEX `idx_health_records_batch_date` (`batch_id`, `date`)');
CALL migration_add_index('health_records', 'idx_health_records_next_due',
    'INDEX `idx_health_records_next_due` (`next_due_date`)');
CALL migration_add_index('health_records', 'idx_health_records_inventory',
    'INDEX `idx_health_records_inventory` (`inventory_id`)');
CALL migration_add_fk('health_records', 'fk_health_records_inventory',
    'FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL migration_add_fk('health_records', 'fk_health_records_recorded_by',
    'FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- Best-effort backfill of inventory_id from medicine_name (non-destructive)
UPDATE `health_records` hr
INNER JOIN `inventory` i
    ON i.`item_name` = hr.`medicine_name`
   AND i.`category`  = 'medicine'
SET hr.`inventory_id` = i.`inventory_id`
WHERE hr.`inventory_id` IS NULL
  AND hr.`medicine_name` IS NOT NULL;

-- ---------------------------------------------------------------------------
-- 7. Extend mortality_records
-- ---------------------------------------------------------------------------
CALL migration_add_column('mortality_records', 'recorded_by',
    'INT(11) DEFAULT NULL AFTER `reason`');
CALL migration_add_column('mortality_records', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `recorded_by`');

CALL migration_add_index('mortality_records', 'idx_mortality_records_batch_date',
    'INDEX `idx_mortality_records_batch_date` (`batch_id`, `date`)');
CALL migration_add_fk('mortality_records', 'fk_mortality_records_recorded_by',
    'FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- ---------------------------------------------------------------------------
-- 8. Extend expenses (financial ledger)
-- ---------------------------------------------------------------------------
ALTER TABLE `expenses`
    MODIFY COLUMN `amount`   DECIMAL(14,2) DEFAULT NULL,
    MODIFY COLUMN `category` ENUM('feed','medicine','supply','limestone','waste','labor','utilities','other') DEFAULT NULL;

CALL migration_add_column('expenses', 'source_type',
    "ENUM('manual','feed_consumption','health_record','inventory_receipt','waste','order','distribution') DEFAULT NULL AFTER `date`");
CALL migration_add_column('expenses', 'source_id',
    'INT(11) DEFAULT NULL AFTER `source_type`');
CALL migration_add_column('expenses', 'inventory_id',
    'INT(11) DEFAULT NULL AFTER `source_id`');
CALL migration_add_column('expenses', 'recorded_by',
    'INT(11) DEFAULT NULL AFTER `inventory_id`');
CALL migration_add_column('expenses', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `recorded_by`');

CALL migration_add_index('expenses', 'idx_expenses_date',
    'INDEX `idx_expenses_date` (`date`)');
CALL migration_add_index('expenses', 'idx_expenses_source',
    'INDEX `idx_expenses_source` (`source_type`, `source_id`)');
CALL migration_add_index('expenses', 'idx_expenses_batch_date',
    'INDEX `idx_expenses_batch_date` (`batch_id`, `date`)');
CALL migration_add_fk('expenses', 'fk_expenses_inventory',
    'FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL migration_add_fk('expenses', 'fk_expenses_recorded_by',
    'FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- ---------------------------------------------------------------------------
-- 9. Extend users (roles + account metadata)
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('admin','worker','manager','sales','viewer') NOT NULL DEFAULT 'worker';

CALL migration_add_column('users', 'email',
    'VARCHAR(100) DEFAULT NULL AFTER `full_name`');
CALL migration_add_column('users', 'phone',
    'VARCHAR(20) DEFAULT NULL AFTER `email`');
CALL migration_add_column('users', 'is_active',
    'TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone`');
CALL migration_add_column('users', 'last_login_at',
    'DATETIME DEFAULT NULL AFTER `is_active`');
CALL migration_add_column('users', 'created_at',
    'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `last_login_at`');
CALL migration_add_column('users', 'updated_at',
    'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

CALL migration_add_index('users', 'uk_users_email',
    'UNIQUE INDEX `uk_users_email` (`email`)');
CALL migration_add_index('users', 'idx_users_active_role',
    'INDEX `idx_users_active_role` (`is_active`, `role`)');

-- ---------------------------------------------------------------------------
-- 10. Extend notifications
-- ---------------------------------------------------------------------------
ALTER TABLE `notifications`
    MODIFY COLUMN `type` ENUM(
        'low_stock','vaccination','general','expiry','mortality',
        'order_due','reservation_due','low_egg_stock','demand_supply_gap'
    ) DEFAULT NULL;

CALL migration_add_column('notifications', 'user_id',
    'INT(11) DEFAULT NULL AFTER `notification_id`');
CALL migration_add_column('notifications', 'entity_type',
    'VARCHAR(50) DEFAULT NULL AFTER `type`');
CALL migration_add_column('notifications', 'entity_id',
    'INT(11) DEFAULT NULL AFTER `entity_type`');
CALL migration_add_column('notifications', 'severity',
    "ENUM('info','warning','critical') DEFAULT 'info' AFTER `entity_id`");
CALL migration_add_column('notifications', 'read_at',
    'DATETIME DEFAULT NULL AFTER `status`');

CALL migration_add_index('notifications', 'idx_notifications_user_status',
    'INDEX `idx_notifications_user_status` (`user_id`, `status`)');
CALL migration_add_index('notifications', 'idx_notifications_entity',
    'INDEX `idx_notifications_entity` (`entity_type`, `entity_id`)');
CALL migration_add_fk('notifications', 'fk_notifications_user',
    'FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- ---------------------------------------------------------------------------
-- 11. Egg domain: grades, production, inventory ledger
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `egg_grades` (
    `grade_id`    INT(11)      NOT NULL AUTO_INCREMENT,
    `grade_code`  VARCHAR(20)  NOT NULL,
    `grade_name`  VARCHAR(50)  NOT NULL,
    `sort_order`  INT(11)      NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`grade_id`),
    UNIQUE KEY `uk_egg_grades_code` (`grade_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `egg_grades` (`grade_code`, `grade_name`, `sort_order`) VALUES
    ('A', 'Grade A', 1),
    ('B', 'Grade B', 2),
    ('C', 'Grade C', 3),
    ('XL', 'Extra Large', 4),
    ('L',  'Large', 5),
    ('M',  'Medium', 6),
    ('S',  'Small', 7);

CREATE TABLE IF NOT EXISTS `egg_production` (
    `production_id`      INT(11)  NOT NULL AUTO_INCREMENT,
    `batch_id`           INT(11)  NOT NULL,
    `production_date`    DATE     NOT NULL,
    `grade_id`           INT(11)  NOT NULL,
    `quantity_collected` INT(11)  NOT NULL DEFAULT 0,
    `quantity_broken`    INT(11)  NOT NULL DEFAULT 0,
    `recorded_by`        INT(11)  DEFAULT NULL,
    `remarks`            TEXT     DEFAULT NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`production_id`),
    UNIQUE KEY `uk_egg_production_batch_date_grade` (`batch_id`, `production_date`, `grade_id`),
    KEY `idx_egg_production_date` (`production_date`),
    KEY `idx_egg_production_grade` (`grade_id`),
    CONSTRAINT `fk_egg_production_batch`
        FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_egg_production_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_egg_production_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `egg_inventory_movements` (
    `movement_id`     INT(11)     NOT NULL AUTO_INCREMENT,
    `movement_date`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `grade_id`        INT(11)     NOT NULL,
    `movement_type`   ENUM(
        'production_in','order_alloc','order_out','reservation_hold',
        'reservation_release','distribution_out','waste_out',
        'adjustment_in','adjustment_out'
    ) NOT NULL,
    `quantity`        INT(11)     NOT NULL,
    `reference_type`  ENUM('production','order','reservation','distribution','waste','adjustment') DEFAULT NULL,
    `reference_id`    INT(11)     DEFAULT NULL,
    `batch_id`        INT(11)     DEFAULT NULL,
    `recorded_by`     INT(11)     DEFAULT NULL,
    `remarks`         TEXT        DEFAULT NULL,
    PRIMARY KEY (`movement_id`),
    KEY `idx_eim_grade_date` (`grade_id`, `movement_date`),
    KEY `idx_eim_reference` (`reference_type`, `reference_id`),
    KEY `idx_eim_batch` (`batch_id`),
    KEY `idx_eim_type` (`movement_type`),
    CONSTRAINT `fk_eim_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eim_batch`
        FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_eim_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 12. Customers and egg commerce
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `customer_id`     INT(11)      NOT NULL AUTO_INCREMENT,
    `customer_code`   VARCHAR(30)  NOT NULL,
    `name`            VARCHAR(150) NOT NULL,
    `contact_person`  VARCHAR(100) DEFAULT NULL,
    `phone`           VARCHAR(30)  DEFAULT NULL,
    `email`           VARCHAR(100) DEFAULT NULL,
    `address`         TEXT         DEFAULT NULL,
    `customer_type`   ENUM('retail','wholesale','institutional') NOT NULL DEFAULT 'retail',
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`customer_id`),
    UNIQUE KEY `uk_customers_code` (`customer_code`),
    KEY `idx_customers_name` (`name`),
    KEY `idx_customers_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `order_id`       INT(11)       NOT NULL AUTO_INCREMENT,
    `order_number`   VARCHAR(30)   NOT NULL,
    `customer_id`    INT(11)       NOT NULL,
    `order_date`     DATE          NOT NULL,
    `required_date`  DATE          DEFAULT NULL,
    `status`         ENUM('draft','pending','confirmed','processing','partially_delivered','delivered','cancelled') NOT NULL DEFAULT 'pending',
    `total_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT          DEFAULT NULL,
    `created_by`     INT(11)       DEFAULT NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`order_id`),
    UNIQUE KEY `uk_orders_number` (`order_number`),
    KEY `idx_orders_customer_status` (`customer_id`, `status`),
    KEY `idx_orders_required_date` (`required_date`),
    KEY `idx_orders_order_date` (`order_date`),
    CONSTRAINT `fk_orders_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `order_item_id`       INT(11)       NOT NULL AUTO_INCREMENT,
    `order_id`            INT(11)       NOT NULL,
    `grade_id`            INT(11)       NOT NULL,
    `quantity_ordered`    INT(11)       NOT NULL,
    `quantity_delivered`  INT(11)       NOT NULL DEFAULT 0,
    `unit_price`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `line_total`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`order_item_id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_grade` (`grade_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reservations` (
    `reservation_id`      INT(11)     NOT NULL AUTO_INCREMENT,
    `reservation_number`  VARCHAR(30) NOT NULL,
    `customer_id`         INT(11)     NOT NULL,
    `pickup_date`         DATE        NOT NULL,
    `status`              ENUM('active','fulfilled','cancelled','expired') NOT NULL DEFAULT 'active',
    `converted_order_id`  INT(11)     DEFAULT NULL,
    `notes`               TEXT        DEFAULT NULL,
    `created_by`          INT(11)     DEFAULT NULL,
    `created_at`          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`reservation_id`),
    UNIQUE KEY `uk_reservations_number` (`reservation_number`),
    KEY `idx_reservations_pickup_status` (`pickup_date`, `status`),
    KEY `idx_reservations_customer` (`customer_id`),
    CONSTRAINT `fk_reservations_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reservations_order`
        FOREIGN KEY (`converted_order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservations_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reservation_items` (
    `reservation_item_id`  INT(11) NOT NULL AUTO_INCREMENT,
    `reservation_id`       INT(11) NOT NULL,
    `grade_id`             INT(11) NOT NULL,
    `quantity`             INT(11) NOT NULL,
    `quantity_fulfilled`   INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`reservation_item_id`),
    KEY `idx_reservation_items_reservation` (`reservation_id`),
    KEY `idx_reservation_items_grade` (`grade_id`),
    CONSTRAINT `fk_reservation_items_reservation`
        FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reservation_items_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `distributions` (
    `distribution_id`     INT(11)     NOT NULL AUTO_INCREMENT,
    `distribution_number` VARCHAR(30) NOT NULL,
    `order_id`            INT(11)     DEFAULT NULL,
    `customer_id`         INT(11)     NOT NULL,
    `distribution_date`   DATE        NOT NULL,
    `status`              ENUM('scheduled','in_transit','delivered','cancelled') NOT NULL DEFAULT 'scheduled',
    `driver_name`         VARCHAR(100) DEFAULT NULL,
    `vehicle`             VARCHAR(50)  DEFAULT NULL,
    `notes`               TEXT         DEFAULT NULL,
    `delivered_by`        INT(11)      DEFAULT NULL,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`distribution_id`),
    UNIQUE KEY `uk_distributions_number` (`distribution_number`),
    KEY `idx_distributions_date_status` (`distribution_date`, `status`),
    KEY `idx_distributions_customer` (`customer_id`),
    KEY `idx_distributions_order` (`order_id`),
    CONSTRAINT `fk_distributions_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_distributions_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_distributions_delivered_by`
        FOREIGN KEY (`delivered_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `distribution_items` (
    `distribution_item_id` INT(11) NOT NULL AUTO_INCREMENT,
    `distribution_id`      INT(11) NOT NULL,
    `order_item_id`        INT(11) DEFAULT NULL,
    `grade_id`             INT(11) NOT NULL,
    `quantity`             INT(11) NOT NULL,
    PRIMARY KEY (`distribution_item_id`),
    KEY `idx_distribution_items_distribution` (`distribution_id`),
    KEY `idx_distribution_items_order_item` (`order_item_id`),
    KEY `idx_distribution_items_grade` (`grade_id`),
    CONSTRAINT `fk_distribution_items_distribution`
        FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`distribution_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_distribution_items_order_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_distribution_items_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 13. Waste and audit
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `waste_records` (
    `waste_id`        INT(11)       NOT NULL AUTO_INCREMENT,
    `waste_date`      DATE          NOT NULL,
    `waste_type`      ENUM('egg','supply','medicine','other') NOT NULL,
    `grade_id`        INT(11)       DEFAULT NULL,
    `inventory_id`    INT(11)       DEFAULT NULL,
    `batch_id`        INT(11)       DEFAULT NULL,
    `quantity`        DECIMAL(10,2) NOT NULL,
    `unit`            VARCHAR(20)   DEFAULT NULL,
    `reason`          ENUM('broken','expired','spoiled','damaged','cull','other') NOT NULL DEFAULT 'other',
    `estimated_cost`  DECIMAL(14,2) DEFAULT NULL,
    `expense_id`      INT(11)       DEFAULT NULL,
    `recorded_by`     INT(11)       DEFAULT NULL,
    `remarks`         TEXT          DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`waste_id`),
    KEY `idx_waste_records_date` (`waste_date`),
    KEY `idx_waste_records_type` (`waste_type`),
    KEY `idx_waste_records_grade` (`grade_id`),
    KEY `idx_waste_records_inventory` (`inventory_id`),
    KEY `idx_waste_records_batch` (`batch_id`),
    CONSTRAINT `fk_waste_records_grade`
        FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_waste_records_inventory`
        FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_waste_records_batch`
        FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_waste_records_expense`
        FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_waste_records_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id`      BIGINT(20)   NOT NULL AUTO_INCREMENT,
    `user_id`     INT(11)      DEFAULT NULL,
    `action`      ENUM('create','update','delete','login','logout') NOT NULL,
    `table_name`  VARCHAR(64)  NOT NULL,
    `record_id`   INT(11)      DEFAULT NULL,
    `old_values`  JSON         DEFAULT NULL,
    `new_values`  JSON         DEFAULT NULL,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_table_record` (`table_name`, `record_id`),
    KEY `idx_audit_logs_created_at` (`created_at`),
    CONSTRAINT `fk_audit_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 14. Reporting views (computed; no duplicate fact storage)
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_egg_inventory_balance` AS
SELECT
    g.`grade_id`,
    g.`grade_code`,
    g.`grade_name`,
    COALESCE(SUM(
        CASE eim.`movement_type`
            WHEN 'production_in'        THEN eim.`quantity`
            WHEN 'adjustment_in'        THEN eim.`quantity`
            WHEN 'reservation_release'  THEN eim.`quantity`
            WHEN 'order_alloc'          THEN -eim.`quantity`
            WHEN 'order_out'            THEN -eim.`quantity`
            WHEN 'reservation_hold'     THEN -eim.`quantity`
            WHEN 'distribution_out'     THEN -eim.`quantity`
            WHEN 'waste_out'            THEN -eim.`quantity`
            WHEN 'adjustment_out'       THEN -eim.`quantity`
            ELSE 0
        END
    ), 0) AS `on_hand_qty`
FROM `egg_grades` g
LEFT JOIN `egg_inventory_movements` eim ON eim.`grade_id` = g.`grade_id`
WHERE g.`is_active` = 1
GROUP BY g.`grade_id`, g.`grade_code`, g.`grade_name`;

CREATE OR REPLACE VIEW `v_egg_reserved_qty` AS
SELECT
    ri.`grade_id`,
    COALESCE(SUM(
        CASE
            WHEN r.`status` = 'active'
            THEN GREATEST(ri.`quantity` - ri.`quantity_fulfilled`, 0)
            ELSE 0
        END
    ), 0) AS `reserved_from_reservations`
FROM `reservation_items` ri
INNER JOIN `reservations` r ON r.`reservation_id` = ri.`reservation_id`
GROUP BY ri.`grade_id`;

CREATE OR REPLACE VIEW `v_egg_order_commitment` AS
SELECT
    oi.`grade_id`,
    COALESCE(SUM(
        CASE
            WHEN o.`status` IN ('pending','confirmed','processing','partially_delivered')
            THEN GREATEST(oi.`quantity_ordered` - oi.`quantity_delivered`, 0)
            ELSE 0
        END
    ), 0) AS `committed_from_orders`
FROM `order_items` oi
INNER JOIN `orders` o ON o.`order_id` = oi.`order_id`
GROUP BY oi.`grade_id`;

CREATE OR REPLACE VIEW `v_demand_summary` AS
SELECT
    g.`grade_id`,
    g.`grade_code`,
    g.`grade_name`,
    COALESCE(oc.`committed_from_orders`, 0) AS `order_demand_qty`,
    COALESCE(rr.`reserved_from_reservations`, 0) AS `reservation_demand_qty`,
    COALESCE(oc.`committed_from_orders`, 0) + COALESCE(rr.`reserved_from_reservations`, 0) AS `total_demand_qty`
FROM `egg_grades` g
LEFT JOIN `v_egg_order_commitment` oc ON oc.`grade_id` = g.`grade_id`
LEFT JOIN `v_egg_reserved_qty` rr ON rr.`grade_id` = g.`grade_id`
WHERE g.`is_active` = 1;

CREATE OR REPLACE VIEW `v_supply_summary` AS
SELECT
    b.`grade_id`,
    b.`grade_code`,
    b.`grade_name`,
    b.`on_hand_qty`,
    COALESCE(p.`avg_daily_production_7d`, 0) AS `avg_daily_production_7d`,
    COALESCE(p.`avg_daily_production_7d`, 0) * 7 AS `projected_production_7d`
FROM `v_egg_inventory_balance` b
LEFT JOIN (
    SELECT
        ep.`grade_id`,
        ROUND(SUM(ep.`quantity_collected` - ep.`quantity_broken`) / 7, 2) AS `avg_daily_production_7d`
    FROM `egg_production` ep
    WHERE ep.`production_date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY ep.`grade_id`
) p ON p.`grade_id` = b.`grade_id`;

CREATE OR REPLACE VIEW `v_demand_supply_gap` AS
SELECT
    s.`grade_id`,
    s.`grade_code`,
    s.`grade_name`,
    s.`on_hand_qty` AS `supply_on_hand_qty`,
    d.`total_demand_qty` AS `demand_committed_qty`,
    s.`on_hand_qty` - d.`total_demand_qty` AS `uncommitted_on_hand_qty`,
    s.`on_hand_qty` - d.`total_demand_qty` AS `supply_demand_gap`
FROM `v_supply_summary` s
INNER JOIN `v_demand_summary` d ON d.`grade_id` = s.`grade_id`;

CREATE OR REPLACE VIEW `v_expiring_lots` AS
SELECT
    il.`lot_id`,
    il.`inventory_id`,
    i.`item_name`,
    i.`category`,
    il.`lot_number`,
    il.`quantity_remaining`,
    il.`expiry_date`,
    DATEDIFF(il.`expiry_date`, CURDATE()) AS `days_until_expiry`
FROM `inventory_lots` il
INNER JOIN `inventory` i ON i.`inventory_id` = il.`inventory_id`
WHERE il.`quantity_remaining` > 0
  AND il.`expiry_date` IS NOT NULL;

CREATE OR REPLACE VIEW `v_egg_available_qty` AS
SELECT
    b.`grade_id`,
    b.`grade_code`,
    b.`grade_name`,
    b.`on_hand_qty`,
    COALESCE(d.`total_demand_qty`, 0) AS `committed_qty`,
    b.`on_hand_qty` - COALESCE(d.`total_demand_qty`, 0) AS `available_qty`
FROM `v_egg_inventory_balance` b
LEFT JOIN `v_demand_summary` d ON d.`grade_id` = b.`grade_id`;

-- ---------------------------------------------------------------------------
-- 15. Record migration + cleanup helpers
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `schema_migrations` (`migration_name`)
VALUES ('integrated_poultry_upgrade');

DROP PROCEDURE IF EXISTS migration_add_column;
DROP PROCEDURE IF EXISTS migration_add_index;
DROP PROCEDURE IF EXISTS migration_add_fk;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of migration
-- =============================================================================
