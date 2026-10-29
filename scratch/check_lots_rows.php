<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
$lots = $p->query("SELECT * FROM inventory_lots")->fetchAll(PDO::FETCH_ASSOC);
echo "inventory_lots count: " . count($lots) . "\n";
print_r($lots);
