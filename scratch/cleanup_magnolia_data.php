<?php
require_once __DIR__ . '/../poultrychat/database.php';

try {
    $conn->beginTransaction();

    echo "=== CLEANING UP MAGNOLIA DATA ===\n";

    // 1. Reset houses pointing to Magnolia batches
    $conn->exec("UPDATE houses SET current_batch_id = NULL, status = 'available' WHERE current_batch_id IN (51, 52, 53)");
    echo "Reset houses with Magnolia batches.\n";

    // 2. Delete reservations linked to Magnolia customers or batches
    $deletedRes = $conn->exec("DELETE FROM reservations WHERE customer_id IN (6, 11) OR batch_id IN (51, 52, 53) OR created_by = 8 OR notes LIKE '%Magnolia%'");
    echo "Deleted $deletedRes reservations.\n";

    // 3. Delete batches with supplier Magnolia
    $deletedBatches = $conn->exec("DELETE FROM batches WHERE batch_id IN (51, 52, 53) OR supplier LIKE '%Magnolia%' OR notes LIKE '%Magnolia%'");
    echo "Deleted $deletedBatches batches.\n";

    // 4. Delete users with Magnolia
    $deletedUsers = $conn->exec("DELETE FROM users WHERE user_id = 8 OR email LIKE '%magnolia%' OR customer_id IN (6, 11)");
    echo "Deleted $deletedUsers users.\n";

    // 5. Delete customers with Magnolia
    $deletedCustomers = $conn->exec("DELETE FROM customers WHERE customer_id IN (6, 11) OR name LIKE '%Magnolia%' OR customer_code = 'INT-MAG'");
    echo "Deleted $deletedCustomers customers.\n";

    // 6. Delete notifications mentioning Magnolia
    $deletedNotifs = $conn->exec("DELETE FROM notifications WHERE message LIKE '%Magnolia%'");
    echo "Deleted $deletedNotifs notifications.\n";

    // 7. Delete audit logs referencing Magnolia
    $deletedAudits = $conn->exec("DELETE FROM audit_logs WHERE user_id = 8 OR new_values LIKE '%Magnolia%' OR old_values LIKE '%Magnolia%'");
    echo "Deleted $deletedAudits audit logs.\n";

    $conn->commit();
    echo "=== MAGNOLIA CLEANUP COMPLETED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "Cleanup failed: " . $e->getMessage() . "\n";
}
