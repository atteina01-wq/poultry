<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "Checking feed_consumption for batch 48, 50:\n";
$f = $conn->query("SELECT * FROM feed_consumption WHERE batch_id IN (48, 50)")->fetchAll(PDO::FETCH_ASSOC);
print_r($f);

echo "\nChecking all feed_consumption from 2026-10-13:\n";
$fToday = $conn->query("SELECT fc.*, b.batch_code FROM feed_consumption fc LEFT JOIN batches b ON fc.batch_id = b.batch_id WHERE fc.date = '2026-10-13'")->fetchAll(PDO::FETCH_ASSOC);
print_r($fToday);

echo "\nChecking all activities for batch 48, 50:\n";
$act = $conn->query("SELECT * FROM activities WHERE batch_id IN (48, 50)")->fetchAll(PDO::FETCH_ASSOC);
print_r($act);

echo "\nChecking all audit_logs mentioning BR-20261013-674 or BR-20261013-974:\n";
$aud = $conn->query("SELECT * FROM audit_logs WHERE new_values LIKE '%BR-20261013-674%' OR new_values LIKE '%BR-20261013-974%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($aud);
