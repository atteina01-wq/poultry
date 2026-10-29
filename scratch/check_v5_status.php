<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SHOW COLUMNS FROM expenses LIKE 'category'");
if ($res) {
    $row = $res->fetch(PDO::FETCH_ASSOC);
    echo "Expenses 'category' column definition in DB:\n";
    echo $row['Type'] . "\n";
} else {
    echo "Query failed or table not found.\n";
}

$res2 = $conn->query("SHOW TABLES LIKE 'activities'");
$row2 = $res2 ? $res2->fetch(PDO::FETCH_NUM) : null;
echo "Activities table exists: " . ($row2 ? "YES" : "NO") . "\n";
