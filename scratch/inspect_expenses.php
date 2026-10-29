<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("DESCRIBE expenses");
echo "COLUMNS IN expenses:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
echo "\nROWS IN expenses:\n";
$rows = $conn->query("SELECT * FROM expenses")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r) . "\n";
}
