<?php
require_once __DIR__ . '/../poultrychat/database.php';

$logs = $conn->query("SELECT * FROM audit_logs WHERE record_id IN (79, 80)")->fetchAll(PDO::FETCH_ASSOC);
print_r($logs);
