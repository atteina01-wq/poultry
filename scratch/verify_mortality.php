<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../poultrychat/database.php';
require __DIR__ . '/../poultrychat/helpers.php';

echo "1. Testing direct record_mortality with 7 arguments (as in mortality.php):\n";
$stmt = $conn->query("SELECT batch_id, current_quantity FROM batches WHERE status = 'active' AND current_quantity > 10 LIMIT 1");
$batch = $stmt->fetch(PDO::FETCH_ASSOC);
$bid = (int)$batch['batch_id'];

$ok1 = record_mortality($conn, $bid, -1, 'Direct test', date('Y-m-d'), 100.50, 1);
echo "  Direct test result: " . ($ok1 ? "SUCCESS" : "FAILED: " . ($_SESSION['last_mortality_error'] ?? '')) . "\n";
if ($ok1) {
    $id = $conn->query("SELECT MAX(mortality_id) FROM mortality_records")->fetchColumn();
    $conn->exec("DELETE FROM expenses WHERE source_type = 'mortality' AND source_id = $id");
    $conn->exec("DELETE FROM mortality_records WHERE mortality_id = $id");
    $conn->exec("UPDATE batches SET current_quantity = current_quantity + 1 WHERE batch_id = $bid");
}

echo "2. Testing nested transaction call with 6 arguments (as in activities.php):\n";
$conn->beginTransaction();
$ok2 = record_mortality($conn, $bid, -1, 'Nested test', date('Y-m-d'), 1);
echo "  Nested test result: " . ($ok2 ? "SUCCESS" : "FAILED: " . ($_SESSION['last_mortality_error'] ?? '')) . "\n";
if ($ok2) {
    $conn->commit();
    $id = $conn->query("SELECT MAX(mortality_id) FROM mortality_records")->fetchColumn();
    $conn->exec("DELETE FROM expenses WHERE source_type = 'mortality' AND source_id = $id");
    $conn->exec("DELETE FROM mortality_records WHERE mortality_id = $id");
    $conn->exec("UPDATE batches SET current_quantity = current_quantity + 1 WHERE batch_id = $bid");
} else {
    $conn->rollBack();
}

echo "All tests complete.\n";
