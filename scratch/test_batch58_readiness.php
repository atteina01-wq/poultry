<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/helpers.php';

$b = $conn->query("SELECT * FROM batches WHERE batch_id = 58")->fetch(PDO::FETCH_ASSOC);
echo "=== BATCH 58 ===\n";
print_r($b);

$r = $conn->query("SELECT * FROM reservations WHERE reservation_id = 104")->fetch(PDO::FETCH_ASSOC);
echo "=== RESERVATION 104 ===\n";
print_r($r);

echo "=== READINESS CALL 1 (batch alone): ===\n";
$read1 = check_release_readiness($conn, 58);
print_r($read1);

echo "=== READINESS CALL 2 (with reservation target): ===\n";
$read2 = check_release_readiness($conn, 58, $r['target_weight_per_bird'], $r['pickup_date']);
print_r($read2);
