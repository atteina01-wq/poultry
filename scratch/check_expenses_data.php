<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SELECT category, COUNT(*) as count, SUM(amount) as total FROM expenses GROUP BY category");
echo "Existing Expenses Data Breakdown:\n";
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    echo "Category: {$row['category']} | Count: {$row['count']} | Total: {$row['total']}\n";
}

$resCol = $conn->query("SHOW COLUMNS FROM expenses LIKE 'category'");
$col = $resCol->fetch(PDO::FETCH_ASSOC);
echo "\nCurrent Column Type: {$col['Type']}\n";
