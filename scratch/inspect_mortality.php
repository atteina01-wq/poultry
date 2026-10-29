<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("DESCRIBE mortality_records");
echo "COLUMNS IN mortality_records:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
echo "\nROWS IN mortality_records:\n";
$rows = $conn->query("SELECT * FROM mortality_records")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r) . "\n";
}
