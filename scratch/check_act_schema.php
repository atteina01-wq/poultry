<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "DESCRIBE activities:\n";
foreach ($p->query("DESCRIBE activities") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nDESCRIBE tasks:\n";
foreach ($p->query("DESCRIBE tasks") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}

echo "\nDESCRIBE audit_logs:\n";
foreach ($p->query("DESCRIBE audit_logs") as $r) {
    echo sprintf("%-25s %-25s %-5s %-5s %s\n", $r['Field'], $r['Type'], $r['Null'], $r['Key'], $r['Default'] ?? 'NULL');
}
