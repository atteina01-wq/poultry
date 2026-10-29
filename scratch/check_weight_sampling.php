<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';

$stmt = $conn->query("SELECT log_id, action, table_name, record_id, created_at FROM audit_logs WHERE table_name = 'activities' AND record_id IN (168, 169, 170, 189)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
