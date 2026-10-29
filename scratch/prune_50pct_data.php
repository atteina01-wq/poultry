<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== EXECUTING 50% DATA PRUNING ===\n\n";

$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

// 1. Audit Logs (464 -> 232)
$conn->exec("DELETE FROM audit_logs WHERE log_id IN (SELECT id FROM (SELECT log_id AS id FROM audit_logs ORDER BY log_id ASC LIMIT 232) AS t)");
$countAudit = $conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
echo "audit_logs count after 50% removal: $countAudit\n";

// 2. Reservations (52 -> 26)
$conn->exec("DELETE FROM reservations WHERE reservation_id IN (SELECT id FROM (SELECT reservation_id AS id FROM reservations ORDER BY reservation_id ASC LIMIT 26) AS t)");
$countRes = $conn->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
echo "reservations count after 50% removal: $countRes\n";

// 3. Activities (32 -> 16)
$conn->exec("DELETE FROM activities WHERE activity_id IN (SELECT id FROM (SELECT activity_id AS id FROM activities ORDER BY activity_id ASC LIMIT 16) AS t)");
$countAct = $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn();
echo "activities count after 50% removal: $countAct\n";

// 4. Feed Consumption (26 -> 13)
$conn->exec("DELETE FROM feed_consumption WHERE consumption_id IN (SELECT id FROM (SELECT consumption_id AS id FROM feed_consumption ORDER BY consumption_id ASC LIMIT 13) AS t)");
$countFeed = $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn();
echo "feed_consumption count after 50% removal: $countFeed\n";

// 5. Expenses (22 -> 11)
$conn->exec("DELETE FROM expenses WHERE expense_id IN (SELECT id FROM (SELECT expense_id AS id FROM expenses ORDER BY expense_id ASC LIMIT 11) AS t)");
$countExp = $conn->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
echo "expenses count after 50% removal: $countExp\n";

// 6. Health Records (21 -> 11)
$conn->exec("DELETE FROM health_records WHERE health_id IN (SELECT id FROM (SELECT health_id AS id FROM health_records ORDER BY health_id ASC LIMIT 10) AS t)");
$countHealth = $conn->query("SELECT COUNT(*) FROM health_records")->fetchColumn();
echo "health_records count after 50% removal: $countHealth\n";

// 7. Mortality Records (14 -> 7)
$conn->exec("DELETE FROM mortality_records WHERE mortality_id IN (SELECT id FROM (SELECT mortality_id AS id FROM mortality_records ORDER BY mortality_id ASC LIMIT 7) AS t)");
$countMort = $conn->query("SELECT COUNT(*) FROM mortality_records")->fetchColumn();
echo "mortality_records count after 50% removal: $countMort\n";

// 8. Cleaning Records (7 -> 3)
$conn->exec("DELETE FROM cleaning_records WHERE cleaning_id IN (SELECT id FROM (SELECT cleaning_id AS id FROM cleaning_records ORDER BY cleaning_id ASC LIMIT 4) AS t)");
$countClean = $conn->query("SELECT COUNT(*) FROM cleaning_records")->fetchColumn();
echo "cleaning_records count after 50% removal: $countClean\n";

// 9. Batches (14 -> 7, remove the 7 old/test/harvested ones)
$pruneBatchIds = [36, 5, 7, 32, 34, 39, 4];
$inStr = implode(',', $pruneBatchIds);
$conn->exec("DELETE FROM batches WHERE batch_id IN ($inStr)");
$countBatches = $conn->query("SELECT COUNT(*) FROM batches")->fetchColumn();
echo "batches count after 50% removal: $countBatches\n";

$conn->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n50% of the data has been successfully removed!\n";
