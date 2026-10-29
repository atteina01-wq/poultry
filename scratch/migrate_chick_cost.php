<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';

try {
    $chk = $conn->query("SHOW COLUMNS FROM reservations LIKE 'chick_cost'")->fetch(PDO::FETCH_ASSOC);
    if (!$chk) {
        $conn->exec("ALTER TABLE reservations ADD COLUMN chick_cost DECIMAL(10,2) DEFAULT 38.00 AFTER unit_price");
        echo "ADDED chick_cost column to reservations table successfully.\n";
    } else {
        echo "chick_cost column already exists in reservations.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
