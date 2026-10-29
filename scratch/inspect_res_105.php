<?php
require_once __DIR__ . '/../poultrychat/database.php';
$r = $conn->query("SELECT * FROM reservations WHERE reservation_id IN (97, 104, 105)")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);
