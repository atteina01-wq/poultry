<?php
$backupFile = 'c:/xampp/htdocs/poultry/database/backup/backup_before_50pct_prune_20261004_234310.sql';
$content = file_get_contents($backupFile);

if (preg_match('/INSERT INTO `activities` VALUES (.*?);/s', $content, $m)) {
    echo "=== ACTIVITIES IN BACKUP ===\n";
    $rows = explode("),\n(", trim($m[1], "();"));
    foreach ($rows as $r) {
        echo "Row: ($r)\n";
    }
}
