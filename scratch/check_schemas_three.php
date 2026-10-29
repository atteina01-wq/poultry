<?php
require __DIR__ . '/../poultrychat/database.php';
foreach(['reservations', 'batches', 'houses'] as $t) {
    echo "--- $t ---\n";
    foreach($conn->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo "{$c['Field']} ({$c['Type']})\n";
    }
}
