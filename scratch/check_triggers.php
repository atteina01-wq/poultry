<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../poultrychat/database.php';

echo "=== CHECKING TRIGGERS ===\n";
$triggers = $conn->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
echo "Trigger count: " . count($triggers) . "\n";
foreach ($triggers as $t) {
    echo "Trigger: " . $t['Trigger'] . " on " . $t['Table'] . "\n";
    echo "Event: " . $t['Event'] . " Timing: " . $t['Timing'] . "\n";
    echo "Statement: " . $t['Statement'] . "\n---\n";
}

echo "=== TEST RECORD MORTALITY STEP BY STEP ===\n";
require __DIR__ . '/../poultrychat/helpers.php';
$stmt = $conn->query("SELECT batch_id, current_quantity FROM batches WHERE status = 'active' AND current_quantity > 0 LIMIT 1");
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if ($batch) {
    $bid = (int)$batch['batch_id'];
    echo "Testing on batch #{$bid} (qty: {$batch['current_quantity']})\n";
    $ok = record_mortality($conn, $bid, -1, 'Diagnostic test', date('Y-m-d'), 0, 1);
    if ($ok) {
        echo "SUCCESSFUL RECORD MORTALITY!\n";
        // Clean up test record
        $lastMortId = $conn->query("SELECT MAX(mortality_id) FROM mortality_records")->fetchColumn();
        if ($lastMortId) {
            $conn->exec("DELETE FROM mortality_records WHERE mortality_id = {$lastMortId}");
            $conn->exec("UPDATE batches SET current_quantity = current_quantity + 1 WHERE batch_id = {$bid}");
            echo "Cleaned up test mortality record #{$lastMortId}\n";
        }
    } else {
        echo "FAILED: " . ($_SESSION['last_mortality_error'] ?? 'no error') . "\n";
    }
} else {
    echo "No active batch with quantity > 0\n";
}
