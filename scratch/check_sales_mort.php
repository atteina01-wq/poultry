<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
echo "--- SALES ---\n";
$sales = $p->query("SELECT * FROM sales")->fetchAll(PDO::FETCH_ASSOC);
print_r($sales);

echo "--- MORTALITY RECORDS ---\n";
$mort = $p->query("SELECT * FROM mortality_records")->fetchAll(PDO::FETCH_ASSOC);
print_r($mort);
