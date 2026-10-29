<?php
$conn = new PDO('mysql:host=localhost;dbname=poultry_db', 'root', '');

echo "=== 1. DATABASE VERSION ===\n";
echo "SELECT VERSION(): " . $conn->query("SELECT VERSION()")->fetchColumn() . "\n\n";

echo "=== 2. LIVE EXPENSES DATA BREAKDOWN ===\n";
$stmt = $conn->query("SELECT category, COUNT(*) as cnt FROM expenses GROUP BY category");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cat = $r['category'] === null ? '[NULL]' : var_export($r['category'], true);
    echo "Category: {$cat} | Count: {$r['cnt']}\n";
}

echo "\n=== 3. LIVE ACTIVITIES DATA BREAKDOWN ===\n";
$stmt = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $t = $r['type'] === null ? '[NULL]' : var_export($r['type'], true);
    echo "Type: {$t} | Count: {$r['cnt']}\n";
}

echo "\n=== 4. EXPENSES COLUMN DEFINITION ===\n";
$col = $conn->query("SHOW COLUMNS FROM expenses LIKE 'category'")->fetch(PDO::FETCH_ASSOC);
echo "expenses.category Type: {$col['Type']} | Null: {$col['Null']} | Default: " . var_export($col['Default'], true) . "\n";

echo "\n=== 5. ACTIVITIES COLUMN DEFINITION ===\n";
$col2 = $conn->query("SHOW COLUMNS FROM activities LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
echo "activities.type Type: {$col2['Type']} | Null: {$col2['Null']} | Default: " . var_export($col2['Default'], true) . "\n";
