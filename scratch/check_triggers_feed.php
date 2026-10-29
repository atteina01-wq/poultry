<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SHOW TRIGGERS WHERE `Table` IN ('feed_consumption', 'health_records')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
