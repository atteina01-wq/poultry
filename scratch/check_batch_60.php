<?php
require_once __DIR__ . '/../poultrychat/database.php';
$b = $conn->query('SELECT * FROM batches WHERE batch_id = 60')->fetch(PDO::FETCH_ASSOC);
echo "Batch 60:\n";
print_r($b);
$h = $conn->query('SELECT * FROM houses WHERE house_id = 3')->fetch(PDO::FETCH_ASSOC);
echo "House 3:\n";
print_r($h);
