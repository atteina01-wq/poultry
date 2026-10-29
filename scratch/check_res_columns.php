<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "COLUMNS IN reservations:\n";
foreach ($p->query("DESCRIBE reservations") as $r) {
    echo sprintf("%-25s %-20s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Default'] ?? 'NULL');
}

echo "\nCOLUMNS IN reservation_items:\n";
foreach ($p->query("DESCRIBE reservation_items") as $r) {
    echo sprintf("%-25s %-20s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Default'] ?? 'NULL');
}
