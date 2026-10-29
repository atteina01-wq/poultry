<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "DESCRIBE notifications:\n";
foreach ($p->query("DESCRIBE notifications") as $r) {
    echo sprintf("%-25s %-20s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Default'] ?? 'NULL');
}
