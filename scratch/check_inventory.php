<?php
require_once __DIR__ . '/../src/autoload.php';
use Poultry\Core\Database;
$db = Database::getInstance()->getConnection();
$rows = $db->query('SELECT inventory_id, item_name, category, quantity, unit FROM inventory')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
