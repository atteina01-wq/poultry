<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
$rows = $p->query("SELECT * FROM health_records")->fetchAll(PDO::FETCH_ASSOC);
echo "health_records count: " . count($rows) . "\n";
print_r($rows);
