<?php
require_once __DIR__ . '/../poultrychat/database.php';
$col = $conn->query("SHOW COLUMNS FROM expenses LIKE 'category'")->fetch(PDO::FETCH_ASSOC);
echo "expenses.category: " . $col['Type'] . PHP_EOL;

$col2 = $conn->query("SHOW COLUMNS FROM activities LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
echo "activities.type: " . $col2['Type'] . PHP_EOL;
