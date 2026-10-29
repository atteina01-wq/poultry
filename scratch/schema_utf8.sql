CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('Feeding','Cleaning','Health Inspection','Health','Supply','General') NOT NULL DEFAULT 'General',
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_activities_user` (`user_id`),
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete','login','logout') NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=657 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `production_date` date DEFAULT NULL,
  `initial_quantity` int(11) DEFAULT NULL,
  `current_quantity` int(11) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `status` enum('active','harvested','closed') DEFAULT 'active',
  `flock_type` enum('broiler','layer') NOT NULL DEFAULT 'broiler',
  `breed` varchar(100) DEFAULT NULL,
  `house_pen` varchar(100) DEFAULT NULL,
  `expected_harvest_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_batches_status_type` (`status`,`flock_type`),
  KEY `idx_batches_flock_type` (`flock_type`),
  KEY `fk_batches_created_by` (`created_by`),
  CONSTRAINT `fk_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cleaning_records` (
  `cleaning_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`cleaning_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `cleaning_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_type` enum('retail','wholesale','institutional') NOT NULL DEFAULT 'retail',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uk_customers_code` (`customer_code`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `distribution_items` (
  `distribution_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`distribution_item_id`),
  KEY `idx_distribution_items_distribution` (`distribution_id`),
  KEY `idx_distribution_items_order_item` (`order_item_id`),
  KEY `idx_distribution_items_grade` (`grade_id`),
  CONSTRAINT `fk_distribution_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`distribution_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_distribution_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_distribution_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `distributions` (
  `distribution_id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_number` varchar(30) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `distribution_date` date NOT NULL,
  `status` enum('scheduled','in_transit','delivered','cancelled') NOT NULL DEFAULT 'scheduled',
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `delivered_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`distribution_id`),
  UNIQUE KEY `uk_distributions_number` (`distribution_number`),
  KEY `idx_distributions_date_status` (`distribution_date`,`status`),
  KEY `idx_distributions_customer` (`customer_id`),
  KEY `idx_distributions_order` (`order_id`),
  KEY `fk_distributions_delivered_by` (`delivered_by`),
  CONSTRAINT `fk_distributions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_distributions_delivered_by` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_distributions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `egg_grades` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(20) NOT NULL,
  `grade_name` varchar(50) NOT NULL,
  `price_per_piece` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pieces_per_tray` int(11) NOT NULL DEFAULT 30,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`grade_id`),
  UNIQUE KEY `uk_egg_grades_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `egg_inventory_movements` (
  `movement_id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_date` datetime NOT NULL DEFAULT current_timestamp(),
  `grade_id` int(11) NOT NULL,
  `movement_type` enum('production_in','order_alloc','order_out','order_return','order_cancellation','reservation_hold','reservation_release','distribution_out','waste_out','adjustment_in','adjustment_out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('production','order','reservation','distribution','waste','adjustment') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`movement_id`),
  KEY `idx_eim_grade_date` (`grade_id`,`movement_date`),
  KEY `idx_eim_reference` (`reference_type`,`reference_id`),
  KEY `idx_eim_batch` (`batch_id`),
  KEY `idx_eim_type` (`movement_type`),
  KEY `fk_eim_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_eim_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_eim_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_eim_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `egg_production` (
  `production_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity_collected` int(11) NOT NULL DEFAULT 0,
  `quantity_broken` int(11) NOT NULL DEFAULT 0,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`production_id`),
  UNIQUE KEY `uk_egg_production_batch_date_grade` (`batch_id`,`production_date`,`grade_id`),
  KEY `idx_egg_production_date` (`production_date`),
  KEY `idx_egg_production_grade` (`grade_id`),
  KEY `fk_egg_production_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_egg_production_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egg_production_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egg_production_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `category` enum('feed','medicine','supply','limestone','waste','labor','utilities','other') DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `source_type` enum('manual','feed_consumption','health_record','inventory_receipt','waste','order','distribution') DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_expenses_date` (`date`),
  KEY `idx_expenses_source` (`source_type`,`source_id`),
  KEY `idx_expenses_batch_date` (`batch_id`,`date`),
  KEY `fk_expenses_inventory` (`inventory_id`),
  KEY `fk_expenses_recorded_by` (`recorded_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  CONSTRAINT `fk_expenses_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `feed_consumption` (
  `consumption_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `feed_stage` enum('Pre-Starter','Starter','Grower','Finisher') DEFAULT NULL,
  `quantity_used` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `unit_cost_snapshot` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`consumption_id`),
  KEY `batch_id` (`batch_id`),
  KEY `inventory_id` (`inventory_id`),
  KEY `idx_feed_consumption_batch_date` (`batch_id`,`date`),
  KEY `fk_feed_consumption_recorded_by` (`recorded_by`),
  CONSTRAINT `feed_consumption_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  CONSTRAINT `feed_consumption_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  CONSTRAINT `fk_feed_consumption_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `health_records` (
  `health_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `type` enum('vaccination','treatment') DEFAULT NULL,
  `medicine_name` varchar(100) DEFAULT NULL,
  `illness_observation` varchar(255) DEFAULT NULL,
  `doctor_consultation` varchar(150) DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `quantity_used` decimal(10,2) DEFAULT 0.00,
  `date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'completed',
  `recorded_by` int(11) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`health_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_health_records_batch_date` (`batch_id`,`date`),
  KEY `idx_health_records_next_due` (`next_due_date`),
  KEY `idx_health_records_inventory` (`inventory_id`),
  KEY `fk_health_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_health_records_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_health_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `category` enum('feed','medicine','supply','limestone','vitamins') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `previous_quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sku` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uk_inventory_item_category` (`item_name`,`category`),
  KEY `idx_inventory_category` (`category`),
  KEY `idx_inventory_active_category` (`is_active`,`category`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `inventory_lots` (
  `lot_id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `lot_number` varchar(50) NOT NULL,
  `quantity_received` decimal(10,2) NOT NULL,
  `quantity_remaining` decimal(10,2) NOT NULL,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lot_id`),
  UNIQUE KEY `uk_inventory_lots_inventory_lot` (`inventory_id`,`lot_number`),
  KEY `idx_inventory_lots_inventory` (`inventory_id`),
  KEY `idx_inventory_lots_expiry` (`expiry_date`,`quantity_remaining`),
  CONSTRAINT `fk_inventory_lots_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `mortality_records` (
  `mortality_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mortality_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_mortality_records_batch_date` (`batch_id`,`date`),
  KEY `fk_mortality_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_mortality_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `mortality_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `type` enum('low_stock','vaccination','general','expiry','mortality','order_due','reservation_due','low_egg_stock','demand_supply_gap') DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `status` enum('unread','read') DEFAULT 'unread',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_user_status` (`user_id`,`status`),
  KEY `idx_notifications_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_delivered` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_grade` (`grade_id`),
  CONSTRAINT `fk_order_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_return_items` (
  `return_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `condition_status` enum('good','damaged','expired') NOT NULL DEFAULT 'good',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`return_item_id`),
  KEY `return_id` (`return_id`),
  KEY `grade_id` (`grade_id`),
  CONSTRAINT `order_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `order_returns` (`return_id`) ON DELETE CASCADE,
  CONSTRAINT `order_return_items_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_returns` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `return_number` varchar(30) NOT NULL,
  `return_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) NOT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `order_id` (`order_id`),
  KEY `returned_by` (`returned_by`),
  CONSTRAINT `order_returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  CONSTRAINT `order_returns_ibfk_2` FOREIGN KEY (`returned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `status` enum('draft','pending','confirmed','processing','partially_delivered','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_orders_number` (`order_number`),
  KEY `idx_orders_customer_status` (`customer_id`,`status`),
  KEY `idx_orders_required_date` (`required_date`),
  KEY `idx_orders_order_date` (`order_date`),
  KEY `fk_orders_created_by` (`created_by`),
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reservation_items` (
  `reservation_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_fulfilled` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`reservation_item_id`),
  KEY `idx_reservation_items_reservation` (`reservation_id`),
  KEY `idx_reservation_items_grade` (`grade_id`),
  CONSTRAINT `fk_reservation_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservation_items_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_birds` int(11) DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `advance_payment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `actual_pickup_date` date DEFAULT NULL,
  `accepted_quantity` int(11) NOT NULL DEFAULT 0,
  `rejected_quantity` int(11) NOT NULL DEFAULT 0,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `pickup_date` date NOT NULL,
  `status` enum('active','fulfilled','cancelled','expired') NOT NULL DEFAULT 'active',
  `converted_order_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uk_reservations_number` (`reservation_number`),
  KEY `idx_reservations_pickup_status` (`pickup_date`,`status`),
  KEY `idx_reservations_customer` (`customer_id`),
  KEY `fk_reservations_order` (`converted_order_id`),
  KEY `fk_reservations_created_by` (`created_by`),
  CONSTRAINT `fk_reservations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_order` FOREIGN KEY (`converted_order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_chickens` int(11) DEFAULT NULL,
  `total_weight` decimal(10,2) DEFAULT NULL,
  `price_per_kg` decimal(14,2) DEFAULT NULL,
  `total_revenue` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`sale_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `schema_migrations` (
  `migration_id` int(11) NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration_id`),
  UNIQUE KEY `uk_schema_migrations_name` (`migration_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `batch_id` int(11) DEFAULT NULL,
  `task_type` enum('daily','one_time') DEFAULT 'one_time',
  `last_reset_date` date DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`),
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_active_role` (`is_active`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `waste_records` (
  `waste_id` int(11) NOT NULL AUTO_INCREMENT,
  `waste_date` date NOT NULL,
  `waste_type` enum('egg','supply','medicine','other') NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `reason` enum('broken','expired','spoiled','damaged','cull','other') NOT NULL DEFAULT 'other',
  `estimated_cost` decimal(14,2) DEFAULT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`waste_id`),
  KEY `idx_waste_records_date` (`waste_date`),
  KEY `idx_waste_records_type` (`waste_type`),
  KEY `idx_waste_records_grade` (`grade_id`),
  KEY `idx_waste_records_inventory` (`inventory_id`),
  KEY `idx_waste_records_batch` (`batch_id`),
  KEY `fk_waste_records_expense` (`expense_id`),
  KEY `fk_waste_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_waste_records_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

