<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$houses = $conn->query("SELECT * FROM houses WHERE house_name IN ('House 1', 'House 2', 'House 6')")->fetchAll(PDO::FETCH_ASSOC);
print_r($houses);

$res = $conn->query("SELECT * FROM reservations WHERE reservation_code IN ('RES-20261013-001', 'RES-20261016-001')")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
