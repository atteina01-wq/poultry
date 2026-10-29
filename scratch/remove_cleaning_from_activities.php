<?php
require_once __DIR__ . '/../poultrychat/database.php';
$cnt = $conn->exec("DELETE FROM activities WHERE type = 'Cleaning'");
echo "Deleted $cnt cleaning records from activities table.\n";
