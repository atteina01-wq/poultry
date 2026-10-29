<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "DESCRIBE inventory:\n";
foreach ($p->query("DESCRIBE inventory") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nDESCRIBE inventory_lots:\n";
foreach ($p->query("DESCRIBE inventory_lots") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nALL INVENTORY ROWS:\n";
$inv = $p->query("SELECT * FROM inventory")->fetchAll(PDO::FETCH_ASSOC);
print_r($inv);
