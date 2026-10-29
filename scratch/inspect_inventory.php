<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("DESCRIBE inventory");
echo "COLUMNS IN inventory:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
echo "\nROWS IN inventory:\n";
$rows = $conn->query("SELECT * FROM inventory")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r) . "\n";
}
