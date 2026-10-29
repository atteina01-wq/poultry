<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

$resIds = [52, 61, 68, 104, 106];
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $tbl) {
    $cols = $conn->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('reservation_id', $cols)) {
        $count = $conn->query("SELECT COUNT(*) FROM `$tbl` WHERE reservation_id IN (" . implode(',', $resIds) . ")")->fetchColumn();
        if ($count > 0) {
            echo "Table `$tbl` has $count rows with reservation_id in (" . implode(',', $resIds) . ")\n";
        }
    }
}
