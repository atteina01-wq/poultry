<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== EXECUTING DELETION OF BR-20261013-674 AND BR-20261013-974 ===\n\n";

try {
    $conn->beginTransaction();

    $targetBatchIds = [48, 50]; // 48: BR-20261013-974, 50: BR-20261013-674
    $in = implode(',', $targetBatchIds);

    // 1. Delete child records for batches 48 and 50 across all tables
    $tables = [
        'feed_consumption',
        'activities',
        'health_records',
        'mortality_records',
        'expenses',
        'cleaning_records',
        'tasks',
        'waste_records'
    ];

    foreach ($tables as $t) {
        $count = $conn->query("SELECT COUNT(*) FROM `$t` WHERE batch_id IN ($in)")->fetchColumn();
        if ($count > 0) {
            $conn->exec("DELETE FROM `$t` WHERE batch_id IN ($in)");
            echo "Deleted $count records from `$t` for batches 48 & 50.\n";
        } else {
            echo "No records in `$t` for batches 48 & 50.\n";
        }
    }

    // 2. Delete the batches themselves
    $conn->exec("DELETE FROM batches WHERE batch_id IN ($in)");
    echo "Deleted batch records 48 (BR-20261013-974) and 50 (BR-20261013-674) from `batches`.\n";

    // 3. Reset House 2 and House 6 status to 'available'
    $conn->exec("UPDATE houses SET status = 'available', current_batch_id = NULL WHERE house_name IN ('House 2', 'House 6')");
    echo "Reset House 2 and House 6 to status 'available'.\n";

    // 4. Restore deducted inventory as requested
    // ChicksFeeds (ID 2): +15.00 bags
    $conn->exec("UPDATE inventory SET quantity = quantity + 15.00 WHERE inventory_id = 2");
    // Vitamin C (ID 28): +50.00 litters
    $conn->exec("UPDATE inventory SET quantity = quantity + 50.00 WHERE inventory_id = 28");
    echo "Restored 15.00 Bags of ChicksFeeds (ID 2) and 50.00 litters of Vitamin C (ID 28) back to stock.\n";

    // 5. Remove the test consumption/treatment records for ID 89 and ID 69 so inventory matches records
    $conn->exec("DELETE FROM feed_consumption WHERE consumption_id = 89");
    $conn->exec("DELETE FROM health_records WHERE health_id = 69");
    $conn->exec("DELETE FROM activities WHERE activity_id = 197");
    $conn->exec("DELETE FROM cleaning_records WHERE cleaning_id = 10");
    echo "Removed test feed consumption #89, health record #69, cleaning activity #197, and cleaning record #10.\n";

    // 6. Log in audit_logs
    $adminUser = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: 1;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmtAudit = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
        VALUES (?, 'delete', 'batches', ?, ?, ?, NOW())
    ");
    $stmtAudit->execute([
        $adminUser,
        48,
        json_encode(['action' => 'delete_batch', 'batch_code' => 'BR-20261013-974', 'reason' => 'User request cleanup']),
        $ip
    ]);
    $stmtAudit->execute([
        $adminUser,
        50,
        json_encode(['action' => 'delete_batch', 'batch_code' => 'BR-20261013-674', 'reason' => 'User request cleanup']),
        $ip
    ]);
    echo "Audit logs recorded.\n";

    $conn->commit();
    echo "\n=== DELETION & RESTORATION COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
