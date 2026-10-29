<?php
require_once __DIR__ . '/../poultrychat/database.php';
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "=== ALL TABLES ===" . PHP_EOL;
print_r($tables);

foreach ($tables as $t) {
    if (strpos($t, 'res') !== false || strpos($t, 'pay') !== false || strpos($t, 'order') !== false || strpos($t, 'batch') !== false) {
        echo "--- Table: $t ---" . PHP_EOL;
        $cols = $conn->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "  " . $c['Field'] . " (" . $c['Type'] . ")" . PHP_EOL;
        }
    }
}
