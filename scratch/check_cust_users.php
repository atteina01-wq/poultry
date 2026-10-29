<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== CUSTOMERS ===\n";
$custs = $conn->query("SELECT customer_id, customer_code, name, phone FROM customers")->fetchAll(PDO::FETCH_ASSOC);
print_r($custs);

echo "=== USERS ===\n";
$users = $conn->query("SELECT user_id, username, role, customer_id FROM users")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
