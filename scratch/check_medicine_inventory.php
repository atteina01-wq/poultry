<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
$meds = $p->query("SELECT * FROM inventory WHERE category = 'medicine'")->fetchAll(PDO::FETCH_ASSOC);
echo "Medicine inventory items: " . count($meds) . "\n";
print_r($meds);
