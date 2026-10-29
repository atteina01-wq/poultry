<?php
require_once __DIR__ . '/../poultrychat/database.php';

// Clean test rows from health_records
$stmt = $conn->query("SELECT health_id, medicine_name FROM health_records WHERE medicine_name LIKE 'Test Vitamin%' OR medicine_name LIKE 'Vaccine Gumboro%'");
$testRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($testRows) . " synthetic test health_records rows.\n";

foreach ($testRows as $r) {
    $conn->exec("DELETE FROM health_records WHERE health_id = {$r['health_id']}");
    $conn->exec("DELETE FROM audit_logs WHERE table_name = 'health_records' AND record_id = {$r['health_id']}");
}

// Clean test activities
$conn->exec("DELETE FROM activities WHERE item_name LIKE 'Test Vitamin%' OR item_name LIKE 'Vaccine Gumboro%'");

echo "Test cleanup complete.\n";
