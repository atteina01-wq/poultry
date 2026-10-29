<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SELECT batch_id, batch_code, status, house_pen, arrival_date FROM batches");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) {
    echo "ID: {$r['batch_id']} | Code: {$r['batch_code']} | Status: {$r['status']} | House: {$r['house_pen']} | Arrival: {$r['arrival_date']}\n";
}
echo "\n--- HOUSES ---\n";
$stmtH = $conn->query("SELECT house_id, house_name, status, current_batch_id FROM houses");
foreach($stmtH->fetchAll(PDO::FETCH_ASSOC) as $h) {
    echo "House ID: {$h['house_id']} | Name: {$h['house_name']} | Status: {$h['status']} | Batch ID: {$h['current_batch_id']}\n";
}
