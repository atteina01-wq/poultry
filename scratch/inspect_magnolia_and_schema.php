<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== TABLES ===\n";
$stmt = $conn->query("SHOW TABLES");
while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $r[0] . "\n";
}

echo "\n=== RESERVATIONS COLUMNS ===\n";
$stmt = $conn->query("DESCRIBE reservations");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n=== CHECKING MAGNOLIA OCCURRENCES ===\n";
$tables = ['customers', 'reservations', 'batches', 'users', 'activities', 'audit_logs', 'houses'];
foreach ($tables as $t) {
    try {
        $cols = $conn->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
        $whereClauses = [];
        foreach ($cols as $col) {
            $whereClauses[] = "`$col` LIKE '%Magnolia%'";
        }
        $sql = "SELECT COUNT(*) FROM `$t` WHERE " . implode(' OR ', $whereClauses);
        $cnt = $conn->query($sql)->fetchColumn();
        echo "Table $t has $cnt records matching 'Magnolia'\n";
    } catch (Exception $e) {
        echo "Table $t error: " . $e->getMessage() . "\n";
    }
}
