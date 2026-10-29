<?php
// db_check.php - Ensures all tables exist in the database with session cache

// Skip redundant schema verification if already verified in this session
if (!empty($_SESSION['db_schema_verified'])) {
    return;
}

$tables_needed = ['batches', 'inventory', 'feed_consumption', 'health_records', 'mortality_records', 'activities', 'cleaning_records', 'users', 'tasks', 'expenses', 'notifications'];
$missing_tables = false;

foreach ($tables_needed as $table) {
    try {
        $result = $conn->query("SELECT 1 FROM $table LIMIT 1");
    } catch (PDOException $e) {
        $missing_tables = true;
        break;
    }
}

if ($missing_tables) {
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        role ENUM('admin', 'worker') NOT NULL DEFAULT 'worker'
    );
    
    -- Insert default users if they don't exist
    -- Note: In a real app, use password_hash()
    -- Admin: admin/admin123, Worker: worker/worker123
    INSERT IGNORE INTO users (username, password, full_name, role) VALUES 
    ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'System Admin', 'admin'),
    ('worker', '" . password_hash('worker123', PASSWORD_DEFAULT) . "', 'Farm Worker', 'worker');

    CREATE TABLE IF NOT EXISTS batches (
        batch_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_code VARCHAR(50) NOT NULL,
        arrival_date DATE NOT NULL,
        initial_quantity INT NOT NULL,
        current_quantity INT NOT NULL,
        supplier VARCHAR(100),
        status VARCHAR(20) DEFAULT 'active'
    );

    CREATE TABLE IF NOT EXISTS inventory (
        inventory_id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(100) NOT NULL,
        category ENUM('feed', 'medicine', 'supply') NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        previous_quantity DECIMAL(10,2) DEFAULT 0,
        unit VARCHAR(20),
        unit_cost DECIMAL(10,2)
    );


    CREATE TABLE IF NOT EXISTS feed_consumption (
        consumption_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        inventory_id INT,
        quantity_used DECIMAL(10,2),
        date DATE NOT NULL,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id),
        FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id)
    );

    CREATE TABLE IF NOT EXISTS health_records (
        health_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        type ENUM('vaccination', 'treatment'),
        medicine_name VARCHAR(100),
        quantity_used INT,
        date DATE,
        notes TEXT,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id)
    );

    CREATE TABLE IF NOT EXISTS mortality_records (
        mortality_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        date DATE NOT NULL,
        quantity_change INT NOT NULL,
        reason VARCHAR(255),
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id)
    );

    CREATE TABLE IF NOT EXISTS activities (
        activity_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        user_id INT NULL,
        type ENUM('Feeding', 'Cleaning', 'Health Inspection', 'Health', 'Supply', 'General') NOT NULL DEFAULT 'General',
        item_name VARCHAR(100),
        quantity DECIMAL(10,2),
        date DATE NOT NULL,
        remarks TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS cleaning_records (
        cleaning_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        date DATE NOT NULL,
        remarks TEXT,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id)
    );

    CREATE TABLE IF NOT EXISTS tasks (
        task_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        assigned_to INT,
        created_by INT,
        batch_id INT,
        due_date DATE,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        task_type ENUM('daily', 'one_time') DEFAULT 'one_time',
        last_reset_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id),
        FOREIGN KEY (created_by) REFERENCES users(user_id),
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id)
    );

    CREATE TABLE IF NOT EXISTS expenses (
        expense_id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT,
        category ENUM('feed', 'medicine', 'other'),
        amount DECIMAL(10,2),
        description VARCHAR(255),
        date DATE,
        FOREIGN KEY (batch_id) REFERENCES batches(batch_id)
    );

    CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        message VARCHAR(255),
        type ENUM('low_stock', 'vaccination', 'general'),
        status ENUM('unread', 'read') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    try {
        $conn->exec($sql);
    } catch(PDOException $e) {
        // Log or handle error silently as we don't want to break the UI
    }
}

// Ensure tasks table has batch_id
try {
    $conn->query("SELECT batch_id FROM tasks LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE tasks ADD COLUMN batch_id INT, ADD FOREIGN KEY (batch_id) REFERENCES batches(batch_id)");
    } catch (PDOException $ex) {}
}

// Ensure tasks table has task_type and last_reset_date
try {
    $conn->query("SELECT task_type FROM tasks LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE tasks ADD COLUMN task_type ENUM('daily', 'one_time') DEFAULT 'one_time', ADD COLUMN last_reset_date DATE");
    } catch (PDOException $ex) {}
}

// Ensure activities type allows operational task categories (Cleaning, Weight Sampling, Supply, General)
try {
    $conn->exec("ALTER TABLE activities MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'General'");
} catch (PDOException $e) {}

// Ensure activities has user_id
try {
    $conn->query("SELECT user_id FROM activities LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE activities ADD COLUMN user_id INT NULL AFTER batch_id, ADD INDEX idx_activities_user (user_id)");
    } catch (PDOException $ex) {}
}

