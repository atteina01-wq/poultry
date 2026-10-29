<?php
require __DIR__ . '/../poultrychat/database.php';

$tables = ['mortality_records', 'expenses', 'audit_logs', 'batches', 'activities'];
foreach ($tables as $t) {
    echo "=== COLUMNS OF $t ===\n";
    $cols = $conn->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
    }
}
