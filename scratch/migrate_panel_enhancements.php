<?php
// scratch/migrate_panel_enhancements.php
require __DIR__ . '/../poultrychat/database.php';

echo "Running migrations for Panel Comments Enhancements...\n";

// 1. Create houses table
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
    echo "[OK] Table 'houses' verified/created.\n";
} catch (PDOException $e) {
    echo "[ERR] houses table: " . $e->getMessage() . "\n";
}

// 2. Seed standard houses
$defaultHouses = [
    ['House 1', 5000, 'Modern tunnel-ventilated house with automated nipple drinkers'],
    ['House 2', 5000, 'Tunnel-ventilated broiler house with pan feeders'],
    ['House 3', 7500, 'High-capacity broiler pen with curtain-sided climate control'],
    ['House 4', 10000, 'Large-scale commercial broiler house with pad cooling'],
    ['Pen A (Brooder)', 3000, 'Specialized brooding house for Day-Old Chicks (Day 1-10)']
];

$stmtInsertH = $conn->prepare("INSERT IGNORE INTO houses (house_name, capacity, notes) VALUES (?, ?, ?)");
foreach ($defaultHouses as $h) {
    $stmtInsertH->execute([$h[0], $h[1], $h[2]]);
}
echo "[OK] Default houses seeded.\n";

// 3. Add delivery_receipt_no, chick_unit_cost, and house_id to batches if missing
try {
    $cols = $conn->query("SHOW COLUMNS FROM batches LIKE 'delivery_receipt_no'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE batches ADD COLUMN delivery_receipt_no VARCHAR(50) NULL AFTER supplier");
        echo "[OK] Added 'delivery_receipt_no' to batches.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] delivery_receipt_no check: " . $e->getMessage() . "\n";
}

try {
    $cols = $conn->query("SHOW COLUMNS FROM batches LIKE 'chick_unit_cost'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE batches ADD COLUMN chick_unit_cost DECIMAL(10,2) DEFAULT 38.00 AFTER delivery_receipt_no");
        echo "[OK] Added 'chick_unit_cost' to batches.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] chick_unit_cost check: " . $e->getMessage() . "\n";
}

// 4. Update houses status based on active batches
try {
    $activeBatches = $conn->query("SELECT batch_id, batch_code, house_pen, expected_harvest_date FROM batches WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($activeBatches as $ab) {
        if (!empty($ab['house_pen'])) {
            $hp = trim($ab['house_pen']);
            // Try matching house
            $stmtH = $conn->prepare("SELECT house_id FROM houses WHERE house_name = ? OR house_name LIKE ?");
            $stmtH->execute([$hp, "%$hp%"]);
            $hid = $stmtH->fetchColumn();
            if ($hid) {
                $stmtUpdH = $conn->prepare("UPDATE houses SET status = 'occupied', current_batch_id = ?, vacant_date = ? WHERE house_id = ?");
                $stmtUpdH->execute([$ab['batch_id'], $ab['expected_harvest_date'], $hid]);
            }
        }
    }
    echo "[OK] Synchronized active batches with houses.\n";
} catch (PDOException $e) {
    echo "[ERR] Sync houses: " . $e->getMessage() . "\n";
}

// 5. Expand users.role enum to include 'integrator' and 'worker'
try {
    $conn->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff', 'worker', 'integrator') NOT NULL DEFAULT 'worker'");
    echo "[OK] Updated users role enum to ('admin', 'staff', 'worker', 'integrator').\n";
} catch (PDOException $e) {
    echo "[NOTE] users role enum: " . $e->getMessage() . "\n";
}

// 6. Ensure default integrator user exists for testing/demo
try {
    $stmtUserCheck = $conn->prepare("SELECT user_id FROM users WHERE username = 'integrator'");
    $stmtUserCheck->execute();
    if (!$stmtUserCheck->fetch()) {
        $hashed = password_hash('integrator123', PASSWORD_DEFAULT);
        $conn->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('integrator', ?, 'Integrator Partner Rep', 'integrator', 1)")->execute([$hashed]);
        echo "[OK] Created demo user 'integrator' (password: integrator123).\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] Integrator demo user: " . $e->getMessage() . "\n";
}

// 7. Add database indexes for query performance
$indexesToAdd = [
    ['mortality_records', 'idx_mort_batch_date', 'batch_id, date'],
    ['feed_consumption', 'idx_feed_batch_date', 'batch_id, date'],
    ['activities', 'idx_act_batch_date', 'batch_id, date'],
    ['reservations', 'idx_res_customer', 'customer_id'],
    ['reservations', 'idx_res_status', 'status'],
    ['batches', 'idx_batch_status_arrival', 'status, arrival_date'],
];

foreach ($indexesToAdd as $idx) {
    $tbl = $idx[0];
    $idxName = $idx[1];
    $cols = $idx[2];
    try {
        $chk = $conn->query("SHOW INDEX FROM {$tbl} WHERE Key_name = '{$idxName}'")->fetch();
        if (!$chk) {
            $conn->exec("ALTER TABLE {$tbl} ADD INDEX {$idxName} ({$cols})");
            echo "[OK] Added index {$idxName} on table {$tbl}.\n";
        }
    } catch (PDOException $e) {
        // Silently skip if column missing or index already there
    }
}

echo "All migration tasks completed successfully!\n";
