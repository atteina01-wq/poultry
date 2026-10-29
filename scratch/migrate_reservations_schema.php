<?php
require_once __DIR__ . '/../poultrychat/database.php';

try {
    $cols = $conn->query("DESCRIBE reservations")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('breed', $cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN breed VARCHAR(100) NULL DEFAULT 'Cobb 500' AFTER target_weight_per_bird");
        echo "Added 'breed' column to reservations.\n";
    } else {
        echo "'breed' column already exists in reservations.\n";
    }

    if (!in_array('is_dispatched', $cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN is_dispatched TINYINT(1) NOT NULL DEFAULT 0 AFTER actual_pickup_date");
        echo "Added 'is_dispatched' column to reservations.\n";
    } else {
        echo "'is_dispatched' column already exists in reservations.\n";
    }

    if (!in_array('dispatched_at', $cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN dispatched_at DATETIME NULL AFTER is_dispatched");
        echo "Added 'dispatched_at' column to reservations.\n";
    } else {
        echo "'dispatched_at' column already exists in reservations.\n";
    }

    if (!in_array('dispatched_by', $cols)) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN dispatched_by INT NULL AFTER dispatched_at");
        echo "Added 'dispatched_by' column to reservations.\n";
    } else {
        echo "'dispatched_by' column already exists in reservations.\n";
    }

    echo "=== SCHEMA MIGRATION SUCCESSFUL ===\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
