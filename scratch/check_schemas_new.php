<?php
require_once __DIR__ . '/../poultrychat/database.php';

foreach (['notifications', 'users', 'customers', 'houses', 'reservations'] as $t) {
    echo "=== $t ===\n";
    foreach ($conn->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo "{$c['Field']} ({$c['Type']})\n";
    }
}
