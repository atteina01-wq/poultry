<?php
require __DIR__ . '/../poultrychat/database.php';

echo "=== 1. Checking rows with empty/none activity type ===\n";
$stmt = $conn->query("SELECT activity_id, type, item_name, date, remarks FROM activities WHERE type IS NULL OR type = '' OR type = 'None' OR TRIM(type) = ''");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " rows to delete.\n";
$ids = array_column($rows, 'activity_id');

if (!empty($ids)) {
    $inClause = implode(',', array_map('intval', $ids));
    
    // Delete from audit_logs for these activities if present
    $conn->exec("DELETE FROM audit_logs WHERE table_name = 'activities' AND record_id IN ($inClause)");
    
    // Delete the empty/none activity records
    $deleted = $conn->exec("DELETE FROM activities WHERE activity_id IN ($inClause)");
    echo "Deleted {$deleted} activity record(s) from activities table.\n";
}

echo "\n=== 2. Upgrading activities.type column to VARCHAR(50) to prevent empty truncations ===\n";
$conn->exec("ALTER TABLE activities MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'General'");
echo "Upgraded activities.type column schema successfully.\n";

echo "\n=== 3. Verification of remaining activity types ===\n";
$stmt2 = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type");
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "Type: '" . $r['type'] . "' -> " . $r['cnt'] . " record(s)\n";
}

$stmtCheck = $conn->query("SELECT COUNT(*) FROM activities WHERE type IS NULL OR type = '' OR type = 'None' OR TRIM(type) = ''");
$noneCount = (int)$stmtCheck->fetchColumn();
echo "\nRemaining None/empty activity type records: {$noneCount}\n";
