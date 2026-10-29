<?php
require_once __DIR__ . '/../poultrychat/database.php';

$backupDir = __DIR__ . '/../database/backup';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupFile = $backupDir . '/backup_before_50pct_prune_' . date('Ymd_His') . '.sql';

$tables = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

$fp = fopen($backupFile, 'w');
fwrite($fp, "-- Full Backup of poultry_db created before 50% data removal\n");
fwrite($fp, "-- Timestamp: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

foreach ($tables as $t) {
    fwrite($fp, "-- Table structure for table `$t`\n");
    fwrite($fp, "DROP TABLE IF EXISTS `$t`;\n");
    $createStmt = $conn->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    fwrite($fp, $createStmt[1] . ";\n\n");

    $rows = $conn->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        fwrite($fp, "-- Dumping data for table `$t`\n");
        fwrite($fp, "INSERT INTO `$t` VALUES ");
        $rowSql = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $conn->quote($val);
                }
            }
            $rowSql[] = "(" . implode(', ', $vals) . ")";
        }
        fwrite($fp, implode(",\n", $rowSql) . ";\n\n");
    }
}
fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($fp);

echo "Backup created successfully at: " . realpath($backupFile) . "\n";
