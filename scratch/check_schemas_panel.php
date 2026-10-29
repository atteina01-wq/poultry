<?php
require __DIR__ . '/../poultrychat/database.php';
echo "--- MORTALITY_RECORDS ---\n";
foreach ($conn->query("DESCRIBE mortality_records")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
echo "\n--- RESERVATIONS ---\n";
foreach ($conn->query("DESCRIBE reservations")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
echo "\n--- USERS ---\n";
foreach ($conn->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}
