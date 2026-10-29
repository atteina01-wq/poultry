<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
foreach($p->query("SHOW COLUMNS FROM inventory WHERE Field='category'") as $r) {
    echo "Category type: " . $r['Type'] . "\n";
}
