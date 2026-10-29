<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "DESCRIBE feed_consumption:\n";
foreach ($p->query("DESCRIBE feed_consumption") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nFEED INVENTORY ITEMS:\n";
$feeds = $p->query("SELECT inventory_id, item_name, category, quantity, unit, unit_cost FROM inventory WHERE category IN ('feed', 'limestone')")->fetchAll(PDO::FETCH_ASSOC);
print_r($feeds);

echo "\nFEED CONSUMPTION RECORDS (sample 5):\n";
$records = $p->query("SELECT * FROM feed_consumption ORDER BY consumption_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($records);
