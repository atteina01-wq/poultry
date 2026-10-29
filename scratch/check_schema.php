<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "TABLES IN poultry_db:\n";
foreach ($p->query("SHOW TABLES") as $row) {
    echo " - " . $row[0] . "\n";
}

echo "\nDESCRIBE batches:\n";
foreach ($p->query("DESCRIBE batches") as $r) {
    echo sprintf("%-25s %-20s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nDESCRIBE mortality_records:\n";
foreach ($p->query("DESCRIBE mortality_records") as $r) {
    echo sprintf("%-25s %-20s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}
