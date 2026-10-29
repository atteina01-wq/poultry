<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== ACTIVITIES (First 5) ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM activities LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== AUDIT LOGS (First 5) ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM audit_logs ORDER BY log_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "=== TASKS (First 5) ===" . PHP_EOL;
$res = $conn->query("SELECT * FROM tasks ORDER BY task_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
