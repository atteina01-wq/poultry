<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM inventory_lots");
$lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "TOTAL LOTS: " . count($lots) . "\n";
foreach ($lots as $l) {
    echo json_encode($l) . "\n";
}
