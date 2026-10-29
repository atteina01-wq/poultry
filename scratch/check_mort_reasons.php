<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM mortality_records");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['mortality_id']} | Date: {$r['date']} | Qty: {$r['quantity_change']} | Reason: '{$r['reason']}'\n";
}
