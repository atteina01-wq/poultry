<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== ALL AUDIT LOGS FOR FEED CONSUMPTION ===\n";
$stmt = $conn->query("SELECT log_id, record_id, action, new_values, created_at FROM audit_logs WHERE table_name = 'feed_consumption' ORDER BY log_id ASC");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Log: {$r['log_id']} | Rec: {$r['record_id']} | Action: {$r['action']} | Values: {$r['new_values']} | Time: {$r['created_at']}\n";
}

echo "\n=== ALL AUDIT LOGS MENTIONING 69 OR 85 OR 86 ===\n";
$stmt2 = $conn->query("SELECT log_id, table_name, record_id, action, new_values FROM audit_logs WHERE record_id IN (69, 85, 86) OR new_values LIKE '%69%' OR new_values LIKE '%85%' OR new_values LIKE '%86%'");
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "Log: {$r['log_id']} | Table: {$r['table_name']} | Rec: {$r['record_id']} | Values: {$r['new_values']}\n";
}
