<?php
echo "=== TESTING SYNTAX ===\n";
$files = [
    'poultrychat/activities.php',
    'poultrychat/reservations.php',
    'poultrychat/batch.php'
];
foreach ($files as $f) {
    $out = shell_exec("php -l " . escapeshellarg(__DIR__ . '/../' . $f));
    echo "$f: $out";
}

echo "\n=== CHECKING ACTIVITIES TABLE IN DATABASE ===\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';

$stmt = $conn->query("SELECT activity_id, batch_id, type, item_name, quantity, status FROM activities WHERE type LIKE '%Weight%' OR item_name LIKE '%Sample%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Weight Sampling records in DB: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo " - ID: {$r['activity_id']} | Type: {$r['type']} | Item: {$r['item_name']}\n";
}

echo "\n=== RENDER ACTIVITIES FOR ADMIN ===\n";
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

ob_start();
include __DIR__ . '/../poultrychat/activities.php';
$adminHtml = ob_get_clean();

$hasWeightOption = (stripos($adminHtml, 'value="Weight Sampling"') !== false);
echo "Admin page contains 'Weight Sampling' dropdown option: " . ($hasWeightOption ? 'YES (ERROR)' : 'NO (PASSED)') . "\n";

$hasWeightRecord = (stripos($adminHtml, 'Weight Sampling') !== false);
echo "Admin page mentions 'Weight Sampling': " . ($hasWeightRecord ? 'YES' : 'NO') . "\n";

echo "\n=== RENDER ACTIVITIES FOR WORKER ===\n";
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'worker';
$_SESSION['username'] = 'worker1';

ob_start();
include __DIR__ . '/../poultrychat/activities.php';
$workerHtml = ob_get_clean();

$workerHasForm = (stripos($workerHtml, 'activityRecordFormCollapse') !== false);
echo "Worker page has Record Activity Form: " . ($workerHasForm ? 'YES' : 'NO (PASSED - Worker cannot record)') . "\n";

$workerHasAction = (stripos($workerHtml, 'Worker Action Mode') !== false);
echo "Worker page has Worker Action Mode: " . ($workerHasAction ? 'YES (PASSED)' : 'NO') . "\n";

echo "\nALL CHECKS COMPLETE!\n";
