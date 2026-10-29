<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

$batchIds = [58, 59, 61];
$resIds = [104, 106];

echo "Checking tables referencing batch_ids: " . implode(',', $batchIds) . "\n";

// List all foreign keys or tables in database
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $tbl) {
    $cols = $conn->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('batch_id', $cols)) {
        $count = $conn->query("SELECT COUNT(*) FROM `$tbl` WHERE batch_id IN (" . implode(',', $batchIds) . ")")->fetchColumn();
        if ($count > 0) {
            echo "Table `$tbl` has $count rows with batch_id\n";
        }
    }
    if (in_array('reservation_id', $cols)) {
        $count = $conn->query("SELECT COUNT(*) FROM `$tbl` WHERE reservation_id IN (" . implode(',', $resIds) . ")")->fetchColumn();
        if ($count > 0) {
            echo "Table `$tbl` has $count rows with reservation_id\n";
        }
    }
}
