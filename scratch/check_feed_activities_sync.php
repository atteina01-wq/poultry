<?php
require_once __DIR__ . '/../poultrychat/database.php';

header('Content-Type: text/plain');

echo "Feed Consumption count: " . $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn() . "\n";
echo "Activities (Feeding) count: " . $conn->query("SELECT COUNT(*) FROM activities WHERE type = 'Feeding'")->fetchColumn() . "\n";

$fc = $conn->query("SELECT * FROM feed_consumption ORDER BY consumption_id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
print_r($fc);
