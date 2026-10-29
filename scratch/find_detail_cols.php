<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%detail%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
