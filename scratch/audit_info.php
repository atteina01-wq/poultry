<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== TABLES ===\n";
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n=== BATCHES COLUMNS ===\n";
$cols = $conn->query("DESCRIBE batches")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== NOTIFICATIONS COLUMNS ===\n";
$cols = $conn->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== CHECK FOR RECEIPT OR HISTORY TABLES ===\n";
foreach ($tables as $t) {
    if (strpos($t, 'receipt') !== false || strpos($t, 'history') !== false || strpos($t, 'reservation') !== false || strpos($t, 'perm') !== false || strpos($t, 'role') !== false) {
        echo "Found related table: $t\n";
    }
}
