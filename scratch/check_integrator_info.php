<?php
require_once __DIR__ . '/../poultrychat/database.php';

$stmt = $conn->query("SELECT user_id, username, role, full_name, customer_id FROM users WHERE role = 'integrator'");
echo "Integrator users:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    echo "ID: {$u['user_id']} | User: {$u['username']} | Role: {$u['role']} | FullName: {$u['full_name']} | CustId: {$u['customer_id']}\n";
}

$stmt2 = $conn->query("SELECT customer_id, name, customer_code FROM customers WHERE name LIKE '%Integrator%' OR name LIKE '%CrestLine%' OR name LIKE '%San Miguel%'");
echo "\nIntegrator customers:\n";
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "ID: {$c['customer_id']} | Name: {$c['name']} | Code: {$c['customer_code']}\n";
}
