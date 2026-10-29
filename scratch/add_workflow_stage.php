<?php
require_once __DIR__ . '/../poultrychat/database.php';
$cols = $conn->query("SHOW COLUMNS FROM reservations LIKE 'workflow_stage'")->fetchAll();
if (empty($cols)) {
    $conn->exec("ALTER TABLE reservations ADD COLUMN workflow_stage ENUM('pending_approval', 'approved', 'receiving', 'receipt_confirmed', 'growing') NOT NULL DEFAULT 'growing' AFTER status");
    echo "[OK] Added workflow_stage column to reservations.\n";
} else {
    echo "[INFO] workflow_stage already exists.\n";
}

// For existing active reservations:
// Res 104 has batch 58 which is active, so workflow_stage is 'growing'
$conn->exec("UPDATE reservations SET workflow_stage = 'growing' WHERE batch_id IS NOT NULL AND status = 'active'");
echo "[OK] Updated active reservations with batches to 'growing'.\n";

// Update Res 97 to fulfilled as requested so Magnolia's active reserved birds is strictly 3,000 for House 1
$conn->exec("UPDATE reservations SET status = 'fulfilled', accepted_quantity = quantity_birds, actual_pickup_date = CURDATE() WHERE reservation_id = 97");
echo "[OK] Updated Res 97 to fulfilled.\n";
