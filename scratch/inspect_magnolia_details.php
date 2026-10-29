<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== CUSTOMERS SCHEMA ===\n";
$stmt = $conn->query("DESCRIBE customers");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . "\n";
}

echo "=== CUSTOMERS WITH MAGNOLIA ===\n";
$stmt = $conn->query("SELECT * FROM customers WHERE name LIKE '%Magnolia%' OR customer_code LIKE '%MAG%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== BATCHES SCHEMA ===\n";
$stmt = $conn->query("DESCRIBE batches");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . "\n";
}

echo "\n=== BATCHES WITH MAGNOLIA ===\n";
$stmt = $conn->query("SELECT * FROM batches WHERE supplier LIKE '%Magnolia%' OR batch_code LIKE '%MAG%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== USERS WITH MAGNOLIA ===\n";
$stmt = $conn->query("SELECT user_id, username, full_name, role, customer_id FROM users WHERE username LIKE '%Magnolia%' OR full_name LIKE '%Magnolia%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
