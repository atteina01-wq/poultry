<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM customers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
