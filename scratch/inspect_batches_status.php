<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$stmt = $conn->query("SELECT batch_id, batch_code, house_pen, status, current_quantity, initial_quantity, arrival_date FROM batches");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['batch_id']} | Code: {$r['batch_code']} | House: {$r['house_pen']} | Status: {$r['status']} | Live: {$r['current_quantity']}/{$r['initial_quantity']} | Arrival: {$r['arrival_date']}\n";
}
