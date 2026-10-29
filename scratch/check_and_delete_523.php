<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== CHECKING AND REMOVING BATCH BR-20261013-523 (ID 49) ===\n\n";

$batch = $conn->query("SELECT * FROM batches WHERE batch_code = 'BR-20261013-523' OR batch_id = 49")->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    echo "Batch BR-20261013-523 not found or already deleted.\n";
    exit;
}

$id = $batch['batch_id'];
echo "Found Batch: {$batch['batch_code']} (ID: $id, House: {$batch['house_pen']}, Supplier: {$batch['supplier']})\n\n";

try {
    $conn->beginTransaction();

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
        $count = $conn->query("SELECT COUNT(*) FROM `$t` WHERE batch_id = $id")->fetchColumn();
        if ($count > 0) {
            $conn->exec("DELETE FROM `$t` WHERE batch_id = $id");
            echo "Deleted $count rows from `$t` for batch $id.\n";
        } else {
            echo "No rows in `$t` for batch $id.\n";
        }
    }

    // Check reservations
    try {
        $resCount = $conn->query("SELECT COUNT(*) FROM reservations WHERE batch_id = $id")->fetchColumn();
        if ($resCount > 0) {
            $conn->exec("UPDATE reservations SET batch_id = NULL WHERE batch_id = $id");
            echo "Unlinked $resCount reservation(s) from batch $id.\n";
        }
    } catch (Exception $e) {}

    // Delete the batch itself
    $conn->exec("DELETE FROM batches WHERE batch_id = $id");
    echo "Deleted batch BR-20261013-523 from `batches` table.\n";

    // Reset House 1
    $conn->exec("UPDATE houses SET status = 'available', current_batch_id = NULL WHERE house_name = 'House 1' OR current_batch_id = $id");
    echo "Reset House 1 to status 'available'.\n";

    // Audit log
    $adminUser = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: 1;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmtAudit = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
        VALUES (?, 'delete', 'batches', ?, ?, ?, NOW())
    ");
    $stmtAudit->execute([
        $adminUser,
        $id,
        json_encode(['action' => 'delete_batch', 'batch_code' => 'BR-20261013-523', 'reason' => 'User request cleanup']),
        $ip
    ]);
    echo "Audit log recorded.\n";

    $conn->commit();
    echo "\n=== BATCH BR-20261013-523 AND ALL CONNECTED DATA SUCCESSFULLY REMOVED ===\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
