<?php
require_once __DIR__ . '/../poultrychat/database.php';

try {
    $conn->exec("ALTER TABLE reservations 
        ADD COLUMN IF NOT EXISTS batch_id INT NULL AFTER customer_id,
        ADD COLUMN IF NOT EXISTS quantity_birds INT NULL AFTER batch_id,
        ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,2) NULL AFTER quantity_birds,
        ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL AFTER weight_kg,
        ADD COLUMN IF NOT EXISTS total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER unit_price,
        ADD COLUMN IF NOT EXISTS advance_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_amount,
        ADD COLUMN IF NOT EXISTS actual_pickup_date DATE NULL AFTER advance_payment,
        ADD COLUMN IF NOT EXISTS accepted_quantity INT NOT NULL DEFAULT 0 AFTER actual_pickup_date,
        ADD COLUMN IF NOT EXISTS rejected_quantity INT NOT NULL DEFAULT 0 AFTER accepted_quantity,
        ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER rejected_quantity
    ");
    echo "SUCCESS: reservations table updated with broiler fields." . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

$cols = $conn->query("SHOW COLUMNS FROM reservations")->fetchAll(PDO::FETCH_COLUMN);
echo "Current reservations columns: " . implode(', ', $cols) . PHP_EOL;
