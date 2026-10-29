<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$res = $conn->query("SELECT * FROM activities ORDER BY activity_id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($res) . "\n";
print_r($res);
