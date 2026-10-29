<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$types = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);
echo "Activities by type:\n";
print_r($types);

$feedings = $conn->query("SELECT * FROM activities WHERE type = 'Feeding'")->fetchAll(PDO::FETCH_ASSOC);
echo "\nFeeding activities count: " . count($feedings) . "\n";
print_r($feedings);
