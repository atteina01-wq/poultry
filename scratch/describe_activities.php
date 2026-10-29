<?php
require __DIR__ . '/../poultrychat/database.php';

echo "--- ACTIVITIES TABLE SCHEMA ---\n";
foreach ($conn->query("DESCRIBE activities") as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
