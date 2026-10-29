<?php
require_once __DIR__ . '/../poultrychat/database.php';
$h4 = $conn->query("SELECT * FROM houses WHERE house_id = 4")->fetch(PDO::FETCH_ASSOC);
print_r($h4);
$b56 = $conn->query("SELECT * FROM batches WHERE batch_id = 56")->fetch(PDO::FETCH_ASSOC);
print_r($b56);
$r97 = $conn->query("SELECT * FROM reservations WHERE reservation_id = 97")->fetch(PDO::FETCH_ASSOC);
print_r($r97);
