<?php
require_once __DIR__ . '/../poultrychat/database.php';
$cols = $conn->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
