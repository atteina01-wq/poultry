<?php
require_once __DIR__ . '/../poultrychat/database.php';

try {
    $conn->exec("ALTER TABLE batches ADD COLUMN IF NOT EXISTS production_date DATE NULL AFTER arrival_date");
    $conn->exec("UPDATE batches SET production_date = arrival_date WHERE production_date IS NULL");
    echo "SUCCESS: production_date column added and backfilled from arrival_date.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$cols = $conn->query("SHOW COLUMNS FROM batches")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
