<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SELECT inventory_id, item_name, category FROM inventory WHERE inventory_id = 4");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
