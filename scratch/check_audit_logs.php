<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== AUDIT LOGS FOR RESERVATIONS OR BATCHES OR HOUSES ===\n";
try {
    $stmt = $conn->query("SELECT * FROM audit_logs ORDER BY log_id DESC LIMIT 30");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "Log #{$row['log_id']}: {$row['action']} on {$row['table_name']} (ID: {$row['record_id']}) by User {$row['user_id']} at {$row['created_at']} | Details: {$row['details']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
