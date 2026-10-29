<?php
require __DIR__ . '/../poultrychat/database.php';

echo "Running migrations for House Reservation & Agreed Weight Release Verification...\n";

// 1. Add house_id and target_weight_per_bird to reservations
try {
    $cols = $conn->query("SHOW COLUMNS FROM reservations LIKE 'house_id'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN house_id INT NULL AFTER batch_id, ADD INDEX idx_res_house (house_id)");
        echo "[OK] Added 'house_id' to reservations.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] house_id check: " . $e->getMessage() . "\n";
}

try {
    $cols = $conn->query("SHOW COLUMNS FROM reservations LIKE 'target_weight_per_bird'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN target_weight_per_bird DECIMAL(5,2) DEFAULT 1.80 AFTER weight_kg");
        echo "[OK] Added 'target_weight_per_bird' to reservations.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] target_weight_per_bird check: " . $e->getMessage() . "\n";
}

// 2. Add target_weight_kg and current_weight_kg to batches
try {
    $cols = $conn->query("SHOW COLUMNS FROM batches LIKE 'target_weight_kg'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE batches ADD COLUMN target_weight_kg DECIMAL(5,2) DEFAULT 1.80 AFTER chick_unit_cost");
        echo "[OK] Added 'target_weight_kg' to batches.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] target_weight_kg check: " . $e->getMessage() . "\n";
}

try {
    $cols = $conn->query("SHOW COLUMNS FROM batches LIKE 'current_weight_kg'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE batches ADD COLUMN current_weight_kg DECIMAL(5,2) DEFAULT 0.00 AFTER target_weight_kg");
        echo "[OK] Added 'current_weight_kg' to batches.\n";
    }
} catch (PDOException $e) {
    echo "[NOTE] current_weight_kg check: " . $e->getMessage() . "\n";
}

echo "Migration complete!\n";
