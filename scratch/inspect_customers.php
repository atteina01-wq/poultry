<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM customers");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "CUSTOMERS IN SYSTEM:\n";
foreach ($customers as $c) {
    echo "ID: {$c['customer_id']} | Code: {$c['customer_code']} | Name: {$c['name']} | Address: '{$c['address']}'\n";
}
