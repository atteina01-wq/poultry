<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

echo "=== CLEANING DATA FOR HOUSE 1, HOUSE 3, HOUSE 6 ===\n";

// 1. Delete active batches for House 1, House 3, House 6
$batchesToDelete = [58, 59, 61];
$delB = $conn->prepare("DELETE FROM batches WHERE batch_id = ?");
foreach ($batchesToDelete as $bId) {
    $delB->execute([$bId]);
    echo "Deleted batch $bId\n";
}

// Update archived batch 40 house_pen so it doesn't associate with House 1
$conn->exec("UPDATE batches SET house_pen = 'Archived - House 1' WHERE batch_id = 40");
echo "Updated batch 40 house_pen to Archived\n";

// 2. Delete reservations for House 1, 3, 6
$resToDelete = [52, 61, 68, 104, 106];
$delR = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
foreach ($resToDelete as $rId) {
    $delR->execute([$rId]);
    echo "Deleted reservation $rId\n";
}

// 3. Delete notifications tied to these reservations
$conn->exec("DELETE FROM notifications WHERE entity_type = 'reservations' AND entity_id IN (" . implode(',', $resToDelete) . ")");
echo "Cleaned up notifications\n";

// 4. Reset House 1, House 3, House 6
$stmtUpdH = $conn->prepare("
    UPDATE houses 
    SET status = 'available', 
        current_batch_id = NULL, 
        vacant_date = CURDATE(),
        notes = 'Sanitized, disinfected, and certified ready for chick intake.' 
    WHERE house_id IN (1, 3, 6)
");
$stmtUpdH->execute();
echo "Reset House 1, 3, 6 to available & vacant\n";

echo "=== VERIFYING HOUSE 1, 3, 6 AFTER CLEANUP ===\n";
$houses = $conn->query("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses WHERE house_id IN (1, 3, 6)")->fetchAll(PDO::FETCH_ASSOC);
print_r($houses);

$res = $conn->query("SELECT reservation_id, reservation_number, house_id, batch_id FROM reservations WHERE house_id IN (1, 3, 6)")->fetchAll(PDO::FETCH_ASSOC);
echo "Remaining reservations for House 1, 3, 6 (should be empty):\n";
print_r($res);

$batches = $conn->query("SELECT batch_id, batch_code, status, house_pen FROM batches WHERE house_pen IN ('House 1', 'House 3', 'House 6')")->fetchAll(PDO::FETCH_ASSOC);
echo "Remaining batches in House 1, 3, 6 (should be empty):\n";
print_r($batches);
