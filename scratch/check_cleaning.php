<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM cleaning_records");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