// Ensure activities has created_at
try {
    $conn->query("SELECT created_at FROM activities LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE activities ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    } catch (PDOException $ex) {}
}

// Ensure inventory table has previous_quantity
try {
    $conn->query("SELECT previous_quantity FROM inventory LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE inventory ADD COLUMN previous_quantity DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $ex) {}
}

// Ensure houses table exists
try {
    $conn->query("SELECT 1 FROM houses LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("
        CREATE TABLE IF NOT EXISTS houses (
            house_id INT AUTO_INCREMENT PRIMARY KEY,
            house_name VARCHAR(50) NOT NULL UNIQUE,
            capacity INT NOT NULL DEFAULT 5000,
            status ENUM('available', 'occupied', 'cleaning', 'maintenance') DEFAULT 'available',
            current_batch_id INT NULL,
            vacant_date DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_house_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $conn->exec("
        INSERT IGNORE INTO houses (house_name, capacity, notes) VALUES 
        ('House 1', 5000, 'Tunnel-ventilated broiler house with pan feeders'),
        ('House 2', 5000, 'Tunnel-ventilated broiler house with automated nipple drinkers'),
        ('House 3', 7500, 'High-capacity broiler pen with curtain-sided climate control'),
        ('House 4', 10000, 'Commercial scale broiler house with pad cooling'),
        ('House 5', 3000, 'Broiler grow-out house with heating and ventilation control'),
        ('House 6', 5000, 'Modern tunnel-ventilated broiler house with automated climate control');
        ");
    } catch (PDOException $ex) {}
}

// Ensure batches has contract grower fields
try {
    $conn->query("SELECT delivery_receipt_no FROM batches LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE batches ADD COLUMN delivery_receipt_no VARCHAR(50) NULL, ADD COLUMN chick_unit_cost DECIMAL(10,2) DEFAULT 38.00");
    } catch (PDOException $ex) {}
}

// Ensure reservations has chick_cost field for configurable day-old chick price
try {
    $conn->query("SELECT chick_cost FROM reservations LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE reservations ADD COLUMN chick_cost DECIMAL(10,2) DEFAULT 38.00 AFTER unit_price");
    } catch (PDOException $ex) {}
}

// Safety check for users table
try {
    $conn->query("SELECT username FROM users LIMIT 1");
} catch (PDOException $e) {
    error_log("db_check.php: users table exists but is missing expected columns. Destructive rebuild skipped to protect user data.");
}

// Ensure users table has customer_id column for integrator company binding
try {
    $conn->query("SELECT customer_id FROM users LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN customer_id INT NULL AFTER role, ADD INDEX idx_users_customer (customer_id)");
    } catch (PDOException $ex) {}
}

// Ensure demo integrator user is linked to San Miguel Foods Inc.
try {
    $conn->exec("UPDATE users SET customer_id = 7 WHERE username = 'integrator' AND (customer_id IS NULL OR customer_id = 0)");
} catch (PDOException $ex) {}

// Ensure feed_consumption has status, completed_by, and completed_at columns
try {
    $conn->query("SELECT status FROM feed_consumption LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE feed_consumption ADD COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'completed' AFTER feed_stage");
    } catch (PDOException $ex) {}
}

try {
    $conn->query("SELECT completed_by FROM feed_consumption LIMIT 1");
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE feed_consumption ADD COLUMN completed_by INT NULL AFTER recorded_by, ADD COLUMN completed_at TIMESTAMP NULL AFTER completed_by, ADD INDEX idx_fc_completed_by (completed_by)");
    } catch (PDOException $ex) {}
}

// 1. Database Cleanup: Ensure poultry_db_scratch is dropped if present
try {
    $conn->exec("DROP DATABASE IF EXISTS poultry_db_scratch");
} catch (Exception $ex) {}

// 2. High-Performance Indexing Optimization across high volume of records
$tableIndexes = [
    'reservations' => [
        'idx_reservations_workflow_stage' => 'workflow_stage',
        'idx_reservations_batch_id'       => 'batch_id',
        'idx_reservations_status_wf'      => 'status, workflow_stage',
        'idx_reservations_created_at'     => 'created_at'
    ],
    'batches' => [
        'idx_batches_house_pen'           => 'house_pen',
        'idx_batches_arrival_date'        => 'arrival_date',
        'idx_batches_harvest_date'        => 'expected_harvest_date',
        'idx_batches_created_at'          => 'created_at'
    ],
    'houses' => [
        'idx_houses_current_batch'        => 'current_batch_id'
    ],
    'cleaning_records' => [
        'idx_cleaning_records_date'       => 'date'
    ],
    'tasks' => [
        'idx_tasks_status_due'            => 'status, due_date',
        'idx_tasks_created_at'            => 'created_at'
    ],
    'notifications' => [
        'idx_notif_user_status_created'   => 'user_id, status, created_at'
    ],
    'audit_logs' => [
        'idx_audit_logs_table_created'    => 'table_name, created_at'
    ],
    'health_records' => [
        'idx_health_records_date'         => 'date',
        'idx_health_records_status'       => 'status'
    ],
    'mortality_records' => [
        'idx_mortality_records_date'      => 'date'
    ],
    'feed_consumption' => [
        'idx_feed_consumption_date'       => 'date'
    ],
    'expenses' => [
        'idx_expenses_date'               => 'date'
    ]
];

foreach ($tableIndexes as $tbl => $indexes) {
    try {
        $existing = $conn->query("SHOW INDEX FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        $existingNames = array_column($existing, 'Key_name');
        foreach ($indexes as $idxName => $idxCols) {
            if (!in_array($idxName, $existingNames)) {
                try {
                    $conn->exec("ALTER TABLE `{$tbl}` ADD INDEX `{$idxName}` ({$idxCols})");
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $e) {}
}

// Mark verified in session
$_SESSION['db_schema_verified'] = true;
?>

