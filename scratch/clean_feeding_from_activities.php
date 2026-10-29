<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== REMOVING FEEDING DATA FROM ACTIVITIES TABLE ===\n\n";

$countBefore = $conn->query("SELECT COUNT(*) FROM activities WHERE type = 'Feeding'")->fetchColumn();
echo "Feeding records in activities before deletion: $countBefore\n";

if ($countBefore > 0) {
    $conn->exec("DELETE FROM activities WHERE type = 'Feeding'");
    echo "Deleted $countBefore Feeding records from activities.\n";
}

$countAfter = $conn->query("SELECT COUNT(*) FROM activities WHERE type = 'Feeding'")->fetchColumn();
echo "Feeding records in activities after deletion: $countAfter\n";

echo "\nRemaining activities by type:\n";
$remaining = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);
print_r($remaining);
