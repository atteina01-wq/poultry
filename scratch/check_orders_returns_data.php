<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== ORDERS STATUSES & COUNTS ===" . PHP_EOL;
$res = $conn->query("SELECT status, COUNT(*) as cnt, SUM(total_amount) as total FROM orders GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== ORDER RETURNS ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM order_returns LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== ORDER RETURN ITEMS ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM order_return_items LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== EXPENSES CATEGORIES ===" . PHP_EOL;
$res = $conn->query("SELECT category, COUNT(*) as cnt, SUM(amount) as total FROM expenses GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== SALES TABLE ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM sales LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== BATCHES ===" . PHP_EOL;
$res = $conn->query("SELECT batch_id, batch_code, initial_quantity, current_quantity, status, arrival_date FROM batches")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

