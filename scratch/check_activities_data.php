<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT activity_id, type, item_name, quantity, date, status FROM activities");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
