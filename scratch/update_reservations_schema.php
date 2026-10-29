<?php
require 'c:\xampp\htdocs\poultry\poultrychat\database.php';

try {
    $conn->exec("ALTER TABLE reservations ADD COLUMN actual_weight_kg DECIMAL(10,2) NULL AFTER accepted_quantity");
    $conn->exec("ALTER TABLE reservations ADD COLUMN actual_price DECIMAL(14,2) NULL AFTER actual_weight_kg");
    $conn->exec("ALTER TABLE reservations ADD COLUMN receipt_number VARCHAR(100) NULL AFTER actual_price");

    $conn->exec("ALTER TABLE reservations DROP COLUMN unit_price");
    $conn->exec("ALTER TABLE reservations DROP COLUMN total_amount");
    $conn->exec("ALTER TABLE reservations DROP COLUMN advance_payment");

    echo "Table reservations updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
