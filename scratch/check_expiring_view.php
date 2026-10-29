<?php
$p = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');
$res = $p->query("SHOW CREATE VIEW v_expiring_lots")->fetch(PDO::FETCH_ASSOC);
echo $res['Create View'] . "\n";
