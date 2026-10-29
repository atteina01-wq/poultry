<?php
require __DIR__ . '/../poultrychat/database.php';

echo "Updating Pen A to House 5 in database...\n";

// 1. Update houses
$stmtH = $conn->prepare("UPDATE houses SET house_name = 'House 5' WHERE house_id = 5 OR house_name LIKE '%Pen A%'");
$stmtH->execute();
echo "Updated houses: " . $stmtH->rowCount() . " row(s)\n";

// 2. Update batches
$stmtB = $conn->prepare("UPDATE batches SET house_pen = 'House 5' WHERE house_pen LIKE '%Pen A%'");
$stmtB->execute();
echo "Updated batches: " . $stmtB->rowCount() . " row(s)\n";

// 3. Verify houses table
$stmt = $conn->query("SELECT house_id, house_name, capacity, status, notes FROM houses ORDER BY house_id ASC");
echo "\n--- CURRENT HOUSES LIST ---\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['house_id']} | Name: {$r['house_name']} | Capacity: {$r['capacity']} | Status: {$r['status']}\n";
}
