<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SHOW DATABASES");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
