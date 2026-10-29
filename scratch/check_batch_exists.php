<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$res = $conn->query("SELECT batch_id FROM batches WHERE batch_id IN (4, 5, 6, 31, 32, 37, 38, 40, 41, 46)")->fetchAll(PDO::FETCH_COLUMN);
echo "Batches that exist in DB: " . implode(', ', $res) . "\n";
