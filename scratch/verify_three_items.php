<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "========================================================================\n";
echo "1. REPORT SERVICE METHODS INSPECTION\n";
echo "========================================================================\n";

$lines = file(__DIR__ . '/../src/Services/ReportService.php');
foreach ($lines as $num => $line) {
    if (preg_match('/function\s+get\w+/', $line, $matches)) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}

echo "\n========================================================================\n";
echo "2. BLANK ACTIVITY TYPE ROWS INSPECTION\n";
echo "========================================================================\n";

$stmt = $conn->query("SELECT * FROM activities WHERE type = '' OR type IS NULL");
$blankActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($blankActivities)) {
    echo "No rows found with blank/null type in activities table.\n";
} else {
    echo "Found " . count($blankActivities) . " row(s) with blank type:\n\n";
    foreach ($blankActivities as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n========================================================================\n";
echo "3. EXPENSES COUNT CONSISTENCY CHECK\n";
echo "========================================================================\n";

$totalCount = $conn->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
echo "SELECT COUNT(*) FROM expenses;\n";
echo "Total Rows: " . $totalCount . "\n\n";

echo "SELECT category, COUNT(*) FROM expenses GROUP BY category;\n";
$stmtExp = $conn->query("SELECT category, COUNT(*) as cnt FROM expenses GROUP BY category");
$sumOfGroups = 0;
while ($r = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
    $cat = $r['category'] === null ? '[NULL]' : var_export($r['category'], true);
    echo "Category: {$cat} | Count: {$r['cnt']}\n";
    $sumOfGroups += (int)$r['cnt'];
}
echo "\nSum of grouped counts: " . $sumOfGroups . "\n";
echo "Consistency Check: " . ($sumOfGroups == $totalCount ? "CONSISTENT (Sum matches Total)" : "INCONSISTENT") . "\n";
