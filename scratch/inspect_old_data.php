<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== ACTIVE BATCHES & HOUSES ===\n";
$batches = $conn->query("SELECT batch_id, batch_code, status, house_pen FROM batches")->fetchAll(PDO::FETCH_ASSOC);
print_r($batches);

echo "=== HEALTH RECORDS ===\n";
$hr = $conn->query("SELECT health_id, batch_id, date, medicine_name, status FROM health_records")->fetchAll(PDO::FETCH_ASSOC);
print_r($hr);

echo "=== FEED CONSUMPTION ===\n";
$fc = $conn->query("SELECT consumption_id, batch_id, date, quantity_used FROM feed_consumption")->fetchAll(PDO::FETCH_ASSOC);
print_r($fc);

echo "=== ACTIVITIES ===\n";
$act = $conn->query("SELECT activity_id, batch_id, type, item_name, date, status FROM activities")->fetchAll(PDO::FETCH_ASSOC);
print_r($act);
