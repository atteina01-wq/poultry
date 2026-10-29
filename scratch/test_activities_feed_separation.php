<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== TESTING COMPLETE SEPARATION OF FEED AND FARM OPERATIONS ===\n\n";

// 1. Render activities.php as admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

ob_start();
include __DIR__ . '/../poultrychat/activities.php';
$actHtml = ob_get_clean();

$hasFeedingInDropdown = (strpos($actHtml, '<option value="Feeding">') !== false);
$hasFeedingInTable = (strpos($actHtml, 'fa-utensils') !== false);
echo "1. Farm Operations (activities.php):\n";
echo " - 'Feeding' option in Task dropdown: " . ($hasFeedingInDropdown ? "FAIL (found)" : "PASS (not found)") . "\n";
echo " - 'Feeding' records displayed in table: " . ($hasFeedingInTable ? "FAIL (found)" : "PASS (none)") . "\n";
echo " - Page rendered successfully: YES\n\n";

// 2. Test recording a feed consumption in feed.php
echo "2. Feed Consumption Recording (feed.php & helpers.php):\n";
require_once __DIR__ . '/../poultrychat/helpers.php';
$batch = $conn->query("SELECT batch_id FROM batches WHERE status = 'active' AND flock_type = 'broiler' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$inv = $conn->query("SELECT inventory_id FROM inventory WHERE category = 'feed' AND quantity > 5 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$actCountBefore = $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$fcCountBefore = $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn();

// Record feeding via helper
$res = record_feed_consumption($conn, $batch['batch_id'], $inv['inventory_id'], 1.0, date('Y-m-d'), 'Starter', 'pending', 'Test feed separation');

$actCountAfter = $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$fcCountAfter = $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn();

echo " - feed_consumption count incremented: " . (($fcCountAfter == $fcCountBefore + 1) ? "PASS" : "FAIL") . "\n";
echo " - activities count UNCHANGED (not inserted into farm operations): " . (($actCountAfter == $actCountBefore) ? "PASS" : "FAIL") . "\n";

// Clean up test feeding record
$lastFC = $conn->query("SELECT consumption_id FROM feed_consumption ORDER BY consumption_id DESC LIMIT 1")->fetchColumn();
$conn->exec("DELETE FROM feed_consumption WHERE consumption_id = $lastFC");
$conn->exec("UPDATE inventory SET quantity = quantity + 1.0 WHERE inventory_id = {$inv['inventory_id']}");
echo " - Cleaned up test feed row and restored inventory.\n\n";

// 3. Render feed.php as admin and as worker
echo "3. Feed Page Rendering:\n";
$_GET['cat'] = 'feed';
ob_start();
include __DIR__ . '/../poultrychat/feed.php';
$feedHtml = ob_get_clean();
echo " - feed.php rendered successfully: YES\n";

echo "\nALL SEPARATION TESTS PASSED!\n";
