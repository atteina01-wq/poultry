<?php
require __DIR__ . '/../poultrychat/database.php';

echo "--- DISTINCT ACTIVITY TYPES IN activities TABLE ---\n";
$stmt = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Type: '" . var_export($row['type'], true) . "' | Count: " . $row['cnt'] . "\n";
}

echo "\n--- CHECK FOR NULL, EMPTY, OR 'None' ACTIVITY TYPES ---\n";
$stmt2 = $conn->query("SELECT * FROM activities WHERE type IS NULL OR type = '' OR type = 'None' OR type LIKE '%none%'");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Count of None/empty/null type rows: " . count($rows2) . "\n";
foreach ($rows2 as $r) {
    echo "ID: {$r['activity_id']} | Type: '{$r['type']}' | Item: '{$r['item_name']}' | Date: '{$r['date']}' | Remarks: '{$r['remarks']}'\n";
}

echo "\n--- SAMPLE ALL ACTIVITIES (First 20) ---\n";
$stmt3 = $conn->query("SELECT activity_id, batch_id, user_id, type, item_name, quantity, date, remarks FROM activities ORDER BY activity_id DESC LIMIT 20");
while ($r = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['activity_id']} | Type: '{$r['type']}' | Item: '{$r['item_name']}' | Qty: '{$r['quantity']}' | Date: '{$r['date']}' | Remarks: '{$r['remarks']}'\n";
}
