<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SHOW CREATE TABLE activities");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
