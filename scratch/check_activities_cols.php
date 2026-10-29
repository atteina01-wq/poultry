<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
header('Content-Type: text/plain');

$cols = $conn->query("DESCRIBE activities")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

$sample = $conn->query("SELECT activity_id, type, status, user_id FROM activities ORDER BY activity_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($sample);
