<?php
$backupFile = 'c:/xampp/htdocs/poultry/database/backup/backup_before_50pct_prune_20261004_234310.sql';
$content = file_get_contents($backupFile);

// Check all feed_consumption rows in backup
if (preg_match('/INSERT INTO `feed_consumption` VALUES (.*?);/s', $content, $m)) {
    echo "Found feed_consumption dump:\n";
    $rows = explode("),\n(", trim($m[1], "();"));
    foreach ($rows as $r) {
        $parts = explode(", ", $r);
        // parts[1] is batch_id
        $bid = trim($parts[1], "'");
        if (in_array((int)$bid, [6, 31, 35, 37, 38, 46])) {
            echo "BATCH MATCH: $r\n";
        }
    }
}

// Check all health_records rows in backup
if (preg_match('/INSERT INTO `health_records` VALUES (.*?);/s', $content, $m)) {
    echo "\nFound health_records dump:\n";
    $rows = explode("),\n(", trim($m[1], "();"));
    foreach ($rows as $r) {
        $parts = explode(", ", $r);
        // parts[1] is batch_id
        $bid = trim($parts[1], "'");
        if (in_array((int)$bid, [6, 31, 35, 37, 38, 46])) {
            echo "BATCH MATCH: $r\n";
        }
    }
}
